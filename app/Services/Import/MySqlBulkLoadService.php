<?php

namespace App\Services\Import;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MySqlBulkLoadService
{
    private ?bool $supportsNativeBulkLoad = null;

    public function supportsNativeBulkLoad(): bool
    {
        if ($this->supportsNativeBulkLoad !== null) {
            return $this->supportsNativeBulkLoad;
        }

        if ((bool) config('import.direct_load.require_local_infile', true)) {
            $envFlag = filter_var(env('DB_MYSQL_LOCAL_INFILE', false), FILTER_VALIDATE_BOOL);
            if ($envFlag !== true) {
                return $this->supportsNativeBulkLoad = false;
            }
        }

        $driver = DB::connection()->getDriverName();
        if (!in_array($driver, ['mysql', 'mariadb'], true)) {
            return $this->supportsNativeBulkLoad = false;
        }

        try {
            $row = DB::selectOne("SHOW VARIABLES LIKE 'local_infile'");
            return $this->supportsNativeBulkLoad = strtoupper((string) ($row->Value ?? $row->value ?? 'OFF')) === 'ON';
        } catch (\Throwable $e) {
            Log::warning('Unable to verify local_infile support: ' . $e->getMessage());
            return $this->supportsNativeBulkLoad = false;
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
        bool $relaxSqlMode = false
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

                if ($relaxSqlMode) {
                    $originalSqlMode = $this->relaxMysqlSqlModeForImport($pdo);
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

                if ($relaxSqlMode && $originalSqlMode !== null) {
                    $pdo->exec('SET SESSION sql_mode = ' . $pdo->quote($originalSqlMode));
                }

                if ($affected === false) {
                    throw new \RuntimeException('LOAD DATA LOCAL INFILE gagal dieksekusi.');
                }

                return (int) $affected;
            } catch (\Throwable $e) {
                $lastException = $e;

                try {
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
        $handle = @fopen($path, 'r');
        if ($handle === false) {
            return 0;
        }

        $lines = 0;
        try {
            while (!feof($handle)) {
                $line = fgets($handle);
                if ($line !== false) {
                    $lines++;
                }
            }
        } finally {
            fclose($handle);
        }

        return $lines;
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
        $totalLines ??= $this->countFileLines($csvPath);
        if ($totalLines <= 0) {
            return 0;
        }

        if ($totalLines <= $chunkLines) {
            $inserted = $this->loadCsvIntoMysql($csvPath, $tableName, $columns, $relaxSqlMode);
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
                        $insertedTotal += $this->loadCsvIntoMysql($chunkPath, $tableName, $columns, $relaxSqlMode);
                    } catch (\Throwable $e) {
                        if ($this->isTransientMysqlLoadError($e) && $chunkLines > 1000) {
                            $insertedTotal += $this->loadCsvIntoMysqlChunked(
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
}
