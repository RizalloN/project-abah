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
        
        try {
            $tables = $backupService->getTables();
            $totalTables = count($tables);
            $config = $backupService->getDatabaseConfig();
            $database = $config['database'];

            $backupDirectory = storage_path('app/private/database_backups');
            if (!is_dir($backupDirectory)) {
                File::makeDirectory($backupDirectory, 0755, true);
            }

            $filename = sprintf(
                '%s_full_%s.sql',
                preg_replace('/[^A-Za-z0-9_-]+/', '_', $database) ?: 'database',
                now()->format('Ymd_His')
            );
            $absolutePath = $backupDirectory . DIRECTORY_SEPARATOR . $filename;

            Cache::put($cacheKey, [
                'status' => 'processing',
                'progress_percent' => 5,
                'current_table_index' => 0,
                'total_tables' => $totalTables,
                'current_table' => 'Schema',
                'message' => 'Menyiapkan schema database...',
            ], now()->addHours(1));

            $schemaCommand = $backupService->buildDumpCommand($config, $database, $absolutePath, ['--no-data']);
            $environment = ['MYSQL_PWD' => (string) ($config['password'] ?? '')];
            
            $this->runProcess($schemaCommand, $environment);

            foreach ($tables as $index => $table) {
                $progress = round(5 + (($index + 1) / $totalTables) * 90);
                Cache::put($cacheKey, [
                    'status' => 'processing',
                    'progress_percent' => $progress,
                    'current_table_index' => $index + 1,
                    'total_tables' => $totalTables,
                    'current_table' => $table,
                    'message' => "Mencadangkan tabel: {$table} ({$progress}%)",
                ], now()->addHours(1));

                $tempPath = $this->createTemporaryDumpPath($database, $table);
                try {
                    $dataCommand = $backupService->buildDumpCommand($config, $database, $tempPath, ['--no-create-info', $table]);
                    $this->runProcess($dataCommand, $environment, function () use ($cacheKey, $progress, $index, $totalTables, $table): void {
                        Cache::put($cacheKey, [
                            'status' => 'processing',
                            'progress_percent' => $progress,
                            'current_table_index' => $index + 1,
                            'total_tables' => $totalTables,
                            'current_table' => $table,
                            'message' => "Mencadangkan tabel: {$table} ({$progress}%)",
                        ], now()->addHours(1));
                    });
                    $this->appendDumpFile($tempPath, $absolutePath);
                } finally {
                    if (is_file($tempPath)) {
                        @unlink($tempPath);
                    }
                }
            }

            Cache::put($cacheKey, [
                'status' => 'completed',
                'progress_percent' => 100,
                'current_table_index' => $totalTables,
                'message' => 'Backup database selesai.',
                'file' => [
                    'name' => $filename,
                    'relative_path' => 'private/database_backups/' . $filename,
                    'download_url' => route('file-management.download', ['path' => 'private/database_backups/' . $filename]),
                ],
            ], now()->addHours(1));

        } catch (\Throwable $e) {
            Log::error("Backup failed for {$backupId}: " . $e->getMessage());
            Cache::put($cacheKey, [
                'status' => 'failed',
                'message' => $e->getMessage(),
            ], now()->addHours(1));
            
            if (isset($absolutePath) && is_file($absolutePath)) {
                @unlink($absolutePath);
            }
        }
    }

    private function runProcess(array $command, array $environment, ?callable $heartbeat = null): array
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        // Merge system environment with backup-specific environment
        $baseEnvironment = [];
        foreach (array_merge($_ENV, $_SERVER) as $key => $value) {
            if (!is_string($key) || $key === '' || is_array($value) || is_object($value)) {
                continue;
            }
            $baseEnvironment[$key] = (string) $value;
        }

        $mergedEnvironment = array_merge($baseEnvironment, $environment);

        $process = proc_open($command, $descriptors, $pipes, base_path(), $mergedEnvironment, ['bypass_shell' => true]);
        if (!is_resource($process)) {
            throw new \RuntimeException('Gagal menjalankan proses mysqldump. Pastikan mysqldump.exe tersedia di PATH atau konfigurasi MYSQLDUMP_BINARY di .env.');
        }

        fclose($pipes[0]);
        $lastHeartbeat = time();
        $heartbeatInterval = 15;

        while (true) {
            $status = proc_get_status($process);
            if (!$status['running']) {
                break;
            }

            if ($heartbeat !== null && (time() - $lastHeartbeat) >= $heartbeatInterval) {
                $heartbeat();
                $lastHeartbeat = time();
            }

            usleep(500000);
        }

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);
        if ($exitCode !== 0) {
            $errorMsg = 'mysqldump failed' . ($stderr !== '' ? ': ' . trim($stderr) : '.');
            
            // Provide helpful error suggestions
            if (strpos($stderr, 'socket') !== false || strpos($stderr, '2004') !== false || strpos($stderr, 'connect') !== false) {
                $errorMsg .= ' | Saran: Pastikan MySQL server sudah berjalan. Cek di XAMPP Control Panel.';
            }
            
            throw new \RuntimeException($errorMsg);
        }

        return ['stdout' => $stdout, 'stderr' => $stderr];
    }

    private function appendDumpFile(string $sourcePath, string $outputPath): void
    {
        $source = fopen($sourcePath, 'rb');
        if (!is_resource($source)) {
            throw new \RuntimeException('Gagal membaca file dump sementara.');
        }

        $destination = fopen($outputPath, 'ab');
        if (!is_resource($destination)) {
            fclose($source);
            throw new \RuntimeException('Gagal membuka file backup untuk append.');
        }

        try {
            fwrite($destination, "\n");
            stream_copy_to_stream($source, $destination);
        } finally {
            fclose($source);
            fclose($destination);
        }
    }

    private function createTemporaryDumpPath(string $database, string $table): string
    {
        $safeName = preg_replace('/[^A-Za-z0-9_-]+/', '_', $database . '_' . $table) ?: 'backup';
        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . $safeName . '_' . uniqid('', true) . '.sql';
    }
}
