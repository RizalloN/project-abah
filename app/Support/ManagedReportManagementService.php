<?php

namespace App\Support;

use Carbon\Carbon;
use App\Models\NamaReport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ManagedReportManagementService
{
    private const MANAGEMENT_MAX_GROUP_ROWS = 5000;
    private const MANAGEMENT_PERIODS_PER_PAGE = 8;

    private const PERIOD_COLUMN_CANDIDATES = [
        'periode',
        'posisi',
        'tanggal',
        'tgl',
        'PERIODE',
        'POSISI',
        'period',
        'snapshot_period',
        'tanggal_periode',
        'tgl_periode',
        'loan_period',
        'casa_period',
    ];

    private const KANCA_COLUMN_CANDIDATES = [
        'kanca',
        'nama_kanca',
        'kantor_cabang',
        'nama_kantor_cabang',
        'cabang1',
        'nama_cabang1',
        'cabang',
        'nama_cabang',
        'branch',
        'nama_branch',
        'brdesc',
        'mbdesc',
        'nama_kci',
        'nama_kcp',
        'kode_cabang1',
        'kode_cabang',
        'kode_kanca',
        'kode_branch',
        'kode_kci',
        'kci',
        'kcp',
        'perusahaan_anak',
        'instansi',
        'bod_boc',
        'nama_nasabah',
        'rekanan_level_1',
    ];

    private const MANAGEMENT_SCOPE_COLUMN_OVERRIDES = [
        'input_rekanan' => [
            'period' => ['created_at', 'updated_at'],
            'kanca' => ['perusahaan_anak', 'rekanan_level_1', 'status_nasabah'],
        ],
        'bod_boc' => [
            'period' => ['created_at', 'updated_at'],
            'kanca' => ['instansi', 'bod_boc', 'nama_nasabah'],
        ],
        'jumlah_merchant_detail' => [
            'period_priority' => ['posisi', 'periode'],
        ],
        'merchant_qris' => [
            'period_priority' => ['posisi', 'periode'],
            'kanca_priority' => ['NAMA_KCI', 'nama_kci'],
        ],
        'merchant_qris_volume' => [
            'period_priority' => ['posisi', 'periode'],
            'kanca_priority' => ['NAMA_KCI', 'nama_kci'],
        ],
        'sv_merchant' => [
            'period_priority' => ['posisi', 'periode'],
            'kanca_priority' => ['NAMA_KCI', 'nama_kci'],
        ],
        'user_brimo_fin' => [
            'period_priority' => ['posisi', 'periode'],
            'kanca_priority' => ['mbdesc', 'branch', 'brdesc'],
        ],
        'brimo_fin' => [
            'period_priority' => ['posisi', 'periode'],
            'kanca_priority' => ['mbdesc', 'branch', 'brdesc'],
        ],
        'user_brimo_rpt_v2' => [
            'period_priority' => ['posisi', 'periode'],
            'kanca_priority' => ['mbdesc', 'branch', 'brdesc'],
        ],
        'brimo_rpt_v2' => [
            'period_priority' => ['posisi', 'periode'],
            'kanca_priority' => ['mbdesc', 'branch', 'brdesc'],
        ],
        'casa_brilink_web' => [
            'period_priority' => ['periode'],
            'kanca_priority' => ['mbdesc'],
        ],
        'casa_brilink_edc' => [
            'period_priority' => ['periode'],
            'kanca_priority' => ['mbdesc'],
        ],
        'gi405_rec_dh' => [
            'period_priority' => ['tanggal'],
            'kanca_priority' => ['kc_konsol'],
        ],
        'cognos_ph' => [
            'period_priority' => ['periode'],
            'kanca_priority' => ['kanca'],
            'kanca_label_fallback_priority' => ['unit_kerja'],
        ],
    ];

    public function resolveReportManagementData(
        int $reportId,
        array $options,
        bool $duplicateCleanupAvailable = false,
        ?callable $progressCallback = null
    ): array
    {
        $this->emitProgress($progressCallback, [
            'stage' => 'validating',
            'message' => 'Memvalidasi report dan tabel sumber...',
            'completed_units' => 0,
            'total_units' => 4,
            'progress_percent' => 5,
        ]);

        $report = NamaReport::where('active', 1)
            ->where('id_report', $reportId)
            ->first();

        if (!$report) {
            return [
                'ok' => false,
                'status_code' => 404,
                'payload' => [
                    'status' => 'error',
                    'message' => 'Report tidak ditemukan.',
                ],
            ];
        }

        $tableName = trim((string) ($report->table_name ?? ''));
        if ($tableName === '') {
            return [
                'ok' => false,
                'status_code' => 422,
                'payload' => [
                    'status' => 'error',
                    'message' => 'Table report belum dikonfigurasi.',
                ],
            ];
        }

        if (!Schema::hasTable($tableName)) {
            return [
                'ok' => false,
                'status_code' => 404,
                'payload' => [
                    'status' => 'error',
                    'message' => "Tabel `{$tableName}` tidak ditemukan.",
                ],
            ];
        }

        $this->emitProgress($progressCallback, [
            'stage' => 'scanning_columns',
            'message' => 'Mendeteksi kolom periode dan kanca yang paling relevan...',
            'completed_units' => 1,
            'total_units' => 4,
            'progress_percent' => 25,
        ]);

        $tableColumns = Schema::getColumnListing($tableName);
        [$periodColumn, $kancaColumn] = $this->resolveManagementScopeColumns($tableName, $tableColumns);

        $maxRows = (int) ($options['max_rows'] ?? self::MANAGEMENT_MAX_GROUP_ROWS);
        $page = (int) ($options['page'] ?? 1);
        $perPage = (int) ($options['per_page'] ?? self::MANAGEMENT_PERIODS_PER_PAGE);

        $this->emitProgress($progressCallback, [
            'stage' => 'grouping',
            'message' => 'Menjalankan query grouping data report. Tahap ini paling berat untuk report besar...',
            'completed_units' => 2,
            'total_units' => 4,
            'progress_percent' => 55,
        ]);

        [$rows, $truncated] = $this->buildManagementRows($tableName, $periodColumn, $kancaColumn, $maxRows);

        $this->emitProgress($progressCallback, [
            'stage' => 'counting',
            'message' => 'Menghitung total baris sumber dan menyiapkan pagination periode...',
            'completed_units' => 3,
            'total_units' => 4,
            'progress_percent' => 82,
        ]);

        $paginatedPeriods = $this->paginateManagementPeriods($rows, $page, $perPage, $periodColumn !== null);
        $displayedRowsTotal = array_reduce($paginatedPeriods['periods'], static function (int $carry, array $period): int {
            return $carry + (int) ($period['total_rows'] ?? 0);
        }, 0);
        $grandTotalRows = (int) DB::table($tableName)->count();

        $this->emitProgress($progressCallback, [
            'stage' => 'finalizing',
            'message' => 'Merapikan hasil akhir agar siap dirender ke tabel management...',
            'completed_units' => 4,
            'total_units' => 4,
            'progress_percent' => 95,
        ]);

        return [
            'ok' => true,
            'status_code' => 200,
            'payload' => [
                'status' => 'success',
                'table_name' => $tableName,
                'period_column' => $periodColumn,
                'kanca_column' => $kancaColumn,
                'duplicate_cleanup_available' => $duplicateCleanupAvailable,
                'max_rows' => $maxRows,
                'truncated' => $truncated,
                'displayed_rows_total' => $displayedRowsTotal,
                'grand_total_rows' => $grandTotalRows,
                'total_groups' => count($rows),
                'rows' => $paginatedPeriods['rows'],
                'periods' => $paginatedPeriods['periods'],
                'pagination' => $paginatedPeriods['pagination'],
            ],
        ];
    }

    private function emitProgress(?callable $progressCallback, array $payload): void
    {
        if ($progressCallback !== null) {
            $progressCallback($payload);
        }
    }

    public function resolveManagementScopeColumns(string $tableName, array $tableColumns): array
    {
        $periodCandidates = $this->resolveCandidateColumns($tableColumns, self::PERIOD_COLUMN_CANDIDATES);
        $periodColumn = $this->resolveMostPopulatedColumn($tableName, $periodCandidates);
        if ($periodColumn === null) {
            $semanticPeriodColumn = $this->resolveSemanticPeriodColumn($tableColumns);
            $periodColumn = $semanticPeriodColumn !== null
                ? $this->resolveMostPopulatedColumn($tableName, [$semanticPeriodColumn])
                : null;
        }

        $kancaCandidates = $this->resolveCandidateColumns($tableColumns, self::KANCA_COLUMN_CANDIDATES);
        $kancaColumn = $this->resolveMostPopulatedColumn($tableName, $kancaCandidates);
        if ($kancaColumn === null) {
            $semanticKancaColumn = $this->resolveSemanticKancaColumn($tableColumns);
            $kancaColumn = $semanticKancaColumn !== null
                ? $this->resolveMostPopulatedColumn($tableName, [$semanticKancaColumn])
                : null;
        }

        $override = self::MANAGEMENT_SCOPE_COLUMN_OVERRIDES[$tableName] ?? null;
        if (!is_array($override)) {
            return [$periodColumn, $kancaColumn];
        }

        $priorityPeriodColumn = $this->resolveMostPopulatedColumn(
            $tableName,
            $this->resolveCandidateColumns($tableColumns, (array) ($override['period_priority'] ?? []))
        );
        if ($priorityPeriodColumn !== null) {
            $periodColumn = $priorityPeriodColumn;
        }

        $priorityKancaColumn = $this->resolveMostPopulatedColumn(
            $tableName,
            $this->resolveCandidateColumns($tableColumns, (array) ($override['kanca_priority'] ?? []))
        );
        if ($priorityKancaColumn !== null) {
            $kancaColumn = $priorityKancaColumn;
        }

        if ($periodColumn === null) {
            $periodColumn = $this->resolveMostPopulatedColumn(
                $tableName,
                $this->resolveCandidateColumns($tableColumns, (array) ($override['period'] ?? []))
            );
        }

        if ($kancaColumn === null) {
            $kancaColumn = $this->resolveMostPopulatedColumn(
                $tableName,
                $this->resolveCandidateColumns($tableColumns, (array) ($override['kanca'] ?? []))
            );
        }

        return [$periodColumn, $kancaColumn];
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
        } catch (\Throwable) {
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

    private function formatManagementPeriodLabel(mixed $value, ?string $columnName = null): string
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
        $kancaLabelFallbackColumn = $this->resolveKancaLabelFallbackColumn($tableName);

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

        if ($kancaLabelFallbackColumn !== null) {
            $safeFallback = str_replace('`', '``', $kancaLabelFallbackColumn);
            $selects[] = "MIN(`{$safeFallback}`) as kanca_label_fallback_value";
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
            $kancaFallbackRaw = $kancaLabelFallbackColumn !== null ? ($item->kanca_label_fallback_value ?? null) : null;
            $periodLabel = $periodRaw === null || trim((string) $periodRaw) === ''
                ? ($periodColumn !== null ? '(Blank)' : '(Tanpa Periode)')
                : $this->formatManagementPeriodLabel($periodRaw, $periodColumn);
            $kancaLabel = $kancaRaw === null || trim((string) $kancaRaw) === ''
                ? ($kancaColumn !== null ? '(Blank)' : '(Semua)')
                : $this->resolveManagementKancaLabel($tableName, (string) $kancaRaw, $kancaFallbackRaw);

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

    private function resolveKancaLabelFallbackColumn(string $tableName): ?string
    {
        $override = self::MANAGEMENT_SCOPE_COLUMN_OVERRIDES[$tableName] ?? null;
        if (!is_array($override)) {
            return null;
        }

        $candidates = (array) ($override['kanca_label_fallback_priority'] ?? []);

        return $candidates[0] ?? null;
    }

    private function resolveManagementKancaLabel(string $tableName, string $kancaRaw, mixed $fallbackRaw = null): string
    {
        $kancaRaw = trim($kancaRaw);
        if ($kancaRaw === '') {
            return '(Blank)';
        }

        if ($tableName !== 'cognos_ph') {
            return $kancaRaw;
        }

        $fallback = trim((string) ($fallbackRaw ?? ''));
        if (
            preg_match('/^\d+$/', $kancaRaw) === 1
            && $fallback !== ''
            && preg_match('/^\d+\s*--\s*(KC|KCP)\b/i', $fallback) === 1
        ) {
            return $fallback;
        }

        return $kancaRaw;
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
}
