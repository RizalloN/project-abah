<?php

namespace App\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class DatabaseBackupService
{
    private const BACKUP_DIRECTORY = 'private/database_backups';

    public function createFullBackup(): array
    {
        $config = $this->getDatabaseConfig();
        $database = $config['database'];

        $backupDirectory = storage_path('app/' . self::BACKUP_DIRECTORY);
        if (!is_dir($backupDirectory)) {
            File::makeDirectory($backupDirectory, 0755, true);
        }

        // Use .sql.gz for compressed backup
        $filename = sprintf(
            '%s_full_%s.sql.gz',
            preg_replace('/[^A-Za-z0-9_-]+/', '_', $database) ?: 'database',
            now()->format('Ymd_His')
        );
        $absolutePath = $backupDirectory . DIRECTORY_SEPARATOR . $filename;

        // Single-pass dump with direct compression (no temp files, no append operations)
        $command = $this->buildOptimizedDumpCommand($config, $database);
        $environment = $this->buildDumpEnvironment($config);
        $this->runOptimizedDumpProcess($command, $absolutePath, $environment);

        $this->assertDumpLooksClean($absolutePath);

        return [
            'filename' => $filename,
            'absolute_path' => $absolutePath,
            'relative_path' => self::BACKUP_DIRECTORY . '/' . $filename,
            'size' => is_file($absolutePath) ? (int) filesize($absolutePath) : 0,
        ];
    }

    public function getTables(): array
    {
        $connectionName = Config::get('database.default');
        $config = Config::get("database.connections.{$connectionName}");
        $database = $config['database'];

        $tables = DB::select('SHOW TABLES');
        $key = "Tables_in_{$database}";

        return collect($tables)->map(function ($table) use ($key) {
            return $table->$key ?? current((array) $table);
        })->toArray();
    }

    public function getDatabaseConfig(): array
    {
        $connectionName = Config::get('database.default');
        $config = Config::get("database.connections.{$connectionName}");

        if (!is_array($config)) {
            throw new RuntimeException('Konfigurasi database aktif tidak ditemukan.');
        }

        return $config;
    }

    public function buildDumpCommand(array $config, string $database, ?string $outputPath, array $extraArgs = []): array
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

        // Handle connection parameters
        $host = (string) ($config['host'] ?? '127.0.0.1');
        $port = (string) ($config['port'] ?? '3306');
        $socket = trim((string) ($config['unix_socket'] ?? ''));
        
        // On Windows XAMPP: prefer TCP/IP connection with protocol specification
        if (strtoupper(substr(PHP_OS_FAMILY, 0, 3)) === 'WIN') {
            // On Windows, use TCP protocol explicitly
            $command[] = '--protocol=TCP';
            $command[] = '--host=' . $host;
            $command[] = '--port=' . $port;
        } else {
            // On Unix-like systems: prefer socket if available
            if ($socket !== '') {
                $command[] = '--socket=' . $socket;
            } else {
                $command[] = '--host=' . $host;
                $command[] = '--port=' . $port;
            }
        }

        $command[] = '--user=' . (string) ($config['username'] ?? '');

        if ($outputPath) {
            $command[] = '--result-file=' . $outputPath;
        }

        $tableArgs = [];
        foreach ($extraArgs as $arg) {
            if (is_string($arg) && $arg !== '' && $arg[0] !== '-') {
                $tableArgs[] = $arg;
                continue;
            }

            $command[] = $arg;
        }

        if (!in_array('--databases', $extraArgs)) {
            $command[] = $database;
        }

        foreach ($tableArgs as $table) {
            $command[] = $table;
        }

        return $command;
    }

    private function buildDumpEnvironment(array $config): array
    {
        $password = (string) ($config['password'] ?? '');
        if ($password === '') {
            return [];
        }

        return ['MYSQL_PWD' => $password];
    }

    /**
     * Build optimized dump command for single-pass backup with direct compression.
     * Combines schema and data in one mysqldump call with gzip piping.
     */
    private function buildOptimizedDumpCommand(array $config, string $database): string
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

        // On Windows, use different pipe syntax than Unix
        if (strtoupper(substr(PHP_OS_FAMILY, 0, 3)) === 'WIN') {
            // Windows: pipe through gzip directly with proc_open
            return implode(' ', array_map('escapeshellarg', $command));
        } else {
            // Unix: use shell pipe syntax (will be executed through shell)
            return implode(' ', array_map('escapeshellarg', $command)) . ' | gzip';
        }
    }

    /**
     * Run optimized dump process with piped compression.
     * Streams mysqldump output directly to gzip without intermediate files.
     */
    private function runOptimizedDumpProcess(string $command, string $outputPath, array $environment = []): void
    {
        $baseEnvironment = [];
        foreach (array_merge($_ENV, $_SERVER) as $key => $value) {
            if (!is_string($key) || $key === '' || is_array($value) || is_object($value)) {
                continue;
            }
            $baseEnvironment[$key] = (string) $value;
        }

        $mergedEnvironment = array_merge($baseEnvironment, $environment);

        // On Windows: cannot use shell pipes reliably, so write uncompressed and compress after
        if (strtoupper(substr(PHP_OS_FAMILY, 0, 3)) === 'WIN') {
            $this->runWindowsOptimizedDump($command, $outputPath, $mergedEnvironment);
        } else {
            $this->runUnixOptimizedDump($command, $outputPath, $mergedEnvironment);
        }
    }

    /**
     * Windows: Stream mysqldump output to gzip utility via pipe.
     * Falls back to uncompressed if gzip unavailable.
     */
    private function runWindowsOptimizedDump(string $command, string $outputPath, array $environment): void
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes, base_path(), $environment, ['bypass_shell' => true]);
        if (!is_resource($process)) {
            throw new RuntimeException('Gagal menjalankan mysqldump. Pastikan mysqldump.exe tersedia.');
        }

        fclose($pipes[0]);

        // Try to pipe through gzip if available
        $gzipPath = $this->resolveGzipBinaryPath();
        $compressionFailed = false;

        if ($gzipPath !== '') {
            try {
                $this->streamThroughGzip($pipes[1], $outputPath, $gzipPath);
            } catch (RuntimeException $e) {
                $compressionFailed = true;
            }
        } else {
            $compressionFailed = true;
        }

        // Fallback: write uncompressed if gzip unavailable
        if ($compressionFailed) {
            $output = fopen($outputPath, 'wb');
            if (!is_resource($output)) {
                fclose($pipes[1]);
                throw new RuntimeException('Gagal membuka file output untuk backup.');
            }

            stream_copy_to_stream($pipes[1], $output);
            fclose($output);
        }

        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);
        if ($exitCode !== 0) {
            if (is_file($outputPath)) {
                @unlink($outputPath);
            }
            throw new RuntimeException('mysqldump gagal: ' . ($stderr ?: 'Unknown error'));
        }
    }

    /**
     * Unix: Use shell pipe to stream mysqldump output through gzip.
     * More efficient than manual piping on Unix systems.
     */
    private function runUnixOptimizedDump(string $command, string $outputPath, array $environment): void
    {
        // Ensure gzip is available
        if (!$this->isGzipAvailable()) {
            throw new RuntimeException('gzip tidak tersedia di sistem Unix. Pasang gzip terlebih dahulu.');
        }

        // Redirect output directly to file via shell
        $fullCommand = $command . ' > ' . escapeshellarg($outputPath);

        $descriptors = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($fullCommand, $descriptors, $pipes, base_path(), $environment);
        if (!is_resource($process)) {
            throw new RuntimeException('Gagal menjalankan mysqldump dengan gzip.');
        }

        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);
        if ($exitCode !== 0) {
            if (is_file($outputPath)) {
                @unlink($outputPath);
            }
            throw new RuntimeException('mysqldump gagal: ' . ($stderr ?: 'Unknown error'));
        }
    }

    /**
     * Stream data from mysqldump through gzip compression.
     * Handles buffering to avoid deadlock.
     */
    private function streamThroughGzip($source, string $outputPath, string $gzipPath): void
    {
        $descriptors = [
            0 => $source,
            1 => ['file', $outputPath, 'wb'],
            2 => ['pipe', 'w'],
        ];

        $pipes = [];
        $process = proc_open(
            escapeshellarg($gzipPath),
            $descriptors,
            $pipes,
            base_path(),
            [],
            ['bypass_shell' => true]
        );

        if (!is_resource($process)) {
            throw new RuntimeException('Gagal menjalankan gzip.');
        }

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);
        if ($exitCode !== 0) {
            throw new RuntimeException('gzip compression failed: ' . ($stderr ?: 'Unknown error'));
        }
    }

    private function resolveGzipBinaryPath(): string
    {
        if (strtoupper(substr(PHP_OS_FAMILY, 0, 3)) === 'WIN') {
            // Windows paths to check for gzip
            $candidates = [
                'C:\\xampp\\php\\gzip.exe',
                'C:\\Program Files\\Git\\usr\\bin\\gzip.exe',
                'C:\\Program Files (x86)\\Git\\usr\\bin\\gzip.exe',
                'gzip.exe',
            ];
        } else {
            $candidates = ['/usr/bin/gzip', '/bin/gzip', 'gzip'];
        }

        foreach ($candidates as $path) {
            if ($this->isExecutable($path)) {
                return $path;
            }
        }

        return '';
    }

    private function isGzipAvailable(): bool
    {
        return $this->resolveGzipBinaryPath() !== '';
    }

    private function isExecutable(string $path): bool
    {
        // For absolute paths
        if (file_exists($path) && is_executable($path)) {
            return true;
        }

        // Check PATH on Unix-like systems
        if (strtoupper(substr(PHP_OS_FAMILY, 0, 3)) !== 'WIN') {
            $output = @shell_exec('which ' . escapeshellarg($path) . ' 2>/dev/null');
            return !empty($output);
        }

        return false;
    }

    private function runDumpProcess(array $command, ?string $outputPath, array $environment = []): array
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $baseEnvironment = [];
        foreach (array_merge($_ENV, $_SERVER) as $key => $value) {
            if (!is_string($key) || $key === '' || is_array($value) || is_object($value)) {
                continue;
            }

            $baseEnvironment[$key] = (string) $value;
        }

        $process = proc_open($command, $descriptors, $pipes, base_path(), array_merge($baseEnvironment, $environment), ['bypass_shell' => true]);
        if (!is_resource($process)) {
            throw new RuntimeException('Gagal menjalankan proses backup database. Pastikan mysqldump.exe tersedia.');
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);
        if ($exitCode !== 0) {
            if ($outputPath) {
                @unlink($outputPath);
            }
            
            $errorMsg = 'Backup database gagal dijalankan';
            if ($stderr !== '') {
                $errorMsg .= ': ' . trim($stderr);
                
                // Add helpful suggestions for common errors
                if (strpos($stderr, 'socket') !== false || strpos($stderr, '2004') !== false || strpos($stderr, '10106') !== false) {
                    $errorMsg .= ' | SOLUSI: Pastikan MySQL Server sudah berjalan di XAMPP Control Panel. Jika masih error, coba restart MySQL.';
                } elseif (strpos($stderr, 'Access denied') !== false) {
                    $errorMsg .= ' | SOLUSI: Cek konfigurasi DB_USERNAME dan DB_PASSWORD di file .env';
                }
            }
            
            throw new RuntimeException($errorMsg);
        }

        return [
            'stdout' => (string) $stdout,
            'stderr' => (string) $stderr,
            'exit_code' => $exitCode,
        ];
    }

    private function appendTableData(array $command, string $tempPath, string $outputPath, array $environment): void
    {
        $this->runDumpProcess($command, $tempPath, $environment);
        $this->appendDumpFile($tempPath, $outputPath);
    }

    private function appendDumpFile(string $sourcePath, string $outputPath): void
    {
        $source = fopen($sourcePath, 'rb');
        if (!is_resource($source)) {
            throw new RuntimeException('Gagal membaca file dump sementara.');
        }

        $destination = fopen($outputPath, 'ab');
        if (!is_resource($destination)) {
            fclose($source);
            throw new RuntimeException('Gagal membuka file backup untuk append.');
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

    private function assertDumpLooksClean(string $outputPath, string $stderr = ''): void
    {
        if (!is_file($outputPath)) {
            throw new RuntimeException('File backup tidak ditemukan setelah proses dump.');
        }

        $size = (int) filesize($outputPath);
        if ($size <= 0) {
            @unlink($outputPath);
            throw new RuntimeException('Backup database menghasilkan file kosong.');
        }

        $handle = fopen($outputPath, 'rb');
        if (!is_resource($handle)) {
            @unlink($outputPath);
            throw new RuntimeException('Backup database tidak bisa divalidasi.');
        }

        $prefix = (string) fread($handle, 4096);
        fclose($handle);

        if (str_starts_with($prefix, "\xEF\xBB\xBF")) {
            $remaining = file_get_contents($outputPath);
            if ($remaining !== false) {
                file_put_contents($outputPath, substr($remaining, 3), LOCK_EX);
                $prefix = substr($prefix, 3);
            }
        }

        $trimmedPrefix = ltrim($prefix);
        $looksLikeHtml = preg_match('/^(<!DOCTYPE\s+html|<html\b)/i', $trimmedPrefix) === 1;
        $looksLikeSql = preg_match('/^(\/\*![0-9]{5}\s+SET|CREATE\s+DATABASE|USE\s+`|DROP\s+TABLE|CREATE\s+TABLE|LOCK\s+TABLES|INSERT\s+INTO)/i', $trimmedPrefix) === 1;

        if ($looksLikeHtml || !$looksLikeSql) {
            @unlink($outputPath);
            throw new RuntimeException(
                'Backup dibatalkan karena output dump tidak valid atau tercampur konten non-SQL.'
                . ($stderr !== '' ? ' Detail: ' . trim($stderr) : '')
            );
        }
    }

    private function resolveDumpBinaryPath(): string
    {
        $configured = trim((string) env('MYSQLDUMP_BINARY', ''));
        $candidates = array_values(array_filter([
            $configured !== '' ? $configured : null,
            'C:\\xampp\\mysql\\bin\\mysqldump.exe',
            'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe',
            'C:\\Program Files\\MariaDB 11.0\\bin\\mysqldump.exe',
            'mysqldump',
        ]));

        foreach ($candidates as $candidate) {
            if ($candidate === 'mysqldump') {
                return $candidate;
            }

            if (is_file($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('Binary mysqldump tidak ditemukan.');
    }
}
