<?php

namespace App\Http\Controllers\Import;

use App\Http\Controllers\Controller;
use App\Services\Import\ImportExecutionService;
use App\Services\Import\ImportProgressService;
use App\Support\ManagedReportSnapshotRebuildCoordinator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ImportJobManagementController extends Controller
{
    public function __construct(
        private readonly ManagedReportSnapshotRebuildCoordinator $snapshotRebuildCoordinator,
        private readonly ImportIndexController $importIndexController
    ) {
    }

    public function index()
    {
        return view('import.job-management');
    }

    public function data(Request $request, ImportProgressService $progressService)
    {
        if (!Schema::hasTable('import_jobs')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tabel `import_jobs` belum tersedia.',
            ], 500);
        }

        $validated = $request->validate([
            'status' => 'nullable|string|in:all,queued,processing,completed,failed,failed_partial,terminated',
            'search' => 'nullable|string|max:255',
            'active_only' => 'nullable|boolean',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:24',
        ]);

        $status = (string) ($validated['status'] ?? 'all');
        $search = trim((string) ($validated['search'] ?? ''));
        $activeOnly = (bool) ($validated['active_only'] ?? false);
        $perPage = (int) ($validated['per_page'] ?? 12);

        $baseQuery = DB::table('import_jobs as ij')
            ->leftJoin('nama_report as nr', 'nr.id_report', '=', 'ij.id_report')
            ->leftJoin('users as u', 'u.id', '=', 'ij.created_by');

        if ($activeOnly) {
            $baseQuery->whereIn('ij.status', ['queued', 'processing']);
        }

        if ($status !== 'all') {
            $baseQuery->where('ij.status', $status);
        }

        if ($search !== '') {
            $baseQuery->where(function ($query) use ($search) {
                $like = '%' . $search . '%';
                $query->where('ij.file_name', 'like', $like)
                    ->orWhere('nr.nama_report', 'like', $like)
                    ->orWhere('nr.table_name', 'like', $like)
                    ->orWhere('u.name', 'like', $like)
                    ->orWhere('ij.id', 'like', $like);
            });
        }

        $jobs = $baseQuery
            ->select([
                'ij.id',
                'ij.id_report',
                'ij.file_name',
                'ij.folder_path',
                'ij.status',
                'ij.total_files',
                'ij.total_success',
                'ij.total_failed',
                'ij.created_by',
                'ij.created_at',
                'ij.updated_at',
                'nr.nama_report',
                'nr.table_name',
                'u.name as created_by_name',
            ])
            ->orderByDesc('ij.updated_at')
            ->paginate($perPage);

        $items = collect($jobs->items())->map(function ($job) use ($progressService) {
            $statusPayload = $progressService->getStatusPayload((int) $job->id);
            $createdAt = $this->safeParseDate($job->created_at);
            $updatedAt = $this->safeParseDate($job->updated_at);
            $durationSeconds = null;

            if ($createdAt && $updatedAt) {
                $durationSeconds = max(0, $updatedAt->diffInSeconds($createdAt));
            }

            return [
                'id' => (int) $job->id,
                'report_name' => (string) ($job->nama_report ?? 'Report #' . (int) $job->id_report),
                'table_name' => (string) ($job->table_name ?? ''),
                'file_name' => (string) ($job->file_name ?? ''),
                'status' => (string) ($statusPayload['status'] ?? $job->status),
                'status_label' => $this->statusLabel((string) ($statusPayload['status'] ?? $job->status)),
                'status_tone' => $this->statusTone((string) ($statusPayload['status'] ?? $job->status)),
                'percent' => (int) ($statusPayload['percent'] ?? 0),
                'processed_rows' => (int) ($statusPayload['processed_rows'] ?? 0),
                'total_rows' => (int) ($statusPayload['total_rows'] ?? $job->total_files ?? 0),
                'total_success' => (int) ($statusPayload['total_success'] ?? $job->total_success ?? 0),
                'total_failed' => (int) ($statusPayload['total_failed'] ?? $job->total_failed ?? 0),
                'message' => (string) ($statusPayload['message'] ?? 'Import sedang diproses.'),
                'phase' => (string) ($statusPayload['phase'] ?? ''),
                'mode' => (string) ($statusPayload['mode'] ?? ''),
                'termination_requested' => (bool) ($statusPayload['termination_requested'] ?? false),
                'can_terminate' => in_array((string) ($statusPayload['status'] ?? $job->status), ['queued', 'processing'], true),
                'can_force_start' => (string) ($statusPayload['status'] ?? $job->status) === 'queued',
                'can_delete' => in_array((string) ($statusPayload['status'] ?? $job->status), ['completed', 'failed', 'failed_partial', 'terminated'], true),
                'created_by_name' => (string) ($job->created_by_name ?? 'System'),
                'created_at' => $createdAt?->toIso8601String(),
                'created_at_label' => $createdAt?->format('d M Y H:i:s'),
                'updated_at' => $updatedAt?->toIso8601String(),
                'updated_at_label' => $updatedAt?->format('d M Y H:i:s'),
                'duration_seconds' => $durationSeconds,
                'duration_label' => $this->formatDuration($durationSeconds),
            ];
        })->values();

        $snapshotJobs = $this->resolveSnapshotJobs();
        $managedDeleteJobs = $this->importIndexController->resolveManagedReportDeleteJobs();
        $rawQueueJobs = $this->resolveRawQueueJobs(
            collect($managedDeleteJobs)->pluck('id')->filter()->map(fn ($id): string => (string) $id)->all(),
            collect($snapshotJobs)->pluck('id')->filter()->map(fn ($id): string => (string) $id)->all()
        );
        $summarySource = DB::table('import_jobs');
        $todayStart = now()->startOfDay();

        return response()->json([
            'status' => 'success',
            'summary' => [
                'active_jobs' => (clone $summarySource)->whereIn('status', ['queued', 'processing'])->count(),
                'queued_jobs' => (clone $summarySource)->where('status', 'queued')->count(),
                'processing_jobs' => (clone $summarySource)->where('status', 'processing')->count(),
                'today_jobs' => (clone $summarySource)->where('created_at', '>=', $todayStart)->count(),
            ],
            'snapshot_summary' => [
                'active_jobs' => collect($snapshotJobs)->whereIn('status', ['queued', 'processing'])->count(),
                'queued_jobs' => collect($snapshotJobs)->where('status', 'queued')->count(),
                'processing_jobs' => collect($snapshotJobs)->where('status', 'processing')->count(),
            ],
            'filters' => [
                'status' => $status,
                'active_only' => $activeOnly,
            ],
            'queue_health' => $this->resolveQueueHealth(),
            'snapshot_jobs' => $snapshotJobs,
            'managed_delete_jobs' => $managedDeleteJobs,
            'managed_delete_summary' => [
                'active_jobs' => collect($managedDeleteJobs)->whereIn('status', ['queued', 'processing'])->count(),
                'queued_jobs' => collect($managedDeleteJobs)->where('status', 'queued')->count(),
                'processing_jobs' => collect($managedDeleteJobs)->where('status', 'processing')->count(),
            ],
            'raw_queue_jobs' => $rawQueueJobs,
            'raw_queue_summary' => [
                'total' => count($rawQueueJobs),
                'pending' => collect($rawQueueJobs)->where('status', 'pending')->count(),
                'reserved' => collect($rawQueueJobs)->where('status', 'reserved')->count(),
            ],
            'active_jobs' => $items->filter(fn (array $job) => in_array($job['status'], ['queued', 'processing'], true))->values()->all(),
            'jobs' => $items->all(),
            'pagination' => [
                'current_page' => $jobs->currentPage(),
                'last_page' => $jobs->lastPage(),
                'per_page' => $jobs->perPage(),
                'total' => $jobs->total(),
                'from' => $jobs->firstItem(),
                'to' => $jobs->lastItem(),
            ],
        ]);
    }

    public function forceStart(int $jobId, ImportProgressService $progressService, ImportExecutionService $executionService)
    {
        $job = $progressService->findJob($jobId);
        if (!$job) {
            return response()->json([
                'status' => 'error',
                'message' => 'Job import tidak ditemukan.',
            ], 404);
        }

        $status = strtolower(trim((string) ($job->status ?? '')));
        if ($status !== 'queued') {
            return response()->json([
                'status' => 'error',
                'message' => 'Force start hanya tersedia untuk job import yang masih queued.',
            ], 422);
        }

        $progressService->cleanupQueuedImportJobRowsForJob($jobId);
        if (!$this->launchImportInBackground($jobId)) {
            $executionService->run($jobId);

            return response()->json([
                'status' => 'success',
                'message' => 'Force start dijalankan. Job import diproses langsung karena background runner tidak tersedia.',
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Force start dijalankan. Job import diproses di background tanpa menunggu worker queue.',
        ]);
    }

    protected function launchImportInBackground(int $jobId): bool
    {
        if ($jobId <= 0) {
            return false;
        }

        try {
            $phpBinary = PHP_BINARY ?: 'php';
            $artisanPath = base_path('artisan');
            $projectRoot = base_path();
            $command = escapeshellarg($phpBinary)
                . ' '
                . escapeshellarg($artisanPath)
                . ' import:run-job '
                . $jobId;

            if (DIRECTORY_SEPARATOR === '\\') {
                $backgroundCommand = 'start /B cmd /C "cd /D '
                    . escapeshellarg($projectRoot)
                    . ' && '
                    . $command
                    . '"';
                @pclose(@popen($backgroundCommand, 'r'));

                return true;
            }

            exec('cd ' . escapeshellarg($projectRoot) . ' && ' . $command . ' > /dev/null 2>&1 &');

            return true;
        } catch (\Throwable $e) {
            Log::warning('ImportJobManagementController: gagal menjalankan import background.', [
                'job_id' => $jobId,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function forceStartSnapshot(string $rebuildId)
    {
        $resolved = $this->snapshotRebuildCoordinator->forceStart($rebuildId);

        return response()->json($resolved['payload'], (int) ($resolved['status_code'] ?? 200));
    }

    public function terminate(Request $request, int $jobId, ImportProgressService $progressService)
    {
        $job = $progressService->findJob($jobId);
        if (!$job) {
            return response()->json([
                'status' => 'error',
                'message' => 'Job import tidak ditemukan.',
            ], 404);
        }

        $status = strtolower(trim((string) ($job->status ?? '')));
        if (!in_array($status, ['queued', 'processing'], true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Hanya job queued atau processing yang bisa dihentikan.',
            ], 422);
        }

        $progressService->requestTermination($jobId, auth()->id());
        $progressService->cleanupQueuedImportJobRowsForJob($jobId);
        $progressService->markTerminated(
            $jobId,
            'Job dihentikan melalui Job Management.',
            (int) ($job->total_success ?? 0),
            (int) ($job->total_failed ?? 0)
        );

        return response()->json([
            'status' => 'success',
            'message' => $status === 'queued'
                ? 'Job queued berhasil dihentikan.'
                : 'Job processing dihentikan paksa. Jika worker lama masih aktif, status job tidak akan diaktifkan kembali.',
        ]);
    }

    public function destroy(int $jobId, ImportProgressService $progressService)
    {
        $job = $progressService->findJob($jobId);
        if (!$job) {
            return response()->json([
                'status' => 'error',
                'message' => 'Job import tidak ditemukan.',
            ], 404);
        }

        $status = strtolower(trim((string) ($job->status ?? '')));
        if (!in_array($status, ['completed', 'failed', 'failed_partial', 'terminated'], true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Hanya riwayat job terminal yang bisa dihapus. Terminate job aktif terlebih dulu.',
            ], 422);
        }

        $progressService->deleteJob($jobId);

        return response()->json([
            'status' => 'success',
            'message' => 'Job berhasil dihapus dari database.',
        ]);
    }

    public function bulkDestroy(Request $request, ImportProgressService $progressService)
    {
        $validated = $request->validate([
            'job_ids' => 'required|array|min:1',
            'job_ids.*' => 'required|integer|min:1',
        ]);

        $jobIds = array_values(array_unique(array_map('intval', $validated['job_ids'])));
        $deletableIds = [];

        foreach ($jobIds as $jobId) {
            $job = $progressService->findJob($jobId);
            if (!$job) {
                continue;
            }

            $status = strtolower(trim((string) ($job->status ?? '')));
            if (in_array($status, ['completed', 'failed', 'failed_partial', 'terminated'], true)) {
                $deletableIds[] = $jobId;
            }
        }

        if (empty($deletableIds)) {
            return response()->json([
                'status' => 'warning',
                'message' => 'Tidak ada job terminal yang valid untuk dihapus.',
                'deleted_count' => 0,
            ]);
        }

        $deletedCount = $progressService->deleteJobsByIds($deletableIds);

        return response()->json([
            'status' => 'success',
            'message' => $deletedCount . ' job berhasil dihapus dari database.',
            'deleted_count' => $deletedCount,
        ]);
    }

    public function destroyQueueJob(int $queueJobId): \Illuminate\Http\JsonResponse
    {
        if (!Schema::hasTable('jobs')) {
            return response()->json(['status' => 'error', 'message' => 'Tabel queue tidak tersedia.'], 500);
        }

        $row = DB::table('jobs')->find($queueJobId);
        if (!$row) {
            return response()->json(['status' => 'error', 'message' => 'Queue job tidak ditemukan.'], 404);
        }

        if ($row->reserved_at !== null) {
            return response()->json([
                'status' => 'error',
                'message' => 'Job sedang di-reserve worker (processing). Tidak dapat dihapus saat sedang diproses.',
            ], 422);
        }

        DB::table('jobs')->where('id', $queueJobId)->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Queue job berhasil dihapus dari antrian.',
        ]);
    }

    public function forceRunQueueJob(int $queueJobId, ImportProgressService $progressService, ImportExecutionService $executionService): \Illuminate\Http\JsonResponse
    {
        if (!Schema::hasTable('jobs')) {
            return response()->json(['status' => 'error', 'message' => 'Tabel queue tidak tersedia.'], 500);
        }

        $row = DB::table('jobs')->find($queueJobId);
        if (!$row) {
            return response()->json(['status' => 'error', 'message' => 'Queue job tidak ditemukan.'], 404);
        }

        if ($row->reserved_at !== null) {
            return response()->json([
                'status' => 'error',
                'message' => 'Job sedang di-reserve worker (sedang diproses). Tidak dapat di-force run.',
            ], 422);
        }

        $payloadData = json_decode((string) ($row->payload ?? '{}'), true) ?? [];
        $serializedCommand = (string) ($payloadData['data']['command'] ?? '');

        if ($serializedCommand === '') {
            return response()->json(['status' => 'error', 'message' => 'Payload job tidak valid atau kosong.'], 422);
        }

        try {
            $jobObject = @unserialize($serializedCommand);
        } catch (\Throwable) {
            $jobObject = false;
        }

        if ($jobObject === false || !is_object($jobObject)) {
            return response()->json(['status' => 'error', 'message' => 'Gagal membaca isi job dari queue. Class mungkin tidak ditemukan.'], 422);
        }

        // RunImportJob → gunakan flow force-start import yang sudah ada
        if ($jobObject instanceof \App\Jobs\RunImportJob) {
            $importJobId = (int) ($jobObject->jobId ?? 0);
            if ($importJobId <= 0) {
                return response()->json(['status' => 'error', 'message' => 'jobId tidak ditemukan di payload.'], 422);
            }
            DB::table('jobs')->where('id', $queueJobId)->delete();
            $progressService->cleanupQueuedImportJobRowsForJob($importJobId);
            
            // RUN IN BACKGROUND to avoid web request timeout
            // Using artisan command is safer for environment loading
            $cmd = "php artisan import:run-job $importJobId";
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                pclose(popen("start /B " . $cmd, "r"));
            } else {
                exec($cmd . " > /dev/null 2>&1 &");
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Import job #' . $importJobId . ' dijalankan di background (force start). Progress dapat dipantau di Dashboard.',
            ]);
        }

        // RunManagedReportSnapshotRebuildJob → pakai coordinator forceStart
        if ($jobObject instanceof \App\Jobs\RunManagedReportSnapshotRebuildJob) {
            $rebuildId = trim((string) ($jobObject->rebuildId ?? ''));
            if ($rebuildId === '') {
                return response()->json(['status' => 'error', 'message' => 'rebuildId tidak ditemukan di payload snapshot job ini.'], 422);
            }
            DB::table('jobs')->where('id', $queueJobId)->delete();
            $resolved = $this->snapshotRebuildCoordinator->forceStart($rebuildId);
            return response()->json($resolved['payload'], (int) ($resolved['status_code'] ?? 200));
        }

        // Job generik (WarmReportCacheJob, SyncImportedReportJob, dll) → jalankan langsung via container
        $rebuildId = (string) \Illuminate\Support\Str::uuid();
        $isSnapshotRelated = property_exists($jobObject, 'rebuildId') || $jobObject instanceof \App\Jobs\WarmReportCacheJob || $jobObject instanceof \App\Jobs\SyncImportedReportJob;

        if ($isSnapshotRelated) {
            $label = class_basename($jobObject);
            if ($jobObject instanceof \App\Jobs\SyncImportedReportJob) {
                $label = 'Sync ' . ($jobObject->tableName ?: 'Data');
                $jobObject->rebuildId = $rebuildId;
            }
            $this->snapshotRebuildCoordinator->registerStandaloneJob($rebuildId, $label, source: 'Force Run Monitor');
        }

        DB::table('jobs')->where('id', $queueJobId)->delete();
        try {
            app()->call([$jobObject, 'handle']);

            if ($isSnapshotRelated) {
                $state = \App\Support\ManagedReportSnapshotRebuildStore::getState($rebuildId);
                if ($state) {
                    $state['status'] = 'completed';
                    $state['stage'] = 'completed';
                    $state['progress_percent'] = 100;
                    $state['finished_at'] = now()->toIso8601String();
                    $state['message'] = 'Job ' . class_basename($jobObject) . ' selesai dijalankan via force run.';
                    \App\Support\ManagedReportSnapshotRebuildStore::putState($state);
                }
            }

            return response()->json([
                'status' => 'success',
                'rebuild_id' => $isSnapshotRelated ? $rebuildId : null,
                'message' => class_basename($jobObject) . ' berhasil dijalankan langsung.',
            ]);
        } catch (\Throwable $e) {
            if ($isSnapshotRelated) {
                $state = \App\Support\ManagedReportSnapshotRebuildStore::getState($rebuildId);
                if ($state) {
                    $state['status'] = 'failed';
                    $state['stage'] = 'failed';
                    $state['error'] = $e->getMessage();
                    $state['finished_at'] = now()->toIso8601String();
                    \App\Support\ManagedReportSnapshotRebuildStore::putState($state);
                }
            }

            return response()->json([
                'status' => 'warning',
                'message' => class_basename($jobObject) . ' sudah dihapus dari queue namun gagal dijalankan inline: ' . $e->getMessage(),
            ], 200);
        }
    }

    public function purgeQueueJobs(Request $request): \Illuminate\Http\JsonResponse
    {
        if (!Schema::hasTable('jobs')) {
            return response()->json(['status' => 'error', 'message' => 'Tabel queue tidak tersedia.'], 500);
        }

        $validated = $request->validate([
            'class_name' => 'nullable|string|max:255',
        ]);

        $filterClass = trim((string) ($validated['class_name'] ?? ''));

        $configuredReportQueue = trim((string) config('queue.report_queue', 'default')) ?: 'default';
        $queues = array_values(array_unique(array_filter([
            $configuredReportQueue, 'default', 'reports-low', 'imports-high',
        ])));

        $allBasenames = $this->allKnownJobBasenames();

        $query = DB::table('jobs')
            ->whereNull('reserved_at')
            ->whereIn('queue', $queues);

        if ($filterClass !== '') {
            $query->where('payload', 'like', '%' . $filterClass . '%');
        } else {
            $query->where(function ($q) use ($allBasenames) {
                foreach ($allBasenames as $basename) {
                    $q->orWhere('payload', 'like', '%' . $basename . '%');
                }
            });
        }

        $deleted = $query->delete();

        return response()->json([
            'status' => 'success',
            'message' => $deleted . ' queue job berhasil dihapus dari antrian.',
            'deleted_count' => $deleted,
        ]);
    }

    public function clear(Request $request, ImportProgressService $progressService)
    {
        if (!Schema::hasTable('import_jobs')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tabel `import_jobs` belum tersedia.',
            ], 500);
        }

        $validated = $request->validate([
            'status' => 'nullable|string|in:all,completed,failed,failed_partial,terminated',
            'search' => 'nullable|string|max:255',
        ]);

        $status = (string) ($validated['status'] ?? 'all');
        $search = trim((string) ($validated['search'] ?? ''));

        $query = DB::table('import_jobs as ij')
            ->leftJoin('nama_report as nr', 'nr.id_report', '=', 'ij.id_report')
            ->leftJoin('users as u', 'u.id', '=', 'ij.created_by')
            ->whereIn('ij.status', ['completed', 'failed', 'failed_partial', 'terminated']);

        if ($status !== 'all') {
            $query->where('ij.status', $status);
        }

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $like = '%' . $search . '%';
                $builder->where('ij.file_name', 'like', $like)
                    ->orWhere('nr.nama_report', 'like', $like)
                    ->orWhere('nr.table_name', 'like', $like)
                    ->orWhere('u.name', 'like', $like)
                    ->orWhere('ij.id', 'like', $like);
            });
        }

        $jobIds = $query->pluck('ij.id')->map(fn ($id) => (int) $id)->all();
        if (empty($jobIds)) {
            return response()->json([
                'status' => 'warning',
                'message' => 'Tidak ada riwayat job yang cocok untuk dihapus.',
                'deleted_count' => 0,
            ]);
        }

        $deletedCount = $progressService->deleteJobsByIds($jobIds);

        return response()->json([
            'status' => 'success',
            'message' => $deletedCount . ' job berhasil dibersihkan dari database.',
            'deleted_count' => $deletedCount,
        ]);
    }

    private function safeParseDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveSnapshotJobs(): array
    {
        $snapshotIds = [];
        $queueRows = $this->snapshotRebuildCoordinator->snapshotQueueRows();
        foreach ($queueRows as $queueRow) {
            $rebuildId = trim((string) ($queueRow['rebuild_id'] ?? ''));
            if ($rebuildId !== '') {
                $snapshotIds[] = $rebuildId;
            }
        }

        $snapshotIds = array_values(array_unique(array_merge(
            $this->snapshotRebuildCoordinator->resolveKnownRebuildIds(),
            $snapshotIds ?? []
        )));
        $queueRowsByRebuildId = collect($queueRows)->keyBy('rebuild_id');
        $jobs = collect($snapshotIds)
            ->map(function (string $rebuildId) use ($queueRowsByRebuildId) {
                $queueRow = $queueRowsByRebuildId->get($rebuildId);
                $state = $this->snapshotRebuildCoordinator->resolveOperationalState($rebuildId, is_array($queueRow) ? $queueRow : null);
                if ($state === null) {
                    return null;
                }

                $createdAt = $this->safeParseDate($state['created_at'] ?? null);
                $updatedAt = $this->safeParseDate($state['updated_at'] ?? null);
                $startedAt = $this->safeParseDate($state['started_at'] ?? null);
                $finishedAt = $this->safeParseDate($state['finished_at'] ?? null);
                $referenceStart = $startedAt ?? $createdAt;
                $referenceEnd = $finishedAt ?? $updatedAt;
                $durationSeconds = ($referenceStart && $referenceEnd)
                    ? max(0, $referenceEnd->diffInSeconds($referenceStart))
                    : null;

                $status = $this->mapSnapshotStatus((string) ($state['status'] ?? 'queued'), (string) ($state['stage'] ?? 'queued'));
                $reportTotalUnits = max(0, (int) ($state['report_total_units'] ?? 0));
                $reportCompletedUnits = max(0, (int) ($state['report_completed_units'] ?? 0));
                $queueRow = $queueRowsByRebuildId->get($rebuildId);

                return [
                    'id' => (string) ($state['rebuild_id'] ?? $rebuildId),
                    'report_name' => 'Snapshot Rebuild Semua Report',
                    'table_name' => 'managed_report_snapshots',
                    'file_name' => (bool) ($state['force_rebuild'] ?? false)
                        ? 'Full Rebuild Snapshot'
                        : 'Refresh Snapshot',
                    'status' => $status,
                    'status_label' => $this->statusLabel($status),
                    'status_tone' => $this->statusTone($status),
                    'percent' => max(0, min(100, (int) ($state['progress_percent'] ?? 0))),
                    'processed_rows' => max(0, (int) ($state['completed_units'] ?? 0)),
                    'total_rows' => max(1, (int) ($state['total_units'] ?? 1)),
                    'total_success' => max(0, (int) ($state['completed_units'] ?? 0)),
                    'total_failed' => 0,
                    'message' => (string) ($state['message'] ?? 'Snapshot rebuild sedang diproses.'),
                    'phase' => (string) ($state['stage'] ?? ''),
                    'mode' => (bool) ($state['force_rebuild'] ?? false) ? 'full_rebuild' : 'refresh',
                    'termination_requested' => false,
                    'can_terminate' => false,
                    'can_force_start' => $status === 'queued',
                    'can_delete' => false,
                    'created_by_name' => 'System',
                    'created_at' => $createdAt?->toIso8601String(),
                    'created_at_label' => $createdAt?->format('d M Y H:i:s'),
                    'updated_at' => $updatedAt?->toIso8601String(),
                    'updated_at_label' => $updatedAt?->format('d M Y H:i:s'),
                    'duration_seconds' => $durationSeconds,
                    'duration_label' => $this->formatDuration($durationSeconds),
                    'kind' => 'snapshot_rebuild',
                    'stage_label' => $this->snapshotStageLabel((string) ($state['stage'] ?? 'queued')),
                    'current_report_label' => (string) ($state['current_report_label'] ?? ''),
                    'current_period' => (string) ($state['current_period'] ?? ''),
                    'report_completed_units' => $reportCompletedUnits,
                    'report_total_units' => $reportTotalUnits,
                    'reports_count' => is_array($state['reports'] ?? null) ? count($state['reports']) : 0,
                    'queue_name' => (string) (($queueRow['queue'] ?? '') ?: ''),
                    'queue_reserved' => (bool) ($queueRow['reserved'] ?? false),
                    'queue_job_id' => isset($queueRow['job_id']) ? (int) $queueRow['job_id'] : null,
                ];
            })
            ->filter()
            ->sortByDesc('updated_at')
            ->values();

        return $jobs->all();
    }

    private function mapSnapshotStatus(string $status, string $stage): string
    {
        $normalizedStatus = strtolower(trim($status));
        $normalizedStage = strtolower(trim($stage));

        if ($normalizedStatus === 'queued' || $normalizedStage === 'queued') {
            return 'queued';
        }

        if (in_array($normalizedStatus, ['running'], true)) {
            return 'processing';
        }

        if (in_array($normalizedStatus, ['completed', 'failed'], true)) {
            return $normalizedStatus;
        }

        return 'processing';
    }

    private function snapshotStageLabel(string $stage): string
    {
        return match (strtolower(trim($stage))) {
            'queued' => 'Queued',
            'planning' => 'Planning',
            'rebuilding' => 'Rebuilding',
            'cache' => 'Cache Refresh',
            'completed' => 'Completed',
            'failed' => 'Failed',
            default => ucfirst($stage !== '' ? $stage : 'unknown'),
        };
    }

    private function resolveQueueHealth(): array
    {
        if (!Schema::hasTable('jobs')) {
            return [
                'status' => 'unavailable',
                'tone' => 'muted',
                'message' => 'Tabel queue `jobs` tidak tersedia.',
                'configured_report_queue' => (string) config('queue.report_queue', 'default'),
                'pending_report_jobs' => 0,
                'pending_managed_delete_jobs' => 0,
                'pending_snapshot_rebuilds' => 0,
                'legacy_reports_low_pending' => 0,
            ];
        }

        $configuredReportQueue = trim((string) config('queue.report_queue', 'default')) ?: 'default';
        $queues = array_values(array_unique(array_filter([$configuredReportQueue, 'default', 'reports-low', 'imports-high'])));
        $reportBasenames = $this->reportQueueBasenames();
        $managedDeleteBasename = class_basename(\App\Jobs\RunManagedReportDeleteJob::class);
        $snapshotRebuildBasename = class_basename(\App\Jobs\RunManagedReportSnapshotRebuildJob::class);
        $reservedJobs = DB::table('jobs')
            ->whereNotNull('reserved_at')
            ->whereIn('queue', $queues)
            ->select(['id', 'queue', 'reserved_at', 'created_at', 'available_at', 'payload'])
            ->orderBy('id')
            ->get()
            ->filter(function ($job) use ($reportBasenames, $managedDeleteBasename) {
                $payload = (string) ($job->payload ?? '');

                if ($managedDeleteBasename !== '' && str_contains($payload, $managedDeleteBasename)) {
                    return true;
                }

                foreach ($reportBasenames as $basename) {
                    if ($basename !== '' && str_contains($payload, $basename)) {
                        return true;
                    }
                }

                return false;
            })
            ->values();

        $snapshotQueueHealth = $this->snapshotRebuildCoordinator->purgeStaleReservedQueueRows();
        $purgedReservedSnapshotJobs = (int) ($snapshotQueueHealth['purged_reserved_snapshot_jobs'] ?? 0);
        $staleReservedSnapshotJobs = (int) ($snapshotQueueHealth['stale_reserved_snapshot_jobs'] ?? 0);

        $jobs = DB::table('jobs')
            ->whereNull('reserved_at')
            ->whereIn('queue', $queues)
            ->select(['id', 'queue', 'created_at', 'available_at', 'payload'])
            ->orderBy('id')
            ->get()
            ->filter(function ($job) use ($reportBasenames, $managedDeleteBasename) {
                $payload = (string) ($job->payload ?? '');

                if ($managedDeleteBasename !== '' && str_contains($payload, $managedDeleteBasename)) {
                    return true;
                }

                foreach ($reportBasenames as $basename) {
                    if ($basename !== '' && str_contains($payload, $basename)) {
                        return true;
                    }
                }

                return false;
            })
            ->values();

        $pendingManagedDeleteJobs = $jobs->filter(fn ($job) => str_contains((string) ($job->payload ?? ''), $managedDeleteBasename))->count();
        $pendingReportJobs = max(0, $jobs->count() - $pendingManagedDeleteJobs);
        $pendingSnapshotRebuilds = $jobs->filter(fn ($job) => str_contains((string) ($job->payload ?? ''), $snapshotRebuildBasename))->count();
        $legacyReportsLowPending = $jobs->where('queue', 'reports-low')->count();
        $oldestPending = $jobs->first();
        $oldestAgeSeconds = $oldestPending ? $this->queueRowAgeSeconds($oldestPending) : null;

        // Build per-type breakdown for notification detail
        $perTypeBreakdown = [];
        foreach ($this->reportQueueBasenames() as $basename) {
            $count = $jobs->filter(fn ($job) => str_contains((string) ($job->payload ?? ''), $basename))->count();
            if ($count > 0) {
                $perTypeBreakdown[] = $basename . ' (' . $count . ')';
            }
        }

        if ($pendingReportJobs === 0 && $pendingManagedDeleteJobs === 0 && $staleReservedSnapshotJobs === 0) {
            return [
                'status' => 'ok',
                'tone' => 'info',
                'is_active' => true,
                'message' => 'Queue report sehat. Tidak ada job report atau delete yang menunggu.',
                'configured_report_queue' => $configuredReportQueue,
                'pending_report_jobs' => 0,
                'pending_managed_delete_jobs' => 0,
                'pending_snapshot_rebuilds' => 0,
                'legacy_reports_low_pending' => 0,
                'oldest_pending_age_seconds' => 0,
                'stale_reserved_snapshot_jobs' => 0,
                'purged_reserved_snapshot_jobs' => $purgedReservedSnapshotJobs,
                'per_type_breakdown' => [],
            ];
        }

        // Check if there's any job currently being processed (reserved)
        $processingCount = DB::table('jobs')->whereNotNull('reserved_at')->count();
        $isProcessing = $processingCount > 0;

        if ($pendingReportJobs > 0) {
            $message = sprintf(
                'Ada %d job report menunggu di queue `%s`.',
                $pendingReportJobs,
                $configuredReportQueue
            );
            if (!empty($perTypeBreakdown)) {
                $message .= ' Rincian: ' . implode(', ', $perTypeBreakdown) . '.';
            }
            if ($pendingSnapshotRebuilds > 0) {
                $message .= sprintf(' Termasuk %d snapshot rebuild.', $pendingSnapshotRebuilds);
            }
            if ($pendingManagedDeleteJobs > 0) {
                $message .= sprintf(' Ada %d job managed delete menunggu di queue `imports-high`.', $pendingManagedDeleteJobs);
            }
        } elseif ($pendingManagedDeleteJobs > 0) {
            $message = sprintf('Ada %d job managed delete yang masih menunggu di queue `imports-high`.', $pendingManagedDeleteJobs);
        } else {
            $message = 'Ada job snapshot yang sudah di-reserve worker tetapi terlalu lama tidak selesai.';
        }

        if ($legacyReportsLowPending > 0 && $configuredReportQueue !== 'reports-low') {
            $message .= sprintf(' Masih ada %d job lama di queue `reports-low` yang perlu dikonsumsi worker lama atau dibersihkan.', $legacyReportsLowPending);
        }

        if ($staleReservedSnapshotJobs > 0) {
            $message .= sprintf(' Terdeteksi %d snapshot rebuild reserved terlalu lama; progress bisa macet bila worker sudah berhenti.', $staleReservedSnapshotJobs);
        }

        // Indication of worker health
        if (($oldestAgeSeconds ?? 0) >= 300) {
            if (!$isProcessing) {
                $message .= ' Indikasinya worker report tidak sedang mengonsumsi queue. Semua job ini dapat dipantau di bagian "Queue Jobs" di bawah.';
                $message .= ' Jalankan `composer queue` untuk import umum, report, dan Daily Loan Dinamis.';
                $status = 'warning';
            } else {
                $message .= ' Worker sedang memproses job berat, antrean bergerak lambat.';
                $status = 'ok';
            }
        } else {
            $status = 'ok';
        }

        return [
            'status' => $status,
            'tone' => $status === 'warning' ? 'warning' : 'info',
            'is_active' => $isProcessing,
            'message' => $message,
            'configured_report_queue' => $configuredReportQueue,
            'pending_report_jobs' => $pendingReportJobs,
            'pending_managed_delete_jobs' => $pendingManagedDeleteJobs,
            'pending_snapshot_rebuilds' => $pendingSnapshotRebuilds,
            'legacy_reports_low_pending' => $legacyReportsLowPending,
            'oldest_pending_age_seconds' => $oldestAgeSeconds ?? 0,
            'stale_reserved_snapshot_jobs' => $staleReservedSnapshotJobs,
            'purged_reserved_snapshot_jobs' => $purgedReservedSnapshotJobs,
            'per_type_breakdown' => $perTypeBreakdown,
        ];
    }

    private function reportQueueBasenames(): array
    {
        return array_map('class_basename', [
            \App\Jobs\RunManagedReportSnapshotRebuildJob::class,
            \App\Jobs\WarmReportCacheJob::class,
            \App\Jobs\SyncImportedReportJob::class,
            \App\Jobs\EnsureDashboardSnapshotJob::class,
            \App\Jobs\EnsureDashboardSimpananSnapshotJob::class,
            \App\Jobs\EnsureRasioCasaSnapshotJob::class,
            \App\Jobs\EnsureRekeningDormantSnapshotJob::class,
        ]);
    }

    private function allKnownJobBasenames(): array
    {
        return array_values(array_unique(array_merge(
            $this->reportQueueBasenames(),
            array_map('class_basename', [
                \App\Jobs\RunImportJob::class,
                \App\Jobs\RunManagedReportLoadJob::class,
                \App\Jobs\RunManagedReportDeleteJob::class,
            ])
        )));
    }

    private function resolveRawQueueJobs(array $trackedManagedDeleteIds = [], array $trackedSnapshotIds = []): array
    {
        if (!Schema::hasTable('jobs')) {
            return [];
        }

        $configuredReportQueue = trim((string) config('queue.report_queue', 'default')) ?: 'default';
        $queues = array_values(array_unique(array_filter([
            $configuredReportQueue, 'default', 'reports-low', 'imports-high',
        ])));
        $allBasenames = $this->allKnownJobBasenames();

        $rows = DB::table('jobs')
            ->whereIn('queue', $queues)
            ->select(['id', 'queue', 'payload', 'reserved_at', 'available_at', 'created_at', 'attempts'])
            ->orderBy('id')
            ->get()
            ->filter(function ($job) use ($allBasenames) {
                $payload = (string) ($job->payload ?? '');
                foreach ($allBasenames as $basename) {
                    if ($basename !== '' && str_contains($payload, $basename)) {
                        return true;
                    }
                }
                return false;
            });

        $mappedRows = $rows->map(function ($row) {
            $payloadData = json_decode((string) ($row->payload ?? '{}'), true) ?? [];
            $displayName = (string) ($payloadData['displayName'] ?? $payloadData['data']['commandName'] ?? '');
            $className = class_basename($displayName);

            $reserved = $row->reserved_at !== null;
            $createdAt = is_numeric($row->created_at)
                ? Carbon::createFromTimestamp((int) $row->created_at)
                : $this->safeParseDate($row->created_at);
            $reservedAt = $row->reserved_at
                ? (is_numeric($row->reserved_at)
                    ? Carbon::createFromTimestamp((int) $row->reserved_at)
                    : $this->safeParseDate($row->reserved_at))
                : null;
            $ageSeconds = $this->queueRowAgeSeconds($row);

            $serializedCommand = (string) ($payloadData['data']['command'] ?? '');
            $jobData = $this->extractSerializedJobData($serializedCommand);
            if (empty($jobData)) {
                $jobData = $this->extractSerializedJobData((string) ($row->payload ?? ''));
            }
            $jobDataLabel = $this->buildJobDataLabel($jobData);

            return [
                'id' => (int) $row->id,
                'queue' => (string) ($row->queue ?? 'default'),
                'class_name' => $className ?: 'UnknownJob',
                'full_class' => $displayName,
                'status' => $reserved ? 'reserved' : 'pending',
                'status_label' => $reserved ? 'Reserved' : 'Pending',
                'status_tone' => $reserved ? 'info' : 'warning',
                'attempts' => (int) ($row->attempts ?? 0),
                'job_data' => $jobData,
                'job_data_label' => $jobDataLabel,
                'created_at' => $createdAt?->toIso8601String(),
                'created_at_label' => $createdAt?->format('d M Y H:i:s'),
                'reserved_at_label' => $reservedAt?->format('d M Y H:i:s'),
                'age_seconds' => $ageSeconds,
                'age_label' => $this->formatDuration($ageSeconds),
                'can_delete' => !$reserved,
                'can_force_run' => !$reserved,
                'kind' => 'raw_queue_job',
            ];
        });

        $trackedManagedDeleteIds = array_fill_keys(array_map('strval', $trackedManagedDeleteIds), true);
        $trackedSnapshotIds = array_fill_keys(array_map('strval', $trackedSnapshotIds), true);
        $queuedImportJobIds = $mappedRows
            ->filter(fn (array $job): bool => $job['class_name'] === class_basename(\App\Jobs\RunImportJob::class))
            ->map(fn (array $job): int => (int) ($job['job_data']['jobId'] ?? 0))
            ->filter(fn (int $jobId): bool => $jobId > 0)
            ->unique()
            ->values()
            ->all();
        $knownImportJobIds = [];

        if (!empty($queuedImportJobIds) && Schema::hasTable('import_jobs')) {
            $knownImportJobIds = DB::table('import_jobs')
                ->whereIn('id', $queuedImportJobIds)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->flip()
                ->all();
        }

        return $mappedRows
            ->reject(function (array $job) use ($knownImportJobIds, $trackedManagedDeleteIds, $trackedSnapshotIds): bool {
                $className = (string) ($job['class_name'] ?? '');
                $jobData = is_array($job['job_data'] ?? null) ? $job['job_data'] : [];

                if ($className === class_basename(\App\Jobs\RunImportJob::class)) {
                    $jobId = (int) ($jobData['jobId'] ?? 0);

                    return $jobId > 0 && array_key_exists($jobId, $knownImportJobIds);
                }

                if ($className === class_basename(\App\Jobs\RunManagedReportDeleteJob::class)) {
                    $deleteId = trim((string) ($jobData['deleteId'] ?? ''));

                    return $deleteId !== '' && isset($trackedManagedDeleteIds[$deleteId]);
                }

                if ($className === class_basename(\App\Jobs\RunManagedReportSnapshotRebuildJob::class)) {
                    $rebuildId = trim((string) ($jobData['rebuildId'] ?? ''));

                    return $rebuildId !== '' && isset($trackedSnapshotIds[$rebuildId]);
                }

                return false;
            })
            ->values()
            ->all();
    }

    private function extractSerializedJobData(string $serialized): array
    {
        if ($serialized === '') {
            return [];
        }

        $data = [];
        $knownProps = ['jobId', 'deleteId', 'tableName', 'periodHint', 'period', 'source', 'rebuildId'];

        foreach ($knownProps as $prop) {
            $propLen = strlen($prop);
            // Match public string property: s:{len}:"{prop}";s:{vlen}:"{value}";
            $pattern = '/s:' . $propLen . ':"' . preg_quote($prop, '/') . '";s:\d+:"([^"]{0,200})"/';
            if (preg_match($pattern, $serialized, $m)) {
                $data[$prop] = $m[1];
                continue;
            }
            // Match public int property: s:{len}:"{prop}";i:{value};
            $pattern = '/s:' . $propLen . ':"' . preg_quote($prop, '/') . '";i:(\d+)/';
            if (preg_match($pattern, $serialized, $m)) {
                $data[$prop] = (int) $m[1];
            }
        }

        return $data;
    }

    private function buildJobDataLabel(array $jobData): string
    {
        $parts = [];
        $labelMap = [
            'jobId' => 'Job',
            'tableName' => 'Tabel',
            'periodHint' => 'Periode',
            'period' => 'Periode',
            'source' => 'Source',
            'rebuildId' => 'Rebuild',
        ];

        foreach ($labelMap as $key => $label) {
            if (isset($jobData[$key]) && $jobData[$key] !== '' && $jobData[$key] !== null) {
                $val = is_string($jobData[$key]) ? $jobData[$key] : (string) $jobData[$key];
                if (strlen($val) <= 60) {
                    $parts[] = $label . ': ' . $val;
                }
            }
        }

        return implode(' • ', $parts);
    }

    private function queueRowAgeSeconds(object $job): int
    {
        $createdAt = $job->created_at ?? null;

        if (is_numeric($createdAt)) {
            return max(0, now()->timestamp - (int) $createdAt);
        }

        $parsed = $this->safeParseDate($createdAt);

        return $parsed ? max(0, now()->diffInSeconds($parsed)) : 0;
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'queued' => 'Queued',
            'processing' => 'Processing',
            'completed' => 'Completed',
            'terminated' => 'Terminated',
            'failed_partial' => 'Partial Failed',
            'failed' => 'Failed',
            default => ucfirst($status !== '' ? $status : 'unknown'),
        };
    }

    private function statusTone(string $status): string
    {
        return match ($status) {
            'queued' => 'warning',
            'processing' => 'info',
            'completed' => 'success',
            'terminated' => 'dark',
            'failed_partial' => 'warning',
            'failed' => 'danger',
            default => 'muted',
        };
    }

    private function formatDuration(?int $seconds): string
    {
        if ($seconds === null) {
            return '-';
        }

        if ($seconds < 60) {
            return $seconds . ' dtk';
        }

        if ($seconds < 3600) {
            return floor($seconds / 60) . ' mnt';
        }

        return floor($seconds / 3600) . ' jam ' . floor(($seconds % 3600) / 60) . ' mnt';
    }
}
