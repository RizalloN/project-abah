<?php

namespace App\Services;

use App\Services\DatabaseBackup\CompressedDatabaseDumpRunner;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\Config;
use RuntimeException;
use Throwable;

class DailyDatabaseBackupService
{
    private const MANIFEST_SCHEMA_VERSION = 1;

    private const MANAGED_BY = 'project-abah-daily-backup';

    private const MANIFEST_FILENAME = 'manifest.json';

    public function __construct(
        private readonly CompressedDatabaseDumpRunner $dumpRunner
    ) {}

    /**
     * @return array{
     *     status: string,
     *     directory: string,
     *     backup_file: string|null,
     *     manifest_file: string|null,
     *     compressed_bytes: int,
     *     uncompressed_bytes: int,
     *     free_space_bytes: int,
     *     deleted_backups: array<int, string>,
     *     warnings: array<int, string>
     * }
     */
    public function backup(?DateTimeImmutable $now = null, bool $dryRun = false): array
    {
        if (! (bool) config('database_backup.enabled', true)) {
            return $this->result('disabled', '', null, null, 0, 0, 0, [], [
                'Backup harian dinonaktifkan melalui konfigurasi.',
            ]);
        }

        $timezone = $this->resolveTimezone();
        $now = $now?->setTimezone($timezone) ?? new DateTimeImmutable('now', $timezone);
        $baseDirectory = $this->resolveBaseDirectory();
        $databaseConfig = $this->resolveDatabaseConfig();
        $database = trim((string) ($databaseConfig['database'] ?? ''));

        if ($database === '') {
            throw new RuntimeException('Nama database aktif tidak tersedia.');
        }

        $dateToken = $now->format('dmY');
        $folderName = $this->folderPrefix().' '.$dateToken;
        $finalDirectory = $baseDirectory.DIRECTORY_SEPARATOR.$folderName;
        $safeDatabase = preg_replace('/[^A-Za-z0-9_-]+/', '_', $database) ?: 'database';
        $backupFilename = $safeDatabase.'_'.$dateToken.'.sql.gz';
        $finalBackupPath = $finalDirectory.DIRECTORY_SEPARATOR.$backupFilename;
        $finalManifestPath = $finalDirectory.DIRECTORY_SEPARATOR.self::MANIFEST_FILENAME;

        if ($dryRun) {
            $binary = $this->resolveDumpBinary();
            $freeSpace = $this->readFreeSpace($baseDirectory, false);

            return $this->result(
                'dry-run',
                $finalDirectory,
                $finalBackupPath,
                $finalManifestPath,
                0,
                0,
                $freeSpace,
                [],
                [
                    'Prasyarat valid. Binary: '.basename($binary).'. Tidak ada file yang ditulis.',
                ]
            );
        }

        $this->ensureBaseDirectoryExists($baseDirectory);
        $lockHandle = $this->acquireLock($baseDirectory);

        try {
            $this->cleanupStaleTemporaryDirectories($baseDirectory);

            if (is_dir($finalDirectory)) {
                $existing = $this->inspectManagedBackupDirectory($baseDirectory, $finalDirectory);
                if ($existing === null) {
                    throw new RuntimeException(
                        "Folder backup hari ini sudah ada tetapi tidak lolos validasi: {$finalDirectory}"
                    );
                }

                [$deletedBackups, $warnings] = $this->pruneRetainedBackups($baseDirectory);

                return $this->result(
                    'skipped',
                    $finalDirectory,
                    $existing['backup_path'],
                    $existing['manifest_path'],
                    (int) ($existing['manifest']['compressed_bytes'] ?? 0),
                    (int) ($existing['manifest']['uncompressed_bytes'] ?? 0),
                    $this->readFreeSpace($baseDirectory),
                    $deletedBackups,
                    $warnings
                );
            }

            $binary = $this->resolveDumpBinary();
            $freeSpace = $this->readFreeSpace($baseDirectory);
            $minimumFreeSpace = max(0, (int) config(
                'database_backup.min_free_space_bytes',
                20 * 1024 * 1024 * 1024
            ));

            if ($freeSpace < $minimumFreeSpace) {
                throw new RuntimeException(sprintf(
                    'Ruang kosong folder backup tidak cukup. Tersedia %s, minimum %s.',
                    $this->formatBytes($freeSpace),
                    $this->formatBytes($minimumFreeSpace)
                ));
            }

            $temporaryDirectory = $baseDirectory.DIRECTORY_SEPARATOR
                .'.backup-project-abah-'.$now->format('Ymd').'-'.bin2hex(random_bytes(6)).'.tmp';

            if (! @mkdir($temporaryDirectory, 0775)) {
                throw new RuntimeException("Gagal membuat folder backup sementara: {$temporaryDirectory}");
            }

            $startedAt = new DateTimeImmutable('now', $timezone);
            $temporaryBackupPath = $temporaryDirectory.DIRECTORY_SEPARATOR.$backupFilename;
            $stderrPath = $temporaryDirectory.DIRECTORY_SEPARATOR.'mysqldump.stderr.log';
            $credentialsPath = $temporaryDirectory.DIRECTORY_SEPARATOR.'.mysql-client.cnf';

            try {
                $this->writeCredentialsFile($credentialsPath, $databaseConfig);
                $command = $this->buildDumpCommand(
                    $binary,
                    $credentialsPath,
                    $databaseConfig,
                    $database
                );

                try {
                    $dump = $this->dumpRunner->run(
                        $command,
                        $temporaryBackupPath,
                        $stderrPath,
                        $this->compressionLevel()
                    );
                } finally {
                    $this->deleteFileIfPresent($credentialsPath);
                }

                $verification = $this->verifyCompressedDump(
                    $temporaryBackupPath,
                    (int) ($dump['uncompressed_bytes'] ?? 0),
                    (string) ($dump['uncompressed_sha256'] ?? ''),
                    (string) ($dump['sql_prefix'] ?? '')
                );

                $compressedBytes = (int) filesize($temporaryBackupPath);
                $compressedHash = hash_file('sha256', $temporaryBackupPath);
                if (! is_string($compressedHash) || $compressedHash === '') {
                    throw new RuntimeException('Hash file backup terkompresi gagal dihitung.');
                }

                $stderrWarning = $this->readStderrWarning($stderrPath);
                $this->deleteFileIfPresent($stderrPath);

                $completedAt = new DateTimeImmutable('now', $timezone);
                $manifest = [
                    'schema_version' => self::MANIFEST_SCHEMA_VERSION,
                    'managed_by' => self::MANAGED_BY,
                    'status' => 'verified',
                    'database' => $database,
                    'backup_date' => $now->format('Y-m-d'),
                    'created_at' => $startedAt->format(DATE_ATOM),
                    'completed_at' => $completedAt->format(DATE_ATOM),
                    'backup_file' => $backupFilename,
                    'compression' => 'gzip',
                    'compression_level' => $this->compressionLevel(),
                    'compressed_bytes' => $compressedBytes,
                    'uncompressed_bytes' => $verification['uncompressed_bytes'],
                    'sha256_compressed' => $compressedHash,
                    'sha256_uncompressed' => $verification['uncompressed_sha256'],
                ];

                $this->writeManifestAtomically(
                    $temporaryDirectory.DIRECTORY_SEPARATOR.self::MANIFEST_FILENAME,
                    $manifest
                );

                if (file_exists($finalDirectory)) {
                    throw new RuntimeException(
                        "Target backup hari ini muncul saat proses berjalan: {$finalDirectory}"
                    );
                }

                if (! @rename($temporaryDirectory, $finalDirectory)) {
                    throw new RuntimeException('Gagal menyelesaikan commit atomik folder backup harian.');
                }
                $temporaryDirectory = null;

                [$deletedBackups, $warnings] = $this->pruneRetainedBackups($baseDirectory);
                if ($stderrWarning !== '') {
                    $warnings[] = 'mysqldump selesai dengan peringatan: '.$stderrWarning;
                }

                return $this->result(
                    'completed',
                    $finalDirectory,
                    $finalBackupPath,
                    $finalManifestPath,
                    $compressedBytes,
                    $verification['uncompressed_bytes'],
                    $this->readFreeSpace($baseDirectory),
                    $deletedBackups,
                    $warnings
                );
            } catch (Throwable $exception) {
                if (is_string($temporaryDirectory) && is_dir($temporaryDirectory)) {
                    $this->removeDirectoryTree($temporaryDirectory);
                }

                throw $exception;
            }
        } finally {
            $this->releaseLock($lockHandle);
        }
    }

    /**
     * @return array<int, string>
     */
    public function managedBackupDirectories(): array
    {
        $baseDirectory = $this->resolveBaseDirectory();
        if (! is_dir($baseDirectory)) {
            return [];
        }

        $directories = [];
        foreach (new \FilesystemIterator($baseDirectory, \FilesystemIterator::SKIP_DOTS) as $item) {
            if (! $item->isDir() || $item->isLink()) {
                continue;
            }

            if ($this->inspectManagedBackupDirectory($baseDirectory, $item->getPathname()) !== null) {
                $directories[] = $item->getPathname();
            }
        }

        sort($directories);

        return $directories;
    }

    private function resolveTimezone(): DateTimeZone
    {
        $name = trim((string) config(
            'database_backup.timezone',
            config('app.timezone', 'Asia/Jakarta')
        ));

        try {
            return new DateTimeZone($name !== '' ? $name : 'Asia/Jakarta');
        } catch (Throwable) {
            throw new RuntimeException("Timezone backup tidak valid: {$name}");
        }
    }

    private function resolveBaseDirectory(): string
    {
        $path = trim((string) config(
            'database_backup.directory',
            'D:\\BACKUP PROJECT ABAH'
        ));

        if ($path === '' || str_contains($path, "\0")) {
            throw new RuntimeException('Folder backup harian belum dikonfigurasi dengan benar.');
        }

        $path = rtrim($path, '\\/');
        $isWindowsAbsolute = preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
        $isUnixAbsolute = str_starts_with($path, '/');
        $isDriveRoot = preg_match('/^[A-Za-z]:$/', $path) === 1;

        if ((! $isWindowsAbsolute && ! $isUnixAbsolute) || $isDriveRoot || $path === '') {
            throw new RuntimeException('Folder backup harus berupa path absolut dan bukan root drive.');
        }

        return $path;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveDatabaseConfig(): array
    {
        $connection = (string) Config::get('database.default');
        $config = Config::get("database.connections.{$connection}");

        if (! is_array($config)) {
            throw new RuntimeException('Konfigurasi koneksi database aktif tidak ditemukan.');
        }

        if (! in_array((string) ($config['driver'] ?? ''), ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('Backup harian hanya mendukung koneksi MySQL/MariaDB.');
        }

        return $config;
    }

    private function ensureBaseDirectoryExists(string $baseDirectory): void
    {
        if (! is_dir($baseDirectory) && ! @mkdir($baseDirectory, 0775, true)) {
            throw new RuntimeException("Folder backup tidak dapat dibuat: {$baseDirectory}");
        }

        if (! is_writable($baseDirectory)) {
            throw new RuntimeException("Folder backup tidak dapat ditulis: {$baseDirectory}");
        }
    }

    /**
     * @return resource
     */
    private function acquireLock(string $baseDirectory)
    {
        $lockPath = $baseDirectory.DIRECTORY_SEPARATOR.'.daily-database-backup.lock';
        $handle = @fopen($lockPath, 'c+b');
        if (! is_resource($handle)) {
            throw new RuntimeException('File lock backup harian tidak dapat dibuat.');
        }

        if (! @flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            throw new RuntimeException('Backup database harian lain masih berjalan.');
        }

        ftruncate($handle, 0);
        fwrite($handle, json_encode([
            'pid' => getmypid(),
            'locked_at' => date(DATE_ATOM),
        ], JSON_UNESCAPED_SLASHES));
        fflush($handle);

        return $handle;
    }

    /**
     * @param  resource  $handle
     */
    private function releaseLock($handle): void
    {
        if (! is_resource($handle)) {
            return;
        }

        @flock($handle, LOCK_UN);
        @fclose($handle);
    }

    private function resolveDumpBinary(): string
    {
        $configured = trim((string) config('database_backup.mysqldump_binary', ''));
        $serviceBinary = trim((string) config('services.system_binaries.mysqldump', ''));
        $candidates = array_values(array_unique(array_filter([
            $configured,
            $serviceBinary,
            'D:\\XAMPP\\mysql\\bin\\mysqldump.exe',
            'C:\\xampp\\mysql\\bin\\mysqldump.exe',
            '/usr/bin/mysqldump',
            '/usr/local/bin/mysqldump',
        ])));

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('Binary mysqldump tidak ditemukan.');
    }

    private function readFreeSpace(string $baseDirectory, bool $requireExisting = true): int
    {
        $probePath = $baseDirectory;
        if (! is_dir($probePath)) {
            if ($requireExisting) {
                throw new RuntimeException("Folder backup tidak ditemukan: {$baseDirectory}");
            }

            $probePath = dirname($baseDirectory);
        }

        $freeSpace = @disk_free_space($probePath);
        if ($freeSpace === false) {
            throw new RuntimeException('Ruang kosong drive backup tidak dapat dibaca.');
        }

        return (int) $freeSpace;
    }

    /**
     * @param  array<string, mixed>  $databaseConfig
     */
    private function writeCredentialsFile(string $path, array $databaseConfig): void
    {
        $lines = [
            '[client]',
            'user='.$this->quoteOptionFileValue((string) ($databaseConfig['username'] ?? '')),
            'password='.$this->quoteOptionFileValue((string) ($databaseConfig['password'] ?? '')),
        ];

        $socket = trim((string) ($databaseConfig['unix_socket'] ?? ''));
        if (PHP_OS_FAMILY !== 'Windows' && $socket !== '') {
            $lines[] = 'socket='.$this->quoteOptionFileValue($socket);
        } else {
            $lines[] = 'protocol=TCP';
            $lines[] = 'host='.$this->quoteOptionFileValue(
                (string) ($databaseConfig['host'] ?? '127.0.0.1')
            );
            $lines[] = 'port='.(int) ($databaseConfig['port'] ?? 3306);
        }

        $payload = implode(PHP_EOL, $lines).PHP_EOL;
        if (file_put_contents($path, $payload, LOCK_EX) !== strlen($payload)) {
            throw new RuntimeException('File kredensial sementara mysqldump gagal dibuat.');
        }

        @chmod($path, 0600);
    }

    private function quoteOptionFileValue(string $value): string
    {
        $escaped = strtr($value, [
            '\\' => '\\\\',
            "\n" => '\\n',
            "\r" => '\\r',
            "\t" => '\\t',
            '"' => '\\"',
        ]);

        return '"'.$escaped.'"';
    }

    /**
     * @param  array<string, mixed>  $databaseConfig
     * @return array<int, string>
     */
    private function buildDumpCommand(
        string $binary,
        string $credentialsPath,
        array $databaseConfig,
        string $database
    ): array {
        $charset = trim((string) ($databaseConfig['charset'] ?? 'utf8mb4'));

        return [
            $binary,
            '--defaults-extra-file='.$credentialsPath,
            '--default-character-set='.($charset !== '' ? $charset : 'utf8mb4'),
            '--single-transaction',
            '--quick',
            '--skip-lock-tables',
            '--skip-comments',
            '--skip-dump-date',
            '--hex-blob',
            '--routines',
            '--triggers',
            '--events',
            '--no-tablespaces',
            '--max-allowed-packet=67108864',
            '--databases',
            $database,
        ];
    }

    private function compressionLevel(): int
    {
        return min(9, max(1, (int) config('database_backup.compression_level', 4)));
    }

    /**
     * @return array{uncompressed_bytes: int, uncompressed_sha256: string}
     */
    private function verifyCompressedDump(
        string $path,
        int $expectedBytes,
        string $expectedHash,
        string $runnerPrefix
    ): array {
        if (! is_file($path) || (int) filesize($path) <= 0) {
            throw new RuntimeException('File gzip backup kosong atau tidak ditemukan.');
        }

        $handle = @gzopen($path, 'rb');
        if (! is_resource($handle)) {
            throw new RuntimeException('File gzip backup tidak dapat dibuka untuk verifikasi.');
        }

        $hashContext = hash_init('sha256');
        $bytes = 0;
        $prefix = '';

        try {
            while (! gzeof($handle)) {
                $chunk = @gzread($handle, 8 * 1024 * 1024);
                if ($chunk === false) {
                    throw new RuntimeException('Integritas gzip backup gagal diverifikasi.');
                }
                if ($chunk === '') {
                    continue;
                }

                $bytes += strlen($chunk);
                hash_update($hashContext, $chunk);
                if (strlen($prefix) < 65536) {
                    $prefix .= substr($chunk, 0, 65536 - strlen($prefix));
                }
            }
        } finally {
            gzclose($handle);
        }

        $hash = hash_final($hashContext);
        if ($bytes <= 0 || $bytes !== $expectedBytes || ! hash_equals($expectedHash, $hash)) {
            throw new RuntimeException('Ukuran atau checksum hasil verifikasi backup tidak cocok.');
        }

        if ($runnerPrefix !== '' && ! hash_equals(
            hash('sha256', $runnerPrefix),
            hash('sha256', substr($prefix, 0, strlen($runnerPrefix)))
        )) {
            throw new RuntimeException('Prefix SQL berubah saat verifikasi gzip.');
        }

        $this->assertSqlPrefixIsValid($prefix);

        return [
            'uncompressed_bytes' => $bytes,
            'uncompressed_sha256' => $hash,
        ];
    }

    private function assertSqlPrefixIsValid(string $prefix): void
    {
        $prefix = ltrim(str_starts_with($prefix, "\xEF\xBB\xBF") ? substr($prefix, 3) : $prefix);
        if ($prefix === '' || preg_match('/^(<!DOCTYPE\s+html|<html\b)/i', $prefix) === 1) {
            throw new RuntimeException('Output mysqldump bukan SQL yang valid.');
        }

        $looksLikeSql = preg_match(
            '/(\/\*(?:M)?![0-9]{5,6}|CREATE\s+(?:DATABASE|TABLE|VIEW)|'
            .'DROP\s+TABLE|INSERT\s+INTO|SET\s+NAMES|USE\s+`)/i',
            $prefix
        ) === 1;

        if (! $looksLikeSql) {
            throw new RuntimeException('Output mysqldump tidak memuat struktur SQL yang dikenali.');
        }
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function writeManifestAtomically(string $manifestPath, array $manifest): void
    {
        try {
            $json = json_encode(
                $manifest,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            ).PHP_EOL;
        } catch (Throwable) {
            throw new RuntimeException('Manifest backup gagal diserialisasi.');
        }

        $partPath = $manifestPath.'.part';
        $handle = @fopen($partPath, 'xb');
        if (! is_resource($handle)) {
            throw new RuntimeException('File manifest sementara tidak dapat dibuat.');
        }

        try {
            $offset = 0;
            $length = strlen($json);
            while ($offset < $length) {
                $written = fwrite($handle, substr($json, $offset));
                if ($written === false || $written === 0) {
                    throw new RuntimeException('Manifest backup gagal ditulis.');
                }
                $offset += $written;
            }

            if (! fflush($handle)) {
                throw new RuntimeException('Manifest backup gagal disimpan ke disk.');
            }
            if (function_exists('fsync')) {
                @fsync($handle);
            }
        } finally {
            fclose($handle);
        }

        if (! @rename($partPath, $manifestPath)) {
            @unlink($partPath);
            throw new RuntimeException('Manifest backup gagal diselesaikan secara atomik.');
        }
    }

    private function readStderrWarning(string $path): string
    {
        if (! is_file($path) || (int) filesize($path) === 0) {
            return '';
        }

        $handle = @fopen($path, 'rb');
        if (! is_resource($handle)) {
            return 'File peringatan mysqldump tidak dapat dibaca.';
        }

        $size = (int) filesize($path);
        if ($size > 8192) {
            fseek($handle, -8192, SEEK_END);
        }
        $content = trim((string) stream_get_contents($handle));
        fclose($handle);

        $content = preg_replace('/\s+/', ' ', $content) ?? $content;

        return substr($content, 0, 1000);
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    private function pruneRetainedBackups(string $baseDirectory): array
    {
        $managed = [];
        $warnings = [];

        foreach (new \FilesystemIterator($baseDirectory, \FilesystemIterator::SKIP_DOTS) as $item) {
            if (! $item->isDir() || $item->isLink()) {
                continue;
            }

            $path = $item->getPathname();
            if (! $this->matchesManagedFolderName($item->getFilename())) {
                if (preg_match('/^\d{8}$/', $item->getFilename()) !== 1) {
                    continue;
                }

                $legacy = $this->inspectLegacyBackupDirectory($baseDirectory, $path);
                if ($legacy === null) {
                    $warnings[] = 'Folder backup legacy tidak valid dan tidak dihapus: '
                        .$item->getFilename();

                    continue;
                }

                $managed[] = $legacy;

                continue;
            }

            $inspected = $this->inspectManagedBackupDirectory($baseDirectory, $path);
            if ($inspected === null) {
                $warnings[] = 'Folder menyerupai backup terkelola tetapi tidak valid dan tidak dihapus: '
                    .$item->getFilename();

                continue;
            }

            $managed[] = $inspected;
        }

        usort($managed, static function (array $left, array $right): int {
            return strcmp($right['backup_date'], $left['backup_date']);
        });

        $retentionCount = max(1, (int) config('database_backup.retention_count', 2));
        $expired = array_slice($managed, $retentionCount);
        $deleted = [];

        foreach ($expired as $backup) {
            try {
                $this->deleteRetainedBackup($backup);
                $deleted[] = basename($backup['directory']);
            } catch (Throwable $exception) {
                $warnings[] = 'Retensi gagal menghapus '.basename($backup['directory'])
                    .': '.$exception->getMessage();
            }
        }

        return [$deleted, $warnings];
    }

    /**
     * Recognize the previous backup layout only for retention migration. A
     * legacy directory must be a direct DDMMYYYY child containing exactly one
     * readable SQL gzip file; every other directory remains untouched.
     *
     * @return array{
     *     directory: string,
     *     backup_path: string,
     *     backup_date: string,
     *     legacy: true
     * }|null
     */
    private function inspectLegacyBackupDirectory(
        string $baseDirectory,
        string $directory
    ): ?array {
        $folderName = basename($directory);
        if (preg_match('/^\d{8}$/', $folderName) !== 1 || is_link($directory)) {
            return null;
        }

        $realBase = realpath($baseDirectory);
        $realDirectory = realpath($directory);
        if ($realBase === false || $realDirectory === false
            || $this->normalizePath(dirname($realDirectory)) !== $this->normalizePath($realBase)) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!dmY', $folderName, $this->resolveTimezone());
        $dateErrors = DateTimeImmutable::getLastErrors();
        if ($date === false
            || (is_array($dateErrors)
                && (($dateErrors['warning_count'] ?? 0) > 0 || ($dateErrors['error_count'] ?? 0) > 0))
            || $date->format('dmY') !== $folderName) {
            return null;
        }

        $entries = array_values(array_diff(scandir($directory) ?: [], ['.', '..']));
        if (count($entries) !== 1) {
            return null;
        }

        $backupFilename = $entries[0];
        $backupPath = $directory.DIRECTORY_SEPARATOR.$backupFilename;
        if ($backupFilename !== basename($backupFilename)
            || ! str_ends_with(strtolower($backupFilename), '.sql.gz')
            || ! is_file($backupPath)
            || is_link($backupPath)
            || (int) filesize($backupPath) <= 0) {
            return null;
        }

        $handle = @gzopen($backupPath, 'rb');
        if (! is_resource($handle)) {
            return null;
        }

        try {
            $prefix = @gzread($handle, 65536);
            if (! is_string($prefix) || $prefix === '') {
                return null;
            }

            try {
                $this->assertSqlPrefixIsValid($prefix);
            } catch (Throwable) {
                return null;
            }
        } finally {
            gzclose($handle);
        }

        return [
            'directory' => $directory,
            'backup_path' => $backupPath,
            'backup_date' => $date->format('Y-m-d'),
            'legacy' => true,
        ];
    }

    /**
     * @return array{
     *     directory: string,
     *     backup_path: string,
     *     manifest_path: string,
     *     backup_date: string,
     *     manifest: array<string, mixed>
     * }|null
     */
    private function inspectManagedBackupDirectory(
        string $baseDirectory,
        string $directory
    ): ?array {
        $folderName = basename($directory);
        if (! $this->matchesManagedFolderName($folderName) || is_link($directory)) {
            return null;
        }

        $realBase = realpath($baseDirectory);
        $realDirectory = realpath($directory);
        if ($realBase === false || $realDirectory === false
            || $this->normalizePath(dirname($realDirectory)) !== $this->normalizePath($realBase)) {
            return null;
        }

        $dateToken = substr($folderName, strlen($this->folderPrefix()) + 1);
        $date = DateTimeImmutable::createFromFormat('!dmY', $dateToken, $this->resolveTimezone());
        $dateErrors = DateTimeImmutable::getLastErrors();
        if ($date === false
            || (is_array($dateErrors)
                && (($dateErrors['warning_count'] ?? 0) > 0 || ($dateErrors['error_count'] ?? 0) > 0))
            || $date->format('dmY') !== $dateToken) {
            return null;
        }

        $manifestPath = $directory.DIRECTORY_SEPARATOR.self::MANIFEST_FILENAME;
        if (! is_file($manifestPath) || is_link($manifestPath)) {
            return null;
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        if (! is_array($manifest)
            || (int) ($manifest['schema_version'] ?? 0) !== self::MANIFEST_SCHEMA_VERSION
            || ($manifest['managed_by'] ?? null) !== self::MANAGED_BY
            || ($manifest['status'] ?? null) !== 'verified'
            || ($manifest['backup_date'] ?? null) !== $date->format('Y-m-d')
            || ($manifest['compression'] ?? null) !== 'gzip') {
            return null;
        }

        $backupFilename = (string) ($manifest['backup_file'] ?? '');
        if ($backupFilename === ''
            || $backupFilename !== basename($backupFilename)
            || str_contains($backupFilename, "\0")
            || ! str_ends_with(strtolower($backupFilename), '.sql.gz')) {
            return null;
        }

        $backupPath = $directory.DIRECTORY_SEPARATOR.$backupFilename;
        if (! is_file($backupPath) || is_link($backupPath) || (int) filesize($backupPath) <= 0) {
            return null;
        }

        $entries = array_values(array_diff(scandir($directory) ?: [], ['.', '..']));
        sort($entries);
        $expectedEntries = [$backupFilename, self::MANIFEST_FILENAME];
        sort($expectedEntries);
        if ($entries !== $expectedEntries) {
            return null;
        }

        if (! preg_match('/^[a-f0-9]{64}$/', (string) ($manifest['sha256_compressed'] ?? ''))
            || ! preg_match('/^[a-f0-9]{64}$/', (string) ($manifest['sha256_uncompressed'] ?? ''))
            || (int) ($manifest['compressed_bytes'] ?? 0) !== (int) filesize($backupPath)
            || (int) ($manifest['uncompressed_bytes'] ?? 0) <= 0) {
            return null;
        }

        return [
            'directory' => $directory,
            'backup_path' => $backupPath,
            'manifest_path' => $manifestPath,
            'backup_date' => $date->format('Y-m-d'),
            'manifest' => $manifest,
        ];
    }

    private function matchesManagedFolderName(string $name): bool
    {
        return preg_match(
            '/^'.preg_quote($this->folderPrefix(), '/').' \d{8}$/',
            $name
        ) === 1;
    }

    private function folderPrefix(): string
    {
        $prefix = trim((string) config('database_backup.folder_prefix', 'backup project-abah'));
        if ($prefix === '' || str_contains($prefix, DIRECTORY_SEPARATOR) || str_contains($prefix, '/')) {
            throw new RuntimeException('Prefix folder backup harian tidak valid.');
        }

        return $prefix;
    }

    /**
     * @param  array{directory: string, backup_path: string, backup_date: string}  $backup
     */
    private function deleteRetainedBackup(array $backup): void
    {
        $quarantineDirectory = dirname($backup['directory']).DIRECTORY_SEPARATOR
            .'.prune-project-abah-'
            .str_replace('-', '', $backup['backup_date'])
            .'-'.bin2hex(random_bytes(6)).'.tmp';

        if (! @rename($backup['directory'], $quarantineDirectory)) {
            throw new RuntimeException('folder tidak dapat dipindahkan ke area retensi');
        }

        $this->removeDirectoryTree($quarantineDirectory);
        if (is_dir($quarantineDirectory)) {
            throw new RuntimeException('folder retensi belum dapat dihapus seluruhnya');
        }
    }

    private function cleanupStaleTemporaryDirectories(string $baseDirectory): void
    {
        foreach (new \FilesystemIterator($baseDirectory, \FilesystemIterator::SKIP_DOTS) as $item) {
            if (! $item->isDir() || $item->isLink()) {
                continue;
            }

            $temporaryName = $item->getFilename();
            $isDumpTemporary = preg_match(
                '/^\.backup-project-abah-\d{8}-[a-f0-9]{12}\.tmp$/',
                $temporaryName
            ) === 1;
            $isPruneTemporary = preg_match(
                '/^\.prune-project-abah-\d{8}-[a-f0-9]{12}\.tmp$/',
                $temporaryName
            ) === 1;

            if (! $isDumpTemporary && ! $isPruneTemporary) {
                continue;
            }

            $this->removeDirectoryTree($item->getPathname());
            if (is_dir($item->getPathname())) {
                throw new RuntimeException(
                    'Folder sementara backup lama tidak dapat dibersihkan: '.$temporaryName
                );
            }
        }
    }

    private function removeDirectoryTree(string $directory): void
    {
        if (! is_dir($directory) || is_link($directory)) {
            if (is_link($directory) || is_file($directory)) {
                @unlink($directory);
            }

            return;
        }

        foreach (new \FilesystemIterator($directory, \FilesystemIterator::SKIP_DOTS) as $item) {
            if ($item->isLink() || $item->isFile()) {
                @unlink($item->getPathname());

                continue;
            }

            if ($item->isDir()) {
                $this->removeDirectoryTree($item->getPathname());
            }
        }

        @rmdir($directory);
    }

    private function deleteFileIfPresent(string $path): void
    {
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function normalizePath(string $path): string
    {
        $normalized = rtrim(str_replace('\\', '/', $path), '/');

        return PHP_OS_FAMILY === 'Windows' ? strtolower($normalized) : $normalized;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KiB', 'MiB', 'GiB', 'TiB'];
        $value = max(0, $bytes);
        $unit = 0;

        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }

        return number_format($value, $unit === 0 ? 0 : 2, ',', '.').' '.$units[$unit];
    }

    /**
     * @param  array<int, string>  $deletedBackups
     * @param  array<int, string>  $warnings
     * @return array{
     *     status: string,
     *     directory: string,
     *     backup_file: string|null,
     *     manifest_file: string|null,
     *     compressed_bytes: int,
     *     uncompressed_bytes: int,
     *     free_space_bytes: int,
     *     deleted_backups: array<int, string>,
     *     warnings: array<int, string>
     * }
     */
    private function result(
        string $status,
        string $directory,
        ?string $backupFile,
        ?string $manifestFile,
        int $compressedBytes,
        int $uncompressedBytes,
        int $freeSpaceBytes,
        array $deletedBackups,
        array $warnings
    ): array {
        return [
            'status' => $status,
            'directory' => $directory,
            'backup_file' => $backupFile,
            'manifest_file' => $manifestFile,
            'compressed_bytes' => $compressedBytes,
            'uncompressed_bytes' => $uncompressedBytes,
            'free_space_bytes' => $freeSpaceBytes,
            'deleted_backups' => $deletedBackups,
            'warnings' => array_values($warnings),
        ];
    }
}
