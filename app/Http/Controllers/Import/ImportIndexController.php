<?php

namespace App\Http\Controllers\Import;

use App\Http\Controllers\Controller;
use App\Support\PartitionMaintenanceService;
use App\Support\ReportDataSyncService;
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
    private const MANAGEMENT_MAX_GROUP_ROWS = 5000;
    private const MANAGEMENT_PERIODS_PER_PAGE = 8;
    private const DELETE_PRECHECK_LIMIT = 200000;
    private const DELETE_CHUNK_SIZE = 5000;
    private const DELETE_CHUNK_SIZE_WITH_IDENTITY = 10000;
    private const DELETE_PROGRESS_TTL_MINUTES = 60;
    private const DELETE_PROGRESS_CACHE_PREFIX = 'report_management_delete:';
    private const DELETE_HARD_GUARD_RATIO = 0.85;

    public function __construct(
        private readonly PartitionMaintenanceService $partitionMaintenanceService
    ) {
    }

    private const PERIOD_COLUMN_CANDIDATES = [
        'periode',
        'posisi',
        'PERIODE',
        'POSISI',
        'loan_period',
        'casa_period',
    ];

    private const KANCA_COLUMN_CANDIDATES = [
        'kanca',
        'kantor_cabang',
        'cabang1',
        'cabang',
        'branch',
        'kode_cabang1',
        'kode_cabang',
        'unit_kerja',
        'perusahaan_anak',
        'instansi',
        'bod_boc',
        'nama_nasabah',
        'rekanan_level_1',
    ];

    /**
     * Fallback kolom filter untuk tabel yang tidak memiliki pasangan periode/kanca standar.
     */
    private const MANAGEMENT_SCOPE_COLUMN_OVERRIDES = [
        'input_rekanan' => [
            'period' => ['created_at', 'updated_at'],
            'kanca' => ['perusahaan_anak', 'rekanan_level_1', 'status_nasabah'],
        ],
        'bod_boc' => [
            'period' => ['created_at', 'updated_at'],
            'kanca' => ['instansi', 'bod_boc', 'nama_nasabah'],
        ],
        'user_brimo_fin' => [
            'kanca_priority' => ['mbdesc'],
        ],
        'user_brimo_rpt_v2' => [
            'kanca_priority' => ['mbdesc'],
        ],
    ];

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
        if ($tableName === '') {
            return response()->json([
                'status' => 'error',
                'message' => 'Table report belum dikonfigurasi.',
            ], 422);
        }

        if (!Schema::hasTable($tableName)) {
            return response()->json([
                'status' => 'error',
                'message' => "Tabel `{$tableName}` tidak ditemukan.",
            ], 404);
        }

        $tableColumns = Schema::getColumnListing($tableName);
        [$periodColumn, $kancaColumn] = $this->resolveManagementScopeColumns($tableName, $tableColumns);

        $maxRows = (int) ($validated['max_rows'] ?? self::MANAGEMENT_MAX_GROUP_ROWS);
        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? self::MANAGEMENT_PERIODS_PER_PAGE);
        [$rows, $truncated] = $this->buildManagementRows($tableName, $periodColumn, $kancaColumn, $maxRows);
        $paginatedPeriods = $this->paginateManagementPeriods($rows, $page, $perPage, $periodColumn !== null);
        $displayedRowsTotal = array_reduce($paginatedPeriods['periods'], static function (int $carry, array $period): int {
            return $carry + (int) ($period['total_rows'] ?? 0);
        }, 0);
        $grandTotalRows = (int) DB::table($tableName)->count();

        return response()->json([
            'status' => 'success',
            'table_name' => $tableName,
            'period_column' => $periodColumn,
            'kanca_column' => $kancaColumn,
            'max_rows' => $maxRows,
            'truncated' => $truncated,
            'displayed_rows_total' => $displayedRowsTotal,
            'grand_total_rows' => $grandTotalRows,
            'total_groups' => count($rows),
            'rows' => $paginatedPeriods['rows'],
            'periods' => $paginatedPeriods['periods'],
            'pagination' => $paginatedPeriods['pagination'],
        ]);
    }

    public function deleteManagedReportRows(Request $request)
    {
        [$prepared, $errorResponse] = $this->prepareManagedDelete($request);
        if ($errorResponse !== null) {
            return $errorResponse;
        }

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

        if (($isHighImpactDelete || $isPotentialFullDelete) && !$prepared['hard_force']) {
            $ratioPercent = (int) round($deleteRatio * 100);
            return response()->json([
                'status' => 'warning',
                'requires_hard_force' => true,
                'table_name' => $prepared['table_name'],
                'candidate_rows' => $candidateRows,
                'table_total_rows' => $tableTotalRows,
                'delete_ratio_percent' => $ratioPercent,
                'message' => $isPotentialFullDelete
                    ? 'Guard keamanan aktif: scope delete menyentuh seluruh tabel. Kirim `hard_force=true` untuk konfirmasi final.'
                    : 'Guard keamanan aktif: scope delete berdampak sangat besar pada tabel. Kirim `hard_force=true` untuk konfirmasi final.',
            ], 409);
        }

        $deleteId = (string) Str::uuid();
        $state = [
            'delete_id' => $deleteId,
            'status' => 'running',
            'stage' => 'deleting',
            'batch_state' => 'idle',
            'message' => 'Delete dimulai. Menyiapkan grup pertama...',
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
            'identity_column' => $prepared['identity_column'],
            'total_rows' => (int) $prepared['candidate_rows'],
            'deleted_rows' => 0,
            'remaining_rows' => (int) $prepared['candidate_rows'],
            'chunk_size' => $this->resolveDeleteChunkSize($prepared['identity_column']),
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

        return response()->json($this->formatDeleteStateResponse($state, [
            'message' => 'Delete dimulai. Progress akan diperbarui otomatis.',
        ]));
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

        if (in_array($state['status'] ?? '', ['completed', 'warning', 'failed'], true)) {
            return response()->json($this->formatDeleteStateResponse($state));
        }

        try {
            $stage = (string) ($state['stage'] ?? 'deleting');

            if ($stage === 'deleting') {
                $state = $this->markDeleteBatchPending($state);
                $this->putDeleteState($deleteId, $state);

                $state = $this->processDeleteChunk($state);

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
            }

            if (($state['stage'] ?? null) === 'cleanup') {
                if (!empty($state['skip_derived_sync'])) {
                    $state['cleanup'] = [
                        'mode' => 'skipped',
                        'reason' => 'period_null_only_delete',
                    ];
                } else {
                    $cleanup = $syncService->cleanupDerivedArtifactsAfterDelete(
                        (string) $state['table_name'],
                        $state['period_hint'] ?? null,
                        static::class . '::processManagedReportDelete'
                    );
                    $state['cleanup'] = $cleanup;
                }
                $state['stage'] = 'syncing';
                $state['batch_state'] = 'cleanup';
                $state['is_waiting_on_batch'] = false;
                $state['active_batch_size'] = 0;
                $state['message'] = !empty($state['skip_derived_sync'])
                    ? 'Delete sumber selesai. Null-only delete terdeteksi, melewati rebuild snapshot penuh...'
                    : 'Delete sumber selesai, membersihkan snapshot dan artefak turunan...';
                $this->putDeleteState($deleteId, $state);
            }

            if (($state['stage'] ?? null) === 'syncing') {
                if (!empty($state['skip_derived_sync'])) {
                    $syncService->syncAfterDeleteLightweight(
                        (string) $state['table_name'],
                        $state['period_hint'] ?? null,
                        static::class . '::processManagedReportDelete'
                    );
                } else {
                    $syncService->syncAfterDelete(
                        (string) $state['table_name'],
                        $state['period_hint'] ?? null,
                        static::class . '::processManagedReportDelete'
                    );
                }
                $state['status'] = 'completed';
                $state['stage'] = 'completed';
                $state['batch_state'] = 'completed';
                $state['is_waiting_on_batch'] = false;
                $state['active_batch_size'] = 0;
                $state['message'] = !empty($state['skip_derived_sync'])
                    ? 'Delete selesai. Statistik tabel sumber dan cache disegarkan tanpa rebuild snapshot penuh.'
                    : 'Delete selesai. Snapshot, cache, dan statistik optimizer sudah disegarkan.';
                $state['updated_at'] = now()->toIso8601String();
                $this->putDeleteState($deleteId, $state);
            }
        } catch (Throwable $e) {
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
        }

        return response()->json($this->formatDeleteStateResponse($state));
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
            'scopes.*.kanca' => 'nullable|string|max:255',
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

        $tableColumns = Schema::getColumnListing($tableName);
        [$periodColumn, $kancaColumn] = $this->resolveManagementScopeColumns($tableName, $tableColumns);

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

        $periodHint = null;
        $skipDerivedSync = false;
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

            if (!$hasNullPeriodScope && count($periodCandidates) === 1) {
                $periodHint = (string) array_key_first($periodCandidates);
            }

            if ($hasNullPeriodScope && count($periodCandidates) === 0) {
                $skipDerivedSync = true;
            }
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
            'candidate_rows' => (int) (clone $baseQuery)->count(),
            'table_total_rows' => (int) DB::table($tableName)->count(),
            'identity_column' => $this->resolveIdentityColumn($tableColumns),
            'period_hint' => $periodHint,
            'skip_derived_sync' => $skipDerivedSync,
        ], null];
    }

    private function processDeleteChunk(array $state): array
    {
        $scopes = $this->extractDeleteScopesFromState($state);
        if (empty($scopes)) {
            throw new \RuntimeException('Scope delete tidak lagi valid.');
        }

        $tableName = (string) $state['table_name'];
        $periodColumn = $state['period_column'] ?? null;
        $kancaColumn = $state['kanca_column'] ?? null;
        $identityColumn = $state['identity_column'] ?? null;
        $chunkSize = (int) ($state['chunk_size'] ?? self::DELETE_CHUNK_SIZE);
        $currentScopeIndex = max(0, (int) ($state['current_scope_index'] ?? 0));
        $totalScopes = count($scopes);

        while ($currentScopeIndex < $totalScopes) {
            $scope = $scopes[$currentScopeIndex];

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
                $scope
            );
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
        $stage = (string) ($state['stage'] ?? 'deleting');
        $status = (string) ($state['status'] ?? 'running');
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
            default => min(100, $actualPercent),
        };

        if (in_array($status, ['completed', 'warning'], true)) {
            $percent = 100;
        }

        return array_merge([
            'status' => $status,
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
            'progress_percent' => $percent,
            'message' => $state['message'] ?? 'Memproses delete...',
            'error' => $state['error'] ?? null,
            'error_code' => $state['error_code'] ?? null,
            'cleanup' => $state['cleanup'] ?? null,
        ], $overrides);
    }

    private function getDeleteState(string $deleteId): ?array
    {
        $state = Cache::get($this->deleteProgressCacheKey($deleteId));

        return is_array($state) ? $state : null;
    }

    private function putDeleteState(string $deleteId, array $state): void
    {
        Cache::put(
            $this->deleteProgressCacheKey($deleteId),
            $state,
            now()->addMinutes(self::DELETE_PROGRESS_TTL_MINUTES)
        );
    }

    private function deleteProgressCacheKey(string $deleteId): string
    {
        return self::DELETE_PROGRESS_CACHE_PREFIX . trim($deleteId);
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

    private function resolveManagementScopeColumns(string $tableName, array $tableColumns): array
    {
        $periodColumn = $this->resolveColumnName($tableColumns, self::PERIOD_COLUMN_CANDIDATES);
        $kancaColumn = $this->resolveColumnName($tableColumns, self::KANCA_COLUMN_CANDIDATES);

        $override = self::MANAGEMENT_SCOPE_COLUMN_OVERRIDES[$tableName] ?? null;
        if (!is_array($override)) {
            return [$periodColumn, $kancaColumn];
        }

        // Some reports (e.g. BRIMO) have explicit branch-name source and should
        // not be grouped by code-like fallback columns.
        $priorityKancaColumn = $this->resolveColumnName($tableColumns, (array) ($override['kanca_priority'] ?? []));
        if ($priorityKancaColumn !== null) {
            $kancaColumn = $priorityKancaColumn;
        }

        if ($periodColumn === null) {
            $periodColumn = $this->resolveColumnName($tableColumns, (array) ($override['period'] ?? []));
        }

        if ($kancaColumn === null) {
            $kancaColumn = $this->resolveColumnName($tableColumns, (array) ($override['kanca'] ?? []));
        }

        return [$periodColumn, $kancaColumn];
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

            $rows[] = [
                'period' => $periodRaw === null || trim((string) $periodRaw) === ''
                    ? ($periodColumn !== null ? '(Blank)' : '(Tanpa Periode)')
                    : (string) $periodRaw,
                'kanca' => $kancaRaw === null || trim((string) $kancaRaw) === ''
                    ? ($kancaColumn !== null ? '(Blank)' : '(Semua)')
                    : (string) $kancaRaw,
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

            $periodLabel = (string) ($row['period'] ?? ($hasPeriodColumn ? '(Blank)' : '(Tanpa Periode)'));
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

            $periodFilter = array_key_exists('period', $scope)
                ? (($scope['period'] ?? '') !== '' ? (string) $scope['period'] : null)
                : null;
            $kancaFilter = array_key_exists('kanca', $scope)
                ? (($scope['kanca'] ?? '') !== '' ? (string) $scope['kanca'] : null)
                : null;
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
                'kanca_filter' => $kancaFilter,
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

    private function resolveDeleteChunkSize(?string $identityColumn): int
    {
        return $identityColumn !== null
            ? self::DELETE_CHUNK_SIZE_WITH_IDENTITY
            : self::DELETE_CHUNK_SIZE;
    }

    private function deleteScopedRows(
        string $tableName,
        Builder $baseQuery,
        ?string $identityColumn,
        ?int $chunkSize = null,
        ?string $periodColumn = null,
        ?string $kancaColumn = null,
        array $scope = []
    ): int
    {
        $limit = max(1, (int) ($chunkSize ?? self::DELETE_CHUNK_SIZE));
        $connection = DB::connection();
        $shouldToggleSnapshotFlag = $this->shouldToggleSnapshotInvalidationFlag($connection->getDriverName());

        if ($shouldToggleSnapshotFlag) {
            $connection->statement('SET @skip_snapshot_invalidation = 1');
        }

        try {
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

            if ($identityColumn !== null && Schema::hasColumn($tableName, $identityColumn)) {
                return $this->deleteRowsByIdentityBatch($tableName, $baseQuery, $identityColumn, $limit, $connection);
            }

            $rows = (clone $baseQuery)->limit($limit)->get();
            if ($rows->isEmpty()) {
                return 0;
            }

            return $this->deleteRowsBySnapshot($tableName, $rows, $connection);
        } finally {
            if ($shouldToggleSnapshotFlag) {
                $connection->statement('SET @skip_snapshot_invalidation = NULL');
            }
        }
    }

    private function deleteRowsByIdentityBatch(string $tableName, Builder $baseQuery, string $identityColumn, int $limit, $connection = null): int
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
            $deleted += (int) $connection->table($tableName)
                ->whereIn($identityColumn, $chunk)
                ->delete();
        }

        return $deleted;
    }

    private function deleteRowsBySnapshot(string $tableName, iterable $rows, $connection = null): int
    {
        $connection = $connection ?: DB::connection();
        $rowsCollection = collect($rows)->values();
        if ($rowsCollection->isEmpty()) {
            return 0;
        }

        $deletedRows = 0;
        $uniqueKeys = array_keys(get_object_vars((object) $rowsCollection->first()));

        foreach ($rowsCollection as $row) {
            $deleteQuery = $connection->table($tableName);

            foreach ($uniqueKeys as $column) {
                $value = $row->{$column} ?? null;
                if ($value === null) {
                    $deleteQuery->whereNull($column);
                } else {
                    $deleteQuery->where($column, $value);
                }
            }

            $deletedRows += (int) $deleteQuery->limit(1)->delete();
        }

        return $deletedRows;
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
                return (string) $errorInfo[1];
            }
        }

        if (preg_match('/\b1205\b/', $e->getMessage()) === 1) {
            return '1205';
        }

        $code = $e->getCode();

        return is_scalar($code) && $code !== '' ? (string) $code : null;
    }

    private function shouldToggleSnapshotInvalidationFlag(?string $driverName): bool
    {
        return in_array(strtolower((string) $driverName), ['mysql', 'mariadb'], true);
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
