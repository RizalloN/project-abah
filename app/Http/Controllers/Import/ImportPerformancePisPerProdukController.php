<?php

namespace App\Http\Controllers\Import;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Import\Concerns\AllocatesGapIds;
use App\Services\Import\ExcelStagingService;
use App\Services\Import\MySqlBulkLoadService;
use App\Support\ReportDataSyncService;
use App\Support\StrictDateParser;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use XMLReader;
use ZipArchive;

class ImportPerformancePisPerProdukController extends Controller
{
    use AllocatesGapIds;

    private const TABLE_NAME = 'performance_pis_per_produk';
    private const UNIQUE_SUFFIX = '_PISPP';
    private const COLUMN_DELIMITER = ',';
    private const STREAM_PROGRESS_EVERY = 500;
    private const INSERT_BATCH_SIZE = 1000;
    private const BULK_LOAD_TEMP_DIR = 'app/import_bulk';
    private const SAMPLE_SCAN_ROWS = 20;
    private const PREVIEW_ROW_LIMIT = 200;
    private const PREVIEW_SCAN_LIMIT = 200;
    private const UNIQUE_VALUE_LIMIT = 5000;
    private const EXCEL_READ_CHUNK_SIZE = 1000;
    private const EXPECTED_HEADERS = [
        'no',
        'kode_kanwil',
        'kanwil',
        'kode_kanca',
        'kanca',
        'kode_uker',
        'uker',
        'corporate_code',
        'nama_perusahaan',
        'jenis_mitra',
        'jenis_perusahaan',
        'tipe_produk',
        'nomor_rekening',
        'nama_rekening',
        'saldo_britama_kerjasama',
        'tanggal_pembuatan_rekening',
        'pn_rm_dana_brinets',
        'pn_rm_dana_pis2',
        'nomor_hp',
        'email',
        'flag_briguna',
        'flag_cc',
    ];

    private function bulkLoadService(): MySqlBulkLoadService
    {
        return app(MySqlBulkLoadService::class);
    }

    private function stagingService(): ExcelStagingService
    {
        return app(ExcelStagingService::class);
    }

    private function stageCacheKey(string $relativePath): string
    {
        return 'performance_pis_excel_stage_v2_' . md5($relativePath);
    }

    private function getStagedExcelState(string $relativePath): array
    {
        $cached = Cache::get($this->stageCacheKey($relativePath));
        return is_array($cached) ? $cached : [];
    }

    private function putStagedExcelState(string $relativePath, array $payload): void
    {
        Cache::put($this->stageCacheKey($relativePath), $payload, now()->addHours(4));
    }

    private function resolveWorkingImportPath(string $relativePath): string
    {
        $absolutePath = Storage::path($relativePath);
        if (!$this->isExcelFile($absolutePath)) {
            return $absolutePath;
        }

        $stageState = $this->getStagedExcelState($relativePath);
        $stagedCsvPath = (string) ($stageState['staged_csv_path'] ?? '');

        return ($stagedCsvPath !== '' && file_exists($stagedCsvPath)) ? $stagedCsvPath : $absolutePath;
    }

    private function isExcelFile(string $path): bool
    {
        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['xlsx', 'xls'], true);
    }

    private function isCsvFile(string $path): bool
    {
        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['csv', 'txt'], true);
    }

    private const TARGET_COLUMNS = [
        'posisi',
        'kode_kanwil',
        'kanwil',
        'kode_kanca',
        'kanca',
        'kode_uker',
        'uker',
        'corporate_code',
        'nama_perusahaan',
        'jenis_mitra',
        'jenis_perusahaan',
        'tipe_produk',
        'nomor_rekening',
        'nama_rekening',
        'saldo_britama_kerjasama',
        'tanggal_pembuatan_rekening',
        'pn_rm_dana_brinets',
        'pn_rm_dana_pis2',
        'nomor_hp',
        'email',
        'flag_briguna',
        'flag_cc',
    ];

    public function upload(Request $request)
    {
        $request->validate([
            'id_report' => 'required',
            'file' => 'required|file|mimes:xlsx,csv,txt',
            'periode' => 'required|date_format:Y-m-d',
        ]);

        try {
            $file = $request->file('file');
            $path = $this->storePerformancePisUpload($file);

            session([
                'active_id_report' => $request->input('id_report'),
                'import_type' => 'performance_pis',
                'performance_pis_file' => $path,
                'performance_pis_periode' => $request->input('periode'),
            ]);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'status' => 'success',
                    'redirect' => route('import.performancepis.prepare-preview'),
                ]);
            }

            return redirect()->route('import.performancepis.preview');
        } catch (\Throwable $e) {
            Log::error('Performance PIS upload error: ' . $e->getMessage(), [
                'file_name' => $request->file('file')?->getClientOriginalName(),
            ]);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Upload Performance PIS gagal: ' . $e->getMessage(),
                ], 422);
            }

            return redirect()->route('import.index')->with('error', 'Upload Performance PIS gagal: ' . $e->getMessage());
        }
    }

    public function preparePreviewStream(Request $request)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        $relativePath = session('performance_pis_file');
        request()->session()->save();

        return response()->stream(function () use ($relativePath) {
            $send = function (string $event, array $data) {
                echo "event: {$event}\n";
                echo 'data: ' . json_encode($data) . "\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            };

            try {
                if (!$relativePath) {
                    $send('error_msg', ['message' => 'Sesi upload Performance PIS tidak ditemukan. Silakan upload ulang.']);
                    return;
                }

                $absolutePath = Storage::path($relativePath);
                if (!file_exists($absolutePath)) {
                    $send('error_msg', ['message' => 'File import Performance PIS tidak ditemukan di server.']);
                    return;
                }

                $workingPath = $absolutePath;
                $stageState = $this->getStagedExcelState($relativePath);
                $stagedCsvPath = (string) ($stageState['staged_csv_path'] ?? '');

                if ($stagedCsvPath !== '' && file_exists($stagedCsvPath)) {
                    $send('progress', ['percent' => 82, 'message' => 'CSV staging siap. Menyiapkan halaman preview...']);
                    $send('ready', [
                        'redirect' => route('import.performancepis.preview', ['file_path' => $relativePath]),
                    ]);
                    return;
                }

                if ($this->isExcelFile($absolutePath)) {
                    $send('progress', ['percent' => 18, 'message' => 'Mendeteksi header Excel Performance PIS...']);
                    $excelContext = $this->buildExcelContext($absolutePath, session('performance_pis_periode'));
                    $this->putStagedExcelState($relativePath, [
                        'total_rows' => max(0, (int) ($excelContext['total_rows'] ?? 0) - (int) ($excelContext['header_line'] ?? 0)),
                        'header_index' => max(0, (int) ($excelContext['header_line'] ?? 1) - 1),
                        'headers' => $this->compactSourceHeaders((array) ($excelContext['source_headers'] ?? [])),
                        'preview_sample_only' => true,
                    ]);
                    $send('progress', ['percent' => 82, 'message' => 'Struktur header valid. Preview akan memakai sampel data.']);
                } else {
                    $send('progress', ['percent' => 45, 'message' => 'Memvalidasi struktur CSV Performance PIS...']);
                    $context = $this->buildCsvContext($absolutePath, session('performance_pis_periode'));
                    $send('progress', ['percent' => 82, 'message' => 'Struktur CSV valid. Menyiapkan halaman preview...']);
                }

                $send('ready', [
                    'redirect' => route('import.performancepis.preview', ['file_path' => $relativePath]),
                ]);
            } catch (\Throwable $e) {
                Log::error('Performance PIS prepare preview error: ' . $e->getMessage(), [
                    'file' => $relativePath,
                ]);

                $send('error_msg', ['message' => 'Gagal menyiapkan preview Performance PIS: ' . $e->getMessage()]);
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
        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        $relativePath = session('performance_pis_file', $request->input('file_path'));
        $periodeInput = $request->input('periode', session('performance_pis_periode'));

        if (!$relativePath) {
            return redirect()->route('import.index')->with('error', 'File import tidak ditemukan. Silakan upload ulang.');
        }

        $absolutePath = Storage::path($relativePath);
        if (!file_exists($absolutePath)) {
            return redirect()->route('import.index')->with('error', 'File Excel tidak ditemukan di server.');
        }

        if (!$periodeInput) {
            return redirect()->route('import.index')->with('error', 'Periode Performance PIS tidak ditemukan. Silakan upload ulang.');
        }

        session(['performance_pis_periode' => $periodeInput]);

        try {
            $workingPath = $this->resolveWorkingImportPath($relativePath);
            $stageState = $this->getStagedExcelState($relativePath);
            $context = $this->buildImportContext($workingPath, $periodeInput, $stageState);
        } catch (\Throwable $e) {
            Log::error('Performance PIS preview context error: ' . $e->getMessage(), [
                'file' => $absolutePath,
            ]);
            return redirect()->route('import.index')->with('error', 'Struktur file Excel tidak dikenali: ' . $e->getMessage());
        }

        try {
            $previewData = [];

            $scannedRows = 0;
            $this->iterateDataRows($workingPath, $context, function (array $row) use (&$previewData, &$scannedRows) {
                $scannedRows++;

                if (count($previewData) < self::PREVIEW_ROW_LIMIT) {
                    $previewData[] = $row;
                }

                return $scannedRows < self::PREVIEW_SCAN_LIMIT;
            });
        } catch (\Throwable $e) {
            Log::error('Performance PIS preview data error: ' . $e->getMessage(), [
                'file' => $absolutePath,
            ]);

            return redirect()->route('import.index')->with('error', 'Preview Performance PIS gagal diproses: ' . $e->getMessage());
        }

        $formattedUniqueValues = [];
        $uniqueValues = $this->collectPreviewUniqueValuesFromRows($previewData, $context);
        foreach ($uniqueValues as $index => $valuesMap) {
            $keys = array_keys($valuesMap);
            usort($keys, 'strnatcmp');
            $formattedUniqueValues[$index] = $keys;
        }

        return view('import.preview', [
            'headers' => $context['headers'],
            'previewData' => $previewData,
            'filePath' => $relativePath,
            'formattedUniqueValues' => $formattedUniqueValues,
            'currentDelimiter' => 'excel',
            'processRoute' => route('import.performancepis.process'),
            'previewRoute' => route('import.performancepis.preview.refresh'),
            'initRoute' => route('import.performancepis.init'),
            'streamRoute' => route('import.performancepis.stream'),
            'backRoute' => route('import.index'),
            'disableArea6AutoFilter' => true,
            'detectedPosisi' => $context['posisi'] ?? $periodeInput,
            'manualPeriode' => $periodeInput,
            'manualPeriodeLabel' => Carbon::parse($periodeInput)->translatedFormat('d F Y'),
            'lockDelimiterSelector' => true,
            'fixedDelimiterLabel' => 'Sampling Excel/CSV Performance PIS',
        ]);
    }

    public function processImport(Request $request)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        $request->validate([
            'file_path' => 'required|string',
            'selected_columns' => 'required|array|min:1',
            'active_filters_json' => 'nullable|string',
            'delimiter' => 'required|string',
            'periode' => 'required|date_format:Y-m-d',
        ]);

        $relativePath = $request->input('file_path');
        $absolutePath = Storage::path($relativePath);
        if (!file_exists($absolutePath)) {
            return response()->json([
                'status' => 'error',
                'title' => 'Gagal!',
                'text' => 'File Excel tidak ditemukan di server.',
            ], 422);
        }

        try {
            $this->bulkLoadService()->assertTransactionalTable(self::TABLE_NAME, 'import Performance PIS');
        } catch (\RuntimeException $e) {
            return response()->json([
                'status' => 'error',
                'title' => 'Import Diblokir',
                'text' => $e->getMessage(),
            ], 422);
        }

        $selectedColumns = array_map('intval', $request->input('selected_columns', []));
        $activeFilters = json_decode($request->input('active_filters_json', '{}'), true) ?: [];

        try {
            $workingPath = $this->resolveWorkingImportPath($relativePath);
            $stageState = $this->getStagedExcelState($relativePath);
            $context = $this->buildImportContext($workingPath, $request->input('periode'), $stageState);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'title' => 'Gagal!',
                'text' => 'Struktur file Excel tidak dikenali: ' . $e->getMessage(),
            ], 422);
        }

        if (!empty($context['posisi']) && DB::table(self::TABLE_NAME)->whereDate('posisi', $context['posisi'])->exists()) {
            $this->cleanupUploadedFile($relativePath);

            return response()->json([
                'status' => 'warning',
                'title' => 'Data Ditolak (Duplikat)!',
                'text' => "Data untuk tanggal POSISI <b>{$context['posisi']}</b> sudah ada di tabel <b class='text-uppercase'>" . self::TABLE_NAME . '</b>.',
            ]);
        }

        $jobId = app(\App\Services\Import\ImportProgressService::class)->createJob([
            'id_report' => session('active_id_report'),
            'file_name' => basename($absolutePath),
            'folder_path' => dirname($absolutePath),
            'status' => 'processing',
            'total_files' => 0,
            'total_success' => 0,
            'total_failed' => 0,
            'created_by' => auth()->id() ?? 1,
            'job_context' => [
                'controller' => static::class,
                'mode' => 'performance_preview',
                'table_name' => 'performance_pis_per_produk',
                'file_hash' => sha1($absolutePath),
            ],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $rows = [];
        $totalRows = 0;
        $totalSuccess = 0;
        $totalFailed = 0;
        $lastErrorMsg = '';

        $this->iterateDataRows($workingPath, $context, function (array $row) use (&$rows, &$totalRows, &$totalSuccess, &$totalFailed, &$lastErrorMsg, $activeFilters, $selectedColumns, $context) {
            if (!$this->passesFilters($row, $activeFilters)) {
                return true;
            }

            $insertRow = $this->buildInsertRow($context['headers'], $row, $selectedColumns);
            if ($insertRow === null) {
                return true;
            }

            $rows[] = $insertRow;
            $totalRows++;

            if (count($rows) >= 500) {
                $this->insertBatch($rows, $totalSuccess, $totalFailed, $lastErrorMsg);
                $rows = [];
            }

            return true;
        });

        if (!empty($rows)) {
            $this->insertBatch($rows, $totalSuccess, $totalFailed, $lastErrorMsg);
        }

        $finalStatus = $totalFailed > 0 ? ($totalSuccess > 0 ? 'failed_partial' : 'failed') : 'completed';
        DB::table('import_jobs')->where('id', $jobId)->update([
            'status' => $finalStatus,
            'total_files' => $totalRows,
            'total_success' => $totalSuccess,
            'total_failed' => $totalFailed,
            'updated_at' => now(),
        ]);

        if ($totalSuccess > 0) {
            $extraCleanupPaths = [];
            $stageCsvPath = (string) ($stageState['staged_csv_path'] ?? '');
            if ($stageCsvPath !== '' && file_exists($stageCsvPath)) {
                $extraCleanupPaths[] = $stageCsvPath;
            }

            $this->cleanupSuccessfulImportArtifacts($jobId, $relativePath, $request->input('periode'), $extraCleanupPaths);
        }

        if ($totalFailed > 0) {
            return response()->json([
                'status' => 'warning',
                'title' => 'Import Memiliki Kendala!',
                'text' => "Berhasil: {$totalSuccess} baris.<br>Gagal: {$totalFailed} baris."
                    . ($lastErrorMsg !== '' ? "<br><br><b>Info MySQL:</b><br><small class='text-danger'>" . htmlspecialchars($lastErrorMsg, ENT_QUOTES) . '</small>' : ''),
            ]);
        }

        return response()->json([
            'status' => 'success',
            'title' => 'Berhasil!',
            'text' => "Sebanyak {$totalSuccess} baris data telah sukses masuk ke tabel <b class='text-uppercase'>" . self::TABLE_NAME . '</b>.',
        ]);
    }

    public function initImport(Request $request)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        $request->validate([
            'file_path' => 'required|string',
            'selected_columns' => 'required|array|min:1',
            'active_filters_json' => 'nullable|string',
            'delimiter' => 'required|string',
            'periode' => 'required|date_format:Y-m-d',
        ]);

        $relativePath = $request->input('file_path');
        $absolutePath = Storage::path($relativePath);
        if (!file_exists($absolutePath)) {
            return response()->json([
                'status' => 'error',
                'title' => 'Gagal!',
                'text' => 'File Excel tidak ditemukan di server.',
            ], 422);
        }

        $selectedColumns = array_map('intval', $request->input('selected_columns', []));
        $activeFilters = json_decode($request->input('active_filters_json', '{}'), true) ?: [];

        try {
            $workingPath = $this->resolveWorkingImportPath($relativePath);
            $stageState = $this->getStagedExcelState($relativePath);
            $context = $this->buildImportContext($workingPath, $request->input('periode'), $stageState);
            $totalRows = $this->estimateImportRowCount($context);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'title' => 'Gagal!',
                'text' => 'Struktur file Excel tidak dikenali: ' . $e->getMessage(),
            ], 422);
        }

        if (empty($context['posisi'])) {
            return response()->json([
                'status' => 'error',
                'title' => 'Gagal!',
                'text' => 'Tanggal posisi Performance PIS tidak berhasil dideteksi.',
            ], 422);
        }

        if (($context['total_rows'] ?? 0) > 0 && $totalRows === 0) {
            return response()->json([
                'status' => 'warning',
                'title' => 'Tidak Ada Data',
                'text' => 'Tidak ada baris yang lolos filter untuk diimport.',
            ], 422);
        }

        if (DB::table(self::TABLE_NAME)->whereDate('posisi', $context['posisi'])->exists()) {
            $this->cleanupUploadedFile($relativePath);

            return response()->json([
                'status' => 'warning',
                'title' => 'Data Ditolak (Duplikat)!',
                'text' => "Data untuk tanggal POSISI <b>{$context['posisi']}</b> sudah ada di tabel <b class='text-uppercase'>" . self::TABLE_NAME . '</b>.',
            ], 422);
        }

        $jobId = app(\App\Services\Import\ImportProgressService::class)->createJob([
            'id_report' => session('active_id_report'),
            'file_name' => basename($absolutePath),
            'folder_path' => dirname($absolutePath),
            'status' => 'processing',
            'total_files' => $totalRows,
            'total_success' => 0,
            'total_failed' => 0,
            'created_by' => auth()->id() ?? 1,
            'job_context' => [
                'controller' => static::class,
                'mode' => 'performance_import',
                'table_name' => 'performance_pis_per_produk',
                'file_hash' => sha1($absolutePath),
                'total_rows' => (int) $totalRows,
            ],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $importParams = [
            'job_id' => $jobId,
            'file_path' => $relativePath,
            'selected_columns' => $selectedColumns,
            'active_filters' => $activeFilters,
            'periode' => $request->input('periode'),
            'total_rows' => $totalRows,
        ];

        session(['performance_pis_import_params' => $importParams]);
        Cache::put('performance_pis_import_params_' . $jobId, $importParams, now()->addHours(4));

        return response()->json([
            'status' => 'success',
            'job_id' => $jobId,
            'total_rows' => $totalRows,
        ]);
    }

    public function processImportStream(Request $request)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);
        DB::disableQueryLog();

        $sessionParams = session('performance_pis_import_params', []);
        $jobId = (int) ($sessionParams['job_id'] ?? $request->query('job_id', 0));
        $params = Cache::get('performance_pis_import_params_' . $jobId, $sessionParams);

        request()->session()->save();

        return response()->stream(function () use ($params, $jobId) {
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
                    $streamLock = Cache::lock('import_performance_pis_stream_job_' . $jobId, 7200);

                    if (!$streamLock->get()) {
                        $job = DB::table('import_jobs')->where('id', $jobId)->first();

                        if ($job && in_array($job->status, ['completed', 'failed', 'failed_partial'], true)) {
                            $send('complete', [
                                'total_success' => (int) ($job->total_success ?? 0),
                                'total_failed' => (int) ($job->total_failed ?? 0),
                                'total_rows' => (int) ($job->total_files ?? 0),
                                'skipped_count' => 0,
                                'skipped_rows' => [],
                                'skip_reasons_summary' => [],
                            ]);
                        } else {
                            $send('error', ['message' => 'Job import Performance PIS ini sudah sedang diproses pada koneksi lain.']);
                        }
                        return;
                    }
                }

                $relativePath = $params['file_path'] ?? '';
                $absolutePath = Storage::path($relativePath);
                $selectedColumns = array_map('intval', $params['selected_columns'] ?? []);
                $activeFilters = $params['active_filters'] ?? [];
                $periode = $params['periode'] ?? null;
                $totalRows = (int) ($params['total_rows'] ?? 0);
                $stageState = $this->getStagedExcelState($relativePath);

                if ($relativePath === '' || !file_exists($absolutePath)) {
                    if ($jobId > 0) {
                        $progressService = app(\App\Services\Import\ImportProgressService::class);
                        $progressService->markFailed($jobId, 'File tidak ditemukan di server. Silakan upload ulang.', 0, 0, 'failed');
                        $progressService->cleanupQueuedImportJobRowsForJob($jobId);
                    }

                    $send('error', ['message' => 'File Excel Performance PIS tidak ditemukan di server.']);
                    return;
                }

                if ($jobId > 0) {
                    DB::table('import_jobs')->where('id', $jobId)->update([
                        'status' => 'processing',
                        'updated_at' => now(),
                    ]);
                    app(\App\Services\Import\ImportProgressService::class)->cacheProgress($jobId, [
                        'status' => 'processing',
                        'percent' => 1,
                        'message' => 'Memvalidasi file Performance PIS dan menyiapkan konteks import...',
                        'processed_rows' => 0,
                        'total_rows' => $totalRows,
                    ]);
                }

                $send('progress', [
                    'percent' => 1,
                    'message' => 'Memvalidasi file Performance PIS dan menyiapkan konteks import...',
                    'rows_done' => 0,
                    'total' => $totalRows,
                    'speed' => 0,
                ]);

                $workingPath = $this->resolveWorkingImportPath($relativePath);
                $context = $this->buildImportContext($workingPath, $periode, $stageState);
                if ($totalRows <= 0) {
                    $totalRows = $this->estimateImportRowCount($context);
                }

                $send('progress', [
                    'percent' => 5,
                    'message' => 'Menyiapkan CSV staging dari file Performance PIS...',
                    'rows_done' => 0,
                    'total' => $totalRows,
                    'speed' => 0,
                ]);

                $fullExcelStagePath = null;
                if ($this->isExcelFile($workingPath)) {
                    $fullExcelStagePath = $this->stagingService()->createStagedCsvPath(
                        storage_path(self::BULK_LOAD_TEMP_DIR),
                        'performance_pis_import'
                    );

                    $stageResult = $this->stagePerformancePisExcelToCsv(
                        $send,
                        $workingPath,
                        $context,
                        $fullExcelStagePath
                    );

                    if ($stageResult === null) {
                        throw new \RuntimeException('Gagal membuat CSV staging dari file Excel Performance PIS.');
                    }

                    $workingPath = $stageResult['staged_csv_path'];
                    $context = $this->buildCsvContext($workingPath, $periode, (int) ($stageResult['total_rows'] ?? 0));
                    $totalRows = (int) ($stageResult['total_rows'] ?? $totalRows);
                }

                $stagingResult = $this->createFilteredCsvStage($workingPath, $context, $activeFilters, $selectedColumns, $totalRows, $send);
                $totalPreparedRows = $stagingResult['rows_done'];
                $stagingPath = $stagingResult['path'];
                $loadColumns = $stagingResult['columns'];

                if ($totalPreparedRows === 0) {
                    @unlink($stagingPath);

                    DB::table('import_jobs')->where('id', $jobId)->update([
                        'status' => 'failed',
                        'total_files' => 0,
                        'total_success' => 0,
                        'total_failed' => 0,
                        'updated_at' => now(),
                    ]);

                    $send('error', ['message' => 'Tidak ada baris data yang lolos filter untuk diimport.']);
                    return;
                }

                if ($jobId > 0) {
                    DB::table('import_jobs')->where('id', $jobId)->update([
                        'total_files' => $totalPreparedRows,
                        'updated_at' => now(),
                    ]);
                }

                $this->bulkLoadService()->assertTransactionalTable(self::TABLE_NAME, 'import Performance PIS');

                $send('progress', [
                    'percent' => 96,
                    'message' => 'CSV staging siap. Memuat data ke MySQL...',
                    'rows_done' => $totalPreparedRows,
                    'total' => $totalPreparedRows,
                    'speed' => 0,
                ]);

                $totalSuccess = 0;
                $totalFailed = 0;
                $lastErrorMsg = '';

                try {
                    if ($this->supportsNativeBulkLoad()) {
                        $totalSuccess = $this->loadCsvIntoMysql($stagingPath, self::TABLE_NAME, $loadColumns);
                        $totalFailed = max(0, $totalPreparedRows - $totalSuccess);
                    } else {
                        throw new \RuntimeException('LOAD DATA LOCAL INFILE tidak tersedia pada koneksi aktif.');
                    }
                } catch (\Throwable $e) {
                    $lastErrorMsg = Str::limit($e->getMessage(), 800, '...');
                    Log::warning('Performance PIS bulk load fallback: ' . $e->getMessage());

                    $send('progress', [
                        'percent' => 97,
                        'message' => 'Bulk load tidak tersedia. Fallback ke batch insert...',
                        'rows_done' => $totalPreparedRows,
                        'total' => $totalPreparedRows,
                        'speed' => 0,
                    ]);

                    $fallbackResult = $this->insertStagedCsvInBatchesWithProgress(
                        $stagingPath,
                        $loadColumns,
                        $totalPreparedRows,
                        $send
                    );
                    $totalSuccess = $fallbackResult['total_success'];
                    $totalFailed = $fallbackResult['total_failed'];
                    if ($fallbackResult['last_error'] !== '') {
                        $lastErrorMsg = $fallbackResult['last_error'];
                    }
                } finally {
                    @unlink($stagingPath);
                    if ($fullExcelStagePath !== null) {
                        @unlink($fullExcelStagePath);
                    }
                }

                DB::table('import_jobs')->where('id', $jobId)->update([
                    'status' => $totalFailed > 0 ? ($totalSuccess > 0 ? 'failed_partial' : 'failed') : 'completed',
                    'total_files' => $totalPreparedRows,
                    'total_success' => $totalSuccess,
                    'total_failed' => $totalFailed,
                    'updated_at' => now(),
                ]);

                if ($totalSuccess > 0) {
                    $extraCleanupPaths = [$stagingPath];
                    $previewStageCsv = (string) ($stageState['staged_csv_path'] ?? '');
                    if ($previewStageCsv !== '' && file_exists($previewStageCsv)) {
                        $extraCleanupPaths[] = $previewStageCsv;
                    }

                    $this->cleanupSuccessfulImportArtifacts($jobId, $relativePath, $params['periode'] ?? null, $extraCleanupPaths);
                }

                $send('complete', [
                    'total_success' => $totalSuccess,
                    'total_failed' => $totalFailed,
                    'total_rows' => $totalPreparedRows,
                    'error_message' => $lastErrorMsg,
                    'skipped_count' => 0,
                    'skipped_rows' => [],
                    'skip_reasons_summary' => [],
                ]);
            } catch (\Throwable $e) {
                Log::error('PERFORMANCE PIS STREAM ERROR: ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());
                if ($jobId > 0) {
                    DB::table('import_jobs')->where('id', $jobId)->update([
                        'status' => 'failed',
                        'updated_at' => now(),
                    ]);
                }
                $send('error', [
                    'message' => 'Fatal Error: ' . $e->getMessage(),
                ]);
            } finally {
                if ($streamLock) {
                    try {
                        $streamLock->release();
                    } catch (\Throwable $e) {
                        Log::warning('Failed to release Performance PIS import stream lock for job ' . $jobId . ': ' . $e->getMessage());
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

    private function buildExcelContext(string $path, ?string $manualPosisi = null): array
    {
        $worksheetMeta = $this->getWorksheetMeta($path);
        $sampleRowCount = $worksheetMeta['total_rows'] > 0
            ? min(self::SAMPLE_SCAN_ROWS, $worksheetMeta['total_rows'])
            : self::SAMPLE_SCAN_ROWS;
        $sampleRows = $this->readExcelRows($path, $worksheetMeta, 1, $sampleRowCount);

        if (empty($sampleRows)) {
            throw new \RuntimeException('Isi file Excel kosong.');
        }

        $structure = $this->detectExcelStructure($sampleRows);
        if (!$structure) {
            throw new \RuntimeException('Baris header Excel Performance PIS tidak ditemukan.');
        }

        $posisiRaw = $structure['posisi_raw'] ?? $this->findPosisiValue($sampleRows, $structure['header_row']['row_number']);

        return [
            'delimiter' => self::COLUMN_DELIMITER,
            'header_line' => $structure['header_row']['row_number'],
            'source_headers' => $structure['header_row']['cells'],
            'source_indexes' => $this->buildSourceIndexes($structure['header_row']['cells']),
            'headers' => self::TARGET_COLUMNS,
            'posisi' => $this->normalizeDateValue($posisiRaw) ?? $this->normalizeDateValue($manualPosisi),
            'worksheet_name' => $worksheetMeta['worksheet_name'],
            'worksheet_entry' => $worksheetMeta['worksheet_entry'],
            'highest_column' => $worksheetMeta['highest_column'],
            'total_rows' => $worksheetMeta['total_rows'],
        ];
    }

    private function buildImportContext(string $path, ?string $manualPosisi = null, array $stageState = []): array
    {
        if ($this->isCsvFile($path)) {
            return $this->buildCsvContext($path, $manualPosisi, (int) ($stageState['total_rows'] ?? 0));
        }

        return $this->buildExcelContext($path, $manualPosisi);
    }

    private function buildCsvContext(string $path, ?string $manualPosisi = null, int $dataRowsTotal = 0): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Gagal membuka file CSV Performance PIS.');
        }

        $headerLine = null;
        $sourceHeaders = [];
        $delimiter = self::COLUMN_DELIMITER;

        try {
            $lineNumber = 0;
            while (($line = fgets($handle)) !== false) {
                $lineNumber++;
                $trimmed = trim(preg_replace('/^\xEF\xBB\xBF/', '', $line));
                if ($trimmed === '') {
                    continue;
                }

                $headerLine = $lineNumber;
                $delimiter = $this->detectCsvDelimiterFromLine($line);
                rewind($handle);
                $consumedLines = 0;
                while ($consumedLines < $headerLine) {
                    $row = fgetcsv($handle, 0, $delimiter);
                    if ($row === false) {
                        break;
                    }

                    $consumedLines++;
                    if ($consumedLines === $headerLine) {
                        $sourceHeaders = array_map(
                            fn ($value) => $this->normalizeHeader($value),
                            array_map(static fn ($value) => $value === null ? null : (string) $value, $row)
                        );
                    }
                }
                break;
            }
        } finally {
            fclose($handle);
        }

        $sourceHeaders = array_values(array_filter($sourceHeaders, fn ($value) => $value !== ''));
        if ($headerLine === null || empty($sourceHeaders)) {
            throw new \RuntimeException('Header CSV Performance PIS tidak ditemukan.');
        }

        return [
            'delimiter' => $delimiter,
            'header_line' => $headerLine,
            'source_headers' => $sourceHeaders,
            'source_indexes' => $this->buildSourceIndexes($sourceHeaders),
            'headers' => self::TARGET_COLUMNS,
            'posisi' => $this->normalizeDateValue($manualPosisi),
            'source_format' => 'csv',
            'total_rows' => $dataRowsTotal > 0 ? ($dataRowsTotal + $headerLine) : 0,
        ];
    }

    private function parseCsvLine(string $line, ?string $delimiter = null): array
    {
        $parsed = str_getcsv($line, $delimiter ?? self::COLUMN_DELIMITER);
        return array_map(static fn ($value) => $value === null ? null : (string) $value, $parsed ?: []);
    }

    private function iterateDataRows(string $path, array $context, callable $callback): void
    {
        if (($context['source_format'] ?? '') === 'csv' || $this->isCsvFile($path)) {
            $this->iterateCsvDataRows($path, $context, $callback);
            return;
        }

        $this->iterateExcelDataRows($path, $context, $callback);
    }

    private function iterateCsvDataRows(string $path, array $context, callable $callback): void
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Gagal membuka file CSV Performance PIS.');
        }

        $lineNumber = 0;
        try {
            while (($data = fgetcsv($handle, 0, $context['delimiter'] ?? self::COLUMN_DELIMITER)) !== false) {
                $lineNumber++;
                if ($lineNumber <= (int) ($context['header_line'] ?? 1)) {
                    continue;
                }

                $data = array_map(static fn ($value) => $value === null ? null : (string) $value, $data);
                $row = $this->mapCsvRow($context, $data);
                if ($row === null) {
                    continue;
                }

                if ($callback($row, $lineNumber) === false) {
                    break;
                }
            }
        } finally {
            fclose($handle);
        }
    }

    private function mapCsvRow(array $context, array $data): ?array
    {
        $data = $this->alignDataRowWithHeaders($context['source_headers'] ?? [], $data);
        if ($data === null || $this->isEmptyCsvRow($data)) {
            return null;
        }

        $row = [];
        foreach ($context['headers'] as $column) {
            if ($column === 'posisi') {
                $row[] = $context['posisi'] ?? null;
                continue;
            }

            $sourceIndex = $context['source_indexes'][$column] ?? null;
            $row[] = $this->normalizeCellValue($column, $sourceIndex !== null ? ($data[$sourceIndex] ?? null) : null);
        }

        return $row;
    }

    private function alignDataRowWithHeaders(array $sourceHeaders, array $data): ?array
    {
        if (empty($sourceHeaders)) {
            return null;
        }

        $expectedCount = count($sourceHeaders);
        $actualCount = count($data);

        if ($actualCount < $expectedCount) {
            $data = array_pad($data, $expectedCount, null);
        } elseif ($actualCount > $expectedCount) {
            $data = array_slice($data, 0, $expectedCount);
        }

        return $data;
    }

    private function isEmptyCsvRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function storePerformancePisUpload($file): string
    {
        return $file->store('performance_pis_imports');
    }

    private function getWorksheetMeta(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('File Excel tidak dapat dibuka sebagai arsip ZIP.');
        }

        $worksheetEntries = [];
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entryName = $zip->getNameIndex($index);
            if (preg_match('#^xl/worksheets/sheet\d+\.xml$#i', (string) $entryName) === 1) {
                $worksheetEntries[] = (string) $entryName;
            }
        }
        natcasesort($worksheetEntries);
        $worksheetEntry = array_values($worksheetEntries)[0] ?? '';
        $zip->close();

        if ($worksheetEntry === '') {
            throw new \RuntimeException('Worksheet XML untuk file Excel tidak ditemukan.');
        }

        [$highestColumn, $totalRows] = $this->extractWorksheetDimension($path, $worksheetEntry);

        return [
            'worksheet_name' => basename($worksheetEntry, '.xml'),
            'worksheet_entry' => $worksheetEntry,
            'total_rows' => $totalRows,
            'highest_column' => $highestColumn,
        ];
    }

    private function extractWorksheetDimension(string $path, string $worksheetEntry): array
    {
        $reader = new XMLReader();
        if (!$reader->open($this->makeZipUri($path, $worksheetEntry))) {
            throw new \RuntimeException('Worksheet XML tidak dapat dibuka.');
        }

        $highestColumn = 'A';
        $totalRows = 0;
        $foundDimension = false;

        try {
            while ($reader->read()) {
                if ($reader->nodeType !== XMLReader::ELEMENT) {
                    continue;
                }

                if ($reader->localName === 'dimension') {
                    $ref = (string) $reader->getAttribute('ref');
                    if ($ref !== '') {
                        $parts = explode(':', $ref);
                        $endRef = end($parts) ?: $ref;
                        if (preg_match('/([A-Z]+)(\d+)/i', $endRef, $matches) === 1) {
                            $highestColumn = strtoupper($matches[1]);
                            $totalRows = (int) $matches[2];
                            $foundDimension = true;
                        }
                    }
                    if ($foundDimension) {
                        break;
                    }
                }

                if ($reader->localName === 'row') {
                    $rowNumber = (int) ($reader->getAttribute('r') ?: 0);
                    if ($rowNumber > $totalRows) {
                        $totalRows = $rowNumber;
                    }
                }
            }
        } finally {
            $reader->close();
        }

        return [$highestColumn, $totalRows];
    }

    private function readWorksheetRows(string $path, string $worksheetEntry, callable $callback): void
    {
        $reader = new XMLReader();
        if (!$reader->open($this->makeZipUri($path, $worksheetEntry))) {
            throw new \RuntimeException('Worksheet XML tidak dapat dibuka untuk dibaca.');
        }

        try {
            while ($reader->read()) {
                if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'row') {
                    continue;
                }

                $rowNumber = (int) ($reader->getAttribute('r') ?: 0);
                $rowXml = $reader->readOuterXml();
                if ($rowXml === '') {
                    continue;
                }

                $rowElement = @simplexml_load_string($rowXml);
                if ($rowElement === false) {
                    continue;
                }

                $cells = [];
                foreach ($rowElement->c as $cell) {
                    $reference = (string) $cell['r'];
                    if ($reference === '') {
                        continue;
                    }

                    $columnIndex = $this->columnIndexFromReference($reference);
                    $cells[$columnIndex] = $this->resolveCellValue($cell);
                }

                if (!empty($cells)) {
                    ksort($cells);
                    $maxIndex = max(array_keys($cells));
                    $normalizedRow = array_fill(0, $maxIndex + 1, '');
                    foreach ($cells as $index => $value) {
                        $normalizedRow[$index] = trim((string) $value);
                    }
                    $cells = $normalizedRow;
                }

                $continue = $callback($rowNumber, $this->trimTrailingEmptyCells($cells));
                if ($continue === false) {
                    break;
                }
            }
        } finally {
            $reader->close();
        }
    }

    private function makeZipUri(string $path, string $entry): string
    {
        return 'zip://' . str_replace('\\', '/', $path) . '#' . ltrim(str_replace('\\', '/', $entry), '/');
    }

    private function resolveCellValue(\SimpleXMLElement $cell): string
    {
        $type = (string) ($cell['t'] ?? '');

        if ($type === 'inlineStr') {
            $texts = [];
            foreach ($cell->xpath('.//*[local-name()="t"]') as $textNode) {
                $texts[] = (string) $textNode;
            }
            return implode('', $texts);
        }

        if (isset($cell->v)) {
            return (string) $cell->v;
        }

        if (isset($cell->is->t)) {
            return (string) $cell->is->t;
        }

        return '';
    }

    private function columnIndexFromReference(string $reference): int
    {
        preg_match('/([A-Z]+)/i', $reference, $matches);
        $letters = strtoupper($matches[1] ?? 'A');
        $index = 0;

        for ($i = 0; $i < strlen($letters); $i++) {
            $index = ($index * 26) + (ord($letters[$i]) - 64);
        }

        return max(0, $index - 1);
    }

    private function columnLetterFromIndex(int $index): string
    {
        $index = max(0, $index);
        $letters = '';

        do {
            $remainder = $index % 26;
            $letters = chr(65 + $remainder) . $letters;
            $index = intdiv($index, 26) - 1;
        } while ($index >= 0);

        return $letters;
    }

    private function readExcelRows(string $path, array $worksheetMeta, int $startRow, int $rowCount): array
    {
        if ($rowCount <= 0) {
            return [];
        }

        $rows = [];
        $endRow = $worksheetMeta['total_rows'] > 0
            ? min($startRow + $rowCount - 1, $worksheetMeta['total_rows'])
            : ($startRow + $rowCount - 1);

        $this->readWorksheetRows($path, $worksheetMeta['worksheet_entry'], function (int $rowNumber, array $cells) use (&$rows, $startRow, $endRow) {
            if ($rowNumber < $startRow) {
                return true;
            }

            if ($rowNumber > $endRow) {
                return false;
            }

            $rows[] = [
                'row_number' => $rowNumber,
                'cells' => $cells,
            ];

            return true;
        });

        return $rows;
    }

    private function stagePerformancePisExcelToCsv(callable $send, string $absolutePath, array $excelContext, string $stagedCsvPath): ?array
    {
        $sourceHeaders = $this->compactSourceHeaders((array) ($excelContext['source_headers'] ?? []));
        if (empty($sourceHeaders)) {
            return null;
        }

        return $this->stagingService()->stageXlsxSheetWithHeadersToCsv(
            $absolutePath,
            $sourceHeaders,
            $stagedCsvPath,
            $send,
            'Menyiapkan CSV staging Performance PIS'
        );
    }

    private function compactSourceHeaders(array $sourceHeaders): array
    {
        $headers = [];
        foreach ($sourceHeaders as $header) {
            if ($this->normalizeHeader((string) $header) === '') {
                continue;
            }

            $headers[] = (string) $header;
        }

        return $headers;
    }

    private function iterateExcelDataRows(string $path, array $context, callable $callback): void
    {
        $startRow = $context['header_line'] + 1;
        $finalRow = (int) ($context['total_rows'] ?? 0);

        if ($finalRow > 0 && $startRow > $finalRow) {
            return;
        }

        $this->readWorksheetRows($path, $context['worksheet_entry'], function (int $rowNumber, array $cells) use ($startRow, $finalRow, $context, $callback) {
            if ($rowNumber < $startRow) {
                return true;
            }

            if ($finalRow > 0 && $rowNumber > $finalRow) {
                return false;
            }

            $row = $this->mapExcelRow($context, $cells);
            if ($row === null) {
                return true;
            }

            return $callback($row, $rowNumber);
        });
    }

    private function detectExcelStructure(array $sampleRows): ?array
    {
        $bestCandidate = null;

        foreach ($sampleRows as $row) {
            $parsedHeaders = array_map(fn ($value) => $this->normalizeHeader($value), $row['cells']);

            $parsedHeaders = array_values(array_filter(
                $parsedHeaders,
                fn ($value) => $value !== ''
            ));

            if (empty($parsedHeaders)) {
                continue;
            }

            $score = $this->scoreHeaderCandidate($parsedHeaders);
            if ($score <= 0) {
                continue;
            }

            $posisiRaw = $this->findPosisiValue($sampleRows, $row['row_number']);
            if ($this->normalizeDateValue($posisiRaw) !== null) {
                $score += 200;
            }

            $candidate = [
                'header_row' => $row,
                'parsed_headers' => $parsedHeaders,
                'posisi_raw' => $posisiRaw,
                'score' => $score,
            ];

            if ($bestCandidate === null || $candidate['score'] > $bestCandidate['score']) {
                $bestCandidate = $candidate;
            }
        }

        return $bestCandidate;
    }

    private function scoreHeaderCandidate(array $headers): int
    {
        if ($headers === self::EXPECTED_HEADERS) {
            return 10000;
        }

        $score = 0;
        foreach (self::EXPECTED_HEADERS as $index => $expectedHeader) {
            if (!in_array($expectedHeader, $headers, true)) {
                continue;
            }

            $score += 10;

            if (($headers[$index] ?? null) === $expectedHeader) {
                $score += 5;
            }
        }

        if (in_array('kode_kanwil', $headers, true) && in_array('nomor_rekening', $headers, true)) {
            $score += 50;
        }

        return $score;
    }

    private function buildSourceIndexes(array $headers): array
    {
        $indexes = [];

        foreach ($headers as $index => $header) {
            $normalized = $this->normalizeHeader($header);
            if ($normalized === '' || $normalized === 'no' || array_key_exists($normalized, $indexes)) {
                continue;
            }

            $indexes[$normalized] = $index;
        }

        return $indexes;
    }

    private function findPosisiValue(array $sampleRows, int $headerRowNumber): ?string
    {
        $pendingPosisiMarker = false;

        foreach ($sampleRows as $row) {
            if ($row['row_number'] >= $headerRowNumber) {
                break;
            }

            $cells = $this->trimTrailingEmptyCells(array_map(
                fn ($value) => trim((string) $value),
                $row['cells']
            ));

            if (empty($cells)) {
                continue;
            }

            if ($pendingPosisiMarker) {
                $candidate = $this->extractDateCandidateFromCells($cells);
                if ($candidate !== null) {
                    return $candidate;
                }

                if ($this->looksLikePosisiRow($cells)) {
                    continue;
                }

                $pendingPosisiMarker = false;
            }

            if ($this->looksLikePosisiRow($cells)) {
                $candidate = $this->extractDateCandidateFromCells(array_slice($cells, 1));
                if ($candidate !== null) {
                    return $candidate;
                }

                $inlineCandidate = $this->extractInlinePosisiValueFromCells($cells);
                if ($inlineCandidate !== null) {
                    return $inlineCandidate;
                }

                $pendingPosisiMarker = true;
            }
        }

        return null;
    }

    private function mapExcelRow(array $context, array $data): ?array
    {
        if ($this->isEmptyExcelRow($data)) {
            return null;
        }

        $rowNumber = trim((string) ($data[0] ?? ''));
        if ($rowNumber === '' || preg_match('/^\d+$/', $rowNumber) !== 1) {
            return null;
        }

        $row = [];

        foreach ($context['headers'] as $column) {
            if ($column === 'posisi') {
                $row[] = $context['posisi'];
                continue;
            }

            $sourceIndex = $context['source_indexes'][$column] ?? null;
            $row[] = $this->normalizeCellValue($column, $sourceIndex !== null ? ($data[$sourceIndex] ?? null) : null);
        }

        return $row;
    }

    private function looksLikePosisiRow(array $cells): bool
    {
        foreach ($cells as $index => $cell) {
            $normalized = $this->normalizeHeader($cell);
            if ($normalized === 'posisi' && $index === 0) {
                return true;
            }

            if ($index === 0 && preg_match('/^\s*posisi\s*[:=|-]?\s*$/i', trim((string) $cell)) === 1) {
                return true;
            }
        }

        return false;
    }

    private function extractDateCandidateFromCells(array $cells): ?string
    {
        foreach ($cells as $cell) {
            $value = trim((string) $cell);
            if ($value === '') {
                continue;
            }

            if ($this->normalizeDateValue($value) !== null) {
                return $value;
            }
        }

        return null;
    }

    private function extractInlinePosisiValueFromCells(array $cells): ?string
    {
        foreach ($cells as $cell) {
            $candidate = $this->extractInlinePosisiValue((string) $cell);
            if ($candidate !== null) {
                return $candidate;
            }
        }

        return null;
    }

    private function extractInlinePosisiValue(string $value): ?string
    {
        if (preg_match('/^\s*posisi\s*[:=|-]\s*(.+)$/i', $value, $matches) !== 1) {
            return null;
        }

        $candidate = trim($matches[1] ?? '');
        return $candidate !== '' ? $candidate : null;
    }

    private function isEmptyExcelRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function trimTrailingEmptyCells(array $cells): array
    {
        while (!empty($cells) && trim((string) end($cells)) === '') {
            array_pop($cells);
        }

        return $cells;
    }

    private function normalizeHeader(?string $header): string
    {
        $header = trim((string) $header);
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header);
        $header = strtolower($header);
        $header = preg_replace('/[^a-z0-9]+/', '_', $header);
        $header = trim($header, '_');

        $aliases = [
            'product_type' => 'tipe_produk',
            'alamat_email' => 'email',
            'relasi_briguna_yes_no' => 'flag_briguna',
            'relasi_kartu_kredit_yes_no' => 'flag_cc',
        ];

        return $aliases[$header] ?? $header;
    }

    private function normalizeCellValue(string $column, $value)
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        if ($column === 'saldo_britama_kerjasama') {
            return $this->normalizeDecimalValue($value);
        }

        if (in_array($column, ['posisi', 'tanggal_pembuatan_rekening'], true)) {
            return $this->normalizeDateValue($value);
        }

        return $value;
    }

    private function normalizeDateValue(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return StrictDateParser::normalize($value, [
            'j/n/Y',
            'n/j/Y',
        ]);
    }

    private function normalizeDecimalValue($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return number_format((float) $value, 2, '.', '');
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $value = preg_replace('/\s+/', '', $value);
        $value = preg_replace('/[^0-9,\.\-]/', '', $value);

        if ($value === '' || $value === '-') {
            return null;
        }

        $hasComma = str_contains($value, ',');
        $hasDot = str_contains($value, '.');

        if ($hasComma && $hasDot) {
            if (strrpos($value, ',') > strrpos($value, '.')) {
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            } else {
                $value = str_replace(',', '', $value);
            }
        } elseif ($hasComma) {
            $parts = explode(',', $value);
            $lastPart = end($parts);

            if (count($parts) > 2 || strlen((string) $lastPart) === 3) {
                $value = str_replace(',', '', $value);
            } else {
                $value = str_replace(',', '.', $value);
            }
        } elseif ($hasDot) {
            $parts = explode('.', $value);
            $lastPart = end($parts);

            if (count($parts) > 2 || strlen((string) $lastPart) === 3) {
                $value = str_replace('.', '', $value);
            }
        }

        if (!is_numeric($value)) {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function detectCsvDelimiterFromLine(string $line): string
    {
        $normalized = preg_replace('/^\xEF\xBB\xBF/', '', $line);
        $candidates = [';', ',', "\t"];
        $bestDelimiter = self::COLUMN_DELIMITER;
        $bestScore = -1;

        foreach ($candidates as $candidate) {
            $fields = str_getcsv($normalized, $candidate);
            $score = is_array($fields) ? count($fields) : 0;
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestDelimiter = $candidate;
            }
        }

        return $bestDelimiter;
    }

    private function passesFilters(array $row, array $activeFilters): bool
    {
        foreach ($activeFilters as $colIndex => $allowedValues) {
            $value = trim((string) ($row[(int) $colIndex] ?? ''));
            if (!in_array($value, array_map(fn ($item) => trim((string) $item), (array) $allowedValues), true)) {
                return false;
            }
        }

        return true;
    }

    private function buildInsertRow(array $headers, array $row, array $selectedColumns, ?string $timestamp = null): ?array
    {
        $timestamp = $timestamp ?? now()->toDateTimeString();

        $insertRow = [
            'uniqueid_namareport' => uniqid('', true) . self::UNIQUE_SUFFIX,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ];

        foreach ($selectedColumns as $index) {
            if (!isset($headers[$index])) {
                continue;
            }

            $column = $headers[$index];
            if (in_array($column, ['id', 'uniqueid_namareport'], true)) {
                continue;
            }

            $insertRow[$column] = $row[$index] ?? null;
        }

        return $this->hasMeaningfulImportData($insertRow, ['uniqueid_namareport', 'created_at', 'updated_at', 'periode', 'posisi'])
            ? $insertRow
            : null;
    }

    private function countFilteredRows(string $absolutePath, array $context, array $activeFilters, array $selectedColumns): int
    {
        $totalRows = 0;

        $this->iterateDataRows($absolutePath, $context, function (array $row) use (&$totalRows, $activeFilters, $selectedColumns, $context) {
            if (!$this->passesFilters($row, $activeFilters)) {
                return true;
            }

            if ($this->buildInsertRow($context['headers'], $row, $selectedColumns) === null) {
                return true;
            }

            $totalRows++;
            return true;
        });

        return $totalRows;
    }

    private function estimateImportRowCount(array $context): int
    {
        $worksheetTotalRows = (int) ($context['total_rows'] ?? 0);
        $headerLine = (int) ($context['header_line'] ?? 0);

        if ($worksheetTotalRows <= 0) {
            return 0;
        }

        return max(0, $worksheetTotalRows - $headerLine);
    }

    private function createBulkLoadTempCsvPath(int $jobId): string
    {
        $directory = storage_path(self::BULK_LOAD_TEMP_DIR);
        if (!is_dir($directory)) {
            @mkdir($directory, 0777, true);
        }

        return $directory . DIRECTORY_SEPARATOR . self::TABLE_NAME . '_' . $jobId . '_' . Str::random(8) . '.csv';
    }

    private function createFilteredCsvStage(
        string $absolutePath,
        array $context,
        array $activeFilters,
        array $selectedColumns,
        int $totalRows,
        callable $send
    ): array {
        $stagingPath = $this->createBulkLoadTempCsvPath((int) (microtime(true) * 1000));
        $outputHandle = fopen($stagingPath, 'w');
        if ($outputHandle === false) {
            throw new \RuntimeException('Gagal membuat file staging Performance PIS.');
        }

        $loadColumns = ['uniqueid_namareport', 'created_at', 'updated_at'];
        foreach ($selectedColumns as $index) {
            $column = $context['headers'][$index] ?? null;
            if (!$column || in_array($column, ['id', 'uniqueid_namareport'], true)) {
                continue;
            }

            $loadColumns[] = $column;
        }
        $loadColumns = array_values(array_unique($loadColumns));

        $rowsDone = 0;
        $sourceRowsScanned = 0;
        $lastProgressAt = 0;
        $startTime = microtime(true);
        $timestamp = now()->toDateTimeString();
        $lastHeartbeatAt = microtime(true);

        try {
            $this->iterateDataRows($absolutePath, $context, function (array $row) use (&$rowsDone, &$sourceRowsScanned, &$lastProgressAt, &$lastHeartbeatAt, $startTime, $totalRows, $activeFilters, $selectedColumns, $context, $timestamp, $outputHandle, $loadColumns, $send) {
                $sourceRowsScanned++;

                $elapsed = max(microtime(true) - $startTime, 0.001);
                $speed = (int) ($sourceRowsScanned / $elapsed);

                if (($sourceRowsScanned - $lastProgressAt) >= 100 || (microtime(true) - $lastHeartbeatAt) >= 1.5) {
                    $lastProgressAt = $sourceRowsScanned;
                    $lastHeartbeatAt = microtime(true);
                    $percent = $totalRows > 0
                        ? min(92, 10 + (int) (($sourceRowsScanned / $totalRows) * 82))
                        : min(92, 10 + (int) min(82, $sourceRowsScanned / 50));

                    $send('progress', [
                        'percent' => $percent,
                        'message' => "Menyusun CSV staging dari Excel Performance PIS... ({$speed} baris sumber/detik)",
                        'rows_done' => $sourceRowsScanned,
                        'total' => $totalRows,
                        'speed' => $speed,
                    ]);
                }

                if (!$this->passesFilters($row, $activeFilters)) {
                    return true;
                }

                $insertRow = $this->buildInsertRow($context['headers'], $row, $selectedColumns, $timestamp);
                if ($insertRow === null) {
                    return true;
                }

                $stagedRow = [];
                foreach ($loadColumns as $column) {
                    $value = $insertRow[$column] ?? null;
                    $stagedRow[] = $value === null ? '\N' : (string) $value;
                }

                fputcsv($outputHandle, $stagedRow);
                $rowsDone++;

                return true;
            });
        } finally {
            fclose($outputHandle);
        }

        return [
            'path' => $stagingPath,
            'rows_done' => $rowsDone,
            'columns' => $loadColumns,
            'timestamp' => $timestamp,
        ];
    }

    private function supportsNativeBulkLoad(): bool
    {
        return $this->bulkLoadService()->supportsNativeBulkLoad();
    }

    private function loadCsvIntoMysql(string $csvPath, string $tableName, array $columns): int
    {
        return $this->bulkLoadService()->loadCsvIntoMysql($csvPath, $tableName, $columns);
    }

    private function insertStagedCsvInBatches(string $csvPath, array $columns): array
    {
        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Gagal membuka file staging CSV untuk fallback insert.');
        }

        $rows = [];
        $totalSuccess = 0;
        $totalFailed = 0;
        $lastError = '';

        try {
            while (($data = fgetcsv($handle, 0, self::COLUMN_DELIMITER)) !== false) {
                if (empty($data)) {
                    continue;
                }

                $row = [];
                foreach ($columns as $index => $column) {
                    $value = $data[$index] ?? null;
                    $row[$column] = $value === '\N' ? null : $value;
                }

                $rows[] = $row;

                if (count($rows) >= self::INSERT_BATCH_SIZE) {
                    $this->insertBatch($rows, $totalSuccess, $totalFailed, $lastError);
                    $rows = [];
                }
            }
        } finally {
            fclose($handle);
        }

        if (!empty($rows)) {
            $this->insertBatch($rows, $totalSuccess, $totalFailed, $lastError);
        }

        return [
            'total_success' => $totalSuccess,
            'total_failed' => $totalFailed,
            'last_error' => $lastError,
        ];
    }

    private function insertStagedCsvInBatchesWithProgress(string $csvPath, array $columns, int $totalRows, callable $send): array
    {
        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Gagal membuka file staging CSV untuk fallback insert.');
        }

        $rows = [];
        $totalSuccess = 0;
        $totalFailed = 0;
        $lastError = '';
        $processedRows = 0;
        $lastHeartbeatAt = microtime(true);
        $startTime = microtime(true);

        try {
            while (($data = fgetcsv($handle, 0, self::COLUMN_DELIMITER)) !== false) {
                if (empty($data)) {
                    continue;
                }

                $row = [];
                foreach ($columns as $index => $column) {
                    $value = $data[$index] ?? null;
                    $row[$column] = $value === '\N' ? null : $value;
                }

                $rows[] = $row;

                if (count($rows) >= self::INSERT_BATCH_SIZE) {
                    $beforeProcessed = $totalSuccess + $totalFailed;
                    $this->insertBatch($rows, $totalSuccess, $totalFailed, $lastError);
                    $rows = [];
                    $processedRows = $totalSuccess + $totalFailed;

                    if ($processedRows !== $beforeProcessed || (microtime(true) - $lastHeartbeatAt) >= 1.0) {
                        $lastHeartbeatAt = microtime(true);
                        $elapsed = max(microtime(true) - $startTime, 0.001);
                        $speed = (int) round($processedRows / $elapsed);
                        $percent = $totalRows > 0
                            ? min(99, 97 + (int) floor(($processedRows / max($totalRows, 1)) * 2))
                            : 97;

                        $send('progress', [
                            'percent' => $percent,
                            'message' => 'Memasukkan data ke MySQL dengan fallback batch insert...',
                            'rows_done' => $processedRows,
                            'total' => $totalRows,
                            'speed' => $speed,
                        ]);
                    }
                }
            }
        } finally {
            fclose($handle);
        }

        if (!empty($rows)) {
            $this->insertBatch($rows, $totalSuccess, $totalFailed, $lastError);
            $processedRows = $totalSuccess + $totalFailed;
        }

        $elapsed = max(microtime(true) - $startTime, 0.001);
        $speed = (int) round($processedRows / $elapsed);
        $send('progress', [
            'percent' => 99,
            'message' => 'Finalisasi fallback batch insert...',
            'rows_done' => $processedRows,
            'total' => $totalRows,
            'speed' => $speed,
        ]);

        return [
            'total_success' => $totalSuccess,
            'total_failed' => $totalFailed,
            'last_error' => $lastError,
        ];
    }

    private function insertBatch(array $rows, int &$totalSuccess, int &$totalFailed, string &$lastErrorMsg): void
    {
        foreach (array_chunk($rows, 100) as $batch) {
            $batch = $this->allocateGapIdsForRows(self::TABLE_NAME, $batch);

            try {
                DB::table(self::TABLE_NAME)->insert($batch);
                $totalSuccess += count($batch);
            } catch (\Throwable $e) {
                $lastErrorMsg = Str::limit($e->getMessage(), 800, '...');
                Log::warning('Import Performance PIS batch insert failed: ' . $e->getMessage());

                foreach ($batch as $single) {
                    try {
                        DB::table(self::TABLE_NAME)->insert($single);
                        $totalSuccess++;
                    } catch (\Throwable $singleError) {
                        $totalFailed++;
                        $lastErrorMsg = Str::limit($singleError->getMessage(), 800, '...');
                    }
                }
            }
        }
    }

    private function cleanupUploadedFile(string $relativePath): void
    {
        try {
            Storage::delete($relativePath);
        } catch (\Throwable $e) {
            Log::warning('Gagal menghapus file Performance PIS sementara: ' . $e->getMessage());
        }
    }

    private function cleanupSuccessfulImportArtifacts(int $jobId, string $relativePath, ?string $periodHint = null, array $extraPaths = []): void
    {
        try {
            app(\App\Services\Import\ImportCleanupService::class)->dispatchImportedJobSync($jobId, self::TABLE_NAME, $periodHint, static::class);
            app(ImportCleanupController::class)->cleanupSuccessfulJobArtifacts(
                $jobId,
                array_values(array_filter(array_merge([$relativePath], $extraPaths)))
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal menjalankan cleanup terpusat Performance PIS: ' . $e->getMessage(), [
                'job_id' => $jobId,
                'relative_path' => $relativePath,
            ]);
        }
    }

    private function hasMeaningfulImportData(array $row, array $ignoredKeys = []): bool
    {
        $ignoredLookup = array_fill_keys(array_map('strtolower', $ignoredKeys), true);

        foreach ($row as $key => $value) {
            if (isset($ignoredLookup[strtolower((string) $key)])) {
                continue;
            }

            if ($value === null) {
                continue;
            }

            if (is_string($value) && trim($value) === '') {
                continue;
            }

            return true;
        }

        return false;
    }

    private function hasUniqueCollectionCapacity(array $uniqueValues): bool
    {
        foreach ($uniqueValues as $values) {
            if (count($values) < self::UNIQUE_VALUE_LIMIT) {
                return true;
            }
        }

        return false;
    }

    private function collectPreviewUniqueValues(string $path, array $context): array
    {
        $uniqueValues = [];
        foreach ($context['headers'] as $index => $header) {
            $uniqueValues[$index] = [];
        }

        $this->iterateDataRows($path, $context, function (array $row) use (&$uniqueValues): bool {
            foreach ($row as $colIndex => $value) {
                if (!isset($uniqueValues[$colIndex]) || count($uniqueValues[$colIndex]) >= self::UNIQUE_VALUE_LIMIT) {
                    continue;
                }

                $key = trim((string) ($value ?? ''));
                $uniqueValues[$colIndex][$key] = true;
            }

            return $this->hasUniqueCollectionCapacity($uniqueValues);
        });

        return $uniqueValues;
    }

    private function collectPreviewUniqueValuesFromRows(array $rows, array $context): array
    {
        $uniqueValues = [];
        foreach ($context['headers'] as $index => $header) {
            $uniqueValues[$index] = [];
        }

        foreach ($rows as $row) {
            foreach ($row as $colIndex => $value) {
                if (!isset($uniqueValues[$colIndex])) {
                    continue;
                }

                $key = trim((string) ($value ?? ''));
                $uniqueValues[$colIndex][$key] = true;
            }
        }

        return $uniqueValues;
    }
}
