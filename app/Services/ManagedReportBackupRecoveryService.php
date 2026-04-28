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

            // Validate temp table data before swap
            $tempTableRowCount = (int) DB::table($tempTable)->count();
            if ($tempTableRowCount === 0) {
                throw new RuntimeException("Tabel staging `{$tempTable}` kosong. File backup mungkin tidak mengandung data valid.");
            }

            $restoredRows = $this->swapRecoveredTableData($tableName, $tempTable, $progressCallback);

            // Validate restored data
            $actualRowCount = (int) DB::table($tableName)->count();
            if ($actualRowCount !== $restoredRows) {
                throw new RuntimeException(
                    sprintf(
                        'Data integrity check gagal: expected %d rows, tapi ditemukan %d rows di tabel %s. Silakan jalankan recovery ulang.',
                        $restoredRows,
                        $actualRowCount,
                        $tableName
                    )
                );
            }

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

            $this->emitProgress($progressCallback, [
                'stage' => 'completed',
                'message' => sprintf('Recovery selesai: %d baris data berhasil dipulihkan.', $restoredRows),
                'completed_units' => 6,
                'total_units' => self::TOTAL_UNITS,
                'progress_percent' => 100,
            ]);

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
        $extension = strtolower(pathinfo($backupName, PATHINFO_EXTENSION));
        $isSqlBackup = $extension === 'sql';
        $isCompressedSqlBackup = $extension === 'gz' && str_ends_with(strtolower($backupName), '.sql.gz');
        if (!$isSqlBackup && !$isCompressedSqlBackup) {
            throw new RuntimeException('File backup harus berformat `.sql` atau `.sql.gz`.');
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

        $nativeExtraction = $this->extractTableSqlWithNativeTool(
            $backupPath,
            $targetTable,
            $tempTable,
            $outputPath,
            $progressCallback
        );

        if (is_array($nativeExtraction)) {
            return $nativeExtraction;
        }

        $isCompressed = $this->isCompressedBackupPath($backupPath);
        $reader = $isCompressed ? gzopen($backupPath, 'rb') : fopen($backupPath, 'rb');
        $writer = fopen($outputPath, 'wb');

        if ((!$isCompressed && !is_resource($reader)) || !is_resource($writer)) {
            if (!$isCompressed && is_resource($reader)) {
                fclose($reader);
            } elseif ($isCompressed && is_resource($reader)) {
                gzclose($reader);
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
            while (($line = $isCompressed ? gzgets($reader) : fgets($reader)) !== false) {
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
            if ($isCompressed) {
                gzclose($reader);
            } else {
                fclose($reader);
            }
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
            0 => ['pipe', 'r'],
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

        $source = fopen($sqlPath, 'rb');
        if (!is_resource($source)) {
            @proc_terminate($process);
            @proc_close($process);

            throw new RuntimeException('SQL hasil ekstraksi tidak bisa dibaca untuk diimpor.');
        }

        $stdin = $pipes[0] ?? null;
        $stderrPipe = $pipes[2] ?? null;
        $totalBytes = max(1, (int) filesize($sqlPath));
        $bytesWritten = 0;

        try {
            while (!feof($source)) {
                $chunk = fread($source, 1024 * 1024);
                if ($chunk === false) {
                    throw new RuntimeException('Gagal membaca hasil ekstraksi backup untuk import.');
                }

                if ($chunk === '') {
                    continue;
                }

                $written = fwrite($stdin, $chunk);
                if ($written === false) {
                    throw new RuntimeException('Gagal menulis data hasil ekstraksi ke proses mysql.');
                }

                $bytesWritten += $written;
                $progress = 58 + (int) round(min(1, $bytesWritten / $totalBytes) * 14);

                $this->emitProgress($progressCallback, [
                    'stage' => 'importing_backup',
                    'message' => sprintf(
                        'Mengimpor hasil ekstraksi backup ke staging sementara... (%s / %s)',
                        $this->formatHumanBytes($bytesWritten),
                        $this->formatHumanBytes($totalBytes)
                    ),
                    'completed_units' => 3,
                    'total_units' => self::TOTAL_UNITS,
                    'progress_percent' => $progress,
                    'bytes_written' => $bytesWritten,
                    'total_bytes' => $totalBytes,
                ]);
            }

            fclose($source);
            if (is_resource($stdin)) {
                fclose($stdin);
            }

            while (true) {
                $status = proc_get_status($process);
                if (!($status['running'] ?? false)) {
                    break;
                }

                usleep(200000);
            }

            $stderr = is_resource($stderrPipe) ? stream_get_contents($stderrPipe) : '';
            if (is_resource($stderrPipe)) {
                fclose($stderrPipe);
            }
            $exitCode = proc_close($process);

            if ($exitCode !== 0) {
                throw new RuntimeException(
                    'Import staging backup gagal' . ($stderr !== '' ? ': ' . trim($stderr) : '.')
                );
            }
        } catch (Throwable $e) {
            if (isset($source) && is_resource($source)) {
                fclose($source);
            }
            if (isset($stdin) && is_resource($stdin)) {
                fclose($stdin);
            }
            if (isset($stderrPipe) && is_resource($stderrPipe)) {
                fclose($stderrPipe);
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
            'message' => 'Menukar tabel staging ke tabel aktif secara atomik...',
            'completed_units' => 4,
            'total_units' => self::TOTAL_UNITS,
            'progress_percent' => 80,
        ]);

        // Validate temp table exists and has data
        if (!Schema::hasTable($tempTable)) {
            throw new RuntimeException("Tabel staging `{$tempTable}` tidak ditemukan. Recovery tidak bisa dilanjutkan.");
        }

        $restoredRows = (int) DB::table($tempTable)->count();
        if ($restoredRows === 0) {
            throw new RuntimeException("Tabel staging `{$tempTable}` kosong. Tidak ada data untuk dipulihkan.");
        }

        DB::statement('SET SESSION foreign_key_checks = 0');
        DB::beginTransaction();

        try {
            $quotedTarget = '`' . str_replace('`', '``', $targetTable) . '`';
            $quotedTemp = '`' . str_replace('`', '``', $tempTable) . '`';
            $backupTable = $this->buildRecoveredTableSwapName($targetTable);
            $quotedBackup = '`' . str_replace('`', '``', $backupTable) . '`';

            // Verify target table still exists
            if (!Schema::hasTable($targetTable)) {
                throw new RuntimeException("Tabel target `{$targetTable}` tidak ditemukan. Database mungkin berubah saat proses berjalan.");
            }

            // Clean up old backup if exists
            if (Schema::hasTable($backupTable)) {
                Schema::dropIfExists($backupTable);
            }

            // Atomic swap: original → backup, staging → original
            DB::statement(
                'RENAME TABLE ' . $quotedTarget . ' TO ' . $quotedBackup . ', ' . $quotedTemp . ' TO ' . $quotedTarget
            );

            // Clean up old backup
            Schema::dropIfExists($backupTable);

            DB::commit();
            DB::statement('SET SESSION foreign_key_checks = 1');

            $this->emitProgress($progressCallback, [
                'stage' => 'swapping_data',
                'message' => sprintf('Tabel berhasil ditukar. %d baris data berhasil dipulihkan.', $restoredRows),
                'completed_units' => 4,
                'total_units' => self::TOTAL_UNITS,
                'progress_percent' => 80,
            ]);
        } catch (Throwable $e) {
            try {
                DB::rollBack();
            } catch (Throwable) {
                // Ignore rollback errors
            }
            DB::statement('SET SESSION foreign_key_checks = 1');

            throw new RuntimeException(
                'Gagal menukar tabel staging ke tabel aktif: ' . $e->getMessage()
            );
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
        
        // Skip comments and special directives
        if (preg_match('/^(--|#|\/\*[^!])/i', $trimmed) === 1) {
            return true;
        }

        // Skip database/use commands
        if (preg_match('/^(USE\s+|CREATE\s+DATABASE|DROP\s+DATABASE|CREATE\s+SCHEMA|DROP\s+SCHEMA|SET\s+(?:SESSION|GLOBAL))/i', $trimmed) === 1) {
            return true;
        }

        // Skip table lock commands
        if (preg_match('/^(LOCK|UNLOCK)\s+TABLES/i', $trimmed) === 1) {
            return true;
        }

        // Skip MySQL-specific pragmas that aren't table-related
        if (preg_match('/^\/\*![0-9]{5}\s+(?!ALTER TABLE)/i', $trimmed) === 1) {
            return true;
        }

        // Skip empty statements
        if (trim($trimmed) === '' || trim($trimmed) === ';') {
            return true;
        }

        return false;
    }

    private function rewriteStatementForRestore(string $statement, string $targetTable, string $tempTable): ?string
    {
        $patterns = [
            '/^DROP TABLE IF EXISTS\s+`' . preg_quote($targetTable, '/') . '`/i',
            '/^CREATE TABLE\s+`' . preg_quote($targetTable, '/') . '`/i',
            '/^INSERT INTO\s+`' . preg_quote($targetTable, '/') . '`/i',
            '/^\/\*![0-9]{5}\s+ALTER TABLE\s+`' . preg_quote($targetTable, '/') . '`\s+(DISABLE|ENABLE)\s+KEYS\s*\*\//i',
            '/^ALTER TABLE\s+`' . preg_quote($targetTable, '/') . '`/i',
        ];

        // Check if this statement matches any patterns for our target table
        $matches = false;
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, ltrim($statement)) === 1) {
                $matches = true;
                break;
            }
        }

        // If no match, skip this statement
        if (!$matches) {
            return null;
        }

        // Replace backtick-quoted table name with temp table
        $rewritten = preg_replace(
            '/`' . preg_quote($targetTable, '/') . '`/i',
            '`' . $tempTable . '`',
            $statement,
            1
        );

        // Validate the rewritten statement is still valid SQL
        if ($rewritten === null || trim($rewritten) === '') {
            return null;
        }

        return $rewritten;
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
        // Normalize the input path
        $normalized = trim(str_replace('\\', '/', $backupRelativePath));
        
        // Security: reject empty or path traversal attempts
        if ($normalized === '' || str_contains($normalized, '..') || str_contains($normalized, '//')) {
            return null;
        }

        // Reject paths with special characters that could bypass security
        if (preg_match('/[<>:|?*]/', $normalized) === 1) {
            return null;
        }

        // Try to get the real path - first normalize it
        $candidate = $this->normalizeManagedRecoveryPath($normalized);
        
        // Check if file exists
        if (!is_file($candidate)) {
            return null;
        }

        // Get the real path (follows symlinks and resolves . and ..)
        $real = realpath($candidate);
        if ($real === false) {
            return null;
        }

        // Verify against allowed backup directories
        $allowedRoots = $this->managedBackupAllowedRoots();
        if (!($this->pathMatchesAllowedRoots($candidate, $allowedRoots) || $this->pathMatchesAllowedRoots($real, $allowedRoots))) {
            return null;
        }

        // Final validation: check if it's actually a file and readable
        if (!is_file($real) || !is_readable($real)) {
            return null;
        }

        // Validate file has proper extension
        $ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));
        if ($ext !== 'sql' && !($ext === 'gz' && str_ends_with(strtolower($real), '.sql.gz'))) {
            return null;
        }

        return $real;
    }

    private function managedBackupAllowedRoots(): array
    {
        $roots = [storage_path('app/' . self::BACKUP_DIRECTORY)];
        $configured = trim((string) env('MANAGED_REPORT_RECOVERY_ALLOWED_BACKUP_DIRS', ''));

        if ($configured !== '') {
            foreach (preg_split('/[;,]+/', $configured) ?: [] as $entry) {
                $entry = trim((string) $entry);
                if ($entry === '') {
                    continue;
                }

                $roots[] = $this->normalizeManagedRecoveryPath($entry);
            }
        }

        $resolved = [];
        foreach ($roots as $root) {
            $candidate = realpath($root);
            $resolved[] = $candidate !== false ? $candidate : $root;
        }

        return array_values(array_unique(array_map(
            static fn (string $path): string => strtolower(rtrim(str_replace('\\', '/', $path), '/')),
            $resolved
        )));
    }

    private function normalizeManagedRecoveryPath(string $path): string
    {
        $normalized = trim(str_replace('\\', '/', $path));
        if ($normalized === '') {
            return storage_path('app/' . self::BACKUP_DIRECTORY);
        }

        if (preg_match('/^[A-Za-z]:\//', $normalized) === 1 || str_starts_with($normalized, '/')) {
            return $normalized;
        }

        return storage_path('app/' . ltrim($normalized, '/'));
    }

    private function pathMatchesAllowedRoots(string $path, array $allowedRoots): bool
    {
        $normalizedPath = strtolower(rtrim(str_replace('\\', '/', $path), '/'));

        foreach ($allowedRoots as $root) {
            $normalizedRoot = strtolower(rtrim(str_replace('\\', '/', $root), '/'));
            if ($normalizedRoot === '') {
                continue;
            }

            if ($normalizedPath === $normalizedRoot || str_starts_with($normalizedPath, $normalizedRoot . '/')) {
                return true;
            }
        }

        return false;
    }

    private function isCompressedBackupPath(string $backupPath): bool
    {
        return strtolower(pathinfo($backupPath, PATHINFO_EXTENSION)) === 'gz';
    }

    private function buildRecoveredTableSwapName(string $targetTable): string
    {
        $safe = preg_replace('/[^A-Za-z0-9_]+/', '_', $targetTable) ?: 'recovery';

        return substr('__restore_old_' . $safe . '_' . now()->format('YmdHis') . '_' . substr(md5($targetTable . '|' . microtime(true)), 0, 6), 0, 64);
    }

    private function formatHumanBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max(0, $bytes);
        if ($bytes === 0) {
            return '0 B';
        }

        $pow = (int) floor(log($bytes, 1024));
        $pow = max(0, min($pow, count($units) - 1));

        return round($bytes / (1024 ** $pow), 2) . ' ' . $units[$pow];
    }

    private function resolveAwkBinaryPath(): string
    {
        $configured = trim((string) env('AWK_BINARY', ''));
        $candidates = array_values(array_filter([
            $configured !== '' ? $configured : null,
            'C:\\Program Files\\Git\\usr\\bin\\awk.exe',
            'C:\\Program Files (x86)\\Git\\usr\\bin\\awk.exe',
            '/usr/bin/awk',
            '/bin/awk',
            'awk',
        ]));

        foreach ($candidates as $candidate) {
            if ($candidate === 'awk' || is_file($candidate)) {
                return $candidate;
            }
        }

        return '';
    }

    private function resolveGzipBinaryPath(): string
    {
        $configured = trim((string) env('GZIP_BINARY', ''));
        $candidates = array_values(array_filter([
            $configured !== '' ? $configured : null,
            'C:\\xampp\\php\\gzip.exe',
            'C:\\Program Files\\Git\\usr\\bin\\gzip.exe',
            'C:\\Program Files (x86)\\Git\\usr\\bin\\gzip.exe',
            '/usr/bin/gzip',
            '/bin/gzip',
            'gzip',
        ]));

        foreach ($candidates as $candidate) {
            if ($candidate === 'gzip' || is_file($candidate)) {
                return $candidate;
            }
        }

        return '';
    }

    private function buildAwkExtractionScript(string $targetTable, string $tempTable): string
    {
        $targetLower = strtolower($targetTable);
        $tempEscaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $tempTable);
        $targetEscaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $targetTable);

        $script = <<<'AWK'
BEGIN {
    statement = "";
    targetLower = "__TARGET_LOWER__";
    targetQuotedLower = "`" targetLower "`";
    targetQuoted = "`__TARGET__`";
    tempQuoted = "`__TEMP__`";
}
{
    statement = statement $0 "\n";
    if ($0 !~ /;\s*$/) {
        next;
    }

    trimmed = statement;
    sub(/^[[:space:]]+/, "", trimmed);
    lower = tolower(trimmed);

    if (lower ~ /^(--|\/\*[^!]|use[[:space:]]+|create[[:space:]]+database|drop[[:space:]]+database|lock[[:space:]]+tables|unlock[[:space:]]+tables)/) {
        statement = "";
        next;
    }

    if (index(lower, "drop table if exists " targetQuotedLower) == 1 ||
        index(lower, "create table " targetQuotedLower) == 1 ||
        index(lower, "alter table " targetQuotedLower) == 1 ||
        index(lower, "insert into " targetQuotedLower) == 1 ||
        index(lower, "/*!" ) == 1 && index(lower, "alter table " targetQuotedLower) > 0) {
        gsub(targetQuoted, tempQuoted, statement);
        print statement;
        print "";
    }

    statement = "";
}
AWK;

        return str_replace(
            ['__TARGET_LOWER__', '__TARGET__', '__TEMP__'],
            [$targetLower, $targetEscaped, $tempEscaped],
            $script
        );
    }

    private function extractTableSqlWithNativeTool(
        string $backupPath,
        string $targetTable,
        string $tempTable,
        string $outputPath,
        ?callable $progressCallback
    ): ?array {
        $awkBinary = $this->resolveAwkBinaryPath();
        if ($awkBinary === '') {
            return null;
        }

        $workDirectory = dirname($outputPath);
        $scriptPath = $workDirectory . DIRECTORY_SEPARATOR . '.extract_' . uniqid('', true) . '.awk';
        if (file_put_contents($scriptPath, $this->buildAwkExtractionScript($targetTable, $tempTable)) === false) {
            return null;
        }

        try {
            $isCompressed = $this->isCompressedBackupPath($backupPath);
            $gzipBinary = $isCompressed ? $this->resolveGzipBinaryPath() : '';
            if ($isCompressed && $gzipBinary === '') {
                return null;
            }

            $command = $isCompressed
                ? escapeshellarg($gzipBinary) . ' -dc ' . escapeshellarg($backupPath) . ' | '
                    . escapeshellarg($awkBinary) . ' -f ' . escapeshellarg($scriptPath)
                : escapeshellarg($awkBinary) . ' -f ' . escapeshellarg($scriptPath) . ' ' . escapeshellarg($backupPath);

            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['file', $outputPath, 'wb'],
                2 => ['pipe', 'w'],
            ];

            $process = proc_open($command, $descriptors, $pipes, base_path());
            if (!is_resource($process)) {
                return null;
            }

            fclose($pipes[0]);
            $stderr = is_resource($pipes[2] ?? null) ? stream_get_contents($pipes[2]) : '';
            if (isset($pipes[2]) && is_resource($pipes[2])) {
                fclose($pipes[2]);
            }

            $exitCode = proc_close($process);
            if ($exitCode !== 0 || !is_file($outputPath) || (int) filesize($outputPath) <= 0) {
                @unlink($outputPath);
                return null;
            }

            try {
                $summary = $this->summarizeExtractedSqlFile($outputPath, $tempTable, $progressCallback);
            } catch (Throwable) {
                @unlink($outputPath);
                return null;
            }

            return [
                'matched_statements' => $summary['matched_statements'],
                'has_data' => $summary['has_data'],
                'output_path' => $outputPath,
                'stderr' => trim((string) $stderr),
            ];
        } finally {
            @unlink($scriptPath);
        }
    }

    private function summarizeExtractedSqlFile(string $sqlPath, string $tableName, ?callable $progressCallback): array
    {
        $reader = fopen($sqlPath, 'rb');
        if (!is_resource($reader)) {
            throw new RuntimeException('File hasil ekstraksi tidak bisa dibaca.');
        }

        $buffer = '';
        $matchedStatements = 0;
        $schemaMatched = false;
        $dataMatched = false;

        try {
            while (($line = fgets($reader)) !== false) {
                $buffer .= $line;
                if (!$this->statementEnds($line)) {
                    continue;
                }

                $statement = trim($buffer);
                $buffer = '';

                if ($statement === '' || $this->shouldSkipStatement($statement)) {
                    continue;
                }

                $matchedStatements++;
                $schemaMatched = $schemaMatched || $this->isSchemaStatement($statement, $tableName);
                $dataMatched = $dataMatched || $this->isDataStatement($statement, $tableName);
            }
        } finally {
            fclose($reader);
        }

        if ($matchedStatements === 0 || !$schemaMatched) {
            throw new RuntimeException("Tabel staging `{$tableName}` tidak ditemukan di hasil ekstraksi native.");
        }

        return [
            'matched_statements' => $matchedStatements,
            'has_data' => $dataMatched,
        ];
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
