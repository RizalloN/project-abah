<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackupService;
use App\Support\DatabaseBackupStatusStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class ProgressiveBackupCommand extends Command
{
    protected $signature = 'db:backup-progressive {backupId}';
    protected $description = 'Perform a database backup with progress tracking in Cache';

    public function handle(DatabaseBackupService $backupService)
    {
        $backupId = $this->argument('backupId');
        $cacheKey = "backup_progress:{$backupId}";
        $config = $backupService->getDatabaseConfig();
        $database = $config['database'];

        try {
            $backupDirectory = storage_path('app/private/database_backups');
            if (!is_dir($backupDirectory)) {
                File::makeDirectory($backupDirectory, 0755, true);
            }

            // Normalize path separators for Windows
            $backupDirectory = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $backupDirectory);

            $baseName = sprintf(
                '%s_full_%s',
                preg_replace('/[^A-Za-z0-9_-]+/', '_', $database) ?: 'database',
                now()->format('Ymd_His')
            );

            // On Windows, always use .sql first, then compress post-process if gzip available
            $filename = $baseName . '.sql';
            $absolutePath = $backupDirectory . DIRECTORY_SEPARATOR . $filename;
            $gzipPath = $this->resolveGzipPath();

            $this->putStatus($backupId, [
                'status' => 'processing',
                'progress_percent' => 2,
                'current_table_index' => 0,
                'total_tables' => 1,
                'current_table' => 'Full Database (Optimized Single-Pass)',
                'message' => 'Memulai backup database dengan single-pass optimization...',
                'updated_at' => now()->timestamp,
                'backup_file' => $absolutePath,
                'compression_enabled' => $gzipPath !== '',
            ]);

            // Single-pass optimized backup with compression
            $this->performOptimizedBackup($backupService, $cacheKey, $absolutePath, $database);

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
                    'size_human' => is_file($absolutePath) ? $this->formatBytes(filesize($absolutePath)) : '0 B',
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

    private function performOptimizedBackup(DatabaseBackupService $backupService, string $cacheKey, string $outputPath, string $database): void
    {
        // Start backup process in background
        $processInfo = $this->startBackupProcess($backupService, $outputPath);

        if (!is_resource($processInfo['process'])) {
            throw new \RuntimeException('Gagal memulai proses backup.');
        }

        $backupProcess = $processInfo['process'];
        $stderrPipe = $processInfo['stderr'] ?? null;

        // Monitor progress by watching output file size
        $lastSize = 0;
        $noProgressCount = 0;
        $maxNoProgressIterations = 120; // ~2 minutes with 1s checks
        $loopCount = 0;

        try {
            while (true) {
                $loopCount++;
                if ($loopCount % 20 === 0) {
                    $currentSize = @filesize($outputPath) ?: 0;
                    $this->putStatusFromCacheKey($cacheKey, [
                        'status' => 'processing',
                        'progress_percent' => min(95, 2 + (($currentSize % 100000) / 100000) * 93),
                        'current_table_index' => 0,
                        'total_tables' => 1,
                        'current_table' => 'Full Database (Optimized Single-Pass)',
                        'message' => sprintf('Mencadangkan database... (%s)', $this->formatBytes($currentSize)),
                        'updated_at' => now()->timestamp,
                        'backup_file' => $outputPath,
                    ]);
                }

                // Check process status
                $status = proc_get_status($backupProcess);
                if (!$status['running']) {
                    break;
                }

                // Monitor file size for progress (every 20 iterations)
                if (is_file($outputPath)) {
                    $currentSize = @filesize($outputPath);
                    if ($currentSize !== false) {
                        $progress = min(95, 2 + (($currentSize % 100000) / 100000) * 93);

                        if ($currentSize > $lastSize) {
                            $lastSize = $currentSize;
                            $noProgressCount = 0;
                        } else {
                            $noProgressCount++;
                        }

                        // Update cache with progress
                        if ($loopCount % 20 === 0) {
                            $this->putStatusFromCacheKey($cacheKey, [
                                'status' => 'processing',
                                'progress_percent' => (int) $progress,
                                'current_table_index' => 0,
                                'total_tables' => 1,
                                'current_table' => 'Full Database (Optimized Single-Pass)',
                                'message' => sprintf(
                                    'Mencadangkan database... (%s)',
                                    $this->formatBytes($currentSize)
                                ),
                                'updated_at' => now()->timestamp,
                                'backup_file' => $outputPath,
                            ]);
                        }

                        // If size hasn't changed for too long, don't fail - just keep waiting
                        if ($noProgressCount > $maxNoProgressIterations) {
                            $noProgressCount = 0; // Reset counter
                        }
                    }
                }

                usleep(500000); // Check every 0.5 seconds
            }

            // Wait for final output
            $stderr = is_resource($stderrPipe) ? stream_get_contents($stderrPipe) : '';
            $exitCode = proc_close($backupProcess);

            if ($exitCode !== 0) {
                throw new \RuntimeException('mysqldump gagal: ' . ($stderr ?: 'Unknown error'));
            }

            // Post-compression if gzip available and output is .sql (not .sql.gz)
            if (!str_ends_with($outputPath, '.gz')) {
                $gzipPath = $this->resolveGzipPath();
                if ($gzipPath !== '' && is_file($outputPath)) {
                    try {
                        $this->compressFileWithGzip($outputPath, $gzipPath);
                    } catch (\Exception $e) {
                        Log::warning("Gzip compression failed, leaving file uncompressed: " . $e->getMessage());
                    }
                }
            }
        } finally {
            // Cleanup pipes
            if (is_resource($stderrPipe)) {
                fclose($stderrPipe);
            }
        }
    }

    private function startBackupProcess(DatabaseBackupService $backupService, string $outputPath): array
    {
        $config = $backupService->getDatabaseConfig();
        $database = $config['database'];

        // Build optimized single-pass command
        $command = $this->buildOptimizedCommand($config, $database);
        $environment = $this->buildEnvironment($config);

        // On Windows: pipe through separate gzip process or write uncompressed
        if (strtoupper(substr(PHP_OS_FAMILY, 0, 3)) === 'WIN') {
            return $this->startWindowsBackupProcess($command, $outputPath, $environment);
        } else {
            return $this->startUnixBackupProcess($command, $outputPath, $environment);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function putStatus(string $backupId, array $payload): void
    {
        DatabaseBackupStatusStore::put($backupId, $payload);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function putStatusFromCacheKey(string $cacheKey, array $payload): void
    {
        $backupId = str_starts_with($cacheKey, 'backup_progress:')
            ? substr($cacheKey, strlen('backup_progress:'))
            : $cacheKey;

        $this->putStatus($backupId, $payload);
    }

    private function buildOptimizedCommand(array $config, string $database): array
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

    private function startWindowsBackupProcess(array $command, string $outputPath, array $environment): array
    {
        $baseEnvironment = [];
        foreach (array_merge($_ENV, $_SERVER) as $key => $value) {
            if (!is_string($key) || $key === '' || is_array($value) || is_object($value)) {
                continue;
            }
            $baseEnvironment[$key] = (string) $value;
        }

        $mergedEnvironment = array_merge($baseEnvironment, $environment);

        // Add --result-file directly to command for direct disk write (avoids stream_copy_to_stream blocking)
        $command[] = '--result-file=' . $outputPath;
        $commandStr = implode(' ', array_map('escapeshellarg', $command));

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($commandStr, $descriptors, $pipes, base_path(), $mergedEnvironment, ['bypass_shell' => true]);
        if (!is_resource($process)) {
            throw new \RuntimeException('Gagal menjalankan mysqldump.');
        }

        fclose($pipes[0]);
        fclose($pipes[1]); // Close stdout since we're using --result-file

        // Return process with stderr pipe for monitoring
        return [
            'process' => $process,
            'stderr' => $pipes[2],
        ];
    }

    private function startUnixBackupProcess(array $command, string $outputPath, array $environment): array
    {
        $baseEnvironment = [];
        foreach (array_merge($_ENV, $_SERVER) as $key => $value) {
            if (!is_string($key) || $key === '' || is_array($value) || is_object($value)) {
                continue;
            }
            $baseEnvironment[$key] = (string) $value;
        }

        $mergedEnvironment = array_merge($baseEnvironment, $environment);

        // Build shell command with gzip pipe
        $commandStr = implode(' ', array_map('escapeshellarg', $command)) . ' | gzip > ' . escapeshellarg($outputPath);

        $descriptors = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($commandStr, $descriptors, $pipes, base_path(), $mergedEnvironment);
        if (!is_resource($process)) {
            throw new \RuntimeException('Gagal menjalankan mysqldump dengan gzip.');
        }

        fclose($pipes[1]);

        // Return process with stderr pipe for monitoring
        return [
            'process' => $process,
            'stderr' => $pipes[2],
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
            [$gzipPath, '--best', '--force', '--stdout', $inputPath],
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
            throw new \RuntimeException("Gzip compression failed with exit code: {$exitCode}" . ($stderr !== '' ? ". {$stderr}" : ''));
        }

        // Remove original uncompressed file
        if (is_file($outputPath) && filesize($outputPath) > 0 && is_file($inputPath)) {
            @unlink($inputPath);
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
            if (file_exists($path) && is_executable($path)) {
                return $path;
            }
        }

        return 'mysqldump';
    }

    private function resolveGzipPath(): string
    {
        $candidates = [
            'C:\\xampp\\php\\gzip.exe',
            'C:\\Program Files\\Git\\usr\\bin\\gzip.exe',
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
