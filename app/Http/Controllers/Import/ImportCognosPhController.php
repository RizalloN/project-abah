<?php

namespace App\Http\Controllers\Import;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Import\Concerns\AuthorizesSessionImportStorageFiles;
use App\Http\Controllers\Import\Concerns\ServesMappedCsvPreviewFilters;
use App\Http\Controllers\Import\Concerns\SmartCsvImportSupport;
use App\Services\Import\ImportCleanupService;
use App\Services\Import\MySqlBulkLoadService;
use App\Support\SargableDateFilter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportCognosPhController extends Controller
{
    use AuthorizesSessionImportStorageFiles, ServesMappedCsvPreviewFilters;

    use SmartCsvImportSupport;

    private const TABLE_NAME = 'cognos_ph';
    private const UNIQUE_SUFFIX = '_CPH';
    private const REPORT_LABEL = 'Cognos PH';
    private const COLUMN_DELIMITER = ';';
    private const BULK_STAGE_DELIMITER = ',';
    private const PREVIEW_ROW_LIMIT = 100;
    private const PREVIEW_SCAN_LIMIT = 1000;
    private const PREVIEW_UNIQUE_LIMIT = 200;
    private const INSERT_BATCH_SIZE = 1000;
    private const STAGED_CSV_TEMP_DIR = 'app/cognos_ph_stage';
    private const BULK_LOAD_TEMP_DIR = 'app/import_bulk';

    private const TARGET_COLUMNS = [
        'periode',
        'kanwil',
        'region',
        'ro_fix',
        'bc',
        'sub_bc',
        'kanca',
        'unit_kerja',
        'acctno',
        'cifno',
        'sname',
        'segmen',
        'segmen_bisnis_2025',
        'produk',
        'segmen_kur',
        'segmen_repeat',
        'segmen_2',
        'compliance',
        'saldo_ph',
    ];

    private const SOURCE_INDEX_MAP = [
        'periode' => 0,
        'kanwil' => 1,
        'region' => 2,
        'ro_fix' => 3,
        'bc' => 4,
        'sub_bc' => 5,
        'kanca' => 6,
        'unit_kerja' => 7,
        'acctno' => 8,
        'cifno' => 9,
        'sname' => 10,
        'segmen' => 11,
        'segmen_bisnis_2025' => 12,
        'produk' => 13,
        'segmen_kur' => 14,
        'segmen_repeat' => 15,
        'segmen_2' => 16,
        'compliance' => 17,
        'saldo_ph' => 18,
    ];

    private const FILTERABLE_COLUMNS = [
        'kanwil',
        'region',
        'ro_fix',
        'kanca',
        'unit_kerja',
        'segmen',
        'segmen_bisnis_2025',
        'produk',
        'segmen_kur',
        'segmen_repeat',
        'segmen_2',
        'compliance',
    ];

    private const DECIMAL_COLUMNS = [
        'saldo_ph',
    ];

    public function upload(Request $request)
    {
        $request->validate([
            'id_report' => 'required',
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:' . $this->configuredSessionImportUploadMaxKilobytes(),
        ]);

        $path = $request->file('file')->store('cognos_ph_imports');

        session([
            'active_id_report' => $request->input('id_report'),
            'import_type' => 'cognos_ph',
            'cognos_ph_file' => $path,
        ]);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'path' => $path,
            ]);
        }

        return redirect()->route('import.cognos-ph.preview');
    }

    public function preparePreviewStream(Request $request)
    {
        ini_set('memory_limit', '512M');
        set_time_limit(0);

        $relativePath = session('cognos_ph_file');
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
                    $send('error_msg', ['message' => 'Sesi upload ' . self::REPORT_LABEL . ' tidak ditemukan.']);
                    return;
                }

                $absolutePath = Storage::path($relativePath);
                if (!file_exists($absolutePath)) {
                    $send('error_msg', ['message' => 'File ' . self::REPORT_LABEL . ' tidak ditemukan di server.']);
                    return;
                }

                $workingPath = $absolutePath;
                if ($this->isExcelFile($absolutePath)) {
                    $stageState = $this->getStagedExcelState($relativePath);
                    $stagedCsvPath = (string) ($stageState['staged_csv_path'] ?? '');

                    if ($stagedCsvPath !== '' && file_exists($stagedCsvPath)) {
                        $workingPath = $stagedCsvPath;
                    } else {
                        $send('progress', ['percent' => 25, 'message' => 'Mendeteksi header Excel ' . self::REPORT_LABEL . '...']);
                        $excelMeta = $this->detectExcelHeaderViaPython($absolutePath);
                        if ($excelMeta === null) {
                            $send('error_msg', ['message' => 'Excel ' . self::REPORT_LABEL . ' membutuhkan Python staging agar bisa dipreview.']);
                            return;
                        }

                        $send('progress', ['percent' => 50, 'message' => 'Mengonversi Excel ke CSV staging ' . self::REPORT_LABEL . '...']);
                        $stageResult = $this->stageExcelToCsv(
                            $send,
                            $absolutePath,
                            (int) ($excelMeta['header_index'] ?? 0),
                            $this->normalizeExcelHeaders((array) ($excelMeta['header_values'] ?? []))
                        );

                        if ($stageResult === null) {
                            $send('error_msg', ['message' => 'Gagal membuat CSV staging dari Excel ' . self::REPORT_LABEL . '.']);
                            return;
                        }

                        $this->putStagedExcelState($relativePath, $stageResult);
                        $workingPath = (string) $stageResult['staged_csv_path'];
                    }
                }

                $send('progress', ['percent' => 70, 'message' => 'Memvalidasi struktur CSV ' . self::REPORT_LABEL . '...']);
                $context = $this->buildCsvContext($workingPath);

                $send('ready', [
                    'redirect' => route('import.cognos-ph.preview', ['file_path' => $relativePath]),
                    'detected_periode' => $context['periode'] ?? null,
                ]);
            } catch (\Throwable $e) {
                Log::error('COGNOS PH PREPARE PREVIEW ERROR: ' . $e->getMessage(), [
                    'file' => $relativePath,
                ]);
                $send('error_msg', ['message' => 'Gagal menyiapkan preview ' . self::REPORT_LABEL . ': ' . $e->getMessage()]);
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
        ini_set('memory_limit', '512M');
        set_time_limit(0);

        $relativePath = (string) session('cognos_ph_file', $request->input('file_path'));
        if (!$relativePath) {
            return redirect()->route('import.index')->with('error', 'File import ' . self::REPORT_LABEL . ' tidak ditemukan. Silakan upload ulang.');
        }

        [$relativePath, $absolutePath] = $this->authorizeSessionImportStorageFile(
            $relativePath,
            'cognos_ph_file',
            ['cognos_ph_imports'],
            ['csv', 'txt', 'xlsx', 'xls']
        );

        $workingPath = $this->resolveWorkingImportPath($relativePath);
        if (!file_exists($workingPath)) {
            return redirect()->route('import.index')->with('error', 'File staging ' . self::REPORT_LABEL . ' tidak ditemukan. Silakan upload ulang.');
        }

        try {
            $context = $this->buildCsvContext($workingPath);
        } catch (\Throwable $e) {
            return redirect()->route('import.index')->with('error', 'Struktur CSV ' . self::REPORT_LABEL . ' tidak dikenali: ' . $e->getMessage());
        }

        [$previewData, $formattedUniqueValues] = $this->collectMappedPreviewSample(
            $workingPath,
            $context,
            self::PREVIEW_ROW_LIMIT,
            self::PREVIEW_SCAN_LIMIT,
            self::PREVIEW_UNIQUE_LIMIT
        );

        return view('import.preview', [
            'headers' => $context['headers'],
            'previewData' => $previewData,
            'filePath' => $relativePath,
            'formattedUniqueValues' => $formattedUniqueValues,
            'currentDelimiter' => $context['delimiter'],
            'processRoute' => route('import.cognos-ph.process'),
            'previewRoute' => route('import.cognos-ph.preview.refresh'),
            'initRoute' => route('import.cognos-ph.init'),
            'streamRoute' => route('import.cognos-ph.stream'),
            'filterOptionsRoute' => route('import.cognos-ph.filter-options'),
            'filteredRowsRoute' => route('import.cognos-ph.filtered-rows'),
            'backRoute' => route('import.index'),
            'lockDelimiterSelector' => true,
            'fixedDelimiterLabel' => 'Delimiter terdeteksi otomatis (;)',
            'disableArea6AutoFilter' => true,
            'deferDependentFilterRefresh' => true,
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
        ]);

        [$relativePath, $absolutePath] = $this->authorizeSessionImportStorageFile(
            (string) $request->input('file_path'),
            'cognos_ph_file',
            ['cognos_ph_imports'],
            ['csv', 'txt', 'xlsx', 'xls']
        );

        try {
            $this->bulkLoadService()->assertTransactionalTable(self::TABLE_NAME, 'import ' . self::REPORT_LABEL);
        } catch (\RuntimeException $e) {
            return response()->json([
                'status' => 'error',
                'title' => 'Import Diblokir',
                'text' => $e->getMessage(),
            ], 422);
        }

        if (!$this->isValidReportSelection()) {
            return response()->json([
                'status' => 'error',
                'title' => 'Gagal!',
                'text' => 'Report yang dipilih tidak mengarah ke tabel ' . self::TABLE_NAME . '.',
            ], 422);
        }

        $workingPath = $this->resolveWorkingImportPath($relativePath);
        if (!file_exists($workingPath)) {
            return response()->json([
                'status' => 'error',
                'title' => 'Gagal!',
                'text' => 'File staging ' . self::REPORT_LABEL . ' tidak ditemukan.',
            ], 422);
        }

        $selectedColumns = array_map('intval', $request->input('selected_columns', []));
        $activeFilters = json_decode($request->input('active_filters_json', '{}'), true) ?: [];

        try {
            $context = $this->buildCsvContext($workingPath);
            $totalRows = $this->countFilteredRows($workingPath, $context, $activeFilters, $selectedColumns);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'title' => 'Gagal!',
                'text' => 'Struktur CSV ' . self::REPORT_LABEL . ' tidak dikenali: ' . $e->getMessage(),
            ], 422);
        }

        if (empty($context['periode'])) {
            return response()->json([
                'status' => 'error',
                'title' => 'Gagal!',
                'text' => 'Kolom PERIODE pada CSV tidak valid atau kosong.',
            ], 422);
        }

        if ($totalRows === 0) {
            return response()->json([
                'status' => 'warning',
                'title' => 'Tidak Ada Data',
                'text' => 'Tidak ada baris yang lolos filter untuk diimport.',
            ], 422);
        }

        if (SargableDateFilter::apply(DB::table(self::TABLE_NAME), 'periode', '=', $context['periode'])->exists()) {
            $this->cleanupUploadedFile($relativePath);

            return response()->json([
                'status' => 'warning',
                'title' => 'Data Ditolak (Duplikat)!',
                'text' => 'Data untuk periode <b>' . Carbon::parse($context['periode'])->translatedFormat('d F Y') . '</b> sudah ada di tabel <b class="text-uppercase">' . self::TABLE_NAME . '</b>.',
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
                'mode' => 'cognos_ph_import',
                'table_name' => self::TABLE_NAME,
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
            'total_rows' => $totalRows,
        ];
        session(['cognos_ph_import_params' => $importParams]);
        Cache::put('cognos_ph_import_params_' . $jobId, $importParams, now()->addHours(4));

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

        $sessionParams = session('cognos_ph_import_params', []);
        $jobId = (int) ($sessionParams['job_id'] ?? $request->query('job_id', 0));
        $params = Cache::get('cognos_ph_import_params_' . $jobId, $sessionParams);

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
                    $streamLock = Cache::lock('import_cognos_ph_stream_job_' . $jobId, 7200);
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
                            $send('error', ['message' => 'Job import ' . self::REPORT_LABEL . ' ini sudah sedang diproses pada koneksi lain.']);
                        }
                        return;
                    }
                }

                $relativePath = $params['file_path'] ?? '';
                $absolutePath = Storage::path($relativePath);
                $selectedColumns = array_map('intval', $params['selected_columns'] ?? []);
                $activeFilters = $params['active_filters'] ?? [];
                $totalRows = (int) ($params['total_rows'] ?? 0);

                if ($relativePath === '' || !file_exists($absolutePath)) {
                    $send('error', ['message' => 'File ' . self::REPORT_LABEL . ' tidak ditemukan di server.']);
                    return;
                }

                $workingPath = $this->resolveWorkingImportPath($relativePath);
                $context = $this->buildCsvContext($workingPath);

                if (!empty($context['periode']) && SargableDateFilter::apply(DB::table(self::TABLE_NAME), 'periode', '=', $context['periode'])->exists()) {
                    $this->cleanupUploadedFile($relativePath);
                    $send('error', ['message' => 'Data untuk periode ' . $context['periode'] . ' sudah ada di tabel ' . self::TABLE_NAME . '.']);
                    return;
                }

                $send('progress', [
                    'percent' => 5,
                    'message' => 'Menyiapkan CSV staging ' . self::REPORT_LABEL . '...',
                    'rows_done' => 0,
                    'total' => $totalRows,
                    'speed' => 0,
                ]);

                $stageResult = $this->createFilteredCsvStage($workingPath, $context, $activeFilters, $selectedColumns, $totalRows, $send);
                $stagingPath = $stageResult['path'];
                $loadColumns = $stageResult['columns'];
                $preparedRows = $stageResult['rows_done'];

                if ($preparedRows === 0) {
                    @unlink($stagingPath);
                    $send('error', ['message' => 'Tidak ada baris data yang lolos filter untuk diimport.']);
                    return;
                }

                if ($jobId > 0) {
                    DB::table('import_jobs')->where('id', $jobId)->update([
                        'total_files' => $preparedRows,
                        'updated_at' => now(),
                    ]);
                }

                $send('progress', [
                    'percent' => 96,
                    'message' => 'CSV staging siap. Memuat data ke MySQL...',
                    'rows_done' => $preparedRows,
                    'total' => $preparedRows,
                    'speed' => 0,
                ]);

                $totalSuccess = 0;
                $totalFailed = 0;
                $lastErrorMsg = '';

                try {
                    if ($this->supportsNativeBulkLoad()) {
                        $totalSuccess = $this->loadCsvIntoMysql($stagingPath, self::TABLE_NAME, $loadColumns);
                        $totalFailed = max(0, $preparedRows - $totalSuccess);
                    } else {
                        throw new \RuntimeException('LOAD DATA LOCAL INFILE tidak tersedia pada koneksi aktif.');
                    }
                } catch (\Throwable $e) {
                    $lastErrorMsg = Str::limit($e->getMessage(), 800, '...');
                    Log::warning(self::REPORT_LABEL . ' bulk load fallback: ' . $e->getMessage());

                    $send('progress', [
                        'percent' => 97,
                        'message' => 'Bulk load tidak tersedia. Fallback ke batch insert...',
                        'rows_done' => $preparedRows,
                        'total' => $preparedRows,
                        'speed' => 0,
                    ]);

                    $fallback = $this->insertStagedCsvInBatchesWithProgress($stagingPath, $loadColumns, $preparedRows, $send);
                    $totalSuccess = $fallback['total_success'];
                    $totalFailed = $fallback['total_failed'];
                    if ($fallback['last_error'] !== '') {
                        $lastErrorMsg = $fallback['last_error'];
                    }
                } finally {
                    @unlink($stagingPath);
                }

                if ($jobId > 0) {
                    DB::table('import_jobs')->where('id', $jobId)->update([
                        'status' => $totalFailed > 0 ? ($totalSuccess > 0 ? 'failed_partial' : 'failed') : 'completed',
                        'total_files' => $preparedRows,
                        'total_success' => $totalSuccess,
                        'total_failed' => $totalFailed,
                        'updated_at' => now(),
                    ]);
                }

                if ($totalSuccess > 0) {
                    $stageState = $this->getStagedExcelState($relativePath);
                    $extraCleanupPaths = [];
                    $previewStageCsv = (string) ($stageState['staged_csv_path'] ?? '');
                    if ($previewStageCsv !== '' && file_exists($previewStageCsv)) {
                        $extraCleanupPaths[] = $previewStageCsv;
                    }
                    $this->cleanupSuccessfulImportArtifacts($jobId, $relativePath, $context['periode'] ?? null, $extraCleanupPaths);
                }

                $send('complete', [
                    'total_success' => $totalSuccess,
                    'total_failed' => $totalFailed,
                    'total_rows' => $preparedRows,
                    'error_message' => $lastErrorMsg,
                    'skipped_count' => 0,
                    'skipped_rows' => [],
                    'skip_reasons_summary' => [],
                ]);
            } catch (\Throwable $e) {
                Log::error('COGNOS PH STREAM ERROR: ' . $e->getMessage(), [
                    'job_id' => $jobId,
                ]);
                if ($jobId > 0) {
                    DB::table('import_jobs')->where('id', $jobId)->update([
                        'status' => 'failed',
                        'updated_at' => now(),
                    ]);
                }
                $send('error', ['message' => 'Fatal Error: ' . $e->getMessage()]);
            } finally {
                if ($streamLock) {
                    try {
                        $streamLock->release();
                    } catch (\Throwable $e) {
                        Log::warning('Failed to release ' . self::REPORT_LABEL . ' import stream lock: ' . $e->getMessage());
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

    public function processImport(Request $request)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        $request->validate([
            'file_path' => 'required|string',
            'selected_columns' => 'required|array|min:1',
            'active_filters_json' => 'nullable|string',
            'delimiter' => 'required|string',
        ]);

        [$relativePath, $absolutePath] = $this->authorizeSessionImportStorageFile(
            (string) $request->input('file_path'),
            'cognos_ph_file',
            ['cognos_ph_imports'],
            ['csv', 'txt', 'xlsx', 'xls']
        );

        try {
            $this->bulkLoadService()->assertTransactionalTable(self::TABLE_NAME, 'import ' . self::REPORT_LABEL);
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
            $context = $this->buildCsvContext($workingPath);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'title' => 'Gagal!',
                'text' => 'Struktur file ' . self::REPORT_LABEL . ' tidak dikenali: ' . $e->getMessage(),
            ], 422);
        }

        if (!empty($context['periode']) && SargableDateFilter::apply(DB::table(self::TABLE_NAME), 'periode', '=', $context['periode'])->exists()) {
            $this->cleanupUploadedFile($relativePath);

            return response()->json([
                'status' => 'warning',
                'title' => 'Data Ditolak (Duplikat)!',
                'text' => 'Data untuk periode <b>' . Carbon::parse($context['periode'])->translatedFormat('d F Y') . '</b> sudah ada di tabel <b class="text-uppercase">' . self::TABLE_NAME . '</b>.',
            ], 422);
        }

        $stageResult = $this->createFilteredCsvStage($workingPath, $context, $activeFilters, $selectedColumns, 0, function () {
        });
        $stagingPath = $stageResult['path'];
        $loadColumns = $stageResult['columns'];
        $preparedRows = $stageResult['rows_done'];

        if ($preparedRows === 0) {
            @unlink($stagingPath);
            return response()->json([
                'status' => 'warning',
                'title' => 'Tidak Ada Data',
                'text' => 'Tidak ada baris yang lolos filter untuk diimport.',
            ], 422);
        }

        $jobId = app(\App\Services\Import\ImportProgressService::class)->createJob([
            'id_report' => session('active_id_report'),
            'file_name' => basename($absolutePath),
            'folder_path' => dirname($absolutePath),
            'status' => 'processing',
            'total_files' => $preparedRows,
            'total_success' => 0,
            'total_failed' => 0,
            'created_by' => auth()->id() ?? 1,
            'job_context' => [
                'controller' => static::class,
                'mode' => 'cognos_ph_import',
                'table_name' => self::TABLE_NAME,
                'file_hash' => sha1($absolutePath),
            ],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $totalSuccess = 0;
        $totalFailed = 0;
        $lastErrorMsg = '';

        try {
            if ($this->supportsNativeBulkLoad()) {
                $totalSuccess = $this->loadCsvIntoMysql($stagingPath, self::TABLE_NAME, $loadColumns);
                $totalFailed = max(0, $preparedRows - $totalSuccess);
            } else {
                throw new \RuntimeException('LOAD DATA LOCAL INFILE tidak tersedia pada koneksi aktif.');
            }
        } catch (\Throwable $e) {
            $lastErrorMsg = Str::limit($e->getMessage(), 800, '...');
            $fallback = $this->insertStagedCsvInBatches($stagingPath, $loadColumns);
            $totalSuccess = $fallback['total_success'];
            $totalFailed = $fallback['total_failed'];
            if ($fallback['last_error'] !== '') {
                $lastErrorMsg = $fallback['last_error'];
            }
        } finally {
            @unlink($stagingPath);
        }

        DB::table('import_jobs')->where('id', $jobId)->update([
            'status' => $totalFailed > 0 ? ($totalSuccess > 0 ? 'failed_partial' : 'failed') : 'completed',
            'total_files' => $preparedRows,
            'total_success' => $totalSuccess,
            'total_failed' => $totalFailed,
            'updated_at' => now(),
        ]);

        if ($totalSuccess > 0) {
            $stageState = $this->getStagedExcelState($relativePath);
            $extraCleanupPaths = [];
            $previewStageCsv = (string) ($stageState['staged_csv_path'] ?? '');
            if ($previewStageCsv !== '' && file_exists($previewStageCsv)) {
                $extraCleanupPaths[] = $previewStageCsv;
            }
            $this->cleanupSuccessfulImportArtifacts($jobId, $relativePath, $context['periode'] ?? null, $extraCleanupPaths);
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

    private function bulkLoadService(): MySqlBulkLoadService
    {
        return app(MySqlBulkLoadService::class);
    }

    private function isExcelFile(string $path): bool
    {
        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['xlsx', 'xls'], true);
    }

    private function findPython(): ?string
    {
        foreach (['python', 'python3', 'py'] as $cmd) {
            $output = @shell_exec(escapeshellcmd($cmd) . ' --version 2>&1');
            if ($output && str_contains($output, 'Python 3')) {
                return $cmd;
            }
        }

        return null;
    }

    private function stageCacheKey(string $relativePath): string
    {
        return 'cognos_ph_excel_stage_' . md5($relativePath);
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

    private function clearStagedExcelState(string $relativePath): void
    {
        Cache::forget($this->stageCacheKey($relativePath));
    }

    private function createStagedCsvPath(): string
    {
        $directory = storage_path(self::STAGED_CSV_TEMP_DIR);
        if (!is_dir($directory)) {
            @mkdir($directory, 0777, true);
        }

        return $directory . DIRECTORY_SEPARATOR . 'cognos_ph_' . Str::random(12) . '.csv';
    }

    private function createBulkLoadTempCsvPath(int $jobId): string
    {
        return $this->bulkLoadService()->createBulkLoadTempCsvPath(storage_path(self::BULK_LOAD_TEMP_DIR), self::TABLE_NAME, $jobId);
    }

    private function detectExcelHeaderViaPython(string $path): ?array
    {
        $pythonExe = $this->findPython();
        $scriptPath = base_path('scripts/excel_gpu_processor.py');

        if (!$pythonExe || !file_exists($scriptPath)) {
            return null;
        }

        $configFile = storage_path('app/cognos_ph_excel_init_' . uniqid() . '.json');
        file_put_contents($configFile, json_encode(['file_path' => $path], JSON_UNESCAPED_UNICODE));

        $nullDevice = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
        $cmd = escapeshellarg($pythonExe)
            . ' ' . escapeshellarg($scriptPath)
            . ' --config ' . escapeshellarg($configFile)
            . ' --mode init'
            . ' 2>' . $nullDevice;

        $output = @shell_exec($cmd);
        @unlink($configFile);

        if (!$output) {
            return null;
        }

        $result = json_decode(trim($output), true);
        if (!$result || ($result['status'] ?? '') !== 'ok') {
            return null;
        }

        return [
            'header_index' => (int) ($result['header_index'] ?? 0),
            'total_rows' => (int) ($result['total_rows'] ?? 0),
            'header_values' => (array) ($result['header_values'] ?? []),
        ];
    }

    private function stageExcelToCsv(callable $send, string $sourcePath, int $headerIndex, array $normalizedHeaders): ?array
    {
        $pythonExe = $this->findPython();
        $scriptPath = base_path('scripts/excel_gpu_processor.py');

        if (!$pythonExe || !file_exists($scriptPath)) {
            return null;
        }

        $stagedCsvPath = $this->createStagedCsvPath();
        $configFile = storage_path('app/cognos_ph_excel_stage_' . uniqid() . '.json');
        file_put_contents($configFile, json_encode([
            'file_path' => $sourcePath,
            'header_index' => $headerIndex,
            'normalized_headers' => $normalizedHeaders,
            'output_csv_path' => $stagedCsvPath,
        ], JSON_UNESCAPED_UNICODE));

        $cmd = escapeshellarg($pythonExe)
            . ' ' . escapeshellarg($scriptPath)
            . ' --config ' . escapeshellarg($configFile)
            . ' --mode stage';

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($process)) {
            @unlink($configFile);
            @unlink($stagedCsvPath);
            return null;
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $buffer = '';
        $donePayload = null;
        $pythonError = null;

        $processLine = function (string $line) use ($send, &$donePayload, &$pythonError): void {
            $line = trim($line);
            if ($line === '') {
                return;
            }

            $data = json_decode($line, true);
            if (!is_array($data)) {
                return;
            }

            $type = $data['type'] ?? 'progress';
            unset($data['type']);

            if ($type === 'progress') {
                $send('progress', $data);
                return;
            }

            if ($type === 'done') {
                $donePayload = $data;
                return;
            }

            if ($type === 'error') {
                $pythonError = $data['message'] ?? 'Python staging error tidak diketahui';
            }
        };

        while (true) {
            $status = proc_get_status($process);
            $chunk = fread($pipes[1], 65536);
            if ($chunk !== false && $chunk !== '') {
                $buffer .= $chunk;
                while (($pos = strpos($buffer, "\n")) !== false) {
                    $line = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 1);
                    $processLine($line);
                }
            }

            if (!$status['running']) {
                break;
            }

            usleep(50000);
        }

        $remaining = stream_get_contents($pipes[1]);
        if ($remaining) {
            $buffer .= $remaining;
            foreach (explode("\n", $buffer) as $line) {
                $processLine($line);
            }
        }

        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        @unlink($configFile);

        if ($pythonError !== null || !$donePayload || !file_exists($stagedCsvPath)) {
            @unlink($stagedCsvPath);
            return null;
        }

        return [
            'staged_csv_path' => $stagedCsvPath,
            'total_rows' => (int) ($donePayload['total_rows'] ?? 0),
            'header_index' => 0,
            'headers' => array_values($normalizedHeaders),
        ];
    }

    private function normalizeExcelHeaders(array $headerValues): array
    {
        $headers = [];
        foreach ($headerValues as $index => $value) {
            $label = trim((string) $value);
            $headers[$index] = $label !== '' ? $label : ('COL_' . $index);
        }

        return $headers;
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

    private function buildCsvContext(string $path): array
    {
        $delimiter = (string) ($this->smartProfileCsvSource($path, ['periode_data', 'saldo_ph'], [self::COLUMN_DELIMITER])['delimiter'] ?? self::COLUMN_DELIMITER);
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Gagal membuka file CSV.');
        }

        $headerLine = null;
        $sourceHeaders = [];
        try {
            $lineNumber = 0;
            while (($line = fgets($handle)) !== false) {
                $lineNumber++;
                $parsed = $this->parseCsvLine($line, $delimiter);
                if ($parsed === [] || $this->isEmptyCsvRow($parsed)) {
                    continue;
                }

                $headerLine = $lineNumber;
                $sourceHeaders = $parsed;
                break;
            }
        } finally {
            fclose($handle);
        }

        if ($headerLine === null || count($sourceHeaders) < 19) {
            throw new \RuntimeException('Header CSV ' . self::REPORT_LABEL . ' tidak ditemukan atau tidak lengkap.');
        }

        return [
            'delimiter' => $delimiter,
            'header_line' => $headerLine,
            'source_headers' => $sourceHeaders,
            'headers' => self::TARGET_COLUMNS,
            'periode' => $this->findPeriodeValue($path, $headerLine, $delimiter),
        ];
    }

    private function findPeriodeValue(string $path, int $headerLineNumber, string $delimiter): ?string
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return null;
        }

        try {
            $lineNumber = 0;
            while (($line = fgets($handle)) !== false) {
                $lineNumber++;
                if ($lineNumber <= $headerLineNumber) {
                    continue;
                }

                $parsed = $this->parseCsvLine($line, $delimiter);
                if ($parsed === [] || $this->isEmptyCsvRow($parsed) || $this->isFooterRow($parsed)) {
                    continue;
                }

                return $this->normalizePeriodeValue($parsed[0] ?? null);
            }
        } finally {
            fclose($handle);
        }

        return null;
    }

    private function countFilteredRows(string $path, array $context, array $activeFilters, array $selectedColumns): int
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Gagal membuka file CSV.');
        }

        $count = 0;
        $lineNumber = 0;
        try {
            while (($line = fgets($handle)) !== false) {
                $lineNumber++;
                if ($lineNumber <= $context['header_line']) {
                    continue;
                }

                $mapped = $this->mapCsvRow($context, $this->parseCsvLine($line, $context['delimiter']), $lineNumber);
                if ($mapped === null || !$this->passesFilters($mapped['row'], $activeFilters)) {
                    continue;
                }

                if ($this->buildInsertRow($context['headers'], $mapped['normalized_row'], $selectedColumns) !== null) {
                    $count++;
                }
            }
        } finally {
            fclose($handle);
        }

        return $count;
    }

    private function parseCsvLine(string $line, ?string $delimiter = null): array
    {
        return $this->smartParseCsvLine($line, $delimiter ?? self::COLUMN_DELIMITER, false);
    }

    private function mapCsvRow(array $context, array $data, int $lineNumber): ?array
    {
        if ($data === [] || $this->isEmptyCsvRow($data) || $this->isFooterRow($data)) {
            return null;
        }

        $data = $this->normalizeSourceRowShape($data);
        $previewRow = [];
        $normalizedRow = [];
        foreach ($context['headers'] as $column) {
            $sourceIndex = self::SOURCE_INDEX_MAP[$column] ?? null;
            $sourceValue = $sourceIndex !== null ? ($data[$sourceIndex] ?? null) : null;
            $previewRow[] = $this->formatPreviewCellValue($column, $sourceValue);
            $normalizedRow[] = $this->normalizeCellValue($column, $sourceValue);
        }

        return [
            'row' => $previewRow,
            'normalized_row' => $normalizedRow,
        ];
    }

    private function normalizeSourceRowShape(array $data): array
    {
        $expectedCount = 19;
        if (count($data) < $expectedCount) {
            return array_pad($data, $expectedCount, '');
        }

        if (count($data) > $expectedCount) {
            return array_slice($data, 0, $expectedCount);
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

    private function isFooterRow(array $row): bool
    {
        $row = $this->normalizeSourceRowShape($row);

        for ($i = 0; $i <= 17; $i++) {
            if (trim((string) ($row[$i] ?? '')) !== '') {
                return false;
            }
        }

        return trim((string) ($row[18] ?? '')) !== '';
    }

    private function normalizeCellValue(string $column, $value)
    {
        $value = trim($this->smartNormalizeQuotedCsvCellValue($value));
        if ($column === 'periode') {
            return $this->normalizePeriodeValue($value);
        }

        if (in_array($column, self::DECIMAL_COLUMNS, true)) {
            return $this->normalizeDecimalValue($value);
        }

        return $value === '' ? null : $value;
    }

    private function formatPreviewCellValue(string $column, $value)
    {
        $value = trim($this->smartNormalizeQuotedCsvCellValue($value));
        if ($value === '') {
            return null;
        }

        if ($column === 'periode') {
            return $this->normalizePeriodeValue($value);
        }

        return $value;
    }

    private function normalizePeriodeValue(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^(?<year>\d{4})(?<month>\d{2})$/', $value, $matches) !== 1) {
            return null;
        }

        $year = (int) $matches['year'];
        $month = (int) $matches['month'];
        if ($month < 1 || $month > 12) {
            return null;
        }

        return Carbon::create($year, $month, 1, 0, 0, 0)->endOfMonth()->toDateString();
    }

    private function normalizeDecimalValue($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            return $value . '.00';
        }

        if (is_float($value)) {
            return number_format((float) $value, 2, '.', '');
        }

        $value = trim($this->smartNormalizeQuotedCsvCellValue($value));
        if ($value === '' || $value === '-') {
            return null;
        }

        $value = preg_replace('/\s+/', '', $value);
        $value = preg_replace('/[^0-9,\.\-]/', '', $value);
        if ($value === '' || $value === '-') {
            return null;
        }

        $negative = str_starts_with($value, '-');
        $unsignedValue = ltrim($value, '-');
        if ($unsignedValue === '') {
            return null;
        }

        $hasComma = str_contains($unsignedValue, ',');
        $hasDot = str_contains($unsignedValue, '.');
        $decimalSeparator = null;

        if ($hasComma && $hasDot) {
            $decimalSeparator = strrpos($unsignedValue, ',') > strrpos($unsignedValue, '.') ? ',' : '.';
        } elseif ($hasComma) {
            $parts = explode(',', $unsignedValue);
            $lastPart = (string) end($parts);
            if (count($parts) === 2 && strlen($lastPart) > 0 && strlen($lastPart) <= 2) {
                $decimalSeparator = ',';
            }
        } elseif ($hasDot) {
            $parts = explode('.', $unsignedValue);
            $lastPart = (string) end($parts);
            if (count($parts) === 2 && strlen($lastPart) > 0 && strlen($lastPart) <= 2) {
                $decimalSeparator = '.';
            }
        }

        if ($decimalSeparator !== null) {
            [$intPart, $decimalPart] = explode($decimalSeparator, $unsignedValue, 2);
            $intPart = preg_replace('/[,.]/', '', $intPart);
            $decimalPart = preg_replace('/[,.]/', '', $decimalPart);
        } else {
            $intPart = preg_replace('/[,.]/', '', $unsignedValue);
            $decimalPart = '';
        }

        $intPart = preg_replace('/\D/', '', (string) $intPart);
        $decimalPart = preg_replace('/\D/', '', (string) $decimalPart);
        if ($intPart === '' && $decimalPart === '') {
            return null;
        }

        if ($intPart === '') {
            $intPart = '0';
        }

        if ($decimalPart === '') {
            $decimalPart = '00';
        } elseif (strlen($decimalPart) === 1) {
            $decimalPart .= '0';
        } elseif (strlen($decimalPart) > 2) {
            return number_format((float) (($negative ? '-' : '') . $intPart . '.' . $decimalPart), 2, '.', '');
        }

        $normalizedIntPart = ltrim($intPart, '0');
        if ($normalizedIntPart === '') {
            $normalizedIntPart = '0';
        }

        return ($negative ? '-' : '') . $normalizedIntPart . '.' . $decimalPart;
    }

    private function passesFilters(array $row, array $activeFilters): bool
    {
        foreach ($activeFilters as $colIndex => $allowedValues) {
            $value = trim((string) ($row[(int) $colIndex] ?? ''));
            $allowed = array_map(fn ($item) => trim((string) $item), (array) $allowedValues);
            if (!in_array($value, $allowed, true)) {
                return false;
            }
        }

        return true;
    }

    private function buildInsertRow(array $headers, array $row, array $selectedColumns, ?string $timestamp = null): ?array
    {
        $timestamp = $timestamp ?? now()->toDateTimeString();

        $insertRow = [
            'uniqueid_namareport' => (string) Str::uuid() . self::UNIQUE_SUFFIX,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
            'periode' => $row[array_search('periode', $headers, true)] ?? null,
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

        return $this->hasMeaningfulImportData($insertRow, ['uniqueid_namareport', 'created_at', 'updated_at', 'periode'])
            ? $insertRow
            : null;
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

    protected function resolveMappedPreviewSource(string $requestedPath, ?string $requestedDelimiter = null): array
    {
        [$relativePath] = $this->authorizeSessionImportStorageFile(
            $requestedPath,
            'cognos_ph_file',
            ['cognos_ph_imports'],
            ['csv', 'txt', 'xlsx', 'xls']
        );

        $workingPath = $this->resolveWorkingImportPath($relativePath);
        if (!file_exists($workingPath)) {
            throw new \RuntimeException('File staging ' . self::REPORT_LABEL . ' tidak ditemukan. Silakan upload ulang.');
        }

        return [$workingPath, $this->buildCsvContext($workingPath)];
    }

    protected function iterateMappedPreviewRows(string $path, array $context, callable $callback): void
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Gagal membuka file CSV ' . self::REPORT_LABEL . '.');
        }

        $lineNumber = 0;
        try {
            while (($line = fgets($handle)) !== false) {
                $lineNumber++;
                if ($lineNumber <= $context['header_line']) {
                    continue;
                }

                $mapped = $this->mapCsvRow(
                    $context,
                    $this->parseCsvLine($line, $context['delimiter']),
                    $lineNumber
                );
                if ($mapped === null) {
                    continue;
                }

                if ($callback($mapped['row']) === false) {
                    break;
                }
            }
        } finally {
            fclose($handle);
        }
    }

    protected function mappedPreviewCacheNamespace(): string
    {
        return 'cognos_ph';
    }

    protected function mappedPreviewLabel(): string
    {
        return self::REPORT_LABEL;
    }

    protected function mappedPreviewFilterableColumns(): ?array
    {
        return self::FILTERABLE_COLUMNS;
    }

    private function collectPreviewUniqueValues(string $path, array $context): array
    {
        [, $formattedUniqueValues] = $this->collectMappedPreviewSample(
            $path,
            $context,
            0,
            self::PREVIEW_SCAN_LIMIT,
            self::PREVIEW_UNIQUE_LIMIT
        );

        return $formattedUniqueValues;
    }

    private function createFilteredCsvStage(string $path, array $context, array $activeFilters, array $selectedColumns, int $totalRows, callable $send): array
    {
        $stagingPath = $this->createBulkLoadTempCsvPath((int) (microtime(true) * 1000));
        $outputHandle = fopen($stagingPath, 'w');
        if ($outputHandle === false) {
            throw new \RuntimeException('Gagal membuat file staging ' . self::REPORT_LABEL . '.');
        }

        $loadColumns = ['uniqueid_namareport', 'created_at', 'updated_at', 'periode'];
        foreach ($selectedColumns as $index) {
            $column = $context['headers'][$index] ?? null;
            if (!$column || in_array($column, ['id', 'uniqueid_namareport', 'periode'], true)) {
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

        $handle = fopen($path, 'r');
        if ($handle === false) {
            fclose($outputHandle);
            throw new \RuntimeException('Gagal membuka file sumber untuk staging ' . self::REPORT_LABEL . '.');
        }

        $lineNumber = 0;
        try {
            while (($line = fgets($handle)) !== false) {
                $lineNumber++;
                if ($lineNumber <= $context['header_line']) {
                    continue;
                }

                $sourceRowsScanned++;
                $mapped = $this->mapCsvRow($context, $this->parseCsvLine($line, $context['delimiter']), $lineNumber);
                if ($mapped === null || !$this->passesFilters($mapped['row'], $activeFilters)) {
                    continue;
                }

                $insertRow = $this->buildInsertRow($context['headers'], $mapped['normalized_row'], $selectedColumns, $timestamp);
                if ($insertRow === null) {
                    continue;
                }

                $stagedRow = [];
                foreach ($loadColumns as $column) {
                    $value = $insertRow[$column] ?? null;
                    $stagedRow[] = $value === null ? '\N' : (string) $value;
                }
                fputcsv($outputHandle, $stagedRow, self::BULK_STAGE_DELIMITER);
                $rowsDone++;

                if ($totalRows > 0 && ($sourceRowsScanned - $lastProgressAt) >= 500) {
                    $lastProgressAt = $sourceRowsScanned;
                    $elapsed = max(microtime(true) - $startTime, 0.001);
                    $speed = (int) ($sourceRowsScanned / $elapsed);
                    $percent = min(92, 10 + (int) (($sourceRowsScanned / max($totalRows, 1)) * 82));
                    $send('progress', [
                        'percent' => $percent,
                        'message' => "Menyusun CSV staging " . self::REPORT_LABEL . "... ({$speed} baris sumber/detik)",
                        'rows_done' => $sourceRowsScanned,
                        'total' => $totalRows,
                        'speed' => $speed,
                    ]);
                }
            }
        } finally {
            fclose($handle);
            fclose($outputHandle);
        }

        return [
            'path' => $stagingPath,
            'rows_done' => $rowsDone,
            'columns' => $loadColumns,
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
            while (($data = fgetcsv($handle, 0, self::BULK_STAGE_DELIMITER)) !== false) {
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
        $startTime = microtime(true);

        try {
            while (($data = fgetcsv($handle, 0, self::BULK_STAGE_DELIMITER)) !== false) {
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
                    $processedRows = $totalSuccess + $totalFailed;
                    $elapsed = max(microtime(true) - $startTime, 0.001);
                    $speed = (int) round($processedRows / $elapsed);
                    $percent = $totalRows > 0 ? min(99, 97 + (int) floor(($processedRows / max($totalRows, 1)) * 2)) : 99;
                    $send('progress', [
                        'percent' => $percent,
                        'message' => "Fallback insert " . self::REPORT_LABEL . "... ({$speed} baris/detik)",
                        'rows_done' => $processedRows,
                        'total' => $totalRows,
                        'speed' => $speed,
                    ]);
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

    private function insertBatch(array $rows, int &$totalSuccess, int &$totalFailed, string &$lastErrorMsg): void
    {
        foreach (array_chunk($rows, 500) as $batch) {
            try {
                DB::table(self::TABLE_NAME)->insert($batch);
                $totalSuccess += count($batch);
            } catch (\Throwable $e) {
                $lastErrorMsg = Str::limit($e->getMessage(), 800, '...');
                Log::warning('Import ' . self::REPORT_LABEL . ' batch insert failed: ' . $e->getMessage());

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
            $stageState = $this->getStagedExcelState($relativePath);
            $stagedCsvPath = (string) ($stageState['staged_csv_path'] ?? '');
            if ($stagedCsvPath !== '' && file_exists($stagedCsvPath)) {
                @unlink($stagedCsvPath);
            }
            $this->clearStagedExcelState($relativePath);
        } catch (\Throwable $e) {
            Log::warning('Gagal menghapus file sementara ' . self::REPORT_LABEL . ': ' . $e->getMessage());
        }
    }

    private function cleanupSuccessfulImportArtifacts(int $jobId, string $relativePath, ?string $periodHint = null, array $extraPaths = []): void
    {
        try {
            app(ImportCleanupService::class)->dispatchImportedJobSync($jobId, self::TABLE_NAME, $periodHint, static::class);
            app(ImportCleanupController::class)->cleanupSuccessfulJobArtifacts(
                $jobId,
                array_values(array_filter(array_merge([$relativePath], $extraPaths)))
            );
            $this->clearStagedExcelState($relativePath);
        } catch (\Throwable $e) {
            Log::warning('Gagal menjalankan cleanup terpusat ' . self::REPORT_LABEL . ': ' . $e->getMessage(), [
                'job_id' => $jobId,
                'relative_path' => $relativePath,
            ]);
        }
    }

    private function isValidReportSelection(): bool
    {
        $tableName = DB::table('nama_report')
            ->where('id_report', session('active_id_report'))
            ->value('table_name');

        return $tableName === self::TABLE_NAME;
    }
}
