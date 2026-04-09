<?php

namespace App\Http\Controllers\Import;

use App\Http\Controllers\Controller;
use App\Support\ReportDataSyncService;
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
    private const DELETE_PRECHECK_LIMIT = 200000;
    private const DELETE_CHUNK_SIZE = 5000;
    private const DELETE_PROGRESS_TTL_MINUTES = 60;
    private const DELETE_PROGRESS_CACHE_PREFIX = 'report_management_delete:';

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
        $reports = NamaReport::where('active', 1)->get();
        $downloadTemplates = $this->downloadTemplateOptions();

        return view('import.index', compact('reports', 'downloadTemplates'));
    }

    public function reportManagement()
    {
        $reports = NamaReport::where('active', 1)->get();

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
        ]);
    }

    public function reportManagementData(Request $request)
    {
        $validated = $request->validate([
            'id_report' => 'required|integer',
            'max_rows' => 'nullable|integer|min:100|max:20000',
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
        [$rows, $truncated] = $this->buildManagementRows($tableName, $periodColumn, $kancaColumn, $maxRows);

        return response()->json([
            'status' => 'success',
            'table_name' => $tableName,
            'period_column' => $periodColumn,
            'kanca_column' => $kancaColumn,
            'max_rows' => $maxRows,
            'truncated' => $truncated,
            'rows' => $rows,
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

        $deleteId = (string) Str::uuid();
        $state = [
            'delete_id' => $deleteId,
            'status' => 'running',
            'stage' => 'deleting',
            'message' => 'Delete dimulai.',
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
            'identity_column' => $prepared['identity_column'],
            'total_rows' => (int) $prepared['candidate_rows'],
            'deleted_rows' => 0,
            'chunk_size' => self::DELETE_CHUNK_SIZE,
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
                $state = $this->processDeleteChunk($state);
            }

            if (($state['stage'] ?? null) === 'cleanup') {
                $cleanup = $syncService->cleanupDerivedArtifactsAfterDelete(
                    (string) $state['table_name'],
                    $state['period_hint'] ?? null,
                    static::class . '::processManagedReportDelete'
                );
                $state['cleanup'] = $cleanup;
                $state['stage'] = 'syncing';
                $state['message'] = 'Membersihkan snapshot, cache index, dan statistik turunan...';
                $this->putDeleteState($deleteId, $state);
            }

            if (($state['stage'] ?? null) === 'syncing') {
                $syncService->syncAfterDelete(
                    (string) $state['table_name'],
                    $state['period_hint'] ?? null,
                    static::class . '::processManagedReportDelete'
                );
                $state['status'] = 'completed';
                $state['stage'] = 'completed';
                $state['message'] = 'Delete selesai. Snapshot, cache, dan statistik optimizer sudah disegarkan.';
                $state['updated_at'] = now()->toIso8601String();
                $this->putDeleteState($deleteId, $state);
            }
        } catch (Throwable $e) {
            Log::warning('Delete report bertahap gagal: ' . $e->getMessage(), [
                'delete_id' => $deleteId,
                'table_name' => $state['table_name'] ?? null,
                'stage' => $state['stage'] ?? null,
            ]);

            $state['status'] = ($state['deleted_rows'] ?? 0) > 0 ? 'warning' : 'failed';
            $state['stage'] = 'failed';
            $state['message'] = ($state['deleted_rows'] ?? 0) > 0
                ? 'Data sumber terhapus sebagian/seluruhnya, tetapi cleanup snapshot atau sinkronisasi lanjutan gagal.'
                : 'Delete gagal diproses.';
            $state['error'] = $e->getMessage();
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
            'candidate_rows' => (int) (clone $baseQuery)->count(),
            'identity_column' => $this->resolveIdentityColumn($tableColumns),
            'period_hint' => $periodHint,
        ], null];
    }

    private function processDeleteChunk(array $state): array
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

        if (empty($scopes)) {
            $scopes[] = [
                'period_filter' => array_key_exists('period_filter', $state)
                    ? (($state['period_filter'] ?? '') !== '' ? (string) $state['period_filter'] : null)
                    : null,
                'kanca_filter' => array_key_exists('kanca_filter', $state)
                    ? (($state['kanca_filter'] ?? '') !== '' ? (string) $state['kanca_filter'] : null)
                    : null,
                'period_is_null' => (bool) ($state['period_is_null'] ?? false),
                'kanca_is_null' => (bool) ($state['kanca_is_null'] ?? false),
            ];
        }

        [$baseQuery, $hasWhereClause] = $this->buildDeleteScopeQueryFromScopes(
            (string) $state['table_name'],
            $state['period_column'] ?? null,
            $state['kanca_column'] ?? null,
            $scopes
        );

        if (!$hasWhereClause) {
            throw new \RuntimeException('Scope delete tidak lagi valid.');
        }

        $identityColumn = $state['identity_column'] ?? null;
        $chunkSize = (int) ($state['chunk_size'] ?? self::DELETE_CHUNK_SIZE);

        if ($identityColumn !== null && Schema::hasColumn((string) $state['table_name'], (string) $identityColumn)) {
            $ids = (clone $baseQuery)
                ->select($identityColumn)
                ->limit($chunkSize)
                ->pluck($identityColumn)
                ->filter(fn ($value) => $value !== null && trim((string) $value) !== '')
                ->values()
                ->all();

            if (!empty($ids)) {
                $affected = DB::table((string) $state['table_name'])
                    ->whereIn((string) $identityColumn, $ids)
                    ->delete();
                $state['deleted_rows'] = (int) ($state['deleted_rows'] ?? 0) + (int) $affected;
            }
        } else {
            $rows = (clone $baseQuery)->limit($chunkSize)->get();
            if ($rows->isNotEmpty()) {
                $uniqueKeys = array_keys(get_object_vars((object) $rows->first()));
                foreach ($rows as $row) {
                    $deleteQuery = DB::table((string) $state['table_name']);
                    foreach ($uniqueKeys as $column) {
                        $value = $row->{$column};
                        if ($value === null) {
                            $deleteQuery->whereNull($column);
                        } else {
                            $deleteQuery->where($column, $value);
                        }
                    }
                    $state['deleted_rows'] = (int) ($state['deleted_rows'] ?? 0) + (int) $deleteQuery->limit(1)->delete();
                }
            }
        }

        $remainingRows = (int) (clone $baseQuery)->count();
        $state['remaining_rows'] = $remainingRows;
        $state['updated_at'] = now()->toIso8601String();

        if ($remainingRows <= 0) {
            $state['stage'] = 'cleanup';
            $state['message'] = 'Delete sumber selesai. Membersihkan snapshot dan artefak turunan...';
        } else {
            $state['message'] = 'Menghapus data sumber secara bertahap...';
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

        $percent = match ($stage) {
            'deleting' => $totalRows > 0
                ? min(85, (int) floor(($deletedRows / max(1, $totalRows)) * 85))
                : 0,
            'cleanup' => 90,
            'syncing' => 96,
            'completed' => 100,
            'failed' => min(99, $totalRows > 0 ? (int) floor(($deletedRows / max(1, $totalRows)) * 85) : 0),
            default => 0,
        };

        if (in_array($status, ['completed', 'warning'], true)) {
            $percent = 100;
        }

        return array_merge([
            'status' => $status,
            'delete_id' => $state['delete_id'] ?? null,
            'stage' => $stage,
            'table_name' => $state['table_name'] ?? null,
            'total_rows' => $totalRows,
            'deleted_rows' => $deletedRows,
            'remaining_rows' => max(0, $totalRows - $deletedRows),
            'progress_percent' => $percent,
            'message' => $state['message'] ?? 'Memproses delete...',
            'error' => $state['error'] ?? null,
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
                    'period' => '-',
                    'kanca' => '-',
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
                'period' => $periodRaw === null || trim((string) $periodRaw) === '' ? '(Blank)' : (string) $periodRaw,
                'kanca' => $kancaRaw === null || trim((string) $kancaRaw) === '' ? '(Blank)' : (string) $kancaRaw,
                'row_count' => (int) ($item->row_count ?? 0),
                'period_is_null' => $periodRaw === null || trim((string) $periodRaw) === '',
                'kanca_is_null' => $kancaRaw === null || trim((string) $kancaRaw) === '',
            ];
        }

        return [$rows, $truncated];
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

    private function resolveIdentityColumn(array $tableColumns): ?string
    {
        return $this->resolveColumnName($tableColumns, ['uniqueid_namareport', 'uniqueid_SMPN', 'id']);
    }

    private function deleteScopedRows(string $tableName, Builder $baseQuery, ?string $identityColumn): int
    {
        if ($identityColumn === null || !Schema::hasColumn($tableName, $identityColumn)) {
            return (int) (clone $baseQuery)->delete();
        }

        $deletedRows = 0;

        while (true) {
            $ids = (clone $baseQuery)
                ->select($identityColumn)
                ->limit(self::DELETE_CHUNK_SIZE)
                ->pluck($identityColumn)
                ->filter(fn ($value) => $value !== null && trim((string) $value) !== '')
                ->values()
                ->all();

            if (empty($ids)) {
                break;
            }

            $affected = DB::table($tableName)->whereIn($identityColumn, $ids)->delete();
            $deletedRows += (int) $affected;

            if ($affected <= 0) {
                break;
            }
        }

        return $deletedRows;
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
