<?php

namespace App\Services\Import;

use App\Http\Controllers\Import\ChunkReadFilter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ExcelQueuedImportService
{
    private const STREAM_PROGRESS_EVERY = 1000;

    public function execute(array $state, array $callbacks, ?callable $send = null): array
    {
        ini_set('memory_limit', '2048M');
        set_time_limit(0);
        DB::disableQueryLog();

        $send ??= static function (string $event, array $payload): void {
        };

        $params = (array) ($state['params'] ?? []);
        $normalizedHeaders = array_values((array) ($state['headers'] ?? []));
        $jobId = (int) ($state['job_id'] ?? ($params['job_id'] ?? 0));
        $headerIndex = (int) ($params['header_index'] ?? 0);
        $tableName = (string) ($params['table_name'] ?? 'daily_loan_dinamis');
        $activeFilters = (array) ($params['active_filters'] ?? []);
        $strategy = ($callbacks['resolve_import_strategy'])($tableName);
        $relativePath = (string) ($params['file_path'] ?? '');
        $stagedCsvPath = (string) ($params['staged_csv_path'] ?? '');
        $estimatedTotalRows = isset($params['total_rows']) ? (int) $params['total_rows'] : null;
        $csvDelimiter = isset($params['delimiter']) ? (string) $params['delimiter'] : null;

        $markFailed = $callbacks['mark_failed'];
        $findJob = $callbacks['find_job'];
        $updateJob = $callbacks['update_job'];

        $fail = function (string $message, int $success = 0, int $failed = 0) use ($jobId, $send, $markFailed): array {
            if ($jobId > 0) {
                $markFailed($jobId, $message, $success, $failed);
            }

            $send('error', [
                'message' => $message,
                'total_success' => $success,
                'total_failed' => $failed,
            ]);

            return [
                'status' => $success > 0 ? 'failed_partial' : 'failed',
                'total_success' => $success,
                'total_failed' => $failed,
            ];
        };

        $resolveCurrentResult = function (string $fallbackStatus = 'processing') use ($jobId, $findJob): array {
            $job = $jobId > 0 ? $findJob($jobId) : null;

            return [
                'status' => (string) ($job->status ?? $fallbackStatus),
                'total_success' => (int) ($job->total_success ?? 0),
                'total_failed' => (int) ($job->total_failed ?? 0),
                'total_rows' => (int) ($job->total_files ?? 0),
            ];
        };

        try {
            $path = Storage::path($relativePath);
            $workingPath = $path;
            $workingHeaderIndex = $headerIndex;
            $workingEstimatedTotalRows = $estimatedTotalRows;
            $workingCsvDelimiter = $csvDelimiter;
            $cleanupExtraPaths = [];
            $lastKeepAlive = time();
            $keepAliveEvery = 15;
            $ping = static function () use (&$lastKeepAlive): void {
                $lastKeepAlive = time();
            };

            if (!file_exists($path)) {
                return $fail('File Excel tidak ditemukan di server. Silakan upload ulang.');
            }

            if ($normalizedHeaders === []) {
                return $fail('Header session hilang. Silakan ulangi import dari awal.');
            }

            try {
                ($callbacks['assert_transactional_table'])($tableName, 'import Excel/CSV');
            } catch (\RuntimeException $e) {
                return $fail($e->getMessage());
            }

            if (!($callbacks['is_csv_file'])($path) && $stagedCsvPath !== '' && file_exists($stagedCsvPath)) {
                $workingPath = $stagedCsvPath;
                $workingHeaderIndex = 0;
                $workingCsvDelimiter = ',';
                $cleanupExtraPaths[] = $stagedCsvPath;
            }

            if (($callbacks['is_csv_file'])($workingPath)) {
                $delimiter = $workingCsvDelimiter !== null && $workingCsvDelimiter !== ''
                    ? $workingCsvDelimiter
                    : ($callbacks['detect_csv_delimiter'])($workingPath);
                $resolvedTotalRows = $workingEstimatedTotalRows;
                if ($resolvedTotalRows === null || $resolvedTotalRows <= 0) {
                    $resolvedTotalRows = ($callbacks['count_csv_data_rows'])($workingPath) + ($workingHeaderIndex + 1);
                }

                $totalDataRows = ($callbacks['resolve_csv_data_row_estimate'])($resolvedTotalRows, $workingHeaderIndex);

                $send('progress', [
                    'percent' => 10,
                    'message' => $workingPath === $path
                        ? "File CSV terdeteksi: {$totalDataRows} baris data. Memulai processing..."
                        : "Excel berhasil distage ke CSV: {$totalDataRows} baris data. Memulai bulk import...",
                    'rows_done' => 0,
                    'total' => $totalDataRows,
                    'speed' => 0,
                    'total_rows' => $totalDataRows,
                    'processed_rows' => 0,
                ]);

                $mode = $strategy->importMode([
                    'active_filters' => $activeFilters,
                    'table_name' => $tableName,
                    'path' => $workingPath,
                ]);

                $send('progress', [
                    'percent' => 12,
                    'message' => 'Mode strict aktif: filter CSV -> LOAD DATA LOCAL INFILE...',
                    'rows_done' => 0,
                    'total' => $totalDataRows,
                    'speed' => 0,
                    'total_rows' => $totalDataRows,
                    'processed_rows' => 0,
                ]);

                $send('progress', [
                    'percent' => 14,
                    'message' => $mode === 'bulk_csv_direct'
                        ? 'Menjalankan direct Daily Loan CSV import...'
                        : ($mode === 'bulk_csv_filtered'
                            ? 'Menyiapkan CSV staging terfilter untuk bulk load MySQL...'
                            : 'Fast-path native tidak dipakai. Menyiapkan CSV staging untuk bulk load MySQL...'),
                    'rows_done' => 0,
                    'total' => $totalDataRows,
                    'speed' => 0,
                    'total_rows' => $totalDataRows,
                    'processed_rows' => 0,
                ]);

                $csvPipeline = ($callbacks['run_csv_pipeline'])([
                    'mode' => $mode,
                    'direct_handler' => function () use (
                        $send,
                        $callbacks,
                        $workingPath,
                        $tableName,
                        $normalizedHeaders,
                        $jobId,
                        $totalDataRows,
                        $delimiter
                    ): array {
                        try {
                            return [
                                'handled' => ($callbacks['process_daily_loan_direct_csv_stream'])(
                                    $send,
                                    $workingPath,
                                    $tableName,
                                    $normalizedHeaders,
                                    $jobId,
                                    $totalDataRows,
                                    $delimiter
                                ),
                            ];
                        } catch (\Throwable $e) {
                            Log::warning('Fast-path Daily Loan CSV unavailable, fallback ke mode lama: ' . $e->getMessage(), [
                                'job_id' => $jobId,
                                'table_name' => $tableName,
                            ]);

                            return ['handled' => false];
                        }
                    },
                    'filtered_handler' => function () use (
                        $send,
                        $callbacks,
                        $workingPath,
                        $tableName,
                        $normalizedHeaders,
                        $activeFilters,
                        $jobId,
                        $totalDataRows,
                        $delimiter
                    ): array {
                        try {
                            return [
                                'handled' => ($callbacks['process_daily_loan_bulk_csv_stream'])(
                                    $send,
                                    $workingPath,
                                    $tableName,
                                    $normalizedHeaders,
                                    $activeFilters,
                                    $jobId,
                                    max(0, $totalDataRows),
                                    $delimiter
                                ),
                            ];
                        } catch (\Throwable $e) {
                            Log::warning('Filtered fast-path Daily Loan CSV unavailable, fallback ke mode lama: ' . $e->getMessage(), [
                                'job_id' => $jobId,
                                'table_name' => $tableName,
                            ]);

                            return ['handled' => false];
                        }
                    },
                    'staged_handler' => function () use (
                        $send,
                        $callbacks,
                        $workingPath,
                        $tableName,
                        $activeFilters,
                        $normalizedHeaders,
                        $jobId,
                        $resolvedTotalRows,
                        $totalDataRows,
                        $delimiter
                    ): array {
                        $forceDirectLoad = $tableName === 'simpanan_multipn';

                        return [
                            'handled' => ($callbacks['process_staged_csv_stream'])(
                                $send,
                                $workingPath,
                                $tableName,
                                $activeFilters,
                                $normalizedHeaders,
                                $jobId,
                                $resolvedTotalRows !== null ? $totalDataRows : null,
                                $delimiter,
                                $forceDirectLoad
                            ),
                        ];
                    },
                ]);

                if (($csvPipeline['handled'] ?? false) === true) {
                    $job = $jobId > 0 ? $findJob($jobId) : null;
                    if ($job && $job->status === 'completed') {
                        ($callbacks['cleanup_successful_import_artifacts'])($jobId, $relativePath, $path, $cleanupExtraPaths);
                    }

                    return $resolveCurrentResult();
                }

                return $fail('Gagal memproses CSV via LOCAL INFILE (mode strict, tanpa fallback).');
            }

            $send('progress', [
                'percent' => 3,
                'message' => 'Menyiapkan pandas -> CSV temp -> LOAD DATA LOCAL INFILE...',
                'rows_done' => 0,
                'total' => 0,
                'speed' => 0,
                'processed_rows' => 0,
            ]);

            $pythonHandled = ($callbacks['try_python_bulk_load'])(
                $send,
                $path,
                $headerIndex,
                $tableName,
                $activeFilters,
                $normalizedHeaders,
                $jobId
            );

            if ($pythonHandled) {
                $job = $jobId > 0 ? $findJob($jobId) : null;
                if ($job && $job->status === 'completed') {
                    ($callbacks['cleanup_successful_import_artifacts'])($jobId, $relativePath, $path, $stagedCsvPath !== '' ? [$stagedCsvPath] : []);
                    if ($stagedCsvPath !== '' && file_exists($stagedCsvPath)) {
                        @unlink($stagedCsvPath);
                    }
                }

                return $resolveCurrentResult();
            }

            $send('progress', [
                'percent' => 5,
                'message' => 'Bulk load native tidak dipakai. Fallback ke import Python/PHP lama...',
                'rows_done' => 0,
                'total' => 0,
                'speed' => 0,
                'processed_rows' => 0,
            ]);

            $pythonHandled = ($callbacks['try_python_gpu'])(
                $send,
                $path,
                $headerIndex,
                $tableName,
                $activeFilters,
                $normalizedHeaders,
                $jobId
            );

            if ($pythonHandled) {
                $job = $jobId > 0 ? $findJob($jobId) : null;
                if ($job && $job->status === 'completed') {
                    ($callbacks['cleanup_successful_import_artifacts'])($jobId, $relativePath, $path);
                }

                return $resolveCurrentResult();
            }

            $send('progress', [
                'percent' => 5,
                'message' => 'Mode PHP Chunked aktif (chunk adaptif, hemat memori)...',
                'rows_done' => 0,
                'total' => 0,
                'speed' => 0,
                'processed_rows' => 0,
            ]);

            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(false);

            $worksheetInfo = $reader->listWorksheetInfo($path);
            $totalRows = $worksheetInfo[0]['totalRows'] ?? 0;
            $totalDataRows = max(0, $totalRows - ($headerIndex + 1));

            $send('progress', [
                'percent' => 10,
                'message' => "File terdeteksi: {$totalDataRows} baris data. Memulai chunked processing...",
                'rows_done' => 0,
                'total' => $totalDataRows,
                'speed' => 0,
                'total_rows' => $totalDataRows,
                'processed_rows' => 0,
            ]);

            if ($jobId > 0) {
                $updateJob($jobId, [
                    'total_files' => $totalDataRows,
                ], [
                    'status' => 'processing',
                    'total_rows' => $totalDataRows,
                    'processed_rows' => 0,
                    'percent' => 10,
                    'message' => "File terdeteksi: {$totalDataRows} baris data. Memulai chunked processing...",
                ]);
            }

            $importContext = ($callbacks['build_import_context'])($tableName, $normalizedHeaders, $activeFilters);

            $send('progress', [
                'percent' => 15,
                'message' => "Mapping kolom selesai. Mulai insert ke tabel `{$tableName}`...",
                'rows_done' => 0,
                'total' => $totalDataRows,
                'speed' => 0,
                'total_rows' => $totalDataRows,
                'processed_rows' => 0,
            ]);

            $chunkFilter = new ChunkReadFilter();
            $chunkFilter->setHeaderRow($headerIndex + 1);

            $chunkSize = match (true) {
                $totalDataRows >= 250000 => 4000,
                $totalDataRows >= 100000 => 3000,
                $totalDataRows >= 25000 => 2000,
                $totalDataRows > 0 => 1000,
                default => 1000,
            };
            $chunkSize = max(500, min($chunkSize, 4000));
            $startExcelRow = $headerIndex + 2;

            $dataToInsert = [];
            $totalInserted = 0;
            $totalFailed = 0;
            $rowsDone = 0;
            $progressEvery = self::STREAM_PROGRESS_EVERY;
            $startTime = microtime(true);
            $lastProgressAt = 0;

            $flushBatch = function () use (&$dataToInsert, &$totalInserted, &$totalFailed, $tableName, $ping, $callbacks) {
                if (empty($dataToInsert)) {
                    return;
                }

                foreach (array_chunk($dataToInsert, ($callbacks['fallback_insert_batch_size'])()) as $batch) {
                    ($callbacks['insert_batch_with_fallback'])($batch, $tableName, $totalInserted, $totalFailed);
                    $ping();
                }

                $dataToInsert = [];
            };

            while ($startExcelRow <= $totalRows) {
                if ((time() - $lastKeepAlive) >= $keepAliveEvery) {
                    $ping();
                }

                $chunkFilter->setRows($startExcelRow, $chunkSize);
                $reader->setReadFilter($chunkFilter);

                $spreadsheet = $reader->load($path);
                $sheet = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
                $spreadsheet->disconnectWorksheets();
                $ping();
                unset($spreadsheet);
                gc_collect_cycles();

                $startArrayIdx = $startExcelRow - 1;
                $endArrayIdx = $startArrayIdx + $chunkSize;
                $timestamp = now()->toDateTimeString();

                foreach ($sheet as $rowIndex => $row) {
                    if ($rowIndex < $startArrayIdx || $rowIndex >= $endArrayIdx) {
                        continue;
                    }
                    if ($rowIndex <= $headerIndex) {
                        continue;
                    }
                    if (empty(array_filter((array) $row, fn($v) => trim((string) $v) !== ''))) {
                        continue;
                    }

                    $finalRow = ($callbacks['map_excel_row_for_insert'])($row, $normalizedHeaders, $importContext, $timestamp);
                    if ($finalRow === null) {
                        continue;
                    }

                    $dataToInsert[] = $finalRow;
                    $rowsDone++;

                    if (count($dataToInsert) >= 2000) {
                        $flushBatch();
                    }

                    if ($rowsDone - $lastProgressAt >= $progressEvery) {
                        $lastProgressAt = $rowsDone;
                        $elapsed = max(microtime(true) - $startTime, 0.001);
                        $speed = (int) ($rowsDone / $elapsed);
                        $pct = $totalDataRows > 0
                            ? min(92, 15 + (int) (($rowsDone / $totalDataRows) * 77))
                            : 50;

                        $send('progress', [
                            'percent' => $pct,
                            'message' => "Menyimpan data ke database... ({$speed} baris/detik)",
                            'rows_done' => $rowsDone,
                            'total' => $totalDataRows,
                            'speed' => $speed,
                            'processed_rows' => $rowsDone,
                            'total_rows' => $totalDataRows,
                            'total_success' => $totalInserted,
                            'total_failed' => $totalFailed,
                        ]);
                    } elseif ((time() - $lastKeepAlive) >= $keepAliveEvery) {
                        $ping();
                    }
                }

                $flushBatch();
                $startExcelRow += $chunkSize;
            }

            $send('progress', [
                'percent' => 96,
                'message' => 'Finalisasi dan menyimpan status import...',
                'rows_done' => $rowsDone,
                'total' => $totalDataRows,
                'speed' => 0,
                'processed_rows' => $rowsDone,
                'total_rows' => $totalDataRows,
                'total_success' => $totalInserted,
                'total_failed' => $totalFailed,
            ]);

            $finalStatus = $totalFailed > 0
                ? ($totalInserted > 0 ? 'failed_partial' : 'failed')
                : 'completed';

            if ($jobId > 0) {
                $updateJob($jobId, [
                    'total_success' => $totalInserted,
                    'total_failed' => $totalFailed,
                    'status' => $finalStatus,
                ], [
                    'status' => $finalStatus,
                    'percent' => 100,
                    'message' => $finalStatus === 'completed'
                        ? 'Import selesai diproses.'
                        : 'Import selesai dengan kegagalan parsial.',
                    'processed_rows' => $rowsDone,
                    'total_rows' => $totalDataRows,
                    'total_success' => $totalInserted,
                    'total_failed' => $totalFailed,
                ]);
            }

            if ($jobId > 0 && $totalInserted > 0 && $finalStatus !== 'completed') {
                try {
                    ($callbacks['cleanup_service_dispatch_imported_job_sync'])($jobId, $finalStatus);
                } catch (\Throwable $e) {
                    Log::warning('Failed to dispatch report snapshot sync after chunk import stream: ' . $e->getMessage(), [
                        'job_id' => $jobId,
                        'status' => $finalStatus,
                    ]);
                }
            }

            if ($finalStatus === 'completed') {
                ($callbacks['cleanup_successful_import_artifacts'])($jobId, $relativePath, $path, $stagedCsvPath !== '' ? [$stagedCsvPath] : []);
            }

            $payload = [
                'total_success' => $totalInserted,
                'total_failed' => $totalFailed,
                'total_rows' => $totalDataRows,
            ];
            $send($finalStatus === 'completed' ? 'complete' : 'error', $payload);

            return array_merge(['status' => $finalStatus], $payload);
        } catch (\Throwable $e) {
            Log::error('EXCEL QUEUED IMPORT ERROR: ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());

            return $fail('Fatal Error: ' . $e->getMessage() . ' (line ' . $e->getLine() . ')');
        }
    }
}
