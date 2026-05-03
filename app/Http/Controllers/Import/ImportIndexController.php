<?php

namespace App\Http\Controllers\Import;

use App\Http\Controllers\Controller;
use App\Jobs\RunManagedReportDeleteJob;
use App\Services\Import\ImportCleanupService;
use App\Services\Import\MySqlBulkLoadService;
use App\Support\ManagedReportLoadCoordinator;
use App\Support\ManagedReportManagementService;
use App\Support\ManagedReportDeleteRecoveryService;
use App\Support\PartitionMaintenanceService;
use App\Support\ManagedReportRecoveryCoordinator;
use App\Support\ManagedReportSnapshotRebuildCoordinator;
use App\Support\ReportDataSyncService;
use App\Support\StrictDateParser;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use App\Models\NamaReport;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class ImportIndexController extends Controller
{
    private array $tableIndexLookupCache = [];

    private const DELETE_AUDIT_TABLE = 'report_sync_audits';
    private const MANAGEMENT_MAX_GROUP_ROWS = 5000;
    private const MANAGEMENT_PERIODS_PER_PAGE = 8;
    private const DELETE_PRECHECK_LIMIT = 200000;
    private const DELETE_CHUNK_SIZE = 10000;
    private const DELETE_CHUNK_SIZE_WITH_IDENTITY = 50000;
    private const DELETE_PROGRESS_TTL_MINUTES = 60;
    private const DELETE_PROGRESS_CACHE_PREFIX = 'report_management_delete';
    private const DELETE_ACTIVE_IDS_CACHE_KEY = 'report_management_delete:active_ids';
    private const DELETE_PROCESS_LOCK_PREFIX = 'report_management_delete_lock';
    private const DELETE_PROCESS_LOCK_SECONDS = 300;
    private const DELETE_PROCESS_GRACE_SECONDS = 0;
    private const DELETE_PROCESS_STALE_SECONDS = 30;
    private const DELETE_FAIL_STALE_SECONDS = 900;
    private const DELETE_QUEUE = 'imports-high';
    private const DELETE_SYNC_QUEUE = 'imports-high';
    private const LW325_BLANK_CREATED_AT_FALLBACK_MODE = 'lw325_blank_created_at';
    private const DELETE_TICK_TIME_BUDGET_MS = 2500;
    private const DELETE_MAX_BATCHES_PER_TICK = 8;
    private const DELETE_HARD_GUARD_RATIO = 0.85;
    private const DELETE_PLAN_B_MIN_ROWS = 100000;
    private const REBUILD_FALLBACK_LOCK_PREFIX = 'report_management_rebuild_lock:';
    private const REBUILD_FALLBACK_LOCK_SECONDS = 7200;
    private const REBUILD_FALLBACK_STALE_SECONDS = 15;
    private const FULL_TABLE_TRUNCATE_SHORTCUT_TABLES = [
        'simpanan_multipn',
        'lw325_ph',
        'daily_loan_dinamis',
    ];

    private const DELETE_INDEX_HINTS = [
        'daily_loan_dinamis' => [
            'index' => 'idx_loan_periode_cab_unit',
            'indexes' => ['idx_loan_periode_cab_unit', 'idx_dld_periode_cabang_unit'],
            'period' => 'periode',
            'kanca' => 'cabang1',
            'identity' => 'uniqueid_namareport',
            'chunk_size' => 25000,
        ],
        'lw325_ph' => [
            'index' => 'idx_lw325ph_delete_scope',
            'period' => 'periode',
            'kanca' => 'kanca',
            'identity' => 'uniqueid_namareport',
            'chunk_size' => 25000,
        ],
        'jumlah_merchant_qris_detail' => [
            'index' => 'idx_jmqd_delete_scope',
            'period' => 'POSISI',
            'kanca' => 'MBDESC',
            'identity' => 'uniqueid_namareport',
        ],
        'cognos_recovery' => [
            'index' => 'idx_cognos_recovery_delete_scope',
            'period' => 'periode',
            'kanca' => 'cabang',
            'identity' => 'uniqueid_namareport',
        ],
        'cognos_ph' => [
            'index' => 'idx_cognos_ph_delete_scope',
            'period' => 'periode',
            'kanca' => 'kanca',
            'identity' => 'uniqueid_namareport',
        ],
        'performance_pis_per_produk' => [
            'index' => 'idx_pis_delete_scope',
            'period' => 'posisi',
            'kanca' => 'kanca',
            'identity' => 'uniqueid_namareport',
        ],
        'performance_kurkecil_mikro' => [
            'index' => 'idx_pkm_delete_scope',
            'period' => 'tanggal_bl',
            'kanca' => 'kanca',
            'identity' => 'uniqueid_namareport',
        ],
        'performance_mantri' => [
            'index' => 'idx_pm_delete_scope',
            'period' => 'snapshot_period',
            'kanca' => 'cabang',
            'identity' => 'uniqueid_namareport',
        ],
        'simpanan_multipn' => [
            'index' => 'idx_smp_posisi_updated',
            'period' => 'posisi',
            'kanca' => 'kantor_cabang',
            'identity' => 'uniqueid_SMPN',
            'chunk_size' => 50000,
        ],
    ];

    public function __construct(
        private readonly PartitionMaintenanceService $partitionMaintenanceService
    ) {
    }

    private function bulkLoadService(): MySqlBulkLoadService
    {
        return app(MySqlBulkLoadService::class);
    }

    private function cleanupService(): ImportCleanupService
    {
        return app(ImportCleanupService::class);
    }

    private function reportManagementService(): ManagedReportManagementService
    {
        return app(ManagedReportManagementService::class);
    }

    private function managedReportDeleteRecoveryService(): ManagedReportDeleteRecoveryService
    {
        return app(ManagedReportDeleteRecoveryService::class);
    }

    private function managedReportSnapshotRebuildCoordinator(): ManagedReportSnapshotRebuildCoordinator
    {
        return app(ManagedReportSnapshotRebuildCoordinator::class);
    }

    private function managedReportLoadCoordinator(): ManagedReportLoadCoordinator
    {
        return app(ManagedReportLoadCoordinator::class);
    }

    private function managedReportRecoveryCoordinator(): ManagedReportRecoveryCoordinator
    {
        return app(ManagedReportRecoveryCoordinator::class);
    }

    private const TEMPLATE_DEFINITIONS = [
        'input_rekanan' => [
            'label' => 'Input Rekanan',
            'filename' => 'template-input-rekanan.xlsx',
            'aliases' => ['input-rekanan', 'template-input-rekanan'],
        ],
        'nasabah_prioritas_bod_boc' => [
            'label' => 'Nasabah Prioritas BOD BOC',
            'filename' => 'template-nasabah-prioritas-bod-boc.xlsx',
            'aliases' => [
                'nasabah-prioritas-bod-boc',
                'template-nasabah-prioritas-bod-boc',
                'nasabah prioritas bod boc',
            ],
        ],
    ];

    public function index()
    {
        $reports = NamaReport::where('active', 1)
            ->orderBy('id_report')
            ->get();
        $downloadTemplates = $this->downloadTemplateOptions();

        return view('import.index', compact('reports', 'downloadTemplates'));
    }

    public function reportManagement()
    {
        $reports = NamaReport::where('active', 1)
            ->orderBy('id_report')
            ->get();
        $backupFiles = $this->managedDatabaseBackupOptions();

        return view('import.report-management', compact('reports', 'backupFiles'));
    }

    public function uploadLimits()
    {
        $postMaxBytes = $this->parseIniSizeToBytes((string) ini_get('post_max_size'));
        $uploadMaxBytes = $this->parseIniSizeToBytes((string) ini_get('upload_max_filesize'));
        $memoryLimitBytes = $this->parseIniSizeToBytes((string) ini_get('memory_limit'));

        $limits = array_filter([$postMaxBytes, $uploadMaxBytes], fn ($value) => $value > 0);
        $effectiveMaxBytes = !empty($limits) ? min($limits) : null;

        return response()->json([
            'status' => 'success',
            'post_max_size' => ini_get('post_max_size'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'max_input_time' => ini_get('max_input_time'),
            'post_max_bytes' => $postMaxBytes > 0 ? $postMaxBytes : null,
            'upload_max_bytes' => $uploadMaxBytes > 0 ? $uploadMaxBytes : null,
            'memory_limit_bytes' => $memoryLimitBytes > 0 ? $memoryLimitBytes : null,
            'effective_max_upload_bytes' => $effectiveMaxBytes,
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    public function reportManagementData(Request $request)
    {
        $validated = $request->validate([
            'id_report' => 'required|integer',
            'max_rows' => 'nullable|integer|min:100|max:20000',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:24',
        ]);

        $resolved = $this->reportManagementService()->resolveReportManagementData(
            (int) $validated['id_report'],
            $validated,
            false
        );

        if (($resolved['ok'] ?? false) && isset($resolved['payload']['table_name'])) {
            $resolved['payload']['duplicate_cleanup_available'] = $this->supportsDuplicateCleanup((string) $resolved['payload']['table_name']);
        }

        return response()->json($resolved['payload'], (int) ($resolved['status_code'] ?? 200));
    }

    public function getQueueStatus()
    {
        $connection = config('queue.default');
        
        $defaultCount = 0;
        $importsHighCount = 0;
        $failedCount = 0;
        $recentJobs = [];
        $failedJobsList = [];

        if ($connection === 'database') {
            $defaultCount = DB::table('jobs')->where('queue', 'default')->count();
            $importsHighCount = DB::table('jobs')->where('queue', 'imports-high')->count();
            $failedCount = DB::table('failed_jobs')->count();

            // Fetch recent jobs (pending & processing)
            $rawJobs = DB::table('jobs')
                ->orderBy('created_at', 'desc')
                ->take(15)
                ->get();

            foreach ($rawJobs as $job) {
                $recentJobs[] = [
                    'id' => $job->id,
                    'queue' => $job->queue,
                    'name' => $this->parseJobName($job->payload),
                    'attempts' => $job->attempts,
                    'status' => $job->reserved_at ? 'Processing' : 'Waiting',
                    'created_at' => $job->created_at ? date('H:i:s', $job->created_at) : '-',
                ];
            }

            // Fetch recent failed jobs
            $rawFailed = DB::table('failed_jobs')
                ->orderBy('failed_at', 'desc')
                ->take(15)
                ->get();

            foreach ($rawFailed as $f) {
                $failedJobsList[] = [
                    'id' => $f->id,
                    'queue' => $f->queue,
                    'name' => $this->parseJobName($f->payload),
                    'failed_at' => date('Y-m-d H:i:s', strtotime((string)$f->failed_at)),
                    'error' => substr((string)$f->exception, 0, 150),
                ];
            }
        }

        $latestReservedDefault = $connection === 'database' 
            ? DB::table('jobs')->where('queue', 'default')->whereNotNull('reserved_at')->max('reserved_at')
            : null;
        
        $latestReservedHigh = $connection === 'database' 
            ? DB::table('jobs')->where('queue', 'imports-high')->whereNotNull('reserved_at')->max('reserved_at')
            : null;

        $isWorkerStale = true;
        $latestActivity = max((int)$latestReservedDefault, (int)$latestReservedHigh);
        
        if ($latestActivity > 0) {
            $isWorkerStale = (time() - $latestActivity) > 300;
        }

        return response()->json([
            'status' => 'success',
            'connection' => $connection,
            'queues' => [
                'default' => [
                    'count' => $defaultCount,
                    'label' => 'Report Queue',
                ],
                'imports-high' => [
                    'count' => $importsHighCount,
                    'label' => 'Managed Delete Queue',
                ],
            ],
            'failed_jobs_count' => $failedCount,
            'recent_jobs' => $recentJobs,
            'detailed_failed_jobs' => $failedJobsList,
            'worker_status' => [
                'is_active' => !$isWorkerStale || ($defaultCount === 0 && $importsHighCount === 0),
                'latest_activity' => $latestActivity > 0 ? date('Y-m-d H:i:s', $latestActivity) : null,
            ]
        ]);
    }

    private function parseJobName(?string $payload): string
    {
        if (!$payload) return 'Unknown Job';
        
        $data = json_decode($payload, true);
        if (!$data) return 'Invalid Payload';
        
        if (isset($data['displayName'])) {
            $name = $data['displayName'];
            // Simplify App\Jobs\Name to just Name
            return str_replace('App\\Jobs\\', '', $name);
        }
        
        if (isset($data['data']['commandName'])) {
            return str_replace('App\\Jobs\\', '', (string)$data['data']['commandName']);
        }
        
        return 'Anonymous Job';
    }

    public function startManagedReportLoad(Request $request)
    {
        $validated = $request->validate([
            'id_report' => 'required|integer',
            'max_rows' => 'nullable|integer|min:100|max:20000',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:24',
        ]);

        $resolved = $this->managedReportLoadCoordinator()->queue(
            (int) $validated['id_report'],
            $validated,
            static::class
        );

        return response()->json($resolved['payload'], (int) ($resolved['status_code'] ?? 200));
    }

    public function managedReportLoadStatus(string $loadId)
    {
        $resolved = $this->managedReportLoadCoordinator()->status($loadId);

        return response()->json($resolved['payload'], (int) ($resolved['status_code'] ?? 200));
    }

    public function rebuildManagedReportSnapshots(Request $request)
    {
        $validated = $request->validate([
            'force_rebuild' => 'nullable|boolean',
        ]);
        $resolved = $this->managedReportSnapshotRebuildCoordinator()->queue(
            (bool) ($validated['force_rebuild'] ?? false),
            static::class
        );

        return response()->json($resolved['payload'], (int) ($resolved['status_code'] ?? 200));
    }

    public function managedReportRebuildStatus(string $rebuildId)
    {
        $resolved = $this->managedReportSnapshotRebuildCoordinator()->status($rebuildId);

        return response()->json($resolved['payload'], (int) ($resolved['status_code'] ?? 200));
    }

    public function startManagedReportRecovery(Request $request)
    {
        $validated = $request->validate([
            'id_report' => 'required|integer',
            'backup_path' => 'required|string|max:2048',
        ]);

        $resolved = $this->managedReportRecoveryCoordinator()->queue(
            (int) $validated['id_report'],
            (string) $validated['backup_path'],
            static::class
        );

        return response()->json($resolved['payload'], (int) ($resolved['status_code'] ?? 200));
    }

    public function managedReportRecoveryStatus(string $recoveryId)
    {
        $resolved = $this->managedReportRecoveryCoordinator()->status($recoveryId);

        return response()->json($resolved['payload'], (int) ($resolved['status_code'] ?? 200));
    }

    public function startForceSyncSnapshots(Request $request)
    {
        try {
            $validated = $request->validate([
                'period' => 'required|string|regex:/^\d{4}-\d{2}-\d{2}$/',
            ]);

            $period = (string) $validated['period'];

            if (!$this->isValidDateString($period)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Format periode tidak valid. Gunakan: YYYY-MM-DD',
                    'sync_id' => null,
                ], 422);
            }

            $syncId = Str::uuid()->toString();
            $cacheKey = "snapshot_force_sync:{$syncId}";

            Cache::put($cacheKey, [
                'sync_id' => $syncId,
                'period' => $period,
                'status' => 'running',
                'progress' => 0,
                'total_tables' => 6,
                'completed_tables' => 0,
                'failed_tables' => 0,
                'message' => 'Memulai sinkronisasi snapshot untuk semua tabel...',
                'started_at' => now()->toIso8601String(),
                'updated_at' => now()->toIso8601String(),
            ], now()->addHours(6));

            Artisan::queue('snapshot:force-sync', [
                '--period' => $period,
            ])->onQueue('imports-high');

            Log::info('Force sync snapshots queued', [
                'sync_id' => $syncId,
                'period' => $period,
                'source' => 'ImportIndexController::startForceSyncSnapshots',
            ]);

            return response()->json([
                'status' => 'queued',
                'message' => "Sinkronisasi snapshot untuk periode {$period} telah di-queue.",
                'sync_id' => $syncId,
                'period' => $period,
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to queue force sync snapshots', [
                'message' => $e->getMessage(),
                'period' => $request->input('period'),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memulai sinkronisasi: ' . $e->getMessage(),
                'sync_id' => null,
            ], 500);
        }
    }

    public function forceSyncSnapshotsStatus(string $syncId)
    {
        try {
            $cacheKey = "snapshot_force_sync:{$syncId}";
            $state = Cache::get($cacheKey);

            if (!$state) {
                return response()->json([
                    'status' => 'not_found',
                    'message' => 'Sync ID tidak ditemukan atau sudah expired.',
                    'sync_id' => $syncId,
                ], 404);
            }

            return response()->json($state);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'sync_id' => $syncId,
            ], 500);
        }
    }

    private function isValidDateString(string $dateString): bool
    {
        try {
            Carbon::createFromFormat('Y-m-d', $dateString);
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function deleteManagedReportRows(Request $request)
    {
        [$prepared, $errorResponse] = $this->prepareManagedDelete($request);
        if ($errorResponse !== null) {
            return $errorResponse;
        }

        $syncService = app(ReportDataSyncService::class);
        $maintenanceMode = $syncService->resolvePostDeleteMaintenanceMode((string) ($prepared['table_name'] ?? ''));

        if ($prepared['candidate_rows'] <= 0) {
            return response()->json($this->formatDeleteStateResponse([
                'status' => 'completed',
                'stage' => 'completed',
                'batch_state' => 'completed',
                'delete_id' => null,
                'table_name' => $prepared['table_name'],
                'total_rows' => 0,
                'deleted_rows' => 0,
                'remaining_rows' => 0,
                'chunk_size' => $this->resolveDeleteChunkSize($prepared['table_name'], $prepared['identity_column'] ?? null),
                'current_scope_index' => 0,
                'scopes' => $prepared['scopes'] ?? [],
                'created_at' => now()->toIso8601String(),
                'updated_at' => now()->toIso8601String(),
                'message' => 'Tidak ada baris yang cocok dengan filter.',
                'cleanup' => null,
            ]));
        }

        if ($prepared['candidate_rows'] > self::DELETE_PRECHECK_LIMIT && !$prepared['force']) {
            return response()->json([
                'status' => 'warning',
                'requires_force' => true,
                'table_name' => $prepared['table_name'],
                'candidate_rows' => (int) $prepared['candidate_rows'],
                'message' => 'Data yang akan dihapus sangat besar. Ulangi request dengan `force=true` untuk melanjutkan.',
            ]);
        }

        $tableTotalRows = max(0, (int) ($prepared['table_total_rows'] ?? 0));
        $candidateRows = max(0, (int) ($prepared['candidate_rows'] ?? 0));
        $deleteRatio = $tableTotalRows > 0 ? ($candidateRows / $tableTotalRows) : 0.0;
        $isHighImpactDelete = $tableTotalRows > 0 && $deleteRatio >= self::DELETE_HARD_GUARD_RATIO;
        $isPotentialFullDelete = $tableTotalRows > 0 && $candidateRows >= $tableTotalRows;

        if ($isHighImpactDelete && !$prepared['hard_force']) {
            $ratioPercent = (int) round($deleteRatio * 100);
            return response()->json([
                'status' => 'warning',
                'requires_hard_force' => true,
                'full_table_scope' => $isPotentialFullDelete,
                'table_name' => $prepared['table_name'],
                'candidate_rows' => $candidateRows,
                'table_total_rows' => $tableTotalRows,
                'delete_ratio_percent' => $ratioPercent,
                'message' => $isPotentialFullDelete
                    ? 'Guard keamanan aktif: scope delete menyentuh seluruh tabel. Kirim `hard_force=true` untuk konfirmasi final.'
                    : 'Guard keamanan aktif: scope delete berdampak sangat besar pada tabel. Kirim `hard_force=true` untuk konfirmasi final.',
            ]);
        }

        if ($this->shouldUseManagedDeleteFullTableShortcut($prepared)) {
            return response()->json($this->formatDeleteStateResponse(
                $this->executeManagedFullTableDeleteShortcut($prepared, $syncService, $maintenanceMode)
            ));
        }

        if ($maintenanceMode === 'lightweight') {
            return response()->json($this->formatDeleteStateResponse(
                $this->executeManagedLightweightDelete($prepared, $syncService)
            ));
        }

        $deleteId = (string) Str::uuid();
        $state = [
            'delete_id' => $deleteId,
            'status' => 'running',
            'stage' => 'queued',
            'batch_state' => 'queued',
            'message' => 'Delete dimulai. Sistem akan memproses langsung dan fallback otomatis bila diperlukan...',
            'table_name' => $prepared['table_name'],
            'id_report' => $prepared['id_report'],
            'period_column' => $prepared['period_column'],
            'kanca_column' => $prepared['kanca_column'],
            'scopes' => $prepared['scopes'],
            'period_filter' => $prepared['period_filter'],
            'kanca_filter' => $prepared['kanca_filter'],
            'period_is_null' => $prepared['period_is_null'],
            'kanca_is_null' => $prepared['kanca_is_null'],
            'period_hint' => $prepared['period_hint'],
            'skip_derived_sync' => (bool) ($prepared['skip_derived_sync'] ?? false),
            'skip_snapshot_cleanup' => (bool) ($prepared['skip_snapshot_cleanup'] ?? false),
            'delete_plan' => (string) ($prepared['delete_plan'] ?? 'normal'),
            'problem_signature' => $prepared['problem_signature'] ?? null,
            'full_table_scope' => (bool) ($prepared['full_table_scope'] ?? false),
            'identity_column' => $prepared['identity_column'],
            'total_rows' => (int) $prepared['candidate_rows'],
            'deleted_rows' => 0,
            'remaining_rows' => (int) $prepared['candidate_rows'],
            'chunk_size' => $this->resolveDeleteChunkSize($prepared['table_name'], $prepared['identity_column']),
            'current_scope_index' => 0,
            'is_waiting_on_batch' => false,
            'active_batch_size' => 0,
            'last_batch_deleted_rows' => 0,
            'last_batch_started_at' => null,
            'last_batch_finished_at' => null,
            'sync_periods' => [],
            'cleanup' => null,
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ];

        $this->putDeleteState($deleteId, $state);

        try {
            RunManagedReportDeleteJob::dispatch($deleteId)->onQueue(self::DELETE_QUEUE);
        } catch (Throwable $e) {
            Log::warning('Gagal dispatch managed report delete job: ' . $e->getMessage(), [
                'delete_id' => $deleteId,
                'table_name' => $prepared['table_name'],
                'exception_class' => $e::class,
            ]);

            // Queue insert can fail when the worker backend is temporarily unavailable.
            // Keep the delete state alive so the HTTP fallback endpoint can continue processing.
            $state['message'] = 'Queue tidak tersedia. Delete dialihkan ke fallback controller dan akan diproses langsung dari halaman ini.';
            $state['queue_dispatch_error'] = $e->getMessage();
            $state['queue_dispatch_error_code'] = $this->resolveManagedDeleteErrorCode($e);
            $state['updated_at'] = now()->toIso8601String();
            $this->putDeleteState($deleteId, $state);

            return response()->json($this->formatDeleteStateResponse($state, [
                'message' => 'Queue tidak tersedia. Delete dialihkan ke fallback controller.',
            ]));
        }

        return response()->json($this->formatDeleteStateResponse($state, [
            'message' => 'Delete dimulai. Sistem akan memproses penghapusan di background.',
        ]));
    }

    public function deleteManagedReportDuplicates(Request $request, ReportDataSyncService $syncService)
    {
        $validated = $request->validate([
            'id_report' => 'required|integer',
        ]);

        $report = NamaReport::where('active', 1)
            ->where('id_report', (int) $validated['id_report'])
            ->first();

        if (!$report) {
            return response()->json([
                'status' => 'error',
                'message' => 'Report tidak ditemukan.',
            ], 404);
        }

        $tableName = trim((string) ($report->table_name ?? ''));
        if (!$this->supportsDuplicateCleanup($tableName)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Hapus duplikat tidak didukung untuk report ini.',
            ], 422);
        }

        if ($tableName === '' || !Schema::hasTable($tableName)) {
            return response()->json([
                'status' => 'error',
                'message' => "Tabel `{$tableName}` tidak ditemukan.",
            ], 404);
        }

        $fingerprintColumns = $this->getDuplicateFingerprintColumns($tableName);
        $identityColumn = $this->getDuplicateIdentityColumn($tableName);
        $requiredColumns = array_merge($fingerprintColumns, [$identityColumn, 'created_at']);
        foreach ($requiredColumns as $column) {
            if (!Schema::hasColumn($tableName, $column)) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Kolom `{$column}` tidak tersedia untuk pembersihan duplikat.",
                ], 422);
            }
        }

        [$deleteSql, $periodSql] = $this->buildDuplicateCleanupQueries($tableName);
        $startedAt = microtime(true);
        $affectedPeriods = [];
        $deletedRows = 0;

        try {
            DB::transaction(function () use ($deleteSql, $periodSql, &$affectedPeriods, &$deletedRows): void {
                $periodRows = DB::select($periodSql);
                $affectedPeriods = array_values(array_filter(array_unique(array_map(
                    static fn ($row): string => trim((string) ($row->period ?? '')),
                    $periodRows
                )), static fn (string $period): bool => $period !== ''));

                $deletedRows = (int) DB::affectingStatement($deleteSql);
            });
        } catch (Throwable $e) {
            Log::warning("Hapus duplikat untuk `{$tableName}` gagal: " . $e->getMessage(), [
                'table_name' => $tableName,
                'report_id' => (int) $validated['id_report'],
                'exception_class' => $e::class,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menghapus duplikat: ' . $e->getMessage(),
            ], 500);
        }

        if ($deletedRows <= 0) {
            $syncService->syncAfterDeleteLightweight(
                $tableName,
                null,
                static::class . '::deleteManagedReportDuplicates'
            );

            return response()->json([
                'status' => 'success',
                'table_name' => $tableName,
                'deleted_rows' => 0,
                'duplicate_groups' => 0,
                'affected_periods' => [],
                'message' => 'Tidak ditemukan duplikat untuk dibersihkan.',
                'progress_percent' => 100,
                'stage' => 'completed',
                'cleanup' => [
                    'mode' => 'dedupe_exact',
                    'periods' => [],
                    'duration_ms' => $this->elapsedMs($startedAt),
                ],
            ]);
        }

        $syncWarnings = [];
        $cleanupService = $this->cleanupService();
        try {
            if (empty($affectedPeriods)) {
                $syncService->syncAfterDeleteLightweight(
                    $tableName,
                    null,
                    static::class . '::deleteManagedReportDuplicates'
                );
            } else {
                foreach ($affectedPeriods as $period) {
                    $syncService->cleanupDerivedArtifactsAfterDelete(
                        $tableName,
                        $period,
                        static::class . '::deleteManagedReportDuplicates'
                    );
                    $cleanupService->dispatchSnapshotRefresh(
                        $tableName,
                        $period,
                        static::class . '::deleteManagedReportDuplicates',
                        self::DELETE_SYNC_QUEUE
                    );
                }
            }
        } catch (Throwable $e) {
            $syncWarnings[] = $e->getMessage();
            Log::warning('Sinkronisasi report setelah hapus duplikat Simpanan MultiPN gagal: ' . $e->getMessage(), [
                'table_name' => $tableName,
                'affected_periods' => $affectedPeriods,
            ]);
        }

        $message = 'Berhasil menghapus ' . number_format($deletedRows, 0, ',', '.') . ' baris duplikat Simpanan MultiPN.'
            . (!empty($affectedPeriods)
                ? ' Periode terdampak: ' . implode(', ', $affectedPeriods) . '. Refresh snapshot prioritas tinggi dijadwalkan per periode.'
                : ' Baris terdampak tidak memiliki periode eksplisit, jadi sistem hanya menyegarkan statistik sumber dan cache tanpa rebuild snapshot global.');

        if (!empty($syncWarnings)) {
            return response()->json([
                'status' => 'warning',
                'table_name' => $tableName,
                'deleted_rows' => $deletedRows,
                'duplicate_groups' => count($affectedPeriods),
                'affected_periods' => $affectedPeriods,
                'message' => $message . ' Sinkronisasi lanjutan memiliki catatan: ' . implode(' ', $syncWarnings),
                'progress_percent' => 100,
                'stage' => 'completed',
                'cleanup' => [
                    'mode' => 'dedupe_exact',
                    'periods' => $affectedPeriods,
                    'duration_ms' => $this->elapsedMs($startedAt),
                ],
            ]);
        }

        return response()->json([
            'status' => 'success',
            'table_name' => $tableName,
            'deleted_rows' => $deletedRows,
            'duplicate_groups' => count($affectedPeriods),
            'affected_periods' => $affectedPeriods,
            'message' => $message,
            'progress_percent' => 100,
            'stage' => 'completed',
            'cleanup' => [
                'mode' => 'dedupe_exact',
                'periods' => $affectedPeriods,
                'duration_ms' => $this->elapsedMs($startedAt),
            ],
        ]);
    }

    private function executeManagedLightweightDelete(array $prepared, ReportDataSyncService $syncService): array
    {
        $tableName = (string) ($prepared['table_name'] ?? '');
        $periodColumn = $prepared['period_column'] ?? null;
        $kancaColumn = $prepared['kanca_column'] ?? null;
        $identityColumn = $prepared['identity_column'] ?? null;
        $scopes = is_array($prepared['scopes'] ?? null) ? $prepared['scopes'] : [];
        $deletedRows = 0;

        try {
            foreach ($scopes as $scope) {
                if (!is_array($scope)) {
                    continue;
                }

                [$scopeQuery, $hasWhereClause] = $this->buildDeleteScopeQueryFromScopes(
                    $tableName,
                    $periodColumn,
                    $kancaColumn,
                    [$scope]
                );

                if (!$hasWhereClause) {
                    continue;
                }

                $iterationsLeft = 250;
                do {
                    $affected = $this->deleteScopedRows(
                        $tableName,
                        $scopeQuery,
                        $identityColumn,
                        $this->resolveDeleteChunkSize($tableName, $identityColumn),
                        $periodColumn,
                        $kancaColumn,
                        $scope
                    );

                    if ($affected <= 0) {
                        break;
                    }

                    $deletedRows += $affected;
                    $iterationsLeft--;

                    if ($iterationsLeft <= 0) {
                        throw new \RuntimeException('Delete ringan berhenti karena batas iterasi tercapai.');
                    }
                } while ((int) (clone $scopeQuery)->count() > 0);
            }
        } catch (Throwable $e) {
            $failure = $this->buildManagedDeleteFailure([
                'deleted_rows' => $deletedRows,
            ], $e);

            Log::warning('Delete ringan report gagal sebelum selesai: ' . $e->getMessage(), [
                'table_name' => $tableName,
                'period_hint' => $prepared['period_hint'] ?? null,
                'deleted_rows' => $deletedRows,
                'exception_class' => $e::class,
            ]);

            return [
                'status' => $deletedRows > 0 ? 'warning' : 'failed',
                'delete_id' => null,
                'stage' => 'failed',
                'batch_state' => 'failed',
                'table_name' => $tableName,
                'total_rows' => (int) ($prepared['candidate_rows'] ?? 0),
                'deleted_rows' => $deletedRows,
                'remaining_rows' => max(0, (int) ($prepared['candidate_rows'] ?? 0) - $deletedRows),
                'chunk_size' => $this->resolveDeleteChunkSize($tableName, $identityColumn),
                'current_scope_index' => 0,
                'scope_count' => count($scopes),
                'is_waiting_on_batch' => false,
                'active_batch_size' => 0,
                'last_batch_deleted_rows' => $deletedRows,
                'last_batch_started_at' => null,
                'last_batch_finished_at' => now()->toIso8601String(),
                'created_at' => now()->toIso8601String(),
                'updated_at' => now()->toIso8601String(),
                'progress_percent' => $deletedRows > 0
                    ? min(100, (int) floor(($deletedRows / max(1, (int) ($prepared['candidate_rows'] ?? 0))) * 100))
                    : 0,
                'message' => $failure['message'],
                'error' => $failure['error'],
                'error_code' => $failure['error_code'],
                'cleanup' => null,
                'can_process_fallback' => false,
                'fallback_stale_seconds' => self::DELETE_PROCESS_STALE_SECONDS,
            ];
        }

        $syncService->syncAfterDeleteLightweight(
            $tableName,
            $prepared['period_hint'] ?? null,
            static::class . '::deleteManagedReportRows'
        );

        return [
            'status' => 'success',
            'delete_id' => null,
            'stage' => 'completed',
            'batch_state' => 'completed',
            'table_name' => $tableName,
            'total_rows' => (int) ($prepared['candidate_rows'] ?? 0),
            'deleted_rows' => $deletedRows,
            'remaining_rows' => 0,
            'chunk_size' => $this->resolveDeleteChunkSize($tableName, $identityColumn),
            'current_scope_index' => 0,
            'scope_count' => count($scopes),
            'is_waiting_on_batch' => false,
            'active_batch_size' => 0,
            'last_batch_deleted_rows' => $deletedRows,
            'last_batch_started_at' => null,
            'last_batch_finished_at' => now()->toIso8601String(),
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
            'progress_percent' => 100,
            'message' => 'Delete selesai. Report ini tidak menggunakan snapshot/index; statistik dan cache sudah disegarkan.',
            'error' => null,
            'error_code' => null,
            'cleanup' => [
                'mode' => 'lightweight',
                'reason' => 'no_snapshot_report',
            ],
            'can_process_fallback' => false,
            'fallback_stale_seconds' => self::DELETE_PROCESS_STALE_SECONDS,
        ];
    }

    private function supportsDuplicateCleanup(string $tableName): bool
    {
        return in_array(strtolower(trim($tableName)), [
            'simpanan_multipn',
            'daily_loan_dinamis',
        ], true);
    }

    /**
     * @return array<int, string>
     */
    private function getDuplicateFingerprintColumns(string $tableName): array
    {
        $tableName = strtolower(trim($tableName));

        if ($tableName === 'simpanan_multipn') {
            return [
                'posisi',
                'regional_office',
                'kantor_cabang',
                'unit_kerja',
                'CIFNO',
                'no_rekening',
                'jenis_simpanan',
                'status',
                'saldo_idr',
            ];
        }

        if ($tableName === 'daily_loan_dinamis') {
            return [
                'periode',
                'kode_kanwil1',
                'kode_cabang1',
                'branch1',
                'unit1',
                'cifno',
                'nomor_rekening1',
                'status_rekening1',
                'nama_debitur1',
                'baki_debet1',
                'kolek',
            ];
        }

        return [];
    }

    private function getDuplicateIdentityColumn(string $tableName): string
    {
        $tableName = strtolower(trim($tableName));
        if ($tableName === 'simpanan_multipn') {
            return 'uniqueid_SMPN';
        }

        return 'uniqueid_namareport';
    }

    private function getDuplicatePeriodColumn(string $tableName): string
    {
        $tableName = strtolower(trim($tableName));
        if ($tableName === 'simpanan_multipn') {
            return 'posisi';
        }

        return 'periode';
    }

    private function buildDuplicateKeepSignatureExpression(string $tableName, string $alias): string
    {
        $identity = $this->getDuplicateIdentityColumn($tableName);

        // OPTIMIZED: Avoid expensive DATE_FORMAT on millions of rows
        return "CONCAT(COALESCE({$alias}.`created_at`, '1000-01-01 00:00:00'), '|', COALESCE({$alias}.`{$identity}`, ''))";
    }

    /**
     * @param array<int, string> $columns
     */
    private function buildNullSafeColumnJoinConditions(array $columns, string $leftAlias, string $rightAlias): string
    {
        return implode(' AND ', array_map(
            static fn (string $column): string => "{$leftAlias}.`{$column}` <=> {$rightAlias}.`{$column}`",
            $columns
        ));
    }

    /**
     * @return array{0:string,1:string}
     */
    private function buildDuplicateCleanupQueries(string $tableName): array
    {
        $columns = $this->getDuplicateFingerprintColumns($tableName);
        $periodColumn = $this->getDuplicatePeriodColumn($tableName);

        $groupColumns = implode(', ', array_map(
            static fn (string $column): string => "s.`{$column}`",
            $columns
        ));
        $joinConditions = $this->buildNullSafeColumnJoinConditions($columns, 't', 'd');
        $keepSignature = $this->buildDuplicateKeepSignatureExpression($tableName, 't');
        $groupKeepSignature = $this->buildDuplicateKeepSignatureExpression($tableName, 's');
        $duplicateGroupsSql = "SELECT {$groupColumns}, MIN({$groupKeepSignature}) AS keep_signature, COUNT(*) AS duplicate_count FROM `{$tableName}` s GROUP BY {$groupColumns} HAVING COUNT(*) > 1";
        $deleteWhereClause = "{$keepSignature} <> d.keep_signature";

        return [
            "DELETE t FROM `{$tableName}` t INNER JOIN ({$duplicateGroupsSql}) d ON {$joinConditions} WHERE {$deleteWhereClause}",
            "SELECT DISTINCT t.`{$periodColumn}` AS period FROM `{$tableName}` t INNER JOIN ({$duplicateGroupsSql}) d ON {$joinConditions} WHERE {$deleteWhereClause}",
        ];
    }

    private function shouldUseManagedDeleteFullTableShortcut(array $prepared): bool
    {
        $tableName = strtolower(trim((string) ($prepared['table_name'] ?? '')));
        if (!in_array($tableName, self::FULL_TABLE_TRUNCATE_SHORTCUT_TABLES, true)) {
            return false;
        }

        $candidateRows = max(0, (int) ($prepared['candidate_rows'] ?? 0));
        $tableTotalRows = max(0, (int) ($prepared['table_total_rows'] ?? 0));

        return $candidateRows > 0
            && $tableTotalRows > 0
            && $candidateRows >= $tableTotalRows
            && (bool) ($prepared['hard_force'] ?? false);
    }

    private function executeManagedFullTableDeleteShortcut(array $prepared, ReportDataSyncService $syncService, string $maintenanceMode): array
    {
        $tableName = (string) ($prepared['table_name'] ?? '');
        $candidateRows = max(0, (int) ($prepared['candidate_rows'] ?? 0));
        $periodHint = $prepared['period_hint'] ?? null;
        $identityColumn = $prepared['identity_column'] ?? null;
        $scopeCount = count(is_array($prepared['scopes'] ?? null) ? $prepared['scopes'] : []);
        $startedAt = microtime(true);
        $sourceDeleted = false;
        $strategy = $this->supportsNativeDeleteTruncateShortcut()
            ? 'full_table_truncate'
            : 'full_table_delete_fallback';
        $source = static::class . '::executeManagedFullTableDeleteShortcut';

        $this->writeManagedDeleteAudit($tableName, $periodHint, 'managed_delete_shortcut_prepare', 'success', [
            'affected_rows' => $candidateRows,
            'context' => [
                'strategy' => $strategy,
                'scope_count' => $scopeCount,
                'maintenance_mode' => $maintenanceMode,
            ],
        ]);

        try {
            $this->bulkLoadService()->assertTransactionalTable($tableName, 'delete data report');

            $this->bulkLoadService()->withTableWriteLock($tableName, function () use ($tableName, &$sourceDeleted): void {
                $this->truncateManagedDeleteTable($tableName);
                $sourceDeleted = true;
            });

            $cleanup = [
                'mode' => 'lightweight',
                'reason' => 'full_table_truncate_shortcut',
            ];
            $message = 'Seluruh tabel berhasil dikosongkan cepat. Statistik dan cache sudah disegarkan.';

            if ($maintenanceMode === 'lightweight') {
                $syncService->syncAfterDeleteLightweight($tableName, $periodHint, $source);
            } else {
                $maintenance = $this->prepareManagedDeleteSnapshotMaintenance([
                    'table_name' => $tableName,
                    'period_hint' => $periodHint,
                    'scopes' => $prepared['scopes'] ?? [],
                    'skip_derived_sync' => (bool) ($prepared['skip_derived_sync'] ?? false),
                    'skip_snapshot_cleanup' => false,
                    'full_table_scope' => true,
                ], $syncService, $source, null);

                $cleanup = $maintenance['cleanup'];
                $message = $maintenance['final_message'];
            }

            $this->writeManagedDeleteAudit($tableName, $periodHint, 'managed_delete_shortcut', 'success', [
                'duration_ms' => $this->elapsedManagedDeleteMs($startedAt),
                'affected_rows' => $candidateRows,
                'context' => [
                    'strategy' => $strategy,
                    'scope_count' => $scopeCount,
                    'maintenance_mode' => $maintenanceMode,
                ],
            ]);

            return [
                'status' => 'success',
                'delete_id' => null,
                'stage' => 'completed',
                'batch_state' => 'completed',
                'table_name' => $tableName,
                'total_rows' => $candidateRows,
                'deleted_rows' => $candidateRows,
                'remaining_rows' => 0,
                'chunk_size' => $this->resolveDeleteChunkSize($tableName, $identityColumn),
                'current_scope_index' => max(0, $scopeCount - 1),
                'scope_count' => $scopeCount,
                'is_waiting_on_batch' => false,
                'active_batch_size' => 0,
                'last_batch_deleted_rows' => $candidateRows,
                'last_batch_started_at' => now()->toIso8601String(),
                'last_batch_finished_at' => now()->toIso8601String(),
                'created_at' => now()->toIso8601String(),
                'updated_at' => now()->toIso8601String(),
                'progress_percent' => 100,
                'message' => $message,
                'error' => null,
                'error_code' => null,
                'cleanup' => $cleanup,
                'delete_strategy' => $strategy,
                'can_process_fallback' => false,
                'fallback_stale_seconds' => self::DELETE_PROCESS_STALE_SECONDS,
            ];
        } catch (Throwable $e) {
            $deletedRows = $sourceDeleted ? $candidateRows : 0;
            $failure = $this->buildManagedDeleteFailure([
                'deleted_rows' => $deletedRows,
            ], $e);

            Log::warning('Delete shortcut report gagal: ' . $e->getMessage(), [
                'table_name' => $tableName,
                'period_hint' => $periodHint,
                'strategy' => $strategy,
                'deleted_rows' => $deletedRows,
                'exception_class' => $e::class,
            ]);

            $this->writeManagedDeleteAudit($tableName, $periodHint, 'managed_delete_shortcut', 'failed', [
                'duration_ms' => $this->elapsedManagedDeleteMs($startedAt),
                'affected_rows' => $deletedRows,
                'message' => $e->getMessage(),
                'context' => [
                    'strategy' => $strategy,
                    'scope_count' => $scopeCount,
                    'maintenance_mode' => $maintenanceMode,
                    'source_deleted' => $sourceDeleted,
                ],
            ]);

            return [
                'status' => $deletedRows > 0 ? 'warning' : 'failed',
                'delete_id' => null,
                'stage' => 'failed',
                'batch_state' => 'failed',
                'table_name' => $tableName,
                'total_rows' => $candidateRows,
                'deleted_rows' => $deletedRows,
                'remaining_rows' => max(0, $candidateRows - $deletedRows),
                'chunk_size' => $this->resolveDeleteChunkSize($tableName, $identityColumn),
                'current_scope_index' => 0,
                'scope_count' => $scopeCount,
                'is_waiting_on_batch' => false,
                'active_batch_size' => 0,
                'last_batch_deleted_rows' => $deletedRows,
                'last_batch_started_at' => null,
                'last_batch_finished_at' => now()->toIso8601String(),
                'created_at' => now()->toIso8601String(),
                'updated_at' => now()->toIso8601String(),
                'progress_percent' => $deletedRows > 0 ? 100 : 0,
                'message' => $failure['message'],
                'error' => $failure['error'],
                'error_code' => $failure['error_code'],
                'cleanup' => $deletedRows > 0 ? [
                    'mode' => $maintenanceMode === 'lightweight' ? 'lightweight' : 'snapshot_cleanup',
                    'reason' => 'cleanup_failed_after_full_table_truncate',
                ] : null,
                'delete_strategy' => $strategy,
                'can_process_fallback' => false,
                'fallback_stale_seconds' => self::DELETE_PROCESS_STALE_SECONDS,
            ];
        }
    }

    public function processManagedReportDelete(string $deleteId, ReportDataSyncService $syncService)
    {
        try {
            $state = $this->getDeleteState($deleteId);
            if ($state === null) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Progress delete tidak ditemukan atau sudah kedaluwarsa.',
                ], 404);
            }

            if ($this->isManagedDeleteCancellationRequested($deleteId, $state)) {
                $latestState = $this->finalizeManagedDeleteCancelled($deleteId, $state);

                return response()->json($this->formatDeleteStateResponse($latestState));
            }

            if (in_array($state['status'] ?? '', ['completed', 'warning', 'failed', 'cancelled'], true)) {
                return response()->json($this->formatDeleteStateResponse($state));
            }

            if (!$this->shouldAllowManagedDeleteFallback($state)) {
                return response()->json($this->formatDeleteStateResponse($state));
            }

            if (!$this->acquireManagedDeleteProcessLock($deleteId)) {
                $latestState = $this->getDeleteState($deleteId) ?? $state;

                return response()->json($this->formatDeleteStateResponse($latestState));
            }

            try {
                $latestState = $this->getDeleteState($deleteId) ?? $state;

                if (!in_array($latestState['status'] ?? '', ['completed', 'warning', 'failed', 'cancelled'], true)) {
                    $latestState = $this->advanceManagedReportDelete($deleteId, $syncService, $latestState);
                }
            } catch (Throwable $e) {
                $latestState = $this->markManagedDeleteFailed($deleteId, $this->getDeleteState($deleteId) ?? $state, $e);
            } finally {
                $this->releaseManagedDeleteProcessLock($deleteId);
            }

            return response()->json($this->formatDeleteStateResponse($latestState));
        } catch (Throwable $e) {
            Log::error('Endpoint process managed delete gagal merespons dengan payload yang aman.', [
                'delete_id' => $deleteId,
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $state = $this->getDeleteState($deleteId);
            if (is_array($state)) {
                $failedState = $this->markManagedDeleteFailed($deleteId, $state, $e);

                return response()->json($this->formatDeleteStateResponse($failedState));
            }

            return response()->json([
                'status' => 'failed',
                'message' => 'Gagal memproses delete. Detail teknis sudah dicatat di log server.',
            ], 500);
        }
    }

    public function cancelManagedReportDelete(string $deleteId)
    {
        [$state, $queueRow] = $this->resolveManagedDeleteControlState($deleteId);
        if ($state === null) {
            return response()->json([
                'status' => 'error',
                'message' => 'Progress delete tidak ditemukan atau sudah kedaluwarsa.',
            ], 404);
        }

        if (in_array($state['status'] ?? '', ['completed', 'warning', 'failed', 'cancelled'], true)) {
            return response()->json($this->formatDeleteStateResponse($state));
        }

        $state['cancel_requested'] = true;
        $state['status'] = 'cancelling';
        $state['stage'] = 'cancelling';
        $state['batch_state'] = 'cancel_requested';
        $state['message'] = 'Pembatalan delete dikirim. Worker akan berhenti setelah batch aman selesai.';
        $state['updated_at'] = now()->toIso8601String();
        $this->putDeleteState($deleteId, $state);
        $this->releaseManagedDeleteQueueRow($queueRow);

        $state = $this->finalizeManagedDeleteCancelled($deleteId, $state);

        return response()->json($this->formatDeleteStateResponse($state, [
            'message' => 'Delete dibatalkan dengan aman.',
        ]));
    }

    public function forceStopManagedReportDelete(string $deleteId)
    {
        [$state, $queueRow] = $this->resolveManagedDeleteControlState($deleteId);
        if ($state === null) {
            return response()->json([
                'status' => 'error',
                'message' => 'Progress delete tidak ditemukan atau sudah kedaluwarsa.',
            ], 404);
        }

        if (in_array($state['status'] ?? '', ['completed', 'warning', 'failed', 'cancelled'], true)) {
            return response()->json($this->formatDeleteStateResponse($state));
        }

        $state['cancel_requested'] = true;
        $state['status'] = 'cancelling';
        $state['stage'] = 'cancelling';
        $state['batch_state'] = 'cancel_requested';
        $state['message'] = 'Force stop dikirim. Worker akan berhenti setelah batch aman selesai.';
        $state['updated_at'] = now()->toIso8601String();
        $this->putDeleteState($deleteId, $state);
        $this->releaseManagedDeleteQueueRow($queueRow);

        $state = $this->finalizeManagedDeleteCancelled($deleteId, $state);

        return response()->json($this->formatDeleteStateResponse($state));
    }

    public function managedReportDeleteStatus(string $deleteId)
    {
        try {
            $state = $this->reconcileManagedReportDeleteState($deleteId);
            if ($state === null) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Progress delete tidak ditemukan atau sudah kedaluwarsa.',
                ], 404);
            }

            return response()->json($this->formatDeleteStateResponse($state));
        } catch (Throwable $e) {
            Log::error('Endpoint status managed delete gagal merespons dengan payload yang aman.', [
                'delete_id' => $deleteId,
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $state = $this->getDeleteState($deleteId);
            if (is_array($state)) {
                $failedState = $this->markManagedDeleteFailed($deleteId, $state, $e);

                return response()->json($this->formatDeleteStateResponse($failedState));
            }

            return response()->json([
                'status' => 'failed',
                'message' => 'Gagal mengambil status proses delete. Detail teknis sudah dicatat di log server.',
            ], 500);
        }
    }

    public function reconcileManagedReportDeleteState(string $deleteId, ?ReportDataSyncService $syncService = null): ?array
    {
        $state = $this->getDeleteState($deleteId);
        if ($state === null) {
            $queueRow = $this->findManagedDeleteQueueRow($deleteId);

            return $this->reconcileManagedDeleteStateWithQueueRow($deleteId, null, $queueRow);
        }

        if (in_array($state['status'] ?? '', ['completed', 'warning', 'failed', 'cancelled'], true)) {
            $queueRow = $this->findManagedDeleteQueueRow($deleteId);

            return $this->reconcileManagedDeleteStateWithQueueRow($deleteId, $state, $queueRow);
        }

        if ($this->shouldAllowManagedDeleteFallback($state)) {
            $syncService = $syncService ?? app(ReportDataSyncService::class);

            if ($this->acquireManagedDeleteProcessLock($deleteId)) {
                try {
                    $latestState = $this->getDeleteState($deleteId) ?? $state;
                    if (!in_array($latestState['status'] ?? '', ['completed', 'warning', 'failed', 'cancelled'], true)) {
                        $latestState = $this->advanceManagedReportDelete($deleteId, $syncService, $latestState);
                        $this->putDeleteState($deleteId, $latestState);
                    }
                    $state = $latestState;
                } catch (Throwable $e) {
                    $state = $this->markManagedDeleteFailed($deleteId, $this->getDeleteState($deleteId) ?? $state, $e);
                } finally {
                    $this->releaseManagedDeleteProcessLock($deleteId);
                }
            } else {
                $state = $this->getDeleteState($deleteId) ?? $state;
            }
        }

        $queueRow = $this->findManagedDeleteQueueRow($deleteId);

        return $this->reconcileManagedDeleteStateWithQueueRow($deleteId, $state, $queueRow);
    }

    public function sweepManagedReportDeleteStates(?ReportDataSyncService $syncService = null): int
    {
        $syncService = $syncService ?? app(ReportDataSyncService::class);
        $reconciled = 0;

        foreach ($this->activeManagedDeleteIds() as $deleteId) {
            if ($this->reconcileManagedReportDeleteState($deleteId, $syncService) !== null) {
                $reconciled++;
            }
        }

        return $reconciled;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function resolveManagedReportDeleteJobs(): array
    {
        $syncService = app(ReportDataSyncService::class);
        $deleteIds = [];

        foreach ($this->activeManagedDeleteIds() as $deleteId) {
            $deleteIds[$deleteId] = true;
        }

        $queueRows = $this->managedReportDeleteQueueRows();
        foreach ($queueRows as $queueRow) {
            $deleteId = trim((string) ($queueRow['delete_id'] ?? ''));
            if ($deleteId !== '') {
                $deleteIds[$deleteId] = true;
            }
        }

        $queueRowsByDeleteId = collect($queueRows)->keyBy('delete_id');

        return collect(array_keys($deleteIds))
            ->map(function (string $deleteId) use ($syncService, $queueRowsByDeleteId): ?array {
                $state = $this->reconcileManagedReportDeleteState($deleteId, $syncService);
                $queueRow = $queueRowsByDeleteId->get($deleteId);
                $state = $this->reconcileManagedDeleteStateWithQueueRow(
                    $deleteId,
                    $state,
                    is_array($queueRow) ? $queueRow : null
                );

                if ($state === null) {
                    return null;
                }

                $createdAt = $this->safeParseDate($state['created_at'] ?? null);
                $updatedAt = $this->safeParseDate($state['updated_at'] ?? null);
                $durationSeconds = ($createdAt && $updatedAt)
                    ? max(0, $updatedAt->diffInSeconds($createdAt))
                    : null;
                $status = $this->mapManagedDeleteStatus(
                    (string) ($state['status'] ?? 'queued'),
                    (string) ($state['stage'] ?? 'queued')
                );

                return [
                    'id' => (string) ($state['delete_id'] ?? $deleteId),
                    'report_name' => 'Managed Report Delete',
                    'table_name' => (string) ($state['table_name'] ?? ''),
                    'file_name' => 'Delete Report Rows',
                    'status' => $status,
                    'status_label' => $this->statusLabel($status),
                    'status_tone' => $this->statusTone($status),
                    'percent' => max(0, min(100, (int) ($state['progress_percent'] ?? 0))),
                    'processed_rows' => max(0, (int) ($state['deleted_rows'] ?? 0)),
                    'total_rows' => max(1, (int) ($state['total_rows'] ?? 1)),
                    'total_success' => max(0, (int) ($state['deleted_rows'] ?? 0)),
                    'total_failed' => 0,
                    'message' => (string) ($state['message'] ?? 'Delete sedang diproses.'),
                    'phase' => (string) ($state['stage'] ?? ''),
                    'mode' => 'managed_delete',
                    'termination_requested' => (bool) ($state['cancel_requested'] ?? false),
                    'can_terminate' => in_array($status, ['queued', 'processing'], true),
                    'can_force_start' => false,
                    'can_delete' => false,
                    'created_by_name' => 'System',
                    'created_at' => $createdAt?->toIso8601String(),
                    'created_at_label' => $createdAt?->format('d M Y H:i:s'),
                    'updated_at' => $updatedAt?->toIso8601String(),
                    'updated_at_label' => $updatedAt?->format('d M Y H:i:s'),
                    'duration_seconds' => $durationSeconds,
                    'duration_label' => $this->formatDuration($durationSeconds),
                    'kind' => 'managed_delete',
                    'stage_label' => $this->managedDeleteStageLabel((string) ($state['stage'] ?? 'queued')),
                    'scope_count' => count($this->extractDeleteScopesFromState($state)),
                    'queue_name' => (string) (($queueRow['queue'] ?? '') ?: ''),
                    'queue_reserved' => (bool) ($queueRow['reserved'] ?? false),
                    'queue_job_id' => isset($queueRow['job_id']) ? (int) $queueRow['job_id'] : null,
                    'can_cancel' => in_array($status, ['queued', 'processing'], true),
                ];
            })
            ->filter()
            ->sortByDesc('updated_at')
            ->values()
            ->all();
    }

    public function downloadTemplate(Request $request)
    {
        $templateKey = (string) $request->query('report', '');
        $requestedFilename = (string) $request->query('file', '');
        $template = $this->resolveTemplateOption($templateKey, $requestedFilename);

        if (!$template) {
            return redirect()
                ->route('import.index')
                ->with('error', 'Template report yang dipilih tidak tersedia.');
        }

        $templatePath = resource_path('templates/import/' . $template['filename']);

        if (!is_file($templatePath)) {
            return redirect()
                ->route('import.index')
                ->with('error', 'File template untuk report tersebut belum tersedia di project.');
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Template ' . $template['label'] . ' siap diunduh.',
                'download_url' => route('import.template', [
                    'report' => $templateKey,
                    'file' => $template['filename'],
                    'download' => 1,
                ]),
                'filename' => $template['filename'],
            ]);
        }

        return response()->download(
            $templatePath,
            $template['filename'],
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }

    private function prepareManagedDelete(Request $request): array
    {
        $validated = $request->validate([
            'id_report' => 'required|integer',
            'period' => 'nullable|string|max:100',
            'kanca' => 'nullable|string|max:255',
            'period_is_null' => 'nullable|boolean',
            'kanca_is_null' => 'nullable|boolean',
            'scopes' => 'nullable|array|max:5000',
            'scopes.*.period' => 'nullable|string|max:100',
            'scopes.*.period_filter' => 'nullable|string|max:100',
            'scopes.*.period_label' => 'nullable|string|max:100',
            'scopes.*.kanca' => 'nullable|string|max:255',
            'scopes.*.kanca_filter' => 'nullable|string|max:255',
            'scopes.*.kanca_label' => 'nullable|string|max:255',
            'scopes.*.period_is_null' => 'nullable|boolean',
            'scopes.*.kanca_is_null' => 'nullable|boolean',
            'scopes.*.fallback_mode' => 'nullable|string|max:100',
            'scopes.*.fallback_period_column' => 'nullable|string|max:100',
            'scopes.*.fallback_period_filter' => 'nullable|string|max:100',
            'scopes.*.fallback_period_label' => 'nullable|string|max:100',
            'force' => 'nullable|boolean',
            'hard_force' => 'nullable|boolean',
        ]);

        $report = NamaReport::where('active', 1)
            ->where('id_report', (int) $validated['id_report'])
            ->first();

        if (!$report) {
            return [null, response()->json([
                'status' => 'error',
                'message' => 'Report tidak ditemukan.',
            ], 404)];
        }

        $tableName = trim((string) ($report->table_name ?? ''));
        if ($tableName === '' || !Schema::hasTable($tableName)) {
            return [null, response()->json([
                'status' => 'error',
                'message' => 'Tabel report tidak valid.',
            ], 422)];
        }

        try {
            $this->bulkLoadService()->assertTransactionalTable($tableName, 'delete data report');
        } catch (\RuntimeException $e) {
            return [null, response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422)];
        }

        $tableColumns = Schema::getColumnListing($tableName);
        [$periodColumn, $kancaColumn] = $this->reportManagementService()->resolveManagementScopeColumns($tableName, $tableColumns);

        $scopes = $this->normalizeDeleteScopes($validated);
        $firstScope = $scopes[0] ?? [
            'period_filter' => null,
            'kanca_filter' => null,
            'period_is_null' => false,
            'kanca_is_null' => false,
        ];
        $periodFilter = $firstScope['period_filter'];
        $kancaFilter = $firstScope['kanca_filter'];
        $periodIsNull = (bool) $firstScope['period_is_null'];
        $kancaIsNull = (bool) $firstScope['kanca_is_null'];
        $force = (bool) ($validated['force'] ?? false);
        $hardForce = (bool) ($validated['hard_force'] ?? false);

        [$baseQuery, $hasWhereClause] = $this->buildDeleteScopeQueryFromScopes(
            $tableName,
            $periodColumn,
            $kancaColumn,
            $scopes
        );

        if (!$hasWhereClause) {
            return [null, response()->json([
                'status' => 'error',
                'message' => 'Filter periode/kanca tidak valid. Tidak ada data yang dihapus.',
            ], 422)];
        }

        $identityColumn = $this->resolveIdentityColumn($tableColumns);
        $supportsFullTableDelete = $periodColumn === null && $kancaColumn === null;
        if ($identityColumn === null && !$supportsFullTableDelete && !$this->canDeleteScopesWithoutIdentity($tableName, $periodColumn, $kancaColumn, $scopes)) {
            return [null, response()->json([
                'status' => 'error',
                'message' => "Delete parsial untuk tabel `{$tableName}` membutuhkan kolom identity/unique yang stabil. Tambahkan kolom seperti `id`/`row_id` atau gunakan scope yang bisa dipangkas per partisi.",
                ], 422)];
        }

        $candidateRows = (int) (clone $baseQuery)->count();
        $tableTotalRows = (int) DB::table($tableName)->count();
        $periodHint = null;
        $skipDerivedSync = false;
        $skipSnapshotCleanup = false;
        if ($periodColumn !== null) {
            $periodCandidates = [];
            $hasNullPeriodScope = false;

            foreach ($scopes as $scope) {
                if ($this->isLw325BlankCreatedAtFallbackScope($tableName, $scope)) {
                    continue;
                }

                if ((bool) ($scope['period_is_null'] ?? false)) {
                    $hasNullPeriodScope = true;
                    continue;
                }

                $scopePeriod = $scope['period_filter'] ?? null;
                if ($scopePeriod !== null && $scopePeriod !== '') {
                    $periodCandidates[(string) $scopePeriod] = true;
                }
            }

            if (count($periodCandidates) === 1) {
                $periodHint = (string) array_key_first($periodCandidates);
            }

            if ($hasNullPeriodScope && count($periodCandidates) === 0) {
                $skipDerivedSync = true;
            }
        }

        if ($periodHint === null) {
            $skipSnapshotCleanup = true;
        }

        $plan = $this->classifyManagedDeletePlan([
            'table_name' => $tableName,
            'period_column' => $periodColumn,
            'kanca_column' => $kancaColumn,
            'scopes' => $scopes,
            'candidate_rows' => $candidateRows,
        ]);

        return [[
            'id_report' => (int) $validated['id_report'],
            'table_name' => $tableName,
            'period_column' => $periodColumn,
            'kanca_column' => $kancaColumn,
            'scopes' => $scopes,
            'period_filter' => $periodFilter,
            'kanca_filter' => $kancaFilter,
            'period_is_null' => $periodIsNull,
            'kanca_is_null' => $kancaIsNull,
            'force' => $force,
            'hard_force' => $hardForce,
            'candidate_rows' => $candidateRows,
            'table_total_rows' => $tableTotalRows,
            'full_table_scope' => $tableTotalRows > 0 && $candidateRows >= $tableTotalRows,
            'identity_column' => $identityColumn,
            'period_hint' => $periodHint,
            'skip_derived_sync' => $skipDerivedSync,
            'skip_snapshot_cleanup' => $skipSnapshotCleanup,
            'delete_plan' => $plan['delete_plan'],
            'problem_signature' => $plan['problem_signature'],
        ], null];
    }

    /**
     * @param array{table_name:string,period_column:?string,kanca_column:?string,scopes:array<int,array<string,mixed>>,candidate_rows:int} $context
     * @return array{delete_plan:string,problem_signature:?string}
     */
    private function classifyManagedDeletePlan(array $context): array
    {
        $tableName = strtolower(trim((string) ($context['table_name'] ?? '')));
        $periodColumn = $context['period_column'] ?? null;
        $kancaColumn = $context['kanca_column'] ?? null;
        $candidateRows = max(0, (int) ($context['candidate_rows'] ?? 0));
        $scopes = is_array($context['scopes'] ?? null) ? $context['scopes'] : [];

        // Plan B: any scope with blank/null period → use direct chunked recovery to avoid
        // Cartesian-variant explosion and poor index utilisation on IS NULL period queries.
        if ($periodColumn !== null && !empty($scopes)) {
            foreach ($scopes as $scope) {
                if ((bool) ($scope['period_is_null'] ?? false)) {
                    return ['delete_plan' => 'blank_period_scope', 'problem_signature' => 'blank_period_scope'];
                }
            }
        }

        if ($tableName !== 'daily_loan_dinamis' || count($scopes) !== 1 || $periodColumn === null || $kancaColumn === null) {
            return ['delete_plan' => 'normal', 'problem_signature' => null];
        }

        $scope = $scopes[0] ?? [];
        $periodFilter = trim((string) ($scope['period_filter'] ?? ''));
        $periodIsNull = (bool) ($scope['period_is_null'] ?? false);
        $kancaIsNull = (bool) ($scope['kanca_is_null'] ?? false);

        if (
            $periodFilter === ''
            || $periodIsNull
            || !$kancaIsNull
            || $candidateRows < self::DELETE_PLAN_B_MIN_ROWS
        ) {
            return ['delete_plan' => 'normal', 'problem_signature' => null];
        }

        return [
            'delete_plan' => 'recovery_blank_scope',
            'problem_signature' => 'daily_loan_blank_kanca_large_scope',
        ];
    }

    public function runManagedReportDelete(string $deleteId, ReportDataSyncService $syncService): void
    {
        $state = $this->getDeleteState($deleteId);
        if ($state === null || in_array($state['status'] ?? '', ['completed', 'warning', 'failed', 'cancelled'], true)) {
            return;
        }

        try {
            while (!in_array($state['status'] ?? '', ['completed', 'warning', 'failed', 'cancelled'], true)) {
                if ($this->isManagedDeleteCancellationRequested($deleteId, $state)) {
                    $this->finalizeManagedDeleteCancelled($deleteId, $state);
                    return;
                }

                if (!$this->acquireManagedDeleteProcessLock($deleteId)) {
                    usleep(250000);
                    $state = $this->getDeleteState($deleteId) ?? $state;
                    continue;
                }

                try {
                    $state = $this->getDeleteState($deleteId) ?? $state;
                    if ($this->isManagedDeleteCancellationRequested($deleteId, $state)) {
                        $state = $this->finalizeManagedDeleteCancelled($deleteId, $state);
                        return;
                    }
                    if (in_array($state['status'] ?? '', ['completed', 'warning', 'failed', 'cancelled'], true)) {
                        continue;
                    }

                    $state = $this->advanceManagedReportDelete($deleteId, $syncService, $state);
                } finally {
                    $this->releaseManagedDeleteProcessLock($deleteId);
                }
            }
        } catch (Throwable $e) {
            $this->markManagedDeleteFailed($deleteId, $state, $e);

            throw $e;
        }
    }

    private function advanceManagedReportDelete(string $deleteId, ReportDataSyncService $syncService, array $state): array
    {
        if ($this->isManagedDeleteCancellationRequested($deleteId, $state)) {
            return $this->finalizeManagedDeleteCancelled($deleteId, $state);
        }

        $stage = (string) ($state['stage'] ?? 'deleting');
        $maintenanceMode = $syncService->resolvePostDeleteMaintenanceMode((string) ($state['table_name'] ?? ''));

        if ($stage === 'queued') {
            if ($this->isManagedDeleteCancellationRequested($deleteId, $state)) {
                return $this->finalizeManagedDeleteCancelled($deleteId, $state);
            }

            $state['stage'] = 'deleting';
            $state['batch_state'] = 'queued';
            $state['is_waiting_on_batch'] = false;
            $state['active_batch_size'] = 0;
            $state['message'] = 'Delete keluar dari antrian dan mulai diproses...';
            $state['updated_at'] = now()->toIso8601String();
            $this->putDeleteState($deleteId, $state);
            $stage = 'deleting';
        }

        if ($stage === 'deleting') {
            if ($this->isManagedDeleteCancellationRequested($deleteId, $state)) {
                return $this->finalizeManagedDeleteCancelled($deleteId, $state);
            }

            $state = $this->markDeleteBatchPending($state);
            $this->putDeleteState($deleteId, $state);

            $state = $this->processDeleteChunk($state);

            if ($this->isManagedDeleteCancellationRequested($deleteId, $state)) {
                return $this->finalizeManagedDeleteCancelled($deleteId, $state);
            }

            if (($state['stage'] ?? null) === 'deleting') {
                $state['batch_state'] = 'deleting_committed';
                $state['message'] = sprintf(
                    'Batch selesai, menghapus %s baris. Grup %d/%d (%s).',
                    number_format((int) ($state['last_batch_deleted_rows'] ?? 0), 0, ',', '.'),
                    max(1, ((int) ($state['current_scope_index'] ?? 0)) + 1),
                    max(1, count($this->extractDeleteScopesFromState($state))),
                    $this->describeDeleteScope(
                        $this->extractDeleteScopesFromState($state)[max(0, (int) ($state['current_scope_index'] ?? 0))] ?? []
                    )
                );
                $state['updated_at'] = now()->toIso8601String();
                $this->putDeleteState($deleteId, $state);
            }

            $stage = (string) ($state['stage'] ?? $stage);

            if ($stage === 'deleting') {
                return $state;
            }
        }

        if ($stage === 'cleanup') {
            if ($this->isManagedDeleteCancellationRequested($deleteId, $state)) {
                return $this->finalizeManagedDeleteCancelled($deleteId, $state);
            }

            $source = static::class . '::runManagedReportDelete';
            $deletedRows = max(0, (int) ($state['deleted_rows'] ?? 0));

            try {
                if ($maintenanceMode === 'lightweight') {
                    $syncService->syncAfterDeleteLightweight(
                        (string) $state['table_name'],
                        $state['period_hint'] ?? null,
                        $source,
                        $deleteId
                    );

                    $state['cleanup'] = [
                        'mode' => 'lightweight',
                        'reason' => 'no_snapshot_report',
                    ];
                    $state['status'] = 'completed';
                    $state['stage'] = 'completed';
                    $state['batch_state'] = 'completed';
                    $state['is_waiting_on_batch'] = false;
                    $state['active_batch_size'] = 0;
                    $state['message'] = 'Delete selesai. Report ini tidak menggunakan snapshot/index; statistik dan cache sudah disegarkan.';
                    $state['updated_at'] = now()->toIso8601String();
                    $this->putDeleteState($deleteId, $state);

                    return $state;
                }

                $maintenance = $this->prepareManagedDeleteSnapshotMaintenance($state, $syncService, $source, $deleteId);
                $state['cleanup'] = $maintenance['cleanup'];
                $state['sync_periods'] = $maintenance['sync_periods'];

                if ($maintenance['complete_without_sync']) {
                    $state['status'] = 'completed';
                    $state['stage'] = 'completed';
                    $state['batch_state'] = 'completed';
                    $state['is_waiting_on_batch'] = false;
                    $state['active_batch_size'] = 0;
                    $state['message'] = $maintenance['final_message'];
                    $state['updated_at'] = now()->toIso8601String();
                    $this->putDeleteState($deleteId, $state);

                    return $state;
                }

                $state['stage'] = 'syncing';
                $state['batch_state'] = 'cleanup';
                $state['is_waiting_on_batch'] = false;
                $state['active_batch_size'] = 0;
                $state['message'] = $maintenance['sync_message'];
                $state['updated_at'] = now()->toIso8601String();
                $this->putDeleteState($deleteId, $state);

                return $state;
            } catch (\Throwable $cleanupException) {
                // Data was already deleted — treat cleanup failure as 'warning', not 'failed'
                Log::error('Cleanup snapshot/sync gagal setelah delete berhasil.', [
                    'delete_id' => $deleteId,
                    'table_name' => $state['table_name'] ?? null,
                    'deleted_rows' => $deletedRows,
                    'maintenance_mode' => $maintenanceMode,
                    'exception' => $cleanupException::class,
                    'message' => $cleanupException->getMessage(),
                    'file' => $cleanupException->getFile(),
                    'line' => $cleanupException->getLine(),
                ]);

                $failure = $this->buildManagedDeleteFailure(array_merge($state, ['deleted_rows' => $deletedRows]), $cleanupException);
                $state['status'] = $deletedRows > 0 ? 'warning' : 'failed';
                $state['stage'] = 'failed';
                $state['batch_state'] = 'failed';
                $state['is_waiting_on_batch'] = false;
                $state['active_batch_size'] = 0;
                $state['message'] = $deletedRows > 0
                    ? 'Data sumber berhasil dihapus tetapi cleanup snapshot gagal. Data sudah aman — jalankan cache warm-up secara manual.'
                    : $failure['message'];
                $state['error'] = $failure['error'];
                $state['error_code'] = $failure['error_code'];
                $state['updated_at'] = now()->toIso8601String();
                $this->putDeleteState($deleteId, $state);

                $this->writeManagedDeleteAudit(
                    (string) ($state['table_name'] ?? ''),
                    $state['period_hint'] ?? null,
                    'managed_delete_failed',
                    'failed',
                    [
                        'affected_rows' => $deletedRows,
                        'message' => $cleanupException->getMessage(),
                        'context' => [
                            'delete_id' => $deleteId,
                            'stage' => 'cleanup',
                            'exception_class' => $cleanupException::class,
                        ],
                    ]
                );

                return $state;
            }

            $stage = (string) ($state['stage'] ?? $stage);
        }

        if ($stage === 'syncing') {
            if ($this->isManagedDeleteCancellationRequested($deleteId, $state)) {
                return $this->finalizeManagedDeleteCancelled($deleteId, $state);
            }

            $syncPeriods = array_values(array_filter(array_map(
                static fn ($period): string => trim((string) $period),
                is_array($state['sync_periods'] ?? null) ? $state['sync_periods'] : []
            ), static fn (string $period): bool => $period !== ''));

            $deletedRows = max(0, (int) ($state['deleted_rows'] ?? 0));

            try {
                if ($maintenanceMode === 'lightweight') {
                    $syncService->syncAfterDeleteLightweight(
                        (string) $state['table_name'],
                        $state['period_hint'] ?? null,
                        static::class . '::runManagedReportDelete',
                        $deleteId
                    );
                } elseif (!empty($syncPeriods)) {
                    $this->dispatchManagedDeleteSnapshotRefreshes(
                        (string) $state['table_name'],
                        $syncPeriods,
                        static::class . '::runManagedReportDelete'
                    );
                    $state['cleanup']['queued_periods'] = $syncPeriods;
                }
            } catch (\Throwable $syncException) {
                // Sync failure after successful delete → 'warning', data is already gone
                Log::error('Sinkronisasi snapshot gagal setelah delete berhasil.', [
                    'delete_id' => $deleteId,
                    'table_name' => $state['table_name'] ?? null,
                    'deleted_rows' => $deletedRows,
                    'sync_periods' => $syncPeriods,
                    'exception' => $syncException::class,
                    'message' => $syncException->getMessage(),
                    'file' => $syncException->getFile(),
                    'line' => $syncException->getLine(),
                ]);

                $state['status'] = $deletedRows > 0 ? 'warning' : 'failed';
                $state['stage'] = 'failed';
                $state['batch_state'] = 'failed';
                $state['is_waiting_on_batch'] = false;
                $state['active_batch_size'] = 0;
                $state['message'] = $deletedRows > 0
                    ? 'Data sumber berhasil dihapus tetapi sinkronisasi snapshot gagal. Data sudah aman — jalankan cache warm-up secara manual.'
                    : 'Sinkronisasi snapshot gagal. Detail teknis sudah dicatat di log server.';
                $state['error'] = $syncException->getMessage();
                $state['error_code'] = $this->resolveManagedDeleteErrorCode($syncException);
                $state['updated_at'] = now()->toIso8601String();
                $this->putDeleteState($deleteId, $state);

                $this->writeManagedDeleteAudit(
                    (string) ($state['table_name'] ?? ''),
                    $state['period_hint'] ?? null,
                    'managed_delete_failed',
                    'failed',
                    [
                        'affected_rows' => $deletedRows,
                        'message' => $syncException->getMessage(),
                        'context' => [
                            'delete_id' => $deleteId,
                            'stage' => 'syncing',
                            'exception_class' => $syncException::class,
                        ],
                    ]
                );

                return $state;
            }

            $state['status'] = 'completed';
            $state['stage'] = 'completed';
            $state['batch_state'] = 'completed';
            $state['is_waiting_on_batch'] = false;
            $state['active_batch_size'] = 0;
            $state['message'] = $maintenanceMode === 'lightweight'
                ? 'Delete selesai. Statistik sumber dan cache sudah disegarkan.'
                : $this->managedDeleteSnapshotSyncMessage($syncPeriods);
            $state['updated_at'] = now()->toIso8601String();
            $this->putDeleteState($deleteId, $state);

            return $state;
        }

        if (in_array($stage, ['completed', 'failed', 'cancelled'], true)) {
            return $state;
        }

        throw new \RuntimeException('Stage delete tidak dikenali.');
    }

    /**
     * @return array{cleanup:array<string,mixed>,sync_periods:array<int,string>,complete_without_sync:bool,final_message:string,sync_message:string}
     */
    private function prepareManagedDeleteSnapshotMaintenance(array $context, ReportDataSyncService $syncService, string $source, ?string $deleteId = null): array
    {
        $tableName = strtolower(trim((string) ($context['table_name'] ?? '')));
        $periodHint = $this->normalizeManagedDeletePeriod($context['period_hint'] ?? null);
        $explicitPeriods = $this->resolveManagedDeleteExplicitPeriodsFromScopes(
            is_array($context['scopes'] ?? null) ? $context['scopes'] : []
        );
        $fullTableScope = (bool) ($context['full_table_scope'] ?? false);
        $skipDerivedSync = (bool) ($context['skip_derived_sync'] ?? false);
        $skipSnapshotCleanup = (bool) ($context['skip_snapshot_cleanup'] ?? false);

        if ($fullTableScope) {
            $deletedSnapshots = $syncService->cleanupDerivedArtifactsAfterDelete($tableName, null, $source, $deleteId);
            $syncService->syncAfterDeleteLightweight($tableName, null, $source, $deleteId);

            return [
                'cleanup' => [
                    'mode' => 'snapshot_cleanup',
                    'reason' => 'full_table_delete',
                    'snapshot_tables' => $deletedSnapshots,
                    'snapshot_periods' => [],
                    'queued_periods' => [],
                ],
                'sync_periods' => [],
                'complete_without_sync' => true,
                'final_message' => 'Delete selesai. Tabel sumber dan snapshot pembantu yang terkait sudah dibersihkan tanpa rebuild ulang karena scope mengosongkan seluruh tabel.',
                'sync_message' => '',
            ];
        }

        $cleanupPeriods = [];
        $cleanupSnapshots = [];

        if (!empty($explicitPeriods)) {
            foreach ($explicitPeriods as $period) {
                $cleanupSnapshots[$period] = $syncService->cleanupDerivedArtifactsAfterDelete($tableName, $period, $source, $deleteId);
                $cleanupPeriods[] = $period;
            }
        }

        $syncService->syncAfterDeleteLightweight($tableName, $periodHint, $source, $deleteId);

        $syncPeriods = $skipDerivedSync ? [] : $explicitPeriods;
        $completeWithoutSync = empty($syncPeriods);

        $reason = $completeWithoutSync
            ? ($skipDerivedSync
                ? 'snapshot_sync_skipped_null_scope'
                : 'snapshot_sync_skipped_ambiguous_scope')
            : 'snapshot_cleanup_scoped_periods';

        return [
            'cleanup' => [
                'mode' => $completeWithoutSync ? 'lightweight' : 'snapshot_cleanup',
                'reason' => $reason,
                'snapshot_tables' => $cleanupSnapshots,
                'snapshot_periods' => $cleanupPeriods,
                'queued_periods' => $syncPeriods,
            ],
            'sync_periods' => $syncPeriods,
            'complete_without_sync' => $completeWithoutSync,
            'final_message' => $skipDerivedSync
                ? 'Delete selesai. Scope hanya menyentuh baris dengan periode null/kosong, jadi sistem hanya menyegarkan statistik sumber dan cache tanpa rebuild snapshot global.'
                : ($skipSnapshotCleanup
                    ? 'Delete selesai. Statistik sumber dan cache sudah disegarkan. Rebuild snapshot dilewati karena scope delete tidak memiliki periode eksplisit yang aman untuk ditargetkan.'
                    : 'Delete selesai. Statistik sumber dan cache sudah disegarkan. Tidak ada refresh snapshot tambahan yang perlu dijalankan.'),
            'sync_message' => count($syncPeriods) === 1
                ? 'Delete sumber selesai. Snapshot periode ' . $syncPeriods[0] . ' sedang dijadwalkan di queue prioritas tinggi...'
                : 'Delete sumber selesai. Snapshot ' . count($syncPeriods) . ' periode sedang dijadwalkan di queue prioritas tinggi...',
        ];
    }

    /**
     * @param array<int, mixed> $scopes
     * @return array<int, string>
     */
    private function resolveManagedDeleteExplicitPeriodsFromScopes(array $scopes): array
    {
        $periods = [];

        foreach ($scopes as $scope) {
            if (!is_array($scope)) {
                continue;
            }

            $period = $this->normalizeManagedDeletePeriod($scope['period_filter'] ?? null);
            if ($period !== null) {
                $periods[$period] = true;
            }
        }

        return array_keys($periods);
    }

    private function normalizeManagedDeletePeriod(mixed $period): ?string
    {
        $normalized = trim((string) $period);

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @param array<int, string> $periods
     */
    private function dispatchManagedDeleteSnapshotRefreshes(string $tableName, array $periods, string $source): void
    {
        foreach ($periods as $period) {
            $this->cleanupService()->dispatchSnapshotRefresh(
                $tableName,
                $period,
                $source,
                self::DELETE_SYNC_QUEUE
            );
        }
    }

    /**
     * @param array<int, string> $periods
     */
    private function managedDeleteSnapshotSyncMessage(array $periods): string
    {
        if (empty($periods)) {
            return 'Delete selesai. Statistik sumber dan cache sudah disegarkan.';
        }

        return count($periods) === 1
            ? 'Delete selesai. Refresh snapshot periode ' . $periods[0] . ' dijadwalkan di queue prioritas tinggi.'
            : 'Delete selesai. Refresh snapshot untuk ' . count($periods) . ' periode dijadwalkan di queue prioritas tinggi.';
    }

    private function markManagedDeleteFailed(string $deleteId, array $state, Throwable $e): array
    {
        Log::error('Delete report bertahap gagal.', [
            'delete_id' => $deleteId,
            'table_name' => $state['table_name'] ?? null,
            'stage' => $state['stage'] ?? null,
            'batch_state' => $state['batch_state'] ?? null,
            'deleted_rows' => $state['deleted_rows'] ?? 0,
            'scope_index' => $state['current_scope_index'] ?? null,
            'last_strategy' => $state['last_delete_strategy'] ?? null,
            'delete_plan' => $state['delete_plan'] ?? 'normal',
            'problem_signature' => $state['problem_signature'] ?? null,
            'exception' => $e::class,
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);

        $failure = $this->buildManagedDeleteFailure($state, $e);
        $state['status'] = ($state['deleted_rows'] ?? 0) > 0 ? 'warning' : 'failed';
        $state['stage'] = 'failed';
        $state['batch_state'] = 'failed';
        $state['is_waiting_on_batch'] = false;
        $state['active_batch_size'] = 0;
        $state['message'] = $failure['message'];
        $state['error'] = $failure['error'];
        $state['error_code'] = $failure['error_code'];
        $state['updated_at'] = now()->toIso8601String();
        $this->putDeleteState($deleteId, $state);
        $this->writeManagedDeleteAudit(
            (string) ($state['table_name'] ?? ''),
            $state['period_hint'] ?? null,
            'managed_delete_failed',
            'failed',
            [
                'affected_rows' => max(0, (int) ($state['deleted_rows'] ?? 0)),
                'message' => $e->getMessage(),
                'context' => [
                    'delete_id' => $deleteId,
                    'stage' => $state['stage'] ?? null,
                    'strategy' => $state['last_delete_strategy'] ?? null,
                    'delete_plan' => $state['delete_plan'] ?? 'normal',
                    'problem_signature' => $state['problem_signature'] ?? null,
                ],
            ]
        );

        return $state;
    }

    private function processDeleteChunk(array $state): array
    {
        $scopes = $this->extractDeleteScopesFromState($state);
        if (empty($scopes)) {
            throw new \RuntimeException('Scope delete tidak lagi valid.');
        }

        $deleteId = (string) ($state['delete_id'] ?? '');
        $tableName = (string) $state['table_name'];
        $periodColumn = $state['period_column'] ?? null;
        $kancaColumn = $state['kanca_column'] ?? null;
        $identityColumn = $state['identity_column'] ?? null;
        $chunkSize = $this->resolveEffectiveDeleteBatchSize($state);
        $deletePlan = (string) ($state['delete_plan'] ?? 'normal');
        $currentScopeIndex = max(0, (int) ($state['current_scope_index'] ?? 0));
        $totalScopes = count($scopes);

        while ($currentScopeIndex < $totalScopes) {
            if ($this->isManagedDeleteCancellationRequested($deleteId, $state)) {
                return $this->finalizeManagedDeleteCancelled($deleteId, $state);
            }

            $scope = $scopes[$currentScopeIndex];
            $deleteStrategy = $this->resolveDeleteScopeStrategy(
                $tableName,
                $periodColumn,
                $kancaColumn,
                $identityColumn,
                $scope,
                $deletePlan
            );
            $batchStartedAt = microtime(true);

            [$scopeQuery, $hasWhereClause] = $this->buildDeleteScopeQueryFromScopes(
                $tableName,
                $periodColumn,
                $kancaColumn,
                [$scope]
            );

            if (!$hasWhereClause) {
                $currentScopeIndex++;
                continue;
            }

            $state['batch_state'] = 'deleting_pending';
            $state['is_waiting_on_batch'] = true;
            $state['active_batch_size'] = $chunkSize;
            $state['current_scope_index'] = $currentScopeIndex;
            $state['last_delete_strategy'] = $deleteStrategy;
            $state['last_batch_deleted_rows'] = 0;
            $state['last_batch_started_at'] = now()->toIso8601String();
            $state['last_batch_finished_at'] = null;
            $state['message'] = sprintf(
                'Memproses batch %s baris... Grup %d/%d (%s).',
                number_format($chunkSize, 0, ',', '.'),
                $currentScopeIndex + 1,
                $totalScopes,
                $this->describeDeleteScope($scope)
            );
            $state['updated_at'] = now()->toIso8601String();
            $this->putDeleteState((string) $state['delete_id'], $state);

            try {
                $affected = $this->deleteScopedRows(
                    $tableName,
                    $scopeQuery,
                    $identityColumn,
                    $chunkSize,
                    $periodColumn,
                    $kancaColumn,
                    $scope,
                    $deleteId,
                    $deletePlan
                );
            } catch (\Throwable $scopeException) {
                // Log full context before re-throwing so markManagedDeleteFailed gets precise state
                Log::error('deleteScopedRows gagal pada scope tertentu.', [
                    'delete_id' => $deleteId,
                    'table_name' => $tableName,
                    'scope_index' => $currentScopeIndex,
                    'scope_count' => $totalScopes,
                    'scope' => $scope,
                    'strategy' => $deleteStrategy,
                    'delete_plan' => $deletePlan,
                    'problem_signature' => $state['problem_signature'] ?? null,
                    'deleted_so_far' => $state['deleted_rows'] ?? 0,
                    'exception' => $scopeException::class,
                    'message' => $scopeException->getMessage(),
                    'file' => $scopeException->getFile(),
                    'line' => $scopeException->getLine(),
                ]);

                $this->writeManagedDeleteAudit(
                    $tableName,
                    $scope['period_filter'] ?? ($state['period_hint'] ?? null),
                    'managed_delete_chunk',
                    'failed',
                    [
                        'duration_ms' => $this->elapsedManagedDeleteMs($batchStartedAt),
                        'affected_rows' => 0,
                        'message' => $scopeException->getMessage(),
                        'context' => [
                            'delete_id' => $deleteId,
                            'scope_index' => $currentScopeIndex + 1,
                            'scope_count' => $totalScopes,
                            'strategy' => $deleteStrategy,
                            'delete_plan' => $deletePlan,
                            'scope' => $scope,
                            'problem_signature' => $state['problem_signature'] ?? null,
                            'exception_class' => $scopeException::class,
                        ],
                    ]
                );

                throw $scopeException;
            }

            if ($this->isManagedDeleteCancellationRequested($deleteId, $state)) {
                $state['deleted_rows'] = (int) ($state['deleted_rows'] ?? 0) + max(0, (int) $affected);
                $state['remaining_rows'] = max(0, (int) ($state['total_rows'] ?? 0) - (int) ($state['deleted_rows'] ?? 0));
                $state['last_batch_deleted_rows'] = max(0, (int) $affected);
                $state['last_batch_finished_at'] = now()->toIso8601String();
                $state['updated_at'] = now()->toIso8601String();
                $this->putDeleteState((string) $state['delete_id'], $state);
                $this->writeManagedDeleteAudit(
                    $tableName,
                    $scope['period_filter'] ?? ($state['period_hint'] ?? null),
                    'managed_delete_chunk',
                    'warning',
                    [
                        'duration_ms' => $this->elapsedManagedDeleteMs($batchStartedAt),
                        'affected_rows' => max(0, (int) $affected),
                        'context' => [
                            'delete_id' => $deleteId,
                            'scope_index' => $currentScopeIndex + 1,
                            'scope_count' => $totalScopes,
                            'strategy' => $deleteStrategy,
                            'delete_plan' => $deletePlan,
                            'scope' => $scope,
                            'cancel_requested' => true,
                            'problem_signature' => $state['problem_signature'] ?? null,
                        ],
                    ]
                );

                return $this->finalizeManagedDeleteCancelled($deleteId, $state);
            }

            if ($affected > 0) {
                $state['deleted_rows'] = (int) ($state['deleted_rows'] ?? 0) + $affected;
                $state['remaining_rows'] = max(0, (int) ($state['total_rows'] ?? 0) - (int) ($state['deleted_rows'] ?? 0));
                $state['current_scope_index'] = $currentScopeIndex;
                $state['batch_state'] = 'deleting_committed';
                $state['is_waiting_on_batch'] = false;
                $state['active_batch_size'] = $chunkSize;
                $state['last_batch_deleted_rows'] = $affected;
                $state['last_batch_finished_at'] = now()->toIso8601String();
                $state['updated_at'] = now()->toIso8601String();

                if (($state['remaining_rows'] ?? 0) <= 0) {
                    $state['stage'] = 'cleanup';
                    $state['message'] = 'Delete sumber selesai, membersihkan snapshot dan artefak turunan...';
                } else {
                    $state['message'] = sprintf(
                        'Batch selesai, menghapus %s baris. Grup %d/%d (%s).',
                        number_format($affected, 0, ',', '.'),
                        $currentScopeIndex + 1,
                        $totalScopes,
                        $this->describeDeleteScope($scope)
                    );
                }

                $this->putDeleteState((string) $state['delete_id'], $state);
                $this->writeManagedDeleteAudit(
                    $tableName,
                    $scope['period_filter'] ?? ($state['period_hint'] ?? null),
                    'managed_delete_chunk',
                    'success',
                    [
                        'duration_ms' => $this->elapsedManagedDeleteMs($batchStartedAt),
                        'affected_rows' => $affected,
                        'context' => [
                            'delete_id' => $deleteId,
                            'scope_index' => $currentScopeIndex + 1,
                            'scope_count' => $totalScopes,
                            'strategy' => $deleteStrategy,
                            'delete_plan' => $deletePlan,
                            'scope' => $scope,
                            'remaining_rows' => $state['remaining_rows'] ?? null,
                            'problem_signature' => $state['problem_signature'] ?? null,
                        ],
                    ]
                );

                return $state;
            }

            $currentScopeIndex++;
        }

        [$verificationQuery, $hasWhereClause] = $this->buildDeleteScopeQueryFromScopes(
            $tableName,
            $periodColumn,
            $kancaColumn,
            $scopes
        );

        $remainingRows = $hasWhereClause ? (int) (clone $verificationQuery)->count() : 0;
        $state['remaining_rows'] = $remainingRows;
        $state['current_scope_index'] = $remainingRows > 0 ? 0 : $totalScopes;
        $state['batch_state'] = $remainingRows > 0 ? 'deleting_pending' : 'cleanup';
        $state['is_waiting_on_batch'] = false;
        $state['active_batch_size'] = 0;
        $state['last_batch_finished_at'] = now()->toIso8601String();
        $state['updated_at'] = now()->toIso8601String();

        if ($remainingRows <= 0) {
            $state['stage'] = 'cleanup';
            $state['message'] = 'Delete sumber selesai, membersihkan snapshot dan artefak turunan...';
        } else {
            $state['message'] = 'Melanjutkan verifikasi data yang masih tersisa sebelum cleanup...';
        }

        $this->putDeleteState((string) $state['delete_id'], $state);

        return $state;
    }

    private function formatDeleteStateResponse(array $state, array $overrides = []): array
    {
        $totalRows = max(0, (int) ($state['total_rows'] ?? 0));
        $deletedRows = max(0, (int) ($state['deleted_rows'] ?? 0));
        $stage = (string) ($state['stage'] ?? 'queued');
        $status = (string) ($state['status'] ?? 'running');
        $message = (string) ($state['message'] ?? 'Memproses delete...');
        $cleanupMode = (string) ($state['cleanup']['mode'] ?? '');
        $successLikeMessage = $this->isSuccessLikeManagedDeleteMessage($message);
        $effectiveStatus = match (true) {
            $status === 'success' => 'completed',
            $status === 'failed' && $deletedRows > 0 => 'warning',
            default => $status,
        };

        if ($status === 'failed' && $successLikeMessage) {
            $effectiveStatus = ($deletedRows > 0 || $cleanupMode === 'lightweight') ? 'warning' : 'completed';
        }

        $isWaitingOnBatch = (bool) ($state['is_waiting_on_batch'] ?? false);
        $batchState = (string) ($state['batch_state'] ?? 'idle');

        $actualPercent = $totalRows > 0
            ? (int) floor(($deletedRows / max(1, $totalRows)) * 100)
            : 0;

        $percent = match ($stage) {
            'deleting' => min(100, $actualPercent),
            'cleanup' => max($actualPercent, $deletedRows >= $totalRows && $totalRows > 0 ? 99 : $actualPercent),
            'syncing' => max($actualPercent, $deletedRows >= $totalRows && $totalRows > 0 ? 99 : $actualPercent),
            'completed' => 100,
            'failed' => min(100, $actualPercent),
            'cancelled' => min(100, $actualPercent),
            default => min(100, $actualPercent),
        };

        if (in_array($effectiveStatus, ['completed', 'warning'], true)) {
            $percent = 100;
        }

        return array_merge([
            'status' => $effectiveStatus,
            'delete_id' => $state['delete_id'] ?? null,
            'stage' => $stage,
            'batch_state' => $batchState,
            'table_name' => $state['table_name'] ?? null,
            'total_rows' => $totalRows,
            'deleted_rows' => $deletedRows,
            'remaining_rows' => max(0, (int) ($state['remaining_rows'] ?? ($totalRows - $deletedRows))),
            'chunk_size' => max(1, (int) ($state['chunk_size'] ?? self::DELETE_CHUNK_SIZE)),
            'current_scope_index' => max(0, (int) ($state['current_scope_index'] ?? 0)),
            'scope_count' => count($this->extractDeleteScopesFromState($state)),
            'is_waiting_on_batch' => $isWaitingOnBatch,
            'active_batch_size' => max(0, (int) ($state['active_batch_size'] ?? 0)),
            'last_batch_deleted_rows' => max(0, (int) ($state['last_batch_deleted_rows'] ?? 0)),
            'last_batch_started_at' => $state['last_batch_started_at'] ?? null,
            'last_batch_finished_at' => $state['last_batch_finished_at'] ?? null,
            'created_at' => $state['created_at'] ?? null,
            'updated_at' => $state['updated_at'] ?? null,
            'progress_percent' => $percent,
            'message' => $message,
            'error' => $state['error'] ?? null,
            'error_code' => $state['error_code'] ?? null,
            'cleanup' => $state['cleanup'] ?? null,
            'delete_plan' => (string) ($state['delete_plan'] ?? 'normal'),
            'delete_strategy' => $state['last_delete_strategy'] ?? ($state['delete_strategy'] ?? null),
            'problem_signature' => $state['problem_signature'] ?? null,
            'cancel_requested' => (bool) ($state['cancel_requested'] ?? false),
            'can_process_fallback' => $this->shouldAllowManagedDeleteFallback($state),
            'fallback_stale_seconds' => self::DELETE_PROCESS_STALE_SECONDS,
        ], $overrides);
    }

    private function isSuccessLikeManagedDeleteMessage(string $message): bool
    {
        $normalized = strtolower(trim($message));

        if ($normalized === '') {
            return false;
        }

        return str_starts_with($normalized, 'delete selesai.')
            || str_starts_with($normalized, 'delete sumber selesai')
            || str_contains($normalized, 'statistik dan cache sudah disegarkan')
            || str_contains($normalized, 'report ini tidak menggunakan snapshot/index');
    }

    private function getDeleteState(string $deleteId): ?array
    {
        $state = $this->deleteProgressStore()->get($this->deleteProgressCacheKey($deleteId));

        if (!is_array($state)) {
            $state = $this->deleteProgressStore()->get($this->legacyDeleteProgressCacheKey($deleteId));
        }

        return is_array($state) ? $state : null;
    }

    /**
     * Update the 'updated_at' timestamp and optionally the message for a job
     * to prevent it from being marked as stale during long operations.
     */
    public function heartbeatManagedDeleteState(string $deleteId, ?string $message = null): bool
    {
        $state = $this->getDeleteState($deleteId);
        if (!$state) {
            return false;
        }

        $state['updated_at'] = now()->toIso8601String();
        if ($message !== null) {
            $state['message'] = $message;
        }

        $this->putDeleteState($deleteId, $state);
        
        // Also extend the lock if we hold it
        $this->deleteProgressStore()->put(
            $this->managedDeleteProcessLockKey($deleteId),
            now()->toIso8601String(),
            now()->addSeconds(self::DELETE_PROCESS_LOCK_SECONDS)
        );

        return true;
    }

    private function isManagedDeleteCancellationRequested(string $deleteId, ?array $state = null): bool
    {
        $state = $state ?? $this->getDeleteState($deleteId);
        if (!is_array($state)) {
            return false;
        }

        return (bool) ($state['cancel_requested'] ?? false)
            || in_array((string) ($state['status'] ?? ''), ['cancelling', 'cancelled'], true);
    }

    private function finalizeManagedDeleteCancelled(string $deleteId, array $state): array
    {
        $deletedRows = max(0, (int) ($state['deleted_rows'] ?? 0));

        $state['cancel_requested'] = true;
        $state['status'] = 'warning';
        $state['stage'] = 'cancelled';
        $state['batch_state'] = 'cancelled';
        $state['is_waiting_on_batch'] = false;
        $state['active_batch_size'] = 0;
        $state['remaining_rows'] = max(0, (int) ($state['remaining_rows'] ?? ($state['total_rows'] ?? 0)));
        $state['cleanup'] = [
            'mode' => 'cancelled',
            'reason' => 'user_requested_termination',
        ];
        $state['message'] = $deletedRows > 0
            ? 'Delete dibatalkan aman setelah sebagian batch selesai. Tidak ada cleanup lanjutan dijalankan.'
            : 'Delete dibatalkan aman sebelum perubahan data lanjut diproses.';
        $state['updated_at'] = now()->toIso8601String();

        $this->putDeleteState($deleteId, $state);

        return $state;
    }

    private function putDeleteState(string $deleteId, array $state): void
    {
        $this->deleteProgressStore()->put(
            $this->deleteProgressCacheKey($deleteId),
            $state,
            now()->addMinutes(self::DELETE_PROGRESS_TTL_MINUTES)
        );
        $this->syncManagedDeleteRegistry($deleteId, $state);
    }

    private function deleteProgressCacheKey(string $deleteId): string
    {
        return $this->managedDeleteCacheNamespace() . ':' . self::DELETE_PROGRESS_CACHE_PREFIX . ':' . trim($deleteId);
    }

    private function legacyDeleteProgressCacheKey(string $deleteId): string
    {
        return self::DELETE_PROGRESS_CACHE_PREFIX . ':' . trim($deleteId);
    }

    private function activeManagedDeleteIds(): array
    {
        $value = $this->deleteProgressStore()->get($this->managedDeleteActiveIdsCacheKey());
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($id): string => trim((string) $id),
            $value
        ), static fn (string $id): bool => $id !== ''));
    }

    private function syncManagedDeleteRegistry(string $deleteId, array $state): void
    {
        $normalizedId = trim($deleteId);
        if ($normalizedId === '') {
            return;
        }

        $ids = $this->activeManagedDeleteIds();
        $terminal = in_array((string) ($state['status'] ?? ''), ['completed', 'warning', 'failed', 'cancelled'], true);

        if ($terminal) {
            $ids = array_values(array_filter($ids, static fn (string $id): bool => $id !== $normalizedId));
        } elseif (!in_array($normalizedId, $ids, true)) {
            $ids[] = $normalizedId;
        }

        if ($ids === []) {
            $this->deleteProgressStore()->forget($this->managedDeleteActiveIdsCacheKey());
            return;
        }

        $this->deleteProgressStore()->put(
            $this->managedDeleteActiveIdsCacheKey(),
            array_values($ids),
            now()->addMinutes(self::DELETE_PROGRESS_TTL_MINUTES)
        );
    }

    private function shouldAllowManagedDeleteFallback(array $state): bool
    {
        if (in_array($state['status'] ?? '', ['completed', 'warning', 'failed', 'cancelled'], true)) {
            return false;
        }

        $stage = (string) ($state['stage'] ?? 'queued');
        $batchState = (string) ($state['batch_state'] ?? '');
        $reference = $state['updated_at'] ?? $state['created_at'] ?? null;
        $ageSeconds = $this->diffNowInSeconds($reference);
        $deleteId = trim((string) ($state['delete_id'] ?? ''));
        $queueRow = $deleteId !== '' ? $this->findManagedDeleteQueueRow($deleteId) : null;
        $queueReserved = $queueRow !== null && (bool) ($queueRow['reserved'] ?? false);
        $queuePendingWithoutWorker = $queueRow !== null
            && !$queueReserved
            && max(
                (int) ($queueRow['created_age_seconds'] ?? 0),
                $this->queueTimestampAgeSeconds($queueRow['available_at'] ?? null)
            ) >= 1;

        // Setelah satu batch berhasil commit, fallback controller boleh langsung melanjutkan
        // selama tidak ada worker queue aktif yang sedang memegang job delete ini.
        if (
            $stage === 'deleting'
            && $batchState === 'deleting_committed'
            && !$queueReserved
        ) {
            return true;
        }

        if (
            in_array($stage, ['cleanup', 'syncing'], true)
            && !$queueReserved
        ) {
            return true;
        }

        if ($stage === 'queued') {
            if ($queuePendingWithoutWorker) {
                return true;
            }

            return $ageSeconds >= self::DELETE_PROCESS_GRACE_SECONDS;
        }

        if (in_array($batchState, ['deleting_pending', 'deleting_committed'], true)) {
            if ($queuePendingWithoutWorker) {
                return true;
            }

            return $ageSeconds >= self::DELETE_PROCESS_STALE_SECONDS;
        }

        if ($queuePendingWithoutWorker) {
            return true;
        }

        return $ageSeconds >= self::DELETE_PROCESS_STALE_SECONDS;
    }

    private function findManagedDeleteQueueRow(string $deleteId): ?array
    {
        $normalizedDeleteId = trim($deleteId);
        if ($normalizedDeleteId === '' || !Schema::hasTable('jobs')) {
            return null;
        }

        foreach ($this->managedReportDeleteQueueRows() as $row) {
            if (trim((string) ($row['delete_id'] ?? '')) === $normalizedDeleteId) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @return array{0: ?array, 1: ?array}
     */
    private function resolveManagedDeleteControlState(string $deleteId): array
    {
        $state = $this->getDeleteState($deleteId);
        $queueRow = $this->findManagedDeleteQueueRow($deleteId);

        if ($state === null && $queueRow !== null) {
            $state = $this->makeSyntheticManagedDeleteState($deleteId, $queueRow);
            $state['message'] = (bool) ($queueRow['reserved'] ?? false)
                ? 'Delete sedang diproses worker queue. Stop paksa akan menutup progress dan menghentikan lanjutan batch.'
                : 'Delete masih menunggu worker queue. Job akan dihentikan langsung dari antrean.';
        }

        return [$state, $queueRow];
    }

    private function releaseManagedDeleteQueueRow(?array $queueRow): void
    {
        if ($queueRow === null || !Schema::hasTable('jobs')) {
            return;
        }

        $jobId = (int) ($queueRow['job_id'] ?? 0);
        if ($jobId <= 0) {
            return;
        }

        DB::table('jobs')->where('id', $jobId)->delete();
    }

    private function reconcileStaleManagedDeleteState(string $deleteId, array $state): array
    {
        if (in_array($state['status'] ?? '', ['completed', 'warning', 'failed', 'cancelled'], true)) {
            return $state;
        }

        $ageSeconds = $this->diffNowInSeconds($state['updated_at'] ?? $state['created_at'] ?? null);
        if ($ageSeconds < self::DELETE_FAIL_STALE_SECONDS) {
            return $state;
        }

        return $this->markManagedDeleteFailed(
            $deleteId,
            $state,
            new \RuntimeException('Delete report management stale timeout. Progress tidak bergerak terlalu lama.')
        );
    }

    private function acquireManagedDeleteProcessLock(string $deleteId): bool
    {
        return $this->deleteProgressStore()->add(
            $this->managedDeleteProcessLockKey($deleteId),
            now()->toIso8601String(),
            now()->addSeconds(self::DELETE_PROCESS_LOCK_SECONDS)
        );
    }

    private function releaseManagedDeleteProcessLock(string $deleteId): void
    {
        $this->deleteProgressStore()->forget($this->managedDeleteProcessLockKey($deleteId));
    }

    private function managedDeleteProcessLockKey(string $deleteId): string
    {
        return $this->managedDeleteCacheNamespace() . ':' . self::DELETE_PROCESS_LOCK_PREFIX . ':' . trim($deleteId);
    }

    private function managedDeleteActiveIdsCacheKey(): string
    {
        return $this->managedDeleteCacheNamespace() . ':' . self::DELETE_ACTIVE_IDS_CACHE_KEY;
    }

    private function managedDeleteCacheNamespace(): string
    {
        $environment = strtolower(trim((string) app()->environment()));
        $environment = preg_replace('/[^a-z0-9_-]+/', '_', $environment) ?? $environment;
        $environment = trim($environment, '_');

        return 'env:' . ($environment !== '' ? $environment : 'production');
    }

    private function managedReportDeleteQueueRows(): array
    {
        if (!Schema::hasTable('jobs')) {
            return [];
        }

        $basename = class_basename(RunManagedReportDeleteJob::class);

        return DB::table('jobs')
            ->where('queue', self::DELETE_QUEUE)
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
                    'delete_id' => $this->extractManagedDeleteIdFromPayload($payload),
                ];
            })
            ->filter(fn (array $row): bool => trim((string) ($row['delete_id'] ?? '')) !== '')
            ->values()
            ->all();
    }

    private function extractManagedDeleteIdFromPayload(string $payload): ?string
    {
        $candidate = '';

        if (preg_match('/deleteId";s:\d+:"([0-9a-f\-]{36})"/i', $payload, $matches) === 1) {
            $candidate = (string) ($matches[1] ?? '');
        }

        if ($candidate === '' && preg_match('/"deleteId":"([0-9a-f\-]{36})"/i', $payload, $matches) === 1) {
            $candidate = (string) ($matches[1] ?? '');
        }

        $candidate = trim($candidate);

        return $candidate !== '' ? $candidate : null;
    }

    private function reconcileManagedDeleteStateWithQueueRow(string $deleteId, ?array $state, ?array $queueRow): ?array
    {
        if ($state === null) {
            return $queueRow ? $this->makeSyntheticManagedDeleteState($deleteId, $queueRow) : null;
        }

        $status = strtolower(trim((string) ($state['status'] ?? '')));
        if ($status === 'failed' && $queueRow !== null) {
            $reserved = (bool) ($queueRow['reserved'] ?? false);
            $state['previous_failed_status'] = [
                'message' => $state['message'] ?? null,
                'error' => $state['error'] ?? null,
                'error_code' => $state['error_code'] ?? null,
                'updated_at' => $state['updated_at'] ?? null,
            ];
            $state['status'] = $reserved ? 'running' : 'queued';
            $state['stage'] = $reserved ? 'deleting' : 'queued';
            $state['batch_state'] = $reserved ? 'deleting_pending' : 'queued';
            $state['message'] = $reserved
                ? 'Delete masih berjalan di worker queue. Status disinkronkan dari Job Management.'
                : 'Delete masih menunggu worker queue. Status disinkronkan dari Job Management.';
            $state['error'] = null;
            $state['error_code'] = null;
            $state['updated_at'] = now()->toIso8601String();
            $status = strtolower(trim((string) $state['status']));
            $this->putDeleteState($deleteId, $state);
        }

        if (in_array($status, ['completed', 'warning', 'failed', 'cancelled'], true)) {
            return $state;
        }

        if ($queueRow === null) {
            $referenceTimestamp = (string) ($state['updated_at'] ?? $state['created_at'] ?? '');
            $isProcessStale = $this->timestampOlderThan($referenceTimestamp, self::DELETE_PROCESS_STALE_SECONDS);
            $isHardStale = $this->timestampOlderThan($referenceTimestamp, self::DELETE_FAIL_STALE_SECONDS);

            if ($isHardStale) {
                return $this->markManagedDeleteFailed(
                    $deleteId,
                    $state,
                    new \RuntimeException('Delete queue tidak lagi aktif dan state terlalu lama tidak bergerak.')
                );
            }

            if ($isProcessStale && !in_array((string) ($state['stage'] ?? ''), ['completed', 'failed', 'cancelled'], true)) {
                $state['status'] = in_array($status, ['queued', 'running', 'cancelling'], true) ? $status : 'running';

                if (trim((string) ($state['message'] ?? '')) === '') {
                    $state['message'] = 'Delete masih menunggu fallback controller melanjutkan batch berikutnya.';
                }
            }
        }

        if ($queueRow !== null && (bool) ($queueRow['reserved'] ?? false) && $status === 'queued') {
            $state['status'] = 'running';
            $state['stage'] = in_array(strtolower(trim((string) ($state['stage'] ?? ''))), ['queued'], true) ? 'deleting' : ($state['stage'] ?? 'deleting');
            $state['message'] = trim((string) ($state['message'] ?? '')) !== ''
                ? (string) $state['message']
                : 'Delete sedang diproses worker queue.';
        }

        return $state;
    }

    private function makeSyntheticManagedDeleteState(string $deleteId, array $queueRow): array
    {
        $timestamp = now()->toIso8601String();
        $reserved = (bool) ($queueRow['reserved'] ?? false);
        $createdAt = $this->queueTimestampToIso8601($queueRow['created_at'] ?? null) ?? $timestamp;
        $updatedAt = $reserved
            ? ($this->queueTimestampToIso8601($queueRow['reserved_at'] ?? null) ?? $createdAt)
            : $createdAt;

        return [
            'delete_id' => $deleteId,
            'status' => $reserved ? 'running' : 'queued',
            'stage' => $reserved ? 'deleting' : 'queued',
            'batch_state' => $reserved ? 'deleting_pending' : 'queued',
            'table_name' => '',
            'total_rows' => 1,
            'deleted_rows' => 0,
            'remaining_rows' => 1,
            'chunk_size' => self::DELETE_CHUNK_SIZE,
            'current_scope_index' => 0,
            'is_waiting_on_batch' => false,
            'active_batch_size' => 0,
            'last_batch_deleted_rows' => 0,
            'last_batch_started_at' => null,
            'last_batch_finished_at' => null,
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
            'progress_percent' => 0,
            'message' => $reserved
                ? 'Delete sedang diproses worker queue.'
                : 'Delete masih menunggu worker queue.',
            'cleanup' => null,
            'cancel_requested' => false,
        ];
    }

    private function safeParseDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
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

    private function mapManagedDeleteStatus(string $status, string $stage): string
    {
        $normalizedStatus = strtolower(trim($status));
        $normalizedStage = strtolower(trim($stage));

        if ($normalizedStatus === 'queued' || $normalizedStage === 'queued') {
            return 'queued';
        }

        if (in_array($normalizedStatus, ['running', 'processing', 'cancelling'], true)) {
            return 'processing';
        }

        if ($normalizedStatus === 'cancelled') {
            return 'terminated';
        }

        if (in_array($normalizedStatus, ['completed', 'warning'], true)) {
            return 'completed';
        }

        if ($normalizedStatus === 'failed') {
            return 'failed';
        }

        if (in_array($normalizedStage, ['deleting', 'cleanup', 'syncing'], true)) {
            return 'processing';
        }

        return 'processing';
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

    private function managedDeleteStageLabel(string $stage): string
    {
        return match (strtolower(trim($stage))) {
            'queued' => 'Queued',
            'deleting' => 'Deleting',
            'cleanup' => 'Cleanup',
            'syncing' => 'Syncing',
            'completed' => 'Completed',
            'failed' => 'Failed',
            'cancelled' => 'Cancelled',
            default => ucfirst($stage !== '' ? $stage : 'unknown'),
        };
    }

    private function diffNowInSeconds(?string $timestamp): int
    {
        if (!is_string($timestamp) || trim($timestamp) === '') {
            return PHP_INT_MAX;
        }

        try {
            return max(0, now()->diffInSeconds(\Carbon\CarbonImmutable::parse($timestamp)));
        } catch (Throwable) {
            return PHP_INT_MAX;
        }
    }

    private function deleteProgressStore()
    {
        return Cache::store('file');
    }

    private function downloadTemplateOptions(): array
    {
        return collect(self::TEMPLATE_DEFINITIONS)
            ->map(function (array $template) {
                return [
                    'label' => $template['label'],
                    'filename' => $template['filename'],
                ];
            })
            ->all();
    }

    private function managedDatabaseBackupOptions(): array
    {
        $directories = $this->managedDatabaseBackupDirectories();
        $files = collect();

        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            $files = $files->concat(
                collect(File::files($directory))
                    ->filter(static fn ($file): bool => in_array(strtolower($file->getExtension()), ['sql', 'gz'], true))
            );
        }

        return $files
            ->unique(static fn ($file): string => strtolower(str_replace('\\', '/', $file->getPathname())))
            ->sortByDesc(static fn ($file): int => (int) $file->getMTime())
            ->values()
            ->map(static function ($file): array {
                $absolutePath = $file->getPathname();
                $storageBase = str_replace('\\', '/', storage_path('app'));
                $normalizedPath = str_replace('\\', '/', $absolutePath);
                $relativePath = str_starts_with($normalizedPath, rtrim($storageBase, '/') . '/')
                    ? substr($normalizedPath, strlen(rtrim($storageBase, '/')) + 1)
                    : $normalizedPath;

                $size = (int) $file->getSize();

                return [
                    'name' => $file->getFilename(),
                    'path' => $relativePath,
                    'size' => $size,
                    'size_human' => number_format($size / 1024 / 1024, 2, ',', '.') . ' MB',
                    'modified_at' => date('d M Y H:i', (int) $file->getMTime()),
                ];
            })
            ->all();
    }

    private function managedDatabaseBackupDirectories(): array
    {
        $directories = [storage_path('app/private/database_backups')];
        $configured = trim((string) env('MANAGED_REPORT_RECOVERY_ALLOWED_BACKUP_DIRS', ''));

        if ($configured !== '') {
            foreach (preg_split('/[;,]+/', $configured) ?: [] as $entry) {
                $entry = trim((string) $entry);
                if ($entry === '') {
                    continue;
                }

                $directories[] = $this->normalizeManagedRecoveryBackupPath($entry);
            }
        }

        return array_values(array_unique(array_map(
            static fn (string $path): string => rtrim(str_replace('\\', '/', $path), '/'),
            $directories
        )));
    }

    private function normalizeManagedRecoveryBackupPath(string $path): string
    {
        $normalized = trim(str_replace('\\', '/', $path));
        if ($normalized === '') {
            return storage_path('app/private/database_backups');
        }

        if (preg_match('/^[A-Za-z]:\//', $normalized) === 1 || str_starts_with($normalized, '/')) {
            return $normalized;
        }

        return storage_path('app/' . ltrim($normalized, '/'));
    }

    private function resolveTemplateOption(string $templateKey, string $requestedFilename = ''): ?array
    {
        $normalizedKey = $this->normalizeTemplateKey($templateKey);
        $normalizedFilename = $this->normalizeTemplateKey($requestedFilename);

        foreach (self::TEMPLATE_DEFINITIONS as $key => $template) {
            $filename = (string) ($template['filename'] ?? '');

            if ($normalizedFilename !== '' && $normalizedFilename === $this->normalizeTemplateKey($filename)) {
                return [
                    'label' => $template['label'],
                    'filename' => $filename,
                ];
            }

            $candidates = array_filter([
                $key,
                $template['label'] ?? null,
                $filename,
                ...($template['aliases'] ?? []),
            ]);

            foreach ($candidates as $candidate) {
                if ($normalizedKey === $this->normalizeTemplateKey((string) $candidate)) {
                    return [
                        'label' => $template['label'],
                        'filename' => $template['filename'],
                    ];
                }
            }
        }

        return null;
    }

    private function normalizeTemplateKey(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/\.xlsx$/', '', $value) ?? $value;
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? $value;

        return trim($value, '_');
    }

    private function resolveColumnName(array $tableColumns, array $candidates): ?string
    {
        $lowerLookup = [];
        foreach ($tableColumns as $column) {
            $lowerLookup[strtolower((string) $column)] = (string) $column;
        }

        foreach ($candidates as $candidate) {
            $resolved = $lowerLookup[strtolower((string) $candidate)] ?? null;
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return null;
    }

    private function resolveCandidateColumns(array $tableColumns, array $candidates): array
    {
        $lowerLookup = [];
        foreach ($tableColumns as $column) {
            $lowerLookup[strtolower((string) $column)] = (string) $column;
        }

        $resolved = [];
        foreach ($candidates as $candidate) {
            $match = $lowerLookup[strtolower((string) $candidate)] ?? null;
            if ($match !== null) {
                $resolved[$match] = $match;
            }
        }

        return array_values($resolved);
    }

    private function countNonNullColumnValues(string $tableName, string $column): int
    {
        $safeColumn = str_replace('`', '``', $column);

        try {
            return (int) DB::table($tableName)
                ->whereNotNull($column)
                ->whereRaw("CAST(`{$safeColumn}` AS CHAR) <> ''")
                ->count();
        } catch (Throwable) {
            return (int) DB::table($tableName)
                ->whereNotNull($column)
                ->count();
        }
    }

    private function resolveMostPopulatedColumn(string $tableName, array $columns): ?string
    {
        if (count($columns) <= 1) {
            return $columns[0] ?? null;
        }

        $bestColumn = null;
        $bestCount = -1;

        foreach ($columns as $column) {
            $count = $this->countNonNullColumnValues($tableName, (string) $column);
            if ($count > $bestCount) {
                $bestCount = $count;
                $bestColumn = (string) $column;
            }
        }

        return $bestColumn;
    }

    private function normalizeManagementColumnKey(string $column): string
    {
        $normalized = strtolower(trim($column));
        $normalized = preg_replace('/[^a-z0-9]+/', '_', $normalized) ?? $normalized;
        $normalized = trim($normalized, '_');

        return preg_replace('/\d+$/', '', $normalized) ?? $normalized;
    }

    private function resolveSemanticPeriodColumn(array $tableColumns): ?string
    {
        $bestColumn = null;
        $bestScore = PHP_INT_MIN;

        foreach ($tableColumns as $column) {
            $column = (string) $column;
            $normalized = $this->normalizeManagementColumnKey($column);
            if ($normalized === '') {
                continue;
            }

            $score = 0;
            $tokens = array_values(array_filter(explode('_', $normalized)));
            $tokenLookup = array_fill_keys($tokens, true);

            if (in_array($normalized, ['periode', 'period', 'snapshot_period', 'loan_period', 'casa_period'], true)) {
                $score += 120;
            }

            if ($normalized === 'posisi') {
                $score += 100;
            }

            if (isset($tokenLookup['periode']) || isset($tokenLookup['period'])) {
                $score += 80;
            }

            if (isset($tokenLookup['posisi'])) {
                $score += 70;
            }

            if (isset($tokenLookup['tanggal']) && (isset($tokenLookup['periode']) || isset($tokenLookup['period']))) {
                $score += 40;
            }

            if (isset($tokenLookup['created']) || isset($tokenLookup['updated'])) {
                $score -= 40;
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestColumn = $column;
            }
        }

        return $bestScore > 0 ? $bestColumn : null;
    }

    private function resolveSemanticKancaColumn(array $tableColumns): ?string
    {
        $bestColumn = null;
        $bestScore = PHP_INT_MIN;

        foreach ($tableColumns as $column) {
            $column = (string) $column;
            $normalized = $this->normalizeManagementColumnKey($column);
            if ($normalized === '') {
                continue;
            }

            $score = 0;
            $tokens = array_values(array_filter(explode('_', $normalized)));
            $tokenLookup = array_fill_keys($tokens, true);

            if (in_array($normalized, [
                'kanca',
                'nama_kanca',
                'kantor_cabang',
                'nama_kantor_cabang',
                'cabang',
                'nama_cabang',
                'branch',
                'nama_branch',
                'mbdesc',
                'brdesc',
                'nama_kci',
                'nama_kcp',
            ], true)) {
                $score += 120;
            }

            if (isset($tokenLookup['kanca']) || isset($tokenLookup['cabang']) || isset($tokenLookup['branch'])) {
                $score += 80;
            }

            if (isset($tokenLookup['kantor']) && isset($tokenLookup['cabang'])) {
                $score += 50;
            }

            if (isset($tokenLookup['nama'])) {
                $score += 30;
            }

            if (isset($tokenLookup['mbdesc']) || isset($tokenLookup['brdesc'])) {
                $score += 90;
            }

            if (isset($tokenLookup['kci']) || isset($tokenLookup['kcp'])) {
                $score += 40;
            }

            if (isset($tokenLookup['unit']) || isset($tokenLookup['uker']) || isset($tokenLookup['outlet']) || isset($tokenLookup['merchant'])) {
                $score -= 120;
            }

            if (isset($tokenLookup['kode']) || isset($tokenLookup['code']) || str_starts_with($normalized, 'kode_')) {
                $score -= 70;
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestColumn = $column;
            }
        }

        return $bestScore > 0 ? $bestColumn : null;
    }

    private function resolveManagementScopeColumns(string $tableName, array $tableColumns): array
    {
        return $this->reportManagementService()->resolveManagementScopeColumns($tableName, $tableColumns);
    }

    private function formatManagementPeriodLabel($value, ?string $columnName = null): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $columnName = strtolower((string) $columnName);
        $looksLikePeriodColumn = str_contains($columnName, 'periode')
            || str_contains($columnName, 'period')
            || str_contains($columnName, 'posisi')
            || str_contains($columnName, 'tanggal')
            || str_contains($columnName, 'tgl');

        if (preg_match('/^\d{4}-\d{2}$/', $value) === 1) {
            return $value;
        }

        if (preg_match('/^(?<month>[A-Za-z]+)[\s-](?<year>\d{4})$/', $value, $matches) === 1) {
            try {
                $month = \DateTimeImmutable::createFromFormat('!F', $matches['month'])
                    ?: \DateTimeImmutable::createFromFormat('!M', $matches['month']);
                if ($month === false) {
                    return $value;
                }

                $monthNumber = (int) $month->format('n');
                return Carbon::create((int) $matches['year'], $monthNumber, 1)->format('Y-m');
            } catch (\Throwable) {
            }
        }

        $strictNormalized = StrictDateParser::normalize($value);
        if ($strictNormalized !== null) {
            return $strictNormalized;
        }

        if ($looksLikePeriodColumn) {
            $normalized = str_replace('/', '-', $value);

            foreach ([
                'Y-m-d H:i:s',
                'Y-m-d H:i',
                'Y-m-d',
                'd-m-Y H:i:s',
                'd-m-Y H:i',
                'd-m-Y',
                'd/m/Y H:i:s',
                'd/m/Y H:i',
                'd/m/Y',
            ] as $format) {
                try {
                    return Carbon::createFromFormat($format, $normalized)->format('Y-m-d');
                } catch (\Throwable) {
                }
            }

            try {
                return Carbon::parse($normalized)->format('Y-m-d');
            } catch (\Throwable) {
            }
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value) === 1) {
            return substr($value, 0, 10);
        }

        return $value;
    }

    private function buildManagementRows(string $tableName, ?string $periodColumn, ?string $kancaColumn, int $maxRows): array
    {
        if ($periodColumn === null && $kancaColumn === null) {
            $count = (int) DB::table($tableName)->count();

            return [[
                [
                    'period' => '(Tanpa Periode)',
                    'kanca' => '(Semua)',
                    'row_count' => $count,
                    'period_is_null' => false,
                    'kanca_is_null' => false,
                ],
            ], false];
        }

        $query = DB::table($tableName);
        $selects = ['COUNT(*) as row_count'];

        if ($periodColumn !== null) {
            $safePeriod = str_replace('`', '``', $periodColumn);
            $selects[] = "`{$safePeriod}` as period_value";
            $query->groupBy($periodColumn)->orderByDesc($periodColumn);
        }

        if ($kancaColumn !== null) {
            $safeKanca = str_replace('`', '``', $kancaColumn);
            $selects[] = "`{$safeKanca}` as kanca_value";
            $query->groupBy($kancaColumn)->orderBy($kancaColumn);
        }

        $result = $query
            ->selectRaw(implode(', ', $selects))
            ->limit($maxRows + 1)
            ->get();

        $truncated = $result->count() > $maxRows;
        if ($truncated) {
            $result = $result->take($maxRows);
        }

        $rows = [];
        foreach ($result as $item) {
            $periodRaw = $periodColumn !== null ? ($item->period_value ?? null) : null;
            $kancaRaw = $kancaColumn !== null ? ($item->kanca_value ?? null) : null;
            $periodLabel = $periodRaw === null || trim((string) $periodRaw) === ''
                ? ($periodColumn !== null ? '(Blank)' : '(Tanpa Periode)')
                : $this->formatManagementPeriodLabel($periodRaw, $periodColumn);
            $kancaLabel = $kancaRaw === null || trim((string) $kancaRaw) === ''
                ? ($kancaColumn !== null ? '(Blank)' : '(Semua)')
                : (string) $kancaRaw;

            $rows[] = [
                'period' => $periodRaw === null || trim((string) $periodRaw) === '' ? '' : (string) $periodRaw,
                'period_label' => $periodLabel,
                'kanca' => $kancaRaw === null || trim((string) $kancaRaw) === '' ? '' : (string) $kancaRaw,
                'kanca_label' => $kancaLabel,
                'row_count' => (int) ($item->row_count ?? 0),
                'period_is_null' => $periodRaw === null || trim((string) $periodRaw) === '',
                'kanca_is_null' => $kancaRaw === null || trim((string) $kancaRaw) === '',
            ];
        }

        return [$rows, $truncated];
    }

    private function paginateManagementPeriods(array $rows, int $page, int $perPage, bool $hasPeriodColumn): array
    {
        $periods = [];
        $periodOrder = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $periodLabel = (string) ($row['period_label'] ?? $row['period'] ?? ($hasPeriodColumn ? '(Blank)' : '(Tanpa Periode)'));
            $periodIsNull = (bool) ($row['period_is_null'] ?? false);
            $bucketKey = $hasPeriodColumn
                ? ($periodIsNull ? '__blank__' : 'value:' . $periodLabel)
                : '__single_period__';

            if (!isset($periods[$bucketKey])) {
                $periodOrder[] = $bucketKey;
                $periods[$bucketKey] = [
                    'period' => $periodLabel,
                    'period_is_null' => $periodIsNull,
                    'group_count' => 0,
                    'total_rows' => 0,
                    'rows' => [],
                ];
            }

            $periods[$bucketKey]['rows'][] = $row;
            $periods[$bucketKey]['group_count']++;
            $periods[$bucketKey]['total_rows'] += (int) ($row['row_count'] ?? 0);
        }

        $orderedPeriods = array_values(array_map(
            static fn (string $bucketKey): array => $periods[$bucketKey],
            $periodOrder
        ));

        $totalPeriods = count($orderedPeriods);
        $perPage = max(1, $perPage);
        $totalPages = max(1, (int) ceil($totalPeriods / $perPage));
        $currentPage = min(max(1, $page), $totalPages);
        $offset = ($currentPage - 1) * $perPage;
        $currentPeriods = array_slice($orderedPeriods, $offset, $perPage);

        $currentRows = [];
        foreach ($currentPeriods as $period) {
            foreach ($period['rows'] as $row) {
            $currentRows[] = $row;
        }
        }

        return [
            'rows' => $currentRows,
            'periods' => $currentPeriods,
            'pagination' => [
                'current_page' => $currentPage,
                'per_page' => $perPage,
                'total_pages' => $totalPages,
                'total_periods' => $totalPeriods,
                'has_prev' => $currentPage > 1,
                'has_next' => $currentPage < $totalPages,
                'from_period' => $totalPeriods === 0 ? 0 : ($offset + 1),
                'to_period' => min($offset + $perPage, $totalPeriods),
            ],
        ];
    }

    private function applyBlankValueConstraint($query, string $column): void
    {
        $safeColumn = str_replace('`', '``', $column);

        $query->where(function ($innerQuery) use ($column, $safeColumn) {
            $innerQuery
                ->whereNull($column)
                ->orWhere($column, '')
                ->orWhereRaw("TRIM(`{$safeColumn}`) = ''");
        });
    }

    private function buildDeleteScopeQuery(
        string $tableName,
        ?string $periodColumn,
        ?string $kancaColumn,
        ?string $periodFilter,
        ?string $kancaFilter,
        bool $periodIsNull,
        bool $kancaIsNull
    ): array {
        $query = DB::table($tableName);
        $hasWhereClause = false;

        if ($periodColumn !== null) {
            if ($periodIsNull) {
                $this->applyBlankValueConstraint($query, $periodColumn);
                $hasWhereClause = true;
            } elseif ($periodFilter !== null && $periodFilter !== '') {
                $this->applyManagedPeriodFilterConstraint($query, $tableName, $periodColumn, $periodFilter);
                $hasWhereClause = true;
            }
        }

        if ($kancaColumn !== null) {
            if ($kancaIsNull) {
                $this->applyBlankValueConstraint($query, $kancaColumn);
                $hasWhereClause = true;
            } elseif ($kancaFilter !== null && $kancaFilter !== '') {
                $query->where($kancaColumn, $kancaFilter);
                $hasWhereClause = true;
            }
        }

        return [$query, $hasWhereClause];
    }

    private function buildDeleteScopeQueryFromScopes(
        string $tableName,
        ?string $periodColumn,
        ?string $kancaColumn,
        array $scopes
    ): array {
        $query = DB::table($tableName);

        if ($periodColumn === null && $kancaColumn === null) {
            return [$query, true];
        }

        $validScopes = [];

        foreach ($scopes as $scope) {
            if (!is_array($scope)) {
                continue;
            }

            $periodFilter = array_key_exists('period_filter', $scope)
                ? (($scope['period_filter'] ?? '') !== '' ? (string) $scope['period_filter'] : null)
                : null;
            $kancaFilter = array_key_exists('kanca_filter', $scope)
                ? (($scope['kanca_filter'] ?? '') !== '' ? (string) $scope['kanca_filter'] : null)
                : null;
            $periodIsNull = (bool) ($scope['period_is_null'] ?? false);
            $kancaIsNull = (bool) ($scope['kanca_is_null'] ?? false);
            $isLw325Fallback = $this->isLw325BlankCreatedAtFallbackScope($tableName, $scope);

            $hasPeriodConstraint = $periodColumn !== null && ($periodIsNull || ($periodFilter !== null && $periodFilter !== ''));
            $hasKancaConstraint = $kancaColumn !== null && ($kancaIsNull || ($kancaFilter !== null && $kancaFilter !== ''));
            $hasFallbackConstraint = $isLw325Fallback && (($scope['fallback_period_filter'] ?? null) !== null && trim((string) ($scope['fallback_period_filter'] ?? '')) !== '');

            if (!$hasPeriodConstraint && !$hasKancaConstraint && !$hasFallbackConstraint) {
                continue;
            }

            $validScopes[] = [
                'period_filter' => $periodFilter,
                'kanca_filter' => $kancaFilter,
                'period_is_null' => $periodIsNull,
                'kanca_is_null' => $kancaIsNull,
                'fallback_mode' => array_key_exists('fallback_mode', $scope) ? (string) ($scope['fallback_mode'] ?? '') : null,
                'fallback_period_column' => array_key_exists('fallback_period_column', $scope) ? (string) ($scope['fallback_period_column'] ?? '') : null,
                'fallback_period_filter' => array_key_exists('fallback_period_filter', $scope) ? (string) ($scope['fallback_period_filter'] ?? '') : null,
                'fallback_period_label' => array_key_exists('fallback_period_label', $scope) ? (string) ($scope['fallback_period_label'] ?? '') : null,
            ];
        }

        if (empty($validScopes)) {
            return [$query, false];
        }

        $query->where(function ($outerQuery) use ($validScopes, $tableName, $periodColumn, $kancaColumn) {
            foreach ($validScopes as $scope) {
                $outerQuery->orWhere(function ($innerQuery) use ($scope, $tableName, $periodColumn, $kancaColumn) {
                    $applied = false;

                    if ($this->isLw325BlankCreatedAtFallbackScope($tableName, $scope)) {
                        if ($periodColumn !== null) {
                            $this->applyBlankValueConstraint($innerQuery, $periodColumn);
                            $applied = true;
                        }

                        if ($kancaColumn !== null) {
                            $this->applyBlankValueConstraint($innerQuery, $kancaColumn);
                            $applied = true;
                        }

                        $fallbackPeriodColumn = trim((string) ($scope['fallback_period_column'] ?? ''));
                        $fallbackPeriodFilter = trim((string) ($scope['fallback_period_filter'] ?? ''));
                        if ($fallbackPeriodColumn !== '' && $fallbackPeriodFilter !== '') {
                            $innerQuery->where($fallbackPeriodColumn, $fallbackPeriodFilter);
                            $applied = true;
                        }
                    } else {
                        if ($periodColumn !== null) {
                            if ((bool) ($scope['period_is_null'] ?? false)) {
                                $this->applyBlankValueConstraint($innerQuery, $periodColumn);
                                $applied = true;
                            } elseif (($scope['period_filter'] ?? null) !== null && $scope['period_filter'] !== '') {
                                $this->applyManagedPeriodFilterConstraint($innerQuery, $tableName, $periodColumn, (string) $scope['period_filter']);
                                $applied = true;
                            }
                        }

                        if ($kancaColumn !== null) {
                            if ((bool) ($scope['kanca_is_null'] ?? false)) {
                                $this->applyBlankValueConstraint($innerQuery, $kancaColumn);
                                $applied = true;
                            } elseif (($scope['kanca_filter'] ?? null) !== null && $scope['kanca_filter'] !== '') {
                                $innerQuery->where($kancaColumn, (string) $scope['kanca_filter']);
                                $applied = true;
                            }
                        }
                    }

                    if (!$applied) {
                        $innerQuery->whereRaw('1 = 0');
                    }
                });
            }
        });

        return [$query, true];
    }

    private function applyManagedPeriodFilterConstraint($query, string $tableName, string $periodColumn, string $periodFilter): void
    {
        $normalizedFilter = trim($periodFilter);
        if (preg_match('/^\d{4}-\d{2}$/', $normalizedFilter) === 1) {
            $safeColumn = str_replace('`', '``', $periodColumn);
            $periodVariants = $this->buildManagedMonthPeriodVariants($normalizedFilter);
            $query->where(function ($periodQuery) use ($periodColumn, $safeColumn, $normalizedFilter, $periodVariants) {
                $periodQuery->whereRaw("SUBSTR(CAST(`{$safeColumn}` AS CHAR), 1, 7) = ?", [$normalizedFilter]);

                if ($periodVariants !== []) {
                    $periodQuery->orWhereIn($periodColumn, $periodVariants);
                }
            });
            return;
        }

        $query->where($periodColumn, $periodFilter);
    }

    /**
     * @return array<int, string>
     */
    private function buildManagedMonthPeriodVariants(string $normalizedMonth): array
    {
        if (preg_match('/^(\d{4})-(\d{2})$/', trim($normalizedMonth), $matches) !== 1) {
            return [];
        }

        $year = (int) $matches[1];
        $month = (int) $matches[2];
        if ($year < 1900 || $month < 1 || $month > 12) {
            return [];
        }

        $englishLong = [
            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
        ];
        $englishShort = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
            5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug',
            9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec',
        ];
        $indonesianLong = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];
        $indonesianShort = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
            5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu',
            9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
        ];

        return array_values(array_unique(array_filter([
            sprintf('%04d-%02d', $year, $month),
            sprintf('%02d-%04d', $month, $year),
            sprintf('%02d/%04d', $month, $year),
            $englishLong[$month] . ' ' . $year,
            $englishShort[$month] . ' ' . $year,
            $indonesianLong[$month] . ' ' . $year,
            $indonesianShort[$month] . ' ' . $year,
        ], static fn (string $value): bool => trim($value) !== '')));
    }

    private function normalizeDeleteScopes(array $validated): array
    {
        $rawScopes = [];
        if (isset($validated['scopes']) && is_array($validated['scopes']) && !empty($validated['scopes'])) {
            $rawScopes = $validated['scopes'];
        } else {
            $rawScopes = [[
                'period' => $validated['period'] ?? null,
                'kanca' => $validated['kanca'] ?? null,
                'period_is_null' => (bool) ($validated['period_is_null'] ?? false),
                'kanca_is_null' => (bool) ($validated['kanca_is_null'] ?? false),
            ]];
        }

        $normalized = [];
        $seen = [];

        foreach ($rawScopes as $scope) {
            if (!is_array($scope)) {
                continue;
            }

            $periodFilter = array_key_exists('period_filter', $scope)
                ? (($scope['period_filter'] ?? '') !== '' ? (string) $scope['period_filter'] : null)
                : (array_key_exists('period', $scope)
                    ? (($scope['period'] ?? '') !== '' ? (string) $scope['period'] : null)
                    : null);
            $kancaFilter = array_key_exists('kanca_filter', $scope)
                ? (($scope['kanca_filter'] ?? '') !== '' ? (string) $scope['kanca_filter'] : null)
                : (array_key_exists('kanca', $scope)
                    ? (($scope['kanca'] ?? '') !== '' ? (string) $scope['kanca'] : null)
                    : null);
            $periodIsNull = (bool) ($scope['period_is_null'] ?? false);
            $kancaIsNull = (bool) ($scope['kanca_is_null'] ?? false);
            $fallbackMode = array_key_exists('fallback_mode', $scope) ? trim((string) ($scope['fallback_mode'] ?? '')) : null;
            $fallbackPeriodColumn = array_key_exists('fallback_period_column', $scope) ? trim((string) ($scope['fallback_period_column'] ?? '')) : null;
            $fallbackPeriodFilter = array_key_exists('fallback_period_filter', $scope) ? trim((string) ($scope['fallback_period_filter'] ?? '')) : null;
            $fallbackPeriodLabel = array_key_exists('fallback_period_label', $scope) ? trim((string) ($scope['fallback_period_label'] ?? '')) : null;

            $scopeKey = json_encode([
                $periodFilter,
                $kancaFilter,
                $periodIsNull,
                $kancaIsNull,
                $fallbackMode,
                $fallbackPeriodColumn,
                $fallbackPeriodFilter,
            ]);

            if ($scopeKey === false || isset($seen[$scopeKey])) {
                continue;
            }

            $seen[$scopeKey] = true;
            $normalized[] = [
                'period_filter' => $periodFilter,
                'period_label' => array_key_exists('period_label', $scope) ? (string) ($scope['period_label'] ?? '') : null,
                'kanca_filter' => $kancaFilter,
                'kanca_label' => array_key_exists('kanca_label', $scope) ? (string) ($scope['kanca_label'] ?? '') : null,
                'period_is_null' => $periodIsNull,
                'kanca_is_null' => $kancaIsNull,
                'fallback_mode' => $fallbackMode,
                'fallback_period_column' => $fallbackPeriodColumn,
                'fallback_period_filter' => $fallbackPeriodFilter,
                'fallback_period_label' => $fallbackPeriodLabel,
            ];
        }

        return $normalized;
    }

    private function extractDeleteScopesFromState(array $state): array
    {
        $rawScopes = $state['scopes'] ?? null;
        $scopes = [];

        if (is_array($rawScopes) && !empty($rawScopes)) {
            foreach ($rawScopes as $scope) {
                if (!is_array($scope)) {
                    continue;
                }

                $scopes[] = [
                    'period_filter' => array_key_exists('period_filter', $scope)
                        ? (($scope['period_filter'] ?? '') !== '' ? (string) $scope['period_filter'] : null)
                        : null,
                    'period_label' => array_key_exists('period_label', $scope)
                        ? (($scope['period_label'] ?? '') !== '' ? (string) $scope['period_label'] : null)
                        : null,
                    'kanca_filter' => array_key_exists('kanca_filter', $scope)
                        ? (($scope['kanca_filter'] ?? '') !== '' ? (string) $scope['kanca_filter'] : null)
                        : null,
                    'kanca_label' => array_key_exists('kanca_label', $scope)
                        ? (($scope['kanca_label'] ?? '') !== '' ? (string) $scope['kanca_label'] : null)
                        : null,
                    'period_is_null' => (bool) ($scope['period_is_null'] ?? false),
                    'kanca_is_null' => (bool) ($scope['kanca_is_null'] ?? false),
                    'fallback_mode' => array_key_exists('fallback_mode', $scope)
                        ? (($scope['fallback_mode'] ?? '') !== '' ? (string) $scope['fallback_mode'] : null)
                        : null,
                    'fallback_period_column' => array_key_exists('fallback_period_column', $scope)
                        ? (($scope['fallback_period_column'] ?? '') !== '' ? (string) $scope['fallback_period_column'] : null)
                        : null,
                    'fallback_period_filter' => array_key_exists('fallback_period_filter', $scope)
                        ? (($scope['fallback_period_filter'] ?? '') !== '' ? (string) $scope['fallback_period_filter'] : null)
                        : null,
                    'fallback_period_label' => array_key_exists('fallback_period_label', $scope)
                        ? (($scope['fallback_period_label'] ?? '') !== '' ? (string) $scope['fallback_period_label'] : null)
                        : null,
                ];
            }
        }

        if (!empty($scopes)) {
            return $scopes;
        }

        return [[
            'period_filter' => array_key_exists('period_filter', $state)
                ? (($state['period_filter'] ?? '') !== '' ? (string) $state['period_filter'] : null)
                : null,
            'period_label' => array_key_exists('period_label', $state)
                ? (($state['period_label'] ?? '') !== '' ? (string) $state['period_label'] : null)
                : null,
            'kanca_filter' => array_key_exists('kanca_filter', $state)
                ? (($state['kanca_filter'] ?? '') !== '' ? (string) $state['kanca_filter'] : null)
                : null,
            'kanca_label' => array_key_exists('kanca_label', $state)
                ? (($state['kanca_label'] ?? '') !== '' ? (string) $state['kanca_label'] : null)
                : null,
            'period_is_null' => (bool) ($state['period_is_null'] ?? false),
            'kanca_is_null' => (bool) ($state['kanca_is_null'] ?? false),
            'fallback_mode' => array_key_exists('fallback_mode', $state)
                ? (($state['fallback_mode'] ?? '') !== '' ? (string) $state['fallback_mode'] : null)
                : null,
            'fallback_period_column' => array_key_exists('fallback_period_column', $state)
                ? (($state['fallback_period_column'] ?? '') !== '' ? (string) $state['fallback_period_column'] : null)
                : null,
            'fallback_period_filter' => array_key_exists('fallback_period_filter', $state)
                ? (($state['fallback_period_filter'] ?? '') !== '' ? (string) $state['fallback_period_filter'] : null)
                : null,
            'fallback_period_label' => array_key_exists('fallback_period_label', $state)
                ? (($state['fallback_period_label'] ?? '') !== '' ? (string) $state['fallback_period_label'] : null)
                : null,
        ]];
    }

    private function resolveIdentityColumn(array $tableColumns): ?string
    {
        return $this->resolveColumnName($tableColumns, ['uniqueid_namareport', 'uniqueid_SMPN', 'id']);
    }

    private function resolveDeleteChunkSize(string $tableName, ?string $identityColumn): int
    {
        $tableConfig = self::DELETE_INDEX_HINTS[$tableName] ?? null;
        $override = is_array($tableConfig) ? ($tableConfig['chunk_size'] ?? null) : null;
        if (is_int($override) && $override > 0) {
            return $override;
        }

        return $identityColumn !== null
            ? self::DELETE_CHUNK_SIZE_WITH_IDENTITY
            : self::DELETE_CHUNK_SIZE;
    }

    private function resolveEffectiveDeleteBatchSize(array $state): int
    {
        $configuredChunkSize = max(1, (int) ($state['chunk_size'] ?? self::DELETE_CHUNK_SIZE));
        $remainingRows = max(0, (int) ($state['remaining_rows'] ?? $state['total_rows'] ?? 0));

        return $remainingRows > 0
            ? max(1, min($configuredChunkSize, $remainingRows))
            : $configuredChunkSize;
    }

    private function resolveDeleteScopeStrategy(
        string $tableName,
        ?string $periodColumn,
        ?string $kancaColumn,
        ?string $identityColumn,
        array $scope,
        string $deletePlan = 'normal'
    ): string {
        if ($this->isLw325BlankCreatedAtFallbackScope($tableName, $scope)) {
            return 'lw325_created_at_blank_scope';
        }

        if ($this->shouldUseBlankScopeRecoveryPlan($tableName, $periodColumn, $kancaColumn, $scope, $deletePlan)) {
            return 'blank_scope_direct_batch';
        }

        if ($this->scopeSupportsPartitionDeleteShortcut($tableName, $periodColumn, $kancaColumn, $scope)) {
            return 'partition_truncate';
        }

        if ($identityColumn === null) {
            return ($periodColumn === null && $kancaColumn === null)
                ? 'full_table_direct_delete'
                : 'direct_delete';
        }

        foreach ($this->buildDeleteConstraintVariants($periodColumn, $kancaColumn, $scope) as $variant) {
            if ($this->resolveDeleteIndexHint($tableName, $periodColumn, $kancaColumn, $identityColumn, $variant) !== null) {
                return 'indexed_batch_delete';
            }
        }

        return 'identity_batch_delete';
    }

    private function shouldUseBlankScopeRecoveryPlan(
        string $tableName,
        ?string $periodColumn,
        ?string $kancaColumn,
        array $scope,
        string $deletePlan
    ): bool {
        if ($this->isLw325BlankCreatedAtFallbackScope($tableName, $scope)) {
            return false;
        }

        if ($deletePlan === 'blank_period_scope') {
            return $periodColumn !== null && (bool) ($scope['period_is_null'] ?? false);
        }

        if ($deletePlan !== 'recovery_blank_scope') {
            return false;
        }

        if (strtolower(trim($tableName)) !== 'daily_loan_dinamis' || $periodColumn === null || $kancaColumn === null) {
            return false;
        }

        $periodValue = trim((string) ($scope['period_filter'] ?? ''));

        return $periodValue !== ''
            && !(bool) ($scope['period_is_null'] ?? false)
            && (bool) ($scope['kanca_is_null'] ?? false);
    }

    private function supportsNativeDeleteTruncateShortcut(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
    }

    private function truncateManagedDeleteTable(string $tableName): void
    {
        if (!$this->supportsNativeDeleteTruncateShortcut()) {
            DB::table($tableName)->delete();
            return;
        }

        $lockWaitSeconds = max(1, (int) config('import.direct_load.snapshot_delete_lock_wait_seconds', 8));
        $wrappedTable = '`' . str_replace('`', '``', $tableName) . '`';
        $originalLockWait = null;

        try {
            $row = DB::selectOne('SELECT @@SESSION.lock_wait_timeout AS lock_wait_timeout');
            $originalLockWait = isset($row->lock_wait_timeout) ? (int) $row->lock_wait_timeout : null;
        } catch (Throwable) {
            $originalLockWait = null;
        }

        try {
            DB::statement('SET SESSION lock_wait_timeout = ' . $lockWaitSeconds);
            DB::statement("TRUNCATE TABLE {$wrappedTable}");
        } finally {
            if ($originalLockWait !== null) {
                try {
                    DB::statement('SET SESSION lock_wait_timeout = ' . max(1, $originalLockWait));
                } catch (Throwable) {
                    // Ignore restore failures; the connection can still be safely reused.
                }
            }
        }
    }

    private function elapsedManagedDeleteMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    private function writeManagedDeleteAudit(string $tableName, ?string $periodHint, string $action, string $status, array $payload = []): void
    {
        $tableName = trim($tableName);
        if ($tableName === '' || !Schema::hasTable(self::DELETE_AUDIT_TABLE)) {
            return;
        }

        try {
            DB::table(self::DELETE_AUDIT_TABLE)->insert([
                'import_job_id' => null,
                'source' => static::class,
                'table_name' => $tableName,
                'period_hint' => $this->normalizeManagedDeleteAuditPeriodHint($periodHint),
                'action' => $action,
                'status' => $status,
                'duration_ms' => $payload['duration_ms'] ?? null,
                'affected_rows' => $payload['affected_rows'] ?? null,
                'message' => $payload['message'] ?? null,
                'context' => isset($payload['context']) ? json_encode($payload['context'], JSON_UNESCAPED_UNICODE) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('Gagal menulis audit delete report management: ' . $e->getMessage(), [
                'table_name' => $tableName,
                'action' => $action,
                'status' => $status,
            ]);
        }
    }

    private function normalizeManagedDeleteAuditPeriodHint(?string $periodHint): ?string
    {
        $value = trim((string) $periodHint);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}$/', $value) === 1) {
            return $value . '-01';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return $value;
        }

        try {
            return \Carbon\Carbon::parse($value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    private function deleteScopedRows(
        string $tableName,
        Builder $baseQuery,
        ?string $identityColumn,
        ?int $chunkSize = null,
        ?string $periodColumn = null,
        ?string $kancaColumn = null,
        array $scope = [],
        ?string $deleteId = null,
        string $deletePlan = 'normal'
    ): int
    {
        if ($this->isLw325BlankCreatedAtFallbackScope($tableName, $scope)) {
            return $this->deleteLw325BlankCreatedAtScope(
                $tableName,
                $baseQuery,
                $identityColumn,
                $chunkSize,
                $deleteId
            );
        }

        if ($this->shouldUseBlankScopeRecoveryPlan($tableName, $periodColumn, $kancaColumn, $scope, $deletePlan)) {
            if ($deletePlan === 'blank_period_scope') {
                $result = $this->managedReportDeleteRecoveryService()->deleteBlankPeriodScope(
                    $tableName,
                    (string) $periodColumn,
                    $kancaColumn,
                    $scope,
                    $identityColumn,
                    max(1, (int) ($chunkSize ?? self::DELETE_CHUNK_SIZE)),
                    $deleteId !== null ? fn ($aff, $tot, $batch) => $this->heartbeatManagedDeleteState($deleteId, "Blank period delete... Batch $batch ($tot rows deleted)") : null,
                    $deleteId !== null ? fn (): bool => $this->isManagedDeleteCancellationRequested($deleteId) : null
                );
            } else {
                $periodValue = trim((string) ($scope['period_filter'] ?? ''));
                $result = $this->managedReportDeleteRecoveryService()->deleteBlankKancaPeriodScope(
                    $tableName,
                    (string) $periodColumn,
                    (string) $kancaColumn,
                    $periodValue,
                    $identityColumn,
                    max(1, (int) ($chunkSize ?? self::DELETE_CHUNK_SIZE)),
                    $deleteId !== null ? fn ($aff, $tot, $batch) => $this->heartbeatManagedDeleteState($deleteId, "Recovering blank scope... Batch $batch ($tot rows deleted)") : null,
                    $deleteId !== null ? fn (): bool => $this->isManagedDeleteCancellationRequested($deleteId) : null
                );
            }

            return (int) ($result['deleted_rows'] ?? 0);
        }

        $this->bulkLoadService()->assertTransactionalTable($tableName, 'delete data report');

        return $this->bulkLoadService()->withTableWriteLock($tableName, function () use (
            $tableName,
            $baseQuery,
            $identityColumn,
            $chunkSize,
            $periodColumn,
            $kancaColumn,
            $scope,
            $deleteId
        ): int {
            $limit = max(1, (int) ($chunkSize ?? self::DELETE_CHUNK_SIZE));
            $connection = $baseQuery->getConnection();
            $driverName = $connection->getDriverName();
            $shouldToggleSnapshotFlag = $this->shouldToggleSnapshotInvalidationFlag($driverName);
            $supportsIndexedDeleteShortcut = in_array($driverName, ['mysql', 'mariadb'], true);

            if ($shouldToggleSnapshotFlag) {
                try {
                    $connection->statement('SET @skip_snapshot_invalidation = 1');
                } catch (\Throwable) {
                    // Non-fatal: session variable not supported (e.g. SQLite in tests); proceed without it
                    $shouldToggleSnapshotFlag = false;
                }
            }

            try {
                if ($deleteId !== null && $this->isManagedDeleteCancellationRequested($deleteId)) {
                    return 0;
                }

                if (!empty($scope)) {
                    $partitionAffected = $this->tryDeleteScopeByPartition(
                        $tableName,
                        $baseQuery,
                        $periodColumn,
                        $kancaColumn,
                        $scope
                    );

                    if ($partitionAffected !== null) {
                        return $partitionAffected;
                    }
                }

                if ($identityColumn === null) {
                    $deleted = (int) (clone $baseQuery)->count();
                    if ($deleted <= 0) {
                        return 0;
                    }

                    if ($deleteId !== null && $this->isManagedDeleteCancellationRequested($deleteId)) {
                        return 0;
                    }

                    return (int) (clone $baseQuery)->delete();
                }

                if ($identityColumn !== null && Schema::hasColumn($tableName, $identityColumn)) {
                    $variants = $this->buildDeleteConstraintVariants($periodColumn, $kancaColumn, $scope);
                    if (empty($variants)) {
                        return 0;
                    }

                    $startedAt = microtime(true);
                    $deleted = 0;
                    $batches = 0;

                    foreach ($variants as $variant) {
                        $indexHint = $this->resolveDeleteIndexHint(
                            $tableName,
                            $periodColumn,
                            $kancaColumn,
                            $identityColumn,
                            $variant
                        );
                        if (!$supportsIndexedDeleteShortcut) {
                            $indexHint = null;
                        }
                        $supportsFastDelete = $indexHint !== null;
                        $batchLimit = $supportsFastDelete
                            ? max(1, $limit)
                            : min($limit, self::DELETE_CHUNK_SIZE);

                        do {
                            if ($deleteId !== null && $this->isManagedDeleteCancellationRequested($deleteId)) {
                                return $deleted;
                            }

                            if ($supportsFastDelete && $this->supportsDirectPredicateDelete($variant)) {
                                $affected = $this->deleteRowsByDirectPredicateBatch(
                                    $tableName,
                                    $variant,
                                    $batchLimit,
                                    $connection,
                                    $deleteId,
                                    $this->shouldUseDirectDeleteWithoutLimit(
                                        $tableName,
                                        $periodColumn,
                                        $kancaColumn,
                                        $variant,
                                        $batchLimit
                                    )
                                );
                            } elseif ($supportsFastDelete) {
                                $affected = $this->deleteRowsByIndexedSubqueryBatch($tableName, $identityColumn, $variant, $batchLimit, $indexHint, $connection, $deleteId);
                            } else {
                                $affected = $this->deleteRowsByIdentityBatch(
                                    $tableName,
                                    $this->makeDeleteVariantQuery($tableName, $variant),
                                    $identityColumn,
                                    $batchLimit,
                                    $connection,
                                    $deleteId
                                );
                            }

                            if ($affected <= 0) {
                                break;
                            }

                            $deleted += $affected;
                            $batches++;
                        } while (
                            $batches < self::DELETE_MAX_BATCHES_PER_TICK
                            && ((microtime(true) - $startedAt) * 1000) < self::DELETE_TICK_TIME_BUDGET_MS
                        );

                        if (
                            $batches >= self::DELETE_MAX_BATCHES_PER_TICK
                            || ((microtime(true) - $startedAt) * 1000) >= self::DELETE_TICK_TIME_BUDGET_MS
                        ) {
                            break;
                        }
                    }

                    return $deleted;
                }

                throw new \RuntimeException("Delete parsial untuk tabel `{$tableName}` diblokir karena tidak ada kolom identity/unique yang aman.");
            } finally {
                if ($shouldToggleSnapshotFlag) {
                    $connection->statement('SET @skip_snapshot_invalidation = NULL');
                }
            }
        });
    }

    private function deleteRowsByIdentityBatch(string $tableName, Builder $baseQuery, string $identityColumn, int $limit, $connection = null, ?string $deleteId = null): int
    {
        $connection = $connection ?: DB::connection();
        $deleted = 0;

        if ($deleteId !== null && $this->isManagedDeleteCancellationRequested($deleteId)) {
            return 0;
        }

        // 1. Secara aman prioritaskan penghapusan baris gagal import/korup (identity null/string kosong)
        // karena baris ini menyangkut di query utama tapi akan skip di chunking identity
        $danglingCacheKey = $deleteId ? "rm_delete_dgl_{$deleteId}_" . md5($tableName) : null;
        if (!$danglingCacheKey || !\Illuminate\Support\Facades\Cache::has($danglingCacheKey)) {
            $danglingQuery = (clone $baseQuery)->where(function ($query) use ($identityColumn) {
                $query->whereNull($identityColumn)
                      ->orWhere($identityColumn, '');
            });

            // DB builder default limit() delete aman digunakan di MySQL/MariaDB
            $deletedDangling = (int) (clone $danglingQuery)->limit($limit)->delete();
            
            if ($deletedDangling > 0) {
                // Jangan langsung return jika baru menghapus sedikit baris dangling, 
                // lanjutkan ke fase pencarian identity agar worker tetap produktif.
                if ($deletedDangling >= $limit) {
                    return $deletedDangling;
                }
                $deleted += $deletedDangling;
            }

            if ($danglingCacheKey) {
                \Illuminate\Support\Facades\Cache::put($danglingCacheKey, true, 86400);
            }
        }

        if ($deleteId !== null && $this->isManagedDeleteCancellationRequested($deleteId)) {
            return 0;
        }

        // 2. Normal batch delete untuk baris aktif (memiliki identity sah)
        $identityValues = (clone $baseQuery)
            ->select($identityColumn)
            ->whereNotNull($identityColumn)
            ->orderBy($identityColumn)
            ->limit($limit)
            ->where($identityColumn, '<>', '')
            ->pluck($identityColumn)
            ->filter(static fn ($value) => $value !== null && $value !== '')
            ->values()
            ->all();

        if (empty($identityValues)) {
            return 0;
        }

        $deletedTotal = $deleted;
        $deleteBatchSize = 2000;

        foreach (array_chunk($identityValues, $deleteBatchSize) as $chunk) {
            if ($deleteId !== null && $this->isManagedDeleteCancellationRequested($deleteId)) {
                break;
            }

            try {
                $batchCount = (int) $connection->table($tableName)
                    ->whereIn($identityColumn, $chunk)
                    ->delete();
            } catch (\Illuminate\Database\QueryException $chunkException) {
                $errorCode = $chunkException->errorInfo[1] ?? null;
                // 1213 = deadlock: log and re-throw so caller can handle/audit
                if ($errorCode == 1213 || str_contains($chunkException->getMessage(), 'Deadlock')) {
                    Log::warning('Deadlock terdeteksi saat delete identity batch.', [
                        'delete_id' => $deleteId,
                        'table_name' => $tableName,
                        'identity_column' => $identityColumn,
                        'chunk_size' => count($chunk),
                        'deleted_so_far' => $deletedTotal,
                        'error_code' => $errorCode,
                    ]);
                }

                throw $chunkException;
            }
            $deletedTotal += $batchCount;
        }

        return $deletedTotal;
    }

    private function buildDeleteConstraintVariants(?string $periodColumn, ?string $kancaColumn, array $scope): array
    {
        $dimensionVariants = [];

        foreach ([
            'period' => ['column' => $periodColumn, 'filter' => $scope['period_filter'] ?? null, 'is_null' => (bool) ($scope['period_is_null'] ?? false)],
            'kanca' => ['column' => $kancaColumn, 'filter' => $scope['kanca_filter'] ?? null, 'is_null' => (bool) ($scope['kanca_is_null'] ?? false)],
        ] as $dimension => $meta) {
            $column = $meta['column'];
            if ($column === null) {
                continue;
            }

            $constraints = [];

            if ($meta['is_null']) {
                $constraints = [
                    ['column' => $column, 'mode' => 'null'],
                    ['column' => $column, 'mode' => 'empty'],
                    ['column' => $column, 'mode' => 'trim'],
                ];
            }

            $filter = $meta['filter'];
            if ($filter !== null && $filter !== '') {
                $constraints[] = [
                    'column' => $column,
                    'mode' => $dimension === 'period' && $this->shouldUseMonthPrefixDeleteConstraint($column, (string) $filter)
                        ? 'month_prefix'
                        : 'equal',
                    'value' => (string) $filter,
                ];
            }

            if (!empty($constraints)) {
                $dimensionVariants[$dimension] = $constraints;
            }
        }

        if (empty($dimensionVariants)) {
            return [];
        }

        $variants = [[]];
        foreach ($dimensionVariants as $constraints) {
            $next = [];
            foreach ($variants as $existing) {
                foreach ($constraints as $constraint) {
                    $next[] = [...$existing, $constraint];
                }
            }
            $variants = $next;
        }

        return $variants;
    }

    private function makeDeleteVariantQuery(string $tableName, array $constraints): Builder
    {
        $query = DB::table($tableName);
        $this->applyDeleteConstraints($query, $constraints);

        return $query;
    }

    private function applyDeleteConstraints(Builder $query, array $constraints): void
    {
        foreach ($constraints as $constraint) {
            $column = (string) ($constraint['column'] ?? '');
            $mode = (string) ($constraint['mode'] ?? '');

            if ($column === '' || $mode === '') {
                continue;
            }

            if ($mode === 'equal') {
                $query->where($column, (string) ($constraint['value'] ?? ''));
                continue;
            }

            if ($mode === 'month_prefix') {
                $safeColumn = str_replace('`', '``', $column);
                $query->whereRaw("SUBSTR(CAST(`{$safeColumn}` AS CHAR), 1, 7) = ?", [(string) ($constraint['value'] ?? '')]);
                continue;
            }

            if ($mode === 'null') {
                $query->whereNull($column);
                continue;
            }

            if ($mode === 'empty') {
                $query->where($column, '');
                continue;
            }

            if ($mode === 'trim') {
                $safeColumn = str_replace('`', '``', $column);
                $query
                    ->whereNotNull($column)
                    ->where($column, '<>', '')
                    ->whereRaw("TRIM(`{$safeColumn}`) = ''");
            }
        }
    }

    private function resolveDeleteIndexHint(
        string $tableName,
        ?string $periodColumn,
        ?string $kancaColumn,
        string $identityColumn,
        array $constraints
    ): ?string {
        $config = self::DELETE_INDEX_HINTS[$tableName] ?? null;
        if (!is_array($config)) {
            return null;
        }

        if (($config['period'] ?? null) !== $periodColumn || ($config['identity'] ?? null) !== $identityColumn) {
            return null;
        }

        $usesKancaConstraint = false;
        foreach ($constraints as $constraint) {
            if (in_array(($constraint['mode'] ?? ''), ['trim', 'month_prefix'], true)) {
                return null;
            }

            if (($constraint['column'] ?? null) === $kancaColumn && $kancaColumn !== null) {
                $usesKancaConstraint = true;
            }
        }

        if ($usesKancaConstraint && ($config['kanca'] ?? null) !== $kancaColumn) {
            return null;
        }

        $indexCandidates = [];
        if (isset($config['indexes']) && is_array($config['indexes'])) {
            foreach ($config['indexes'] as $candidate) {
                $candidate = trim((string) $candidate);
                if ($candidate !== '') {
                    $indexCandidates[] = $candidate;
                }
            }
        }

        $primaryIndexCandidate = trim((string) ($config['index'] ?? ''));
        if ($primaryIndexCandidate !== '') {
            array_unshift($indexCandidates, $primaryIndexCandidate);
        }

        foreach (array_values(array_unique($indexCandidates)) as $indexName) {
            if ($this->tableIndexExists($tableName, $indexName)) {
                return $indexName;
            }
        }

        return null;
    }

    private function tableIndexExists(string $tableName, string $indexName): bool
    {
        $driver = DB::connection()->getDriverName();
        if (!in_array($driver, ['mysql', 'mariadb'], true)) {
            return false;
        }

        $tableKey = strtolower(trim($tableName));
        if ($tableKey === '') {
            return false;
        }

        if (!isset($this->tableIndexLookupCache[$tableKey])) {
            try {
                $rows = DB::select('SHOW INDEX FROM `' . str_replace('`', '``', $tableName) . '`');
            } catch (Throwable) {
                $this->tableIndexLookupCache[$tableKey] = [];
                return false;
            }

            $lookup = [];
            foreach ($rows as $row) {
                $resolvedName = '';
                if (is_object($row) && isset($row->Key_name)) {
                    $resolvedName = (string) $row->Key_name;
                } elseif (is_array($row) && isset($row['Key_name'])) {
                    $resolvedName = (string) $row['Key_name'];
                }

                $resolvedName = strtolower(trim($resolvedName));
                if ($resolvedName !== '') {
                    $lookup[$resolvedName] = true;
                }
            }

            $this->tableIndexLookupCache[$tableKey] = $lookup;
        }

        return isset($this->tableIndexLookupCache[$tableKey][strtolower(trim($indexName))]);
    }

    private function buildDeleteWhereSql(array $constraints, ?string $tableAlias = null): array
    {
        $clauses = [];
        $bindings = [];
        $wrappedAlias = $tableAlias !== null && trim($tableAlias) !== ''
            ? '`' . str_replace('`', '``', trim($tableAlias)) . '`.'
            : '';

        foreach ($constraints as $constraint) {
            $column = (string) ($constraint['column'] ?? '');
            $mode = (string) ($constraint['mode'] ?? '');
            if ($column === '' || $mode === '') {
                continue;
            }

            $wrappedColumn = $wrappedAlias . '`' . str_replace('`', '``', $column) . '`';

            if ($mode === 'equal') {
                $clauses[] = "{$wrappedColumn} = ?";
                $bindings[] = (string) ($constraint['value'] ?? '');
                continue;
            }

            if ($mode === 'month_prefix') {
                $clauses[] = "{$wrappedColumn} LIKE ?";
                $bindings[] = (string) ($constraint['value'] ?? '') . '%';
                continue;
            }

            if ($mode === 'null') {
                $clauses[] = "{$wrappedColumn} IS NULL";
                continue;
            }

            if ($mode === 'empty') {
                $clauses[] = "{$wrappedColumn} = ''";
                continue;
            }

            if ($mode === 'trim') {
                $clauses[] = "{$wrappedColumn} IS NOT NULL AND {$wrappedColumn} <> '' AND TRIM({$wrappedColumn}) = ''";
            }
        }

        return [
            !empty($clauses) ? implode(' AND ', array_map(static fn (string $clause): string => '(' . $clause . ')', $clauses)) : '1 = 0',
            $bindings,
        ];
    }

    private function deleteRowsByIndexedSubqueryBatch(
        string $tableName,
        string $identityColumn,
        array $constraints,
        int $limit,
        string $indexHint,
        $connection = null,
        ?string $deleteId = null
    ): int {
        $connection = $connection ?: DB::connection();
        if ($deleteId !== null && $this->isManagedDeleteCancellationRequested($deleteId)) {
            return 0;
        }

        [$whereSql, $bindings] = $this->buildDeleteWhereSql($constraints);
        $wrappedTable = '`' . str_replace('`', '``', $tableName) . '`';
        $wrappedIdentity = '`' . str_replace('`', '``', $identityColumn) . '`';
        $wrappedIndex = '`' . str_replace('`', '``', $indexHint) . '`';
        $deleted = 0;

        // Hapus dulu baris dangling yang tidak punya identity agar scope tidak tertahan.
        $danglingDeleted = (int) $this->makeDeleteVariantQuery($tableName, $constraints)
            ->where(function ($query) use ($identityColumn) {
                $query->whereNull($identityColumn)
                    ->orWhere($identityColumn, '');
            })
            ->limit($limit)
            ->delete();

        if ($danglingDeleted > 0) {
            if ($danglingDeleted >= $limit) {
                return $danglingDeleted;
            }

            $deleted += $danglingDeleted;
        }

        if ($deleteId !== null && $this->isManagedDeleteCancellationRequested($deleteId)) {
            return $deleted;
        }

        $selectSql = "
SELECT {$wrappedIdentity}
FROM {$wrappedTable} FORCE INDEX ({$wrappedIndex})
WHERE {$whereSql}
  AND {$wrappedIdentity} IS NOT NULL
  AND {$wrappedIdentity} <> ''
ORDER BY {$wrappedIdentity}
LIMIT {$limit}
";

        $rows = $connection->select($selectSql, $bindings);
        $identityValues = collect($rows)
            ->map(static fn ($row) => is_object($row) ? ($row->{$identityColumn} ?? null) : ($row[$identityColumn] ?? null))
            ->filter(static fn ($value) => $value !== null && $value !== '')
            ->values()
            ->all();

        if (empty($identityValues)) {
            return $deleted;
        }

        foreach (array_chunk($identityValues, 2000) as $chunk) {
            if ($deleteId !== null && $this->isManagedDeleteCancellationRequested($deleteId)) {
                break;
            }

            $deleted += (int) $connection->table($tableName)
                ->whereIn($identityColumn, $chunk)
                ->delete();
        }

        return $deleted;
    }

    private function supportsDirectPredicateDelete(array $constraints): bool
    {
        if (empty($constraints)) {
            return false;
        }

        foreach ($constraints as $constraint) {
            if (($constraint['mode'] ?? '') !== 'equal') {
                return false;
            }
        }

        return true;
    }

    private function shouldUseDirectDeleteWithoutLimit(
        string $tableName,
        ?string $periodColumn,
        ?string $kancaColumn,
        array $constraints,
        int $limit
    ): bool {
        if (strtolower(trim($tableName)) !== 'simpanan_multipn') {
            return false;
        }

        if ($periodColumn === null || $limit < 1000000) {
            return false;
        }

        $hasPeriodEqualityConstraint = false;

        foreach ($constraints as $constraint) {
            if ((string) ($constraint['mode'] ?? '') !== 'equal') {
                return false;
            }

            $column = (string) ($constraint['column'] ?? '');
            if ($column === $periodColumn) {
                $hasPeriodEqualityConstraint = true;
                continue;
            }

            if ($kancaColumn !== null && $column === $kancaColumn) {
                return false;
            }
        }

        return $hasPeriodEqualityConstraint;
    }

    private function deleteRowsByDirectPredicateBatch(
        string $tableName,
        array $constraints,
        int $limit,
        $connection = null,
        ?string $deleteId = null,
        bool $withoutLimit = false
    ): int {
        $connection = $connection ?: DB::connection();
        if ($deleteId !== null && $this->isManagedDeleteCancellationRequested($deleteId)) {
            return 0;
        }

        [$whereSql, $bindings] = $this->buildDeleteWhereSql($constraints);
        $wrappedTable = '`' . str_replace('`', '``', $tableName) . '`';
        $limit = max(1, $limit);
        $sql = "
DELETE FROM {$wrappedTable}
WHERE {$whereSql}
";
        if (!$withoutLimit) {
            $sql .= "\nLIMIT {$limit}\n";
        }

        return $this->withManagedDeleteLockWait($connection, 300, fn (): int => (int) $connection->affectingStatement($sql, $bindings));
    }

    private function withManagedDeleteLockWait($connection, int $seconds, callable $callback): mixed
    {
        if (!in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
            return $callback();
        }

        $originalLockWait = null;
        try {
            $row = $connection->selectOne('SELECT @@SESSION.lock_wait_timeout AS lock_wait_timeout');
            $originalLockWait = isset($row->lock_wait_timeout) ? (int) $row->lock_wait_timeout : null;
        } catch (Throwable) {
            $originalLockWait = null;
        }

        try {
            $connection->statement('SET SESSION lock_wait_timeout = ' . max(1, $seconds));

            return $callback();
        } finally {
            if ($originalLockWait !== null) {
                try {
                    $connection->statement('SET SESSION lock_wait_timeout = ' . max(1, $originalLockWait));
                } catch (Throwable) {
                    // The connection can still be reused safely if restore fails.
                }
            }
        }
    }

    private function shouldUseMonthPrefixDeleteConstraint(string $column, string $value): bool
    {
        $normalizedValue = trim($value);
        if (preg_match('/^\d{4}-\d{2}$/', $normalizedValue) !== 1) {
            return false;
        }

        $normalizedColumn = strtolower(trim($column));

        return str_contains($normalizedColumn, 'periode')
            || str_contains($normalizedColumn, 'period')
            || str_contains($normalizedColumn, 'posisi')
            || str_contains($normalizedColumn, 'tanggal')
            || str_contains($normalizedColumn, 'tgl');
    }

    private function markDeleteBatchPending(array $state): array
    {
        $scopes = $this->extractDeleteScopesFromState($state);
        $currentScopeIndex = max(0, (int) ($state['current_scope_index'] ?? 0));
        $totalScopes = max(1, count($scopes));
        $scope = $scopes[$currentScopeIndex] ?? [];

        $state['batch_state'] = 'deleting_pending';
        $state['is_waiting_on_batch'] = true;
        $state['active_batch_size'] = $this->resolveEffectiveDeleteBatchSize($state);
        $state['last_batch_deleted_rows'] = max(0, (int) ($state['last_batch_deleted_rows'] ?? 0));
        $state['last_batch_started_at'] = now()->toIso8601String();
        $state['last_batch_finished_at'] = null;
        $state['message'] = sprintf(
            'Menyiapkan batch delete... Grup %d/%d (%s).',
            min($totalScopes, $currentScopeIndex + 1),
            $totalScopes,
            $this->describeDeleteScope($scope)
        );
        $state['updated_at'] = now()->toIso8601String();

        return $state;
    }

    private function buildManagedDeleteFailure(array $state, Throwable $e): array
    {
        $errorCode = $this->resolveManagedDeleteErrorCode($e);
        $deletedRows = max(0, (int) ($state['deleted_rows'] ?? 0));

        // MySQL/MariaDB error code 1205: Lock wait timeout exceeded
        if ($errorCode === '1205') {
            return [
                'error_code' => $errorCode,
                'message' => $deletedRows > 0
                    ? 'Delete berhenti karena lock timeout saat menunggu trigger atau snapshot. Sebagian data mungkin sudah terhapus.'
                    : 'Batch delete gagal karena lock timeout saat menunggu trigger atau snapshot.',
                'error' => 'Lock timeout saat delete batch. Coba ulang setelah proses lain selesai.',
            ];
        }

        // MySQL error 1213: Deadlock found when trying to get lock
        if ($errorCode === '1213') {
            return [
                'error_code' => $errorCode,
                'message' => $deletedRows > 0
                    ? 'Delete berhenti karena deadlock antar transaksi database. Sebagian data sudah terhapus.'
                    : 'Batch delete gagal karena deadlock. Coba ulangi proses delete.',
                'error' => 'Deadlock terdeteksi. Coba ulangi proses setelah beberapa detik.',
            ];
        }

        // MySQL error 1146: Table doesn't exist
        if ($errorCode === '1146') {
            return [
                'error_code' => $errorCode,
                'message' => 'Tabel sumber tidak ditemukan di database. Operasi delete dibatalkan.',
                'error' => 'Tabel tidak ada: ' . ($state['table_name'] ?? 'unknown') . '. Periksa konfigurasi report management.',
            ];
        }

        // MySQL error 1054: Unknown column in field list
        if ($errorCode === '1054') {
            return [
                'error_code' => $errorCode,
                'message' => 'Kolom yang diperlukan tidak ditemukan di tabel. Delete dibatalkan untuk menghindari data korup.',
                'error' => 'Kolom tidak dikenali di tabel ' . ($state['table_name'] ?? 'unknown') . '. Sumber data mungkin berubah skema.',
            ];
        }

        // MySQL error 2006: MySQL server has gone away
        if ($errorCode === '2006') {
            return [
                'error_code' => $errorCode,
                'message' => $deletedRows > 0
                    ? 'Koneksi database terputus saat proses delete berjalan. Sebagian data mungkin sudah terhapus.'
                    : 'Koneksi database terputus sebelum delete dimulai. Pastikan MySQL berjalan dan coba lagi.',
                'error' => 'MySQL server gone away (2006). Periksa max_allowed_packet dan koneksi database.',
            ];
        }

        // RuntimeException from safety guard (no identity column, scope invalid, etc.)
        if ($e instanceof \RuntimeException && str_contains($e->getMessage(), 'identity')) {
            return [
                'error_code' => $errorCode,
                'message' => 'Delete dibatalkan: tabel tidak memiliki kolom identity/unique yang aman untuk delete bertahap.',
                'error' => $e->getMessage(),
            ];
        }

        return [
            'error_code' => $errorCode,
            'message' => $deletedRows > 0
                ? 'Data sumber terhapus sebagian/seluruhnya, tetapi cleanup snapshot atau sinkronisasi lanjutan gagal.'
                : 'Delete gagal diproses.',
            'error' => $deletedRows > 0
                ? 'Cleanup snapshot atau sinkronisasi lanjutan gagal. Detail teknis sudah dicatat di log server.'
                : 'Delete tidak dapat diproses. Detail teknis sudah dicatat di log server.',
        ];
    }

    private function resolveManagedDeleteErrorCode(Throwable $e): ?string
    {
        if ($e instanceof QueryException) {
            $errorInfo = $e->errorInfo ?? [];
            if (isset($errorInfo[1])) {
                $normalizedCode = trim((string) $errorInfo[1]);
                return ($normalizedCode !== '' && $normalizedCode !== '0') ? $normalizedCode : null;
            }
        }

        if (preg_match('/\b1205\b/', $e->getMessage()) === 1) {
            return '1205';
        }

        $code = $e->getCode();
        if (!is_scalar($code)) {
            return null;
        }

        $normalizedCode = trim((string) $code);

        return ($normalizedCode !== '' && $normalizedCode !== '0') ? $normalizedCode : null;
    }

    private function shouldToggleSnapshotInvalidationFlag(?string $driverName): bool
    {
        return in_array(strtolower((string) $driverName), ['mysql', 'mariadb'], true);
    }

    private function canDeleteScopesWithoutIdentity(
        string $tableName,
        ?string $periodColumn,
        ?string $kancaColumn,
        array $scopes
    ): bool
    {
        foreach ($scopes as $scope) {
            if (!$this->scopeSupportsPartitionDeleteShortcut($tableName, $periodColumn, $kancaColumn, $scope)) {
                return false;
            }
        }

        return !empty($scopes);
    }

    private function scopeSupportsPartitionDeleteShortcut(
        string $tableName,
        ?string $periodColumn,
        ?string $kancaColumn,
        array $scope
    ): bool
    {
        if (!$this->partitionMaintenanceService->supportsPartitionDdl()) {
            return false;
        }

        if ($periodColumn === null) {
            return false;
        }

        $periodValue = (string) ($scope['period_filter'] ?? '');
        if ($periodValue === '' || (bool) ($scope['period_is_null'] ?? false)) {
            return false;
        }

        $hasKancaRestriction = ($kancaColumn !== null)
            && (
                ((string) ($scope['kanca_filter'] ?? '')) !== ''
                || (bool) ($scope['kanca_is_null'] ?? false)
            );

        if ($hasKancaRestriction) {
            return false;
        }

        return $this->partitionMaintenanceService->resolveSinglePartitionForValue(
            $tableName,
            $periodColumn,
            $periodValue
        ) !== null;
    }

    private function tryDeleteScopeByPartition(
        string $tableName,
        Builder $baseQuery,
        ?string $periodColumn,
        ?string $kancaColumn,
        array $scope
    ): ?int
    {
        if (!$this->partitionMaintenanceService->supportsPartitionDdl()) {
            return null;
        }

        if ($periodColumn === null) {
            return null;
        }

        $periodValue = (string) ($scope['period_filter'] ?? '');
        if ($periodValue === '' || (bool) ($scope['period_is_null'] ?? false)) {
            return null;
        }

        $hasKancaRestriction = ($kancaColumn !== null)
            && (
                ((string) ($scope['kanca_filter'] ?? '')) !== ''
                || (bool) ($scope['kanca_is_null'] ?? false)
            );

        if ($hasKancaRestriction) {
            return null;
        }

        $partitionName = $this->partitionMaintenanceService->resolveSinglePartitionForValue(
            $tableName,
            $periodColumn,
            $periodValue
        );

        if ($partitionName === null) {
            return null;
        }

        $affected = (int) (clone $baseQuery)->count();
        if ($affected <= 0) {
            return 0;
        }

        $this->partitionMaintenanceService->truncatePartition($tableName, $partitionName);

        return $affected;
    }

    private function describeDeleteScope(array $scope): string
    {
        $parts = [];

        if (($scope['fallback_mode'] ?? null) === self::LW325_BLANK_CREATED_AT_FALLBACK_MODE) {
            $fallbackLabel = trim((string) ($scope['fallback_period_label'] ?? ''));
            $parts[] = 'Periode kosong';
            $parts[] = 'Kanca kosong';
            if ($fallbackLabel !== '') {
                $parts[] = $fallbackLabel;
            }

            return implode(' | ', $parts);
        }

        if ((bool) ($scope['period_is_null'] ?? false)) {
            $parts[] = 'Periode kosong';
        } elseif (($scope['period_filter'] ?? null) !== null && $scope['period_filter'] !== '') {
            $parts[] = 'Periode ' . (string) $scope['period_filter'];
        }

        if ((bool) ($scope['kanca_is_null'] ?? false)) {
            $parts[] = 'Kanca kosong';
        } elseif (($scope['kanca_filter'] ?? null) !== null && $scope['kanca_filter'] !== '') {
            $parts[] = 'Kanca ' . (string) $scope['kanca_filter'];
        }

        return !empty($parts) ? implode(' | ', $parts) : 'scope aktif';
    }

    private function isLw325BlankCreatedAtFallbackScope(string $tableName, array $scope): bool
    {
        if (strtolower(trim($tableName)) !== 'lw325_ph') {
            return false;
        }

        $fallbackMode = trim((string) ($scope['fallback_mode'] ?? ''));
        $fallbackPeriodColumn = trim((string) ($scope['fallback_period_column'] ?? ''));
        $fallbackPeriodFilter = trim((string) ($scope['fallback_period_filter'] ?? ''));

        return $fallbackMode === self::LW325_BLANK_CREATED_AT_FALLBACK_MODE
            && $fallbackPeriodColumn === 'created_at'
            && $fallbackPeriodFilter !== ''
            && (bool) ($scope['period_is_null'] ?? false)
            && (bool) ($scope['kanca_is_null'] ?? false);
    }

    private function deleteLw325BlankCreatedAtScope(
        string $tableName,
        Builder $baseQuery,
        ?string $identityColumn,
        ?int $chunkSize,
        ?string $deleteId
    ): int {
        $this->bulkLoadService()->assertTransactionalTable($tableName, 'delete data report');

        return $this->bulkLoadService()->withTableWriteLock($tableName, function () use (
            $tableName,
            $baseQuery,
            $identityColumn,
            $chunkSize,
            $deleteId
        ): int {
            $limit = max(1, (int) ($chunkSize ?? self::DELETE_CHUNK_SIZE));
            $connection = $baseQuery->getConnection();
            $driverName = $connection->getDriverName();
            $shouldToggleSnapshotFlag = $this->shouldToggleSnapshotInvalidationFlag($driverName);

            if ($shouldToggleSnapshotFlag) {
                try {
                    $connection->statement('SET @skip_snapshot_invalidation = 1');
                } catch (Throwable) {
                    $shouldToggleSnapshotFlag = false;
                }
            }

            try {
                if ($deleteId !== null && $this->isManagedDeleteCancellationRequested($deleteId)) {
                    return 0;
                }

                if ($identityColumn !== null && Schema::hasColumn($tableName, $identityColumn)) {
                    return $this->deleteRowsByIdentityBatch(
                        $tableName,
                        clone $baseQuery,
                        $identityColumn,
                        $limit,
                        $connection,
                        $deleteId
                    );
                }

                return (int) (clone $baseQuery)->limit($limit)->delete();
            } finally {
                if ($shouldToggleSnapshotFlag) {
                    $connection->statement('SET @skip_snapshot_invalidation = NULL');
                }
            }
        });
    }

    private function parseIniSizeToBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return 0;
        }

        $unit = strtolower(substr($value, -1));
        $bytes = (int) $value;

        return match ($unit) {
            'g' => $bytes * 1024 * 1024 * 1024,
            'm' => $bytes * 1024 * 1024,
            'k' => $bytes * 1024,
            default => (int) $value,
        };
    }
}
