<?php

namespace App\Http\Controllers\Import;

use App\Http\Controllers\Controller;
use App\Support\ReportDataSyncService;
use Illuminate\Http\Request;
use App\Models\NamaReport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ImportIndexController extends Controller
{
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
        $periodColumn = $this->resolveColumnName($tableColumns, self::PERIOD_COLUMN_CANDIDATES);
        $kancaColumn = $this->resolveColumnName($tableColumns, self::KANCA_COLUMN_CANDIDATES);

        $rows = $this->buildManagementRows($tableName, $periodColumn, $kancaColumn);

        return response()->json([
            'status' => 'success',
            'table_name' => $tableName,
            'period_column' => $periodColumn,
            'kanca_column' => $kancaColumn,
            'rows' => $rows,
        ]);
    }

    public function deleteManagedReportRows(Request $request, ReportDataSyncService $syncService)
    {
        $validated = $request->validate([
            'id_report' => 'required|integer',
            'period' => 'nullable|string|max:100',
            'kanca' => 'nullable|string|max:255',
            'period_is_null' => 'nullable|boolean',
            'kanca_is_null' => 'nullable|boolean',
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
        if ($tableName === '' || !Schema::hasTable($tableName)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tabel report tidak valid.',
            ], 422);
        }

        $tableColumns = Schema::getColumnListing($tableName);
        $periodColumn = $this->resolveColumnName($tableColumns, self::PERIOD_COLUMN_CANDIDATES);
        $kancaColumn = $this->resolveColumnName($tableColumns, self::KANCA_COLUMN_CANDIDATES);

        $periodFilter = array_key_exists('period', $validated) ? (string) ($validated['period'] ?? '') : null;
        $kancaFilter = array_key_exists('kanca', $validated) ? (string) ($validated['kanca'] ?? '') : null;
        $periodIsNull = (bool) ($validated['period_is_null'] ?? false);
        $kancaIsNull = (bool) ($validated['kanca_is_null'] ?? false);

        if ($periodColumn === null && $kancaColumn === null) {
            return response()->json([
                'status' => 'error',
                'message' => "Tabel `{$tableName}` tidak memiliki kolom periode/kanca yang bisa difilter, delete dibatalkan demi keamanan.",
            ], 422);
        }

        $hasWhereClause = false;
        $deletedRows = DB::transaction(function () use (
            $tableName,
            $periodColumn,
            $kancaColumn,
            $periodFilter,
            $kancaFilter,
            $periodIsNull,
            $kancaIsNull,
            &$hasWhereClause
        ) {
            $query = DB::table($tableName);

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

            if (!$hasWhereClause) {
                return 0;
            }

            return $query->delete();
        });

        if (!$hasWhereClause) {
            return response()->json([
                'status' => 'error',
                'message' => 'Filter periode/kanca tidak valid. Tidak ada data yang dihapus.',
            ], 422);
        }

        $periodHint = null;
        if ($periodColumn !== null && !$periodIsNull && $periodFilter !== null && $periodFilter !== '') {
            $periodHint = $periodFilter;
        }

        try {
            $syncService->syncAfterDelete($tableName, $periodHint, static::class . '::deleteManagedReportRows');
        } catch (Throwable $e) {
            Log::warning('Delete report berhasil, namun sinkronisasi snapshot gagal: ' . $e->getMessage(), [
                'table_name' => $tableName,
                'period_hint' => $periodHint,
                'id_report' => (int) $validated['id_report'],
            ]);

            return response()->json([
                'status' => 'warning',
                'deleted_rows' => (int) $deletedRows,
                'table_name' => $tableName,
                'message' => 'Data sumber berhasil dihapus, tetapi sinkronisasi snapshot gagal. Jalankan sinkronisasi manual.',
            ]);
        }

        return response()->json([
            'status' => 'success',
            'deleted_rows' => (int) $deletedRows,
            'table_name' => $tableName,
        ]);
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

    private function buildManagementRows(string $tableName, ?string $periodColumn, ?string $kancaColumn): array
    {
        if ($periodColumn === null && $kancaColumn === null) {
            $count = (int) DB::table($tableName)->count();

            return [[
                'period' => '-',
                'kanca' => '-',
                'row_count' => $count,
                'period_is_null' => false,
                'kanca_is_null' => false,
            ]];
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

        $result = $query->selectRaw(implode(', ', $selects))->get();

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

        return $rows;
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
