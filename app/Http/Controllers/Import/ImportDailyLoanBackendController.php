<?php

namespace App\Http\Controllers\Import;

use App\Http\Controllers\Controller;
use App\Services\Import\ImportCleanupService;
use App\Services\Import\ExcelImportJobService;
use App\Services\Import\ImportExecutionService;
use App\Services\Import\ImportProgressService;
use App\Support\StrictDateParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportDailyLoanBackendController extends Controller
{
    private const DAILY_LOAN_REPORT_ID = 8;
    private const DAILY_LOAN_TABLE = 'daily_loan_dinamis';
    private const STORAGE_DIR = 'imports/backend/daily-loan';

    public function __construct(
        private readonly ExcelImportJobService $jobService,
        private readonly ImportExecutionService $executionService,
        private readonly ImportProgressService $progressService,
        private readonly ImportCleanupService $cleanupService,
    ) {
    }

    public function importLocalCsv(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_path' => ['required', 'string'],
            'periode' => ['nullable', 'string'],
            'mode' => ['nullable', 'string', 'in:sync,queue'],
            'replace_existing_periods' => ['nullable', 'boolean'],
        ]);

        $sourcePath = $this->resolveReadableSourcePath((string) $validated['source_path']);
        if ($sourcePath === null) {
            return response()->json([
                'status' => 'error',
                'message' => 'File sumber tidak ditemukan atau tidak bisa dibaca dari server.',
            ], 422);
        }

        if (!in_array(strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION)), ['csv', 'txt'], true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Backend import Daily Loan hanya menerima file CSV/TXT.',
            ], 422);
        }

        $requestedPeriod = $this->normalizeRequestedPeriod($validated['periode'] ?? null);
        if (($validated['periode'] ?? null) !== null && $requestedPeriod === null) {
            return response()->json([
                'status' => 'error',
                'message' => 'Format periode tidak valid. Gunakan format seperti `19042026`, `19-04-2026`, atau `2026-04-19`.',
            ], 422);
        }

        $metadata = $this->inspectDailyLoanCsv($sourcePath);
        if (($metadata['header_found'] ?? false) !== true) {
            return response()->json([
                'status' => 'error',
                'message' => 'Header `PERIODE` Daily Loan tidak ditemukan pada file sumber.',
            ], 422);
        }

        $detectedPeriods = array_values((array) ($metadata['periods'] ?? []));
        if ($requestedPeriod !== null && $detectedPeriods !== [] && !in_array($requestedPeriod, $detectedPeriods, true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Periode request tidak cocok dengan isi file Daily Loan.',
                'requested_period' => $requestedPeriod,
                'detected_periods' => $detectedPeriods,
            ], 422);
        }

        $replaceExistingPeriods = $request->boolean('replace_existing_periods', true);
        $replaceTargetRows = 0;
        if ($replaceExistingPeriods && $detectedPeriods !== [] && Schema::hasTable(self::DAILY_LOAN_TABLE)) {
            $replaceTargetRows = (int) DB::table(self::DAILY_LOAN_TABLE)
                ->whereIn('periode', $detectedPeriods)
                ->count();
        }

        $mode = (string) ($validated['mode'] ?? 'sync');
        if ($mode === 'queue') {
            $copiedRelativePath = $this->copySourceIntoImportStorage($sourcePath);
            $jobId = $this->jobService->createImportJobRecord(
                self::DAILY_LOAN_REPORT_ID,
                Storage::path($copiedRelativePath),
                0,
                [
                    'controller' => ImportExcelController::class,
                    'mode' => 'backend_local_daily_loan',
                    'table_name' => self::DAILY_LOAN_TABLE,
                    'file_path' => $copiedRelativePath,
                    'backend_source_path' => $sourcePath,
                    'backend_detected_periods' => $detectedPeriods,
                ],
                auth()->id()
            );

            $this->jobService->putImportJobState($jobId, [
                'params' => [
                    'table_name' => self::DAILY_LOAN_TABLE,
                    'file_path' => $copiedRelativePath,
                    'active_filters' => [],
                    'disable_inline_fallback' => false,
                    'replace_existing_periods' => $replaceExistingPeriods,
                    'backend_detected_periods' => $detectedPeriods,
                    'replace_periods' => $detectedPeriods,
                    'job_id' => $jobId,
                ],
                'headers' => [],
            ]);

            $dispatched = $this->executionService->dispatch(
                $jobId,
                'Backend import Daily Loan dari file lokal telah masuk antrian.'
            );

            return response()->json([
                'status' => $dispatched ? 'queued' : 'error',
                'job_id' => $jobId,
                'message' => $dispatched
                    ? 'Backend import Daily Loan berhasil diantrekan.'
                    : 'Job import Daily Loan gagal diantrekan.',
                'source_path' => $sourcePath,
                'storage_path' => Storage::path($copiedRelativePath),
                'detected_periods' => $detectedPeriods,
                'deleted_existing_rows' => $replaceTargetRows,
                'source_size_bytes' => (int) ($metadata['source_size_bytes'] ?? filesize($sourcePath) ?: 0),
                'sampled_data_rows' => (int) ($metadata['sampled_data_rows'] ?? 0),
            ], $dispatched ? 202 : 500);
        }

        return $this->runSyncDirectImport(
            $sourcePath,
            $metadata,
            $detectedPeriods,
            $replaceExistingPeriods,
            $replaceTargetRows
        );
    }

    private function resolveReadableSourcePath(string $sourcePath): ?string
    {
        $trimmed = trim($sourcePath);
        if ($trimmed === '') {
            return null;
        }

        $resolved = realpath($trimmed);
        if ($resolved === false || !is_file($resolved) || !is_readable($resolved)) {
            return null;
        }

        return $resolved;
    }

    private function normalizeRequestedPeriod(?string $value): ?string
    {
        $trimmed = trim((string) $value);
        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/^\d{8}$/', $trimmed) === 1) {
            $trimmed = substr($trimmed, 0, 2) . '-' . substr($trimmed, 2, 2) . '-' . substr($trimmed, 4, 4);
        }

        try {
            return StrictDateParser::normalize($trimmed);
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeDailyLoanHeaderToken(string $value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value);
        $value = strtoupper(trim($value));
        $value = preg_replace('/[^A-Z0-9]+/', '_', $value);

        return trim((string) $value, '_');
    }

    private function isDailyLoanHeaderRow(array $parsed): bool
    {
        $tokens = array_map(
            fn ($value) => $this->normalizeDailyLoanHeaderToken((string) $value),
            array_values($parsed)
        );

        if (($tokens[0] ?? null) !== 'PERIODE') {
            return false;
        }

        $requiredGroups = [
            ['KODE_KANWIL1', 'KODE_KANWIL'],
            ['CIFNO'],
            ['NOMOR_REKENING1', 'NOMOR_REKENING'],
            ['BAKI_DEBET1', 'BAKI_DEBET'],
        ];

        $matchedGroups = 0;
        foreach ($requiredGroups as $group) {
            foreach ($group as $candidate) {
                if (in_array($candidate, $tokens, true)) {
                    $matchedGroups++;
                    break;
                }
            }
        }

        return $matchedGroups >= 3;
    }

    private function inspectDailyLoanCsv(string $sourcePath): array
    {
        $handle = @fopen($sourcePath, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('Gagal membuka file CSV Daily Loan.');
        }

        $headerFound = false;
        $delimiter = ',';
        $lineNumber = 0;
        $sampledDataRows = 0;
        $periods = [];
        $headerLineNumber = null;
        $headers = [];

        try {
            while (($line = fgets($handle)) !== false) {
                $lineNumber++;
                $rawLine = preg_replace('/^\xEF\xBB\xBF/', '', trim((string) $line));
                if ($rawLine === '') {
                    continue;
                }

                if (!$headerFound) {
                    foreach ([',', ';', "\t"] as $candidateDelimiter) {
                        $parsed = str_getcsv($rawLine, $candidateDelimiter, '"', '\\');
                        if ($this->isDailyLoanHeaderRow((array) $parsed)) {
                            $headerFound = true;
                            $delimiter = $candidateDelimiter;
                            $headerLineNumber = $lineNumber;
                            $headers = array_values((array) $parsed);
                            break;
                        }
                    }

                    continue;
                }

                $parsed = str_getcsv($rawLine, $delimiter, '"', '\\');
                $periodValue = trim((string) ($parsed[0] ?? ''));
                if ($periodValue === '') {
                    continue;
                }

                try {
                    $normalized = StrictDateParser::normalize($periodValue);
                } catch (\Throwable) {
                    $normalized = null;
                }

                if ($normalized !== null) {
                    $periods[$normalized] = true;
                }

                $sampledDataRows++;
                if ($sampledDataRows >= 2000 && count($periods) > 0) {
                    break;
                }
            }
        } finally {
            fclose($handle);
        }

        return [
            'header_found' => $headerFound,
            'delimiter' => $delimiter,
            'header_line_number' => $headerLineNumber,
            'headers' => $headers,
            'periods' => array_keys($periods),
            'sampled_data_rows' => $sampledDataRows,
            'source_size_bytes' => (int) (filesize($sourcePath) ?: 0),
            'line_number_scanned' => $lineNumber,
        ];
    }

    private function runSyncDirectImport(
        string $sourcePath,
        array $metadata,
        array $detectedPeriods,
        bool $replaceExistingPeriods,
        int $replaceTargetRows
    ): JsonResponse {
        $jobId = $this->jobService->createImportJobRecord(
            self::DAILY_LOAN_REPORT_ID,
            $sourcePath,
            0,
            [
                'controller' => static::class,
                'mode' => 'backend_sync_daily_loan_direct',
                'table_name' => self::DAILY_LOAN_TABLE,
                'backend_source_path' => $sourcePath,
                'backend_detected_periods' => $detectedPeriods,
                'replace_existing_periods' => $replaceExistingPeriods,
            ],
            auth()->id()
        );

        $startedAt = microtime(true);
        $stagedPath = null;
        $sourceCleanup = false;
        $skippedRows = [];
        $skippedCount = 0;
        $sourcePreNormalized = false;
        $loadBackend = 'php';
        $inserted = 0;
        $totalRows = 0;
        $failedRows = 0;

        try {
            $this->progressService->markProcessing($jobId, [
                'status' => 'processing',
                'phase' => 'staging',
                'percent' => 10,
                'message' => 'Menormalkan CSV Daily Loan backend untuk direct LOAD DATA...',
                'total_rows' => 0,
                'processed_rows' => 0,
            ]);

            $importController = app(ImportExcelController::class);
            $loadSource = (array) $this->invokeImportExcelControllerMethod(
                $importController,
                'prepareDailyLoanDirectLoadSource',
                [$sourcePath, (string) ($metadata['delimiter'] ?? ','), null]
            );

            $stagedPath = (string) ($loadSource['path'] ?? $sourcePath);
            $sourceCleanup = (bool) ($loadSource['cleanup'] ?? false);
            $loadBackend = (string) ($loadSource['backend'] ?? 'php');
            $sourcePreNormalized = (bool) ($loadSource['source_pre_normalized'] ?? false);
            $skippedRows = array_values(array_unique(array_map('intval', (array) ($loadSource['skipped_rows'] ?? []))));
            $skippedCount = (int) ($loadSource['skipped_count'] ?? count($skippedRows));
            $headers = array_values((array) ($metadata['headers'] ?? []));

            $this->progressService->cacheProgress($jobId, [
                'status' => 'processing',
                'phase' => 'loading',
                'percent' => 35,
                'message' => 'Menjalankan direct LOAD DATA Daily Loan dari source backend terstandar...',
            ]);

            $loadPlan = $this->invokeImportExcelControllerMethod(
                $importController,
                'buildDirectDailyLoanCsvLoadPlan',
                [$stagedPath, $headers, [
                    'source_backend' => $loadBackend,
                    'source_pre_normalized' => $sourcePreNormalized,
                    'replace_existing_periods' => $replaceExistingPeriods,
                    'replace_periods' => $detectedPeriods,
                ]]
            );
            $inserted = (int) $this->invokeImportExcelControllerMethod(
                $importController,
                'executeDirectDailyLoanCsvLoad',
                [$stagedPath, $loadPlan]
            );

            $totalRows = max($inserted, (int) ($loadSource['written_rows'] ?? 0));
            if ($skippedCount > 0) {
                $totalRows = max($totalRows, $inserted + $skippedCount);
            }
            $failedRows = max(0, $totalRows - $inserted);

            $this->progressService->markCompleted($jobId, $inserted, $failedRows, $totalRows, [
                'status' => 'completed',
                'phase' => 'loading',
                'percent' => 100,
                'message' => 'Direct LOAD DATA Daily Loan selesai diproses.',
                'total_rows' => $totalRows,
                'processed_rows' => $totalRows,
                'total_success' => $inserted,
                'total_failed' => $failedRows,
            ]);

            $this->cleanupService->dispatchImportedJobSync($jobId, source: static::class);
            $durationSeconds = round(microtime(true) - $startedAt, 3);

            return response()->json([
                'status' => 'completed',
                'job_id' => $jobId,
                'message' => 'Direct LOAD DATA Daily Loan selesai diproses.',
                'source_path' => $sourcePath,
                'staged_path' => $stagedPath,
                'detected_periods' => $detectedPeriods,
                'deleted_existing_rows' => $replaceTargetRows,
                'load_backend' => $loadBackend,
                'source_pre_normalized' => $sourcePreNormalized,
                'skipped_count' => $skippedCount,
                'skipped_rows' => array_slice($skippedRows, 0, 500),
                'source_size_bytes' => (int) ($metadata['source_size_bytes'] ?? filesize($sourcePath) ?: 0),
                'sampled_data_rows' => (int) ($metadata['sampled_data_rows'] ?? 0),
                'duration_seconds' => $durationSeconds,
                'total_rows' => $totalRows,
                'total_success' => $inserted,
                'total_failed' => $failedRows,
            ]);
        } catch (\Throwable $e) {
            $this->progressService->markFailed($jobId, $e->getMessage());

            return response()->json([
                'status' => 'failed',
                'job_id' => $jobId,
                'message' => $e->getMessage(),
                'source_path' => $sourcePath,
                'staged_path' => $stagedPath,
                'detected_periods' => $detectedPeriods,
                'deleted_existing_rows' => $replaceTargetRows,
                'load_backend' => $loadBackend,
                'source_pre_normalized' => $sourcePreNormalized,
                'skipped_count' => $skippedCount,
                'skipped_rows' => array_slice($skippedRows, 0, 500),
                'source_size_bytes' => (int) ($metadata['source_size_bytes'] ?? filesize($sourcePath) ?: 0),
                'sampled_data_rows' => (int) ($metadata['sampled_data_rows'] ?? 0),
                'duration_seconds' => round(microtime(true) - $startedAt, 3),
            ], 500);
        } finally {
            if ($sourceCleanup && $stagedPath !== null && is_file($stagedPath)) {
                @unlink($stagedPath);
            }
        }
    }

    private function createDirectLoadReadyCsv(string $sourcePath, array $metadata): string
    {
        $headerLineNumber = (int) ($metadata['header_line_number'] ?? 0);
        if ($headerLineNumber <= 0) {
            throw new \RuntimeException('Header line Daily Loan tidak ditemukan untuk staging direct import.');
        }

        $directory = Storage::path(self::STORAGE_DIR . '/' . now()->format('Ymd'));
        File::ensureDirectoryExists($directory);
        $stagedPath = $directory . DIRECTORY_SEPARATOR . 'direct_load_' . Str::uuid()->toString() . '.csv';

        $sourceHandle = @fopen($sourcePath, 'rb');
        if ($sourceHandle === false) {
            throw new \RuntimeException('Gagal membuka file sumber Daily Loan untuk staging direct import.');
        }

        $targetHandle = @fopen($stagedPath, 'wb');
        if ($targetHandle === false) {
            fclose($sourceHandle);
            throw new \RuntimeException('Gagal membuat file staging direct import Daily Loan.');
        }

        $lineNumber = 0;

        try {
            while (($line = fgets($sourceHandle)) !== false) {
                $lineNumber++;
                if ($lineNumber < $headerLineNumber) {
                    continue;
                }

                fwrite($targetHandle, $line);
            }
        } finally {
            fclose($sourceHandle);
            fclose($targetHandle);
        }

        return $stagedPath;
    }

    private function invokeImportExcelControllerMethod(
        ImportExcelController $controller,
        string $methodName,
        array $arguments = []
    ): mixed {
        $method = new \ReflectionMethod($controller, $methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($controller, $arguments);
    }

    private function copySourceIntoImportStorage(string $sourcePath): string
    {
        $directory = self::STORAGE_DIR . '/' . now()->format('Ymd');
        $targetName = now()->format('His') . '_' . Str::uuid()->toString() . '_' . File::basename($sourcePath);
        $relativePath = $directory . '/' . $targetName;
        $absolutePath = Storage::path($relativePath);
        File::ensureDirectoryExists(dirname($absolutePath));

        $sourceHandle = @fopen($sourcePath, 'rb');
        if ($sourceHandle === false) {
            throw new \RuntimeException('Gagal membuka file sumber untuk disalin ke storage import.');
        }

        $targetHandle = @fopen($absolutePath, 'wb');
        if ($targetHandle === false) {
            fclose($sourceHandle);
            throw new \RuntimeException('Gagal membuat file staging backend Daily Loan di storage.');
        }

        try {
            $copiedBytes = stream_copy_to_stream($sourceHandle, $targetHandle);
            if ($copiedBytes === false || $copiedBytes <= 0) {
                throw new \RuntimeException('Gagal menyalin file sumber ke storage import backend.');
            }
        } finally {
            fclose($sourceHandle);
            fclose($targetHandle);
        }

        return $relativePath;
    }
}
