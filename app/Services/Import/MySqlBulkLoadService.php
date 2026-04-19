<?php

namespace App\Services\Import;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class MySqlBulkLoadService
{
    private ?bool $supportsNativeBulkLoad = null;
    private array $tableEngineCache = [];

    public function supportsNativeBulkLoad(): bool
    {
        if ($this->supportsNativeBulkLoad !== null) {
            return $this->supportsNativeBulkLoad;
        }

        if (!$this->usesMysqlFamilyConnection()) {
            return $this->supportsNativeBulkLoad = false;
        }

        try {
            $row = DB::selectOne("SHOW VARIABLES LIKE 'local_infile'");
            $mysqlValue = strtoupper((string) ($row->Value ?? $row->value ?? 'OFF'));

            // Check PHP-side permission via mysqli setting
            $phpValue = strtoupper((string) ini_get('mysqli.allow_local_infile'));

            if ($mysqlValue !== 'ON') {
                Log::info('MySQL Bulk Load: local_infile is OFF on MySQL server. Fallback used.');
                return $this->supportsNativeBulkLoad = false;
            }

            if ($phpValue !== 'ON' && $phpValue !== '1') {
                Log::warning('MySQL Bulk Load: local_infile is ON in MySQL but OFF in php.ini. Fallback used.');
                return $this->supportsNativeBulkLoad = false;
            }

            return $this->supportsNativeBulkLoad = true;
        } catch (\Throwable $e) {
            Log::warning('Unable to verify local_infile support: ' . $e->getMessage());
            return $this->supportsNativeBulkLoad = false;
        }
    }

    public function fallbackInsertBatchSize(int $columnCount = 1): int
    {
        // MySQL limit for placeholders is ~65,535. 
        // We use 60,000 as a safe limit for 1 INSERT query.
        $safePlaceholderLimit = 60000;
        $calculatedBatch = (int) floor($safePlaceholderLimit / max(1, $columnCount));
        
        // Cap it between 100 and 5000
        return (int) max(100, min(5000, $calculatedBatch));
    }

    public function assertTransactionalTable(string $tableName, string $operation = 'operasi tulis database'): void
    {
        $tableName = trim($tableName);
        if ($tableName === '' || !$this->usesMysqlFamilyConnection() || !Schema::hasTable($tableName)) {
            return;
        }

        $engine = strtoupper($this->getTableEngine($tableName));
        if ($engine === '' || $engine === 'INNODB') {
            return;
        }

        throw new \RuntimeException(
            "Operasi {$operation} diblokir karena tabel `{$tableName}` memakai engine `{$engine}` yang tidak transactional. "
            . 'Ubah tabel ke InnoDB terlebih dahulu lalu ulangi proses.'
        );
    }

    public function withTableWriteLock(string $tableName, callable $callback, int $timeoutSeconds = 10)
    {
        $tableName = trim($tableName);
        if ($tableName === '' || !$this->usesMysqlFamilyConnection()) {
            return $callback();
        }

        $lockName = $this->tableWriteLockName($tableName);
        $row = DB::selectOne('SELECT GET_LOCK(?, ?) AS lock_result', [$lockName, $timeoutSeconds]);
        if ((int) ($row->lock_result ?? 0) !== 1) {
            throw new \RuntimeException("Tabel `{$tableName}` sedang diproses oleh operasi tulis lain. Tunggu proses sebelumnya selesai.");
        }

        try {
            return $callback();
        } finally {
            try {
                DB::selectOne('SELECT RELEASE_LOCK(?) AS release_result', [$lockName]);
            } catch (\Throwable $e) {
                Log::warning('Failed to release MySQL table write lock: ' . $e->getMessage(), [
                    'table' => $tableName,
                    'lock' => $lockName,
                ]);
            }
        }
    }

    public function createBulkLoadTempCsvPath(string $directory, string $tableName, int $jobId): string
    {
        if (!is_dir($directory)) {
            @mkdir($directory, 0777, true);
        }

        return rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $tableName . '_' . $jobId . '_' . Str::random(8) . '.csv';
    }

    public function relaxMysqlSqlModeForImport(\PDO $pdo): ?string
    {
        $originalMode = $pdo->query('SELECT @@SESSION.sql_mode')->fetchColumn();
        $originalMode = $originalMode === false ? '' : (string) $originalMode;

        $modes = array_values(array_filter(array_map('trim', explode(',', $originalMode))));
        $filteredModes = array_values(array_filter($modes, static function (string $mode): bool {
            return !in_array(strtoupper($mode), ['STRICT_TRANS_TABLES', 'STRICT_ALL_TABLES'], true);
        }));

        $relaxedMode = implode(',', $filteredModes);
        if ($relaxedMode !== $originalMode) {
            $pdo->exec('SET SESSION sql_mode = ' . $pdo->quote($relaxedMode));
        }

        return $originalMode;
    }

    public function loadCsvIntoMysql(
        string $csvPath,
        string $tableName,
        array $columns,
        bool $relaxSqlMode = false,
        ?callable $beforeLoad = null
    ): int {
        $this->assertTransactionalTable($tableName, 'bulk import');

        if (!$this->supportsNativeBulkLoad()) {
            return $this->loadCsvIntoMysqlChunked(
                $csvPath,
                $tableName,
                $columns,
                null,
                $this->fallbackInsertBatchSize(),
                null,
                $relaxSqlMode
            );
        }

        return $this->withTableWriteLock($tableName, function () use ($csvPath, $tableName, $columns, $relaxSqlMode, $beforeLoad): int {
            return $this->loadCsvIntoMysqlInternal($csvPath, $tableName, $columns, $relaxSqlMode, $beforeLoad);
        });
    }

    private function loadCsvIntoMysqlInternal(
        string $csvPath,
        string $tableName,
        array $columns,
        bool $relaxSqlMode = false,
        ?callable $beforeLoad = null
    ): int {
        if (!file_exists($csvPath)) {
            throw new \RuntimeException('File CSV sementara tidak ditemukan untuk bulk load.');
        }

        if ($columns === []) {
            throw new \RuntimeException('Kolom bulk load kosong.');
        }

        [$dsn, $username, $password] = $this->resolvePdoCredentials();
        $quotedColumns = implode(', ', array_map(static function (string $column): string {
            return '`' . str_replace('`', '``', $column) . '`';
        }, $columns));

        $lastException = null;

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $pdo = null;
            $originalSqlMode = null;

            try {
                $pdo = new \PDO($dsn, $username, $password, [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::MYSQL_ATTR_LOCAL_INFILE => true,
                    \PDO::ATTR_TIMEOUT => 120,
                ]);

                $pdo->beginTransaction();

                if ($relaxSqlMode) {
                    $originalSqlMode = $this->relaxMysqlSqlModeForImport($pdo);
                }

                if ($beforeLoad !== null) {
                    $beforeLoad($pdo);
                }

                $normalizedPath = str_replace('\\', '/', realpath($csvPath) ?: $csvPath);
                $quotedPath = $pdo->quote($normalizedPath);
                $sql = "LOAD DATA LOCAL INFILE {$quotedPath} INTO TABLE `{$tableName}` "
                    . "CHARACTER SET utf8mb4 "
                    . "FIELDS TERMINATED BY ',' ENCLOSED BY '\"' "
                    . "LINES TERMINATED BY '\\n' "
                    . "({$quotedColumns})";

                $pdo->exec('SET @skip_snapshot_invalidation = 1');
                $affected = $pdo->exec($sql);
                $pdo->exec('SET @skip_snapshot_invalidation = NULL');

                $pdo->commit();

                if ($affected === false) {
                    throw new \RuntimeException('LOAD DATA LOCAL INFILE gagal dieksekusi.');
                }

                if ($relaxSqlMode && $originalSqlMode !== null) {
                    try {
                        $pdo->exec('SET SESSION sql_mode = ' . $pdo->quote($originalSqlMode));
                    } catch (\Throwable $cleanupError) {
                        Log::warning('Failed to restore sql_mode after bulk load: ' . $cleanupError->getMessage());
                    }
                }

                return (int) $affected;
            } catch (\Throwable $e) {
                $lastException = $e;

                try {
                    if ($pdo !== null && $pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    if ($pdo !== null) {
                        $pdo->exec('SET @skip_snapshot_invalidation = NULL');
                    }
                } catch (\Throwable $ignored) {
                }

                if (!$this->isTransientMysqlLoadError($e) || $attempt === 3) {
                    break;
                }

                usleep(300000 * $attempt);
            } finally {
                $pdo = null;
            }
        }

        if ($lastException instanceof \Throwable) {
            throw $lastException;
        }

        throw new \RuntimeException('LOAD DATA LOCAL INFILE gagal dieksekusi.');
    }

    public function countFileLines(string $path): int
    {
        if (!file_exists($path)) {
            return 0;
        }

        $fileSize = filesize($path);
        if ($fileSize === false || $fileSize === 0) {
            return 0;
        }

        $sampleSize = min(65536, (int) ($fileSize / 2));
        $handle = @fopen($path, 'r');
        if ($handle === false) {
            return (int) max(1, ceil($fileSize / 100));
        }

        try {
            $sampleData = fread($handle, $sampleSize);
            if ($sampleData === false) {
                return (int) max(1, ceil($fileSize / 100));
            }

            $sampleLines = substr_count($sampleData, "\n");
            if ($sampleLines === 0) {
                return 1;
            }

            $estimatedLines = (int) ceil($sampleLines * ($fileSize / $sampleSize));
            return max(1, $estimatedLines);
        } finally {
            fclose($handle);
        }
    }

    public function loadCsvIntoMysqlChunked(
        string $csvPath,
        string $tableName,
        array $columns,
        ?callable $onProgress = null,
        int $chunkLines = 8000,
        ?int $totalLines = null,
        bool $relaxSqlMode = false
    ): int {
        $this->assertTransactionalTable($tableName, 'bulk import');

        if ($totalLines === null && file_exists($csvPath)) {
            $totalLines = $this->countFileLines($csvPath);
        }

        return $this->withTableWriteLock($tableName, function () use (
            $csvPath,
            $tableName,
            $columns,
            $onProgress,
            $chunkLines,
            $totalLines,
            $relaxSqlMode
        ): int {
            if (!$this->supportsNativeBulkLoad()) {
                return $this->loadCsvIntoMysqlPhpChunkedInternal(
                    $csvPath,
                    $tableName,
                    $columns,
                    $onProgress,
                    $totalLines
                );
            }

            return $this->loadCsvIntoMysqlChunkedInternal(
                $csvPath,
                $tableName,
                $columns,
                $onProgress,
                $chunkLines,
                $totalLines,
                $relaxSqlMode
            );
        });
    }

    private function loadCsvIntoMysqlPhpChunkedInternal(
        string $csvPath,
        string $tableName,
        array $columns,
        ?callable $onProgress = null,
        ?int $totalLines = null
    ): int {
        $totalLines ??= $this->countFileLines($csvPath);
        if ($totalLines <= 0) {
            return 0;
        }

        if (!file_exists($csvPath)) {
            throw new \RuntimeException('File CSV sementara tidak ditemukan untuk fallback bulk load.');
        }

        if ($columns === []) {
            throw new \RuntimeException('Kolom bulk load kosong.');
        }

        $source = @fopen($csvPath, 'r');
        if ($source === false) {
            throw new \RuntimeException('Gagal membuka file CSV untuk fallback bulk load.');
        }

        $batchSize = $this->fallbackInsertBatchSize(count($columns));
        $batch = [];
        $insertedTotal = 0;
        $failedTotal = 0;
        $processedLines = 0;

        try {
            while (($row = fgetcsv($source, 0, ',')) !== false) {
                $processedLines++;

                if (empty(array_filter((array) $row, static fn ($value): bool => trim((string) $value) !== ''))) {
                    continue;
                }

                $normalizedRow = $this->normalizePhpCsvInsertRow($row, $columns);
                if ($normalizedRow === null) {
                    continue;
                }

                $batch[] = $normalizedRow;

                if (count($batch) >= $batchSize) {
                    $this->insertBatchWithFallback($batch, $tableName, $insertedTotal, $failedTotal);
                    $batch = [];

                    if ($onProgress) {
                        $onProgress($processedLines, $totalLines, $insertedTotal);
                    }
                }
            }
        } finally {
            fclose($source);
        }

        if (!empty($batch)) {
            $this->insertBatchWithFallback($batch, $tableName, $insertedTotal, $failedTotal);

            if ($onProgress) {
                $onProgress($processedLines, $totalLines, $insertedTotal);
            }
        }

        return $insertedTotal;
    }

    private function normalizePhpCsvInsertRow(array $row, array $columns): ?array
    {
        $expectedColumns = count($columns);
        if ($expectedColumns <= 0) {
            return null;
        }

        if (count($row) < $expectedColumns) {
            $row = array_pad($row, $expectedColumns, null);
        } elseif (count($row) > $expectedColumns) {
            $row = array_slice($row, 0, $expectedColumns);
        }

        $normalized = [];
        foreach ($columns as $index => $column) {
            $value = $row[$index] ?? null;
            if ($value === '\N') {
                $value = null;
            } elseif (is_string($value)) {
                $value = rtrim($value, "\r");
            }

            $normalized[$column] = $value;
        }

        if (empty(array_filter($normalized, static fn ($value): bool => trim((string) $value) !== ''))) {
            return null;
        }

        return $normalized;
    }

    private function insertBatchWithFallback(array $batch, string $tableName, int &$totalInserted, int &$totalFailed): void
    {
        if (empty($batch)) {
            return;
        }

        try {
            DB::table($tableName)->insert($batch);
            $totalInserted += count($batch);
            return;
        } catch (\Throwable $e) {
            if (count($batch) <= 25) {
                foreach ($batch as $single) {
                    try {
                        DB::table($tableName)->insert($single);
                        $totalInserted++;
                    } catch (\Throwable) {
                        $totalFailed++;
                    }
                }

                return;
            }
        }

        $midpoint = (int) ceil(count($batch) / 2);
        $leftBatch = array_slice($batch, 0, $midpoint);
        $rightBatch = array_slice($batch, $midpoint);

        $this->insertBatchWithFallback($leftBatch, $tableName, $totalInserted, $totalFailed);
        $this->insertBatchWithFallback($rightBatch, $tableName, $totalInserted, $totalFailed);
    }

    private function loadCsvIntoMysqlChunkedInternal(
        string $csvPath,
        string $tableName,
        array $columns,
        ?callable $onProgress = null,
        int $chunkLines = 8000,
        ?int $totalLines = null,
        bool $relaxSqlMode = false
    ): int {
        $totalLines ??= $this->countFileLines($csvPath);
        if ($totalLines <= 0) {
            return 0;
        }

        if ($totalLines <= $chunkLines) {
            $inserted = $this->loadCsvIntoMysqlInternal($csvPath, $tableName, $columns, $relaxSqlMode);
            if ($onProgress) {
                $onProgress($totalLines, $totalLines, $inserted);
            }
            return $inserted;
        }

        $source = @fopen($csvPath, 'r');
        if ($source === false) {
            throw new \RuntimeException('Gagal membuka file CSV untuk chunked LOAD DATA.');
        }

        $insertedTotal = 0;
        $processedLines = 0;
        $chunkIndex = 0;
        $chunkDir = dirname($csvPath);

        try {
            while (!feof($source)) {
                $chunkPath = $chunkDir . DIRECTORY_SEPARATOR . 'chunk_' . $chunkIndex . '_' . Str::random(6) . '.csv';
                $chunkHandle = @fopen($chunkPath, 'w');
                if ($chunkHandle === false) {
                    throw new \RuntimeException('Gagal membuat file chunk untuk LOAD DATA.');
                }

                $currentChunkLines = 0;
                try {
                    while ($currentChunkLines < $chunkLines && !feof($source)) {
                        $line = fgets($source);
                        if ($line === false) {
                            break;
                        }
                        fwrite($chunkHandle, $line);
                        $currentChunkLines++;
                        $processedLines++;
                    }
                } finally {
                    fclose($chunkHandle);
                }

                if ($currentChunkLines > 0) {
                    try {
                        $insertedTotal += $this->loadCsvIntoMysqlInternal($chunkPath, $tableName, $columns, $relaxSqlMode);
                    } catch (\Throwable $e) {
                        if ($this->isTransientMysqlLoadError($e) && $chunkLines > 1000) {
                            $insertedTotal += $this->loadCsvIntoMysqlChunkedInternal(
                                $chunkPath,
                                $tableName,
                                $columns,
                                null,
                                max(1000, (int) floor($chunkLines / 2)),
                                $currentChunkLines,
                                $relaxSqlMode
                            );
                        } else {
                            throw $e;
                        }
                    }

                    if ($onProgress) {
                        $onProgress($processedLines, $totalLines, $insertedTotal);
                    }
                }

                if (file_exists($chunkPath)) {
                    @unlink($chunkPath);
                }

                $chunkIndex++;
            }
        } finally {
            fclose($source);
        }

        return $insertedTotal;
    }

    public function isTransientMysqlLoadError(\Throwable $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, "error while reading query's response packet")
            || str_contains($message, 'server has gone away')
            || str_contains($message, 'lost connection')
            || str_contains($message, 'error writing communication packets')
            || str_contains($message, 'packets out of order');
    }

    private function resolvePdoCredentials(): array
    {
        $connection = config('database.default', 'mysql');
        $dbConfig = config("database.connections.{$connection}", []);
        $charset = $dbConfig['charset'] ?? 'utf8mb4';
        $host = $dbConfig['host'] ?? '127.0.0.1';
        $port = $dbConfig['port'] ?? '3306';
        $database = $dbConfig['database'] ?? '';
        $username = $dbConfig['username'] ?? '';
        $password = $dbConfig['password'] ?? '';
        $unixSocket = $dbConfig['unix_socket'] ?? '';

        $dsn = $unixSocket !== ''
            ? "mysql:unix_socket={$unixSocket};dbname={$database};charset={$charset}"
            : "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";

        return [$dsn, $username, $password];
    }

    private function usesMysqlFamilyConnection(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
    }

    private function getTableEngine(string $tableName): string
    {
        $tableName = trim($tableName);
        if ($tableName === '' || !$this->usesMysqlFamilyConnection()) {
            return '';
        }

        if (array_key_exists($tableName, $this->tableEngineCache)) {
            return $this->tableEngineCache[$tableName];
        }

        $row = DB::selectOne(
            'SELECT ENGINE FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
            [$tableName]
        );

        return $this->tableEngineCache[$tableName] = strtoupper((string) ($row->ENGINE ?? $row->engine ?? ''));
    }

    private function tableWriteLockName(string $tableName): string
    {
        return 'project_abah:table_write:' . strtolower(trim($tableName));
    }
}
