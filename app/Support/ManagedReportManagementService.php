<?php

namespace App\Support;

use Carbon\Carbon;
use App\Models\NamaReport;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ManagedReportManagementService
{
    private const MANAGEMENT_MAX_GROUP_ROWS = 5000;
    private const MANAGEMENT_PERIODS_PER_PAGE = 8;
    private const MANAGEMENT_EXACT_PERIOD_COUNT_ROW_LIMIT = 250000;
    private const MANAGEMENT_HEAVY_TABLE_ROW_LIMIT = 1000000;
    private const MANAGEMENT_HEAVY_PERIODS_PER_PAGE = 1;
    private const LW325_BLANK_CREATED_AT_FALLBACK_MODE = 'lw325_blank_created_at';

    private const SCOPE_COLUMN_CACHE_TTL = 86400;
    private const AGGREGATE_CACHE_TTL = 120;
    private const ESTIMATE_ROWS_CACHE_TTL = 300;

    /** @var array<string, int> */
    private array $estimateRowsMemo = [];

    /** @var array<string, int> */
    private array $columnPopulationMemo = [];

    /** @var array<string, bool> */
    private array $columnIsStringMemo = [];

    public static function cacheTagForTable(string $tableName): string
    {
        return 'report_management:' . $tableName;
    }

    private static function versionKey(string $tableName): string
    {
        return 'report_management:version:' . $tableName;
    }

    private static function currentVersion(string $tableName): string
    {
        $key = self::versionKey($tableName);

        try {
            $value = Cache::get($key);
            if (is_string($value) && $value !== '') {
                return $value;
            }

            $seed = (string) (microtime(true) * 1000);
            Cache::forever($key, $seed);
            return $seed;
        } catch (\Throwable) {
            return 'runtime';
        }
    }

    public static function invalidateTableCache(string $tableName): void
    {
        try {
            $tag = self::cacheTagForTable($tableName);
            $store = Cache::getStore();
            if (method_exists($store, 'tags')) {
                Cache::tags([$tag])->flush();
            }
        } catch (\Throwable) {
        }

        try {
            Cache::forever(self::versionKey($tableName), (string) (microtime(true) * 1000));
        } catch (\Throwable) {
        }
    }

    private function rememberCached(string $tableName, string $key, int $ttl, \Closure $callback): mixed
    {
        $version = self::currentVersion($tableName);
        $versionedKey = $key . ':v=' . $version;

        try {
            $store = Cache::getStore();
            if (method_exists($store, 'tags')) {
                return Cache::tags([self::cacheTagForTable($tableName)])->remember($versionedKey, $ttl, $callback);
            }
        } catch (\Throwable) {
        }

        try {
            return Cache::remember($versionedKey, $ttl, $callback);
        } catch (\Throwable) {
            return $callback();
        }
    }

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
        'daily_loan_dinamis' => [
            'period_priority' => ['periode'],
            'kanca_priority' => ['cabang1'],
        ],
        'input_rekanan' => [
            'period' => ['created_at', 'updated_at'],
            'kanca' => ['perusahaan_anak', 'rekanan_level_1', 'status_nasabah'],
        ],
        'bod_boc' => [
            'period' => ['created_at', 'updated_at'],
            'kanca' => ['instansi', 'bod_boc', 'nama_nasabah'],
        ],
        'jumlah_merchant_detail' => [
            'period_priority' => ['posisi', 'month_day_year_of_posisi', 'Month_Day_Year_of_Posisi', 'month_day_year_of_periode', 'Month_Day_Year_of_Periode', 'periode'],
        ],
        'jumlah_merchant_qris_detail' => [
            'period_priority' => ['POSISI', 'Month_Day_Year_of_Posisi', 'month_day_year_of_posisi', 'Month_Day_Year_of_Periode', 'month_day_year_of_periode', 'PERIODE'],
            'kanca_priority' => ['MBDESC', 'BRDESC'],
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
        'gi405_recovery' => [
            'period_priority' => ['periode'],
            'no_kanca' => true,
        ],
        'cognos_ph' => [
            'period_priority' => ['periode'],
            'kanca_priority' => ['kanca'],
            'kanca_label_fallback_priority' => ['unit_kerja'],
        ],
        'ssa_simpanan' => [
            'period_priority' => ['Month_Day_Year_of_Posisi', 'month_day_year_of_posisi'],
            'kanca_priority' => ['nama_cabang'],
            'normalize_kanca_whitespace' => true,
        ],
        'hourly_dpk' => [
            'period_priority' => ['posisi'],
            'kanca_priority' => ['mbname'],
            'kanca_label_fallback_priority' => ['brname'],
            'normalize_kanca_whitespace' => true,
        ],
        'ssa_pinjaman' => [
            'period_priority' => ['month_day_year_of_periode', 'Month_Day_Year_of_Periode'],
            'kanca_priority' => ['nama_cabang'],
            'normalize_kanca_whitespace' => true,
        ],
        'ssa_almafacts' => [
            'period_priority' => ['month_day_year_of_posisi'],
            'kanca_priority' => ['kanca_konsolidasi'],
            'normalize_kanca_whitespace' => true,
        ],
        'lw321pn' => [
            'period_priority' => ['periode'],
            'kanca_priority' => ['kode_kanca'],
            'kanca_label_fallback_priority' => ['kanca'],
        ],
        'dly_kap_resegmentasi' => [
            'period_priority' => ['periode'],
            'kanca_priority' => ['kode_cabang'],
            'kanca_extra_priority' => ['kode_unit'],
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
        $extraScopeColumns = $this->resolveManagementExtraScopeColumns($tableName, $tableColumns);

        $maxRows = (int) ($options['max_rows'] ?? self::MANAGEMENT_MAX_GROUP_ROWS);
        $page = (int) ($options['page'] ?? 1);
        $perPage = (int) ($options['per_page'] ?? self::MANAGEMENT_PERIODS_PER_PAGE);
        $pageTarget = strtolower(trim((string) ($options['page_target'] ?? '')));

        $this->emitProgress($progressCallback, [
            'stage' => 'grouping',
            'message' => 'Menjalankan query grouping data report. Tahap ini paling berat untuk report besar...',
            'completed_units' => 2,
            'total_units' => 4,
            'progress_percent' => 55,
        ]);

        $managementPage = $this->buildManagementPage(
            $tableName,
            $periodColumn,
            $kancaColumn,
            $extraScopeColumns,
            $maxRows,
            $page,
            $perPage,
            $pageTarget
        );

        $this->emitProgress($progressCallback, [
            'stage' => 'counting',
            'message' => 'Menyiapkan ringkasan baris sumber dan pagination periode...',
            'completed_units' => 3,
            'total_units' => 4,
            'progress_percent' => 82,
        ]);

        $grandTotalRows = $this->estimateTableRows($tableName);

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
                'truncated' => $managementPage['truncated'],
                'displayed_rows_total' => $managementPage['displayed_rows_total'],
                'grand_total_rows' => $grandTotalRows,
                'total_groups' => $managementPage['total_groups'],
                'rows' => $managementPage['rows'],
                'periods' => $managementPage['periods'],
                'pagination' => $managementPage['pagination'],
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
        $schemaSignature = md5(implode('|', $tableColumns));
        $cacheKey = 'report_management:scope_cols:' . $tableName . ':' . $schemaSignature;

        $resolved = $this->rememberCached($tableName, $cacheKey, self::SCOPE_COLUMN_CACHE_TTL, function () use ($tableName, $tableColumns) {
            return $this->computeManagementScopeColumns($tableName, $tableColumns);
        });

        return is_array($resolved) ? $resolved : [null, null];
    }

    private function computeManagementScopeColumns(string $tableName, array $tableColumns): array
    {
        $override = self::MANAGEMENT_SCOPE_COLUMN_OVERRIDES[$tableName] ?? null;
        $periodColumn = null;
        $kancaColumn = null;
        $noKanca = is_array($override) && ($override['no_kanca'] ?? false);

        if (is_array($override)) {
            $priorityPeriod = $this->resolveCandidateColumns($tableColumns, (array) ($override['period_priority'] ?? []));
            if ($priorityPeriod !== []) {
                $periodColumn = $this->resolveMostPopulatedColumn($tableName, $priorityPeriod);
            }

            if (!$noKanca) {
                $priorityKanca = $this->resolveCandidateColumns($tableColumns, (array) ($override['kanca_priority'] ?? []));
                if ($priorityKanca !== []) {
                    $kancaColumn = $this->resolveMostPopulatedColumn($tableName, $priorityKanca);
                }
            }
        }

        if ($periodColumn === null) {
            $periodCandidates = $this->resolveCandidateColumns($tableColumns, self::PERIOD_COLUMN_CANDIDATES);
            $periodColumn = $this->resolveMostPopulatedColumn($tableName, $periodCandidates);
            if ($periodColumn === null) {
                $semanticPeriodColumn = $this->resolveSemanticPeriodColumn($tableColumns);
                $periodColumn = $semanticPeriodColumn !== null
                    ? $this->resolveMostPopulatedColumn($tableName, [$semanticPeriodColumn])
                    : null;
            }
        }

        if ($kancaColumn === null && !$noKanca) {
            $kancaCandidates = $this->resolveCandidateColumns($tableColumns, self::KANCA_COLUMN_CANDIDATES);
            $kancaColumn = $this->resolveMostPopulatedColumn($tableName, $kancaCandidates);
            if ($kancaColumn === null) {
                $semanticKancaColumn = $this->resolveSemanticKancaColumn($tableColumns);
                $kancaColumn = $semanticKancaColumn !== null
                    ? $this->resolveMostPopulatedColumn($tableName, [$semanticKancaColumn])
                    : null;
            }
        }

        if (is_array($override)) {
            if ($periodColumn === null) {
                $periodColumn = $this->resolveMostPopulatedColumn(
                    $tableName,
                    $this->resolveCandidateColumns($tableColumns, (array) ($override['period'] ?? []))
                );
            }

            if ($kancaColumn === null && !$noKanca) {
                $kancaColumn = $this->resolveMostPopulatedColumn(
                    $tableName,
                    $this->resolveCandidateColumns($tableColumns, (array) ($override['kanca'] ?? []))
                );
            }
        }

        return [$periodColumn, $kancaColumn];
    }

    public function resolveManagementExtraScopeColumns(string $tableName, array $tableColumns): array
    {
        $override = self::MANAGEMENT_SCOPE_COLUMN_OVERRIDES[$tableName] ?? null;
        if (!is_array($override)) {
            return [];
        }

        return $this->resolveCandidateColumns(
            $tableColumns,
            (array) ($override['kanca_extra_priority'] ?? [])
        );
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
        $memoKey = $tableName . '|' . $column;
        if (array_key_exists($memoKey, $this->columnPopulationMemo)) {
            return $this->columnPopulationMemo[$memoKey];
        }

        $safeColumn = str_replace('`', '``', $column);

        try {
            $sample = DB::selectOne(
                "SELECT SUM(CASE WHEN `{$safeColumn}` IS NOT NULL AND CAST(`{$safeColumn}` AS CHAR) <> '' THEN 1 ELSE 0 END) AS populated"
                . " FROM (SELECT `{$safeColumn}` FROM `{$tableName}` LIMIT 10000) AS sample"
            );
            $populated = $sample !== null ? (int) ($sample->populated ?? 0) : 0;
        } catch (\Throwable) {
            try {
                $sample = DB::selectOne(
                    "SELECT SUM(CASE WHEN `{$safeColumn}` IS NOT NULL THEN 1 ELSE 0 END) AS populated"
                    . " FROM (SELECT `{$safeColumn}` FROM `{$tableName}` LIMIT 10000) AS sample"
                );
                $populated = $sample !== null ? (int) ($sample->populated ?? 0) : 0;
            } catch (\Throwable) {
                $populated = 0;
            }
        }

        return $this->columnPopulationMemo[$memoKey] = $populated;
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

    private function buildManagementPage(
        string $tableName,
        ?string $periodColumn,
        ?string $kancaColumn,
        array $extraScopeColumns,
        int $maxRows,
        int $page,
        int $perPage,
        string $pageTarget = ''
    ): array {
        if (
            $periodColumn !== null
            && !$this->supportsLw325BlankCreatedAtFallback($tableName, $periodColumn, $kancaColumn)
        ) {
            return $this->buildPeriodPagedManagementRows(
                $tableName,
                $periodColumn,
                $kancaColumn,
                $extraScopeColumns,
                $maxRows,
                $page,
                $perPage,
                $pageTarget
            );
        }

        [$rows, $truncated] = $this->buildManagementRows($tableName, $periodColumn, $kancaColumn, $extraScopeColumns, $maxRows);
        $paginatedPeriods = $this->paginateManagementPeriods($rows, $page, $perPage, $periodColumn !== null, $pageTarget);
        $displayedRowsTotal = array_reduce($paginatedPeriods['periods'], static function (int $carry, array $period): int {
            return $carry + (int) ($period['total_rows'] ?? 0);
        }, 0);

        return [
            'rows' => $paginatedPeriods['rows'],
            'periods' => $paginatedPeriods['periods'],
            'pagination' => $paginatedPeriods['pagination'],
            'truncated' => $truncated,
            'displayed_rows_total' => $displayedRowsTotal,
            'total_groups' => count($rows),
        ];
    }

    private function buildPeriodPagedManagementRows(
        string $tableName,
        string $periodColumn,
        ?string $kancaColumn,
        array $extraScopeColumns,
        int $maxRows,
        int $page,
        int $perPage,
        string $pageTarget = ''
    ): array {
        $safePeriod = str_replace('`', '``', $periodColumn);
        $estimatedSourceRows = $this->estimateTableRows($tableName);
        $perPage = $this->resolveEffectiveManagementPerPage($perPage, $estimatedSourceRows);

        $useExactPeriodCount = $pageTarget === 'last' || $estimatedSourceRows <= self::MANAGEMENT_EXACT_PERIOD_COUNT_ROW_LIMIT;
        $requestedPage = max(1, $page);
        $currentPage = $requestedPage;
        $offset = ($requestedPage - 1) * $perPage;
        $totalPeriods = null;

        $periodListCacheKey = 'report_management:period_list:' . md5(implode('|', [
            $tableName,
            $periodColumn,
            $requestedPage,
            $pageTarget,
            $useExactPeriodCount ? $perPage : ($perPage + 1),
        ]));

        $periodRowsRaw = $this->rememberCached($tableName, $periodListCacheKey, self::AGGREGATE_CACHE_TTL, function () use ($tableName, $periodColumn, $safePeriod, $offset, $useExactPeriodCount, $perPage, $pageTarget) {
            if ($useExactPeriodCount) {
                return $this->fetchExactPeriodPageRows($tableName, $periodColumn, $safePeriod, $offset, $perPage, $pageTarget);
            }

            return DB::table($tableName)
                ->selectRaw("`{$safePeriod}` as period_value")
                ->groupBy($periodColumn)
                ->orderByDesc($periodColumn)
                ->offset($offset)
                ->limit($perPage + 1)
                ->get()
                ->all();
        });

        $periodRows = collect(is_array($periodRowsRaw) ? $periodRowsRaw : []);
        if ($useExactPeriodCount && $periodRows->isNotEmpty()) {
            $totalPeriods = (int) ($periodRows->first()->total_periods ?? 0);
            $totalPages = max(1, (int) ceil($totalPeriods / $perPage));
            $currentPage = $pageTarget === 'last' ? $totalPages : min($requestedPage, $totalPages);
        }

        $hasNext = !$useExactPeriodCount && $periodRows->count() > $perPage;
        if ($hasNext) {
            $periodRows = $periodRows->take($perPage);
        }
        $resolvedTotalPeriods = $totalPeriods ?? ($offset + $periodRows->count() + ($hasNext ? 1 : 0));

        if ($periodRows->isEmpty()) {
            return [
                'rows' => [],
                'periods' => [],
                'pagination' => $this->buildManagementPagination(
                    $currentPage,
                    $perPage,
                    $resolvedTotalPeriods,
                    $useExactPeriodCount,
                    $hasNext
                ),
                'truncated' => false,
                'displayed_rows_total' => 0,
                'total_groups' => 0,
            ];
        }

        $nonBlankPeriods = [];
        $hasBlankPeriod = false;
        foreach ($periodRows as $periodRow) {
            $periodValue = $periodRow->period_value ?? null;
            if ($periodValue === null || trim((string) $periodValue) === '') {
                $hasBlankPeriod = true;
                continue;
            }

            $nonBlankPeriods[(string) $periodValue] = $periodValue;
        }

        $query = DB::table($tableName);
        $query->where(function ($periodQuery) use ($periodColumn, $nonBlankPeriods, $hasBlankPeriod) {
            $hasConstraint = false;

            if ($nonBlankPeriods !== []) {
                $periodQuery->whereIn($periodColumn, array_values($nonBlankPeriods));
                $hasConstraint = true;
            }

            if ($hasBlankPeriod) {
                $method = $hasConstraint ? 'orWhere' : 'where';
                $periodQuery->{$method}(function ($blankQuery) use ($periodColumn) {
                    $this->applyBlankValueConstraint($blankQuery, $periodColumn);
                });
            }
        });

        $selects = ['COUNT(*) as row_count', "`{$safePeriod}` as period_value"];
        $query->groupBy($periodColumn);

        $kancaLabelFallbackColumn = $this->resolveKancaLabelFallbackColumn($tableName);
        if ($kancaColumn !== null) {
            $safeKanca = str_replace('`', '``', $kancaColumn);
            $selects[] = "`{$safeKanca}` as kanca_value";
            $query->groupBy($kancaColumn);
        }

        foreach (array_values($extraScopeColumns) as $index => $extraColumn) {
            $safeExtra = str_replace('`', '``', (string) $extraColumn);
            $selects[] = "`{$safeExtra}` as extra_value_{$index}";
            $query->groupBy((string) $extraColumn);
        }

        if ($kancaLabelFallbackColumn !== null) {
            $safeFallback = str_replace('`', '``', $kancaLabelFallbackColumn);
            $selects[] = "MIN(`{$safeFallback}`) as kanca_label_fallback_value";
        }

        $aggregateCacheKey = 'report_management:agg:' . md5(implode('|', [
            $tableName,
            $periodColumn,
            $kancaColumn ?? '',
            implode(',', array_map('strval', $extraScopeColumns)),
            $kancaLabelFallbackColumn ?? '',
            $maxRows,
            $hasBlankPeriod ? '1' : '0',
            implode(',', array_map('strval', array_values($nonBlankPeriods))),
        ]));

        $resultRaw = $this->rememberCached($tableName, $aggregateCacheKey, self::AGGREGATE_CACHE_TTL, function () use ($query, $selects, $maxRows) {
            return $query
                ->selectRaw(implode(', ', $selects))
                ->limit($maxRows + 1)
                ->get()
                ->all();
        });
        $result = collect(is_array($resultRaw) ? $resultRaw : []);

        $truncated = $result->count() > $maxRows;
        if ($truncated) {
            $result = $result->take($maxRows);
        }

        $rows = $this->normalizeManagementGroupRows(
            $tableName,
            $result,
            $periodColumn,
            $kancaColumn,
            $kancaLabelFallbackColumn,
            $extraScopeColumns
        );
        $periods = $this->groupCurrentManagementPeriods($rows, $periodColumn !== null);
        $displayedRowsTotal = array_reduce($periods, static function (int $carry, array $period): int {
            return $carry + (int) ($period['total_rows'] ?? 0);
        }, 0);

        return [
            'rows' => $rows,
            'periods' => $periods,
            'pagination' => $this->buildManagementPagination(
                $currentPage,
                $perPage,
                $resolvedTotalPeriods,
                $useExactPeriodCount,
                $hasNext
            ),
            'truncated' => $truncated,
            'displayed_rows_total' => $displayedRowsTotal,
            'total_groups' => count($rows),
        ];
    }

    private function fetchExactPeriodPageRows(
        string $tableName,
        string $periodColumn,
        string $safePeriod,
        int $offset,
        int $perPage,
        string $pageTarget
    ): array {
        $groupedPeriods = DB::table($tableName)
            ->selectRaw("`{$safePeriod}` as period_value")
            ->groupBy($periodColumn);

        $rankedPeriods = DB::query()
            ->fromSub($groupedPeriods, 'management_periods')
            ->selectRaw('period_value, ROW_NUMBER() OVER (ORDER BY period_value DESC) as row_number, COUNT(*) OVER () as total_periods');

        $pagedPeriods = DB::query()->fromSub($rankedPeriods, 'ranked_management_periods');

        if ($pageTarget === 'last') {
            $pagedPeriods->whereRaw('row_number >= total_periods - ((total_periods - 1) % ?) ', [$perPage]);
        } else {
            $pagedPeriods->where(function ($query) use ($offset, $perPage) {
                $query
                    ->whereBetween('row_number', [$offset + 1, $offset + $perPage])
                    ->orWhere(function ($lastPageQuery) use ($offset, $perPage) {
                        $lastPageQuery
                            ->whereRaw('? > total_periods', [$offset + 1])
                            ->whereRaw('row_number >= total_periods - ((total_periods - 1) % ?) ', [$perPage]);
                    });
            });
        }

        return $pagedPeriods
            ->orderByDesc('period_value')
            ->get()
            ->all();
    }

    private function resolveEffectiveManagementPerPage(int $requestedPerPage, int $estimatedSourceRows): int
    {
        $requestedPerPage = max(1, $requestedPerPage);
        if ($estimatedSourceRows >= self::MANAGEMENT_HEAVY_TABLE_ROW_LIMIT) {
            return min($requestedPerPage, self::MANAGEMENT_HEAVY_PERIODS_PER_PAGE);
        }

        return $requestedPerPage;
    }

    private function buildManagementPagination(
        int $currentPage,
        int $perPage,
        int $totalPeriods,
        bool $totalPeriodsExact = true,
        ?bool $hasNextOverride = null
    ): array
    {
        $totalPages = max(1, (int) ceil($totalPeriods / max(1, $perPage)));
        $offset = ($currentPage - 1) * $perPage;
        $hasNext = $hasNextOverride ?? ($currentPage < $totalPages);

        return [
            'current_page' => $currentPage,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
            'total_periods' => $totalPeriods,
            'total_periods_exact' => $totalPeriodsExact,
            'has_prev' => $currentPage > 1,
            'has_next' => $hasNext,
            'from_period' => $totalPeriods === 0 ? 0 : ($offset + 1),
            'to_period' => min($offset + $perPage, $totalPeriods),
        ];
    }

    private function groupCurrentManagementPeriods(array $rows, bool $hasPeriodColumn): array
    {
        $periods = [];
        $periodOrder = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $periodLabel = (string) ($row['period_label'] ?? $row['period'] ?? ($hasPeriodColumn ? '(Blank)' : '(Tanpa Periode)'));
            $periodIsNull = (bool) ($row['period_is_null'] ?? false);
            $fallbackBucketKey = trim((string) ($row['fallback_bucket_key'] ?? ''));
            $bucketKey = $hasPeriodColumn
                ? ($fallbackBucketKey !== ''
                    ? 'fallback:' . $fallbackBucketKey
                    : ($periodIsNull ? '__blank__' : 'value:' . $periodLabel))
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

        return array_values(array_map(
            static fn (string $bucketKey): array => $periods[$bucketKey],
            $periodOrder
        ));
    }

    private function normalizeManagementGroupRows(
        string $tableName,
        iterable $result,
        ?string $periodColumn,
        ?string $kancaColumn,
        ?string $kancaLabelFallbackColumn,
        array $extraScopeColumns = []
    ): array {
        $rows = [];
        $extraScopeColumns = array_values($extraScopeColumns);

        foreach ($result as $item) {
            $periodRaw = $periodColumn !== null ? ($item->period_value ?? null) : null;
            $kancaRaw = $kancaColumn !== null ? ($item->kanca_value ?? null) : null;
            $kancaFallbackRaw = $kancaLabelFallbackColumn !== null ? ($item->kanca_label_fallback_value ?? null) : null;
            $extraFilters = $this->buildManagementExtraFilters($tableName, $item, $extraScopeColumns);
            $normalizedPeriodFilter = $periodRaw === null || trim((string) $periodRaw) === ''
                ? ''
                : $this->normalizeManagementPeriodFilter($tableName, $periodRaw, $periodColumn);
            $normalizedKancaFilter = $kancaRaw === null || trim((string) $kancaRaw) === ''
                ? ''
                : $this->normalizeManagementKancaFilter($tableName, (string) $kancaRaw);
            $kancaLabel = $kancaRaw === null || trim((string) $kancaRaw) === ''
                ? ($kancaColumn !== null ? '(Blank)' : '(Semua)')
                : $this->resolveManagementKancaLabel($tableName, (string) $kancaRaw, $kancaFallbackRaw);
            $kancaLabel = $this->appendManagementExtraLabel($tableName, $kancaLabel, $extraFilters);
            $periodIsNull = $periodRaw === null || trim((string) $periodRaw) === '';
            $kancaIsNull = $kancaRaw === null || trim((string) $kancaRaw) === '';
            $aggregateKey = json_encode([
                $periodIsNull,
                $periodIsNull ? '' : $normalizedPeriodFilter,
                $kancaIsNull,
                $kancaIsNull ? '' : $normalizedKancaFilter,
                array_map(static fn (array $filter): array => [
                    $filter['column'] ?? '',
                    (bool) ($filter['is_null'] ?? false),
                    $filter['value'] ?? '',
                ], $extraFilters),
            ]);

            if ($aggregateKey === false || !isset($rows[$aggregateKey])) {
                $rows[$aggregateKey] = [
                    'period' => $periodIsNull ? '' : $normalizedPeriodFilter,
                    'period_label' => $periodIsNull ? ($periodColumn !== null ? '(Blank)' : '(Tanpa Periode)') : $normalizedPeriodFilter,
                    'kanca' => $kancaIsNull ? '' : $normalizedKancaFilter,
                    'kanca_label' => $kancaLabel,
                    'extra_filters' => $extraFilters,
                    'row_count' => 0,
                    'period_is_null' => $periodIsNull,
                    'kanca_is_null' => $kancaIsNull,
                    '_raw_period_values' => [],
                ];
            }

            $rows[$aggregateKey]['row_count'] += (int) ($item->row_count ?? 0);
            if (!$periodIsNull && $periodRaw !== null) {
                $rawPeriodValue = trim((string) $periodRaw);
                if ($rawPeriodValue !== '' && count($rows[$aggregateKey]['_raw_period_values']) < 2) {
                    $rows[$aggregateKey]['_raw_period_values'][$rawPeriodValue] = true;
                }
            }
        }

        $rows = array_map(function (array $row) use ($tableName, $periodColumn) {
            $rawPeriodValues = array_keys((array) ($row['_raw_period_values'] ?? []));
            if (
                !(bool) ($row['period_is_null'] ?? false)
                && count($rawPeriodValues) === 1
            ) {
                $row['period_label'] = $this->resolveAggregatedPeriodLabel(
                    $tableName,
                    trim((string) $rawPeriodValues[0]),
                    (string) ($row['period_label'] ?? ''),
                    $periodColumn
                );
            }

            unset($row['_raw_period_values']);

            return $row;
        }, array_values($rows));

        usort($rows, function (array $left, array $right) use ($tableName): int {
            $pL = (string) ($left['period'] ?? '');
            $pR = (string) ($right['period'] ?? '');

            if ($pL === '' && $pR !== '') return 1;
            if ($pL !== '' && $pR === '') return -1;

            $cmp = strcmp($pR, $pL);
            if ($cmp !== 0) return $cmp;

            if ($tableName === 'dly_kap_resegmentasi') {
                return $this->compareDlyKapManagementRows($left, $right);
            }

            return strcmp((string) ($left['kanca_label'] ?? ''), (string) ($right['kanca_label'] ?? ''));
        });

        return $rows;
    }

    private function compareDlyKapManagementRows(array $left, array $right): int
    {
        $branchCompare = $this->compareManagementNumericText(
            (string) ($left['kanca'] ?? ''),
            (string) ($right['kanca'] ?? '')
        );
        if ($branchCompare !== 0) {
            return $branchCompare;
        }

        $unitCompare = $this->compareManagementNumericText(
            $this->managementExtraFilterValue($left, 'kode_unit'),
            $this->managementExtraFilterValue($right, 'kode_unit')
        );
        if ($unitCompare !== 0) {
            return $unitCompare;
        }

        return strcmp((string) ($left['kanca_label'] ?? ''), (string) ($right['kanca_label'] ?? ''));
    }

    private function managementExtraFilterValue(array $row, string $column): string
    {
        foreach ((array) ($row['extra_filters'] ?? []) as $filter) {
            if (($filter['column'] ?? null) === $column) {
                return (string) ($filter['value'] ?? '');
            }
        }

        return '';
    }

    private function compareManagementNumericText(string $left, string $right): int
    {
        $left = trim($left);
        $right = trim($right);

        if ($left === '' && $right !== '') return 1;
        if ($left !== '' && $right === '') return -1;

        if (preg_match('/^\d+$/', $left) === 1 && preg_match('/^\d+$/', $right) === 1) {
            $leftNumber = (int) ltrim($left, '0');
            $rightNumber = (int) ltrim($right, '0');
            if ($leftNumber !== $rightNumber) {
                return $leftNumber <=> $rightNumber;
            }
        }

        return strcmp($left, $right);
    }

    private function estimateTableRows(string $tableName): int
    {
        if (array_key_exists($tableName, $this->estimateRowsMemo)) {
            return $this->estimateRowsMemo[$tableName];
        }

        $cacheKey = 'report_management:estimate:' . $tableName;
        $value = $this->rememberCached($tableName, $cacheKey, self::ESTIMATE_ROWS_CACHE_TTL, function () use ($tableName): int {
            try {
                if (DB::connection()->getDriverName() === 'mysql') {
                    $row = DB::selectOne(
                        'SELECT TABLE_ROWS AS table_rows FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                        [$tableName]
                    );
                    if ($row !== null && $row->table_rows !== null) {
                        return max(0, (int) $row->table_rows);
                    }
                }
            } catch (\Throwable) {
            }

            return (int) DB::table($tableName)->count();
        });

        return $this->estimateRowsMemo[$tableName] = (int) $value;
    }

    private function buildManagementRows(
        string $tableName,
        ?string $periodColumn,
        ?string $kancaColumn,
        array $extraScopeColumns,
        int $maxRows
    ): array
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

        if ($this->supportsLw325BlankCreatedAtFallback($tableName, $periodColumn, $kancaColumn)) {
            return $this->buildLw325ManagementRows($tableName, $periodColumn, $kancaColumn, $maxRows);
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

        foreach (array_values($extraScopeColumns) as $index => $extraColumn) {
            $safeExtra = str_replace('`', '``', (string) $extraColumn);
            $selects[] = "`{$safeExtra}` as extra_value_{$index}";
            $query->groupBy((string) $extraColumn)->orderBy((string) $extraColumn);
        }

        if ($kancaLabelFallbackColumn !== null) {
            $safeFallback = str_replace('`', '``', $kancaLabelFallbackColumn);
            $selects[] = "MIN(`{$safeFallback}`) as kanca_label_fallback_value";
        }

        $aggregateCacheKey = 'report_management:agg_full:' . md5(implode('|', [
            $tableName,
            $periodColumn ?? '',
            $kancaColumn ?? '',
            implode(',', array_map('strval', $extraScopeColumns)),
            $kancaLabelFallbackColumn ?? '',
            $maxRows,
        ]));

        $resultRaw = $this->rememberCached($tableName, $aggregateCacheKey, self::AGGREGATE_CACHE_TTL, function () use ($query, $selects, $maxRows) {
            return $query
                ->selectRaw(implode(', ', $selects))
                ->limit($maxRows + 1)
                ->get()
                ->all();
        });
        $result = collect(is_array($resultRaw) ? $resultRaw : []);

        $truncated = $result->count() > $maxRows;
        if ($truncated) {
            $result = $result->take($maxRows);
        }

        $rows = $this->normalizeManagementGroupRows(
            $tableName,
            $result,
            $periodColumn,
            $kancaColumn,
            $kancaLabelFallbackColumn,
            $extraScopeColumns
        );

        return [$rows, $truncated];
    }

    private function buildLw325ManagementRows(string $tableName, string $periodColumn, string $kancaColumn, int $maxRows): array
    {
        $rows = [];
        $kancaLabelFallbackColumn = $this->resolveKancaLabelFallbackColumn($tableName);
        $remainingLimit = max(1, $maxRows + 1);

        $regularQuery = DB::table($tableName);
        $this->applyNotBlankIntersectionConstraint($regularQuery, $periodColumn, $kancaColumn);

        $regularSelects = ['COUNT(*) as row_count'];
        $safePeriod = str_replace('`', '``', $periodColumn);
        $safeKanca = str_replace('`', '``', $kancaColumn);
        $regularSelects[] = "`{$safePeriod}` as period_value";
        $regularSelects[] = "`{$safeKanca}` as kanca_value";
        $regularQuery->groupBy($periodColumn)->orderByDesc($periodColumn);
        $regularQuery->groupBy($kancaColumn)->orderBy($kancaColumn);

        if ($kancaLabelFallbackColumn !== null) {
            $safeFallback = str_replace('`', '``', $kancaLabelFallbackColumn);
            $regularSelects[] = "MIN(`{$safeFallback}`) as kanca_label_fallback_value";
        }

        $regularCacheKey = 'report_management:lw325_regular:' . md5(implode('|', [
            $tableName,
            $periodColumn,
            $kancaColumn,
            $kancaLabelFallbackColumn ?? '',
            $remainingLimit,
        ]));

        $regularRowsRaw = $this->rememberCached($tableName, $regularCacheKey, self::AGGREGATE_CACHE_TTL, function () use ($regularQuery, $regularSelects, $remainingLimit) {
            return $regularQuery
                ->selectRaw(implode(', ', $regularSelects))
                ->limit($remainingLimit)
                ->get()
                ->all();
        });
        $regularRows = collect(is_array($regularRowsRaw) ? $regularRowsRaw : []);

        foreach ($regularRows as $item) {
            $periodRaw = $item->period_value ?? null;
            $kancaRaw = $item->kanca_value ?? null;
            $kancaFallbackRaw = $kancaLabelFallbackColumn !== null ? ($item->kanca_label_fallback_value ?? null) : null;
            $normalizedPeriodFilter = $periodRaw === null || trim((string) $periodRaw) === ''
                ? ''
                : $this->normalizeManagementPeriodFilter($tableName, $periodRaw, $periodColumn);
            $periodLabel = $periodRaw === null || trim((string) $periodRaw) === ''
                ? '(Blank)'
                : $this->formatManagementPeriodLabel($periodRaw, $periodColumn);
            $normalizedKancaFilter = $kancaRaw === null || trim((string) $kancaRaw) === ''
                ? ''
                : $this->normalizeManagementKancaFilter($tableName, (string) $kancaRaw);
            $kancaLabel = $kancaRaw === null || trim((string) $kancaRaw) === ''
                ? '(Blank)'
                : $this->resolveManagementKancaLabel($tableName, (string) $kancaRaw, $kancaFallbackRaw);
            $periodIsNull = $periodRaw === null || trim((string) $periodRaw) === '';
            $kancaIsNull = $kancaRaw === null || trim((string) $kancaRaw) === '';
            $aggregateKey = json_encode([
                $periodIsNull,
                $periodIsNull ? '' : $normalizedPeriodFilter,
                $kancaIsNull,
                $kancaIsNull ? '' : $normalizedKancaFilter,
            ]);

            if ($aggregateKey === false || !isset($rows[$aggregateKey])) {
                $rows[$aggregateKey] = [
                    'period' => $periodIsNull ? '' : $normalizedPeriodFilter,
                    'period_label' => $periodIsNull ? '(Blank)' : $normalizedPeriodFilter,
                    'kanca' => $kancaIsNull ? '' : $normalizedKancaFilter,
                    'kanca_label' => $kancaLabel,
                    'row_count' => 0,
                    'period_is_null' => $periodIsNull,
                    'kanca_is_null' => $kancaIsNull,
                    '_raw_period_values' => [],
                ];
            }

            $rows[$aggregateKey]['row_count'] += (int) ($item->row_count ?? 0);
            if (!$periodIsNull && $periodRaw !== null) {
                $rawPeriodValue = trim((string) $periodRaw);
                if ($rawPeriodValue !== '' && count($rows[$aggregateKey]['_raw_period_values']) < 2) {
                    $rows[$aggregateKey]['_raw_period_values'][$rawPeriodValue] = true;
                }
            }
        }

        $specialCacheKey = 'report_management:lw325_special:' . md5(implode('|', [
            $tableName,
            $periodColumn,
            $kancaColumn,
            $remainingLimit,
        ]));

        $specialRowsRaw = $this->rememberCached($tableName, $specialCacheKey, self::AGGREGATE_CACHE_TTL, function () use ($tableName, $periodColumn, $kancaColumn, $remainingLimit) {
            return DB::table($tableName)
                ->selectRaw('COUNT(*) as row_count, `created_at` as fallback_created_at')
                ->where(function ($query) use ($periodColumn) {
                    $this->applyBlankValueConstraint($query, $periodColumn);
                })
                ->where(function ($query) use ($kancaColumn) {
                    $this->applyBlankValueConstraint($query, $kancaColumn);
                })
                ->whereNotNull('created_at')
                ->groupBy('created_at')
                ->orderByDesc('created_at')
                ->limit($remainingLimit)
                ->get()
                ->all();
        });
        $specialRows = collect(is_array($specialRowsRaw) ? $specialRowsRaw : []);

        foreach ($specialRows as $item) {
            $createdAtRaw = $item->fallback_created_at ?? null;
            $normalizedCreatedAt = $this->normalizeManagementCreatedAtFilter($createdAtRaw);
            if ($normalizedCreatedAt === null) {
                continue;
            }

            $aggregateKey = json_encode([
                'fallback',
                self::LW325_BLANK_CREATED_AT_FALLBACK_MODE,
                $normalizedCreatedAt,
            ]);

            if ($aggregateKey === false) {
                continue;
            }

            $rows[$aggregateKey] = [
                'period' => '',
                'period_label' => $this->formatLw325FallbackPeriodLabel($normalizedCreatedAt),
                'kanca' => '',
                'kanca_label' => '(Blank)',
                'row_count' => (int) ($item->row_count ?? 0),
                'period_is_null' => true,
                'kanca_is_null' => true,
                'fallback_mode' => self::LW325_BLANK_CREATED_AT_FALLBACK_MODE,
                'fallback_period_column' => 'created_at',
                'fallback_period_filter' => $normalizedCreatedAt,
                'fallback_period_label' => $this->formatLw325FallbackPeriodLabel($normalizedCreatedAt),
                'fallback_bucket_key' => self::LW325_BLANK_CREATED_AT_FALLBACK_MODE . ':' . $normalizedCreatedAt,
            ];
        }

        $residualCacheKey = 'report_management:lw325_residual:' . md5(implode('|', [
            $tableName,
            $periodColumn,
            $kancaColumn,
        ]));

        $residualRaw = $this->rememberCached($tableName, $residualCacheKey, self::AGGREGATE_CACHE_TTL, function () use ($tableName, $periodColumn, $kancaColumn) {
            $row = DB::table($tableName)
                ->selectRaw('COUNT(*) as row_count')
                ->where(function ($query) use ($periodColumn) {
                    $this->applyBlankValueConstraint($query, $periodColumn);
                })
                ->where(function ($query) use ($kancaColumn) {
                    $this->applyBlankValueConstraint($query, $kancaColumn);
                })
                ->whereNull('created_at')
                ->first();
            return ['row_count' => (int) ($row->row_count ?? 0)];
        });
        $residualBlankRows = (object) (is_array($residualRaw) ? $residualRaw : ['row_count' => 0]);

        if (((int) ($residualBlankRows->row_count ?? 0)) > 0) {
            $aggregateKey = json_encode(['residual_blank_blank_created_at_null']);
            if ($aggregateKey !== false) {
                $rows[$aggregateKey] = [
                    'period' => '',
                    'period_label' => '(Blank)',
                    'kanca' => '',
                    'kanca_label' => '(Blank)',
                    'row_count' => (int) ($residualBlankRows->row_count ?? 0),
                    'period_is_null' => true,
                    'kanca_is_null' => true,
                ];
            }
        }

        $rows = array_map(function (array $row) use ($tableName, $periodColumn) {
            $rawPeriodValues = array_keys((array) ($row['_raw_period_values'] ?? []));
            if (
                !(bool) ($row['period_is_null'] ?? false)
                && count($rawPeriodValues) === 1
            ) {
                $row['period_label'] = $this->resolveAggregatedPeriodLabel(
                    $tableName,
                    trim((string) $rawPeriodValues[0]),
                    (string) ($row['period_label'] ?? ''),
                    $periodColumn
                );
            }

            unset($row['_raw_period_values']);

            return $row;
        }, array_values($rows));

        usort($rows, function (array $left, array $right): int {
            $leftBucket = (string) ($left['fallback_bucket_key'] ?? '');
            $rightBucket = (string) ($right['fallback_bucket_key'] ?? '');
            if ($leftBucket !== '' || $rightBucket !== '') {
                return strcmp($rightBucket, $leftBucket);
            }

            return strcmp((string) ($right['period_label'] ?? ''), (string) ($left['period_label'] ?? ''));
        });

        $truncated = count($rows) > $maxRows;
        if ($truncated) {
            $rows = array_slice($rows, 0, $maxRows);
        }

        return [$rows, $truncated];
    }

    private function buildManagementExtraFilters(string $tableName, object $item, array $extraScopeColumns): array
    {
        $filters = [];

        foreach (array_values($extraScopeColumns) as $index => $column) {
            $column = (string) $column;
            $rawValue = $item->{'extra_value_' . $index} ?? null;
            $isNull = $rawValue === null || trim((string) $rawValue) === '';
            $value = $isNull ? '' : $this->normalizeManagementKancaFilter($tableName, (string) $rawValue);

            $filters[] = [
                'column' => $column,
                'label' => $this->formatManagementExtraColumnLabel($column),
                'value' => $value,
                'value_label' => $isNull ? '(Blank)' : trim((string) $rawValue),
                'is_null' => $isNull,
            ];
        }

        return $filters;
    }

    private function appendManagementExtraLabel(string $tableName, string $baseLabel, array $extraFilters): string
    {
        if ($tableName !== 'dly_kap_resegmentasi' || $extraFilters === []) {
            return $baseLabel;
        }

        foreach ($extraFilters as $filter) {
            if (($filter['column'] ?? null) === 'kode_unit') {
                $unitLabel = trim((string) ($filter['value_label'] ?? $filter['value'] ?? ''));
                return 'Cabang ' . $baseLabel . ' | Unit ' . ($unitLabel !== '' ? $unitLabel : '(Blank)');
            }
        }

        return $baseLabel;
    }

    private function formatManagementExtraColumnLabel(string $column): string
    {
        $label = str_replace('_', ' ', trim($column));
        $label = preg_replace('/\s+/', ' ', $label) ?? $label;

        return ucwords($label);
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

        if ($tableName === 'lw321pn') {
            $fallback = trim((string) ($fallbackRaw ?? ''));
            if ($fallback !== '') {
                return $fallback;
            }

            return $kancaRaw;
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

    private function normalizeManagementPeriodFilter(string $tableName, mixed $periodRaw, ?string $periodColumn = null): string
    {
        return $this->formatManagementPeriodLabel($periodRaw, $periodColumn);
    }

    private function normalizeManagementCreatedAtFilter(mixed $createdAtRaw): ?string
    {
        $value = trim((string) ($createdAtRaw ?? ''));
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    private function formatLw325FallbackPeriodLabel(string $createdAt): string
    {
        return 'Import ' . $createdAt;
    }

    private function supportsLw325BlankCreatedAtFallback(string $tableName, ?string $periodColumn, ?string $kancaColumn): bool
    {
        return strtolower(trim($tableName)) === 'lw325_ph'
            && $periodColumn !== null
            && $kancaColumn !== null
            && Schema::hasColumn($tableName, 'created_at');
    }

    private function applyNotBlankIntersectionConstraint($query, string $periodColumn, string $kancaColumn): void
    {
        $tableName = $this->resolveQueryTableName($query);

        $query->where(function ($innerQuery) use ($tableName, $periodColumn, $kancaColumn) {
            $innerQuery->where(function ($periodFilledQuery) use ($tableName, $periodColumn) {
                $this->applyNotBlankColumnPredicate($periodFilledQuery, $tableName, $periodColumn);
            })->orWhere(function ($kancaFilledQuery) use ($tableName, $kancaColumn) {
                $this->applyNotBlankColumnPredicate($kancaFilledQuery, $tableName, $kancaColumn);
            });
        });
    }

    private function applyBlankValueConstraint($query, string $column): void
    {
        $tableName = $this->resolveQueryTableName($query);
        $safeColumn = str_replace('`', '``', $column);
        $isStringLike = $this->isStringLikeColumn($tableName, $column);

        $query->where(function ($innerQuery) use ($column, $safeColumn, $isStringLike) {
            $innerQuery->whereNull($column);
            if ($isStringLike) {
                $innerQuery->orWhereRaw("`{$safeColumn}` = ''");
            }
        });
    }

    private function applyNotBlankColumnPredicate($query, ?string $tableName, string $column): void
    {
        $safeColumn = str_replace('`', '``', $column);
        $query->whereNotNull($column);
        if ($this->isStringLikeColumn($tableName, $column)) {
            $query->whereRaw("`{$safeColumn}` <> ''");
        }
    }

    private function resolveQueryTableName($query): ?string
    {
        try {
            $from = is_object($query) && property_exists($query, 'from') ? $query->from : null;
            if (!is_string($from) || $from === '') {
                return null;
            }
            $segments = preg_split('/\s+as\s+|\s+/i', trim($from));
            $candidate = is_array($segments) && $segments !== [] ? trim((string) $segments[0]) : trim((string) $from);
            return $candidate !== '' ? trim($candidate, '`') : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function isStringLikeColumn(?string $tableName, string $column): bool
    {
        if ($tableName === null || $tableName === '') {
            return true;
        }

        $memoKey = $tableName . '|' . $column;
        if (array_key_exists($memoKey, $this->columnIsStringMemo)) {
            return $this->columnIsStringMemo[$memoKey];
        }

        $stringLike = true;
        try {
            $type = Schema::getColumnType($tableName, $column);
            $normalizedType = strtolower((string) $type);
            $stringLike = in_array($normalizedType, [
                'string',
                'varchar',
                'char',
                'text',
                'tinytext',
                'mediumtext',
                'longtext',
                'guid',
                'uuid',
                'json',
                'enum',
                'set',
            ], true);

            if (
                !$stringLike
                && DB::connection()->getDriverName() === 'sqlite'
                && in_array($normalizedType, ['date', 'datetime', 'timestamp'], true)
            ) {
                $stringLike = true;
            }
        } catch (\Throwable) {
        }

        return $this->columnIsStringMemo[$memoKey] = $stringLike;
    }

    private function normalizeManagementKancaFilter(string $tableName, string $kancaRaw): string
    {
        $normalized = trim($kancaRaw);
        $override = self::MANAGEMENT_SCOPE_COLUMN_OVERRIDES[$tableName] ?? null;
        $normalizeWhitespace = is_array($override) && (bool) ($override['normalize_kanca_whitespace'] ?? false);

        if ($normalizeWhitespace) {
            $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;
        }

        return $normalized;
    }

    private function resolveAggregatedPeriodLabel(string $tableName, string $singleRawPeriod, string $defaultLabel, ?string $periodColumn = null): string
    {
        if ($singleRawPeriod === '') {
            return $defaultLabel;
        }

        if (!in_array($tableName, ['ssa_simpanan', 'ssa_pinjaman'], true)) {
            return $defaultLabel;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $singleRawPeriod) === 1) {
            return $singleRawPeriod;
        }

        $strictNormalized = StrictDateParser::normalize($singleRawPeriod);
        if ($strictNormalized !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $strictNormalized) === 1) {
            return $strictNormalized;
        }

        $formatted = $this->formatManagementPeriodLabel($singleRawPeriod, $periodColumn);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $formatted) === 1 ? $formatted : $defaultLabel;
    }

    private function paginateManagementPeriods(array $rows, int $page, int $perPage, bool $hasPeriodColumn, string $pageTarget = ''): array
    {
        $periods = [];
        $periodOrder = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $periodLabel = (string) ($row['period_label'] ?? $row['period'] ?? ($hasPeriodColumn ? '(Blank)' : '(Tanpa Periode)'));
            $periodIsNull = (bool) ($row['period_is_null'] ?? false);
            $fallbackBucketKey = trim((string) ($row['fallback_bucket_key'] ?? ''));
            $bucketKey = $hasPeriodColumn
                ? ($fallbackBucketKey !== ''
                    ? 'fallback:' . $fallbackBucketKey
                    : ($periodIsNull ? '__blank__' : 'value:' . $periodLabel))
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
        $currentPage = $pageTarget === 'last' ? $totalPages : min(max(1, $page), $totalPages);
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
