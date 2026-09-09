<?php

namespace App\Support;

use App\Jobs\WarmLandingLoanRiskCacheJob;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LandingLoanRiskCacheService
{
    private const SOURCE_TABLE = 'daily_loan_dinamis';

    private const CACHE_VERSION = 'v1';

    private const GENERATION_KEY = 'dashboard_simpanan:loan_risk:generation:v1';

    private const AREA_6_BRANCHES = ['KC Madiun', 'KC Magetan', 'KC Ngawi', 'KC Ponorogo'];

    /** @var array<int, string> */
    private const REQUIRED_COLUMNS = [
        'periode',
        'cabang1',
        'unit1',
        'status_rekening1',
        'baki_debet1',
        'kolek',
        'umur_tunggakan',
        'tunggakan_pokok',
        'tunggakan_bunga',
    ];

    public function generation(): int
    {
        return max(1, (int) Cache::get(self::GENERATION_KEY, 1));
    }

    /**
     * Read-only path for HTTP requests. A missing current-version cache never
     * falls through to the multi-million-row source query.
     *
     * @return array<string, mixed>
     */
    public function snapshot(?string $period): array
    {
        $period = StrictDateParser::normalize($period);
        if ($period === null) {
            return $this->emptyPayload(null, false);
        }

        $current = Cache::get($this->currentCacheKey($period));
        if ($this->isPayload($current, $period)) {
            return array_replace($current, ['refresh_pending' => false]);
        }

        $stable = Cache::get($this->stableCacheKey($period));
        $this->dispatchRefresh($period);

        if ($this->isPayload($stable, $period)) {
            return array_replace($stable, ['refresh_pending' => true]);
        }

        return $this->emptyPayload($period, true);
    }

    /** @return array<string, mixed> */
    public function warm(string $period): array
    {
        $period = StrictDateParser::normalize($period);
        if ($period === null) {
            return $this->emptyPayload(null, false);
        }

        $current = Cache::get($this->currentCacheKey($period));
        if ($this->isPayload($current, $period)) {
            return $current;
        }

        return $this->rebuild($period);
    }

    /**
     * Worker-only materialization. Production uses two source scans: one per
     * unit and one branch rollup (including an exact Area 6 total).
     *
     * @return array<string, mixed>
     */
    public function rebuild(string $period): array
    {
        $period = StrictDateParser::normalize($period);
        if ($period === null || ! $this->sourceSchemaIsReady()) {
            return $this->emptyPayload($period, false);
        }

        $accountColumn = Schema::hasColumn(self::SOURCE_TABLE, 'nomor_rekening1')
            ? 'nomor_rekening1'
            : null;
        $penaltyColumn = Schema::hasColumn(self::SOURCE_TABLE, 'tunggakan_penalti')
            ? 'tunggakan_penalti'
            : (Schema::hasColumn(self::SOURCE_TABLE, 'tunggakan_pinalti') ? 'tunggakan_pinalti' : null);

        $unitRows = $this->aggregateQuery($period, $accountColumn, $penaltyColumn)
            ->addSelect(['cabang1', 'unit1'])
            ->groupBy(['cabang1', 'unit1'])
            ->get()
            ->map(fn ($row): array => $this->mapAggregateRow($row))
            ->filter(fn (array $row): bool => $this->rowHasMetrics($row))
            ->values()
            ->all();

        [$branchTotals, $areaTotals] = $this->loadTotals($period, $accountColumn, $penaltyColumn);

        $payload = [
            'available' => true,
            'period' => $period,
            'generated_at' => now()->toIso8601String(),
            'rows' => $unitRows,
            'branch_totals' => $branchTotals,
            'area_totals' => $areaTotals,
            'refresh_pending' => false,
        ];

        Cache::put($this->currentCacheKey($period), $payload, now()->addHours(24));
        Cache::put($this->stableCacheKey($period), $payload, now()->addDays(7));
        Cache::add(self::GENERATION_KEY, 1, now()->addYears(2));
        Cache::increment(self::GENERATION_KEY);

        return $payload;
    }

    private function sourceSchemaIsReady(): bool
    {
        return Schema::hasTable(self::SOURCE_TABLE)
            && Schema::hasColumns(self::SOURCE_TABLE, self::REQUIRED_COLUMNS);
    }

    private function aggregateQuery(string $period, ?string $accountColumn, ?string $penaltyColumn): Builder
    {
        $actualKolek = 'CAST(kolek AS UNSIGNED)';
        $age = 'CAST(umur_tunggakan AS SIGNED)';
        $expectedKolek = "CASE
            WHEN {$age} <= 0 THEN 1
            WHEN {$age} <= 90 THEN 2
            WHEN {$age} <= 120 THEN 3
            WHEN {$age} <= 180 THEN 4
            ELSE 5
        END";
        $ktsCondition = "status_rekening1 IN ('1', '3')
            AND COALESCE(baki_debet1, 0) > 0
            AND kolek IN ('1', '2', '3', '4', '5')
            AND umur_tunggakan IS NOT NULL
            AND {$actualKolek} <> {$expectedKolek}";

        $arrearsAmount = 'COALESCE(tunggakan_pokok, 0) + COALESCE(tunggakan_bunga, 0)';
        if ($penaltyColumn !== null) {
            $arrearsAmount .= " + COALESCE({$penaltyColumn}, 0)";
        }
        $smallArrearsCondition = "({$arrearsAmount}) > 0 AND ({$arrearsAmount}) <= 100000";
        $smallArrearsCount = $accountColumn !== null
            ? "COUNT(DISTINCT CASE WHEN {$smallArrearsCondition} THEN {$accountColumn} END)"
            : "SUM(CASE WHEN {$smallArrearsCondition} THEN 1 ELSE 0 END)";

        return DB::table(self::SOURCE_TABLE)
            ->where('periode', $period)
            ->whereIn('cabang1', self::AREA_6_BRANCHES)
            ->selectRaw("SUM(CASE WHEN {$ktsCondition} THEN 1 ELSE 0 END) AS kts_count")
            ->selectRaw("COALESCE(SUM(CASE WHEN {$ktsCondition} THEN COALESCE(baki_debet1, 0) ELSE 0 END), 0) AS kts_os")
            ->selectRaw("{$smallArrearsCount} AS small_arrears_count")
            ->selectRaw("COALESCE(SUM(CASE WHEN {$smallArrearsCondition} THEN ({$arrearsAmount}) ELSE 0 END), 0) AS small_arrears_amount");
    }

    /**
     * @return array{0: array<string, array<string, int|float|string>>, 1: array<string, int|float>}
     */
    private function loadTotals(string $period, ?string $accountColumn, ?string $penaltyColumn): array
    {
        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $rows = $this->aggregateQuery($period, $accountColumn, $penaltyColumn)
                ->addSelect('cabang1')
                ->groupByRaw('cabang1 WITH ROLLUP')
                ->get();

            $areaRow = $rows->first(fn ($row): bool => $row->cabang1 === null);
            $branchRows = $rows->reject(fn ($row): bool => $row->cabang1 === null);
        } else {
            $branchRows = $this->aggregateQuery($period, $accountColumn, $penaltyColumn)
                ->addSelect('cabang1')
                ->groupBy('cabang1')
                ->get();
            $areaRow = $this->aggregateQuery($period, $accountColumn, $penaltyColumn)->first();
        }

        $branchTotals = $branchRows
            ->mapWithKeys(function ($row): array {
                $mapped = $this->mapAggregateRow($row);

                return [$this->normalizeBranch((string) $mapped['branch']) => $mapped];
            })
            ->all();

        $areaTotals = $areaRow !== null
            ? $this->metricsFromRow($areaRow)
            : $this->emptyMetrics();

        return [$branchTotals, $areaTotals];
    }

    /** @return array<string, int|float|string> */
    private function mapAggregateRow(object $row): array
    {
        return [
            'branch' => trim((string) ($row->cabang1 ?? '')),
            'unit' => trim((string) ($row->unit1 ?? '')),
        ] + $this->metricsFromRow($row);
    }

    /** @return array{kts_count: int, kts_os: float, small_arrears_count: int, small_arrears_amount: float} */
    private function metricsFromRow(object $row): array
    {
        return [
            'kts_count' => (int) ($row->kts_count ?? 0),
            'kts_os' => (float) ($row->kts_os ?? 0),
            'small_arrears_count' => (int) ($row->small_arrears_count ?? 0),
            'small_arrears_amount' => (float) ($row->small_arrears_amount ?? 0),
        ];
    }

    /** @param array<string, int|float|string> $row */
    private function rowHasMetrics(array $row): bool
    {
        return (int) ($row['kts_count'] ?? 0) > 0
            || (int) ($row['small_arrears_count'] ?? 0) > 0
            || (float) ($row['small_arrears_amount'] ?? 0) > 0;
    }

    private function dispatchRefresh(string $period): void
    {
        if (config('queue.default') === 'sync') {
            return;
        }

        $throttleKey = 'dashboard_simpanan:loan_risk:dispatch:'.self::CACHE_VERSION.':'.$period;
        if (! Cache::add($throttleKey, true, now()->addMinute())) {
            return;
        }

        WarmLandingLoanRiskCacheJob::dispatch($period);
    }

    private function currentCacheKey(string $period): string
    {
        return 'dashboard_simpanan:loan_risk:'.self::CACHE_VERSION
            .':'.$period.':report-v'.ReportCacheVersion::get('pinjaman');
    }

    private function stableCacheKey(string $period): string
    {
        return 'dashboard_simpanan:loan_risk:'.self::CACHE_VERSION.':'.$period.':stable';
    }

    /** @param mixed $payload */
    private function isPayload($payload, string $period): bool
    {
        return is_array($payload)
            && ($payload['period'] ?? null) === $period
            && isset($payload['rows'], $payload['branch_totals'], $payload['area_totals']);
    }

    /** @return array<string, mixed> */
    private function emptyPayload(?string $period, bool $refreshPending): array
    {
        return [
            'available' => false,
            'period' => $period,
            'generated_at' => null,
            'rows' => [],
            'branch_totals' => [],
            'area_totals' => $this->emptyMetrics(),
            'refresh_pending' => $refreshPending,
        ];
    }

    /** @return array{kts_count: int, kts_os: float, small_arrears_count: int, small_arrears_amount: float} */
    private function emptyMetrics(): array
    {
        return [
            'kts_count' => 0,
            'kts_os' => 0.0,
            'small_arrears_count' => 0,
            'small_arrears_amount' => 0.0,
        ];
    }

    private function normalizeBranch(string $branch): string
    {
        return strtoupper(trim($branch));
    }
}
