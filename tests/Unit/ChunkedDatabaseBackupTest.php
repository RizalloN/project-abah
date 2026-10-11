<?php

namespace Tests\Unit;

use App\Services\DailyDatabaseBackupService;
use App\Services\DatabaseBackup\ChunkedBackupPlanner;
use App\Services\DatabaseBackup\ChunkedDatabaseBackupWriter;
use App\Services\DatabaseBackup\CompressedDatabaseDumpRunner;
use App\Services\DatabaseBackup\ParallelPartRunner;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ChunkedDatabaseBackupTest extends TestCase
{
    private string $backupRoot;

    /** @var array<int, string> */
    private array $dumpedKeys = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->backupRoot = storage_path('framework/testing/daily-database-backup-'.str_replace('.', '', uniqid('', true)));
        File::ensureDirectoryExists($this->backupRoot);
        $dumpBinary = $this->backupRoot.DIRECTORY_SEPARATOR.'test-mysqldump.exe';
        File::put($dumpBinary, 'test executable placeholder');

        Config::set('database.default', 'mysql');
        Config::set('database.connections.mysql', [
            'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 3306, 'database' => 'project_abah',
            'username' => 'backup-test-user', 'password' => 'not-a-real-password', 'unix_socket' => '', 'charset' => 'utf8mb4',
        ]);
        Config::set('database_backup', [
            'enabled' => true,
            'directory' => $this->backupRoot,
            'retention_count' => 1,
            'compression_level' => 4,
            'min_free_space_bytes' => 0,
            'mysqldump_binary' => $dumpBinary,
            'folder_prefix' => 'backup project-abah',
            'timezone' => 'Asia/Jakarta',
            'mode' => 'chunked',
            'workers' => 2,
            'max_reuse_days' => 7,
            'import_wait_minutes' => 0,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        if (str_contains(basename($this->backupRoot), 'daily-database-backup-')) {
            File::deleteDirectory($this->backupRoot);
        }

        parent::tearDown();
    }

    public function test_first_backup_dumps_every_part_and_publishes_a_verified_manifest(): void
    {
        $result = $this->service($this->plan(['p1' => 'a', 'p2' => 'b']))->backup($this->date('2026-07-30 23:00:00'));

        $this->assertSame('completed', $result['status']);
        $this->assertSame(['parts_total' => 5, 'parts_dumped' => 5, 'parts_reused' => 0], array_intersect_key(
            $result['stats'], array_flip(['parts_total', 'parts_dumped', 'parts_reused'])
        ));

        $manifest = $this->manifest('2026-07-30');
        $this->assertSame(2, $manifest['schema_version']);
        $this->assertSame('chunked', $manifest['layout']);
        $this->assertSame(['schema', 'data', 'data', 'data', 'post'], array_column($manifest['parts'], 'kind'));
        foreach ($manifest['parts'] as $part) {
            $path = $this->folder('2026-07-30').DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $part['file']);
            $this->assertSame(hash_file('sha256', $path), $part['sha256_compressed']);
            $this->assertFalse($part['reused']);
        }
        $this->assertSame(['data', 'manifest.json', 'post-data.sql.gz', 'schema.sql.gz'], $this->topLevel('2026-07-30'));
    }

    public function test_unchanged_periods_are_reused_and_changed_periods_dumped_again(): void
    {
        $this->service($this->plan(['p1' => 'a', 'p2' => 'b']))->backup($this->date('2026-07-30 23:00:00'));
        $previousP1 = $this->manifestPart('2026-07-30', 'data/big_table/p1');

        $this->dumpedKeys = [];
        $result = $this->service($this->plan(['p1' => 'a', 'p2' => 'changed']))->backup($this->date('2026-07-31 08:00:00'));

        $this->assertSame(1, $result['stats']['parts_reused']);
        $this->assertNotContains('data/big_table/p1', $this->dumpedKeys);
        $this->assertContains('data/big_table/p2', $this->dumpedKeys);
        $reused = $this->manifestPart('2026-07-31', 'data/big_table/p1');
        $this->assertTrue($reused['reused']);
        $this->assertSame($previousP1['sha256_compressed'], $reused['sha256_compressed']);
        $this->assertSame($previousP1['dumped_at'], $reused['dumped_at']);
        // Retention removed yesterday; the linked part must survive intact.
        $this->assertDirectoryDoesNotExist($this->folder('2026-07-30'));
        $this->assertSame(
            $reused['sha256_compressed'],
            hash_file('sha256', $this->folder('2026-07-31').DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'big_table'.DIRECTORY_SEPARATOR.'p1.sql.gz')
        );
    }

    public function test_parts_older_than_the_reuse_window_are_dumped_again(): void
    {
        $this->service($this->plan(['p1' => 'a']))->backup($this->date('2026-07-20 23:00:00'));

        $this->dumpedKeys = [];
        $this->service($this->plan(['p1' => 'a']))->backup($this->date('2026-07-28 23:00:00'));

        $this->assertContains('data/big_table/p1', $this->dumpedKeys);
        $this->assertFalse($this->manifestPart('2026-07-28', 'data/big_table/p1')['reused']);
    }

    public function test_worker_failure_publishes_nothing_and_keeps_the_previous_backup(): void
    {
        $this->service($this->plan(['p1' => 'a']))->backup($this->date('2026-07-30 23:00:00'));

        try {
            $this->service($this->plan(['p1' => 'changed']), fail: true)->backup($this->date('2026-07-31 23:00:00'));
            $this->fail('A failed worker must abort the backup.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Simulated worker failure', $exception->getMessage());
        }

        $this->assertDirectoryExists($this->folder('2026-07-30'));
        $this->assertDirectoryDoesNotExist($this->folder('2026-07-31'));
        $this->assertSame([], glob($this->backupRoot.DIRECTORY_SEPARATOR.'.backup-project-abah-*') ?: []);
    }

    public function test_corrupted_or_resized_parts_are_detected_before_restore(): void
    {
        $service = $this->service($this->plan(['p1' => 'a']));
        $service->backup($this->date('2026-07-30 23:00:00'));
        $part = $this->folder('2026-07-30').DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'big_table'.DIRECTORY_SEPARATOR.'p1.sql.gz';

        $backup = $service->resolveChunkedBackup();
        $original = (string) file_get_contents($part);
        file_put_contents($part, strrev($original));
        try {
            $service->verifyChunkedParts($backup);
            $this->fail('Same-size corruption must fail checksum verification.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Checksum part backup tidak cocok', $exception->getMessage());
        }

        file_put_contents($part, $original.'x');
        $this->expectException(RuntimeException::class);
        $service->resolveChunkedBackup('backup project-abah 30072026');
    }

    /**
     * @param  array<string, string>  $periods  period => identity
     * @return array<int, array<string, mixed>>
     */
    private function plan(array $periods): array
    {
        $plan = [['key' => 'schema', 'file' => 'schema.sql.gz', 'kind' => 'schema', 'table' => null]];
        foreach ($periods as $period => $identity) {
            $plan[] = [
                'key' => 'data/big_table/'.$period, 'file' => 'data/big_table/'.$period.'.sql.gz', 'kind' => 'data',
                'table' => 'big_table', 'period_column' => 'periode', 'period' => $period, 'where' => "`periode` = '{$period}'",
                'rows' => 10, 'schema_hash' => 'h', 'fingerprint' => hash('sha256', $period.$identity),
            ];
        }
        $plan[] = ['key' => 'data/small_table', 'file' => 'data/small_table.sql.gz', 'kind' => 'data', 'table' => 'small_table', 'schema_hash' => 'h', 'fingerprint' => null];
        $plan[] = ['key' => 'post-data', 'file' => 'post-data.sql.gz', 'kind' => 'post', 'table' => null];

        return $plan;
    }

    /** @param  array<int, array<string, mixed>>  $plan */
    private function service(array $plan, bool $fail = false): DailyDatabaseBackupService
    {
        $planner = Mockery::mock(ChunkedBackupPlanner::class);
        $planner->shouldReceive('plan')->andReturn($plan);

        $runner = Mockery::mock(ParallelPartRunner::class);
        $runner->shouldReceive('run')->andReturnUsing(function (string $command, array $specs) use ($fail): array {
            $this->assertSame('database:backup-part', $command);
            $results = [];
            foreach ($specs as $key => $specPath) {
                if ($fail) {
                    throw new RuntimeException('Worker '.$key.' gagal: Simulated worker failure');
                }
                $spec = json_decode((string) file_get_contents($specPath), true);
                $sql = "/*!40101 SET NAMES utf8mb4 */;\nINSERT INTO `t` VALUES ('{$key}');\n";
                $gzip = gzopen($spec['gzip_path'], 'wb4');
                gzwrite($gzip, $sql);
                gzclose($gzip);
                $this->dumpedKeys[] = $key;
                $results[$key] = [
                    'status' => 'ok', 'compressed_bytes' => filesize($spec['gzip_path']), 'uncompressed_bytes' => strlen($sql),
                    'sha256_compressed' => hash_file('sha256', $spec['gzip_path']), 'sha256_uncompressed' => hash('sha256', $sql), 'stable' => true,
                ];
            }

            return $results;
        });

        return new DailyDatabaseBackupService(
            Mockery::mock(CompressedDatabaseDumpRunner::class),
            new ChunkedDatabaseBackupWriter($planner, $runner)
        );
    }

    private function date(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, new DateTimeZone('Asia/Jakarta'));
    }

    private function folder(string $date): string
    {
        return $this->backupRoot.DIRECTORY_SEPARATOR.'backup project-abah '.(new DateTimeImmutable($date))->format('dmY');
    }

    /** @return array<string, mixed> */
    private function manifest(string $date): array
    {
        return json_decode((string) file_get_contents($this->folder($date).DIRECTORY_SEPARATOR.'manifest.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    /** @return array<string, mixed> */
    private function manifestPart(string $date, string $key): array
    {
        return collect($this->manifest($date)['parts'])->firstWhere('key', $key);
    }

    /** @return array<int, string> */
    private function topLevel(string $date): array
    {
        $entries = array_values(array_diff(scandir($this->folder($date)) ?: [], ['.', '..']));
        sort($entries);

        return $entries;
    }
}
