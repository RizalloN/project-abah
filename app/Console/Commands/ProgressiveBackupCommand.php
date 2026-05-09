<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackupService;
use App\Support\DatabaseBackupStatusStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class ProgressiveBackupCommand extends Command
{
    protected $signature = 'db:backup-progressive {backupId}';
    protected $description = 'Perform a database backup with progress tracking in Cache';

    public function handle(DatabaseBackupService $backupService): void
    {
        set_time_limit(0);
        ini_set('memory_limit', '-1');

        $backupId = $this->argument('backupId');
        $config = $backupService->getDatabaseConfig();
        $database = $config['database'];
        $logPath = storage_path("logs/database-backup-{$backupId}.log");

        // Register shutdown handler so a killed PHP process still marks backup as failed
        register_shutdown_function(function () use ($backupId, $logPath): void {
            $status = DatabaseBackupStatusStore::get($backupId);
            if (is_array($status) && in_array($status['status'] ?? null, ['starting', 'processing'], true)) {
                $error = error_get_last();
                $msg = $error ? $error['message'] : 'Proses backup dihentikan secara tidak terduga.';
                DatabaseBackupStatusStore::put($backupId, [
                    'status' => 'failed',
                    'message' => $msg,
                    'updated_at' => now()->timestamp,
                ]);
                Log::error("ProgressiveBackupCommand [{$backupId}] terminated unexpectedly: {$msg}");
            }
        });

        try {
            $backupDirectory = storage_path('app/private/database_backups');
            if (!is_dir($backupDirectory)) {
                File::makeDirectory($backupDirectory, 0755, true);
            }

            $backupDirectory = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $backupDirectory);

            $baseName = sprintf(
                '%s_full_%s',
                preg_replace('/[^A-Za-z0-9_-]+/', '_', $database) ?: 'database',
                now()->format('Ymd_His')
            );

            $filename = $baseName . '.sql';
            $absolutePath = $backupDirectory . DIRECTORY_SEPARATOR . $filename;

            $this->putStatus($backupId, [
                'status' => 'processing',
                'progress_percent' => 2,
                'current_table_index' => 0,
                'total_tables' => 1,
                'current_table' => 'Full Database (Single-Pass)',
                'message' => 'Memulai backup database...',
                'updated_at' => now()->timestamp,
                'backup_file' => $absolutePath,
            ]);

            $this->performOptimizedBackup($backupService, $backupId, $absolutePath, $database, $logPath);

            if (is_file($absolutePath . '.gz')) {
                $absolutePath .= '.gz';
            }
            $actualFilename = basename($absolutePath);

            $this->putStatus($backupId, [
                'status' => 'completed',
                'progress_percent' => 100,
                'current_table_index' => 1,
                'total_tables' => 1,
                'message' => 'Backup database selesai.',
                'updated_at' => now()->timestamp,
                'file' => [
                    'name' => $actualFilename,
                    'relative_path' => 'private/database_backups/' . $actualFilename,
                    'download_url' => route('file-management.download', ['path' => 'private/database_backups/' . $actualFilename]),
                    'size' => is_file($absolutePath) ? filesize($absolutePath) : 0,
                    'size_human' => is_file($absolutePath) ? $this->formatBytes((int) filesize($absolutePath)) : '0 B',
                ],
            ]);

        } catch (\Throwable $e) {
            Log::error("Backup failed for {$backupId}: " . $e->getMessage());
            $this->putStatus($backupId, [
                'status' => 'failed',
                'message' => $e->getMessage(),
                'updated_at' => now()->timestamp,
            ]);

            if (isset($absolutePath) && is_file($absolutePath)) {
                @unlink($absolutePath);
            }
        }
    }

    private function performOptimizedBackup(
        DatabaseBackupService $backupService,
        string $backupId,
        string $outputPath,
        string $database,
        string $logPath
    ): void {
        // Estimate database size in bytes for accurate progress reporting
        $estimatedBytes = $this->estimateDatabaseBytes($database);

        $processInfo = $this->startBackupProcess($backupService, $outputPath, $logPath);

        if (!is_resource($processInfo['process'])) {
            throw new \RuntimeException('Gagal memulai proses backup.');
        }

        $backupProcess = $processInfo['process'];
        $loopCount = 0;
        $lastSize = 0;
        $noProgressSeconds = 0;
        // Allow up to 10 minutes without file growth before considering it stalled
        $maxStallSeconds = 600;

        try {
            while (true) {
                $loopCount++;

                $status = proc_get_status($backupProcess);
                if (!$status['running']) {
                    break;
                }

                clearstatcache(true, $outputPath);
                $currentSize = is_file($outputPath) ? (int) @filesize($outputPath) : 0;

                if ($currentSize > $lastSize) {
                    $lastSize = $currentSize;
                    $noProgressSeconds = 0;
                } else {
                    $noProgressSeconds++;
                }

                // If file is not growing for maxStallSeconds, terminate mysqldump
                if ($noProgressSeconds > $maxStallSeconds && $lastSize === 0) {
                    @proc_terminate($backupProcess);
                    throw new \RuntimeException(
                        'mysqldump tidak menghasilkan output selama ' . $maxStallSeconds . ' detik. Backup dibatalkan.'
                    );
                }

                if ($loopCount % 4 === 0) {
                    $progress = $estimatedBytes > 0
                        ? min(95, 2 + (int) (($currentSize / $estimatedBytes) * 93))
                        : min(95, 2 + (int) (log(max(1, $currentSize / 1024 / 1024) + 1, 2) * 8));

                    $this->putStatus($backupId, [
                        'status' => 'processing',
                        'progress_percent' => $progress,
                        'current_table_index' => 0,
                        'total_tables' => 1,
                        'current_table' => 'Full Database (Single-Pass)',
                        'message' => sprintf('Mencadangkan database... (%s)', $this->formatBytes($currentSize)),
                        'updated_at' => now()->timestamp,
                        'backup_file' => $outputPath,
                    ]);
                }

                sleep(1);
            }

            $exitCode = proc_close($backupProcess);

            // On Windows with bypass_shell, proc_close exit code may be unreliable.
            // Cross-check: if exit code != 0, always treat as failure.
            // If exit code == 0 but file is suspiciously small, log a warning.
            if ($exitCode !== 0) {
                $stderr = is_file($logPath) ? (string) @file_get_contents($logPath) : '';
                if (is_file($outputPath)) {
                    @unlink($outputPath);
                }
                throw new \RuntimeException('mysqldump gagal (exit ' . $exitCode . '): ' . ($stderr ?: 'Lihat log di ' . $logPath));
            }

            // Validate the dump is not empty
            if (!is_file($outputPath) || (int) @filesize($outputPath) === 0) {
                throw new \RuntimeException('mysqldump selesai tetapi file backup kosong atau tidak ditemukan.');
            }

            // Post-compression if gzip available
            if (!str_ends_with($outputPath, '.gz')) {
                $gzipPath = $this->resolveGzipPath();
                if ($gzipPath !== '' && is_file($outputPath)) {
                    $this->putStatus($backupId, [
                        'status' => 'processing',
                        'progress_percent' => 96,
                        'current_table_index' => 0,
                        'total_tables' => 1,
                        'message' => sprintf('Mengompres file backup (%s)...', $this->formatBytes((int) filesize($outputPath))),
                        'updated_at' => now()->timestamp,
                        'backup_file' => $outputPath,
                    ]);

                    try {
                        $this->compressFileWithGzip($outputPath, $gzipPath);
                    } catch (\Exception $e) {
                        Log::warning("Gzip compression failed, keeping uncompressed: " . $e->getMessage());
                    }
                }
            }
        } finally {
            // Ensure process is closed if still running
            if (is_resource($backupProcess) && ($processInfo['process'] ?? null) === $backupProcess) {
                @proc_terminate($backupProcess);
                @proc_close($backupProcess);
            }
        }
    }

    private function startBackupProcess(DatabaseBackupService $backupService, string $outputPath, string $logPath): array
    {
        $config = $backupService->getDatabaseConfig();
        $database = $config['database'];

        $command = $this->buildOptimizedCommand($config, $database, $outputPath);
        $environment = $this->buildEnvironment($config);

        if (strtoupper(substr(PHP_OS_FAMILY, 0, 3)) === 'WIN') {
            return $this->startWindowsBackupProcess($command, $outputPath, $environment, $logPath);
        } else {
            return $this->startUnixBackupProcess($command, $outputPath, $environment, $logPath);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function putStatus(string $backupId, array $payload): void
    {
        DatabaseBackupStatusStore::put($backupId, $payload);
    }

    private function buildOptimizedCommand(array $config, string $database, string $outputPath): array
    {
        $command = [
            $this->resolveDumpBinaryPath(),
            '--default-character-set=' . (string) ($config['charset'] ?? 'utf8mb4'),
            '--single-transaction',
            '--quick',
            '--skip-comments',
            '--skip-dump-date',
            '--hex-blob',
            '--routines',
            '--triggers',
            '--events',
            '--no-tablespaces',
            '--net-buffer-length=16777216',
            '--max-allowed-packet=536870912',
        ];

        $host = (string) ($config['host'] ?? '127.0.0.1');
        $port = (string) ($config['port'] ?? '3306');
        $socket = trim((string) ($config['unix_socket'] ?? ''));

        if (strtoupper(substr(PHP_OS_FAMILY, 0, 3)) === 'WIN') {
            $command[] = '--protocol=TCP';
            $command[] = '--host=' . $host;
            $command[] = '--port=' . $port;
        } else {
            if ($socket !== '') {
                $command[] = '--socket=' . $socket;
            } else {
                $command[] = '--host=' . $host;
                $command[] = '--port=' . $port;
            }
        }

        $command[] = '--user=' . (string) ($config['username'] ?? '');

        // Exclude temp and staging tables
        foreach ($this->getTempTablesToExclude($database) as $table) {
            $command[] = '--ignore-table=' . $database . '.' . $table;
        }

        // Use --result-file for direct disk write (avoids stdout pipe buffering)
        $command[] = '--result-file=' . $outputPath;
        $command[] = $database;

        return $command;
    }

    private function buildEnvironment(array $config): array
    {
        $password = (string) ($config['password'] ?? '');
        if ($password === '') {
            return [];
        }
        return ['MYSQL_PWD' => $password];
    }

    private function startWindowsBackupProcess(array $command, string $outputPath, array $environment, string $logPath): array
    {
        $baseEnvironment = [];
        foreach (array_merge($_ENV, $_SERVER) as $key => $value) {
            if (!is_string($key) || $key === '' || is_array($value) || is_object($value)) {
                continue;
            }
            $baseEnvironment[$key] = (string) $value;
        }

        $mergedEnvironment = array_merge($baseEnvironment, $environment);

        // Build command string; each arg is double-quoted for Windows CreateProcess
        $commandStr = implode(' ', array_map('escapeshellarg', $command));

        $descriptors = [
            0 => ['pipe', 'r'],
            // mysqldump uses --result-file, stdout is minimal
            1 => ['file', $logPath, 'ab'],
            // CRITICAL: stderr must go to FILE, not pipe.
            // A pipe has a 4KB buffer on Windows; if mysqldump writes > 4KB of warnings
            // it blocks forever while PHP's monitoring loop waits for it to exit.
            2 => ['file', $logPath, 'ab'],
        ];

        $process = proc_open($commandStr, $descriptors, $pipes, base_path(), $mergedEnvironment, ['bypass_shell' => true]);
        if (!is_resource($process)) {
            throw new \RuntimeException('Gagal menjalankan mysqldump. Pastikan mysqldump.exe tersedia.');
        }

        if (isset($pipes[0]) && is_resource($pipes[0])) {
            fclose($pipes[0]);
        }

        return [
            'process' => $process,
            'stderr' => null,
        ];
    }

    private function startUnixBackupProcess(array $command, string $outputPath, array $environment, string $logPath): array
    {
        $baseEnvironment = [];
        foreach (array_merge($_ENV, $_SERVER) as $key => $value) {
            if (!is_string($key) || $key === '' || is_array($value) || is_object($value)) {
                continue;
            }
            $baseEnvironment[$key] = (string) $value;
        }

        $mergedEnvironment = array_merge($baseEnvironment, $environment);
        $commandStr = implode(' ', array_map('escapeshellarg', $command));

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['file', $logPath, 'ab'],
            2 => ['file', $logPath, 'ab'],
        ];

        $process = proc_open($commandStr, $descriptors, $pipes, base_path(), $mergedEnvironment);
        if (!is_resource($process)) {
            throw new \RuntimeException('Gagal menjalankan mysqldump.');
        }

        if (isset($pipes[0]) && is_resource($pipes[0])) {
            fclose($pipes[0]);
        }

        return [
            'process' => $process,
            'stderr' => null,
        ];
    }

    private function compressFileWithGzip(string $inputPath, string $gzipPath): void
    {
        if (!is_file($inputPath)) {
            throw new \RuntimeException("Input file tidak ditemukan: {$inputPath}");
        }

        $outputPath = $inputPath . '.gz';

        $descriptors = [
            1 => ['file', $outputPath, 'wb'],
            2 => ['pipe', 'w'],
        ];

        $pipes = [];
        $process = proc_open(
            // Use compression level 4 — good balance between speed and size for large SQL files.
            // '--best' (-9) is too slow for multi-GB SQL dumps.
            [$gzipPath, '-4', '--force', '--stdout', $inputPath],
            $descriptors,
            $pipes,
            base_path(),
            [],
            ['bypass_shell' => true]
        );

        if (!is_resource($process)) {
            throw new \RuntimeException('Gagal menjalankan gzip.');
        }

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            @unlink($outputPath);
            throw new \RuntimeException("Gzip gagal (exit {$exitCode}): " . ($stderr !== '' ? $stderr : 'unknown error'));
        }

        if (is_file($outputPath) && filesize($outputPath) > 0 && is_file($inputPath)) {
            @unlink($inputPath);
        }
    }

    /**
     * Return temp/staging table names that should be excluded from backup.
     * These are transient tables with no recovery value.
     */
    /**
     * Return transient/staging table names to exclude from the full backup.
     * Tables starting with tmp_ or __ (double underscore) are never needed for restore.
     */
    private function getTempTablesToExclude(string $database): array
    {
        try {
            $tables = DB::select(
                "SELECT table_name FROM information_schema.tables
                 WHERE table_schema = ?
                   AND (SUBSTRING(table_name, 1, 4) = 'tmp_'
                     OR SUBSTRING(table_name, 1, 2) = '__')",
                [$database]
            );

            return array_filter(
                array_map(static fn ($t) => (string) ($t->table_name ?? $t->TABLE_NAME ?? ''), $tables),
                static fn (string $n) => $n !== ''
            );
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Estimate uncompressed size of the database in bytes for progress reporting.
     */
    private function estimateDatabaseBytes(string $database): int
    {
        try {
            $result = DB::select(
                "SELECT SUM(data_length + index_length) AS bytes
                 FROM information_schema.tables
                 WHERE table_schema = ?",
                [$database]
            );
            return (int) ($result[0]->bytes ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    private function resolveDumpBinaryPath(): string
    {
        $candidates = [
            'C:\\xampp\\mysql\\bin\\mysqldump.exe',
            'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe',
            'C:\\Program Files (x86)\\MySQL\\MySQL Server 5.7\\bin\\mysqldump.exe',
            '/usr/bin/mysqldump',
            '/usr/local/bin/mysqldump',
            'mysqldump',
        ];

        foreach ($candidates as $path) {
            if ($path === 'mysqldump' || (file_exists($path) && is_executable($path))) {
                return $path;
            }
        }

        return 'mysqldump';
    }

    private function resolveGzipPath(): string
    {
        $candidates = [
            'C:\\Program Files\\Git\\usr\\bin\\gzip.exe',
            'C:\\Program Files (x86)\\Git\\usr\\bin\\gzip.exe',
            'C:\\xampp\\php\\gzip.exe',
            'C:\\Windows\\System32\\gzip.exe',
            '/usr/bin/gzip',
            '/bin/gzip',
        ];

        foreach ($candidates as $path) {
            if (file_exists($path) && is_executable($path)) {
                return $path;
            }
        }

        return '';
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
