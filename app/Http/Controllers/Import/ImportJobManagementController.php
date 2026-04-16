<?php

namespace App\Http\Controllers\Import;

use App\Http\Controllers\Controller;
use App\Jobs\RunManagedReportSnapshotRebuildJob;
use App\Services\Import\ImportExecutionService;
use App\Services\Import\ImportProgressService;
use App\Support\ManagedReportSnapshotRebuildCoordinator;
use App\Support\ManagedReportSnapshotRebuildStore;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ImportJobManagementController extends Controller
{
    private const SNAPSHOT_QUEUELESS_STALE_SECONDS = 300;
    private const SNAPSHOT_RESERVED_STALE_SECONDS = 600;

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

        $managedDeleteJobs = $this->importIndexController->resolveManagedReportDeleteJobs();

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

        $summarySource = DB::table('import_jobs');
        $todayStart = now()->startOfDay();
        $snapshotJobs = $this->resolveSnapshotJobs();

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
        $executionService->run($jobId);

        return response()->json([
            'status' => 'success',
            'message' => 'Force start dijalankan. Job import diproses langsung tanpa menunggu worker queue.',
        ]);
    }

    public function forceStartSnapshot(string $rebuildId)
    {
        if (Schema::hasTable('jobs')) {
            $basename = class_basename(\App\Jobs\RunManagedReportSnapshotRebuildJob::class);
            DB::table('jobs')
                ->where('payload', 'like', '%' . $basename . '%')
                ->where('payload', 'like', '%' . $rebuildId . '%')
                ->delete();
        }

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

        if ($status === 'queued') {
            $progressService->cleanupQueuedImportJobRowsForJob($jobId);

            $progressService->markTerminated(
                $jobId,
                'Job dihentikan melalui Job Management.',
                (int) ($job->total_success ?? 0),
                (int) ($job->total_failed ?? 0)
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Job queued berhasil dihentikan.',
            ]);
        }

        $statusPayload = $progressService->getStatusPayload($jobId);
        $progressService->cacheProgress($jobId, [
            'status' => 'processing',
            'message' => 'Permintaan terminate dikirim. Worker akan menghentikan job pada checkpoint progress berikutnya.',
            'percent' => (int) ($statusPayload['percent'] ?? 0),
            'processed_rows' => (int) ($statusPayload['processed_rows'] ?? ((int) ($job->total_success ?? 0) + (int) ($job->total_failed ?? 0))),
            'total_success' => (int) ($statusPayload['total_success'] ?? $job->total_success ?? 0),
            'total_failed' => (int) ($statusPayload['total_failed'] ?? $job->total_failed ?? 0),
            'total_rows' => (int) ($statusPayload['total_rows'] ?? $job->total_files ?? 0),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Permintaan terminate dikirim ke worker.',
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
        $activeRebuildId = ManagedReportSnapshotRebuildStore::getActiveRebuildId();
        if ($activeRebuildId) {
            $snapshotIds[] = $activeRebuildId;
        }

        $pendingValue = Cache::get(ManagedReportSnapshotRebuildStore::PENDING_KEY);
        $pendingRebuildId = $this->extractSnapshotRebuildId($pendingValue);
        if ($pendingRebuildId) {
            $snapshotIds[] = $pendingRebuildId;
        }

        $queueRows = $this->snapshotRebuildQueueRows();
        foreach ($queueRows as $queueRow) {
            $rebuildId = trim((string) ($queueRow['rebuild_id'] ?? ''));
            if ($rebuildId !== '') {
                $snapshotIds[] = $rebuildId;
            }
        }

        $queueRowsByRebuildId = collect($queueRows)->keyBy('rebuild_id');
        $jobs = collect(array_values(array_unique($snapshotIds)))
            ->map(function (string $rebuildId) use ($queueRowsByRebuildId) {
                $state = $this->snapshotRebuildCoordinator->reconcile($rebuildId);
                $queueRow = $queueRowsByRebuildId->get($rebuildId);
                $state = $this->reconcileSnapshotStateWithQueueRow($rebuildId, $state, is_array($queueRow) ? $queueRow : null);
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

    private function snapshotRebuildQueueRows(): array
    {
        if (!Schema::hasTable('jobs')) {
            return [];
        }

        $configuredReportQueue = trim((string) config('queue.report_queue', 'default')) ?: 'default';
        $queues = array_values(array_unique(array_filter([$configuredReportQueue, 'default', 'reports-low'])));
        $basename = class_basename(RunManagedReportSnapshotRebuildJob::class);

        return DB::table('jobs')
            ->whereIn('queue', $queues)
            ->where('payload', 'like', '%' . $basename . '%')
            ->select(['id', 'queue', 'reserved_at', 'available_at', 'created_at', 'payload'])
            ->orderByDesc('id')
            ->get()
            ->map(function ($job): array {
                $payload = (string) ($job->payload ?? '');

                return [
                    'job_id' => (int) ($job->id ?? 0),
                    'queue' => (string) ($job->queue ?? ''),
                    'reserved' => $job->reserved_at !== null,
                    'reserved_at' => $job->reserved_at,
                    'reserved_age_seconds' => $this->queueTimestampAgeSeconds($job->reserved_at),
                    'created_at' => $job->created_at,
                    'created_age_seconds' => $this->queueTimestampAgeSeconds($job->created_at),
                    'available_at' => $job->available_at,
                    'payload' => $payload,
                    'rebuild_id' => $this->extractSnapshotRebuildIdFromPayload($payload),
                ];
            })
            ->filter(fn (array $row): bool => trim((string) ($row['rebuild_id'] ?? '')) !== '')
            ->values()
            ->all();
    }

    private function reconcileSnapshotStateWithQueueRow(string $rebuildId, ?array $state, ?array $queueRow): ?array
    {
        if ($state === null) {
            return $queueRow ? $this->makeSyntheticSnapshotState($rebuildId, $queueRow) : null;
        }

        $status = strtolower(trim((string) ($state['status'] ?? '')));
        if (in_array($status, ['completed', 'failed'], true)) {
            return $state;
        }

        $updatedAt = (string) ($state['updated_at'] ?? $state['started_at'] ?? $state['created_at'] ?? '');

        if ($queueRow === null && $this->timestampOlderThan($updatedAt, self::SNAPSHOT_QUEUELESS_STALE_SECONDS)) {
            return $this->markSnapshotStateAsFailed(
                $state,
                'Progress snapshot tidak lagi memiliki job queue aktif dan tidak bergerak terlalu lama. State dibersihkan otomatis.'
            );
        }

        if (
            $queueRow !== null
            && (bool) ($queueRow['reserved'] ?? false)
            && (int) ($queueRow['reserved_age_seconds'] ?? 0) >= self::SNAPSHOT_RESERVED_STALE_SECONDS
            && $this->timestampOlderThan($updatedAt, self::SNAPSHOT_RESERVED_STALE_SECONDS)
        ) {
            if (isset($queueRow['job_id'])) {
                DB::table('jobs')->where('id', $queueRow['job_id'])->delete();
            }

            return $this->markSnapshotStateAsFailed(
                $state,
                'Job snapshot sudah di-reserve worker tetapi progress tidak bergerak terlalu lama. Kemungkinan worker berhenti di tengah proses.'
            );
        }

        if ($queueRow !== null && (bool) ($queueRow['reserved'] ?? false) && $status === 'queued') {
            $state['status'] = 'running';
            $state['stage'] = in_array(strtolower(trim((string) ($state['stage'] ?? ''))), ['queued'], true) ? 'planning' : ($state['stage'] ?? 'planning');
            $state['queued'] = false;
            $state['message'] = trim((string) ($state['message'] ?? '')) !== ''
                ? (string) $state['message']
                : 'Snapshot rebuild sedang diproses worker queue.';
        }

        return $state;
    }

    private function makeSyntheticSnapshotState(string $rebuildId, array $queueRow): array
    {
        $timestamp = now()->toIso8601String();
        $reserved = (bool) ($queueRow['reserved'] ?? false);
        $createdAt = $this->queueTimestampToIso8601($queueRow['created_at'] ?? null) ?? $timestamp;
        $updatedAt = $reserved
            ? ($this->queueTimestampToIso8601($queueRow['reserved_at'] ?? null) ?? $createdAt)
            : $createdAt;

        return [
            'rebuild_id' => $rebuildId,
            'status' => $reserved ? 'running' : 'queued',
            'stage' => $reserved ? 'planning' : 'queued',
            'queued' => !$reserved,
            'force_rebuild' => true,
            'source' => static::class,
            'message' => $reserved
                ? 'Snapshot rebuild sedang diproses worker queue.'
                : 'Snapshot rebuild masih menunggu worker queue.',
            'progress_percent' => 0,
            'completed_units' => 0,
            'total_units' => 1,
            'build_units' => 0,
            'current_report_key' => null,
            'current_report_label' => null,
            'current_period' => null,
            'report_completed_units' => 0,
            'report_total_units' => 0,
            'reports' => [],
            'results' => [],
            'started_at' => $reserved ? $updatedAt : null,
            'finished_at' => null,
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ];
    }

    private function markSnapshotStateAsFailed(array $state, string $message): array
    {
        $rebuildId = trim((string) ($state['rebuild_id'] ?? ''));
        $failedState = ManagedReportSnapshotRebuildStore::putState(array_merge($state, [
            'status' => 'failed',
            'stage' => 'failed',
            'queued' => false,
            'message' => $message,
            'error' => $message,
            'finished_at' => now()->toIso8601String(),
        ]));

        if ($rebuildId !== '' && ManagedReportSnapshotRebuildStore::getActiveRebuildId() === $rebuildId) {
            ManagedReportSnapshotRebuildStore::forgetActiveRebuildId();
        }

        $pendingRebuildId = $this->extractSnapshotRebuildId(Cache::get(ManagedReportSnapshotRebuildStore::PENDING_KEY));
        if ($rebuildId !== '' && $pendingRebuildId === $rebuildId) {
            Cache::forget(ManagedReportSnapshotRebuildStore::PENDING_KEY);
        }

        return $failedState;
    }

    private function extractSnapshotRebuildIdFromPayload(string $payload): ?string
    {
        $candidate = '';

        if (preg_match('/rebuildId";s:\d+:"([0-9a-f\-]{36})"/i', $payload, $matches) === 1) {
            $candidate = (string) ($matches[1] ?? '');
        }

        if ($candidate === '' && preg_match('/"rebuildId":"([0-9a-f\-]{36})"/i', $payload, $matches) === 1) {
            $candidate = (string) ($matches[1] ?? '');
        }

        $candidate = trim($candidate);

        return $candidate !== '' ? $candidate : null;
    }

    private function queueTimestampAgeSeconds(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_numeric($value)) {
            return max(0, now()->timestamp - (int) $value);
        }

        $parsed = $this->safeParseDate($value);

        return $parsed ? max(0, now()->diffInSeconds($parsed)) : 0;
    }

    private function queueTimestampToIso8601(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return Carbon::createFromTimestamp((int) $value)->toIso8601String();
        }

        return $this->safeParseDate($value)?->toIso8601String();
    }

    private function timestampOlderThan(?string $value, int $seconds): bool
    {
        $parsed = $this->safeParseDate($value);

        return $parsed ? $parsed->addSeconds(max(1, $seconds))->lessThanOrEqualTo(now()) : true;
    }

    private function extractSnapshotRebuildId(mixed $value): ?string
    {
        if (is_array($value)) {
            $candidate = trim((string) ($value['rebuild_id'] ?? ''));

            return $candidate !== '' ? $candidate : null;
        }

        if (!is_string($value)) {
            return null;
        }

        $candidate = trim($value);

        return $candidate !== '' ? $candidate : null;
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

        $purgedReservedSnapshotJobs = 0;
        $staleReservedSnapshotJobs = 0;
        foreach ($reservedJobs as $job) {
            $reservedAgeSeconds = $this->queueTimestampAgeSeconds($job->reserved_at ?? null);
            if ($reservedAgeSeconds < self::SNAPSHOT_RESERVED_STALE_SECONDS) {
                continue;
            }

            $rebuildId = $this->extractSnapshotRebuildIdFromPayload((string) ($job->payload ?? ''));
            if ($rebuildId === null) {
                continue;
            }

            $state = ManagedReportSnapshotRebuildStore::getState($rebuildId);
            $status = strtolower(trim((string) ($state['status'] ?? '')));
            if ($state !== null && !in_array($status, ['completed', 'failed', 'warning'], true)) {
                $staleReservedSnapshotJobs++;
                continue;
            }

            DB::table('jobs')->where('id', (int) ($job->id ?? 0))->delete();
            $purgedReservedSnapshotJobs++;
        }

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
        $configuredQueuePending = $jobs->where('queue', $configuredReportQueue)->count();
        $oldestPending = $jobs->first();
        $oldestAgeSeconds = $oldestPending ? $this->queueRowAgeSeconds($oldestPending) : null;
        if ($pendingReportJobs === 0 && $pendingManagedDeleteJobs === 0 && $staleReservedSnapshotJobs === 0) {
            return [
                'status' => 'ok',
                'tone' => 'info',
                'message' => 'Queue report sehat. Tidak ada job report atau delete yang menunggu.',
                'configured_report_queue' => $configuredReportQueue,
                'pending_report_jobs' => 0,
                'pending_managed_delete_jobs' => 0,
                'pending_snapshot_rebuilds' => 0,
                'legacy_reports_low_pending' => 0,
                'oldest_pending_age_seconds' => 0,
                'stale_reserved_snapshot_jobs' => 0,
                'purged_reserved_snapshot_jobs' => $purgedReservedSnapshotJobs,
            ];
        }

        if ($pendingReportJobs > 0) {
            $message = sprintf(
                'Ada %d job report menunggu di queue `%s`%s.',
                $pendingReportJobs,
                $configuredReportQueue,
                $pendingSnapshotRebuilds > 0 ? " termasuk {$pendingSnapshotRebuilds} snapshot rebuild" : ''
            );

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

        if (($oldestAgeSeconds ?? 0) >= 120) {
            $message .= ' Indikasinya worker report tidak sedang mengonsumsi queue.';
        }

        return [
            'status' => 'warning',
            'tone' => 'warning',
            'message' => $message,
            'configured_report_queue' => $configuredReportQueue,
            'pending_report_jobs' => $pendingReportJobs,
            'pending_managed_delete_jobs' => $pendingManagedDeleteJobs,
            'pending_snapshot_rebuilds' => $pendingSnapshotRebuilds,
            'legacy_reports_low_pending' => $legacyReportsLowPending,
            'oldest_pending_age_seconds' => $oldestAgeSeconds ?? 0,
            'stale_reserved_snapshot_jobs' => $staleReservedSnapshotJobs,
            'purged_reserved_snapshot_jobs' => $purgedReservedSnapshotJobs,
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
