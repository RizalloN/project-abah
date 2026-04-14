<?php

namespace App\Http\Controllers\Import;

use App\Http\Controllers\Controller;
use App\Services\Import\ImportProgressService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ImportJobManagementController extends Controller
{
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
            'status' => 'nullable|string|in:all,queued,processing,completed,failed,failed_partial',
            'search' => 'nullable|string|max:255',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:24',
        ]);

        $status = (string) ($validated['status'] ?? 'all');
        $search = trim((string) ($validated['search'] ?? ''));
        $perPage = (int) ($validated['per_page'] ?? 12);

        $baseQuery = DB::table('import_jobs as ij')
            ->leftJoin('nama_report as nr', 'nr.id_report', '=', 'ij.id_report')
            ->leftJoin('users as u', 'u.id', '=', 'ij.created_by');

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
            ->orderByRaw("CASE WHEN ij.status IN ('processing','queued') THEN 0 ELSE 1 END")
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

        return response()->json([
            'status' => 'success',
            'summary' => [
                'active_jobs' => (clone $summarySource)->whereIn('status', ['queued', 'processing'])->count(),
                'queued_jobs' => (clone $summarySource)->where('status', 'queued')->count(),
                'processing_jobs' => (clone $summarySource)->where('status', 'processing')->count(),
                'today_jobs' => (clone $summarySource)->where('created_at', '>=', $todayStart)->count(),
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
            $progressService->markFailed(
                $jobId,
                'Job dihentikan melalui Job Management.',
                (int) ($job->total_success ?? 0),
                (int) ($job->total_failed ?? 0),
                ((int) ($job->total_success ?? 0) > 0 || (int) ($job->total_failed ?? 0) > 0) ? 'failed_partial' : 'failed'
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

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'queued' => 'Queued',
            'processing' => 'Processing',
            'completed' => 'Completed',
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
