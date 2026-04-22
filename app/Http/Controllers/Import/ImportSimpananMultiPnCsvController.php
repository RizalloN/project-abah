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

            $payload = $this->buildPreviewPayloadFromCsvFile($path);
            $headers = $payload['normalized_headers'] ?? [];
            $previewMeta = (array) session('excel_preview_meta', []);
            $dataRows = $this->countCsvPhysicalDataRows($path);
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
        $eligibility = $this->resolveDirectCsvFastPathEligibility('simpanan_multipn', $params, $normalizedHeaders);

        if (!($eligibility['eligible'] ?? false)) {
            $reason = (string) ($eligibility['reason'] ?? 'Fast import tidak tersedia.');
            Log::info('Simpanan MultiPN direct path not eligible; using queue-based fallback: ' . $reason, [
                'job_id' => $jobId,
                'table_name' => 'simpanan_multipn',
            ]);

            $relativePath = (string) ($eligibility['relative_path'] ?? '');
            $absolutePath = (string) ($eligibility['absolute_path'] ?? '');
            $totalRows = (int) ($eligibility['total_rows'] ?? 0);

            request()->session()->save();

            return response()->stream(function () use (
                $jobId,
                $relativePath,
                $absolutePath,
                $totalRows,
                $normalizedHeaders,
                $activeFilters,
                $reason
            ) {
                $streamLock = null;
                $send = function (string $event, array $data) use ($jobId) {
                    if ($jobId > 0 && $event === 'progress') {
                        $this->cacheFastImportProgress($jobId, array_merge([
                            'status' => 'processing',
                        ], $data, [
                            'total_rows' => (int) ($data['total_rows'] ?? $data['total'] ?? 0),
                            'processed_rows' => (int) ($data['processed_rows'] ?? $data['rows_done'] ?? 0),
                        ]));
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
                            if ($job && in_array($job->status, ['completed', 'failed', 'failed_partial'], true)) {
                                $send('complete', [
                                    'total_success' => (int) ($job->total_success ?? 0),
                                    'total_failed' => (int) ($job->total_failed ?? 0),
                                    'total_rows' => (int) ($job->total_files ?? 0),
                                ]);
                                return;
                            }

                            // If it's already processing, we "attach" to its progress by polling
                            $send('progress', [
                                'percent' => 5,
                                'message' => 'Job sedang diproses oleh worker. Menyambung ke progress...',
                            ]);

                            while (true) {
                                $currentJob = DB::table('import_jobs')->where('id', $jobId)->first();
                                if (!$currentJob) break;

                                $progress = $this->progressService()->getJobProgress($jobId);
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
                                            'message' => $currentJob->error_message ?: 'Import gagal diproses (status: ' . $currentJob->status . ')',
                                        ]);
                                    }
                                    return;
                                }

                                sleep(1);
                                if (connection_aborted()) return;
                            }
                            return;
                        }

                        $this->progressService()->markProcessing($jobId, [
                            'status' => 'processing',
                            'phase' => 'preparing_load_plan',
                            'percent' => 10,
                            'message' => 'Direct LOAD DATA tidak siap. Menyiapkan staging CSV Simpanan MultiPN...',
                            'processed_rows' => 0,
                            'total_rows' => $totalRows,
                        ]);
                    }

                    $send('progress', [
                        'status' => 'processing',
                        'phase' => 'preparing_load_plan',
                        'percent' => 10,
                        'message' => 'Direct LOAD DATA tidak siap. Menyiapkan staging CSV Simpanan MultiPN...',
                        'rows_done' => 0,
                        'total' => $totalRows,
                        'speed' => 0,
                    ]);

                    $handled = $this->processStagedCsvStream(
                        $send,
                        $absolutePath,
                        'simpanan_multipn',
                        $activeFilters,
                        $normalizedHeaders,
                        $jobId,
                        $totalRows,
                        null,
                        true
                    );

                    if ($handled) {
                        $job = $jobId > 0 ? DB::table('import_jobs')->where('id', $jobId)->first() : null;
                        if ($job && $job->status === 'completed') {
                            $this->cleanupSuccessfulImportArtifacts($jobId, $relativePath, $absolutePath);
                        }
                        return;
                    }

                    if ($jobId > 0) {
                        DB::table('import_jobs')->where('id', $jobId)->update([
                            'status' => 'failed',
                            'updated_at' => now(),
                        ]);
                    }

                    $send('error', [
                        'message' => 'Import Simpanan MultiPN gagal diproses: ' . $reason,
                    ]);
                } catch (\Throwable $e) {
                    Log::error('SIMPANAN MULTIPN STAGED DIRECT LOAD FALLBACK ERROR: ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());

                    if ($jobId > 0) {
                        DB::table('import_jobs')->where('id', $jobId)->update([
                            'status' => 'failed',
                            'updated_at' => now(),
                        ]);
                    }

                    $send('error', [
                        'message' => 'Fast import Simpanan MultiPN gagal: ' . $e->getMessage(),
                    ]);
                } finally {
                    if ($streamLock) {
                        try {
                            $streamLock->release();
                        } catch (\Throwable $e) {
                            Log::warning('Failed to release Simpanan MultiPN staged fallback lock for job ' . $jobId . ': ' . $e->getMessage());
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

        $selectedColumns = $this->resolveSelectedColumns($params, $normalizedHeaders);
        $relativePath = (string) ($eligibility['relative_path'] ?? '');
        $absolutePath = (string) ($eligibility['absolute_path'] ?? '');
        $totalRows = (int) ($eligibility['total_rows'] ?? 0);

        // If filters are present, use queue-based filtered import instead of direct LOAD DATA
        if (!empty($activeFilters)) {
            request()->session()->save();

            return response()->stream(function () use (
                $jobId,
                $absolutePath,
                $totalRows,
                $normalizedHeaders,
                $activeFilters
            ) {
                $send = function (string $event, array $data) use ($jobId) {
                    if ($jobId > 0 && $event === 'progress') {
                        $this->cacheFastImportProgress($jobId, array_merge([
                            'status' => 'processing',
                        ], $data, [
                            'total_rows' => (int) ($data['total_rows'] ?? $data['total'] ?? 0),
                            'processed_rows' => (int) ($data['processed_rows'] ?? $data['rows_done'] ?? 0),
                        ]));
                    }

                    echo "event: {$event}\n";
                    echo 'data: ' . json_encode($data) . "\n\n";
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                };

                try {
                    $send('progress', [
                        'status' => 'processing',
                        'phase' => 'preparing_load_plan',
                        'percent' => 5,
                        'message' => 'Menyiapkan filtered import Simpanan MultiPN melalui queue...',
                        'rows_done' => 0,
                        'total' => $totalRows,
                        'speed' => 0,
                    ]);

                    $handled = $this->processStagedCsvStream(
                        $send,
                        $absolutePath,
                        'simpanan_multipn',
                        $activeFilters,
                        $normalizedHeaders,
                        $jobId,
                        $totalRows,
                        null,
                        true
                    );

                    if ($handled) {
                        return;
                    }

                    if ($jobId > 0) {
                        DB::table('import_jobs')->where('id', $jobId)->update([
                            'status' => 'failed',
                            'updated_at' => now(),
                        ]);
                    }

                    $send('error', [
                        'message' => 'Filtered import Simpanan MultiPN gagal diproses.',
                    ]);
                } catch (\Throwable $e) {
                    Log::error('SIMPANAN MULTIPN FILTERED IMPORT ERROR: ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());

                    if ($jobId > 0) {
                        DB::table('import_jobs')->where('id', $jobId)->update([
                            'status' => 'failed',
                            'updated_at' => now(),
                        ]);
                    }

                    $send('error', [
                        'message' => 'Filtered import Simpanan MultiPN gagal: ' . $e->getMessage(),
                    ]);
                }
            }, 200, [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache, no-store',
                'X-Accel-Buffering' => 'no',
                'Connection' => 'keep-alive',
            ]);
        }

        request()->session()->save();

        return response()->stream(function () use ($jobId, $relativePath, $absolutePath, $totalRows, $normalizedHeaders, $selectedColumns, $activeFilters) {
            $streamLock = null;
            $cleanupPaths = [];
            $send = function (string $event, array $data) use ($jobId) {
                if ($jobId > 0 && $event === 'progress') {
                    $this->cacheFastImportProgress($jobId, array_merge([
                        'status' => 'processing',
                    ], $data, [
                        'total_rows' => (int) ($data['total_rows'] ?? $data['total'] ?? 0),
                        'processed_rows' => (int) ($data['processed_rows'] ?? $data['rows_done'] ?? 0),
                    ]));
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

                            $progress = $this->progressService()->getJobProgress($jobId);
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
                                        'message' => $currentJob->error_message ?: 'Import gagal diproses (status: ' . $currentJob->status . ')',
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

                if (!file_exists($absolutePath)) {
                    $send('error', ['message' => 'File CSV Simpanan MultiPN tidak ditemukan di server.']);
                    return;
                }

                try {
                    app(MySqlBulkLoadService::class)->assertTransactionalTable('simpanan_multipn', 'import CSV Simpanan MultiPN');
                } catch (\RuntimeException $e) {
                    if ($jobId > 0) {
                        DB::table('import_jobs')->where('id', $jobId)->update([
                            'status' => 'failed',
                            'updated_at' => now(),
                        ]);
                    }

                    $send('error', ['message' => $e->getMessage()]);
                    return;
                }

                $send('progress', [
                    'status' => 'processing',
                    'phase' => 'validating',
                    'percent' => 3,
                    'message' => 'Validasi file fast import Simpanan MultiPN...',
                    'rows_done' => 0,
                    'total' => $totalRows,
                    'speed' => 0,
                ]);

                try {
                    $loadPlan = $this->buildDirectCsvLoadPlan($absolutePath, $normalizedHeaders, $selectedColumns, $send);
                    if (!empty($loadPlan['cleanup_path']) && is_string($loadPlan['cleanup_path'])) {
                        $cleanupPaths[] = $loadPlan['cleanup_path'];
                    }
                } catch (\Throwable $e) {
                    Log::warning('Fast-path Simpanan MultiPN direct plan failed, trying staged direct LOAD DATA fallback: ' . $e->getMessage(), [
                        'job_id' => $jobId,
                        'absolute_path' => $absolutePath,
                    ]);

                    $send('progress', [
                        'status' => 'processing',
                        'phase' => 'preparing_load_plan',
                        'percent' => 8,
                        'message' => 'Direct plan gagal. Menyiapkan staging CSV lalu LOAD DATA LOCAL INFILE...',
                        'rows_done' => 0,
                        'total' => $totalRows,
                        'speed' => 0,
                    ]);

                    $handled = $this->processStagedCsvStream(
                        $send,
                        $absolutePath,
                        'simpanan_multipn',
                        $activeFilters,
                        $normalizedHeaders,
                        $jobId,
                        $totalRows,
                        null,
                        true
                    );

                    if ($handled) {
                        $job = $jobId > 0 ? DB::table('import_jobs')->where('id', $jobId)->first() : null;
                        if ($job && $job->status === 'completed') {
                            $this->cleanupSuccessfulImportArtifacts($jobId, $relativePath, $absolutePath);
                        }
                        return;
                    }

                    $send('error', [
                        'message' => 'Fast import CSV tidak bisa dilanjutkan: ' . $e->getMessage(),
                    ]);
                    return;
                }

                $send('progress', [
                    'status' => 'processing',
                    'phase' => 'preparing_load_plan',
                    'percent' => 8,
                    'message' => 'Menyiapkan direct LOAD DATA untuk Simpanan MultiPN...',
                    'rows_done' => 0,
                    'total' => $totalRows,
                    'speed' => 0,
                ]);

                $startTime = microtime(true);
                $beforeLoad = $this->buildSimpananMultiPnDirectLoadBeforeLoadCallback($loadPlan);
                $inserted = $this->executeDirectCsvLoad($loadPlan, $beforeLoad, $send, $jobId);
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
                    $this->cacheFastImportProgress($jobId, [
                        'status' => $failed > 0 ? ($inserted > 0 ? 'failed_partial' : 'failed') : 'completed',
                        'phase' => 'completed',
                        'percent' => 100,
                        'message' => $failed > 0 ? 'Fast import Simpanan MultiPN selesai dengan kegagalan parsial.' : 'Fast import Simpanan MultiPN selesai.',
                        'processed_rows' => $inserted + $failed,
                        'total_rows' => $totalRows > 0 ? $totalRows : $inserted,
                        'total_success' => $inserted,
                        'total_failed' => $failed,
                    ]);
                }

                $send('progress', [
                    'status' => 'processing',
                    'phase' => 'syncing_report',
                    'percent' => 98,
                    'message' => 'LOAD DATA selesai. Menyusun snapshot akhir Simpanan MultiPN...',
                    'rows_done' => $inserted,
                    'total' => $totalRows > 0 ? $totalRows : $inserted,
                    'speed' => 0,
                ]);

                $this->cleanupSuccessfulImportArtifacts(
                    $jobId,
                    $relativePath,
                    $absolutePath,
                    (array) ($loadPlan['period_hints'] ?? []),
                    importBatchTimestamp: (string) ($loadPlan['import_batch_timestamp'] ?? '')
                );

                $send('complete', [
                    'total_success' => $inserted,
                    'total_failed' => $failed,
                    'total_rows' => $totalRows > 0 ? $totalRows : $inserted,
                ]);
            } catch (\Throwable $e) {
                Log::warning('Simpanan MultiPN direct path failed, trying staged direct LOAD DATA fallback: ' . $e->getMessage(), [
                    'job_id' => $jobId,
                    'absolute_path' => $absolutePath,
                ]);

                try {
                    $send('progress', [
                        'status' => 'processing',
                        'phase' => 'preparing_load_plan',
                        'percent' => 10,
                        'message' => 'Direct path gagal. Menyiapkan staging CSV lalu LOAD DATA LOCAL INFILE...',
                        'rows_done' => 0,
                        'total' => $totalRows,
                        'speed' => 0,
                    ]);

                    $handled = $this->processStagedCsvStream(
                        $send,
                        $absolutePath,
                        'simpanan_multipn',
                        $activeFilters,
                        $normalizedHeaders,
                        $jobId,
                        $totalRows,
                        null,
                        true
                    );

                    if ($handled) {
                        $job = $jobId > 0 ? DB::table('import_jobs')->where('id', $jobId)->first() : null;
                        if ($job && $job->status === 'completed') {
                $this->cleanupSuccessfulImportArtifacts($jobId, $relativePath, $absolutePath);
                        }
                        return;
                    }
                } catch (\Throwable $fallbackException) {
                Log::error('SIMPANAN MULTIPN STAGED DIRECT LOAD FALLBACK ERROR: ' . $fallbackException->getMessage() . ' | ' . $fallbackException->getFile() . ':' . $fallbackException->getLine());
            }

                Log::error('SIMPANAN MULTIPN DIRECT CSV LOAD ERROR: ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());

                if ($jobId > 0) {
                    $this->progressService()->markFailed($jobId, 'Fast import CSV gagal: ' . $e->getMessage());
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

    private function buildDirectCsvLoadPlan(string $absolutePath, array $normalizedHeaders, array $selectedColumns, ?callable $send = null): array
    {
        $delimiter = $this->detectCsvDelimiter($absolutePath);
        $loadSource = $this->prepareSimpananMultiPnDirectLoadSource($absolutePath, $delimiter, $send);
        $sourcePath = (string) ($loadSource['path'] ?? $absolutePath);
        $cleanupPath = !empty($loadSource['cleanup']) ? $sourcePath : null;
        $sourceRows = (int) ($loadSource['written_rows'] ?? 0);
        $balanceCrosscheckMaxRows = max(0, (int) config('import.direct_load.simpanan_multipn.balance_crosscheck_max_rows', 100000));
        $sourceBalanceTotalCents = ($balanceCrosscheckMaxRows === 0 || $sourceRows <= $balanceCrosscheckMaxRows)
            ? $this->calculateSimpananMultiPnSourceBalanceTotal($sourcePath, $delimiter)
            : null;

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
                'posisi' => "`{$dbColumn}` = " . StrictDateParser::buildMySqlCaseExpression("NULLIF(TRIM({$variable}), '')"),
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
            'validation_backend' => (string) ($loadSource['backend'] ?? 'php'),
            'validation_skipped_count' => (int) ($loadSource['skipped_count'] ?? 0),
            'validation_duplicate_count' => (int) ($loadSource['duplicate_count'] ?? 0),
            'validation_written_rows' => (int) ($loadSource['written_rows'] ?? 0),
            'import_batch_token' => $importBatchToken,
            'unique_id_column' => $uniqueIdColumn,
            'period_hints' => $this->collectSimpananMultiPnSnapshotPeriods($sourcePath),
        ];

        if ($sourceBalanceTotalCents !== null) {
            $plan['source_balance_total_cents'] = $sourceBalanceTotalCents;
        }

        return $plan;
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
            $affected = $pdo->exec($sql);
        } finally {
            try {
                $pdo->exec('SET @skip_snapshot_invalidation = NULL');
            } catch (\Throwable) {
                // abaikan reset session variable bila koneksi sudah gagal
            }
        }

        if ($affected === false) {
            throw new \RuntimeException('LOAD DATA LOCAL INFILE gagal dieksekusi untuk Simpanan MultiPN.');
        }

        return (int) $affected;
    }

    private function buildSimpananMultiPnDirectLoadBeforeLoadCallback(array $loadPlan): ?callable
    {
        $periodHints = array_values(array_unique(array_filter(array_map(
            static fn ($value): string => trim((string) $value),
            (array) ($loadPlan['period_hints'] ?? [])
        ), static fn (string $value): bool => $value !== '')));

        if ($periodHints === []) {
            return null;
        }

        return function (\PDO $pdo) use ($periodHints): void {
            $this->deleteExistingSimpananMultiPnPeriods($pdo, $periodHints);
        };
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
        $reason = strtolower(trim($reason));

        if ($reason === '') {
            return true;
        }

        if (str_contains($reason, 'local infile')) {
            return false;
        }

        if (str_contains($reason, 'header import tidak tersedia')) {
            return false;
        }

        if (str_contains($reason, 'file sumber import') || str_contains($reason, 'file csv') || str_contains($reason, 'file tidak ditemukan')) {
            return false;
        }

        return true;
    }

    private function configureLongRunningImportRuntime(): void
    {
        @ini_set('memory_limit', '1024M');
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);
    }

    private function collectSimpananMultiPnSnapshotPeriods(string $absolutePath): array
    {
        if ($absolutePath === '' || !file_exists($absolutePath)) {
            return [];
        }

        $periods = $this->collectCsvNormalizedValuesForHeaders($absolutePath, ['POSISI']);
        $periods = array_values(array_unique(array_filter(array_map(
            static fn ($value) => trim((string) $value),
            $periods
        ), static fn (string $value): bool => $value !== '')));

        sort($periods);

        return $periods;
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
            // OPTIMIZATION: Try CSV reading first to avoid a full table scan on created_at
            if ($absolutePath !== null && file_exists($absolutePath)) {
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
}
