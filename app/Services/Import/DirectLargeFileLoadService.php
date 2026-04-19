<?php

namespace App\Services\Import;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDO;

/**
 * Optimized service untuk handle file besar dengan direct LOAD DATA
 *
 * Optimizations:
 * - Avoid chunking untuk files under max_allowed_packet
 * - Single LOAD DATA call instead of multiple chunks
 * - Pre-validate CSV format sebelum load
 * - Minimal temp file I/O
 * - Memory-aware streaming for validation
 */
class DirectLargeFileLoadService
{
    private const MAX_RETRIES = 3;
    private const VALIDATION_CHUNK_SIZE = 1048576; // 1MB untuk validation
    private const MEMORY_SAFE_BUFFER = 104857600; // 100MB safe buffer

    public function __construct(
        private readonly MySqlBulkLoadService $bulkLoadService,
    ) {
    }

    /**
     * Load file besar dengan optimasi: langsung LOAD DATA tanpa chunking jika possible
     * 
     * OPTIMASI PHASE 1: Single-pass file profiling
     * OPTIMASI PHASE 3: Lazy validation (skip untuk file kecil)
     */
    public function loadLargeFile(
        string $csvPath,
        string $tableName,
        array $columns,
        ?callable $onProgress = null,
        ?int $totalLines = null
    ): int {
        if (!file_exists($csvPath)) {
            throw new \RuntimeException("File CSV tidak ditemukan: $csvPath");
        }

        $fileSize = filesize($csvPath);
        if ($fileSize === false) {
            throw new \RuntimeException("Gagal mendapatkan file size: $csvPath");
        }

        $maxAllowedPacket = $this->getMaxAllowedPacket();

        // OPTIMASI PHASE 3: Lazy validation - skip untuk file kecil
        // Jika file < max_allowed_packet - buffer, tidak perlu validasi (file kecil = unlikely ada error)
        $needsValidation = $fileSize >= ($maxAllowedPacket - self::MEMORY_SAFE_BUFFER);

        if ($needsValidation) {
            // Pre-validate CSV format hanya untuk file besar
            $validation = $this->validateCsvFormat($csvPath, $columns);
            if (!$validation['valid']) {
                throw new \RuntimeException("CSV format tidak valid: " . $validation['error']);
            }
        }

        // Jika file < max_allowed_packet - buffer, load directly
        if ($fileSize < ($maxAllowedPacket - self::MEMORY_SAFE_BUFFER)) {
            Log::info("Optimasi: Load file langsung tanpa chunking", [
                'file' => $csvPath,
                'size' => $fileSize,
                'max_packet' => $maxAllowedPacket,
            ]);

            return $this->loadDirectWithRetry($csvPath, $tableName, $columns, $onProgress, $totalLines);
        }

        // Jika terlalu besar, gunakan smart chunking dengan persistent PDO
        Log::info("File besar: gunakan smart chunking dengan persistent PDO", [
            'file' => $csvPath,
            'size' => $fileSize,
            'max_packet' => $maxAllowedPacket,
        ]);

        return $this->loadWithSmartChunkingOptimized($csvPath, $tableName, $columns, $onProgress, $totalLines);
    }

    /**
     * Direct load dengan retry logic untuk handle transient errors
     */
    private function loadDirectWithRetry(
        string $csvPath,
        string $tableName,
        array $columns,
        ?callable $onProgress = null,
        ?int $totalLines = null
    ): int {
        $lastException = null;

        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            try {
                if ($onProgress && $totalLines) {
                    $onProgress(0, $totalLines, 0);
                }

                $rowCount = $this->bulkLoadService->loadCsvIntoMysql(
                    $csvPath,
                    $tableName,
                    $columns,
                    relaxSqlMode: true
                );

                if ($onProgress && $totalLines) {
                    $onProgress($totalLines, $totalLines, $rowCount);
                }

                Log::info("Direct LOAD DATA berhasil", [
                    'file' => $csvPath,
                    'table' => $tableName,
                    'rows' => $rowCount,
                    'attempt' => $attempt,
                ]);

                return $rowCount;
            } catch (\Throwable $e) {
                $lastException = $e;

                // Jika transient error, retry
                if ($this->isTransientError($e) && $attempt < self::MAX_RETRIES) {
                    $backoffMs = 300 * $attempt;
                    Log::warning("Direct LOAD DATA transient error, retry...", [
                        'attempt' => $attempt,
                        'error' => $e->getMessage(),
                        'backoff_ms' => $backoffMs,
                    ]);
                    usleep($backoffMs * 1000);
                    continue;
                }

                // Fatal error, fall back ke chunking
                if ($this->shouldFallbackToChunking($e)) {
                    Log::warning("Direct LOAD DATA gagal, fall back ke smart chunking", [
                        'error' => $e->getMessage(),
                    ]);

                    return $this->loadWithSmartChunking($csvPath, $tableName, $columns, $onProgress, $totalLines);
                }

                throw $e;
            }
        }

        throw $lastException ?? new \RuntimeException('Load gagal setelah ' . self::MAX_RETRIES . ' attempts');
    }

    /**
     * Smart chunking: minimal temp files, optimal chunk size
     */
    private function loadWithSmartChunking(
        string $csvPath,
        string $tableName,
        array $columns,
        ?callable $onProgress = null,
        ?int $totalLines = null
    ): int {
        $totalLines ??= $this->estimateLineCount($csvPath);

        // Hitung optimal chunk size based on max_allowed_packet
        $chunkSize = $this->calculateOptimalChunkSize($columns);

        Log::info("Smart chunking started", [
            'file' => $csvPath,
            'total_lines' => $totalLines,
            'chunk_size' => $chunkSize,
        ]);

        $source = @fopen($csvPath, 'rb');
        if (!$source) {
            throw new \RuntimeException("Gagal membuka file: $csvPath");
        }

        $inserted = 0;
        $processedLines = 0;
        $chunkIndex = 0;
        $tempDir = sys_get_temp_dir();

        try {
            while (!feof($source)) {
                $chunkPath = $tempDir . DIRECTORY_SEPARATOR . 'chunk_' . uniqid() . '.csv';
                $chunkHandle = @fopen($chunkPath, 'wb');

                if (!$chunkHandle) {
                    throw new \RuntimeException("Gagal create chunk file: $chunkPath");
                }

                $currentChunkLines = 0;
                $chunkFileSize = 0;

                try {
                    while ($currentChunkLines < $chunkSize && !feof($source)) {
                        $line = fgets($source);
                        if ($line === false) {
                            break;
                        }

                        fwrite($chunkHandle, $line);
                        $chunkFileSize += strlen($line);
                        $currentChunkLines++;
                        $processedLines++;

                        // Jika chunk file sudah besar, flush
                        if ($chunkFileSize > 50 * 1024 * 1024) { // 50MB
                            break;
                        }
                    }
                } finally {
                    fclose($chunkHandle);
                }

                if ($currentChunkLines > 0) {
                    try {
                        $chunkInserted = $this->bulkLoadService->loadCsvIntoMysql(
                            $chunkPath,
                            $tableName,
                            $columns,
                            relaxSqlMode: true
                        );

                        $inserted += $chunkInserted;

                        if ($onProgress) {
                            $onProgress($processedLines, $totalLines, $inserted);
                        }

                        Log::info("Chunk loaded", [
                            'chunk' => $chunkIndex,
                            'lines' => $currentChunkLines,
                            'size' => $chunkFileSize,
                            'inserted' => $chunkInserted,
                        ]);
                    } finally {
                        @unlink($chunkPath);
                    }
                }

                $chunkIndex++;
            }
        } finally {
            fclose($source);
        }

        return $inserted;
    }

    /**
     * OPTIMASI PHASE 2: Smart chunking dengan persistent PDO
     * 
     * Reuse single PDO connection untuk semua chunks
     * Saves: ~50ms × N chunks
     * 
     * For 50 chunks: 2.5 seconds saved!
     */
    private function loadWithSmartChunkingOptimized(
        string $csvPath,
        string $tableName,
        array $columns,
        ?callable $onProgress = null,
        ?int $totalLines = null
    ): int {
        $totalLines ??= $this->estimateLineCount($csvPath);
        $chunkSize = $this->calculateOptimalChunkSize($columns);

        Log::info("Smart chunking optimized (persistent PDO)", [
            'file' => $csvPath,
            'total_lines' => $totalLines,
            'chunk_size' => $chunkSize,
        ]);

        // PHASE 2 OPTIMIZATION: Create persistent PDO once for all chunks
        $persistentPdo = $this->bulkLoadService->createPersistentPdo();
        $source = @fopen($csvPath, 'rb');

        if (!$source) {
            $this->bulkLoadService->closePersistentPdo();
            throw new \RuntimeException("Gagal membuka file: $csvPath");
        }

        $inserted = 0;
        $processedLines = 0;
        $chunkIndex = 0;
        $tempDir = sys_get_temp_dir();

        try {
            while (!feof($source)) {
                $chunkPath = $tempDir . DIRECTORY_SEPARATOR . 'chunk_' . uniqid() . '.csv';
                $chunkHandle = @fopen($chunkPath, 'wb');

                if (!$chunkHandle) {
                    throw new \RuntimeException("Gagal create chunk file: $chunkPath");
                }

                $currentChunkLines = 0;
                $chunkFileSize = 0;

                try {
                    while ($currentChunkLines < $chunkSize && !feof($source)) {
                        $line = fgets($source);
                        if ($line === false) {
                            break;
                        }

                        fwrite($chunkHandle, $line);
                        $chunkFileSize += strlen($line);
                        $currentChunkLines++;
                        $processedLines++;

                        if ($chunkFileSize > 50 * 1024 * 1024) {  // 50MB
                            break;
                        }
                    }
                } finally {
                    fclose($chunkHandle);
                }

                if ($currentChunkLines > 0) {
                    try {
                        // Use persistent PDO - NO new connection created!
                        $chunkInserted = $this->bulkLoadService->loadCsvIntoMysqlWithPdo(
                            $persistentPdo,
                            $chunkPath,
                            $tableName,
                            $columns,
                            relaxSqlMode: true
                        );

                        $inserted += $chunkInserted;

                        if ($onProgress) {
                            $onProgress($processedLines, $totalLines, $inserted);
                        }

                        Log::info("Chunk loaded (persistent PDO)", [
                            'chunk' => $chunkIndex,
                            'lines' => $currentChunkLines,
                            'size' => $chunkFileSize,
                            'inserted' => $chunkInserted,
                        ]);
                    } finally {
                        @unlink($chunkPath);
                    }
                }

                $chunkIndex++;
            }
        } finally {
            fclose($source);
            $this->bulkLoadService->closePersistentPdo();
        }

        return $inserted;
    }

    /**
     * Validasi CSV format tanpa load ke database
     */
    private function validateCsvFormat(string $csvPath, array $columns): array
    {
        try {
            $handle = @fopen($csvPath, 'rb');
            if (!$handle) {
                return ['valid' => false, 'error' => 'Gagal membuka file'];
            }

            $lineNum = 0;
            $errorMsg = '';

            try {
                while (!feof($handle) && $lineNum < 1000) {
                    $line = fgets($handle);
                    if (!$line) {
                        break;
                    }

                    $lineNum++;

                    // Validasi line bisa di-parse sebagai CSV
                    $row = str_getcsv($line, ',', '"', '\\');
                    if (!is_array($row) || count($row) === 0) {
                        $errorMsg = "Line $lineNum tidak valid";
                        break;
                    }

                    // Check kolom count match
                    if ($lineNum === 1 && count($row) < count($columns)) {
                        $errorMsg = "Jumlah kolom tidak sesuai (expected: " . count($columns) . ", got: " . count($row) . ")";
                        break;
                    }
                }
            } finally {
                fclose($handle);
            }

            if ($errorMsg) {
                return ['valid' => false, 'error' => $errorMsg];
            }

            return ['valid' => true];
        } catch (\Throwable $e) {
            return ['valid' => false, 'error' => 'Validation error: ' . $e->getMessage()];
        }
    }

    /**
     * Hitung optimal chunk size berdasarkan column count
     */
    private function calculateOptimalChunkSize(array $columns): int
    {
        $columnCount = count($columns);

        // Estimate average row size: ~100 bytes per column
        $estimatedRowSize = $columnCount * 100;

        // Target chunk: 20MB (stays under most max_allowed_packet settings)
        $targetChunkBytes = 20 * 1024 * 1024;

        $chunkSize = max(1000, (int) floor($targetChunkBytes / $estimatedRowSize));

        // Cap at reasonable limit
        return min(100000, $chunkSize);
    }

    /**
     * Get MySQL max_allowed_packet setting
     */
    private function getMaxAllowedPacket(): int
    {
        try {
            $row = DB::selectOne("SELECT @@max_allowed_packet as value");
            return (int) ($row->value ?? 16 * 1024 * 1024);
        } catch (\Throwable $e) {
            Log::warning("Gagal query max_allowed_packet: " . $e->getMessage());
            return 16 * 1024 * 1024; // Default 16MB
        }
    }

    /**
     * Estimate line count untuk progress tracking
     */
    private function estimateLineCount(string $path): int
    {
        $fileSize = @filesize($path);
        if (!$fileSize || $fileSize <= 0) {
            return 1;
        }

        $handle = @fopen($path, 'rb');
        if (!$handle) {
            return (int) max(1, ceil($fileSize / 100));
        }

        try {
            $sample = fread($handle, 65536);
            if (!$sample) {
                return 1;
            }

            $lineCount = substr_count($sample, "\n");
            return $lineCount > 0
                ? (int) ceil($fileSize / (strlen($sample) / $lineCount))
                : 1;
        } finally {
            fclose($handle);
        }
    }

    private function isTransientError(\Throwable $e): bool
    {
        $msg = strtolower($e->getMessage());
        return str_contains($msg, 'server has gone away')
            || str_contains($msg, 'lost connection')
            || str_contains($msg, 'error writing communication packets')
            || str_contains($msg, 'packets out of order');
    }

    private function shouldFallbackToChunking(\Throwable $e): bool
    {
        $msg = strtolower($e->getMessage());
        return str_contains($msg, 'mysql has gone away')
            || str_contains($msg, 'lost connection to mysql server')
            || str_contains($msg, 'max_allowed_packet')
            || str_contains($msg, 'packet too large');
    }
}
