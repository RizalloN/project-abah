<?php

namespace App\Http\Controllers\Import;

use App\Services\Import\MySqlBulkLoadService;
use App\Services\Import\ImportCleanupService;
use App\Support\StrictDateParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportSimpananMultiPnCsvController extends ImportExcelController
{
    private const REPORT_ID = 9;
    private const PREVIEW_ROW_LIMIT = 80;
    private const PREVIEW_UNIQUE_SCAN_LIMIT = 250;
    private const PREVIEW_MAX_UNIQUE_VALUES = 120;
    private const ROW_COUNT_ESTIMATE_SAMPLE_BYTES = 1048576;

    private function useSimpananReport(Request $request): Request
    {
        $request->merge(['id_report' => self::REPORT_ID]);
        session([
            'active_id_report' => self::REPORT_ID,
            'excel_import_source' => 'simpanan_csv',
        ]);

        return $request;
    }

    public function upload(Request $request)
    {
        $request = $this->useSimpananReport($request);

        $request->validate([
            'file' => [
                'required',
                'file',
                function (string $attribute, $file, \Closure $fail) {
                    $originalExtension = $file ? strtolower((string) $file->getClientOriginalExtension()) : '';
                    $detectedMimeType = $file ? strtolower((string) ($file->getMimeType() ?: $file->getClientMimeType())) : '';

                    $allowedExtensions = ['csv', 'txt'];
                    $allowedMimeTypes = [
                        'text/plain',
                        'text/csv',
                        'application/csv',
                        'text/comma-separated-values',
                        'text/x-csv',
                    ];

                    if (
                        !in_array($originalExtension, $allowedExtensions, true)
                        && !in_array($detectedMimeType, $allowedMimeTypes, true)
                    ) {
                        $fail('File harus berformat CSV (.csv atau .txt).');
                    }
                },
            ],
        ], [
            'file.required' => 'File CSV wajib dipilih.',
            'file.file' => 'Upload gagal, file CSV tidak valid.',
        ]);

        $file = $request->file('file');

        if (!$file || !$file->isValid()) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => 'Upload gagal, file CSV tidak valid.'], 422);
            }

            return back()->with('error', 'Upload gagal, file CSV tidak valid.');
        }

        if (!file_exists(Storage::path('excel_imports'))) {
            Storage::makeDirectory('excel_imports');
        }

        $path = $file->store('excel_imports');
        $cacheKey = 'excel_preview_' . md5($path . '|simpanan_csv|' . (auth()->id() ?? 'guest') . '|' . microtime(true));

        session([
            'excel_path' => $path,
            'active_id_report' => self::REPORT_ID,
            'excel_preview_key' => $cacheKey,
            'excel_import_source' => 'simpanan_csv',
        ]);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'cache_key' => $cacheKey,
            ]);
        }

        return redirect()->route('import.simpanan.csv.preview');
    }

    public function preparePreviewStream(Request $request)
    {
        $request = $this->useSimpananReport($request);

        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        $sessionPath = session('excel_path');
        $cacheKey = session('excel_preview_key');
        request()->session()->save();

        return response()->stream(function () use ($sessionPath, $cacheKey) {
            $send = function (string $event, array $data) {
                echo "event: {$event}\n";
                echo 'data: ' . json_encode($data) . "\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            };

            try {
                if (!$sessionPath) {
                    $send('error_msg', ['message' => 'Sesi upload CSV Simpanan MultiPN tidak ditemukan.']);
                    return;
                }

                $path = Storage::path(urldecode($sessionPath));
                if (!file_exists($path)) {
                    $send('error_msg', ['message' => 'File CSV Simpanan MultiPN tidak ditemukan di server.']);
                    return;
                }

                $send('progress', ['percent' => 5, 'message' => 'Membaca header CSV Simpanan MultiPN...', 'step' => 1]);
                $payload = $this->buildPreviewPayloadFromCsvFile($path);
                $send('progress', ['percent' => 72, 'message' => 'Header ditemukan. Menyusun preview dan opsi filter...', 'step' => 3]);

                $cachePayload = [
                    'headers' => $payload['headers'],
                    'preview' => $payload['preview'],
                    'formattedUniqueValues' => $payload['formattedUniqueValues'],
                    'displayFilterMap' => $payload['displayFilterMap'],
                    'headerIndex' => $payload['header_index'] ?? 0,
                    'normalizedHeaders' => $payload['normalized_headers'] ?? [],
                    'path' => urldecode($sessionPath),
                    'stagedCsvPath' => $path,
                    'delimiter' => $payload['delimiter'] ?? ';',
                    'currentDelimiter' => $payload['delimiter'] ?? ';',
                    'total_rows' => $payload['total_rows'] ?? ($payload['total_sample_rows'] ?? null),
                ];

                $useCacheKey = $cacheKey ?: ('excel_preview_' . md5(urldecode($sessionPath) . '|simpanan_csv_preview|' . microtime(true)));
                Cache::put($useCacheKey, $cachePayload, now()->addMinutes(10));

                $send('progress', ['percent' => 95, 'message' => 'Finalisasi preview CSV Simpanan MultiPN...', 'step' => 5]);
                $send('ready', [
                    'redirect' => route('import.simpanan.csv.preview', ['ck' => $useCacheKey]),
                ]);
            } catch (\Throwable $e) {
                Log::error('SIMPANAN MULTIPN CSV PREVIEW ERROR: ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());
                $send('error_msg', ['message' => $this->formatSafeImportFailureMessage('Gagal menyiapkan preview CSV Simpanan MultiPN: ', $e)]);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-store',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);
    }

    public function preview(Request $request)
    {
        $request = $this->useSimpananReport($request);
        $cacheKey = (string) $request->query('ck', '');
        $payload = $cacheKey !== '' ? Cache::get($cacheKey) : null;

        if (!is_array($payload)) {
            $sessionPath = session('excel_path', $request->path);
            if (!$sessionPath) {
                return redirect()->route('import.index')->with('sweet_warning', [
                    'title' => 'Sesi Berakhir',
                    'text' => 'Silakan upload ulang.',
                ]);
            }

            $relativePath = urldecode((string) $sessionPath);
            $absolutePath = Storage::path($relativePath);
            if (!file_exists($absolutePath)) {
                return redirect()->route('import.index')->with('sweet_warning', [
                    'title' => 'File Tidak Ditemukan',
                    'text' => 'File mungkin sudah terhapus.',
                ]);
            }

            $built = $this->buildPreviewPayloadFromCsvFile($absolutePath);
            $payload = [
                'headers' => $built['headers'],
                'preview' => $built['preview'],
                'formattedUniqueValues' => $built['formattedUniqueValues'],
                'displayFilterMap' => $built['displayFilterMap'],
                'headerIndex' => $built['header_index'] ?? 0,
                'normalizedHeaders' => $built['normalized_headers'] ?? [],
                'path' => $relativePath,
                'stagedCsvPath' => $absolutePath,
                'delimiter' => $built['delimiter'] ?? ';',
                'currentDelimiter' => $built['delimiter'] ?? ';',
                'total_rows' => $built['total_rows'] ?? ($built['total_sample_rows'] ?? null),
            ];
            $cacheKey = 'excel_preview_' . md5($relativePath . '|simpanan_csv_direct|' . microtime(true));
            Cache::put($cacheKey, $payload, now()->addMinutes(10));
        }

        $previewMeta = [
            'path' => $payload['path'] ?? null,
            'staged_csv_path' => $payload['stagedCsvPath'] ?? null,
            'header_index' => isset($payload['headerIndex']) ? (int) $payload['headerIndex'] : 0,
            'normalized_headers' => (array) ($payload['normalizedHeaders'] ?? []),
            'source_headers' => (array) ($payload['sourceHeaders'] ?? ($payload['normalizedHeaders'] ?? [])),
            'total_rows' => isset($payload['total_rows']) ? (int) $payload['total_rows'] : null,
            'delimiter' => isset($payload['delimiter']) ? (string) $payload['delimiter'] : ';',
        ];

        $this->excelImportJobService()->putPreviewState($cacheKey, [
            'displayFilterMap' => $payload['displayFilterMap'] ?? [],
            'previewMeta' => $previewMeta,
        ]);

        session([
            'excel_display_filter_map' => $payload['displayFilterMap'] ?? [],
            'import_display_to_source_map' => $payload['displayFilterMap'] ?? [],
            'excel_preview_meta' => $previewMeta,
            'excel_headers' => (array) ($payload['normalizedHeaders'] ?? []),
        ]);

        $delimiter = (string) ($payload['delimiter'] ?? $payload['currentDelimiter'] ?? ';');

        return view('import.preview', [
            'headers' => $payload['headers'] ?? [],
            'previewData' => $this->buildSimpananLegacyPreviewRows(
                (array) ($payload['headers'] ?? []),
                (array) ($payload['preview'] ?? [])
            ),
            'formattedUniqueValues' => [],
            'filePath' => $payload['path'] ?? null,
            'currentDelimiter' => $delimiter,
            'lockDelimiterSelector' => true,
            'fixedDelimiterLabel' => $this->describeDelimiter($delimiter),
            'hideDelimiterCard' => true,
            'processRoute' => '',
            'backRoute' => route('import.index'),
            'initRoute' => route('import.simpanan.csv.init'),
            'streamRoute' => route('import.simpanan.csv.stream'),
            'previewStateKey' => $cacheKey,
            'forceAllFiltersCheckedOnLoad' => true,
            'filtersDisabled' => true,
            'pageTitle' => 'Preview Simpanan Multi PN',
            'previewBannerTitle' => 'Preview Simpanan Multi PN',
        ]);
    }

    public function initImport(Request $request)
    {
        $request = $this->useSimpananReport($request);
        $response = parent::initExcelImport($request);

        try {
            $responseData = json_decode($response->getContent(), true);
            if (isset($responseData['job_id']) && $responseData['job_id'] > 0) {
                $this->populateDirectImportJobState((int) $responseData['job_id']);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to populate headers after initImport: ' . $e->getMessage());
        }

        return $response;
    }

    private function populateDirectImportJobState(int $jobId): void
    {
        try {
            $sessionPath = session('excel_path', '');
            if (!$sessionPath) {
                return;
            }

            $path = Storage::path(urldecode($sessionPath));
            if (!file_exists($path)) {
                return;
            }

            $previewMeta = (array) session('excel_preview_meta', []);
            $payload = [];
            $headers = array_values(array_filter(
                (array) ($previewMeta['normalized_headers'] ?? []),
                static fn ($header): bool => trim((string) $header) !== ''
            ));
            if ($headers === []) {
                $payload = $this->buildPreviewPayloadFromCsvFile($path);
                $headers = $payload['normalized_headers'] ?? [];
            }

            $dataRows = $this->estimateCsvPhysicalDataRows($path);
            if ($dataRows <= 0) {
                $dataRows = (int) ($previewMeta['total_rows'] ?? ($payload['total_sample_rows'] ?? 0));
            }

            if (!empty($headers)) {
                $jobState = $this->excelImportJobService()->getImportJobState($jobId) ?: [];
                $params = (array) ($jobState['params'] ?? []);
                $relativePath = urldecode($sessionPath);

                $params = array_merge($params, [
                    'table_name' => 'simpanan_multipn',
                    'file_path' => $params['file_path'] ?? $relativePath,
                    'staged_csv_path' => file_exists($path) ? $path : ($params['staged_csv_path'] ?? null),
                    'header_index' => $params['header_index'] ?? (int) ($previewMeta['header_index'] ?? ($payload['header_index'] ?? 0)),
                    'total_rows' => $dataRows,
                    'delimiter' => $params['delimiter'] ?? (string) ($previewMeta['delimiter'] ?? ($payload['delimiter'] ?? ';')),
                    'job_id' => $jobId,
                ]);

                $jobState['params'] = $params;
                $jobState['headers'] = $headers;
                $this->excelImportJobService()->putImportJobState($jobId, $jobState);
                session(['excel_headers' => $headers]);

                Log::debug("Populated direct import state for Simpanan MultiPN job {$jobId}: " . count($headers) . ' headers');
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to populate direct import state for Simpanan MultiPN job ' . $jobId . ': ' . $e->getMessage());
        }
    }

    protected function buildPreviewPayloadFromCsvFile(string $csvPath): array
    {
        if (!file_exists($csvPath)) {
            throw new \RuntimeException('File CSV tidak ditemukan.');
        }

        $fileSize = @filesize($csvPath);
        $fileMtime = @filemtime($csvPath);
        $cacheKey = 'simpanan_multipn_preview:v2:' . md5($csvPath . '|' . $fileSize . '|' . $fileMtime);
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && !empty($cached['headers'])) {
            return $cached;
        }

        $delimiterCacheKey = 'csv_delimiter:' . md5($csvPath . '|' . $fileSize);
        $delimiter = Cache::get($delimiterCacheKey);
        if (!is_string($delimiter) || $delimiter === '') {
            $delimiter = $this->detectCsvDelimiter($csvPath);
            Cache::put($delimiterCacheKey, $delimiter, now()->addHours(24));
        }

        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Gagal membuka file CSV.');
        }

        $startedAt = microtime(true);

        try {
            $headers = $this->readCsvRecord($handle, $delimiter);
            if ($headers === false || empty($headers)) {
                throw new \RuntimeException('Header CSV tidak ditemukan.');
            }

            foreach ($headers as $index => $header) {
                $header = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header);
                $headers[$index] = trim($header) !== '' ? trim($header) : 'COL_' . $index;
            }

            $cleanPreview = [];
            $formattedUniqueValues = [];
            $rowsProcessedForUniques = 0;
            $sampledRows = 0;

            foreach (array_keys($headers) as $index) {
                $formattedUniqueValues[$index] = [];
            }

            while (($row = $this->readCsvRecord($handle, $delimiter)) !== false) {
                if (empty(array_filter((array) $row, static fn ($value): bool => trim((string) $value) !== ''))) {
                    continue;
                }

                $sampledRows++;
                $row = $this->padPreviewRow((array) $row, count($headers));
                $displayRow = [];

                foreach ($headers as $index => $header) {
                    $value = $this->normalizePreviewValue($row[$index] ?? null);
                    $displayRow[$header] = $value;

                    if ($rowsProcessedForUniques < self::PREVIEW_UNIQUE_SCAN_LIMIT) {
                        $formattedValue = $value === null || $value === '' ? '(Blank)' : (string) $value;
                        if (
                            isset($formattedUniqueValues[$index][$formattedValue])
                            || count($formattedUniqueValues[$index]) < self::PREVIEW_MAX_UNIQUE_VALUES
                        ) {
                            $formattedUniqueValues[$index][$formattedValue] = true;
                        }
                    }
                }

                if (!$this->hasMeaningfulImportData($displayRow)) {
                    continue;
                }

                if (count($cleanPreview) < self::PREVIEW_ROW_LIMIT) {
                    $cleanPreview[] = $displayRow;
                }

                if ($rowsProcessedForUniques < self::PREVIEW_UNIQUE_SCAN_LIMIT) {
                    $rowsProcessedForUniques++;
                }

                if (count($cleanPreview) >= self::PREVIEW_ROW_LIMIT && $rowsProcessedForUniques >= self::PREVIEW_UNIQUE_SCAN_LIMIT) {
                    break;
                }
            }

            foreach ($formattedUniqueValues as $index => $valuesMap) {
                $keys = array_keys($valuesMap);
                usort($keys, 'strnatcmp');
                $formattedUniqueValues[$index] = $keys;
            }

            $filteredPreview = $this->stripIgnoredPreviewColumns(
                $headers,
                $cleanPreview,
                $formattedUniqueValues,
                'simpanan_multipn'
            );

            $orderedPreview = $this->orderPreviewColumns(
                $filteredPreview['headers'],
                $filteredPreview['formatted_unique_values'],
                $filteredPreview['rows'],
                'simpanan_multipn'
            );

            $estimatedRows = $this->estimateCsvPhysicalDataRows($csvPath);
            Log::info('Simpanan MultiPN CSV preview prepared with bounded sampling.', [
                'file' => basename($csvPath),
                'sampled_rows' => $sampledRows,
                'preview_rows' => count($cleanPreview),
                'estimated_rows' => $estimatedRows,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            $result = [
                'headers' => $orderedPreview['headers'],
                'preview' => $orderedPreview['preview'],
                'formattedUniqueValues' => $orderedPreview['formatted_unique_values'],
                'displayFilterMap' => $orderedPreview['display_filter_map'],
                'header_index' => 0,
                'header_row' => 1,
                'normalized_headers' => $filteredPreview['headers'],
                'rows_scanned' => min($rowsProcessedForUniques, $sampledRows),
                'total_sample_rows' => $estimatedRows > 0 ? $estimatedRows : $sampledRows,
                'total_rows' => $estimatedRows > 0 ? $estimatedRows : null,
                'delimiter' => $delimiter,
            ];

            Cache::put($cacheKey, $result, now()->addHours(6));

            return $result;
        } finally {
            fclose($handle);
        }
    }

    private function buildSimpananLegacyPreviewRows(array $headers, array $previewRows): array
    {
        $indexedRows = [];

        foreach ($previewRows as $row) {
            $row = is_array($row) ? $row : (array) $row;
            $indexedRow = [];
            foreach ($headers as $header) {
                $value = $row[$header] ?? null;
                $indexedRow[] = $value === null ? '' : (string) $value;
            }
            $indexedRows[] = $indexedRow;
        }

        return $indexedRows;
    }

    private function describeDelimiter(string $delimiter): string
    {
        return match ($delimiter) {
            ';' => 'Titik Koma ( ; )',
            "\t" => 'Tab',
            '|' => 'Garis Lurus / Pipe ( | )',
            '.' => 'Titik ( . )',
            default => 'Koma ( , )',
        };
    }

    private function padPreviewRow(array $row, int $expectedColumns): array
    {
        if (count($row) < $expectedColumns) {
            return array_pad($row, $expectedColumns, null);
        }

        if (count($row) > $expectedColumns) {
            return array_slice($row, 0, $expectedColumns);
        }

        return $row;
    }

    private function normalizePreviewValue($value)
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);
            return $value === '' ? null : $value;
        }

        return $value;
    }

    private function estimateCsvPhysicalDataRows(string $csvPath): int
    {
        $fileSize = @filesize($csvPath);
        if ($fileSize === false || $fileSize <= 0) {
            return 0;
        }

        $handle = @fopen($csvPath, 'rb');
        if ($handle === false) {
            return 0;
        }

        $lineCount = 0;
        $lastByte = '';

        try {
            while (!feof($handle)) {
                $chunk = (string) fread($handle, 4 * 1024 * 1024);
                if ($chunk === '') {
                    continue;
                }

                $lineCount += substr_count($chunk, "\n");
                $lastByte = substr($chunk, -1);
            }
        } finally {
            @fclose($handle);
        }

        if ($lineCount <= 0 && $lastByte === '') {
            return 0;
        }

        if ($lastByte !== "\n") {
            $lineCount++;
        }

        return max(0, $lineCount - 1);
    }

    public function processImportStream(Request $request)
    {
        $request = $this->useSimpananReport($request);
        $this->configureLongRunningImportRuntime();

        $sessionParams = session('excel_import_params', []);
        $jobId = (int) ($sessionParams['job_id'] ?? $request->query('job_id', 0));
        if ($jobId > 0) {
            $this->populateDirectImportJobState($jobId);
        }
        $jobState = $jobId > 0 ? $this->excelImportJobService()->getImportJobState($jobId) : [];
        $params = !empty($jobState['params']) ? (array) $jobState['params'] : $sessionParams;
        $normalizedHeaders = $this->resolveNormalizedHeadersForDirectImport($jobId, $jobState, $params);
        $activeFilters = (array) ($params['active_filters'] ?? []);
        $selectedColumns = $this->resolveSelectedColumns($params, $normalizedHeaders);
        $streamFailureResponse = function (string $message) use ($jobId) {
            request()->session()->save();

            return response()->stream(function () use ($jobId, $message) {
                if ($jobId > 0) {
                    $this->updateImportJobStatusSafely($jobId, [
                        'status' => 'failed',
                        'updated_at' => now(),
                    ]);
                    $this->progressService()->markFailed($jobId, $message);
                }

                echo "event: error\n";
                echo 'data: ' . json_encode(['message' => $message]) . "\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }, 200, [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache, no-store',
                'X-Accel-Buffering' => 'no',
                'Connection' => 'keep-alive',
            ]);
        };

        try {
            $eligibility = $this->resolveDirectCsvFastPathEligibility('simpanan_multipn', $params, $normalizedHeaders);
        } catch (\Throwable $e) {
            Log::warning('Simpanan MultiPN fast-path eligibility check gagal. Import dihentikan tanpa fallback lambat.', [
                'job_id' => $jobId,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return $streamFailureResponse(
                $this->formatSafeImportFailureMessage('Fast import Simpanan MultiPN gagal divalidasi: ', $e)
            );
        }

        if (!($eligibility['eligible'] ?? false)) {
            $reason = (string) ($eligibility['reason'] ?? 'Fast import tidak tersedia.');
            Log::warning('Simpanan MultiPN direct path tidak eligible. Import dihentikan tanpa fallback lambat: ' . $reason, [
                'job_id' => $jobId,
                'table_name' => 'simpanan_multipn',
            ]);
            return $streamFailureResponse(
                $this->formatSafeImportFailureMessage('Fast import Simpanan MultiPN tidak tersedia: ', $reason)
            );
        }

        $relativePath = (string) ($eligibility['relative_path'] ?? '');
        $absolutePath = (string) ($eligibility['absolute_path'] ?? '');
        $totalRows = (int) ($eligibility['total_rows'] ?? 0);

        // Calculate file fingerprint untuk deduplication check
        $contentHash = '';
        try {
            $contentHash = $this->calculateFileFingerprint($absolutePath);
            Log::debug('Simpanan MultiPN: Content fingerprint calculated', [
                'job_id' => $jobId,
                'content_hash' => $contentHash,
                'file_size' => @filesize($absolutePath),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Simpanan MultiPN: Fingerprint calculation failed (continuing): ' . $e->getMessage(), [
                'job_id' => $jobId,
                'file' => basename($absolutePath),
            ]);
        }

        // Acquire atomic lock to prevent simultaneous import of identical file
        // This prevents race condition where two jobs upload same file simultaneously
        $contentLock = null;
        if ($contentHash !== '') {
            $contentLock = Cache::lock("import_lock_content_{$contentHash}", 3600);
            if (!$contentLock->get()) {
                return response()->stream(function () use ($jobId) {
                    echo "event: error\n";
                    echo 'data: ' . json_encode([
                        'message' => 'File identik sedang diproses oleh job lain. Mohon tunggu beberapa saat atau reload halaman.'
                    ]) . "\n\n";
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();

                    if ($jobId > 0) {
                        $this->updateImportJobStatusSafely($jobId, [
                            'status' => 'failed',
                            'updated_at' => now(),
                        ]);
                        $this->progressService()->markFailed($jobId, 'File identik sedang diproses oleh job lain.');
                    }
                }, 200, [
                    'Content-Type' => 'text/event-stream',
                    'Cache-Control' => 'no-cache, no-store',
                    'X-Accel-Buffering' => 'no',
                    'Connection' => 'keep-alive',
                ]);
            }

            Log::info('Simpanan MultiPN: Content lock acquired', [
                'job_id' => $jobId,
                'content_hash' => $contentHash,
            ]);
        }

        request()->session()->save();

        return response()->stream(function () use ($jobId, $relativePath, $absolutePath, $totalRows, $normalizedHeaders, $selectedColumns, $activeFilters, $params, $contentHash, $contentLock) {
            $streamLock = null;
            $cleanupPaths = [];
            $usePolarsStage = !empty($activeFilters);
            $send = function (string $event, array $data) use ($jobId, $totalRows) {
                if ($jobId > 0 && $event === 'progress' && !$this->isImportTerminationRequested($jobId)) {
                    $resolvedTotalRows = (int) ($data['total_rows'] ?? $data['total'] ?? 0);
                    if ($resolvedTotalRows <= 0 && $totalRows > 0) {
                        $resolvedTotalRows = $totalRows;
                    }

                    $resolvedProcessedRows = (int) ($data['processed_rows'] ?? $data['rows_done'] ?? 0);
                    if ($resolvedProcessedRows > $resolvedTotalRows && $resolvedTotalRows > 0) {
                        $resolvedProcessedRows = $resolvedTotalRows;
                    }

                    $data['total_rows'] = $resolvedTotalRows;
                    $data['processed_rows'] = $resolvedProcessedRows;
                    $data['total'] = (int) ($data['total'] ?? $resolvedTotalRows);
                    $data['rows_done'] = (int) ($data['rows_done'] ?? $resolvedProcessedRows);

                    $this->cacheFastImportProgress($jobId, array_merge([
                        'status' => 'processing',
                    ], $data, [
                        'total_rows' => $resolvedTotalRows,
                        'processed_rows' => $resolvedProcessedRows,
                    ]));

                    $jobAttributes = ['status' => 'processing'];
                    if ($resolvedTotalRows > 0) {
                        $jobAttributes['total_files'] = $resolvedTotalRows;
                    }

                    $this->progressService()->updateJob($jobId, $jobAttributes);
                }

                echo "event: {$event}\n";
                echo 'data: ' . json_encode($data) . "\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            };

            try {
                if ($jobId > 0) {
                    $streamLock = Cache::lock('import_excel_stream_job_' . $jobId, 7200);

                    if (!$streamLock->get()) {
                        $job = DB::table('import_jobs')->where('id', $jobId)->first();
                        $progress = $this->progressService()->getCachedProgress($jobId);

                        if ($job && $this->shouldRecoverStalledSimpananMultiPnStreamLock($job, $progress)) {
                            Log::warning('Simpanan MultiPN import stream lock recovered from stale early processing state.', [
                                'job_id' => $jobId,
                                'status' => $job->status ?? null,
                                'updated_at' => $job->updated_at ?? null,
                                'progress_percent' => $progress['percent'] ?? null,
                            ]);

                            Cache::lock('import_excel_stream_job_' . $jobId, 1)->forceRelease();
                            $streamLock = Cache::lock('import_excel_stream_job_' . $jobId, 7200);

                            if (!$streamLock->get()) {
                                throw new \RuntimeException('Import Simpanan MultiPN masih dikunci proses lama. Silakan ulangi beberapa detik lagi.');
                            }
                        } else {

                            if ($job && in_array($job->status, ['completed', 'failed', 'failed_partial'], true)) {
                                $send('complete', [
                                    'total_success' => (int) ($job->total_success ?? 0),
                                    'total_failed' => (int) ($job->total_failed ?? 0),
                                    'total_rows' => (int) ($job->total_files ?? 0),
                                ]);
                                return;
                            }

                            // Already processing: poll progress
                            $send('progress', [
                                'percent' => 5,
                                'message' => 'Job sedang diproses. Menyambung ke progress...',
                            ]);

                            while (true) {
                                $currentJob = DB::table('import_jobs')->where('id', $jobId)->first();
                                if (!$currentJob) break;

                                $progress = $this->progressService()->getStatusPayload($jobId);
                                if ($progress) {
                                    $send('progress', $progress);
                                }

                                if (in_array($currentJob->status, ['completed', 'failed', 'failed_partial', 'terminated'], true)) {
                                    if ($currentJob->status === 'completed') {
                                        $send('complete', [
                                            'total_success' => (int) ($currentJob->total_success ?? 0),
                                            'total_failed' => (int) ($currentJob->total_failed ?? 0),
                                            'total_rows' => (int) ($currentJob->total_files ?? 0),
                                        ]);
                                    } else {
                                        $send('error', [
                                            'message' => $this->resolveImportJobFailureMessage(
                                                $currentJob,
                                                'Import gagal diproses (status: ' . $currentJob->status . ')'
                                            ),
                                        ]);
                                    }
                                    return;
                                }

                                sleep(1);
                                if (connection_aborted()) return;
                            }
                            return;
                        }
                    }
                }

                if (!file_exists($absolutePath)) {
                    $send('error', ['message' => 'File CSV Simpanan MultiPN tidak ditemukan di server.']);
                    return;
                }

                try {
                    app(MySqlBulkLoadService::class)->assertTransactionalTable('simpanan_multipn', 'import CSV Simpanan MultiPN');
                } catch (\RuntimeException $e) {
                    if ($jobId > 0) {
                        $this->updateImportJobStatusSafely($jobId, [
                            'status' => 'failed',
                            'updated_at' => now(),
                        ]);
                    }

                    $send('error', ['message' => $this->formatSafeImportFailureMessage('Fast import Simpanan MultiPN gagal: ', $e)]);
                    return;
                }

                $send('progress', [
                    'status' => 'processing',
                    'phase' => 'validating',
                    'percent' => 3,
                    'message' => $usePolarsStage
                        ? 'Validasi file filtered fast import Simpanan MultiPN...'
                        : 'Validasi file fast import Simpanan MultiPN...',
                    'rows_done' => 0,
                    'total' => $totalRows,
                    'speed' => 0,
                ]);

                // Validate file uniqueness before processing
                if ($contentHash !== '') {
                    try {
                        $periodHints = $this->collectSimpananMultiPnSnapshotPeriods($absolutePath, 10000);
                        if (!empty($periodHints)) {
                            $this->validateFileUniqueness($contentHash, $periodHints);
                            Log::info('Simpanan MultiPN: File uniqueness validation passed', [
                                'job_id' => $jobId,
                                'content_hash' => $contentHash,
                                'periods_count' => count($periodHints),
                            ]);
                        }
                    } catch (\RuntimeException $e) {
                        throw new \RuntimeException('Validasi keunikan file gagal: ' . $e->getMessage());
                    }
                }

                try {
                    if ($usePolarsStage) {
                        $preparedSource = $this->stageSimpananMultiPnCsvWithPolars(
                            $send,
                            $absolutePath,
                            $params['delimiter'] ?? null,
                            $activeFilters,
                            $jobId,
                            $selectedColumns,
                            $normalizedHeaders
                        );

                        if ($preparedSource === null || empty($preparedSource['path'])) {
                            throw new \RuntimeException('Polars gagal menyiapkan staging CSV Simpanan MultiPN untuk import filtered.');
                        }

                        $cleanupPaths[] = (string) $preparedSource['path'];
                        $loadPlan = $this->buildDirectCsvLoadPlan($preparedSource['path'], $normalizedHeaders, $selectedColumns, $send, [
                            'prepared_source' => $preparedSource,
                            'delimiter' => $params['delimiter'] ?? null,
                        ], $contentHash);
                    } else {
                        $preparedSource = $this->prepareSimpananMultiPnDirectLoadSource($absolutePath, $params['delimiter'] ?? null, $send);
                        $loadPlan = $this->buildDirectCsvLoadPlan($absolutePath, $normalizedHeaders, $selectedColumns, $send, [
                            'prepared_source' => $preparedSource,
                            'delimiter' => $params['delimiter'] ?? null,
                        ], $contentHash);
                        if (!empty($loadPlan['cleanup_path']) && is_string($loadPlan['cleanup_path'])) {
                            $cleanupPaths[] = $loadPlan['cleanup_path'];
                        }
                    }
                } catch (\Throwable $e) {
                    if ($this->isTerminationInterruption($e, $jobId)) {
                        $this->handleTerminationInterruption($jobId, $send);
                        return;
                    }

                    Log::error('Fast-path Simpanan MultiPN direct plan failed. Fallback lambat dimatikan: ' . $e->getMessage(), [
                        'job_id' => $jobId,
                        'absolute_path' => $absolutePath,
                    ]);

                    if ($jobId > 0) {
                        $this->updateImportJobStatusSafely($jobId, [
                            'status' => 'failed',
                            'updated_at' => now(),
                        ]);
                    }

                    $send('error', [
                        'message' => $this->formatSafeImportFailureMessage(
                            $usePolarsStage
                                ? 'Filtered fast import Simpanan MultiPN gagal: '
                                : 'Fast import CSV tidak bisa dilanjutkan: ',
                            $e
                        ),
                    ]);
                    return;
                }

                $plannedLoadRows = max(0, (int) ($loadPlan['validation_written_rows'] ?? $loadPlan['source_rows'] ?? 0));
                $plannedSkippedRows = max(0, (int) ($loadPlan['validation_skipped_count'] ?? 0));
                $plannedTotalRows = $plannedLoadRows + $plannedSkippedRows;
                if ($plannedTotalRows <= 0) {
                    $plannedTotalRows = $totalRows > 0 ? $totalRows : $plannedLoadRows;
                }

                $send('progress', [
                    'status' => 'processing',
                    'phase' => 'preparing_load_plan',
                    'percent' => 8,
                    'message' => $usePolarsStage
                        ? 'Menyiapkan direct LOAD DATA untuk filtered Simpanan MultiPN...'
                        : 'Menyiapkan direct LOAD DATA untuk Simpanan MultiPN...',
                    'rows_done' => 0,
                    'total' => $plannedTotalRows,
                    'speed' => 0,
                ]);

                $startTime = microtime(true);
                $beforeLoad = $this->buildSimpananMultiPnDirectLoadBeforeLoadCallback($loadPlan);
                $inserted = $this->executeDirectCsvLoad($loadPlan, $beforeLoad, $send, $jobId);
                $elapsed = max(microtime(true) - $startTime, 0.001);
                $speed = (int) ($inserted / $elapsed);
                $expectedLoadRows = max(0, (int) ($loadPlan['validation_written_rows'] ?? $loadPlan['source_rows'] ?? 0));
                $validationSkipped = max(0, (int) ($loadPlan['validation_skipped_count'] ?? 0));
                $finalTotalRows = $expectedLoadRows + $validationSkipped;
                if ($finalTotalRows <= 0) {
                    $finalTotalRows = $totalRows > 0 ? $totalRows : $inserted;
                }

                $failed = $validationSkipped + max(0, $expectedLoadRows - $inserted);

                $send('progress', [
                    'status' => 'processing',
                    'phase' => 'finalizing',
                    'percent' => 98,
                    'message' => 'LOAD DATA selesai. Menyelesaikan status import Simpanan MultiPN...',
                    'rows_done' => $inserted,
                    'total' => $finalTotalRows,
                    'speed' => $speed,
                ]);

                if ($jobId > 0) {
                    $terminalPayload = [
                        'phase' => 'completed',
                        'percent' => 100,
                        'message' => $failed > 0 ? 'Fast import Simpanan MultiPN selesai dengan kegagalan parsial.' : 'Fast import Simpanan MultiPN selesai.',
                        'processed_rows' => $inserted + $failed,
                        'total_rows' => $finalTotalRows,
                        'total_success' => $inserted,
                        'total_failed' => $failed,
                    ];

                    if ($failed > 0) {
                        $terminalStatus = $inserted > 0 ? 'failed_partial' : 'failed';
                        $this->progressService()->updateTotals(
                            $jobId,
                            $inserted,
                            $failed,
                            $finalTotalRows,
                            $terminalStatus,
                            array_merge(['status' => $terminalStatus], $terminalPayload)
                        );
                    } else {
                        $this->progressService()->markCompleted(
                            $jobId,
                            $inserted,
                            0,
                            $finalTotalRows,
                            array_merge(['status' => 'completed'], $terminalPayload)
                        );
                    }
                }

                $send('complete', [
                    'total_success' => $inserted,
                    'total_failed' => $failed,
                    'total_rows' => $finalTotalRows,
                ]);

                $this->cleanupSuccessfulImportArtifacts(
                    $jobId,
                    $relativePath,
                    $absolutePath,
                    (array) ($loadPlan['period_hints'] ?? []),
                    importBatchTimestamp: (string) ($loadPlan['import_batch_timestamp'] ?? '')
                );
            } catch (\Throwable $e) {
                if ($this->isTerminationInterruption($e, $jobId)) {
                    $this->handleTerminationInterruption($jobId, $send);
                    return;
                }

                Log::error('Simpanan MultiPN direct path gagal dan fallback lambat dimatikan: ' . $e->getMessage(), [
                    'job_id' => $jobId,
                    'absolute_path' => $absolutePath,
                ]);

                if ($jobId > 0) {
                    if ($this->isImportTerminationRequested($jobId)) {
                        $this->terminateImportJobNow($jobId, 'Job dihentikan melalui Job Management.');
                    } else {
                        $this->progressService()->markFailed($jobId, $this->formatSafeImportFailureMessage('Fast import CSV gagal: ', $e));
                    }
                }

                $send('error', [
                    'message' => $this->isImportTerminationRequested($jobId)
                        ? 'Import dihentikan oleh pengguna.'
                        : $this->formatSafeImportFailureMessage('Fast import CSV gagal: ', $e),
                ]);
                } finally {
                    // Release content lock (atomic lock preventing simultaneous imports)
                    if ($contentLock) {
                        try {
                            $contentLock->release();
                            Log::debug('Simpanan MultiPN: Content lock released', [
                                'job_id' => $jobId,
                            ]);
                        } catch (\Throwable $e) {
                            Log::warning('Failed to release Simpanan MultiPN content lock: ' . $e->getMessage());
                        }
                    }

                    // Release stream lock
                    if ($streamLock) {
                        try {
                            $streamLock->release();
                        } catch (\Throwable $e) {
                            Log::warning('Failed to release Simpanan MultiPN direct import lock for job ' . $jobId . ': ' . $e->getMessage());
                        }
                    }

                    foreach (array_unique(array_filter($cleanupPaths)) as $cleanupPath) {
                        if (is_string($cleanupPath) && $cleanupPath !== '' && file_exists($cleanupPath)) {
                            @unlink($cleanupPath);
                        }
                    }
                }
            }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-store',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);
    }

    private function isTerminationInterruption(\Throwable $e, int $jobId = 0): bool
    {
        $message = strtolower(trim($e->getMessage()));
        if ($message !== '' && str_contains($message, 'dihentikan oleh pengguna')) {
            return true;
        }

        return $jobId > 0 && $this->isImportTerminationRequested($jobId);
    }

    private function handleTerminationInterruption(int $jobId, callable $send): void
    {
        if ($jobId <= 0) {
            return;
        }

        $this->terminateImportJobNow($jobId, 'Job dihentikan melalui Job Management.');
        $send('error', [
            'message' => 'Import dihentikan oleh pengguna.',
        ]);
    }

    private function shouldRecoverStalledSimpananMultiPnStreamLock(object $job, array $progress): bool
    {
        if (strtolower(trim((string) ($job->status ?? ''))) !== 'processing') {
            return false;
        }

        $success = (int) ($job->total_success ?? 0);
        $failed = (int) ($job->total_failed ?? 0);
        if ($success > 0 || $failed > 0) {
            return false;
        }

        $processedRows = (int) ($progress['processed_rows'] ?? $progress['rows_done'] ?? 0);
        $percent = (int) ($progress['percent'] ?? 0);
        $phase = strtolower(trim((string) ($progress['phase'] ?? '')));
        if ($processedRows > 0 || $percent > 5 || !in_array($phase, ['', 'polars', 'validating'], true)) {
            return false;
        }

        try {
            return now()->diffInSeconds(\Carbon\Carbon::parse((string) ($job->updated_at ?? $job->created_at ?? now()))) >= 180;
        } catch (\Throwable) {
            return false;
        }
    }

    private function updateImportJobStatusSafely(int $jobId, array $attributes): void
    {
        if ($jobId <= 0) {
            return;
        }

        if ($this->isImportTerminationRequested($jobId)) {
            return;
        }

        $currentStatus = DB::table('import_jobs')->where('id', $jobId)->value('status');
        if (is_string($currentStatus) && strtolower(trim($currentStatus)) === 'terminated') {
            return;
        }

        DB::table('import_jobs')->where('id', $jobId)->update($attributes);
    }

    private function resolveImportJobFailureMessage(object $job, string $fallback): string
    {
        foreach (['error_message', 'message', 'error'] as $property) {
            $value = data_get($job, $property);
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return $fallback;
    }

    private function formatSafeImportFailureMessage(string $prefix, mixed $messageSource): string
    {
        $message = $messageSource instanceof \Throwable
            ? $messageSource->getMessage()
            : (string) $messageSource;

        $message = trim($message);
        if ($message === '') {
            return $prefix . 'Import gagal diproses.';
        }

        $normalized = strtolower($message);
        foreach ([
            'fatal error:',
            'uncaught ',
            'undefined variable',
            'undefined array key',
            'undefined property',
            'call to undefined function',
            'attempt to read property',
            'trying to access array offset',
            'typed property',
        ] as $needle) {
            if (str_contains($normalized, $needle)) {
                return $prefix . 'Import gagal diproses karena kesalahan internal pada worker. Detail teknis sudah dicatat di log server.';
            }
        }

        return $prefix . $message;
    }

    private function resolveNormalizedHeadersForDirectImport(int $jobId, array $jobState, array $params): array
    {
        $normalizedHeaders = !empty($jobState['headers']) ? (array) $jobState['headers'] : (array) session('excel_headers', []);
        $normalizedHeaders = array_values(array_filter(
            $normalizedHeaders,
            static fn ($header): bool => trim((string) $header) !== ''
        ));

        if ($normalizedHeaders !== []) {
            return $normalizedHeaders;
        }

        $candidatePaths = array_values(array_filter([
            (string) ($params['staged_csv_path'] ?? ''),
            (string) ($params['file_path'] ?? ''),
            (string) session('excel_path', ''),
        ], static fn ($path): bool => is_string($path) && trim($path) !== ''));

        foreach ($candidatePaths as $candidatePath) {
            $absolutePath = $candidatePath;
            if (!file_exists($absolutePath)) {
                $absolutePath = Storage::path($candidatePath);
            }

            if (!file_exists($absolutePath) || !$this->isCsvFile($absolutePath)) {
                continue;
            }

            try {
                $payload = $this->buildPreviewPayloadFromCsvFile($absolutePath);
                $normalizedHeaders = array_values(array_filter(
                    (array) ($payload['normalized_headers'] ?? []),
                    static fn ($header): bool => trim((string) $header) !== ''
                ));

                if ($normalizedHeaders === []) {
                    continue;
                }

                if ($jobId > 0) {
                    $jobState['headers'] = $normalizedHeaders;
                    $this->excelImportJobService()->putImportJobState($jobId, $jobState);
                }

                session(['excel_headers' => $normalizedHeaders]);

                Log::info('Recovered missing Simpanan MultiPN headers directly from CSV source.', [
                    'job_id' => $jobId,
                    'source_path' => $absolutePath,
                    'header_count' => count($normalizedHeaders),
                ]);

                return $normalizedHeaders;
            } catch (\Throwable $e) {
                Log::warning('Failed to recover Simpanan MultiPN headers directly from CSV source: ' . $e->getMessage(), [
                    'job_id' => $jobId,
                    'source_path' => $absolutePath,
                ]);
            }
        }

        return [];
    }

    private function resolveSelectedColumns(array $params, array $normalizedHeaders): array
    {
        $selectedColumns = array_values(array_map('intval', (array) ($params['selected_columns'] ?? [])));
        if (!empty($selectedColumns)) {
            return $selectedColumns;
        }

        $allHeaderIndexes = [];
        foreach ($normalizedHeaders as $index => $header) {
            if (trim((string) $header) === '') {
                continue;
            }

            $allHeaderIndexes[] = (int) $index;
        }

        return $allHeaderIndexes;
    }

    private function supportsDirectCsvBulkLoad(): bool
    {
        return app(MySqlBulkLoadService::class)->supportsNativeBulkLoad();
    }

    private function detectCsvDelimiter(string $path): string
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return ',';
        }

        try {
            $sampleLine = fgets($handle);
            if ($sampleLine === false) {
                return ',';
            }

            $sampleLine = preg_replace('/^\xEF\xBB\xBF/', '', $sampleLine);
            $delimiters = [',', ';', "\t", '|'];
            $bestDelimiter = ',';
            $bestCount = -1;

            foreach ($delimiters as $delimiter) {
                $count = count(str_getcsv($sampleLine, $delimiter));
                if ($count > $bestCount) {
                    $bestCount = $count;
                    $bestDelimiter = $delimiter;
                }
            }

            return $bestDelimiter;
        } finally {
            fclose($handle);
        }
    }

    private function readCsvRecord($handle, string $delimiter = ',')
    {
        $row = fgetcsv($handle, 0, $delimiter);
        if ($row === false) {
            return false;
        }

        if (!empty($row)) {
            $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) ($row[0] ?? ''));
        }

        return $row;
    }

    private function normalizeHeaderName(string $header): string
    {
        $normalized = preg_replace('/[^A-Z0-9]+/', '_', strtoupper(trim($header)));
        $normalized = trim((string) $normalized, '_');

        $aliases = [
            'NOREKENING' => 'no_rekening',
            'NOMORREKENING' => 'no_rekening',
            'NOMOR_REKENING' => 'no_rekening',
            'NO_REKENING' => 'no_rekening',
            'CIF_NO' => 'cifno',
            'CIF_NUMBER' => 'cifno',
            'STATUS_REKENING' => 'status',
            'STATUSREKENING' => 'status',
            'STATUS_REK' => 'status',
            'STATUSREK' => 'status',
            'STATUS_SIMPANAN' => 'status',
            'STATUSSIMPANAN' => 'status',
            'STATUS_DORMANT' => 'status',
            'STATUSDORMANT' => 'status',
        ];

        if (isset($aliases[$normalized])) {
            return $aliases[$normalized];
        }

        return strtolower($normalized);
    }

    private function isRowNumberHeader(string $header): bool
    {
        return in_array($this->normalizeHeaderName($header), [
            'no',
            'row_num',
            'rownumber',
            'nomor_baris',
            'urutan',
        ], true);
    }

    private function hasMalformedRowsForDirectLoad(string $absolutePath, string $delimiter, array $sourceHeaders, int $maxDataRowsToScan = 0): bool
    {
        $handle = fopen($absolutePath, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Gagal membuka file CSV untuk validasi fast import.');
        }

        try {
            $headerSkipped = false;
            $scannedRows = 0;

            while (($row = $this->readCsvRecord($handle, $delimiter)) !== false) {
                if (!$headerSkipped) {
                    $headerSkipped = true;
                    continue;
                }

                if (empty(array_filter((array) $row, fn ($value) => trim((string) $value) !== ''))) {
                    continue;
                }

                if (count($row) !== count($sourceHeaders)) {
                    return true;
                }

                if (!$this->isCompleteSimpananMultiPnSourceRow($sourceHeaders, (array) $row, 'simpanan_multipn')) {
                    return true;
                }

                $scannedRows++;
                if ($maxDataRowsToScan > 0 && $scannedRows >= $maxDataRowsToScan) {
                    break;
                }
            }
        } finally {
            fclose($handle);
        }

        return false;
    }

    private function buildDirectCsvLoadPlan(
        string $absolutePath,
        array $normalizedHeaders,
        array $selectedColumns,
        ?callable $send = null,
        array $sourceMeta = [],
        string $contentHash = ''
    ): array
    {
        $assumeCleanSource = (bool) ($sourceMeta['assume_clean_source'] ?? false);
        $preparedSource = isset($sourceMeta['prepared_source']) && is_array($sourceMeta['prepared_source'])
            ? (array) $sourceMeta['prepared_source']
            : null;
        $delimiter = trim((string) ($sourceMeta['delimiter'] ?? ''));
        $sourcePath = $absolutePath;
        $cleanupPath = null;
        $sourceRows = 0;
        $sourceHeaders = [];
        $sourceBalanceTotalCents = array_key_exists('source_balance_total_cents', $sourceMeta)
            ? (int) $sourceMeta['source_balance_total_cents']
            : null;
        $validationBackend = trim((string) ($sourceMeta['backend'] ?? ''));
        $validationSkippedCount = (int) ($sourceMeta['skipped_count'] ?? 0);
        $validationDuplicateCount = (int) ($sourceMeta['duplicate_count'] ?? 0);
        $periodHints = array_values(array_unique(array_filter(array_map(
            static fn ($value): string => trim((string) $value),
            (array) ($sourceMeta['period_hints'] ?? [])
        ), static fn (string $value): bool => $value !== '')));

        if ($preparedSource !== null) {
            $sourcePath = (string) ($preparedSource['path'] ?? $absolutePath);
            $preparedNormalized = (bool) ($preparedSource['normalized'] ?? false);
            if ($delimiter === '' || ($preparedNormalized && $sourcePath !== '' && $sourcePath !== $absolutePath)) {
                $delimiter = $this->detectCsvDelimiter($sourcePath !== '' ? $sourcePath : $absolutePath);
            }

            $cleanupPath = !empty($preparedSource['cleanup']) ? $sourcePath : null;
            $sourceRows = max(0, (int) ($preparedSource['written_rows'] ?? $preparedSource['total_rows'] ?? 0));
            $validationBackend = trim((string) ($preparedSource['backend'] ?? ''));
            $validationSkippedCount = (int) ($preparedSource['skipped_count'] ?? 0);
            $validationDuplicateCount = (int) ($preparedSource['duplicate_count'] ?? 0);
            $sourceHeaders = array_values(array_filter(array_map(
                static fn ($value): string => trim((string) $value),
                (array) ($preparedSource['headers'] ?? [])
            ), static fn (string $value): bool => $value !== ''));
            $periodHints = array_values(array_unique(array_filter(array_map(
                static fn ($value): string => trim((string) $value),
                (array) ($preparedSource['period_hints'] ?? [])
            ), static fn (string $value): bool => $value !== '')));

            if ($sourceBalanceTotalCents === null && array_key_exists('balance_total_cents', $preparedSource)) {
                $sourceBalanceTotalCents = $preparedSource['balance_total_cents'] === null
                    ? null
                    : (int) $preparedSource['balance_total_cents'];
            }
        } elseif ($assumeCleanSource) {
            if ($delimiter === '') {
                $delimiter = $this->detectCsvDelimiter($absolutePath);
            }

            $sourceHeaders = array_values(array_filter(array_map(
                static fn ($value): string => trim((string) $value),
                (array) ($sourceMeta['source_headers'] ?? [])
            ), static fn (string $value): bool => $value !== ''));

            if ($sourceHeaders === []) {
                $sourceHeaders = array_values(array_filter(array_map(
                    static fn ($value): string => trim((string) $value),
                    $normalizedHeaders
                ), static fn (string $value): bool => $value !== ''));
            }

            if ($sourceHeaders === []) {
                throw new \RuntimeException('Header CSV Simpanan MultiPN tidak ditemukan.');
            }

            $sourceRows = max(0, (int) ($sourceMeta['written_rows'] ?? $sourceMeta['total_rows'] ?? 0));

            $sampleLimit = max(1, min(
                50,
                (int) config('import.direct_load.validation_sample_rows', 5000)
            ));
            $this->validateSimpananMultiPnCleanSourceSample($sourcePath, $delimiter, $sourceHeaders, $sampleLimit);

            if ($sourceBalanceTotalCents === null && array_key_exists('balance_total_cents', $sourceMeta)) {
                $sourceBalanceTotalCents = (int) $sourceMeta['balance_total_cents'];
            }
        } else {
            $delimiter = $this->detectCsvDelimiter($absolutePath);
            $loadSource = $this->prepareSimpananMultiPnDirectLoadSource($absolutePath, $delimiter, $send);
            $sourcePath = (string) ($loadSource['path'] ?? $absolutePath);
            $cleanupPath = !empty($loadSource['cleanup']) ? $sourcePath : null;
            $sourceRows = (int) ($loadSource['written_rows'] ?? 0);
            $validationBackend = trim((string) ($loadSource['backend'] ?? 'php'));
            $validationSkippedCount = (int) ($loadSource['skipped_count'] ?? 0);
            $validationDuplicateCount = (int) ($loadSource['duplicate_count'] ?? 0);
            $sourceHeaders = array_values(array_filter(array_map(
                static fn ($value): string => trim((string) $value),
                (array) ($loadSource['headers'] ?? [])
            ), static fn (string $value): bool => $value !== ''));
            $periodHints = array_values(array_unique(array_filter(array_map(
                static fn ($value): string => trim((string) $value),
                (array) ($loadSource['period_hints'] ?? [])
            ), static fn (string $value): bool => $value !== '')));
            if ($sourceBalanceTotalCents === null && array_key_exists('balance_total_cents', $loadSource)) {
                $sourceBalanceTotalCents = $loadSource['balance_total_cents'] === null
                    ? null
                    : (int) $loadSource['balance_total_cents'];
            }

            // Keep strict balance validation for small/medium imports, but avoid a second
            // full scan on very large files where Polars/post-import snapshot audit is safer.
            $balanceCrosscheckMaxRows = max(0, (int) config('import.direct_load.simpanan_multipn.balance_crosscheck_max_rows', 50000));
            if ($sourceBalanceTotalCents === null && $sourceRows > 0 && $sourceRows <= $balanceCrosscheckMaxRows) {
                $sourceBalanceTotalCents = $this->calculateSimpananMultiPnSourceBalanceTotal($sourcePath, $delimiter);
            } elseif ($sourceBalanceTotalCents === null && $sourceRows > $balanceCrosscheckMaxRows) {
                Log::debug('Simpanan MultiPN balance crosscheck skipped to avoid large-file double scan', [
                    'source_rows' => $sourceRows,
                    'max_rows' => $balanceCrosscheckMaxRows,
                    'backend' => $validationBackend,
                ]);
            }

            if ($sourceHeaders === []) {
                $handle = fopen($sourcePath, 'r');
                if ($handle === false) {
                    throw new \RuntimeException('Gagal membuka file CSV.');
                }

                try {
                    $sourceHeaders = $this->readCsvRecord($handle, $delimiter);
                } finally {
                    fclose($handle);
                }

                if ($sourceHeaders === false || empty($sourceHeaders)) {
                    throw new \RuntimeException('Header CSV Simpanan MultiPN tidak ditemukan.');
                }
            }
        }

        $selectedLookup = [];
        foreach ($selectedColumns as $index) {
            $header = $normalizedHeaders[$index] ?? null;
            if (!$header) {
                continue;
            }
            $selectedLookup[$this->normalizeHeaderName((string) $header)] = true;
        }

        if (empty($selectedLookup)) {
            throw new \RuntimeException('Tidak ada kolom terpilih untuk import.');
        }

        $tableColumns = Schema::getColumnListing('simpanan_multipn');
        $tableColumnsByLower = [];
        foreach ($tableColumns as $column) {
            $tableColumnsByLower[strtolower($column)] = $column;
        }

        $uniqueIdColumn = null;
        foreach (['uniqueid_SMPN', 'uniqueid_SimoPN'] as $candidate) {
            $lowerCandidate = strtolower($candidate);
            if (isset($tableColumnsByLower[$lowerCandidate])) {
                $uniqueIdColumn = $tableColumnsByLower[$lowerCandidate];
                break;
            }
        }

        $importBatchTimestamp = now()->format('Y-m-d H:i:s');
        $importBatchToken = str_replace('-', '', Str::uuid()->toString());
        $fieldVariables = [];
        $setClauses = [
            "`created_at` = '{$importBatchTimestamp}'",
            "`updated_at` = '{$importBatchTimestamp}'",
        ];

        if ($uniqueIdColumn !== null) {
            $setClauses[] = "`{$uniqueIdColumn}` = CONCAT('SMPN_{$importBatchToken}_', REPLACE(UUID(), '-', ''), '_SMPN')";
        }

        foreach ($sourceHeaders as $index => $header) {
            $header = trim((string) $header);
            $normalized = $this->normalizeHeaderName($header);
            $variable = '@csv_col_' . $index;
            $fieldVariables[] = $variable;

            if ($header === '' || $this->isRowNumberHeader($header) || !isset($selectedLookup[$normalized])) {
                continue;
            }

            $dbColumn = $tableColumnsByLower[$normalized] ?? null;
            if (
                $dbColumn === null
                || in_array($dbColumn, ['id', 'created_at', 'updated_at'], true)
                || ($uniqueIdColumn !== null && strcasecmp($dbColumn, $uniqueIdColumn) === 0)
            ) {
                continue;
            }

            $setClauses[] = match (strtolower($dbColumn)) {
                'saldo_idr' => "`{$dbColumn}` = " . $this->buildDirectLoadDecimalExpression($variable),
                'posisi' => "`{$dbColumn}` = " . StrictDateParser::buildMySqlCaseExpression("TRIM({$variable})"),
                default => "`{$dbColumn}` = NULLIF(TRIM({$variable}), '')",
            };
        }

        if (count($setClauses) <= ($uniqueIdColumn !== null ? 3 : 2)) {
            throw new \RuntimeException('Tidak ada mapping kolom yang bisa dipakai untuk fast import.');
        }

        $plan = [
            'delimiter' => $delimiter,
            'field_variables' => $fieldVariables,
            'set_clauses' => $setClauses,
            'import_batch_timestamp' => $importBatchTimestamp,
            'source_path' => $sourcePath,
            'cleanup_path' => $cleanupPath,
            'validation_backend' => $validationBackend !== '' ? $validationBackend : ($assumeCleanSource ? 'polars' : 'php'),
            'validation_skipped_count' => $validationSkippedCount,
            'validation_duplicate_count' => $validationDuplicateCount,
            'validation_written_rows' => $sourceRows,
            'import_batch_token' => $importBatchToken,
            'unique_id_column' => $uniqueIdColumn,
            'period_hints' => $periodHints !== []
                ? $periodHints
                : ($assumeCleanSource ? [] : $this->collectSimpananMultiPnSnapshotPeriods($sourcePath)),
            'source_headers' => $sourceHeaders,
            'source_rows' => $sourceRows,
        ];

        if ($sourceBalanceTotalCents !== null) {
            $plan['source_balance_total_cents'] = $sourceBalanceTotalCents;
        }

        if ($contentHash !== '') {
            $plan['content_hash'] = $contentHash;
        }

        return $plan;
    }

    private function validateSimpananMultiPnCleanSourceSample(
        string $csvPath,
        string $delimiter,
        array $headers,
        int $sampleLimit = 25
    ): void {
        $handle = @fopen($csvPath, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('Gagal membuka staged CSV Simpanan MultiPN untuk validasi cepat.');
        }

        try {
            $fileHeaders = $this->readCsvRecord($handle, $delimiter);
            if ($fileHeaders === false || empty($fileHeaders)) {
                throw new \RuntimeException('Header staged CSV Simpanan MultiPN tidak ditemukan.');
            }

            if (isset($fileHeaders[0]) && is_string($fileHeaders[0])) {
                $fileHeaders[0] = preg_replace('/^\xEF\xBB\xBF/', '', $fileHeaders[0]);
            }

            $normalizedHeaders = array_values(array_filter(array_map(
                static fn ($value): string => trim((string) $value),
                $headers
            ), static fn (string $value): bool => $value !== ''));

            if ($normalizedHeaders === []) {
                throw new \RuntimeException('Header staged CSV Simpanan MultiPN tidak ditemukan.');
            }

            $scannedRows = 0;
            while ($scannedRows < $sampleLimit && ($row = $this->readCsvRecord($handle, $delimiter)) !== false) {
                $scannedRows++;
                $row = (array) $row;
                if (count($row) === count($normalizedHeaders) + 1) {
                    $firstValue = trim((string) ($row[0] ?? ''));
                    if ($firstValue !== '' && preg_match('/^\d+$/', $firstValue) === 1) {
                        $row = array_slice($row, 1);
                    }
                }

                if (!$this->isCompleteSimpananMultiPnSourceRow($normalizedHeaders, $row, 'simpanan_multipn')) {
                    throw new \RuntimeException('CSV staging Simpanan MultiPN menghasilkan baris tidak valid pada sampel awal. Import dibatalkan agar data null tidak masuk ke database.');
                }
            }

            if ($scannedRows === 0) {
                throw new \RuntimeException('CSV staging Simpanan MultiPN tidak memiliki baris data.');
            }
        } finally {
            fclose($handle);
        }
    }

    private function executeDirectCsvLoad(
        array $loadPlan,
        ?callable $beforeLoad = null,
        ?callable $send = null,
        int $jobId = 0
    ): int
    {
        $absolutePath = (string) ($loadPlan['source_path'] ?? '');
        if ($absolutePath === '') {
            throw new \RuntimeException('Path source direct load Simpanan MultiPN tidak ditemukan.');
        }

        $this->configureLongRunningImportRuntime();
        $bulkLoadService = app(MySqlBulkLoadService::class);
        $bulkLoadService->assertTransactionalTable('simpanan_multipn', 'import CSV Simpanan MultiPN');

        return $bulkLoadService->withTableWriteLock('simpanan_multipn', function () use ($absolutePath, $loadPlan, $beforeLoad, $send, $jobId): int {
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

            $pdo = new \PDO($dsn, $username, $password, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::MYSQL_ATTR_LOCAL_INFILE => true,
                \PDO::ATTR_TIMEOUT => 120,
            ]);

            $normalizedPath = str_replace('\\', '/', realpath($absolutePath) ?: $absolutePath);
            $quotedPath = $pdo->quote($normalizedPath);
            $quotedFields = implode(', ', $loadPlan['field_variables']);
            $quotedDelimiter = addslashes($loadPlan['delimiter']);
            $setClause = implode(",\n", $loadPlan['set_clauses']);
            $sql = "LOAD DATA LOCAL INFILE {$quotedPath} INTO TABLE `simpanan_multipn` "
                . "CHARACTER SET utf8mb4 "
                . "FIELDS TERMINATED BY '{$quotedDelimiter}' OPTIONALLY ENCLOSED BY '\"' "
                . "LINES TERMINATED BY '\\n' "
                . "IGNORE 1 LINES "
                . "({$quotedFields}) "
                . "SET {$setClause}";

            try {
                $pdo->beginTransaction();

                // Capture MySQL connection ID for termination support
                $mysqlThreadId = null;
                try {
                    $mysqlThreadId = (int) $pdo->query('SELECT CONNECTION_ID()')->fetchColumn();
                    if ($mysqlThreadId > 0 && $jobId > 0) {
                        $this->storeJobMetadataThreadId($jobId, $mysqlThreadId, (array) ($loadPlan['content_hash'] ?? ''));
                        Log::debug('Simpanan MultiPN: MySQL thread ID captured', [
                            'job_id' => $jobId,
                            'mysql_thread_id' => $mysqlThreadId,
                        ]);
                    }
                } catch (\Throwable $e) {
                    Log::warning('Failed to capture MySQL thread ID: ' . $e->getMessage(), [
                        'job_id' => $jobId,
                    ]);
                }

                if ($send) {
                    $send('progress', [
                        'percent' => 50,
                        'message' => 'Menjalankan LOAD DATA LOCAL INFILE ke tabel simpanan_multipn...',
                        'mode' => 'raw'
                    ]);
                }

                Log::info("Import Simpanan MultiPN: Memulai eksekusi LOAD DATA LOCAL INFILE.", [
                    'job_id' => $jobId,
                    'batch_token' => $loadPlan['import_batch_token'] ?? 'N/A',
                    'file' => basename($absolutePath)
                ]);

                $affected = $this->executeLoadDataWithSnapshotInvalidationBypassed($pdo, $sql, $beforeLoad);

                Log::info("Import Simpanan MultiPN: LOAD DATA LOCAL INFILE selesai.", [
                    'affected_rows' => $affected
                ]);

                if (array_key_exists('source_balance_total_cents', $loadPlan)) {
                    $expectedBalanceCents = (int) $loadPlan['source_balance_total_cents'];
                    $batchToken = trim((string) ($loadPlan['import_batch_token'] ?? ''));
                    if ($batchToken === '') {
                        throw new \RuntimeException('Token validasi batch import Simpanan MultiPN tidak ditemukan.');
                    }

                    $uniqueIdColumn = trim((string) ($loadPlan['unique_id_column'] ?? ''));
                    $summaryWhereClause = '';

                    if ($uniqueIdColumn !== '') {
                        $batchPrefix = 'SMPN_' . $batchToken . '_';
                        $quotedPrefix = $pdo->quote($batchPrefix . '%');
                        $summaryWhereClause = "WHERE `{$uniqueIdColumn}` LIKE {$quotedPrefix}";
                    } else {
                        $importBatchTimestamp = trim((string) ($loadPlan['import_batch_timestamp'] ?? ''));
                        if ($importBatchTimestamp === '') {
                            throw new \RuntimeException('Marker validasi batch import Simpanan MultiPN tidak ditemukan.');
                        }

                        // WARNING: This query is extremely slow on large tables (14M+ rows) without an index on created_at
                        Log::warning("Import Simpanan MultiPN: Unique ID column tidak ditemukan. Menggunakan created_at fallback yang lambat.", [
                            'batch_token' => $batchToken,
                            'timestamp' => $importBatchTimestamp
                        ]);

                        $quotedTimestamp = $pdo->quote($importBatchTimestamp);
                        $summaryWhereClause = "WHERE `created_at` = {$quotedTimestamp}";
                    }

                    $summarySql = "SELECT COUNT(*) AS row_count, COALESCE(SUM(COALESCE(`saldo_idr`, 0)), 0) AS total_balance "
                        . "FROM `simpanan_multipn` "
                        . $summaryWhereClause;
                    $summary = $pdo->query($summarySql);
                    if ($summary === false) {
                        throw new \RuntimeException('Gagal melakukan crosscheck hasil import Simpanan MultiPN.');
                    }

                    $summaryRow = $summary->fetch(\PDO::FETCH_ASSOC) ?: [];
                    $importBalanceCents = $this->decimalStringToCents((string) ($summaryRow['total_balance'] ?? '0.00'));
                    $importedRows = (int) ($summaryRow['row_count'] ?? 0);
                    $expectedRows = (int) ($loadPlan['validation_written_rows'] ?? 0);

                    if ($importedRows !== $affected || $importedRows !== $expectedRows) {
                        throw new \RuntimeException(sprintf(
                            'Crosscheck jumlah baris Simpanan MultiPN gagal. CSV bersih=%d, LOAD DATA=%d, query batch=%d.',
                            $expectedRows,
                            $affected,
                            $importedRows
                        ));
                    }

                    if ($importBalanceCents !== $expectedBalanceCents) {
                        throw new \RuntimeException(sprintf(
                            'Crosscheck saldo Simpanan MultiPN gagal. CSV bersih=%s, database=%s.',
                            $this->formatCentsAsDecimal($expectedBalanceCents),
                            $this->formatCentsAsDecimal($importBalanceCents)
                        ));
                    }
                }

                $pdo->commit();
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) {
                    try {
                        $pdo->rollBack();
                    } catch (\Throwable) {
                    }
                }

                throw $e;
            } finally {
                $pdo = null;
            }

            return $affected;
        });
    }

    private function executeLoadDataWithSnapshotInvalidationBypassed(
        \PDO $pdo,
        string $sql,
        ?callable $beforeLoad = null
    ): int
    {
        try {
            $pdo->exec('SET @skip_snapshot_invalidation = 1');

            if ($beforeLoad !== null) {
                $beforeLoad($pdo);
            }

            // Execute LOAD DATA
            $affected = $pdo->exec($sql);

            if ($affected === false) {
                throw new \RuntimeException('LOAD DATA LOCAL INFILE gagal dieksekusi untuk Simpanan MultiPN.');
            }

            return (int) $affected;
        } finally {
            try {
                $pdo->exec('SET @skip_snapshot_invalidation = NULL');
            } catch (\Throwable) {
                // abaikan reset session variable bila koneksi sudah gagal
            }
        }
    }

    private function buildSimpananMultiPnDirectLoadBeforeLoadCallback(array $loadPlan): ?callable
    {
        // DISABLED: Automatic delete is disabled per user request to allow 'Append' mode.
        return null;
    }

    private function deleteExistingSimpananMultiPnPeriods(\PDO $pdo, array $periodHints): void
    {
        $periodHints = array_values(array_unique(array_filter(array_map(
            static fn ($value): string => trim((string) $value),
            $periodHints
        ), static fn (string $value): bool => $value !== '')));

        if ($periodHints === []) {
            return;
        }

        $lockWaitSeconds = max(1, (int) config('import.direct_load.snapshot_delete_lock_wait_seconds', 8));
        $originalLockWait = null;

        try {
            $originalLockWait = $pdo->query('SELECT @@SESSION.lock_wait_timeout')->fetchColumn();
        } catch (\Throwable) {
            $originalLockWait = null;
        }

        try {
            $pdo->exec('SET SESSION lock_wait_timeout = ' . $lockWaitSeconds);

            $placeholders = implode(', ', array_fill(0, count($periodHints), '?'));
            $statement = $pdo->prepare("DELETE FROM `simpanan_multipn` WHERE `posisi` IN ({$placeholders})");
            if ($statement === false) {
                throw new \RuntimeException('Gagal menyiapkan delete scope Simpanan MultiPN sebelum direct load.');
            }

            $statement->execute($periodHints);

            Log::info('Import Simpanan MultiPN: Existing periods deleted before direct load.', [
                'period_hints' => $periodHints,
                'deleted_rows' => $statement->rowCount(),
            ]);
        } finally {
            if ($originalLockWait !== null && $originalLockWait !== false) {
                try {
                    $pdo->exec('SET SESSION lock_wait_timeout = ' . max(1, (int) $originalLockWait));
                } catch (\Throwable) {
                    // Ignore restore failures; the import transaction can still proceed safely.
                }
            }
        }
    }

    private function calculateSimpananMultiPnSourceBalanceTotal(string $csvPath, string $delimiter): int
    {
        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Gagal membuka file CSV Simpanan MultiPN untuk menghitung total saldo.');
        }

        try {
            $headers = $this->readCsvRecord($handle, $delimiter);
            if ($headers === false || empty($headers)) {
                throw new \RuntimeException('Header CSV Simpanan MultiPN tidak ditemukan saat menghitung total saldo.');
            }

            $saldoIndex = null;
            foreach ($headers as $index => $header) {
                if ($this->normalizeHeaderName((string) $header) === 'saldo_idr') {
                    $saldoIndex = $index;
                    break;
                }
            }

            if ($saldoIndex === null) {
                throw new \RuntimeException('Kolom saldo_idr tidak ditemukan pada CSV Simpanan MultiPN.');
            }

            $totalCents = 0;
            while (($row = $this->readCsvRecord($handle, $delimiter)) !== false) {
                if (empty(array_filter((array) $row, fn ($value) => trim((string) $value) !== ''))) {
                    continue;
                }

                $saldo = $row[$saldoIndex] ?? null;
                $normalized = $this->normalizeDecimalValue($saldo);
                if ($normalized === null) {
                    throw new \RuntimeException('Gagal menormalisasi saldo_idr pada CSV Simpanan MultiPN.');
                }

                $totalCents += $this->decimalStringToCents($normalized);
            }

            return $totalCents;
        } finally {
            fclose($handle);
        }
    }

    private function decimalStringToCents(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }

        $negative = str_starts_with($value, '-');
        if ($negative) {
            $value = ltrim($value, '-');
        }

        $parts = explode('.', $value, 2);
        $whole = preg_replace('/\D+/', '', $parts[0] ?? '0');
        $fraction = preg_replace('/\D+/', '', $parts[1] ?? '');
        $fraction = substr(str_pad($fraction, 2, '0', STR_PAD_RIGHT), 0, 2);

        $cents = ((int) ($whole === '' ? '0' : $whole)) * 100 + (int) ($fraction === '' ? '0' : $fraction);

        return $negative ? -$cents : $cents;
    }

    private function formatCentsAsDecimal(int $cents): string
    {
        $negative = $cents < 0;
        $absolute = abs($cents);
        $whole = intdiv($absolute, 100);
        $fraction = str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);

        return ($negative ? '-' : '') . $whole . '.' . $fraction;
    }

    private function shouldUseStagedDirectLoadFallback(string $reason): bool
    {
        return false;
    }

    private function configureLongRunningImportRuntime(): void
    {
        @ini_set('memory_limit', '1024M');
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);
    }

    private function collectSimpananMultiPnSnapshotPeriods(string $absolutePath, int $maxRows = 0): array
    {
        if ($absolutePath === '' || !file_exists($absolutePath)) {
            return [];
        }

        if ($maxRows <= 0) {
            $periods = $this->collectCsvNormalizedValuesForHeaders($absolutePath, ['POSISI']);
        } else {
            $periods = $this->collectSimpananMultiPnSnapshotPeriodsFromSample($absolutePath, $maxRows);
        }

        $periods = array_values(array_unique(array_filter(array_map(
            static fn ($value) => trim((string) $value),
            $periods
        ), static fn (string $value): bool => $value !== '')));

        sort($periods);

        return $periods;
    }

    private function collectSimpananMultiPnSnapshotPeriodsFromSample(string $absolutePath, int $maxRows): array
    {
        $delimiter = $this->detectCsvDelimiter($absolutePath);
        $handle = @fopen($absolutePath, 'rb');
        if ($handle === false) {
            return [];
        }

        try {
            $headers = $this->readCsvRecord($handle, $delimiter);
            if ($headers === false || empty($headers)) {
                return [];
            }

            $posisiIndex = null;
            foreach ($headers as $index => $header) {
                if ($this->normalizeHeaderName((string) $header) === 'posisi') {
                    $posisiIndex = (int) $index;
                    break;
                }
            }

            if ($posisiIndex === null) {
                return [];
            }

            $periods = [];
            $scannedRows = 0;
            while ($scannedRows < $maxRows && ($row = $this->readCsvRecord($handle, $delimiter)) !== false) {
                if (empty(array_filter((array) $row, static fn ($value): bool => trim((string) $value) !== ''))) {
                    continue;
                }

                $scannedRows++;
                $value = trim((string) ($row[$posisiIndex] ?? ''));
                if ($value === '') {
                    continue;
                }

                $normalized = StrictDateParser::normalize($value) ?? $value;
                $periods[$normalized] = true;
            }

            return array_values(array_keys($periods));
        } finally {
            fclose($handle);
        }
    }

    private function collectImportedBatchPeriods(string $importBatchTimestamp): array
    {
        $normalizedTimestamp = trim($importBatchTimestamp);
        if ($normalizedTimestamp === '') {
            return [];
        }

        try {
            return DB::table('simpanan_multipn')
                ->where('created_at', $normalizedTimestamp)
                ->whereNotNull('posisi')
                ->distinct()
                ->orderBy('posisi')
                ->pluck('posisi')
                ->map(static fn ($value) => trim((string) $value))
                ->filter(static fn (string $value): bool => $value !== '')
                ->values()
                ->all();
        } catch (\Throwable $e) {
            Log::warning('Gagal membaca periode hasil batch import Simpanan MultiPN: ' . $e->getMessage(), [
                'created_at' => $normalizedTimestamp,
            ]);

            return [];
        }
    }

    private function cleanupSuccessfulImportArtifacts(
        int $jobId,
        string $relativePath,
        ?string $absolutePath = null,
        array $periodHints = [],
        ?string $importBatchTimestamp = null
    ): void
    {
        try {
            // Prefer period hints collected during import. Only rescan CSV when no hint is available.
            if ($periodHints === [] && $absolutePath !== null && file_exists($absolutePath)) {
                $periodHints = $this->collectSimpananMultiPnSnapshotPeriods($absolutePath);
            }

            if ($periodHints === [] && $importBatchTimestamp !== null && trim($importBatchTimestamp) !== '') {
                // Warning: This query is extremely slow on large tables (14M+ rows) without an index on created_at
                Log::info("Import Simpanan MultiPN: Melakukan fallback deteksi periode via database (slow path)...", [
                    'timestamp' => $importBatchTimestamp
                ]);
                $periodHints = $this->collectImportedBatchPeriods($importBatchTimestamp);
            }

            $cleanupService = app(ImportCleanupService::class);

            if (!empty($periodHints)) {
                foreach ($periodHints as $periodHint) {
                    $cleanupService->dispatchImportedJobSync($jobId, 'simpanan_multipn', $periodHint, static::class);
                }
            } else {
                $cleanupService->dispatchImportedJobSync($jobId, 'simpanan_multipn', null, static::class);
            }

            app(ImportCleanupController::class)->cleanupSuccessfulJobArtifacts(
                $jobId,
                array_values(array_filter([$relativePath, $absolutePath]))
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal menjalankan cleanup terpusat Simpanan MultiPN CSV: ' . $e->getMessage(), [
                'job_id' => $jobId,
                'relative_path' => $relativePath,
            ]);
        }
    }

    private function calculateFileFingerprint(string $absolutePath): string
    {
        if (!file_exists($absolutePath)) {
            throw new \RuntimeException('File tidak ditemukan saat menghitung fingerprint: ' . $absolutePath);
        }

        $fileName = basename($absolutePath);
        $fileSize = @filesize($absolutePath);

        if ($fileSize === false) {
            throw new \RuntimeException('Gagal membaca ukuran file untuk fingerprinting: ' . $absolutePath);
        }

        $handle = @fopen($absolutePath, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('Gagal membuka file untuk fingerprinting: ' . $absolutePath);
        }

        try {
            $sample = @fread($handle, 8192);
            if ($sample === false) {
                throw new \RuntimeException('Gagal membaca sampel file untuk fingerprinting: ' . $absolutePath);
            }

            return sha1($fileName . '|' . $fileSize . '|' . $sample);
        } finally {
            @fclose($handle);
        }
    }

    private function validateFileUniqueness(string $contentHash, array $periodHints): void
    {
        if (empty($contentHash)) {
            return;
        }

        $periodHints = array_values(array_unique(array_filter(array_map(
            static fn ($value): string => trim((string) $value),
            $periodHints
        ), static fn (string $value): bool => $value !== '')));

        try {
            // Safety check: Verify that job_content_hash column exists before querying
            if (!Schema::hasColumn('import_jobs', 'job_content_hash')) {
                Log::info('Simpanan MultiPN: job_content_hash column not available yet, skipping uniqueness check');
                return;
            }

            // OPTIMIZED: Query menggunakan virtual column index (O(log N))
            // Jika migration belum dijalankan, query akan tetap berfungsi (fallback tanpa index)
            $existingJob = DB::table('import_jobs')
                ->where('status', 'completed')
                ->where('id_report', self::REPORT_ID)
                ->where('job_content_hash', $contentHash)
                ->first(['job_context']);

            if (!$existingJob) {
                // Tidak ada job dengan hash yang sama, aman untuk import
                return;
            }

            // Ada job dengan hash yang sama - ini adalah suspicious
            Log::warning('Simpanan MultiPN: Identical file hash found in completed jobs', [
                'content_hash' => $contentHash,
                'new_periods' => $periodHints,
            ]);

            // Jika ada job dengan hash yang sama, cek periode overlap
            $jobContext = $existingJob->job_context;
            if (!is_string($jobContext) || trim($jobContext) === '') {
                // Safety check: Jika tidak ada context dari job lama (corrupted), tetap reject
                if (empty($periodHints)) {
                    throw new \RuntimeException(
                        'File identik telah diimpor sebelumnya (periode tidak terdeteksi pada file baru). '
                        . 'Verifikasi bahwa kolom POSISI pada CSV sudah benar.'
                    );
                }
                return;
            }

            $context = @json_decode($jobContext, true);
            if (!is_array($context)) {
                // Corrupted job context dari import lama
                if (empty($periodHints)) {
                    throw new \RuntimeException(
                        'File identik telah diimpor sebelumnya (periode tidak terdeteksi pada file baru). '
                        . 'Verifikasi bahwa kolom POSISI pada CSV sudah benar.'
                    );
                }
                return;
            }

            $storedPeriods = (array) ($context['period_hints'] ?? []);
            $storedPeriods = array_values(array_unique(array_filter(array_map(
                static fn ($value): string => trim((string) $value),
                $storedPeriods
            ), static fn (string $value): bool => $value !== '')));

            // SAFEGUARD: Jika file baru tidak punya periode terdeteksi, tapi ada file lama dengan periode
            // Ini mungkin indikasi kolom POSISI berubah atau CSV format salah
            if (empty($periodHints) && !empty($storedPeriods)) {
                throw new \RuntimeException(
                    'File identik telah diimpor sebelumnya untuk periode: ' . implode(', ', array_slice($storedPeriods, 0, 3)) . '. '
                    . 'Periode pada file baru tidak terdeteksi - verifikasi kolom POSISI pada CSV Anda.'
                );
            }

            if (empty($storedPeriods)) {
                return;
            }

            // Cek overlap periode
            $hasOverlapPeriod = !empty(array_intersect($periodHints, $storedPeriods));

            if ($hasOverlapPeriod) {
                throw new \RuntimeException(
                    'File sudah pernah di-import sebelumnya untuk periode yang sama: ' . implode(', ', array_intersect($periodHints, $storedPeriods)) . '. '
                    . 'Gunakan file berbeda atau ubah periode upload.'
                );
            }
        } catch (\Throwable $e) {
            Log::warning('Validasi keunikan file Simpanan MultiPN gagal (continuing without check): ' . $e->getMessage(), [
                'content_hash' => $contentHash,
            ]);
        }
    }

    private function storeJobMetadataThreadId(int $jobId, int $mysqlThreadId, array $loadPlanMeta = []): void
    {
        if ($jobId <= 0 || $mysqlThreadId <= 0) {
            return;
        }

        try {
            $job = DB::table('import_jobs')->where('id', $jobId)->first(['job_context']);
            if (!$job) {
                return;
            }

            $jobContext = $job->job_context;
            $context = is_string($jobContext) && trim($jobContext) !== ''
                ? @json_decode($jobContext, true)
                : [];

            if (!is_array($context)) {
                $context = [];
            }

            $context['mysql_thread_id'] = $mysqlThreadId;

            if (!empty($loadPlanMeta) && isset($loadPlanMeta['content_hash'])) {
                $context['content_hash'] = $loadPlanMeta['content_hash'];
            }

            $encoded = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($encoded === false) {
                Log::warning('Failed to encode job context for thread ID storage', [
                    'job_id' => $jobId,
                    'mysql_thread_id' => $mysqlThreadId,
                ]);
                return;
            }

            DB::table('import_jobs')
                ->where('id', $jobId)
                ->update(['job_context' => $encoded]);
        } catch (\Throwable $e) {
            Log::warning('Failed to store job metadata thread ID: ' . $e->getMessage(), [
                'job_id' => $jobId,
                'mysql_thread_id' => $mysqlThreadId,
            ]);
        }
    }
}
