<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ReportSnapshotBuilder
{
    private const DASHBOARD_SNAPSHOT_TABLE = 'dashboard_pinjaman_snapshots';
    private const RASIO_SNAPSHOT_TABLE = 'rasio_casa_debitur_snapshots';
    private const DORMANT_SNAPSHOT_TABLE = 'rekening_dormant_snapshots';

    private const PRIORITY_BRANCHES = ['MADIUN', 'MAGETAN', 'NGAWI', 'PONOROGO'];
    private const SEGMENTS = ['total', 'briguna', 'kpr', 'mikro', 'smc'];

    private const AREA_BRANCHES = [
        'KC Madiun',
        'KC Magetan',
        'KC Ngawi',
        'KC Ponorogo',
    ];

    private const BRANCH_PATTERNS = [
        'KC Madiun' => 'KC MADIUN',
        'KC Magetan' => 'KC MAGETAN',
        'KC Ngawi' => 'KC NGAWI',
        'KC Ponorogo' => 'KC PONOROGO',
    ];

    public function rebuild(string $report = 'all', ?string $period = null, bool $force = false): array
    {
        $report = strtolower(trim($report));

        return match ($report) {
            'dashboard', 'dashboard-pinjaman', 'pinjaman' => [
                'dashboard' => $this->rebuildDashboard($period, $force),
            ],
            'rasio', 'rasio-casa', 'rasio-casa-debitur' => [
                'rasio' => $this->rebuildRasioCasa($period, $force),
            ],
            'dormant', 'rekening-dormant' => [
                'dormant' => $this->rebuildRekeningDormant($period, $force),
            ],
            default => [
                'dashboard' => $this->rebuildDashboard($period, $force),
                'rasio' => $this->rebuildRasioCasa($period, $force),
                'dormant' => $this->rebuildRekeningDormant($period, $force),
            ],
        };
    }

    public function rebuildDashboard(?string $period = null, bool $force = false): array
    {
        $results = [];

        foreach ($this->resolveDashboardPeriods($period) as $snapshotPeriod) {
            $results[$snapshotPeriod] = $this->buildDashboardPeriodSnapshot($snapshotPeriod, $force);
        }

        return $results;
    }

    public function rebuildRasioCasa(?string $period = null, bool $force = false): array
    {
        $results = [];

        foreach ($this->resolveRasioPeriods($period) as $snapshotPeriod) {
            $results[$snapshotPeriod] = $this->buildRasioPeriodSnapshot($snapshotPeriod, $force);
        }

        return $results;
    }

    public function rebuildRekeningDormant(?string $period = null, bool $force = false): array
    {
        $results = [];

        foreach ($this->resolveDormantPeriods($period) as $snapshotPeriod) {
            $results[$snapshotPeriod] = $this->buildDormantPeriodSnapshot($snapshotPeriod, $force);
        }

        return $results;
    }

    private function buildDashboardPeriodSnapshot(string $period, bool $force): int
    {
        if (!$force && DB::table(self::DASHBOARD_SNAPSHOT_TABLE)->where('periode', $period)->exists()) {
            return (int) DB::table(self::DASHBOARD_SNAPSHOT_TABLE)->where('periode', $period)->count();
        }

        DB::table(self::DASHBOARD_SNAPSHOT_TABLE)->where('periode', $period)->delete();

        $buffer = [];
        $inserted = 0;
        $bucketExpression = $this->buildDashboardBucketExpression();

        DB::table('daily_loan_dinamis')
            ->where('periode', $period)
            ->whereNotNull('nomor_rekening1')
            ->where('nomor_rekening1', '<>', '')
            ->selectRaw("
                id,
                TRIM(nomor_rekening1) as account_number,
                COALESCE(baki_debet1, 0) as loan_balance,
                {$bucketExpression} as quality_bucket,
                COALESCE(segmen_dashboard, '') as segmen_dashboard,
                COALESCE(produk_dashboard, '') as produk_dashboard,
                COALESCE(cabang1, '') as cabang1,
                COALESCE(unit1, '') as unit1
            ")
            ->orderBy('id')
            ->chunkById(2000, function ($rows) use (&$buffer, &$inserted, $period) {
                foreach ($rows as $row) {
                    $accountNumber = trim((string) ($row->account_number ?? ''));

                    if ($accountNumber === '') {
                        continue;
                    }

                    $buffer[] = [
                        'periode' => $period,
                        'account_number' => $accountNumber,
                        'loan_balance' => (float) ($row->loan_balance ?? 0),
                        'quality_bucket' => trim((string) ($row->quality_bucket ?? 'L')) ?: 'L',
                        'segmen_dashboard' => trim((string) ($row->segmen_dashboard ?? '')),
                        'produk_dashboard' => trim((string) ($row->produk_dashboard ?? '')),
                        'cabang1' => trim((string) ($row->cabang1 ?? '')),
                        'unit1' => trim((string) ($row->unit1 ?? '')),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    if (count($buffer) >= 1000) {
                        DB::table(self::DASHBOARD_SNAPSHOT_TABLE)->insert($buffer);
                        $inserted += count($buffer);
                        $buffer = [];
                    }
                }
            });

        if (!empty($buffer)) {
            DB::table(self::DASHBOARD_SNAPSHOT_TABLE)->insert($buffer);
            $inserted += count($buffer);
        }

        return $inserted;
    }

    private function buildRasioPeriodSnapshot(string $loanPeriod, bool $force): int
    {
        if (!$force && DB::table(self::RASIO_SNAPSHOT_TABLE)->where('loan_period', $loanPeriod)->exists()) {
            return (int) DB::table(self::RASIO_SNAPSHOT_TABLE)->where('loan_period', $loanPeriod)->count();
        }

        $snapshot = $this->computeRasioSummary($loanPeriod);

        DB::table(self::RASIO_SNAPSHOT_TABLE)->where('loan_period', $loanPeriod)->delete();

        $rows = [];
        foreach (($snapshot['branch_labels'] ?? []) as $branchKey => $branchLabel) {
            foreach (self::SEGMENTS as $segmentKey) {
                $rows[] = [
                    'loan_period' => $loanPeriod,
                    'casa_period' => $snapshot['casa_date'],
                    'branch_key' => $branchKey,
                    'branch_label' => $branchLabel,
                    'segment_key' => $segmentKey,
                    'os_amount' => (float) ($snapshot['os'][$branchKey][$segmentKey] ?? 0),
                    'casa_amount' => (float) ($snapshot['casa'][$branchKey][$segmentKey] ?? 0),
                    'source_row_count' => (int) ($snapshot['row_count'] ?? 0),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        if (!empty($rows)) {
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table(self::RASIO_SNAPSHOT_TABLE)->insert($chunk);
            }
        }

        return count($rows);
    }

    private function buildDormantPeriodSnapshot(string $period, bool $force): int
    {
        if (!$force && DB::table(self::DORMANT_SNAPSHOT_TABLE)->where('posisi', $period)->exists()) {
            return (int) DB::table(self::DORMANT_SNAPSHOT_TABLE)->where('posisi', $period)->count();
        }

        DB::table(self::DORMANT_SNAPSHOT_TABLE)->where('posisi', $period)->delete();

        $baseQuery = DB::table('simpanan_multipn')
            ->where('posisi', $period)
            ->where('status', '9')
            ->whereNotNull('kantor_cabang')
            ->where('kantor_cabang', '<>', '')
            ->selectRaw("
                kantor_cabang as raw_branch,
                COALESCE(NULLIF(TRIM(unit_kerja), ''), '') as normalized_unit
            ");

        $rows = DB::query()
            ->fromSub($baseQuery, 'dormant_base')
            ->selectRaw('raw_branch')
            ->selectRaw('normalized_unit as unit_kerja')
            ->selectRaw('COUNT(*) as dormant_count')
            ->groupBy('raw_branch', 'normalized_unit')
            ->get();

        $buffer = [];
        foreach ($rows as $row) {
            $rawBranch = trim((string) ($row->raw_branch ?? ''));
            $branchLabel = $this->mapDormantBranchLabel($rawBranch);

            if ($rawBranch === '' || $branchLabel === null) {
                continue;
            }

            $buffer[] = [
                'posisi' => $period,
                'branch_label' => $branchLabel,
                'raw_branch' => $rawBranch,
                'unit_kerja' => trim((string) ($row->unit_kerja ?? '')),
                'dormant_count' => (int) ($row->dormant_count ?? 0),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (!empty($buffer)) {
            foreach (array_chunk($buffer, 500) as $chunk) {
                DB::table(self::DORMANT_SNAPSHOT_TABLE)->insert($chunk);
            }
        }

        return count($buffer);
    }

    private function computeRasioSummary(string $loanPeriod): array
    {
        $loanKeyColumn = $this->resolveExistingColumn('daily_loan_dinamis', ['nocif', 'cifno', 'CIFNO'], 'cifno');
        $casaKeyColumn = $this->resolveExistingColumn('simpanan_multipn', ['nocif', 'cifno', 'CIFNO'], 'CIFNO');
        $loanBranchColumn = $this->resolveExistingColumn('daily_loan_dinamis', ['cabang1', 'cabang'], 'cabang1');
        $loanSegmentColumn = $this->resolveExistingColumn('daily_loan_dinamis', ['segmen_dashboard'], 'segmen_dashboard');
        $loanProductColumn = $this->resolveExistingColumn('daily_loan_dinamis', ['produk_dashboard'], 'produk_dashboard');
        $loanBalanceColumn = $this->resolveExistingColumn('daily_loan_dinamis', ['baki_debet1', 'baki_debet'], 'baki_debet1');
        $casaDate = $this->resolveAvailableCasaPeriod($loanPeriod);

        $loanBranchSql = $this->buildLoanBranchExpression($loanBranchColumn);
        $loanIdentitySql = "TRIM({$loanKeyColumn})";
        $brigunaFlagSql = $this->buildSegmentFlagExpression($loanSegmentColumn, $loanProductColumn, 'briguna');
        $kprFlagSql = $this->buildSegmentFlagExpression($loanSegmentColumn, $loanProductColumn, 'kpr');
        $mikroFlagSql = $this->buildSegmentFlagExpression($loanSegmentColumn, $loanProductColumn, 'mikro');
        $smcFlagSql = $this->buildSegmentFlagExpression($loanSegmentColumn, $loanProductColumn, 'smc');

        $loanBase = DB::table('daily_loan_dinamis')
            ->where('periode', $loanPeriod)
            ->whereNotNull($loanKeyColumn)
            ->where($loanKeyColumn, '<>', '')
            ->selectRaw("
                {$loanBranchSql} as branch_key,
                {$loanIdentitySql} as identity_key,
                COALESCE({$loanBalanceColumn}, 0) as loan_balance,
                1 as has_total,
                {$brigunaFlagSql} as has_briguna,
                {$kprFlagSql} as has_kpr,
                {$mikroFlagSql} as has_mikro,
                {$smcFlagSql} as has_smc
            ");

        $loanRows = DB::query()
            ->fromSub($loanBase, 'loan_base')
            ->selectRaw("
                branch_key,
                identity_key,
                SUM(loan_balance) as total_os,
                SUM(CASE WHEN has_briguna = 1 THEN loan_balance ELSE 0 END) as briguna_os,
                SUM(CASE WHEN has_kpr = 1 THEN loan_balance ELSE 0 END) as kpr_os,
                SUM(CASE WHEN has_mikro = 1 THEN loan_balance ELSE 0 END) as mikro_os,
                SUM(CASE WHEN has_smc = 1 THEN loan_balance ELSE 0 END) as smc_os,
                MAX(has_total) as has_total,
                MAX(has_briguna) as has_briguna,
                MAX(has_kpr) as has_kpr,
                MAX(has_mikro) as has_mikro,
                MAX(has_smc) as has_smc
            ")
            ->groupBy('branch_key', 'identity_key')
            ->orderByRaw($this->buildBranchSortExpression('branch_key'))
            ->get();

        $snapshot = [
            'loan_date' => $loanPeriod,
            'casa_date' => $casaDate,
            'row_count' => 0,
            'branch_labels' => [],
            'os' => [],
            'casa' => [],
        ];

        $identityMappings = [];
        $identityVariants = [];

        foreach ($loanRows as $row) {
            $branchKey = $this->normalizePriorityBranchKey($row->branch_key ?? null);
            $identityKey = $this->normalizeIdentityKey($row->identity_key ?? null);

            if ($branchKey === '' || $identityKey === '') {
                continue;
            }

            $snapshot['row_count']++;
            $snapshot['branch_labels'][$branchKey] = $this->formatPriorityBranchLabel($branchKey);
            $snapshot['os'][$branchKey] ??= array_fill_keys(self::SEGMENTS, 0);
            $snapshot['casa'][$branchKey] ??= array_fill_keys(self::SEGMENTS, 0);

            $snapshot['os'][$branchKey]['total'] += (float) ($row->total_os ?? 0);
            $snapshot['os'][$branchKey]['briguna'] += (float) ($row->briguna_os ?? 0);
            $snapshot['os'][$branchKey]['kpr'] += (float) ($row->kpr_os ?? 0);
            $snapshot['os'][$branchKey]['mikro'] += (float) ($row->mikro_os ?? 0);
            $snapshot['os'][$branchKey]['smc'] += (float) ($row->smc_os ?? 0);

            $identityMappings[$identityKey][$branchKey] = [
                'total' => ((int) ($row->has_total ?? 0)) === 1,
                'briguna' => ((int) ($row->has_briguna ?? 0)) === 1,
                'kpr' => ((int) ($row->has_kpr ?? 0)) === 1,
                'mikro' => ((int) ($row->has_mikro ?? 0)) === 1,
                'smc' => ((int) ($row->has_smc ?? 0)) === 1,
            ];

            foreach ($this->buildIdentityVariants($identityKey) as $variant) {
                $identityVariants[$variant] = true;
            }
        }

        if ($casaDate && !empty($identityVariants)) {
            $applyCasaTypeFilter = $this->shouldApplyCasaTypeFilter($casaDate);
            $casaBalances = [];

            foreach (array_chunk(array_keys($identityVariants), 2000) as $chunk) {
                $casaQuery = DB::table('simpanan_multipn')
                    ->where('posisi', $casaDate)
                    ->whereIn($casaKeyColumn, $chunk);

                if ($applyCasaTypeFilter) {
                    $casaQuery->where(function ($query) {
                        $query->where('jenis_simpanan', 'like', 'GIRO%')
                            ->orWhere('jenis_simpanan', 'like', 'TABUNGAN%');
                    });
                }

                foreach ($casaQuery
                    ->selectRaw("{$casaKeyColumn} as identity_key, SUM(COALESCE(saldo_idr, 0)) as casa_balance")
                    ->groupBy($casaKeyColumn)
                    ->get() as $row
                ) {
                    $identityKey = $this->normalizeIdentityKey($row->identity_key ?? null);

                    if ($identityKey !== '') {
                        $casaBalances[$identityKey] = ($casaBalances[$identityKey] ?? 0) + (float) ($row->casa_balance ?? 0);
                    }
                }
            }

            foreach ($casaBalances as $identityKey => $balance) {
                foreach (($identityMappings[$identityKey] ?? []) as $branchKey => $flags) {
                    foreach ($flags as $segmentKey => $enabled) {
                        if ($enabled) {
                            $snapshot['casa'][$branchKey][$segmentKey] += $balance;
                        }
                    }
                }
            }
        }

        foreach (self::PRIORITY_BRANCHES as $branchKey) {
            $snapshot['branch_labels'][$branchKey] ??= $this->formatPriorityBranchLabel($branchKey);
            $snapshot['os'][$branchKey] ??= array_fill_keys(self::SEGMENTS, 0);
            $snapshot['casa'][$branchKey] ??= array_fill_keys(self::SEGMENTS, 0);
        }

        return $snapshot;
    }

    private function resolveDashboardPeriods(?string $period): array
    {
        $selected = $this->resolveAvailablePeriod('daily_loan_dinamis', 'periode', $period);

        if (!$selected) {
            return [];
        }

        if ($period) {
            return [$selected];
        }

        $comparison = $this->resolveAvailablePeriod(
            'daily_loan_dinamis',
            'periode',
            Carbon::parse($selected)->subMonthNoOverflow()->endOfMonth()->toDateString()
        );

        return array_values(array_unique(array_filter([$selected, $comparison])));
    }

    private function resolveRasioPeriods(?string $period): array
    {
        return $this->resolveDashboardPeriods($period);
    }

    private function resolveDormantPeriods(?string $period): array
    {
        $selected = $this->resolveAvailablePeriod('simpanan_multipn', 'posisi', $period);

        if (!$selected) {
            return [];
        }

        if ($period) {
            return [$selected];
        }

        $currentDate = Carbon::parse($selected);

        $mtd = $this->resolveAvailablePeriod(
            'simpanan_multipn',
            'posisi',
            $currentDate->copy()->subMonthNoOverflow()->endOfMonth()->toDateString()
        );
        $ytd = $this->resolveAvailablePeriod(
            'simpanan_multipn',
            'posisi',
            $currentDate->copy()->subYearNoOverflow()->endOfYear()->toDateString()
        );
        $yoy = $this->resolveAvailablePeriod(
            'simpanan_multipn',
            'posisi',
            $currentDate->copy()->subYearNoOverflow()->endOfMonth()->toDateString()
        );

        return array_values(array_unique(array_filter([$selected, $mtd, $ytd, $yoy])));
    }

    private function resolveAvailablePeriod(string $table, string $column, ?string $targetDate): ?string
    {
        try {
            $query = DB::table($table);

            if ($targetDate) {
                $query->where($column, '<=', Carbon::parse($targetDate)->toDateString());
            }

            return $query->max($column);
        } catch (Throwable) {
            return null;
        }
    }

    private function resolveAvailableCasaPeriod(string $targetDate): ?string
    {
        try {
            return DB::table('simpanan_multipn')
                ->where('posisi', '<=', $targetDate)
                ->max('posisi');
        } catch (Throwable) {
            return null;
        }
    }

    private function shouldApplyCasaTypeFilter(string $casaDate): bool
    {
        try {
            return DB::table('simpanan_multipn')
                ->where('posisi', $casaDate)
                ->where(function ($query) {
                    $query->where('jenis_simpanan', 'like', 'GIRO%')
                        ->orWhere('jenis_simpanan', 'like', 'TABUNGAN%');
                })
                ->exists();
        } catch (Throwable) {
            return false;
        }
    }

    private function buildDashboardBucketExpression(): string
    {
        $rawQualityExpression = "
            CASE
                WHEN TRIM(COALESCE(kolek_detail, '')) = '' OR TRIM(COALESCE(kolek_detail, '')) = '0' THEN
                    CASE
                        WHEN COALESCE(umur_tunggakan, 0) <= 0 THEN 'L'
                        WHEN COALESCE(umur_tunggakan, 0) <= 30 THEN 'DPK 1'
                        WHEN COALESCE(umur_tunggakan, 0) <= 60 THEN 'DPK 2'
                        WHEN COALESCE(umur_tunggakan, 0) <= 90 THEN 'DPK 3'
                        WHEN COALESCE(umur_tunggakan, 0) <= 120 THEN 'KL'
                        WHEN COALESCE(umur_tunggakan, 0) <= 150 THEN 'D1'
                        WHEN COALESCE(umur_tunggakan, 0) <= 180 THEN 'D2'
                        ELSE 'M'
                    END
                ELSE UPPER(TRIM(COALESCE(kolek_detail, '')))
            END
        ";

        return "
            CASE
                WHEN ({$rawQualityExpression}) = 'L' AND UPPER(COALESCE(flag_restruk, '')) = 'Y' THEN 'LR'
                WHEN ({$rawQualityExpression}) = 'L' THEN 'L'
                WHEN ({$rawQualityExpression}) IN ('DPK 1', 'SML1') THEN 'SML1'
                WHEN ({$rawQualityExpression}) IN ('DPK 2', 'SML2') THEN 'SML2'
                WHEN ({$rawQualityExpression}) IN ('DPK 3', 'SML3') THEN 'SML3'
                WHEN ({$rawQualityExpression}) IN ('KL', 'D1', 'D2', 'M', 'NPL') THEN 'NPL'
                WHEN ({$rawQualityExpression}) = 'PH' THEN 'PH'
                WHEN ({$rawQualityExpression}) = 'PAY' THEN 'Pay'
                ELSE 'L'
            END
        ";
    }

    private function buildLoanBranchExpression(string $loanBranchColumn): string
    {
        return "
            CASE
                WHEN UPPER(TRIM(COALESCE({$loanBranchColumn}, ''))) LIKE '%MADIUN%' THEN 'MADIUN'
                WHEN UPPER(TRIM(COALESCE({$loanBranchColumn}, ''))) LIKE '%MAGETAN%' THEN 'MAGETAN'
                WHEN UPPER(TRIM(COALESCE({$loanBranchColumn}, ''))) LIKE '%NGAWI%' THEN 'NGAWI'
                WHEN UPPER(TRIM(COALESCE({$loanBranchColumn}, ''))) LIKE '%PONOROGO%' THEN 'PONOROGO'
                ELSE ''
            END
        ";
    }

    private function buildBranchSortExpression(string $column): string
    {
        return "
            CASE {$column}
                WHEN 'MADIUN' THEN 1
                WHEN 'MAGETAN' THEN 2
                WHEN 'NGAWI' THEN 3
                WHEN 'PONOROGO' THEN 4
                ELSE 99
            END
        ";
    }

    private function buildSegmentFlagExpression(string $segmentColumn, string $productColumn, string $bucket): string
    {
        $segmen = "LOWER(TRIM(COALESCE({$segmentColumn}, '')))";
        $produk = "LOWER(TRIM(COALESCE({$productColumn}, '')))";

        return match ($bucket) {
            'briguna' => "
                CASE
                    WHEN {$segmen} LIKE '%consumer%' AND {$produk} LIKE '%briguna%' THEN 1
                    ELSE 0
                END
            ",
            'kpr' => "
                CASE
                    WHEN {$segmen} LIKE '%consumer%' AND {$produk} LIKE '%kpr%' THEN 1
                    ELSE 0
                END
            ",
            'mikro' => "
                CASE
                    WHEN {$segmen} LIKE '%micro%' OR {$segmen} LIKE '%mikro%' OR {$segmen} LIKE '%umkm%' THEN 1
                    ELSE 0
                END
            ",
            'smc' => "
                CASE
                    WHEN {$segmen} LIKE '%small%' OR {$segmen} LIKE '%smc%' OR {$segmen} LIKE '%menengah%' THEN 1
                    ELSE 0
                END
            ",
            default => '0',
        };
    }

    private function resolveExistingColumn(string $table, array $candidates, string $fallback): string
    {
        $columns = Schema::getColumnListing($table);
        $map = [];

        foreach ($columns as $column) {
            $map[strtolower($column)] = $column;
        }

        foreach ($candidates as $candidate) {
            $matched = $map[strtolower($candidate)] ?? null;
            if ($matched) {
                return $matched;
            }
        }

        return $fallback;
    }

    private function normalizePriorityBranchKey(?string $branch): string
    {
        $value = strtoupper(trim((string) $branch));
        if ($value === '') {
            return '';
        }

        $value = preg_replace('/^KC[\.\s-]*/', '', $value);
        $value = preg_replace('/\s+/', ' ', $value);

        foreach (self::PRIORITY_BRANCHES as $branchName) {
            if (str_contains($value, $branchName)) {
                return $branchName;
            }
        }

        return '';
    }

    private function normalizeIdentityKey($value): string
    {
        $normalized = strtoupper(trim((string) $value));
        $normalized = ltrim($normalized, "'");

        return trim($normalized);
    }

    private function buildIdentityVariants(string $identityKey): array
    {
        $variants = [$identityKey];
        if (!str_starts_with($identityKey, "'")) {
            $variants[] = "'" . $identityKey;
        }

        return array_values(array_unique($variants));
    }

    private function formatPriorityBranchLabel(string $branchKey): string
    {
        return 'KC ' . $this->normalizePriorityBranchKey($branchKey);
    }

    private function mapDormantBranchLabel(string $rawBranch): ?string
    {
        $upperBranch = strtoupper(trim($rawBranch));

        foreach (self::BRANCH_PATTERNS as $label => $needle) {
            if ($needle !== '' && str_contains($upperBranch, $needle)) {
                return $label;
            }
        }

        return null;
    }
}
