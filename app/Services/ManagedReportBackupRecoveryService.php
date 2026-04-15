<?php

namespace App\Services;

use App\Models\NamaReport;
use App\Support\ReportDataSyncService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class ManagedReportBackupRecoveryService
{
    private const BACKUP_DIRECTORY = 'private/database_backups';
    private const TEMP_SQL_DIRECTORY = 'private/database_backups/_restore_work';
    private const TOTAL_UNITS = 6;

    public function __construct(
        private readonly ReportDataSyncService $reportDataSyncService
    ) {
    }

    public function recoverReportTable(int $reportId, string $backupRelativePath, ?callable $progressCallback = null): array
    {
        [$report, $tableName, $backupPath, $backupName] = $this->validateRecoveryRequest($reportId, $backupRelativePath, $progressCallback);

        $timestamp = now()->format('Ymd_His');
        $tempToken = substr(md5($tableName . '|' . $backupName . '|' . microtime(true)), 0, 10);
        $tempTable = substr('__restore_' . preg_replace('/[^A-Za-z0-9_]+/', '_', $tableName) . '_' . $tempToken, 0, 60);
        $tempSqlPath = $this->buildTempSqlPath($tableName, $timestamp, $tempToken);

        $this->dropTemporaryTable($tempTable);

        try {
            $extracted = $this->extractTableSqlFromBackup(
                $backupPath,
                $tableName,
                $tempTable,
                $tempSqlPath,
                $progressCallback
            );

            $this->emitProgress($progressCallback, [
                'stage' => 'importing_backup',
                'message' => 'Mengimpor hasil ekstraksi backup ke tabel staging sementara...',
                'completed_units' => 3,
                'total_units' => self::TOTAL_UNITS,
                'progress_percent' => 58,
            ]);

            $this->importSqlFileIntoCurrentDatabase($tempSqlPath, $progressCallback);

            if (!Schema::hasTable($tempTable)) {
                throw new RuntimeException("Tabel staging `{$tempTable}` tidak ditemukan setelah proses import backup.");
            }

            $restoredRows = $this->swapRecoveredTableData($tableName, $tempTable, $progressCallback);

            $this->emitProgress($progressCallback, [
                'stage' => 'syncing',
                'message' => 'Menyegarkan statistik optimizer, snapshot, dan cache report...',
                'completed_units' => 5,
                'total_units' => self::TOTAL_UNITS,
                'progress_percent' => 90,
            ]);

            $this->reportDataSyncService->syncImportedTable(
                $tableName,
                null,
                null,
                static::class . '::recoverReportTable'
            );

            $this->emitProgress($progressCallback, [
                'stage' => 'cleanup',
                'message' => 'Membersihkan tabel staging dan file kerja sementara...',
                'completed_units' => 6,
                'total_units' => self::TOTAL_UNITS,
                'progress_percent' => 97,
            ]);

            $this->dropTemporaryTable($tempTable);
            @unlink($tempSqlPath);

            return [
                'report_id' => (int) $report->id_report,
                'report_name' => (string) ($report->nama_report ?? $tableName),
                'table_name' => $tableName,
                'backup_name' => $backupName,
                'restored_rows' => $restoredRows,
                'matched_statements' => (int) ($extracted['matched_statements'] ?? 0),
            ];
        } catch (Throwable $e) {
            $this->dropTemporaryTable($tempTable);
            if (is_file($tempSqlPath)) {
                @unlink($tempSqlPath);
            }

            throw $e;
        }
    }

    private function validateRecoveryRequest(int $reportId, string $backupRelativePath, ?callable $progressCallback): array
    {
        $this->emitProgress($progressCallback, [
            'stage' => 'validating',
            'message' => 'Memvalidasi report yang dipilih dan file backup SQL...',
            'completed_units' => 0,
            'total_units' => self::TOTAL_UNITS,
            'progress_percent' => 5,
        ]);

        $report = NamaReport::query()
            ->where('active', 1)
            ->where('id_report', $reportId)
            ->first();

        if (!$report) {
            throw new RuntimeException('Report tidak ditemukan.');
        }

        $tableName = trim((string) ($report->table_name ?? ''));
        if ($tableName === '' || !Schema::hasTable($tableName)) {
            throw new RuntimeException("Tabel report `{$tableName}` tidak ditemukan.");
        }

        $backupPath = $this->resolveBackupPath($backupRelativePath);
        if ($backupPath === null || !is_file($backupPath)) {
            throw new RuntimeException('File backup tidak ditemukan atau berada di luar folder backup terkelola.');
        }

        $backupName = basename($backupPath);
        if (strtolower(pathinfo($backupName, PATHINFO_EXTENSION)) !== 'sql') {
            throw new RuntimeException('File backup harus berformat `.sql`.');
        }

        return [$report, $tableName, $backupPath, $backupName];
    }

    private function extractTableSqlFromBackup(
        string $backupPath,
        string $targetTable,
        string $tempTable,
        string $outputPath,
        ?callable $progressCallback
    ): array {
        $this->emitProgress($progressCallback, [
            'stage' => 'extracting_backup',
            'message' => 'Memindai file backup dan mengekstrak statement untuk report yang dipilih...',
            'completed_units' => 1,
            'total_units' => self::TOTAL_UNITS,
            'progress_percent' => 14,
        ]);

        $workDirectory = dirname($outputPath);
        if (!is_dir($workDirectory)) {
            File::makeDirectory($workDirectory, 0755, true);
        }

        $reader = fopen($backupPath, 'rb');
        $writer = fopen($outputPath, 'wb');

        if (!is_resource($reader) || !is_resource($writer)) {
            if (is_resource($reader)) {
                fclose($reader);
            }
            if (is_resource($writer)) {
                fclose($writer);
            }

            throw new RuntimeException('File backup tidak bisa dibaca untuk proses recovery.');
        }

        fwrite($writer, "SET FOREIGN_KEY_CHECKS=0;\n");
        fwrite($writer, "SET UNIQUE_CHECKS=0;\n");
        fwrite($writer, "SET SQL_NOTES=0;\n");

        $buffer = '';
        $bytesRead = 0;
        $backupSize = max(1, (int) filesize($backupPath));
        $lastProgressBucket = -1;
        $matchedStatements = 0;
        $schemaMatched = false;
        $dataMatched = false;

        try {
            while (($line = fgets($reader)) !== false) {
                $bytesRead += strlen($line);
                $buffer .= $line;

                if (!$this->statementEnds($line)) {
                    continue;
                }

                $statement = trim($buffer);
                $buffer = '';

                if ($statement === '' || $this->shouldSkipStatement($statement)) {
                    $lastProgressBucket = $this->emitExtractionProgress($progressCallback, $bytesRead, $backupSize, $lastProgressBucket);
                    continue;
                }

                $rewritten = $this->rewriteStatementForRestore($statement, $targetTable, $tempTable);
                if ($rewritten !== null) {
                    fwrite($writer, $rewritten . "\n\n");
                    $matchedStatements++;
                    $schemaMatched = $schemaMatched || $this->isSchemaStatement($statement, $targetTable);
                    $dataMatched = $dataMatched || $this->isDataStatement($statement, $targetTable);
                }

                $lastProgressBucket = $this->emitExtractionProgress($progressCallback, $bytesRead, $backupSize, $lastProgressBucket);
            }
        } finally {
            fwrite($writer, "SET SQL_NOTES=1;\n");
            fwrite($writer, "SET UNIQUE_CHECKS=1;\n");
            fwrite($writer, "SET FOREIGN_KEY_CHECKS=1;\n");
            fclose($reader);
            fclose($writer);
        }

        if ($matchedStatements === 0 || !$schemaMatched) {
            @unlink($outputPath);
            throw new RuntimeException("Tabel `{$targetTable}` tidak ditemukan di file backup yang dipilih.");
        }

        return [
            'matched_statements' => $matchedStatements,
            'has_data' => $dataMatched,
            'output_path' => $outputPath,
        ];
    }

    private function emitExtractionProgress(?callable $progressCallback, int $bytesRead, int $backupSize, int $lastProgressBucket): int
    {
        $currentBucket = (int) floor(($bytesRead / max(1, $backupSize)) * 100);
        if ($currentBucket === $lastProgressBucket) {
            return $lastProgressBucket;
        }

        $percent = 14 + (int) round(min(1, $bytesRead / max(1, $backupSize)) * 31);

        $this->emitProgress($progressCallback, [
            'stage' => 'extracting_backup',
            'message' => 'Memindai backup SQL dan mengekstrak statement tabel report...',
            'completed_units' => 2,
            'total_units' => self::TOTAL_UNITS,
            'progress_percent' => $percent,
            'bytes_read' => $bytesRead,
            'total_bytes' => $backupSize,
        ]);

        return $currentBucket;
    }

    private function importSqlFileIntoCurrentDatabase(string $sqlPath, ?callable $progressCallback): void
    {
        $config = $this->databaseConfig();
        $command = [
            $this->resolveMysqlBinaryPath(),
            '--host=' . (string) ($config['host'] ?? '127.0.0.1'),
            '--port=' . (string) ($config['port'] ?? '3306'),
            '--user=' . (string) ($config['username'] ?? ''),
            '--default-character-set=' . (string) ($config['charset'] ?? 'utf8mb4'),
            '--binary-mode',
            (string) ($config['database'] ?? ''),
        ];

        $environment = $this->mysqlEnvironment($config);
        $nullDevice = strtoupper(substr(PHP_OS_FAMILY, 0, 7)) === 'WINDOWS' ? 'NUL' : '/dev/null';
        $descriptors = [
            0 => ['file', $sqlPath, 'r'],
            1 => ['file', $nullDevice, 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open(
            $command,
            $descriptors,
            $pipes,
            base_path(),
            $environment,
            ['bypass_shell' => true]
        );

        if (!is_resource($process)) {
            throw new RuntimeException('Gagal menjalankan proses import SQL recovery.');
        }

        $startedAt = microtime(true);

        try {
            while (true) {
                $status = proc_get_status($process);
                if (!($status['running'] ?? false)) {
                    break;
                }

                $elapsedSeconds = max(0, (int) floor(microtime(true) - $startedAt));
                $progress = min(72, 58 + max(0, min(14, $elapsedSeconds)));

                $this->emitProgress($progressCallback, [
                    'stage' => 'importing_backup',
                    'message' => $elapsedSeconds > 0
                        ? "Mengimpor hasil ekstraksi backup ke staging sementara... ({$elapsedSeconds} detik)"
                        : 'Mengimpor hasil ekstraksi backup ke staging sementara...',
                    'completed_units' => 3,
                    'total_units' => self::TOTAL_UNITS,
                    'progress_percent' => $progress,
                ]);

                usleep(500000);
            }

            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[2]);
            $exitCode = proc_close($process);

            if ($exitCode !== 0) {
                throw new RuntimeException(
                    'Import staging backup gagal' . ($stderr !== '' ? ': ' . trim($stderr) : '.')
                );
            }
        } catch (Throwable $e) {
            if (isset($pipes[2]) && is_resource($pipes[2])) {
                fclose($pipes[2]);
            }
            @proc_terminate($process);
            @proc_close($process);

            throw $e;
        }
    }

    private function swapRecoveredTableData(string $targetTable, string $tempTable, ?callable $progressCallback): int
    {
        $this->emitProgress($progressCallback, [
            'stage' => 'swapping_data',
            'message' => 'Mengganti isi tabel report aktif dengan data hasil recovery...',
            'completed_units' => 4,
            'total_units' => self::TOTAL_UNITS,
            'progress_percent' => 76,
        ]);

        $targetColumns = Schema::getColumnListing($targetTable);
        $tempColumns = array_map(
            static fn ($row): string => (string) ($row->Field ?? ''),
            DB::select('SHOW COLUMNS FROM `' . str_replace('`', '``', $tempTable) . '`')
        );

        $tempLookup = array_fill_keys($tempColumns, true);
        $matchedColumns = array_values(array_filter($targetColumns, static fn (string $column): bool => isset($tempLookup[$column])));

        if ($matchedColumns === []) {
            throw new RuntimeException("Tidak ada kolom yang cocok antara tabel aktif `{$targetTable}` dan hasil backup.");
        }

        $quotedColumns = implode(', ', array_map(
            static fn (string $column): string => '`' . str_replace('`', '``', $column) . '`',
            $matchedColumns
        ));

        $restoredRows = (int) DB::table($tempTable)->count();
        DB::statement('SET SESSION foreign_key_checks = 0');

        try {
            DB::statement('TRUNCATE TABLE `' . str_replace('`', '``', $targetTable) . '`');

            if ($restoredRows > 0) {
                DB::statement(
                    'INSERT INTO `' . str_replace('`', '``', $targetTable) . '` (' . $quotedColumns . ') ' .
                    'SELECT ' . $quotedColumns . ' FROM `' . str_replace('`', '``', $tempTable) . '`'
                );
            }
        } finally {
            DB::statement('SET SESSION foreign_key_checks = 1');
        }

        return $restoredRows;
    }

    private function statementEnds(string $line): bool
    {
        return preg_match('/;\s*$/', rtrim($line)) === 1;
    }

    private function shouldSkipStatement(string $statement): bool
    {
        $trimmed = ltrim($statement);

        return preg_match('/^(--|\/\*[^!]|USE\s+|CREATE\s+DATABASE|DROP\s+DATABASE|LOCK\s+TABLES|UNLOCK\s+TABLES)/i', $trimmed) === 1;
    }

    private function rewriteStatementForRestore(string $statement, string $targetTable, string $tempTable): ?string
    {
        $patterns = [
            '/^DROP TABLE IF EXISTS `' . preg_quote($targetTable, '/') . '`/i',
            '/^CREATE TABLE `' . preg_quote($targetTable, '/') . '`/i',
            '/^INSERT INTO `' . preg_quote($targetTable, '/') . '`/i',
            '/^\/\*![0-9]{5} ALTER TABLE `' . preg_quote($targetTable, '/') . '` (DISABLE|ENABLE) KEYS \*\//i',
            '/^ALTER TABLE `' . preg_quote($targetTable, '/') . '`/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, ltrim($statement)) === 1) {
                return preg_replace(
                    '/`' . preg_quote($targetTable, '/') . '`/i',
                    '`' . $tempTable . '`',
                    $statement,
                    1
                );
            }
        }

        return null;
    }

    private function isSchemaStatement(string $statement, string $targetTable): bool
    {
        return preg_match('/^(DROP TABLE IF EXISTS|CREATE TABLE|ALTER TABLE|\/\*![0-9]{5} ALTER TABLE) `' . preg_quote($targetTable, '/') . '`/i', ltrim($statement)) === 1;
    }

    private function isDataStatement(string $statement, string $targetTable): bool
    {
        return preg_match('/^INSERT INTO `' . preg_quote($targetTable, '/') . '`/i', ltrim($statement)) === 1;
    }

    private function buildTempSqlPath(string $tableName, string $timestamp, string $tempToken): string
    {
        $safeTable = preg_replace('/[^A-Za-z0-9_-]+/', '_', $tableName) ?: 'report';

        return storage_path('app/' . self::TEMP_SQL_DIRECTORY . '/' . $safeTable . '_' . $timestamp . '_' . $tempToken . '.sql');
    }

    private function dropTemporaryTable(string $tempTable): void
    {
        if (Schema::hasTable($tempTable)) {
            DB::statement('DROP TABLE `' . str_replace('`', '``', $tempTable) . '`');
        }
    }

    private function resolveBackupPath(string $backupRelativePath): ?string
    {
        $normalized = trim(str_replace('\\', '/', $backupRelativePath));
        if ($normalized === '' || str_contains($normalized, '..')) {
            return null;
        }

        $base = storage_path('app/' . self::BACKUP_DIRECTORY);
        $candidate = storage_path('app/' . ltrim($normalized, '/'));
        $real = realpath($candidate);
        if ($real === false) {
            return null;
        }

        $normalizedBase = strtolower(str_replace('\\', '/', $base));
        $normalizedReal = strtolower(str_replace('\\', '/', $real));

        return str_starts_with($normalizedReal, rtrim($normalizedBase, '/') . '/')
            ? $real
            : null;
    }

    private function databaseConfig(): array
    {
        $connectionName = Config::get('database.default');
        $config = Config::get("database.connections.{$connectionName}");

        if (!is_array($config)) {
            throw new RuntimeException('Konfigurasi database aktif tidak ditemukan.');
        }

        $driver = strtolower((string) ($config['driver'] ?? ''));
        if (!in_array($driver, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('Recovery dari backup SQL hanya didukung untuk MySQL/MariaDB.');
        }

        return $config;
    }

    private function mysqlEnvironment(array $config): array
    {
        $environment = [];
        $password = (string) ($config['password'] ?? '');
        if ($password !== '') {
            $environment['MYSQL_PWD'] = $password;
        }

        return $environment;
    }

    private function resolveMysqlBinaryPath(): string
    {
        $configured = trim((string) env('MYSQL_BINARY', ''));
        $candidates = array_values(array_filter([
            $configured !== '' ? $configured : null,
            'C:\\xampp\\mysql\\bin\\mysql.exe',
            'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysql.exe',
            'C:\\Program Files\\MariaDB 11.0\\bin\\mysql.exe',
            'mysql',
        ]));

        foreach ($candidates as $candidate) {
            if ($candidate === 'mysql' || is_file($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('Binary mysql client tidak ditemukan.');
    }

    private function emitProgress(?callable $progressCallback, array $payload): void
    {
        if ($progressCallback !== null) {
            $progressCallback($payload);
        }
    }
}
