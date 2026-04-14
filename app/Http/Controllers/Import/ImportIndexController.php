<?php

namespace App\Http\Controllers\Import;

use App\Http\Controllers\Controller;
use App\Jobs\RunManagedReportDeleteJob;
use App\Services\Import\MySqlBulkLoadService;
use App\Support\ManagedReportManagementService;
use App\Support\PartitionMaintenanceService;
use App\Support\ManagedReportSnapshotRebuildCoordinator;
use App\Support\ReportDataSyncService;
use App\Support\StrictDateParser;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use App\Models\NamaReport;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class ImportIndexController extends Controller
{
    private const DELETE_AUDIT_TABLE = 'report_sync_audits';
    private const MANAGEMENT_MAX_GROUP_ROWS = 5000;
    private const MANAGEMENT_PERIODS_PER_PAGE = 8;
    private const DELETE_PRECHECK_LIMIT = 200000;
    private const DELETE_CHUNK_SIZE = 10000;
    private const DELETE_CHUNK_SIZE_WITH_IDENTITY = 50000;
    private const DELETE_PROGRESS_TTL_MINUTES = 60;
    private const DELETE_PROGRESS_CACHE_PREFIX = 'report_management_delete:';
    private const DELETE_PROCESS_LOCK_PREFIX = 'report_management_delete_lock:';
    private const DELETE_PROCESS_LOCK_SECONDS = 120;
    private const DELETE_PROCESS_GRACE_SECONDS = 0;
    private const DELETE_PROCESS_STALE_SECONDS = 0;
    private const DELETE_TICK_TIME_BUDGET_MS = 2500;
    private const DELETE_MAX_BATCHES_PER_TICK = 8;
    private const DELETE_HARD_GUARD_RATIO = 0.85;
    private const REBUILD_FALLBACK_LOCK_PREFIX = 'report_management_rebuild_lock:';
    private const REBUILD_FALLBACK_LOCK_SECONDS = 7200;
    private const REBUILD_FALLBACK_STALE_SECONDS = 15;
    private const FULL_TABLE_TRUNCATE_SHORTCUT_TABLES = [
        'simpanan_multipn',
    ];

    private const DELETE_INDEX_HINTS = [
        'daily_loan_dinamis' => [
            'index' => 'idx_dld_delete_scope',
            'period' => 'periode',
            'kanca' => 'cabang1',
            'identity' => 'uniqueid_namareport',
        ],
        'lw325_ph' => [
            'index' => 'idx_lw325ph_delete_scope',
            'period' => 'periode',
            'kanca' => 'kanca',
            'identity' => 'uniqueid_namareport',
        ],
        'performance_pis_per_produk' => [
            'index' => 'idx_pppp_delete_scope',
            'period' => 'posisi',
            'kanca' => 'kanca',
            'identity' => 'uniqueid_namareport',
        ],
        'simpanan_multipn' => [
            'index' => 'idx_smp_delete_scope',
            'period' => 'posisi',
            'kanca' => 'kantor_cabang',
            'identity' => 'uniqueid_SMPN',
            'chunk_size' => 10000,
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

    private function reportManagementService(): ManagedReportManagementService
    {
        return app(ManagedReportManagementService::class);
    }

    private function managedReportSnapshotRebuildCoordinator(): ManagedReportSnapshotRebuildCoordinator
    {
        return app(ManagedReportSnapshotRebuildCoordinator::class);
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

        return view('import.report-management', compact('reports'));
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

    public function deleteManagedReportRows(Request $request)
    {
        [$prepared, $errorResponse] = $this->prepareManagedDelete($request);
        if ($errorResponse !== null) {
            return $errorResponse;
        }

        $syncService = app(ReportDataSyncService::class);
        $maintenanceMode = $syncService->resolvePostDeleteMaintenanceMode((string) ($prepared['table_name'] ?? ''));

        if ($prepared['candidate_rows'] <= 0) {
            return response()->json([
                'status' => 'completed',
                'deleted_rows' => 0,
                'table_name' => $prepared['table_name'],
                'message' => 'Tidak ada baris yang cocok dengan filter.',
                'progress_percent' => 100,
                'stage' => 'completed',
            ]);
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
            return response()->json($this->executeManagedFullTableDeleteShortcut($prepared, $syncService, $maintenanceMode));
        }

        if ($maintenanceMode === 'lightweight') {
            return response()->json($this->executeManagedLightweightDelete($prepared, $syncService));
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
            'cleanup' => null,
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ];

        $this->putDeleteState($deleteId, $state);

        try {
            RunManagedReportDeleteJob::dispatch($deleteId);
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
                'message' => 'Hapus duplikat hanya tersedia untuk Simpanan MultiPN.',
            ], 422);
        }

        if ($tableName === '' || !Schema::hasTable($tableName)) {
            return response()->json([
                'status' => 'error',
                'message' => "Tabel `{$tableName}` tidak ditemukan.",
            ], 404);
        }

        $requiredColumns = array_merge($this->getSimpananDuplicateFingerprintColumns(), ['uniqueid_SMPN', 'created_at']);
        foreach ($requiredColumns as $column) {
            if (!Schema::hasColumn($tableName, $column)) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Kolom `{$column}` tidak tersedia untuk pembersihan duplikat.",
                ], 422);
            }
        }

        [$deleteSql, $periodSql] = $this->buildSimpananDuplicateCleanupQueries($tableName);
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
            Log::warning('Hapus duplikat Simpanan MultiPN gagal: ' . $e->getMessage(), [
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
                'status' => 'completed',
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
        try {
            if (empty($affectedPeriods)) {
                $syncService->dispatchSnapshotRefresh(
                    $tableName,
                    null,
                    static::class . '::deleteManagedReportDuplicates'
                );
            } else {
                foreach ($affectedPeriods as $period) {
                    $syncService->dispatchSnapshotRefresh(
                        $tableName,
                        $period,
                        static::class . '::deleteManagedReportDuplicates'
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
                ? ' Periode terdampak: ' . implode(', ', $affectedPeriods) . '.'
                : '')
            . ' Refresh snapshot dijadwalkan di background.';

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
            'status' => 'completed',
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
            'status' => 'completed',
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
        return strtolower(trim($tableName)) === 'simpanan_multipn';
    }

    /**
     * @return array<int, string>
     */
    private function getSimpananDuplicateFingerprintColumns(): array
    {
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

    private function buildSimpananDuplicateKeepSignatureExpression(string $alias): string
    {
        return "CONCAT(DATE_FORMAT(COALESCE({$alias}.`created_at`, '1000-01-01 00:00:00'), '%Y%m%d%H%i%s'), '|', COALESCE({$alias}.`uniqueid_SMPN`, ''))";
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
    private function buildSimpananDuplicateCleanupQueries(string $tableName): array
    {
        $columns = $this->getSimpananDuplicateFingerprintColumns();
        $groupColumns = implode(', ', array_map(
            static fn (string $column): string => "s.`{$column}`",
            $columns
        ));
        $joinConditions = $this->buildNullSafeColumnJoinConditions($columns, 't', 'd');
        $keepSignature = $this->buildSimpananDuplicateKeepSignatureExpression('t');
        $groupKeepSignature = $this->buildSimpananDuplicateKeepSignatureExpression('s');
        $duplicateGroupsSql = "SELECT {$groupColumns}, MIN({$groupKeepSignature}) AS keep_signature, COUNT(*) AS duplicate_count FROM `{$tableName}` s GROUP BY {$groupColumns} HAVING COUNT(*) > 1";
        $deleteWhereClause = "{$keepSignature} <> d.keep_signature";

        return [
            "DELETE t FROM `{$tableName}` t INNER JOIN ({$duplicateGroupsSql}) d ON {$joinConditions} WHERE {$deleteWhereClause}",
            "SELECT DISTINCT t.`posisi` AS period FROM `{$tableName}` t INNER JOIN ({$duplicateGroupsSql}) d ON {$joinConditions} WHERE {$deleteWhereClause}",
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

            $this->bulkLoadService()->withTableWriteLock($tableName, function () use ($tableName, $syncService, $maintenanceMode, $periodHint, $source, &$sourceDeleted): void {
                $this->truncateManagedDeleteTable($tableName);
                $sourceDeleted = true;

                if ($maintenanceMode === 'lightweight') {
                    $syncService->syncAfterDeleteLightweight($tableName, $periodHint, $source);
                    return;
                }

                $syncService->dispatchSnapshotRefresh($tableName, $periodHint, $source);
            });

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
                'status' => 'completed',
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
                'message' => $maintenanceMode === 'lightweight'
                    ? 'Seluruh tabel berhasil dikosongkan cepat. Statistik dan cache sudah disegarkan.'
                    : 'Seluruh tabel berhasil dikosongkan cepat. Snapshot turunan, cache, dan statistik optimizer sudah dibersihkan.',
                'error' => null,
                'error_code' => null,
                'cleanup' => [
                    'mode' => $maintenanceMode === 'lightweight' ? 'lightweight' : 'snapshot_cleanup',
                    'reason' => 'full_table_truncate_shortcut',
                ],
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
    }

    public function cancelManagedReportDelete(string $deleteId)
    {
        $state = $this->getDeleteState($deleteId);
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

        $state = $this->finalizeManagedDeleteCancelled($deleteId, $state);

        return response()->json($this->formatDeleteStateResponse($state, [
            'message' => 'Delete dibatalkan dengan aman.',
        ]));
    }

    public function managedReportDeleteStatus(string $deleteId)
    {
        $state = $this->getDeleteState($deleteId);
        if ($state === null) {
            return response()->json([
                'status' => 'error',
                'message' => 'Progress delete tidak ditemukan atau sudah kedaluwarsa.',
            ], 404);
        }

        return response()->json($this->formatDeleteStateResponse($state));
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

        if ($periodColumn === null && $kancaColumn === null) {
            return [null, response()->json([
                'status' => 'error',
                'message' => "Tabel `{$tableName}` tidak memiliki kolom periode/kanca yang bisa difilter, delete dibatalkan demi keamanan.",
            ], 422)];
        }

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
        if ($identityColumn === null && !$this->canDeleteScopesWithoutIdentity($tableName, $periodColumn, $kancaColumn, $scopes)) {
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
            'identity_column' => $identityColumn,
            'period_hint' => $periodHint,
            'skip_derived_sync' => $skipDerivedSync,
            'skip_snapshot_cleanup' => $skipSnapshotCleanup,
        ], null];
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

            return $state;
        }

        if ($stage === 'cleanup') {
            if ($this->isManagedDeleteCancellationRequested($deleteId, $state)) {
                return $this->finalizeManagedDeleteCancelled($deleteId, $state);
            }

            if ($maintenanceMode === 'lightweight') {
                $syncService->syncAfterDeleteLightweight(
                    (string) $state['table_name'],
                    $state['period_hint'] ?? null,
                    static::class . '::runManagedReportDelete'
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

            $state['cleanup'] = [
                'mode' => 'snapshot_cleanup',
                'reason' => 'delete_derived_artifacts_after_delete',
            ];

            $state['stage'] = 'syncing';
            $state['batch_state'] = 'cleanup';
            $state['is_waiting_on_batch'] = false;
            $state['active_batch_size'] = 0;
            $state['message'] = 'Delete sumber selesai, membersihkan snapshot dan cache...';
            $state['updated_at'] = now()->toIso8601String();
            $this->putDeleteState($deleteId, $state);

            return $state;
        }

        if ($stage === 'syncing') {
            if ($this->isManagedDeleteCancellationRequested($deleteId, $state)) {
                return $this->finalizeManagedDeleteCancelled($deleteId, $state);
            }

            if ($maintenanceMode === 'lightweight') {
                $syncService->syncAfterDeleteLightweight(
                    (string) $state['table_name'],
                    $state['period_hint'] ?? null,
                    static::class . '::runManagedReportDelete'
                );
            } else {
                $syncService->dispatchSnapshotRefresh(
                    (string) $state['table_name'],
                    $state['period_hint'] ?? null,
                    static::class . '::runManagedReportDelete'
                );
            }

            $state['status'] = 'completed';
            $state['stage'] = 'completed';
            $state['batch_state'] = 'completed';
            $state['is_waiting_on_batch'] = false;
            $state['active_batch_size'] = 0;
            $state['message'] = $maintenanceMode === 'lightweight'
                ? 'Delete selesai. Statistik sumber dan cache sudah disegarkan.'
                : 'Delete selesai. Refresh snapshot, cache, dan statistik optimizer dijadwalkan di background.';
            $state['updated_at'] = now()->toIso8601String();
            $this->putDeleteState($deleteId, $state);

            return $state;
        }

        if (in_array($stage, ['completed', 'failed', 'cancelled'], true)) {
            return $state;
        }

        throw new \RuntimeException('Stage delete tidak dikenali.');
    }

    private function markManagedDeleteFailed(string $deleteId, array $state, Throwable $e): array
    {
        Log::warning('Delete report bertahap gagal: ' . $e->getMessage(), [
            'delete_id' => $deleteId,
            'table_name' => $state['table_name'] ?? null,
            'stage' => $state['stage'] ?? null,
            'exception_class' => $e::class,
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
        $chunkSize = (int) ($state['chunk_size'] ?? self::DELETE_CHUNK_SIZE);
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
                $scope
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

            $affected = $this->deleteScopedRows(
                $tableName,
                $scopeQuery,
                $identityColumn,
                $chunkSize,
                $periodColumn,
                $kancaColumn,
                $scope,
                $deleteId
            );

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
                            'scope' => $scope,
                            'cancel_requested' => true,
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
                            'scope' => $scope,
                            'remaining_rows' => $state['remaining_rows'] ?? null,
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
        $effectiveStatus = $status === 'failed' && $deletedRows > 0 ? 'warning' : $status;
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
            'message' => $state['message'] ?? 'Memproses delete...',
            'error' => $state['error'] ?? null,
            'error_code' => $state['error_code'] ?? null,
            'cleanup' => $state['cleanup'] ?? null,
            'delete_strategy' => $state['last_delete_strategy'] ?? ($state['delete_strategy'] ?? null),
            'cancel_requested' => (bool) ($state['cancel_requested'] ?? false),
            'can_process_fallback' => $this->shouldAllowManagedDeleteFallback($state),
            'fallback_stale_seconds' => self::DELETE_PROCESS_STALE_SECONDS,
        ], $overrides);
    }

    private function getDeleteState(string $deleteId): ?array
    {
        $state = $this->deleteProgressStore()->get($this->deleteProgressCacheKey($deleteId));

        return is_array($state) ? $state : null;
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
    }

    private function deleteProgressCacheKey(string $deleteId): string
    {
        return self::DELETE_PROGRESS_CACHE_PREFIX . trim($deleteId);
    }

    private function shouldAllowManagedDeleteFallback(array $state): bool
    {
        if (in_array($state['status'] ?? '', ['completed', 'warning', 'failed', 'cancelled'], true)) {
            return false;
        }

        $stage = (string) ($state['stage'] ?? 'queued');
        $reference = $state['updated_at'] ?? $state['created_at'] ?? null;
        $ageSeconds = $this->diffNowInSeconds($reference);

        if ($stage === 'queued') {
            return $ageSeconds >= self::DELETE_PROCESS_GRACE_SECONDS;
        }

        return $ageSeconds >= self::DELETE_PROCESS_STALE_SECONDS;
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
        return self::DELETE_PROCESS_LOCK_PREFIX . trim($deleteId);
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
        $preferMonthLabel = $looksLikePeriodColumn;

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
            if ($preferMonthLabel && preg_match('/^\d{4}-\d{2}(-\d{2})?/', $strictNormalized) === 1) {
                return substr($strictNormalized, 0, 7);
            }

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
                    return Carbon::createFromFormat($format, $normalized)->format('Y-m');
                } catch (\Throwable) {
                }
            }

            try {
                return Carbon::parse($normalized)->format('Y-m');
            } catch (\Throwable) {
            }
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value) === 1) {
            return substr($value, 0, 7);
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
                $query->where($periodColumn, $periodFilter);
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

            $hasPeriodConstraint = $periodColumn !== null && ($periodIsNull || ($periodFilter !== null && $periodFilter !== ''));
            $hasKancaConstraint = $kancaColumn !== null && ($kancaIsNull || ($kancaFilter !== null && $kancaFilter !== ''));

            if (!$hasPeriodConstraint && !$hasKancaConstraint) {
                continue;
            }

            $validScopes[] = [
                'period_filter' => $periodFilter,
                'kanca_filter' => $kancaFilter,
                'period_is_null' => $periodIsNull,
                'kanca_is_null' => $kancaIsNull,
            ];
        }

        if (empty($validScopes)) {
            return [$query, false];
        }

        $query->where(function ($outerQuery) use ($validScopes, $periodColumn, $kancaColumn) {
            foreach ($validScopes as $scope) {
                $outerQuery->orWhere(function ($innerQuery) use ($scope, $periodColumn, $kancaColumn) {
                    $applied = false;

                    if ($periodColumn !== null) {
                        if ((bool) ($scope['period_is_null'] ?? false)) {
                            $this->applyBlankValueConstraint($innerQuery, $periodColumn);
                            $applied = true;
                        } elseif (($scope['period_filter'] ?? null) !== null && $scope['period_filter'] !== '') {
                            $innerQuery->where($periodColumn, (string) $scope['period_filter']);
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

                    if (!$applied) {
                        $innerQuery->whereRaw('1 = 0');
                    }
                });
            }
        });

        return [$query, true];
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

            $scopeKey = json_encode([
                $periodFilter,
                $kancaFilter,
                $periodIsNull,
                $kancaIsNull,
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
                    'kanca_filter' => array_key_exists('kanca_filter', $scope)
                        ? (($scope['kanca_filter'] ?? '') !== '' ? (string) $scope['kanca_filter'] : null)
                        : null,
                    'period_is_null' => (bool) ($scope['period_is_null'] ?? false),
                    'kanca_is_null' => (bool) ($scope['kanca_is_null'] ?? false),
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
            'kanca_filter' => array_key_exists('kanca_filter', $state)
                ? (($state['kanca_filter'] ?? '') !== '' ? (string) $state['kanca_filter'] : null)
                : null,
            'period_is_null' => (bool) ($state['period_is_null'] ?? false),
            'kanca_is_null' => (bool) ($state['kanca_is_null'] ?? false),
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

    private function resolveDeleteScopeStrategy(
        string $tableName,
        ?string $periodColumn,
        ?string $kancaColumn,
        ?string $identityColumn,
        array $scope
    ): string {
        if ($this->scopeSupportsPartitionDeleteShortcut($tableName, $periodColumn, $kancaColumn, $scope)) {
            return 'partition_truncate';
        }

        if ($identityColumn === null) {
            return 'unsupported';
        }

        foreach ($this->buildDeleteConstraintVariants($periodColumn, $kancaColumn, $scope) as $variant) {
            if ($this->resolveDeleteIndexHint($tableName, $periodColumn, $kancaColumn, $identityColumn, $variant) !== null) {
                return 'indexed_batch_delete';
            }
        }

        return 'identity_batch_delete';
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
        ?string $deleteId = null
    ): int
    {
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
                $connection->statement('SET @skip_snapshot_invalidation = 1');
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

                            $affected = $supportsFastDelete
                                ? $this->deleteRowsByIndexedSubqueryBatch($tableName, $identityColumn, $variant, $batchLimit, $indexHint, $connection, $deleteId)
                                : $this->deleteRowsByIdentityBatch(
                                    $tableName,
                                    $this->makeDeleteVariantQuery($tableName, $variant),
                                    $identityColumn,
                                    $batchLimit,
                                    $connection,
                                    $deleteId
                                );

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

        $deleted = 0;
        $deleteBatchSize = 2000;

        foreach (array_chunk($identityValues, $deleteBatchSize) as $chunk) {
            if ($deleteId !== null && $this->isManagedDeleteCancellationRequested($deleteId)) {
                break;
            }

            $deleted += (int) $connection->table($tableName)
                ->whereIn($identityColumn, $chunk)
                ->delete();
        }

        return $deleted;
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
                    'mode' => 'equal',
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
            if (($constraint['mode'] ?? '') === 'trim') {
                return null;
            }

            if (($constraint['column'] ?? null) === $kancaColumn && $kancaColumn !== null) {
                $usesKancaConstraint = true;
            }
        }

        if ($usesKancaConstraint && ($config['kanca'] ?? null) !== $kancaColumn) {
            return null;
        }

        $indexName = (string) ($config['index'] ?? '');
        if ($indexName === '' || !$this->tableIndexExists($tableName, $indexName)) {
            return null;
        }

        return $indexName;
    }

    private function tableIndexExists(string $tableName, string $indexName): bool
    {
        $driver = DB::connection()->getDriverName();
        if (!in_array($driver, ['mysql', 'mariadb'], true)) {
            return false;
        }

        $rows = DB::select(
            'SHOW INDEX FROM `' . str_replace('`', '``', $tableName) . '` WHERE Key_name = ?',
            [$indexName]
        );

        return !empty($rows);
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
        $sql = "
DELETE FROM {$wrappedTable}
WHERE {$whereSql}
ORDER BY {$wrappedIdentity}
LIMIT {$limit}
";

        return (int) $connection->affectingStatement($sql, $bindings);
    }

    private function markDeleteBatchPending(array $state): array
    {
        $scopes = $this->extractDeleteScopesFromState($state);
        $currentScopeIndex = max(0, (int) ($state['current_scope_index'] ?? 0));
        $totalScopes = max(1, count($scopes));
        $scope = $scopes[$currentScopeIndex] ?? [];

        $state['batch_state'] = 'deleting_pending';
        $state['is_waiting_on_batch'] = true;
        $state['active_batch_size'] = max(1, (int) ($state['chunk_size'] ?? self::DELETE_CHUNK_SIZE));
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

        if ($errorCode === '1205') {
            return [
                'error_code' => $errorCode,
                'message' => $deletedRows > 0
                    ? 'Delete berhenti karena lock timeout saat menunggu trigger atau snapshot. Sebagian data mungkin sudah terhapus.'
                    : 'Batch delete gagal karena lock timeout saat menunggu trigger atau snapshot.',
                'error' => 'Lock timeout saat delete batch. Coba ulang setelah proses lain selesai.',
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


