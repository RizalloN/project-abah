<?php

namespace App\Http\Controllers\Import;

use App\Support\ReportDataSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class ImportSimpananMultiPnCsvController extends ImportExcelController
{
    private const REPORT_ID = 9;

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
                ];

                $useCacheKey = $cacheKey ?: ('excel_preview_' . md5(urldecode($sessionPath) . '|simpanan_csv_preview|' . microtime(true)));
                Cache::put($useCacheKey, $cachePayload, now()->addMinutes(10));

                $send('progress', ['percent' => 95, 'message' => 'Finalisasi preview CSV Simpanan MultiPN...', 'step' => 5]);
                $send('ready', [
                    'redirect' => route('import.simpanan.csv.preview', ['ck' => $useCacheKey]),
                ]);
            } catch (\Throwable $e) {
                Log::error('SIMPANAN MULTIPN CSV PREVIEW ERROR: ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());
                $send('error_msg', ['message' => 'Gagal menyiapkan preview CSV Simpanan MultiPN: ' . $e->getMessage()]);
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
        return parent::previewExcel($this->useSimpananReport($request));
    }

    public function initImport(Request $request)
    {
        return parent::initExcelImport($this->useSimpananReport($request));
    }

    public function processImportStream(Request $request)
    {
        $request = $this->useSimpananReport($request);

        $sessionParams = session('excel_import_params', []);
        $jobId = (int) ($sessionParams['job_id'] ?? $request->query('job_id', 0));
        $jobState = method_exists($this, 'getExcelImportJobState') ? $this->getExcelImportJobState($jobId) : [];
        $params = !empty($jobState['params']) ? (array) $jobState['params'] : $sessionParams;
        $normalizedHeaders = !empty($jobState['headers']) ? (array) $jobState['headers'] : session('excel_headers', []);
        $activeFilters = $params['active_filters'] ?? [];

        if (!empty($activeFilters) || empty($normalizedHeaders) || !$this->supportsDirectCsvBulkLoad()) {
            return parent::processExcelStream($request);
        }

        $relativePath = (string) ($params['file_path'] ?? '');
        $selectedColumns = $this->resolveSelectedColumns($params, $normalizedHeaders);
        $totalRows = (int) ($params['total_rows'] ?? 0);

        if ($relativePath === '') {
            return parent::processExcelStream($request);
        }

        $absolutePath = Storage::path($relativePath);
        if (!file_exists($absolutePath)) {
            return parent::processExcelStream($request);
        }

        try {
            $loadPlan = $this->buildDirectCsvLoadPlan($absolutePath, $normalizedHeaders, $selectedColumns);
        } catch (\Throwable $e) {
            Log::warning('Fast-path Simpanan MultiPN CSV unavailable, fallback to staged import: ' . $e->getMessage());
            return parent::processExcelStream($request);
        }

        request()->session()->save();

        return response()->stream(function () use ($jobId, $relativePath, $absolutePath, $totalRows, $loadPlan) {
            $streamLock = null;
            $send = function (string $event, array $data) {
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

                        if ($job && in_array($job->status, ['completed', 'failed', 'failed_partial'], true)) {
                            $send('complete', [
                                'total_success' => (int) ($job->total_success ?? 0),
                                'total_failed' => (int) ($job->total_failed ?? 0),
                                'total_rows' => (int) ($job->total_files ?? 0),
                            ]);
                        } else {
                            $send('error', [
                                'message' => 'Job import ini sudah sedang diproses pada koneksi lain.',
                            ]);
                        }
                        return;
                    }
                }

                if (!file_exists($absolutePath)) {
                    $send('error', ['message' => 'File CSV Simpanan MultiPN tidak ditemukan di server.']);
                    return;
                }

                $send('progress', [
                    'percent' => 8,
                    'message' => 'Menyiapkan direct LOAD DATA untuk Simpanan MultiPN...',
                    'rows_done' => 0,
                    'total' => $totalRows,
                    'speed' => 0,
                ]);

                $startTime = microtime(true);
                $inserted = $this->executeDirectCsvLoad($absolutePath, $loadPlan);
                $elapsed = max(microtime(true) - $startTime, 0.001);
                $speed = (int) ($inserted / $elapsed);
                $failed = max(0, $totalRows - $inserted);

                if ($jobId > 0) {
                    DB::table('import_jobs')->where('id', $jobId)->update([
                        'status' => $failed > 0 ? ($inserted > 0 ? 'failed_partial' : 'failed') : 'completed',
                        'total_files' => $totalRows > 0 ? $totalRows : $inserted,
                        'total_success' => $inserted,
                        'total_failed' => $failed,
                        'updated_at' => now(),
                    ]);
                }

                $this->cleanupSuccessfulImportArtifacts($jobId, $relativePath, $absolutePath);

                $send('progress', [
                    'percent' => 98,
                    'message' => "LOAD DATA selesai. Kecepatan rata-rata {$speed} baris/detik.",
                    'rows_done' => $inserted,
                    'total' => $totalRows > 0 ? $totalRows : $inserted,
                    'speed' => $speed,
                ]);

                $send('complete', [
                    'total_success' => $inserted,
                    'total_failed' => $failed,
                    'total_rows' => $totalRows > 0 ? $totalRows : $inserted,
                ]);
            } catch (\Throwable $e) {
                Log::error('SIMPANAN MULTIPN DIRECT CSV LOAD ERROR: ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());

                if ($jobId > 0) {
                    DB::table('import_jobs')->where('id', $jobId)->update([
                        'status' => 'failed',
                        'updated_at' => now(),
                    ]);
                }

                $send('error', [
                    'message' => 'Fast import CSV gagal: ' . $e->getMessage(),
                ]);
            } finally {
                if ($streamLock) {
                    try {
                        $streamLock->release();
                    } catch (\Throwable $e) {
                        Log::warning('Failed to release Simpanan MultiPN direct import lock for job ' . $jobId . ': ' . $e->getMessage());
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
        $driver = DB::connection()->getDriverName();
        if (!in_array($driver, ['mysql', 'mariadb'], true)) {
            return false;
        }

        try {
            $row = DB::selectOne("SHOW VARIABLES LIKE 'local_infile'");
            return strtoupper((string) ($row->Value ?? $row->value ?? 'OFF')) === 'ON';
        } catch (\Throwable $e) {
            Log::warning('Unable to verify local_infile support for Simpanan MultiPN CSV: ' . $e->getMessage());
            return false;
        }
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
        return strtolower(str_replace(' ', '_', trim($header)));
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

    private function hasMalformedRowsForDirectLoad(string $absolutePath, string $delimiter, array $sourceHeaders): bool
    {
        $handle = fopen($absolutePath, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Gagal membuka file CSV untuk validasi fast import.');
        }

        try {
            $headerSkipped = false;

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

                if (!$this->isCompleteSimpananMultiPnSourceRow($sourceHeaders, (array) $row)) {
                    return true;
                }
            }
        } finally {
            fclose($handle);
        }

        return false;
    }

    private function buildDirectCsvLoadPlan(string $absolutePath, array $normalizedHeaders, array $selectedColumns): array
    {
        $delimiter = $this->detectCsvDelimiter($absolutePath);
        $handle = fopen($absolutePath, 'r');
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

        if ($this->hasMalformedRowsForDirectLoad($absolutePath, $delimiter, $sourceHeaders)) {
            throw new \RuntimeException('CSV mengandung baris tidak lengkap, fast import dialihkan ke mode aman.');
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

        $fieldVariables = [];
        $setClauses = [
            "`created_at` = NOW()",
            "`updated_at` = NOW()",
        ];

        if ($uniqueIdColumn !== null) {
            $setClauses[] = "`{$uniqueIdColumn}` = CONCAT(REPLACE(UUID(), '-', ''), '_SMPN')";
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
                'saldo_idr' => "`{$dbColumn}` = NULLIF(REPLACE(REPLACE(TRIM({$variable}), ',', ''), ' ', ''), '')",
                'posisi' => "`{$dbColumn}` = CASE "
                    . "WHEN TRIM({$variable}) = '' THEN NULL "
                    . "WHEN STR_TO_DATE(TRIM({$variable}), '%Y-%m-%d') IS NOT NULL THEN STR_TO_DATE(TRIM({$variable}), '%Y-%m-%d') "
                    . "WHEN STR_TO_DATE(TRIM({$variable}), '%d/%m/%Y') IS NOT NULL THEN STR_TO_DATE(TRIM({$variable}), '%d/%m/%Y') "
                    . "WHEN STR_TO_DATE(TRIM({$variable}), '%m/%d/%Y') IS NOT NULL THEN STR_TO_DATE(TRIM({$variable}), '%m/%d/%Y') "
                    . "ELSE NULL END",
                default => "`{$dbColumn}` = NULLIF(TRIM({$variable}), '')",
            };
        }

        if (count($setClauses) <= ($uniqueIdColumn !== null ? 3 : 2)) {
            throw new \RuntimeException('Tidak ada mapping kolom yang bisa dipakai untuk fast import.');
        }

        return [
            'delimiter' => $delimiter,
            'field_variables' => $fieldVariables,
            'set_clauses' => $setClauses,
        ];
    }

    private function executeDirectCsvLoad(string $absolutePath, array $loadPlan): int
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

        $pdo = new \PDO($dsn, $username, $password, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::MYSQL_ATTR_LOCAL_INFILE => true,
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

        $affected = $pdo->exec($sql);
        $pdo = null;

        if ($affected === false) {
            throw new \RuntimeException('LOAD DATA LOCAL INFILE gagal dieksekusi untuk Simpanan MultiPN.');
        }

        return (int) $affected;
    }

    private function cleanupSuccessfulImportArtifacts(int $jobId, string $relativePath, ?string $absolutePath = null): void
    {
        try {
            app(ReportDataSyncService::class)->syncImportedTable('simpanan_multipn', jobId: $jobId, source: static::class);
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
}
