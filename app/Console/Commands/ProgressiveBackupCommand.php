<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
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

            $baseName = sprintf(
                '%s_full_%s',
                preg_replace('/[^A-Za-z0-9_-]+/', '_', $database) ?: 'database',
                now()->format('Ymd_His')
            );

            $gzipPath = $this->resolveGzipPath();
            $extension = $gzipPath !== '' ? '.sql.gz' : '.sql';
            $filename = $baseName . $extension;
            $absolutePath = $backupDirectory . DIRECTORY_SEPARATOR . $filename;

            Cache::put($cacheKey, [
                'status' => 'processing',
                'progress_percent' => 2,
                'current_table_index' => 0,
                'total_tables' => 1,
                'current_table' => 'Full Database (Optimized Single-Pass)',
                'message' => 'Memulai backup database dengan single-pass optimization...',
                'updated_at' => now()->timestamp,
                'backup_file' => $absolutePath,
                'compression_enabled' => $gzipPath !== '',
            ], now()->addHours(1));

            // Single-pass optimized backup with compression
            $this->performOptimizedBackup($backupService, $cacheKey, $absolutePath, $database);

            $actualFilename = basename($absolutePath);
            if (!is_file($absolutePath)) {
                $actualFilename = $baseName . (file_exists($baseName . '.sql.gz') ? '.sql.gz' : '.sql');
                $absolutePath = $backupDirectory . DIRECTORY_SEPARATOR . $actualFilename;
            }

            Cache::put($cacheKey, [
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
            ], now()->addHours(1));

        } catch (\Throwable $e) {
            Log::error("Backup failed for {$backupId}: " . $e->getMessage());
            Cache::put($cacheKey, [
                'status' => 'failed',
                'message' => $e->getMessage(),
                'updated_at' => now()->timestamp,
            ], now()->addHours(1));
            
            if (isset($absolutePath) && is_file($absolutePath)) {
                @unlink($absolutePath);
            }
        }
    }

    private function performOptimizedBackup(DatabaseBackupService $backupService, string $cacheKey, string $outputPath, string $database): void
    {
        // Start backup process in background
        $backupProcess = $this->startBackupProcess($backupService, $outputPath);
        
        if (!is_resource($backupProcess)) {
            throw new \RuntimeException('Gagal memulai proses backup.');
        }

        // Monitor progress by watching output file size
        $lastSize = 0;
        $noProgressCount = 0;
        $maxNoProgressIterations = 120; // ~2 minutes with 1s checks

        while (true) {
            // Check process status
            $status = proc_get_status($backupProcess);
            if (!$status['running']) {
                break;
            }

            // Monitor file size for progress
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

                    Cache::put($cacheKey, [
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
                    ], now()->addHours(1));

                    // If size hasn't changed for too long, don't fail - just keep waiting
                    if ($noProgressCount > $maxNoProgressIterations) {
                        Log::warning("Backup file size hasn't changed for 2 minutes, but process still running. Continuing...");
                        $noProgressCount = 0; // Reset counter
                    }
                }
            }

            usleep(500000); // Check every 0.5 seconds
        }

        // Wait for final output
        $stdout = stream_get_contents($GLOBALS['backup_stdout_pipe'] ?? STDIN);
        $stderr = stream_get_contents($GLOBALS['backup_stderr_pipe'] ?? STDIN);
        $exitCode = proc_close($backupProcess);

        if ($exitCode !== 0) {
            throw new \RuntimeException('mysqldump gagal: ' . ($stderr ?: 'Unknown error'));
        }
    }

    private function startBackupProcess(DatabaseBackupService $backupService, string $outputPath)
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

    private function startWindowsBackupProcess(array $command, string $outputPath, array $environment)
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

        // Try to compress output with gzip if available (post-process)
        $gzipPath = $this->resolveGzipPath();
        if ($gzipPath !== '') {
            try {
                $this->compressFileWithGzip($outputPath, $gzipPath);
            } catch (\Exception $e) {
                Log::warning('Gzip compression failed, backup file left uncompressed: ' . $e->getMessage());
            }
        }

        fclose($pipes[1]);

        // Store pipes globally for cleanup in main process
        $GLOBALS['backup_stderr_pipe'] = $pipes[2];

        return $process;
    }

    private function startUnixBackupProcess(array $command, string $outputPath, array $environment)
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
        $GLOBALS['backup_stderr_pipe'] = $pipes[2];

        return $process;
    }

    private function compressFileWithGzip(string $inputPath, string $gzipPath): void
    {
        if (!is_file($inputPath)) {
            throw new \RuntimeException("Input file tidak ditemukan: {$inputPath}");
        }

        $outputPath = $inputPath . '.gz';

        $descriptors = [
            0 => ['file', $inputPath, 'rb'],
            1 => ['file', $outputPath, 'wb'],
            2 => ['pipe', 'w'],
        ];

        $pipes = [];
        $process = proc_open(
            escapeshellarg($gzipPath) . ' --best --force',
            $descriptors,
            $pipes,
            base_path(),
            [],
            ['bypass_shell' => true]
        );

        if (!is_resource($process)) {
            throw new \RuntimeException('Gagal menjalankan gzip.');
        }

        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            throw new \RuntimeException("Gzip compression failed with exit code: {$exitCode}");
        }

        // Remove original uncompressed file
        if (is_file($inputPath)) {
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
