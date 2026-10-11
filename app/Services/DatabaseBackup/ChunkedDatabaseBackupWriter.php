<?php

namespace App\Services\DatabaseBackup;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Builds a chunked backup inside a temporary directory. Unchanged period
 * chunks of the previous verified backup are hard-linked (no copy, no extra
 * space); everything else is dumped by a bounded worker pool and verified.
 */
class ChunkedDatabaseBackupWriter
{
    public function __construct(
        private readonly ChunkedBackupPlanner $planner,
        private readonly ParallelPartRunner $runner
    ) {}

    /**
     * @param  array<int, string>  $dumpBaseCommand  mysqldump binary and connection options
     * @param  array{directory: string, manifest: array<string, mixed>}|null  $previous
     * @return array{parts: array<int, array<string, mixed>>, stats: array<string, int|float>}
     */
    public function write(
        string $temporaryDirectory,
        array $dumpBaseCommand,
        string $database,
        ?array $previous,
        DateTimeImmutable $now
    ): array {
        $plan = $this->planner->plan(
            $database,
            (array) config('database_backup.chunked_tables', []),
            (array) config('database_backup.schema_only_tables', []),
            (array) config('database_backup.schema_only_prefixes', [])
        );

        [$reuse, $dump] = $this->decide($plan, $previous, $now);

        $parts = [];
        foreach ($reuse as $part) {
            $parts[$part['key']] = $this->linkPreviousPart($part, $previous['directory'], $temporaryDirectory);
        }

        $workDirectory = $temporaryDirectory.DIRECTORY_SEPARATOR.'.work';
        if (! @mkdir($workDirectory, 0775, true) && ! is_dir($workDirectory)) {
            throw new RuntimeException('Folder kerja backup tidak dapat dibuat.');
        }

        $specs = [];
        $planned = [];
        foreach ($this->orderLargestFirst($dump, $database) as $index => $part) {
            $target = $temporaryDirectory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $part['file']);
            if (! is_dir(dirname($target)) && ! @mkdir(dirname($target), 0775, true) && ! is_dir(dirname($target))) {
                throw new RuntimeException('Folder part backup tidak dapat dibuat: '.$part['file']);
            }

            $specPath = $workDirectory.DIRECTORY_SEPARATOR.sprintf('part-%05d.json', $index);
            file_put_contents($specPath, json_encode([
                'command' => $this->partCommand($dumpBaseCommand, $database, $part),
                'gzip_path' => $target,
                'stderr_path' => $specPath.'.stderr',
                'compression_level' => min(9, max(1, (int) config('database_backup.compression_level', 4))),
                'result_path' => $specPath.'.result.json',
                'recheck' => isset($part['period_column']) ? [
                    'database' => $database,
                    'table' => $part['table'],
                    'period_column' => $part['period_column'],
                    'period' => $part['period'],
                    'fingerprint' => $part['fingerprint'],
                ] : null,
            ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $specs[$part['key']] = $specPath;
            $planned[$part['key']] = $part;
        }

        $dumpedAt = $now->format(DATE_ATOM);
        $results = $this->runner->run(
            'database:backup-part',
            $specs,
            (int) config('database_backup.workers', 3)
        );

        foreach ($results as $key => $result) {
            $part = $planned[$key];
            $parts[$key] = $this->describe($part) + [
                'compressed_bytes' => (int) $result['compressed_bytes'],
                'uncompressed_bytes' => (int) $result['uncompressed_bytes'],
                'sha256_compressed' => (string) $result['sha256_compressed'],
                'sha256_uncompressed' => (string) $result['sha256_uncompressed'],
                'stable' => (bool) $result['stable'],
                'reused' => false,
                'dumped_at' => $dumpedAt,
            ];
        }

        $this->removeTree($workDirectory);

        // Keep the planned order: schema, data, post-data.
        $ordered = [];
        foreach ($plan as $part) {
            if (! isset($parts[$part['key']])) {
                throw new RuntimeException('Part backup tidak lengkap: '.$part['key']);
            }
            $ordered[] = $parts[$part['key']];
        }

        return [
            'parts' => $ordered,
            'stats' => [
                'parts_total' => count($ordered),
                'parts_dumped' => count($dump),
                'parts_reused' => count($reuse),
                'dumped_uncompressed_bytes' => array_sum(array_map(
                    static fn (array $part): int => $part['reused'] ? 0 : (int) $part['uncompressed_bytes'],
                    $ordered
                )),
            ],
        ];
    }

    /**
     * Reuse a chunk only when its identity and schema are unchanged, the
     * previous dump was stable and its file is intact. Parts older than the
     * reuse window are dumped again, and the oldest ones are refreshed early
     * so roughly 1/window of the reusable volume is re-read every night.
     *
     * @param  array<int, array<string, mixed>>  $plan
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>}
     */
    private function decide(array $plan, ?array $previous, DateTimeImmutable $now): array
    {
        $previousParts = [];
        foreach ((array) ($previous['manifest']['parts'] ?? []) as $part) {
            if (is_array($part) && isset($part['key'])) {
                $previousParts[(string) $part['key']] = $part;
            }
        }

        $window = max(1, (int) config('database_backup.max_reuse_days', 7));
        $candidates = [];
        $dump = [];
        foreach ($plan as $part) {
            $old = $previousParts[$part['key']] ?? null;
            $reusable = $previous !== null
                && ($part['fingerprint'] ?? null) !== null
                && is_array($old)
                && hash_equals((string) $part['fingerprint'], (string) ($old['fingerprint'] ?? ''))
                && ($old['stable'] ?? false) === true
                && $this->previousFileIntact($previous['directory'], $old);

            if (! $reusable) {
                $dump[] = $part;

                continue;
            }

            $ageDays = ($now->getTimestamp() - strtotime((string) $old['dumped_at'])) / 86400;
            $candidates[] = ['part' => $part + ['previous' => $old], 'age' => $ageDays, 'bytes' => (int) $old['uncompressed_bytes']];
        }

        usort($candidates, static fn (array $a, array $b): int => $b['age'] <=> $a['age']);
        $budget = (int) ceil(array_sum(array_column($candidates, 'bytes')) / $window);
        $refreshed = 0;
        $reuse = [];
        foreach ($candidates as $candidate) {
            if ($candidate['age'] >= $window || ($refreshed < $budget && $candidate['age'] >= 1)) {
                $refreshed += $candidate['bytes'];
                unset($candidate['part']['previous']);
                $dump[] = $candidate['part'];

                continue;
            }
            $reuse[] = $candidate['part'];
        }

        return [$reuse, $dump];
    }

    /** @param  array<string, mixed>  $old */
    private function previousFileIntact(string $previousDirectory, array $old): bool
    {
        $path = $previousDirectory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, (string) ($old['file'] ?? ''));

        return is_file($path)
            && ! is_link($path)
            && (int) filesize($path) === (int) ($old['compressed_bytes'] ?? -1)
            && preg_match('/^[a-f0-9]{64}$/', (string) ($old['sha256_compressed'] ?? '')) === 1;
    }

    /** @param  array<string, mixed>  $part */
    private function linkPreviousPart(array $part, string $previousDirectory, string $temporaryDirectory): array
    {
        $old = $part['previous'];
        $source = $previousDirectory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, (string) $old['file']);
        $target = $temporaryDirectory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, (string) $part['file']);
        if (! is_dir(dirname($target)) && ! @mkdir(dirname($target), 0775, true) && ! is_dir(dirname($target))) {
            throw new RuntimeException('Folder part backup tidak dapat dibuat: '.$part['file']);
        }

        // A hard link shares the verified bytes; copying is the fallback when
        // the backup root cannot hold links (different volume or filesystem).
        if (! @link($source, $target) && ! @copy($source, $target)) {
            throw new RuntimeException('Part backup sebelumnya tidak dapat dipakai ulang: '.$part['file']);
        }
        if ((int) filesize($target) !== (int) $old['compressed_bytes']) {
            throw new RuntimeException('Ukuran part yang dipakai ulang tidak cocok: '.$part['file']);
        }

        return $this->describe($part) + [
            'compressed_bytes' => (int) $old['compressed_bytes'],
            'uncompressed_bytes' => (int) $old['uncompressed_bytes'],
            'sha256_compressed' => (string) $old['sha256_compressed'],
            'sha256_uncompressed' => (string) $old['sha256_uncompressed'],
            'stable' => true,
            'reused' => true,
            'dumped_at' => (string) $old['dumped_at'],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $parts
     * @return array<int, array<string, mixed>>
     */
    private function orderLargestFirst(array $parts, string $database): array
    {
        try {
            $tableBytes = collect(DB::select(
                'SELECT TABLE_NAME AS name, DATA_LENGTH AS bytes, TABLE_ROWS AS row_estimate
                 FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?',
                [$database]
            ))->keyBy('name');
        } catch (\Throwable) {
            // Ordering only shortens the run; keep the planned order without it.
            return array_values($parts);
        }

        usort($parts, static function (array $left, array $right) use ($tableBytes): int {
            $estimate = static function (array $part) use ($tableBytes): float {
                $table = $tableBytes->get((string) ($part['table'] ?? ''));
                if ($table === null) {
                    return 0.0;
                }
                if (! isset($part['rows'])) {
                    return (float) $table->bytes;
                }

                return (float) $table->bytes * (int) $part['rows'] / max(1, (int) $table->row_estimate);
            };

            return $estimate($right) <=> $estimate($left);
        });

        return array_values($parts);
    }

    /**
     * @param  array<int, string>  $base
     * @param  array<string, mixed>  $part
     * @return array<int, string>
     */
    private function partCommand(array $base, string $database, array $part): array
    {
        return match ($part['kind']) {
            // Triggers are applied after all data so restores never fire them.
            'schema' => [...$base, '--no-data', '--skip-triggers', $database],
            'post' => [...$base, '--no-data', '--no-create-info', '--triggers', '--routines', '--events', $database],
            default => array_values(array_filter([
                ...$base,
                '--no-create-info',
                '--skip-triggers',
                isset($part['where']) ? '--where='.$part['where'] : null,
                $database,
                (string) $part['table'],
            ])),
        };
    }

    /**
     * @param  array<string, mixed>  $part
     * @return array<string, mixed>
     */
    private function describe(array $part): array
    {
        return array_intersect_key($part, array_flip([
            'key', 'file', 'kind', 'table', 'period_column', 'period', 'rows', 'schema_hash', 'fingerprint',
        ]));
    }

    private function removeTree(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }
        foreach (new \FilesystemIterator($directory, \FilesystemIterator::SKIP_DOTS) as $item) {
            $item->isDir() && ! $item->isLink() ? $this->removeTree($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($directory);
    }
}
