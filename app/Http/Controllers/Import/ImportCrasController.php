<?php

namespace App\Http\Controllers\Import;

use App\Http\Controllers\Controller;
use App\Models\NamaReport;
use App\Services\Import\CrasSourceService;
use App\Services\Import\ImportExecutionService;
use App\Services\Import\ImportProgressService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportCrasController extends Controller
{
    private const CHUNK_SIZE_BYTES = 8 * 1024 * 1024;
    private const UPLOAD_DIRECTORY = 'cras_uploads';
    private const CHUNK_DIRECTORY = 'app/chunk_uploads_cras';
    private const PREVIEW_CACHE_HOURS = 6;

    public function __construct(
        private readonly CrasSourceService $sourceService,
        private readonly ImportProgressService $progressService,
        private readonly ImportExecutionService $executionService,
    ) {
    }

    public function upload(Request $request)
    {
        $report = $this->resolveReport((int) $request->input('id_report'));
        if (!$report) {
            return response()->json(['status' => 'error', 'message' => 'Report SSA CRAS tidak ditemukan.'], 422);
        }

        $request->validate(['file' => 'required|file']);
        $file = $request->file('file');
        $extension = strtolower((string) $file?->getClientOriginalExtension());
        if (!$file || !$file->isValid() || !in_array($extension, ['csv', 'txt', 'xlsx'], true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'File CRAS harus berformat CSV, TXT, atau XLSX.',
            ], 422);
        }

        Storage::makeDirectory(self::UPLOAD_DIRECTORY);
        $relativePath = $file->storeAs(
            self::UPLOAD_DIRECTORY,
            $this->buildStoredFileName($file->getClientOriginalName(), $extension)
        );
        $this->rememberUploadSession((int) $report->id_report, $relativePath);

        return response()->json([
            'status' => 'success',
            'redirect' => route('import.cras.prepare-preview'),
        ]);
    }

    public function initChunkUpload(Request $request)
    {
        $request->validate([
            'original_name' => 'required|string|max:255',
            'total_size' => 'required|integer|min:1',
            'total_chunks' => 'required|integer|min:1',
        ]);

        $report = $this->resolveReport();
        if (!$report) {
            return response()->json(['status' => 'error', 'message' => 'Report SSA CRAS tidak ditemukan.'], 422);
        }

        $originalName = (string) $request->input('original_name');
        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($extension, ['csv', 'txt', 'xlsx'], true)) {
            return response()->json(['status' => 'error', 'message' => 'File CRAS harus CSV, TXT, atau XLSX.'], 422);
        }

        $totalSize = (int) $request->input('total_size');
        $totalChunks = (int) $request->input('total_chunks');
        $expectedChunks = max(1, (int) ceil($totalSize / self::CHUNK_SIZE_BYTES));
        if ($totalChunks !== $expectedChunks) {
            return response()->json(['status' => 'error', 'message' => 'Jumlah potongan upload tidak sesuai.'], 422);
        }

        $uploadId = 'cras_' . Str::uuid();
        $directory = $this->chunkDirectory($uploadId);
        File::ensureDirectoryExists($directory);
        File::put($directory . DIRECTORY_SEPARATOR . 'meta.json', json_encode([
            'original_name' => $originalName,
            'total_size' => $totalSize,
            'total_chunks' => $totalChunks,
            'user_id' => auth()->id(),
            'report_id' => (int) $report->id_report,
            'created_at' => now()->toIso8601String(),
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

        return response()->json(['status' => 'success', 'upload_id' => $uploadId]);
    }

    public function uploadChunk(Request $request)
    {
        $request->validate([
            'upload_id' => ['required', 'string', 'regex:/^cras_[0-9a-f-]{36}$/'],
            'chunk_index' => 'required|integer|min:0',
            'total_chunks' => 'required|integer|min:1',
            'file' => 'required|file',
        ]);

        $uploadId = (string) $request->input('upload_id');
        $directory = $this->chunkDirectory($uploadId);
        $meta = $this->readChunkMeta($directory);
        if (!$meta || !$this->ownsChunkUpload($meta)) {
            return response()->json(['status' => 'error', 'message' => 'Sesi upload CRAS tidak valid.'], 404);
        }

        $chunkIndex = (int) $request->input('chunk_index');
        $totalChunks = (int) $request->input('total_chunks');
        if ($totalChunks !== (int) ($meta['total_chunks'] ?? 0) || $chunkIndex >= $totalChunks) {
            return response()->json(['status' => 'error', 'message' => 'Urutan potongan CRAS tidak valid.'], 422);
        }

        $chunk = $request->file('file');
        if (!$chunk || !$chunk->isValid() || (int) $chunk->getSize() > self::CHUNK_SIZE_BYTES) {
            return response()->json(['status' => 'error', 'message' => 'Potongan file CRAS tidak valid.'], 422);
        }

        $name = sprintf('part_%06d.bin', $chunkIndex);
        $target = $directory . DIRECTORY_SEPARATOR . $name;
        if (is_file($target)) {
            File::delete($target);
        }
        $chunk->move($directory, $name);

        return response()->json(['status' => 'success', 'chunk_index' => $chunkIndex]);
    }

    public function finalizeChunkUpload(Request $request)
    {
        $request->validate([
            'upload_id' => ['required', 'string', 'regex:/^cras_[0-9a-f-]{36}$/'],
            'total_chunks' => 'required|integer|min:1',
            'original_name' => 'required|string|max:255',
        ]);

        $uploadId = (string) $request->input('upload_id');
        $directory = $this->chunkDirectory($uploadId);
        $meta = $this->readChunkMeta($directory);
        if (!$meta || !$this->ownsChunkUpload($meta)) {
            return response()->json(['status' => 'error', 'message' => 'Sesi upload CRAS tidak valid.'], 404);
        }

        $totalChunks = (int) $request->input('total_chunks');
        $originalName = (string) ($meta['original_name'] ?? '');
        if (
            $totalChunks !== (int) ($meta['total_chunks'] ?? 0)
            || $originalName !== (string) $request->input('original_name')
        ) {
            return response()->json(['status' => 'error', 'message' => 'Metadata upload CRAS tidak sesuai.'], 422);
        }

        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        Storage::makeDirectory(self::UPLOAD_DIRECTORY);
        $relativePath = self::UPLOAD_DIRECTORY . '/' . $this->buildStoredFileName($originalName, $extension);
        $absolutePath = Storage::path($relativePath);
        $output = fopen($absolutePath, 'wb');
        if ($output === false) {
            return response()->json(['status' => 'error', 'message' => 'File final CRAS tidak dapat dibuat.'], 500);
        }

        try {
            for ($index = 0; $index < $totalChunks; $index++) {
                $partPath = $directory . DIRECTORY_SEPARATOR . sprintf('part_%06d.bin', $index);
                $input = is_file($partPath) ? fopen($partPath, 'rb') : false;
                if ($input === false) {
                    throw new \RuntimeException('Potongan file CRAS ke-' . ($index + 1) . ' belum lengkap.');
                }
                stream_copy_to_stream($input, $output);
                fclose($input);
            }
        } catch (\Throwable $exception) {
            fclose($output);
            File::delete($absolutePath);
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()], 422);
        }
        fclose($output);

        if ((int) @filesize($absolutePath) !== (int) ($meta['total_size'] ?? 0)) {
            File::delete($absolutePath);
            return response()->json(['status' => 'error', 'message' => 'Ukuran file CRAS hasil gabungan tidak sesuai.'], 422);
        }

        File::deleteDirectory($directory);
        $this->rememberUploadSession((int) ($meta['report_id'] ?? 0), $relativePath);

        return response()->json([
            'status' => 'success',
            'redirect' => route('import.cras.prepare-preview'),
        ]);
    }

    public function preparePreviewStream(Request $request)
    {
        @set_time_limit(0);
        $relativePath = (string) session('cras_file', '');
        request()->session()->save();

        return response()->stream(function () use ($relativePath): void {
            $send = static function (string $event, array $payload): void {
                echo "event: {$event}\n";
                echo 'data: ' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            };

            try {
                $absolutePath = $this->resolveUploadedPath($relativePath);
                $sourceType = strtolower((string) pathinfo($absolutePath, PATHINFO_EXTENSION)) === 'xlsx'
                    ? 'XLSX'
                    : 'UTF-16LE';
                $send('progress', [
                    'percent' => 2,
                    'message' => "Membaca {$sourceType} dan memvalidasi 33 kolom CRAS...",
                ]);
                $state = $this->sourceService->inspect($absolutePath, function (array $progress) use ($send): void {
                    $send('progress', [
                        'percent' => (int) ($progress['percent'] ?? 5),
                        'message' => (string) ($progress['message'] ?? 'Memvalidasi CRAS...'),
                        'rows_done' => (int) ($progress['rows_done'] ?? 0),
                    ]);
                });
                $stateKey = $this->storePreviewState($relativePath, $state);
                session(['cras_preview_state_key' => $stateKey]);
                request()->session()->save();

                $send('ready', [
                    'redirect' => route('import.cras.preview', ['preview_state_key' => $stateKey]),
                ]);
            } catch (\Throwable $exception) {
                Log::error('CRAS preview preparation failed.', [
                    'file' => $relativePath,
                    'message' => $exception->getMessage(),
                ]);
                $send('error_msg', ['message' => $exception->getMessage()]);
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
        $relativePath = (string) session('cras_file', '');
        if ($relativePath === '') {
            return redirect()->route('import.index')->with('error', 'File CRAS tidak ditemukan. Silakan upload ulang.');
        }

        try {
            $this->resolveUploadedPath($relativePath);
            [$stateKey, $state] = $this->resolvePreviewState(
                $relativePath,
                (string) $request->input('preview_state_key', session('cras_preview_state_key', ''))
            );
        } catch (\Throwable $exception) {
            return redirect()->route('import.index')->with('error', $exception->getMessage());
        }

        return view('import.preview', [
            'pageTitle' => 'Preview SSA CRAS',
            'previewBannerTitle' => 'SSA CRAS',
            'filePath' => $relativePath,
            'headers' => CrasSourceService::SOURCE_HEADERS,
            'previewData' => (array) ($state['preview_rows'] ?? []),
            'formattedUniqueValues' => [CrasSourceService::BRANCH_INDEX => (array) ($state['branch_values'] ?? [])],
            'filterableColumnIndices' => [CrasSourceService::BRANCH_INDEX],
            'currentDelimiter' => "\t",
            'hideDelimiterCard' => true,
            'previewStateKey' => $stateKey,
            'processRoute' => route('import.cras.init'),
            'initRoute' => route('import.cras.init'),
            'streamRoute' => route('import.cras.stream'),
            'filterOptionsRoute' => route('import.cras.filter-options'),
            'filteredRowsRoute' => route('import.cras.filtered-rows'),
            'warmIndexRoute' => '',
            'initialFilterOptionsAreComplete' => true,
            'disableFilterOptionsLocalCache' => true,
            'deferDependentFilterRefresh' => true,
            'portalFilterDropdowns' => true,
            'lockColumnSelection' => true,
            'preserveFilterValueWhitespace' => true,
            'detectedPosisi' => (string) ($state['period'] ?? ''),
            'backRoute' => route('import.index'),
        ]);
    }

    public function previewFilterOptions(Request $request)
    {
        try {
            $relativePath = (string) $request->query('file_path', session('cras_file', ''));
            [, $state] = $this->resolvePreviewState($relativePath, (string) $request->query('preview_state_key', ''));
            $column = (int) $request->query('column_index', -1);

            return response()->json([
                'status' => 'success',
                'values' => $column === CrasSourceService::BRANCH_INDEX
                    ? array_values((array) ($state['branch_values'] ?? []))
                    : [],
            ]);
        } catch (\Throwable $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()], 422);
        }
    }

    public function previewFilteredRows(Request $request)
    {
        try {
            $relativePath = (string) $request->query('file_path', session('cras_file', ''));
            [, $state] = $this->resolvePreviewState($relativePath, (string) $request->query('preview_state_key', ''));
            $filters = json_decode((string) $request->query('active_filters_json', '{}'), true);
            $filters = is_array($filters) ? $filters : [];
            $selectedBranches = $filters[CrasSourceService::BRANCH_INDEX]
                ?? $filters[(string) CrasSourceService::BRANCH_INDEX]
                ?? (array) ($state['branch_values'] ?? []);
            $selectedBranches = is_array($selectedBranches) ? array_map('strval', $selectedBranches) : [];

            $rows = [];
            $totalMatched = 0;
            foreach ($selectedBranches as $branch) {
                $totalMatched += (int) (($state['branch_counts'][$branch] ?? 0));
                foreach ((array) ($state['branch_samples'][$branch] ?? []) as $sample) {
                    $rows[] = $sample;
                }
            }
            usort($rows, static fn (array $left, array $right): int => (int) ($left['source_row'] ?? 0) <=> (int) ($right['source_row'] ?? 0));
            $rows = array_slice($rows, 0, 100);

            return response()->json([
                'status' => 'success',
                'rows' => array_values(array_map(static fn (array $sample): array => (array) ($sample['row'] ?? []), $rows)),
                'total_matched' => $totalMatched,
                'truncated' => $totalMatched > count($rows),
            ]);
        } catch (\Throwable $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()], 422);
        }
    }

    public function initImport(Request $request)
    {
        $request->validate([
            'file_path' => 'required|string',
            'active_filters_json' => 'nullable|string',
            'preview_state_key' => 'required|string',
        ]);

        try {
            $relativePath = (string) $request->input('file_path');
            $absolutePath = $this->resolveUploadedPath($relativePath);
            [, $state] = $this->resolvePreviewState($relativePath, (string) $request->input('preview_state_key'));
            $selectedBranches = $this->resolveSelectedBranches(
                (string) $request->input('active_filters_json', '{}'),
                (array) ($state['branch_values'] ?? [])
            );
            if ($selectedBranches === []) {
                throw new \RuntimeException('Pilih minimal satu cabang untuk import CRAS.');
            }

            $totalRows = array_sum(array_map(
                static fn (string $branch): int => (int) ($state['branch_counts'][$branch] ?? 0),
                $selectedBranches
            ));
            if ($totalRows <= 0) {
                throw new \RuntimeException('Tidak ada baris CRAS yang sesuai dengan filter cabang.');
            }

            $period = (string) ($state['period'] ?? '');
            $overlap = DB::table(CrasSourceService::TABLE)
                ->where('cras_periode', $period)
                ->whereIn('ket_kanca', $selectedBranches)
                ->value('ket_kanca');
            if ($overlap !== null) {
                throw new \RuntimeException(
                    "Data CRAS periode {$period} untuk cabang `{$overlap}` sudah ada di database."
                );
            }

            $report = $this->resolveReport((int) session('active_id_report', 0));
            if (!$report) {
                throw new \RuntimeException('Konfigurasi report SSA CRAS tidak ditemukan.');
            }

            $uuidPrefix = bin2hex(random_bytes(10));
            $jobId = $this->progressService->createJob([
                'id_report' => (int) $report->id_report,
                'file_name' => basename($relativePath),
                'folder_path' => dirname($absolutePath),
                'status' => 'uploaded',
                'total_files' => $totalRows,
                'total_success' => 0,
                'total_failed' => 0,
                'created_by' => auth()->id() ?? 1,
                'job_context' => [
                    'controller' => static::class,
                    'mode' => 'cras_exact_import',
                    'table_name' => CrasSourceService::TABLE,
                    'file_path' => $relativePath,
                    'total_rows' => $totalRows,
                ],
            ]);

            $params = [
                'job_id' => $jobId,
                'controller' => static::class,
                'table_name' => CrasSourceService::TABLE,
                'file_path' => $relativePath,
                'selected_branches' => $selectedBranches,
                'period' => $period,
                'total_rows' => $totalRows,
                'uuid_prefix' => $uuidPrefix,
                'header_index' => 0,
                'preview_state_key' => (string) $request->input('preview_state_key'),
            ];
            $this->progressService->cacheJobState($jobId, [
                'params' => $params,
                'headers' => CrasSourceService::SOURCE_HEADERS,
            ]);
            $this->executionService->dispatch($jobId, 'Import CRAS masuk ke antrean prioritas.');

            return response()->json([
                'status' => 'success',
                'job_id' => $jobId,
                'total_rows' => $totalRows,
            ]);
        } catch (\Throwable $exception) {
            return response()->json([
                'status' => 'error',
                'title' => str_contains(strtolower($exception->getMessage()), 'sudah ada')
                    ? 'Data Ditolak (Duplikat)!'
                    : 'Import CRAS Dibatalkan',
                'message' => $exception->getMessage(),
                'text' => $exception->getMessage(),
                'duplicate_detected' => str_contains(strtolower($exception->getMessage()), 'sudah ada'),
            ], 422);
        }
    }

    public function processImportStream(Request $request)
    {
        $jobId = (int) $request->query('job_id', 0);
        if ($jobId <= 0) {
            return response()->stream(function (): void {
                echo "event: error\n";
                echo 'data: ' . json_encode(['message' => 'Job CRAS tidak ditemukan.']) . "\n\n";
            }, 200, ['Content-Type' => 'text/event-stream']);
        }

        $this->executionService->dispatch($jobId);

        return $this->executionService->streamStatus($request, $jobId, false);
    }

    public function initializeQueuedImportJobForExecution(int $jobId): bool
    {
        $state = $this->progressService->getJobState($jobId);

        return !empty($state['params']) && !empty($state['headers']);
    }

    public function executeQueuedImport(array $state, ?callable $send = null): array
    {
        $params = (array) ($state['params'] ?? []);
        $jobId = (int) ($params['job_id'] ?? 0);
        $relativePath = (string) ($params['file_path'] ?? '');
        $period = (string) ($params['period'] ?? '');
        $branches = array_values(array_map('strval', (array) ($params['selected_branches'] ?? [])));
        $totalRows = (int) ($params['total_rows'] ?? 0);
        $uuidPrefix = (string) ($params['uuid_prefix'] ?? '');
        $previewStateKey = (string) ($params['preview_state_key'] ?? '');
        $absolutePath = $this->resolveUploadedPath($relativePath);
        $sourceType = strtolower((string) pathinfo($absolutePath, PATHINFO_EXTENSION)) === 'xlsx'
            ? 'XLSX'
            : 'UTF-16LE';
        $stagingDirectory = storage_path('app/import_bulk');
        File::ensureDirectoryExists($stagingDirectory);
        $stagedPath = $stagingDirectory . DIRECTORY_SEPARATOR . "cras_{$jobId}_" . Str::random(8) . '.csv';
        $loadedRows = 0;

        $emit = static function (string $event, array $payload) use ($send): void {
            if ($send !== null) {
                $send($event, $payload);
            }
        };

        try {
            $startedAt = microtime(true);
            $emit('progress', [
                'percent' => 10,
                'phase' => 'exact_staging',
                'mode' => 'cras_exact',
                'message' => "Membaca {$sourceType} tanpa mengubah nilai sumber...",
                'processed_rows' => 0,
                'total_rows' => $totalRows,
                'rollback_metadata' => [
                    'table_name' => CrasSourceService::TABLE,
                    'unique_id_col' => 'cras_uuid',
                    'unique_id_prefix' => $uuidPrefix,
                ],
            ]);

            $stage = $this->sourceService->stageForImport(
                $absolutePath,
                $stagedPath,
                $branches,
                $period,
                function (array $progress) use ($emit, $totalRows, $startedAt, $uuidPrefix): void {
                    $rowsDone = (int) ($progress['rows_done'] ?? 0);
                    $elapsed = max(0.001, microtime(true) - $startedAt);
                    $percent = $totalRows > 0
                        ? min(68, 10 + (int) floor(($rowsDone / $totalRows) * 58))
                        : 10;
                    $emit('progress', [
                        'percent' => $percent,
                        'phase' => 'exact_staging',
                        'mode' => 'cras_exact',
                        'message' => (string) ($progress['message'] ?? 'Menyiapkan staging CRAS...'),
                        'processed_rows' => min($rowsDone, $totalRows),
                        'total_rows' => $totalRows,
                        'speed' => (int) round($rowsDone / $elapsed),
                        'speed_label' => 'baris/detik',
                        'rollback_metadata' => [
                            'table_name' => CrasSourceService::TABLE,
                            'unique_id_col' => 'cras_uuid',
                            'unique_id_prefix' => $uuidPrefix,
                        ],
                    ]);
                }
            );

            $stagedRows = (int) ($stage['imported_rows'] ?? 0);
            if ($stagedRows !== $totalRows) {
                throw new \RuntimeException(
                    "Jumlah staging CRAS berubah: {$stagedRows} dari {$totalRows} baris yang dipilih."
                );
            }

            $emit('progress', [
                'percent' => 72,
                'phase' => 'direct_load',
                'mode' => 'direct_load',
                'message' => 'Staging valid. Menjalankan transaksi LOAD DATA MySQL...',
                'processed_rows' => $stagedRows,
                'total_rows' => $totalRows,
                'rollback_metadata' => [
                    'table_name' => CrasSourceService::TABLE,
                    'unique_id_col' => 'cras_uuid',
                    'unique_id_prefix' => $uuidPrefix,
                ],
            ]);

            $loadedRows = $this->sourceService->loadStagedCsv(
                $stagedPath,
                $period,
                $branches,
                $uuidPrefix,
                $stagedRows
            );

            $emit('progress', [
                'percent' => 99,
                'phase' => 'verifying',
                'mode' => 'direct_load',
                'message' => 'Memverifikasi jumlah baris dan UUID CRAS...',
                'processed_rows' => $loadedRows,
                'total_rows' => $totalRows,
                'rollback_metadata' => [
                    'table_name' => CrasSourceService::TABLE,
                    'unique_id_col' => 'cras_uuid',
                    'unique_id_prefix' => $uuidPrefix,
                ],
            ]);

            File::delete($absolutePath);
            if ($previewStateKey !== '') {
                Cache::forget($this->previewCacheKey($previewStateKey));
            }

            return [
                'status' => 'completed',
                'total_success' => $loadedRows,
                'total_failed' => 0,
                'total_rows' => $loadedRows,
            ];
        } catch (\Throwable $exception) {
            if ($loadedRows > 0 && preg_match('/^[a-f0-9]{20}$/', $uuidPrefix)) {
                DB::table(CrasSourceService::TABLE)
                    ->where('cras_uuid', 'like', $uuidPrefix . '%')
                    ->delete();
            }
            throw $exception;
        } finally {
            File::delete($stagedPath);
        }
    }

    private function resolveReport(int $reportId = 0): ?NamaReport
    {
        return NamaReport::query()
            ->where('active', 1)
            ->where('table_name', CrasSourceService::TABLE)
            ->when($reportId > 0, static fn ($query) => $query->where('id_report', $reportId))
            ->first();
    }

    private function rememberUploadSession(int $reportId, string $relativePath): void
    {
        session([
            'active_id_report' => $reportId,
            'import_type' => 'cras',
            'cras_file' => $relativePath,
            'cras_preview_state_key' => null,
        ]);
    }

    private function buildStoredFileName(string $originalName, string $extension): string
    {
        $base = Str::slug(pathinfo($originalName, PATHINFO_FILENAME));

        return now()->format('Ymd_His') . '_' . Str::random(8) . '_'
            . ($base !== '' ? $base : 'ssa-cras') . '.' . $extension;
    }

    private function resolveUploadedPath(string $relativePath): string
    {
        $normalized = str_replace('\\', '/', urldecode($relativePath));
        if (!str_starts_with($normalized, self::UPLOAD_DIRECTORY . '/')) {
            throw new \RuntimeException('Lokasi file CRAS tidak valid.');
        }

        $absolutePath = Storage::path($normalized);
        $realPath = realpath($absolutePath);
        $uploadRoot = realpath(Storage::path(self::UPLOAD_DIRECTORY));
        if ($realPath === false || $uploadRoot === false) {
            throw new \RuntimeException('File CRAS tidak ditemukan di server.');
        }

        $rootPrefix = rtrim(str_replace('\\', '/', strtolower($uploadRoot)), '/') . '/';
        $filePath = str_replace('\\', '/', strtolower($realPath));
        if (!str_starts_with($filePath, $rootPrefix)) {
            throw new \RuntimeException('Lokasi file CRAS berada di luar direktori upload.');
        }

        return $realPath;
    }

    private function storePreviewState(string $relativePath, array $state): string
    {
        $key = hash('sha256', implode('|', [
            $relativePath,
            (string) ($state['source_size'] ?? 0),
            (string) ($state['source_mtime'] ?? 0),
            (string) (auth()->id() ?? 'guest'),
            Str::random(16),
        ]));
        $state['relative_path'] = $relativePath;
        $state['user_id'] = auth()->id();
        Cache::put($this->previewCacheKey($key), $state, now()->addHours(self::PREVIEW_CACHE_HOURS));

        return $key;
    }

    private function resolvePreviewState(string $relativePath, string $stateKey): array
    {
        $this->resolveUploadedPath($relativePath);
        $stateKey = $stateKey !== '' ? $stateKey : (string) session('cras_preview_state_key', '');
        $state = $stateKey !== '' ? Cache::get($this->previewCacheKey($stateKey)) : null;
        if (!is_array($state) || ($state['relative_path'] ?? null) !== $relativePath) {
            throw new \RuntimeException('Cache preview CRAS kedaluwarsa. Upload ulang file untuk memvalidasi sumber.');
        }
        if (($state['user_id'] ?? null) !== auth()->id()) {
            throw new \RuntimeException('Preview CRAS tidak sesuai pengguna yang mengunggah file.');
        }

        return [$stateKey, $state];
    }

    private function previewCacheKey(string $stateKey): string
    {
        return 'cras_preview:' . $stateKey;
    }

    private function resolveSelectedBranches(string $filtersJson, array $availableBranches): array
    {
        $filters = json_decode($filtersJson, true);
        $filters = is_array($filters) ? $filters : [];
        $selected = $filters[CrasSourceService::BRANCH_INDEX]
            ?? $filters[(string) CrasSourceService::BRANCH_INDEX]
            ?? $availableBranches;
        $selected = is_array($selected) ? array_values(array_unique(array_map('strval', $selected))) : [];
        $availableLookup = array_fill_keys(array_map('strval', $availableBranches), true);

        foreach ($selected as $branch) {
            if (!array_key_exists($branch, $availableLookup)) {
                throw new \RuntimeException("Cabang filter CRAS `{$branch}` tidak ditemukan di file sumber.");
            }
        }

        return $selected;
    }

    private function chunkDirectory(string $uploadId): string
    {
        if (!preg_match('/^cras_[0-9a-f-]{36}$/', $uploadId)) {
            throw new \InvalidArgumentException('ID upload CRAS tidak valid.');
        }

        return storage_path(self::CHUNK_DIRECTORY . DIRECTORY_SEPARATOR . $uploadId);
    }

    private function readChunkMeta(string $directory): ?array
    {
        $path = $directory . DIRECTORY_SEPARATOR . 'meta.json';
        if (!is_file($path)) {
            return null;
        }

        $decoded = json_decode((string) File::get($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    private function ownsChunkUpload(array $meta): bool
    {
        return (string) ($meta['user_id'] ?? '') === (string) auth()->id();
    }
}
