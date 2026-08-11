<?php

namespace Tests\Unit;

use App\Services\DailyDatabaseBackupService;
use App\Services\DatabaseBackup\CompressedDatabaseDumpRunner;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class DailyDatabaseBackupServiceTest extends TestCase
{
    private const DATABASE_NAME = 'project_abah';

    private string $backupRoot;

    private string $dumpBinary;

    protected function setUp(): void
    {
        parent::setUp();

        $this->backupRoot = storage_path(
            'framework/testing/daily-database-backup-'.str_replace('.', '', uniqid('', true))
        );

        File::ensureDirectoryExists($this->backupRoot);
        $this->dumpBinary = $this->backupRoot.DIRECTORY_SEPARATOR.'test-mysqldump.exe';
        File::put($this->dumpBinary, 'test executable placeholder');
        Cache::flush();

        Config::set('cache.default', 'array');
        Config::set('database.default', 'mysql');
        Config::set('database.connections.mysql', [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => self::DATABASE_NAME,
            'username' => 'backup-test-user',
            'password' => 'not-a-real-password',
            'unix_socket' => '',
            'charset' => 'utf8mb4',
        ]);
        Config::set('database_backup', [
            'enabled' => true,
            'directory' => $this->backupRoot,
            'retention_count' => 2,
            'compression_level' => 4,
            'min_free_space_bytes' => 0,
            'mysqldump_binary' => $this->dumpBinary,
            'folder_prefix' => 'backup project-abah',
            'timezone' => 'Asia/Jakarta',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        Cache::flush();

        $testingRoot = $this->normalizePath(storage_path('framework/testing')).'/';
        $backupRoot = $this->normalizePath($this->backupRoot);

        if (str_starts_with($backupRoot.'/', $testingRoot)
            && str_contains(basename($backupRoot), 'daily-database-backup-')) {
            File::deleteDirectory($this->backupRoot);
        }

        parent::tearDown();
    }

    public function test_backup_is_published_atomically_with_a_verified_manifest_and_matching_hashes(): void
    {
        $now = $this->date('2026-07-31 08:15:00');
        $sql = "-- MySQL dump\nCREATE TABLE `sample` (`id` int);\nINSERT INTO `sample` VALUES (1);\n";
        $runner = $this->successfulRunner($sql);

        $result = (new DailyDatabaseBackupService($runner))->backup($now);

        $folder = $this->managedFolder('2026-07-31');
        $gzipPath = $folder.DIRECTORY_SEPARATOR.'project_abah_31072026.sql.gz';
        $manifestPath = $folder.DIRECTORY_SEPARATOR.'manifest.json';

        $this->assertSame('completed', $result['status']);
        $this->assertDirectoryExists($folder);
        $this->assertFileExists($gzipPath);
        $this->assertFileExists($manifestPath);
        $this->assertSame($sql, $this->readGzip($gzipPath));

        $manifest = json_decode(
            (string) File::get($manifestPath),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $this->assertSame(1, $manifest['schema_version']);
        $this->assertSame('project-abah-daily-backup', $manifest['managed_by']);
        $this->assertSame('verified', $manifest['status']);
        $this->assertSame(self::DATABASE_NAME, $manifest['database']);
        $this->assertSame('2026-07-31', $manifest['backup_date']);
        $this->assertSame(basename($gzipPath), $manifest['backup_file']);
        $this->assertSame('gzip', $manifest['compression']);
        $this->assertSame(4, $manifest['compression_level']);
        $this->assertSame(filesize($gzipPath), $manifest['compressed_bytes']);
        $this->assertSame(strlen($sql), $manifest['uncompressed_bytes']);
        $this->assertSame(hash_file('sha256', $gzipPath), $manifest['sha256_compressed']);
        $this->assertSame(hash('sha256', $sql), $manifest['sha256_uncompressed']);
        $this->assertNotSame('', (string) $manifest['created_at']);
        $this->assertNotSame('', (string) $manifest['completed_at']);
        $this->assertSame(filesize($gzipPath), $result['compressed_bytes']);
        $this->assertSame(strlen($sql), $result['uncompressed_bytes']);
        $this->assertSame([], $result['deleted_backups']);

        $this->assertSame(
            ['manifest.json', 'project_abah_31072026.sql.gz'],
            $this->relativeFiles($folder)
        );
        $this->assertNoTemporaryArtifactsRemain();
    }

    public function test_retention_keeps_only_two_newest_valid_backups_and_preserves_unrelated_content(): void
    {
        $oldest = $this->createManagedBackup('2026-07-28');
        $old = $this->createManagedBackup('2026-07-29');
        $newestExisting = $this->createManagedBackup('2026-07-30');
        $legacy = $this->createLegacyBackup('2026-04-28');

        $unrelatedFolder = $this->backupRoot.DIRECTORY_SEPARATOR.'backup manual 27072026';
        $invalidManagedFolder = $this->backupRoot.DIRECTORY_SEPARATOR.'backup project-abah 01012020';
        $invalidLegacyFolder = $this->backupRoot.DIRECTORY_SEPARATOR.'31132020';
        $unrelatedFile = $this->backupRoot.DIRECTORY_SEPARATOR.'legacy-full-backup.sql.gz';
        File::ensureDirectoryExists($unrelatedFolder);
        File::put($unrelatedFolder.DIRECTORY_SEPARATOR.'keep.txt', 'keep');
        File::ensureDirectoryExists($invalidManagedFolder);
        File::put($invalidManagedFolder.DIRECTORY_SEPARATOR.'readme.txt', 'not a managed backup');
        File::ensureDirectoryExists($invalidLegacyFolder);
        File::put($invalidLegacyFolder.DIRECTORY_SEPARATOR.'invalid.sql.gz', 'not gzip');
        File::put($unrelatedFile, 'keep');

        $result = (new DailyDatabaseBackupService(
            $this->successfulRunner("-- MySQL dump\nCREATE TABLE `retention_test` (`id` int);\n")
        ))->backup($this->date('2026-07-31 08:30:00'));

        $today = $this->managedFolder('2026-07-31');

        $this->assertSame('completed', $result['status']);
        $this->assertDirectoryDoesNotExist($oldest);
        $this->assertDirectoryDoesNotExist($old);
        $this->assertDirectoryDoesNotExist($legacy);
        $this->assertDirectoryExists($newestExisting);
        $this->assertDirectoryExists($today);
        $this->assertDirectoryExists($unrelatedFolder);
        $this->assertDirectoryExists($invalidManagedFolder);
        $this->assertDirectoryExists($invalidLegacyFolder);
        $this->assertFileExists($unrelatedFile);
        $this->assertSame(
            [
                'backup project-abah 29072026',
                'backup project-abah 28072026',
                '28042026',
            ],
            array_values($result['deleted_backups'])
        );
        $this->assertNoTemporaryArtifactsRemain();
    }

    public function test_runner_failure_does_not_prune_existing_backups_and_removes_temporary_artifacts(): void
    {
        $existingFolders = [
            $this->createManagedBackup('2026-07-28'),
            $this->createManagedBackup('2026-07-29'),
            $this->createManagedBackup('2026-07-30'),
            $this->createLegacyBackup('2026-04-28'),
        ];

        $runner = Mockery::mock(CompressedDatabaseDumpRunner::class);
        $runner->shouldReceive('run')
            ->once()
            ->andReturnUsing(function (array $command, string $gzipPath, string $stderrPath): array {
                File::ensureDirectoryExists(dirname($gzipPath));
                File::put($gzipPath, 'partial gzip');
                File::put($stderrPath, 'simulated failure');

                throw new RuntimeException('Simulated dump failure.');
            });

        try {
            (new DailyDatabaseBackupService($runner))->backup($this->date('2026-07-31 08:45:00'));
            $this->fail('Backup failure should be propagated to the caller.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Simulated dump failure', $exception->getMessage());
        }

        foreach ($existingFolders as $existingFolder) {
            $this->assertDirectoryExists($existingFolder);
        }

        $this->assertDirectoryDoesNotExist($this->managedFolder('2026-07-31'));
        $this->assertNoTemporaryArtifactsRemain();
    }

    private function successfulRunner(string $sql): CompressedDatabaseDumpRunner
    {
        $runner = Mockery::mock(CompressedDatabaseDumpRunner::class);
        $runner->shouldReceive('run')
            ->once()
            ->andReturnUsing(function (
                array $command,
                string $gzipPath,
                string $stderrPath,
                int $compressionLevel
            ) use ($sql): array {
                $this->assertNotEmpty($command);
                $this->assertSame($this->dumpBinary, $command[0]);
                $this->assertSame(4, $compressionLevel);
                $this->assertStringStartsWith($this->normalizePath($this->backupRoot), $this->normalizePath($gzipPath));
                $this->assertNotSame($this->normalizePath($gzipPath), $this->normalizePath($stderrPath));

                File::ensureDirectoryExists(dirname($gzipPath));
                $gzip = gzopen($gzipPath, 'wb'.$compressionLevel);
                if (! is_resource($gzip)) {
                    throw new RuntimeException('Test fixture could not open gzip output.');
                }

                gzwrite($gzip, $sql);
                gzclose($gzip);
                File::put($stderrPath, '');

                return [
                    'uncompressed_bytes' => strlen($sql),
                    'uncompressed_sha256' => hash('sha256', $sql),
                    'sql_prefix' => substr($sql, 0, 65536),
                    'exit_code' => 0,
                ];
            });

        return $runner;
    }

    private function createManagedBackup(string $date): string
    {
        $dateValue = $this->date($date.' 01:00:00');
        $folder = $this->managedFolder($date);
        $backupFile = self::DATABASE_NAME.'_'.$dateValue->format('dmY').'.sql.gz';
        $gzipPath = $folder.DIRECTORY_SEPARATOR.$backupFile;
        $sql = "-- MySQL dump\nSELECT '".$date."';\n";

        File::ensureDirectoryExists($folder);
        $gzip = gzopen($gzipPath, 'wb4');
        if (! is_resource($gzip)) {
            throw new RuntimeException('Test fixture could not create a managed backup.');
        }

        gzwrite($gzip, $sql);
        gzclose($gzip);

        File::put($folder.DIRECTORY_SEPARATOR.'manifest.json', json_encode([
            'schema_version' => 1,
            'managed_by' => 'project-abah-daily-backup',
            'status' => 'verified',
            'database' => self::DATABASE_NAME,
            'backup_date' => $date,
            'created_at' => $dateValue->format(DATE_ATOM),
            'completed_at' => $dateValue->format(DATE_ATOM),
            'backup_file' => $backupFile,
            'compression' => 'gzip',
            'compression_level' => 4,
            'compressed_bytes' => filesize($gzipPath),
            'uncompressed_bytes' => strlen($sql),
            'sha256_compressed' => hash_file('sha256', $gzipPath),
            'sha256_uncompressed' => hash('sha256', $sql),
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

        return $folder;
    }

    private function createLegacyBackup(string $date): string
    {
        $dateValue = $this->date($date.' 01:00:00');
        $folder = $this->backupRoot.DIRECTORY_SEPARATOR.$dateValue->format('dmY');
        $gzipPath = $folder.DIRECTORY_SEPARATOR
            .'project_abah_full_'.$dateValue->format('Ymd_His').'.sql.gz';
        $sql = "-- MySQL dump\nCREATE TABLE `legacy_sample` (`id` int);\n";

        File::ensureDirectoryExists($folder);
        $gzip = gzopen($gzipPath, 'wb4');
        if (! is_resource($gzip)) {
            throw new RuntimeException('Test fixture could not create a legacy backup.');
        }

        gzwrite($gzip, $sql);
        gzclose($gzip);

        return $folder;
    }

    private function managedFolder(string $date): string
    {
        return $this->backupRoot
            .DIRECTORY_SEPARATOR
            .'backup project-abah '
            .$this->date($date.' 00:00:00')->format('dmY');
    }

    private function readGzip(string $path): string
    {
        $handle = gzopen($path, 'rb');
        if (! is_resource($handle)) {
            throw new RuntimeException('Test could not open generated gzip.');
        }

        $contents = '';
        while (! gzeof($handle)) {
            $chunk = gzread($handle, 8192);
            if ($chunk === false) {
                gzclose($handle);
                throw new RuntimeException('Test could not read generated gzip.');
            }
            $contents .= $chunk;
        }
        gzclose($handle);

        return $contents;
    }

    /**
     * @return array<int, string>
     */
    private function relativeFiles(string $folder): array
    {
        $files = array_map(
            static fn (\SplFileInfo $file): string => $file->getFilename(),
            File::files($folder)
        );
        sort($files);

        return $files;
    }

    private function assertNoTemporaryArtifactsRemain(): void
    {
        if (! is_dir($this->backupRoot)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $this->backupRoot,
                \FilesystemIterator::SKIP_DOTS
            )
        );

        foreach ($iterator as $entry) {
            $name = $entry->getFilename();
            $this->assertStringNotContainsString('.part', $name);
            $this->assertStringNotContainsString('.tmp', $name);
        }
    }

    private function date(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, new DateTimeZone('Asia/Jakarta'));
    }

    private function normalizePath(string $path): string
    {
        $normalized = rtrim(str_replace('\\', '/', $path), '/');

        return PHP_OS_FAMILY === 'Windows' ? strtolower($normalized) : $normalized;
    }
}
