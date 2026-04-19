<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class DashboardHarianSnapshotService
{
    public const SNAPSHOT_TABLE = 'dashboard_harian_snapshots';
    private const LOAN_TABLE = 'ssa_pinjaman';
    private const SAVINGS_TABLE = 'ssa_simpanan';
    private const METRIC_COLUMNS = [
        'ph_tupok',
        'ph_lunas',
        'rec_dh_total',
        'rec_dh_small',
        'rec_dh_consumer',
        'rec_dh_micro',
        'total_simpanan',
        'simpanan_ritel',
        'giro_ritel',
        'deposito_ritel',
        'tabungan_ritel',
        'simpanan_mikro',
        'giro_mikro',
        'deposito_mikro',
        'tabungan_mikro',
        'simpanan_wholesale',
        'giro_wholesale',
        'deposito_wholesale',
        'tabungan_wholesale',
        'total_casa',
        'casa_ritel',
        'casa_mikro',
        'total_os',
        'total_os_non_commercial',
        'commercial_os',
        'sme_os',
        'kecil_os',
        'kecil_non_cashcoll_os',
        'cashcoll_os',
        'medium_os',
        'consumer_os',
        'briguna_konsumer_os',
        'kpr_os',
        'kkb_os',
        'micro_os',
        'briguna_mikro_os',
        'kupedes_os',
        'kur_mikro_os',
        'kur_kecil_os',
        'kur_kpp_os',
        'total_sml_abs_non_commercial',
        'commercial_sml',
        'sme_sml',
        'kecil_sml',
        'kecil_non_cashcoll_sml',
        'cashcoll_sml',
        'medium_sml',
        'consumer_sml',
        'briguna_konsumer_sml',
        'kpr_sml',
        'kkb_sml',
        'micro_sml',
        'briguna_mikro_sml',
        'kupedes_sml',
        'kur_mikro_sml',
        'kur_kecil_sml',
        'kur_kpp_sml',
        'total_npl_abs_non_commercial',
        'commercial_npl',
        'sme_npl',
        'kecil_npl',
        'kecil_non_cashcoll_npl',
        'cashcoll_npl',
        'medium_npl',
        'consumer_npl',
        'briguna_konsumer_npl',
        'kpr_npl',
        'kkb_npl',
        'micro_npl',
        'briguna_mikro_npl',
        'kupedes_npl',
        'kur_mikro_npl',
        'kur_kecil_npl',
        'kur_kpp_npl',
    ];
    private const ROW_DEFINITIONS = [
        ['key' => 'total_simpanan', 'label' => '1. Simpanan', 'type' => 'currency', 'depth' => 0, 'accent' => 'strong'],
        ['key' => 'simpanan_ritel', 'label' => 'A. Ritel', 'type' => 'currency', 'depth' => 1, 'accent' => 'section'],
        ['key' => 'giro_ritel', 'label' => 'Giro', 'type' => 'currency', 'depth' => 2, 'accent' => 'default'],
        ['key' => 'deposito_ritel', 'label' => 'Deposito', 'type' => 'currency', 'depth' => 2, 'accent' => 'default'],
        ['key' => 'tabungan_ritel', 'label' => 'Tabungan', 'type' => 'currency', 'depth' => 2, 'accent' => 'default'],
        ['key' => 'simpanan_mikro', 'label' => 'B. Mikro', 'type' => 'currency', 'depth' => 1, 'accent' => 'section'],
        ['key' => 'giro_mikro', 'label' => 'Giro', 'type' => 'currency', 'depth' => 2, 'accent' => 'default'],
        ['key' => 'deposito_mikro', 'label' => 'Deposito', 'type' => 'currency', 'depth' => 2, 'accent' => 'default'],
        ['key' => 'tabungan_mikro', 'label' => 'Tabungan', 'type' => 'currency', 'depth' => 2, 'accent' => 'default'],
        ['key' => 'simpanan_wholesale', 'label' => 'C. Wholesale', 'type' => 'currency', 'depth' => 1, 'accent' => 'section'],
        ['key' => 'giro_wholesale', 'label' => 'Giro', 'type' => 'currency', 'depth' => 2, 'accent' => 'default'],
        ['key' => 'deposito_wholesale', 'label' => 'Deposito', 'type' => 'currency', 'depth' => 2, 'accent' => 'default'],
        ['key' => 'tabungan_wholesale', 'label' => 'Tabungan', 'type' => 'currency', 'depth' => 2, 'accent' => 'default'],
        ['key' => 'total_os', 'label' => '2. OS Total', 'type' => 'currency', 'depth' => 0, 'accent' => 'strong'],
        ['key' => 'total_os_non_commercial', 'label' => 'Total OS Non Commercial', 'type' => 'currency', 'depth' => 1, 'accent' => 'section'],
        ['key' => 'commercial_os', 'label' => 'A. Commercial', 'type' => 'currency', 'depth' => 1, 'accent' => 'default'],
        ['key' => 'sme_os', 'label' => 'B. SME', 'type' => 'currency', 'depth' => 1, 'accent' => 'section'],
        ['key' => 'kecil_os', 'label' => 'Kecil', 'type' => 'currency', 'depth' => 2, 'accent' => 'default'],
        ['key' => 'kecil_non_cashcoll_os', 'label' => 'Kecil Non Cashcoll', 'type' => 'currency', 'depth' => 3, 'accent' => 'muted'],
        ['key' => 'cashcoll_os', 'label' => 'Cashcoll', 'type' => 'currency', 'depth' => 3, 'accent' => 'muted'],
        ['key' => 'medium_os', 'label' => 'Medium', 'type' => 'currency', 'depth' => 2, 'accent' => 'default'],
        ['key' => 'consumer_os', 'label' => 'C. Konsumer', 'type' => 'currency', 'depth' => 1, 'accent' => 'section'],
        ['key' => 'briguna_konsumer_os', 'label' => 'Briguna', 'type' => 'currency', 'depth' => 2, 'accent' => 'default'],
        ['key' => 'kpr_os', 'label' => 'KPR', 'type' => 'currency', 'depth' => 2, 'accent' => 'default'],
        ['key' => 'kkb_os', 'label' => 'KKB', 'type' => 'currency', 'depth' => 2, 'accent' => 'default'],
        ['key' => 'micro_os', 'label' => 'D. Mikro', 'type' => 'currency', 'depth' => 1, 'accent' => 'section'],
        ['key' => 'briguna_mikro_os', 'label' => 'Briguna Mikro', 'type' => 'currency', 'depth' => 2, 'accent' => 'default'],
        ['key' => 'kupedes_os', 'label' => 'Kupedes', 'type' => 'currency', 'depth' => 2, 'accent' => 'default'],
        ['key' => 'kur_mikro_os', 'label' => 'KUR Mikro', 'type' => 'currency', 'depth' => 2, 'accent' => 'default'],
        ['key' => 'kur_kecil_os', 'label' => 'KUR Kecil', 'type' => 'currency', 'depth' => 2, 'accent' => 'default'],
        ['key' => 'kur_kpp_os', 'label' => 'KUR KPP', 'type' => 'currency', 'depth' => 2, 'accent' => 'default'],
        ['key' => 'total_sml_pct_non_commercial', 'label' => '3. Total SML (%) Non Commercial', 'type' => 'percent', 'depth' => 0, 'accent' => 'strong'],
        ['key' => 'total_sml_abs_non_commercial', 'label' => 'Total SML (ABS) Non Commercial', 'type' => 'currency', 'depth' => 1, 'accent' => 'section'],
        ['key' => 'commercial_sml', 'label' => 'A. Commercial', 'type' => 'currency', 'depth' => 1, 'accent' => 'default'],
        ['key' => 'sme_sml', 'label' => 'B. SME', 'type' => 'currency', 'depth' => 1, 'accent' => 'section'],
        ['key' => 'kecil_sml', 'label' => 'Kecil', 'type' => 'currency', 'depth' => 2, 'accent' => 'default'],
        ['key' => 'kecil_non_cashcoll_sml', 'label' => 'Kecil Non Cashcoll', 'type' => 'currency', 'depth' => 3, 'accent' => 'muted'],
        ['key' => 'cashcoll_sml', 'label' => 'Cashcoll', 'type' => 'currency', 'depth' => 3, 'accent' => 'muted'],
        ['key' => 'medium_sml', 'label' => 'Medium', 'type' => 'currency', 'depth' => 2, 'accent' => 'default'],
        ['key' => 'consumer_sml', 'label' => 'C. Konsumer', 'type' => 'currency', 'depth' => 1, 'accent' => 'section'],
        ['key' => 'briguna_konsumer_sml', 'label' => 'Briguna', 'type' => 'currency', 'depth' => 2, 'accent' => 'default'],
        ['key' => 'kpr_sml', 'label' => 'KPR', 'type' => 'currency', 'depth' => 2, 'accent' => 'default'],
        ['key' => 'kkb_sml', 'label' => 'KKB', 'type' => 'currency', 'depth' => 2, 'accent' => 'default'],
        ['key' => 'micro_sml', 'label' => 'D. Mikro', 'type' => 'currency', 'depth' => 1, 'accent' => 'section'],
        ['key' => 'briguna_mikro_sml', 'label' => 'Briguna Mikro', 'type' => 'currency', 'depth' => 2, 'accent' => 'default'],
        ['key' => 'kupedes_sml', 'label' => 'Kupedes', 'type' => 'currency', 'depth' => 2, 'accent' => 'default'],
        ['key' => 'kur_mikro_sml', 'label' => 'KUR Mikro', 'type' => 'currency', 'depth' => 2, 'accent' => 'default'],
        ['key' => 'kur_kecil_sml', 'label' => 'KUR Kecil', 'type' => 'currency', 'depth' => 2, 'accent' => 'default'],
        ['key' => 'kur_kpp_sml', 'label' => 'KUR KPP', 'type' => 'currency', 'depth' => 2, 'accent' => 'default'],
        ['key' => 'total_npl_pct_non_commercial', 'label' => '4. Total NPL (%) Non Commercial', 'type' => 'percent', 'depth' => 0, 'accent' => 'strong'],
        ['key' => 'total_npl_abs_non_commercial', 'label' => 'Total NPL (ABS) Non Commercial', 'type' => 'currency', 'depth' => 1, 'accent' => 'section'],
        ['key' => 'commercial_npl', 'label' => 'A. Commercial', 'type' => 'currency', 'depth' => 1, 'accent' => 'default'],
        ['key' => 'sme_npl', 'label' => 'B. SME', 'type' => 'currency', 'depth' => 1, 'accent' => 'section'],
        ['key' => 'kecil_npl', 'label' => 'Kecil', 'type' => 'currency', 'depth' => 2, 'accent' => 'default'],
        ['key' => 'kecil_non_cashcoll_npl', 'label' => 'Kecil Non Cashcoll', 'type' => 'currency', 'depth' => 3, 'accent' => 'muted'],
        ['key' => 'cashcoll_npl', 'label' => 'Cashcoll', 'type' => 'currency', 'depth' => 3, 'accent' => 'muted'],
        ['key' => 'medium_npl', 'label' => 'Medium', 'type' => 'currency', 'depth' => 2, 'accent' => 'default'],
        ['key' => 'consumer_npl', 'label' => 'C. Konsumer', 'type' => 'currency', 'depth' => 1, 'accent' => 'section'],
        ['key' => 'briguna_konsumer_npl', 'label' => 'Briguna', 'type' => 'currency', 'depth' => 2, 'accent' => 'default'],
        ['key' => 'kpr_npl', 'label' => 'KPR', 'type' => 'currency', 'depth' => 2, 'accent' => 'default'],
        ['key' => 'kkb_npl', 'label' => 'KKB', 'type' => 'currency', 'depth' => 2, 'accent' => 'default'],
        ['key' => 'micro_npl', 'label' => 'D. Mikro', 'type' => 'currency', 'depth' => 1, 'accent' => 'section'],
        ['key' => 'briguna_mikro_npl', 'label' => 'Briguna Mikro', 'type' => 'currency', 'depth' => 2, 'accent' => 'default'],
        ['key' => 'kupedes_npl', 'label' => 'Kupedes', 'type' => 'currency', 'depth' => 2, 'accent' => 'default'],
        ['key' => 'kur_mikro_npl', 'label' => 'KUR Mikro', 'type' => 'currency', 'depth' => 2, 'accent' => 'default'],
        ['key' => 'kur_kecil_npl', 'label' => 'KUR Kecil', 'type' => 'currency', 'depth' => 2, 'accent' => 'default'],
        ['key' => 'kur_kpp_npl', 'label' => 'KUR KPP', 'type' => 'currency', 'depth' => 2, 'accent' => 'default'],
        ['key' => 'casa_pct', 'label' => '5. %CASA', 'type' => 'percent', 'depth' => 0, 'accent' => 'strong'],
        ['key' => 'total_casa', 'label' => 'Total CASA', 'type' => 'currency', 'depth' => 1, 'accent' => 'default'],
        ['key' => 'casa_ritel', 'label' => 'CASA Ritel', 'type' => 'currency', 'depth' => 1, 'accent' => 'default'],
        ['key' => 'casa_mikro', 'label' => 'CASA Mikro', 'type' => 'currency', 'depth' => 1, 'accent' => 'default'],
        ['key' => 'ldr_non_commercial', 'label' => '6. LDR Non Commercial', 'type' => 'percent', 'depth' => 0, 'accent' => 'strong'],
        ['key' => 'ldr_ritel_non_commercial', 'label' => 'LDR Ritel Non Commercial', 'type' => 'percent', 'depth' => 1, 'accent' => 'default'],
        ['key' => 'ldr_mikro_non_commercial', 'label' => 'LDR Mikro Non Commercial', 'type' => 'percent', 'depth' => 1, 'accent' => 'default'],
        ['key' => 'rec_dh_total', 'label' => '7. Rec DH per Segmen', 'type' => 'currency', 'depth' => 0, 'accent' => 'strong'],
        ['key' => 'rec_dh_small', 'label' => 'Small', 'type' => 'currency', 'depth' => 1, 'accent' => 'section'],
        ['key' => 'rec_dh_consumer', 'label' => 'Consumer', 'type' => 'currency', 'depth' => 1, 'accent' => 'section'],
        ['key' => 'rec_dh_micro', 'label' => 'Micro', 'type' => 'currency', 'depth' => 1, 'accent' => 'section'],
    ];

    public function rebuild(?string $period = null, bool $force = false, ?callable $progress = null): array
    {
        $results = [];
        $periods = $this->resolveSharedPeriods($period);
        $totalPeriods = count($periods);

        foreach ($periods as $index => $snapshotPeriod) {
            $results[$snapshotPeriod] = $this->buildPeriodSnapshot($snapshotPeriod, $force);

            if ($progress !== null) {
                $progress([
                    'current_period' => $snapshotPeriod,
                    'completed_units' => $index + 1,
                    'total_units' => $totalPeriods,
                    'current_result_count' => (int) ($results[$snapshotPeriod] ?? 0),
                ]);
            }
        }

        if ($period === null) {
            $this->cleanupSnapshotOrphans($periods);
        }

        return $results;
    }

    public function describeRebuildPlan(?string $period = null): array
    {
        $periods = $this->resolveSharedPeriods($period);

        return [
            'periods' => $periods,
            'total_units' => count($periods),
        ];
    }

    public function resolveAffectedSnapshotPeriodsForPh(?string $phPeriod = null): array
    {
        $sharedPeriods = $this->resolveSharedPeriods();
        if ($sharedPeriods === []) {
            return [];
        }

        $normalizedPhPeriod = $this->normalizeDate($phPeriod);
        if ($normalizedPhPeriod === null) {
            return $sharedPeriods;
        }

        $affected = [];
        $sharedPeriodsAsc = $sharedPeriods;
        sort($sharedPeriodsAsc);

        if (in_array($normalizedPhPeriod, $sharedPeriodsAsc, true)) {
            $affected[] = $normalizedPhPeriod;
        }

        foreach ($sharedPeriodsAsc as $sharedPeriod) {
            if ($sharedPeriod > $normalizedPhPeriod) {
                $affected[] = $sharedPeriod;
                break;
            }
        }

        if ($affected === []) {
            return [];
        }

        rsort($affected);

        return array_values(array_unique($affected));
    }

    public function rebuildAffectedByPhPeriod(?string $phPeriod = null, bool $force = false): array
    {
        $results = [];

        foreach ($this->resolveAffectedSnapshotPeriodsForPh($phPeriod) as $snapshotPeriod) {
            $results[$snapshotPeriod] = $this->buildPeriodSnapshot($snapshotPeriod, $force);
        }

        return $results;
    }

    public function buildPeriodSnapshot(string $period, bool $force = false): int
    {
        if (!Schema::hasTable(self::SNAPSHOT_TABLE) || !Schema::hasTable(self::LOAN_TABLE) || !Schema::hasTable(self::SAVINGS_TABLE)) {
            return 0;
        }

        if (!$force) {
            $existingCount = (int) DB::table(self::SNAPSHOT_TABLE)->where('snapshot_period', $period)->count();
            if ($existingCount > 0) {
                return $existingCount;
            }
        }

        if (!$this->sourcePeriodExists(self::LOAN_TABLE, $period) || !$this->sourcePeriodExists(self::SAVINGS_TABLE, $period)) {
            DB::table(self::SNAPSHOT_TABLE)->where('snapshot_period', $period)->delete();

            return 0;
        }

        [$payload] = $this->buildAggregatedRowsForPeriod($period);

        if ($payload === []) {
            DB::table(self::SNAPSHOT_TABLE)->where('snapshot_period', $period)->delete();

            return 0;
        }

        foreach (array_chunk($payload, 250) as $chunk) {
            DB::table(self::SNAPSHOT_TABLE)->upsert(
                $chunk,
                ['snapshot_period', 'kanca_key', 'unit_key'],
                array_merge(['kanca_label', 'unit_label'], self::METRIC_COLUMNS, ['source_row_count', 'updated_at'])
            );
        }

        $validIds = array_column($payload, 'uniqueid_dhs');
        DB::table(self::SNAPSHOT_TABLE)
            ->where('snapshot_period', $period)
            ->whereNotIn('uniqueid_dhs', $validIds)
            ->delete();

        return count($payload);
    }

    public function fetchPeriods(): Collection
    {
        try {
            if (Schema::hasTable(self::SNAPSHOT_TABLE) && DB::table(self::SNAPSHOT_TABLE)->exists()) {
                return DB::table(self::SNAPSHOT_TABLE)
                    ->select('snapshot_period')
                    ->distinct()
                    ->orderByDesc('snapshot_period')
                    ->pluck('snapshot_period')
                    ->map(fn ($value) => Carbon::parse($value)->toDateString())
                    ->values();
            }
        } catch (Throwable) {
            // Fall through to source intersection.
        }

        return collect($this->resolveSharedPeriods());
    }

    public function resolveEffectivePeriod(?string $requestedPeriod): ?string
    {
        $targetDate = $this->normalizeDate($requestedPeriod);

        try {
            if (Schema::hasTable(self::SNAPSHOT_TABLE) && DB::table(self::SNAPSHOT_TABLE)->exists()) {
                $query = DB::table(self::SNAPSHOT_TABLE);

                if ($targetDate) {
                    $query->where('snapshot_period', '<=', $targetDate);
                }

                return $query->max('snapshot_period');
            }
        } catch (Throwable) {
            // Fall through to source lookup.
        }

        $periods = $this->resolveSharedPeriods($targetDate);

        return $periods[0] ?? null;
    }

    public function resolveEffectiveRkaPeriod(?string $requestedMonth, ?string $fallbackPeriod = null): ?string
    {
        $availableYears = $this->availableRkaYears();
        $fallbackYear = $fallbackPeriod ? (int) Carbon::parse($fallbackPeriod)->format('Y') : null;
        $normalizedMonth = $this->normalizeMonthValue($requestedMonth);
        $requestedYear = $normalizedMonth ? (int) substr($normalizedMonth, 0, 4) : null;
        $resolvedYear = $this->resolveRkaYear($requestedYear, $fallbackYear, $availableYears);

        if ($resolvedYear === null) {
            return $this->resolveEffectivePeriod($fallbackPeriod);
        }

        $resolvedMonth = $normalizedMonth
            ? (int) substr($normalizedMonth, 5, 2)
            : (int) Carbon::parse($fallbackPeriod ?? now())->format('m');

        return sprintf('%04d-%02d-01', $resolvedYear, $resolvedMonth);
    }

    public function resolveComparisonPeriods(string $selectedPeriod, ?string $rkaPeriod = null): array
    {
        $selected = Carbon::parse($selectedPeriod);
        $resolvedRka = $this->resolveEffectiveRkaPeriod($rkaPeriod ? substr($rkaPeriod, 0, 7) : null, $selectedPeriod);

        return [
            'current' => $selectedPeriod,
            'yoy' => $this->resolveEffectivePeriod($selected->copy()->subYearsNoOverflow(1)->endOfMonth()->toDateString()),
            'ytd' => $this->resolveEffectivePeriod($selected->copy()->subYearsNoOverflow(1)->endOfYear()->toDateString()),
            'm2' => $this->resolveEffectivePeriod($selected->copy()->subMonthsNoOverflow(2)->endOfMonth()->toDateString()),
            'mtm' => $this->resolveEffectivePeriod($selected->copy()->subMonthsNoOverflow(1)->toDateString()),
            'mtd' => $this->resolveEffectivePeriod($selected->copy()->subMonthsNoOverflow(1)->endOfMonth()->toDateString()),
            'h1' => $this->resolvePreviousPeriod($selectedPeriod),
            'h2' => $this->resolvePreviousNthPeriod($selectedPeriod, 2),
            'rka' => $resolvedRka,
            'rka_dec' => $resolvedRka ? Carbon::parse($resolvedRka)->month(12)->startOfMonth()->toDateString() : null,
        ];
    }

    public function fetchFilterOptions(?string $period = null, array|string|null $selectedKanca = null, array|string|null $selectedUnit = null): array
    {
        $effectivePeriod = $this->resolveEffectivePeriod($period);
        $periodOptions = $this->fetchPeriods()
            ->map(fn ($value) => [
                'value' => $value,
                'label' => $this->formatPeriodLabel($value),
            ])
            ->all();
        $monthOptions = $this->fetchMonthFilterOptions($effectivePeriod);

        if (!$effectivePeriod) {
            return [
                'kanca' => [['value' => 'all', 'label' => 'Semua Kanca']],
                'unit_kerja' => [['value' => 'all', 'label' => 'Semua Unit Kerja']],
                'posisi_terakhir' => $periodOptions,
                'posisi_rka' => $monthOptions,
            ];
        }

        $normalizedKanca = $this->normalizeFilterValues($selectedKanca);
        $normalizedUnit = $this->normalizeFilterValues($selectedUnit);
        $kancas = collect();
        $units = collect();

        try {
            if (Schema::hasTable(self::SAVINGS_TABLE)) {
                $rawPeriodCandidates = $this->sourcePeriodRawCandidates(self::SAVINGS_TABLE, $effectivePeriod);

                $kancas = DB::table(self::SAVINGS_TABLE . ' as ss')
                    ->whereIn('ss.Month_Day_Year_of_Posisi', $rawPeriodCandidates)
                    ->selectRaw("TRIM(COALESCE(ss.nama_cabang, '')) as value")
                    ->selectRaw("TRIM(COALESCE(ss.nama_cabang, '')) as label")
                    ->whereRaw("TRIM(COALESCE(ss.nama_cabang, '')) <> ''")
                    ->distinct()
                    ->orderBy('label')
                    ->get();

                $unitQuery = DB::table(self::SAVINGS_TABLE . ' as ss')
                    ->whereIn('ss.Month_Day_Year_of_Posisi', $rawPeriodCandidates)
                    ->selectRaw("TRIM(COALESCE(ss.nama_uker, '')) as value")
                    ->selectRaw("TRIM(COALESCE(ss.nama_uker, '')) as label")
                    ->selectRaw("TRIM(COALESCE(ss.nama_cabang, '')) as kanca_value")
                    ->whereRaw("TRIM(COALESCE(ss.nama_uker, '')) <> ''");

                if ($normalizedKanca !== []) {
                    $unitQuery->whereIn(DB::raw("TRIM(COALESCE(ss.nama_cabang, ''))"), $normalizedKanca);
                }

                $units = $unitQuery
                    ->distinct()
                    ->orderBy('label')
                    ->get();
            }
        } catch (Throwable) {
            $kancas = collect();
            $units = collect();
        }

        if ($kancas->isEmpty() || $units->isEmpty()) {
            [$payload] = $this->buildAggregatedRowsForPeriod($effectivePeriod);

            $kancas = collect($payload)
                ->map(fn (array $row) => ['value' => $row['kanca_key'], 'label' => $row['kanca_label']])
                ->unique('value')
                ->sortBy('label')
                ->values();

            $units = collect($payload)
                ->map(fn (array $row) => ['value' => $row['unit_key'], 'label' => $row['unit_label'], 'kanca_value' => $row['kanca_key']])
                ->unique(fn (array $row) => $row['kanca_value'] . '|' . $row['value'])
                ->sortBy('label')
                ->values();
        }

        $scopedUnits = $units
            ->filter(function ($row) use ($normalizedKanca) {
                if ($normalizedKanca === []) {
                    return true;
                }

                return in_array((string) data_get($row, 'kanca_value'), $normalizedKanca, true);
            })
            ->values();

        if (
            $normalizedUnit !== []
            && !$scopedUnits->contains(fn ($row) => in_array((string) data_get($row, 'value'), $normalizedUnit, true))
        ) {
            $normalizedUnit = [];
        }

        return [
            'kanca' => array_values(array_merge([['value' => 'all', 'label' => 'Semua Kanca']], $kancas->map(fn ($row) => (array) $row)->all())),
            'unit_kerja' => array_values(array_merge([['value' => 'all', 'label' => 'Semua Unit Kerja']], $scopedUnits->map(fn ($row) => (array) $row)->all())),
            'posisi_terakhir' => $periodOptions,
            'posisi_rka' => $monthOptions,
        ];
    }

    public function buildDashboardPayload(?string $selectedPeriod, ?string $rkaPeriod = null, array|string|null $kancaKey = null, array|string|null $unitKey = null): array
    {
        if (!$selectedPeriod) {
            return [
                'selected_period' => null,
                'selected_period_label' => 'Belum ada data',
                'selected_rka_period' => null,
                'selected_rka_label' => 'Belum ada data',
                'comparison_periods' => [],
                'rows' => [],
                'summary' => [
                    'source' => self::SNAPSHOT_TABLE,
                    'kanca_label' => 'Semua Kanca',
                    'unit_label' => 'Semua Unit Kerja',
                    'row_count' => 0,
                    'current_total_simpanan' => 0,
                    'current_total_os_non_commercial' => 0,
                    'current_casa_pct' => 0,
                ],
            ];
        }

        $comparisonPeriods = $this->resolveComparisonPeriods($selectedPeriod, $rkaPeriod);
        $periodKeys = array_values(array_unique(array_filter(array_values($comparisonPeriods))));
        $metricsByPeriod = $this->loadMetricsForPeriods($periodKeys, $kancaKey, $unitKey);

        $currentMetrics = $metricsByPeriod[$comparisonPeriods['current']] ?? $this->finalizeMetrics($this->emptyMetrics());
        $yoyMetrics = $comparisonPeriods['yoy'] ? ($metricsByPeriod[$comparisonPeriods['yoy']] ?? $this->finalizeMetrics($this->emptyMetrics())) : $this->finalizeMetrics($this->emptyMetrics());
        $ytdMetrics = $comparisonPeriods['ytd'] ? ($metricsByPeriod[$comparisonPeriods['ytd']] ?? $this->finalizeMetrics($this->emptyMetrics())) : $this->finalizeMetrics($this->emptyMetrics());
        $m2Metrics = $comparisonPeriods['m2'] ? ($metricsByPeriod[$comparisonPeriods['m2']] ?? $this->finalizeMetrics($this->emptyMetrics())) : $this->finalizeMetrics($this->emptyMetrics());
        $mtmMetrics = $comparisonPeriods['mtm'] ? ($metricsByPeriod[$comparisonPeriods['mtm']] ?? $this->finalizeMetrics($this->emptyMetrics())) : $this->finalizeMetrics($this->emptyMetrics());
        $mtdMetrics = $comparisonPeriods['mtd'] ? ($metricsByPeriod[$comparisonPeriods['mtd']] ?? $this->finalizeMetrics($this->emptyMetrics())) : $this->finalizeMetrics($this->emptyMetrics());
        $h2Metrics = $comparisonPeriods['h2'] ? ($metricsByPeriod[$comparisonPeriods['h2']] ?? $this->finalizeMetrics($this->emptyMetrics())) : $this->finalizeMetrics($this->emptyMetrics());
        $rkaMetrics = $this->buildRkaMetrics($comparisonPeriods['rka'], $selectedPeriod, $kancaKey, $unitKey, false);
        $rkaDecMetrics = $this->buildRkaMetrics($comparisonPeriods['rka'], $selectedPeriod, $kancaKey, $unitKey, true);

        $rows = collect(self::ROW_DEFINITIONS)->map(function (array $definition) use (
            $currentMetrics,
            $yoyMetrics,
            $ytdMetrics,
            $m2Metrics,
            $mtmMetrics,
            $mtdMetrics,
            $h2Metrics,
            $rkaMetrics,
            $rkaDecMetrics
        ) {
            $metricKey = $definition['key'];

            return [
                'key' => $metricKey,
                'label' => $definition['label'],
                'type' => $definition['type'],
                'depth' => $definition['depth'],
                'accent' => $definition['accent'],
                'values' => [
                    'yoy' => (float) ($yoyMetrics[$metricKey] ?? 0),
                    'ytd' => (float) ($ytdMetrics[$metricKey] ?? 0),
                    'm2' => (float) ($m2Metrics[$metricKey] ?? 0),
                    'mtm' => (float) ($mtmMetrics[$metricKey] ?? 0),
                    'mtd' => (float) ($mtdMetrics[$metricKey] ?? 0),
                    'h2' => (float) ($h2Metrics[$metricKey] ?? 0),
                    'current' => (float) ($currentMetrics[$metricKey] ?? 0),
                    'rka' => (float) ($rkaMetrics[$metricKey] ?? 0),
                    'rka_dec' => (float) ($rkaDecMetrics[$metricKey] ?? 0),
                    'penc_pct' => $this->safePercent(
                        (float) ($currentMetrics[$metricKey] ?? 0),
                        (float) ($rkaMetrics[$metricKey] ?? 0)
                    ),
                ],
                'deltas' => [
                    'yoy' => (float) ($currentMetrics[$metricKey] ?? 0) - (float) ($yoyMetrics[$metricKey] ?? 0),
                    'ytd' => (float) ($currentMetrics[$metricKey] ?? 0) - (float) ($ytdMetrics[$metricKey] ?? 0),
                    'mtd' => (float) ($currentMetrics[$metricKey] ?? 0) - (float) ($mtdMetrics[$metricKey] ?? 0),
                    'dtd' => (float) ($currentMetrics[$metricKey] ?? 0) - (float) ($h2Metrics[$metricKey] ?? 0),
                ],
            ];
        })->values()->all();

        $source = $this->canUseSnapshotMetrics() && $this->normalizeFilterValues($kancaKey) === [] && $this->normalizeFilterValues($unitKey) === []
            ? self::SNAPSHOT_TABLE
            : 'source_fallback';

        return [
            'selected_period' => $selectedPeriod,
            'selected_period_label' => $this->formatPeriodLabel($selectedPeriod),
            'selected_rka_period' => $comparisonPeriods['rka'],
            'selected_rka_label' => $this->formatMonthLabel($comparisonPeriods['rka']),
            'comparison_periods' => [
                'yoy' => ['period' => $comparisonPeriods['yoy'], 'label' => $this->formatPeriodLabel($comparisonPeriods['yoy'])],
                'ytd' => ['period' => $comparisonPeriods['ytd'], 'label' => $this->formatPeriodLabel($comparisonPeriods['ytd'])],
                'm2' => ['period' => $comparisonPeriods['m2'], 'label' => $this->formatPeriodLabel($comparisonPeriods['m2'])],
                'mtm' => ['period' => $comparisonPeriods['mtm'], 'label' => $this->formatPeriodLabel($comparisonPeriods['mtm'])],
                'mtd' => ['period' => $comparisonPeriods['mtd'], 'label' => $this->formatPeriodLabel($comparisonPeriods['mtd'])],
                'h2' => ['period' => $comparisonPeriods['h2'], 'label' => $this->formatPeriodLabel($comparisonPeriods['h2'])],
                'h1' => ['period' => $comparisonPeriods['h1'], 'label' => $this->formatPeriodLabel($comparisonPeriods['h1'])],
                'rka' => ['period' => $comparisonPeriods['rka'], 'label' => $this->formatMonthLabel($comparisonPeriods['rka'])],
                'rka_dec' => ['period' => $comparisonPeriods['rka_dec'], 'label' => $this->formatPeriodLabel($comparisonPeriods['rka_dec'])],
            ],
            'rows' => $rows,
            'summary' => [
                'source' => $source,
                'kanca_label' => $this->displayFilterLabel($kancaKey, 'Semua Kanca', $selectedPeriod, 'kanca', $kancaKey, $unitKey),
                'unit_label' => $this->displayFilterLabel($unitKey, 'Semua Unit Kerja', $selectedPeriod, 'unit_kerja', $kancaKey, $unitKey),
                'row_count' => count($rows),
                'current_total_simpanan' => (float) ($currentMetrics['total_simpanan'] ?? 0),
                'current_total_os_non_commercial' => (float) ($currentMetrics['total_os_non_commercial'] ?? 0),
                'current_casa_pct' => (float) ($currentMetrics['casa_pct'] ?? 0),
            ],
        ];
    }

    private function loadMetricsForPeriods(array $periods, array|string|null $kancaKey, array|string|null $unitKey): array
    {
        $normalizedPeriods = array_values(array_unique(array_filter(array_map([$this, 'normalizeDate'], $periods))));
        if ($normalizedPeriods === []) {
            return [];
        }

        $metricsByPeriod = [];
        $normalizedKanca = $this->normalizeFilterValues($kancaKey);
        $normalizedUnit = $this->normalizeFilterValues($unitKey);
        $useSnapshot = $normalizedKanca === [] && $normalizedUnit === [] && $this->canUseSnapshotMetrics();

        if ($useSnapshot) {
            $selects = collect(self::METRIC_COLUMNS)
                ->map(fn (string $column) => "COALESCE(SUM({$column}), 0) as {$column}")
                ->implode(",\n");

            $query = DB::table(self::SNAPSHOT_TABLE)
                ->whereIn('snapshot_period', $normalizedPeriods)
                ->groupBy('snapshot_period')
                ->orderBy('snapshot_period')
                ->selectRaw('snapshot_period')
                ->selectRaw($selects)
                ->selectRaw('MAX(source_row_count) as source_row_count');

            foreach ($query->get() as $row) {
                $metricsByPeriod[$row->snapshot_period] = $this->finalizeMetrics((array) $row);
            }
        }

        foreach ($normalizedPeriods as $period) {
            if (!isset($metricsByPeriod[$period])) {
                $metricsByPeriod[$period] = $this->buildMetricsFromSource($period, $normalizedKanca, $normalizedUnit);
            }
        }

        return $metricsByPeriod;
    }

    private function buildMetricsFromSource(string $period, array|string|null $kancaKey, array|string|null $unitKey): array
    {
        [$payload] = $this->buildAggregatedRowsForPeriod($period, $kancaKey, $unitKey);
        $metrics = $this->emptyMetrics();

        foreach ($payload as $row) {
            $this->accumulateMetrics($metrics, $row);
        }

        return $this->finalizeMetrics($metrics);
    }

    private function buildAggregatedRowsForPeriod(string $period, array|string|null $kancaKey = null, array|string|null $unitKey = null): array
    {
        $buckets = [];
        $sourceRowCount = 0;

        foreach ($this->fetchSavingsAggregates($period, $kancaKey, $unitKey) as $row) {
            $kancaLabel = $this->normalizeKancaLabel($row->raw_kantor_cabang ?? $row->raw_unit_kerja ?? null);
            if ($kancaLabel === '') {
                continue;
            }

            $unitLabel = $this->normalizeUnitLabel($row->raw_unit_kerja ?? null, $kancaLabel);
            $bucketKey = $this->makeBucketKey($kancaLabel, $unitLabel);
            $this->initializeBucket($buckets, $bucketKey, $kancaLabel, $unitLabel);

            foreach ([
                'giro_ritel',
                'deposito_ritel',
                'tabungan_ritel',
                'giro_mikro',
                'deposito_mikro',
                'tabungan_mikro',
                'giro_wholesale',
                'deposito_wholesale',
                'tabungan_wholesale',
            ] as $metric) {
                $buckets[$bucketKey][$metric] += (float) ($row->{$metric} ?? 0);
            }

            $sourceRowCount++;
        }

        foreach ($this->fetchLoanAggregates($period, $kancaKey, $unitKey) as $row) {
            $kancaLabel = $this->normalizeKancaLabel($row->raw_cabang ?? $row->raw_unit ?? null);
            if ($kancaLabel === '') {
                continue;
            }

            $unitLabel = $this->normalizeUnitLabel($row->raw_unit ?? null, $kancaLabel);
            $bucketKey = $this->makeBucketKey($kancaLabel, $unitLabel);
            $this->initializeBucket($buckets, $bucketKey, $kancaLabel, $unitLabel);

            foreach ($this->loanMetricKeys() as $metric) {
                $buckets[$bucketKey][$metric] += (float) ($row->{$metric} ?? 0);
            }

            $sourceRowCount++;
        }

        foreach ($this->fetchPhAggregates($period, $kancaKey, $unitKey) as $row) {
            $kancaLabel = $this->normalizeKancaLabel($row->raw_kanca ?? $row->raw_unit ?? null);
            if ($kancaLabel === '') {
                continue;
            }

            $unitLabel = $this->normalizeUnitLabel($row->raw_unit ?? null, $kancaLabel);
            $bucketKey = $this->makeBucketKey($kancaLabel, $unitLabel);
            $this->initializeBucket($buckets, $bucketKey, $kancaLabel, $unitLabel);

            $buckets[$bucketKey]['ph_tupok'] += (float) ($row->ph_tupok ?? 0);
            $buckets[$bucketKey]['ph_lunas'] += (float) ($row->ph_lunas ?? 0);
            $buckets[$bucketKey]['rec_dh_small'] += (float) ($row->rec_dh_small ?? 0);
            $buckets[$bucketKey]['rec_dh_consumer'] += (float) ($row->rec_dh_consumer ?? 0);
            $buckets[$bucketKey]['rec_dh_micro'] += (float) ($row->rec_dh_micro ?? 0);
            $buckets[$bucketKey]['rec_dh_total'] += (float) ($row->rec_dh_total ?? 0);
            $sourceRowCount++;
        }

        $payload = [];
        foreach ($buckets as $row) {
            $metrics = $this->finalizeMetrics($row);
            $payload[] = array_merge(
                [
                    'uniqueid_dhs' => md5(implode('|', ['dhs', $period, $row['kanca_key'], $row['unit_key']])),
                    'snapshot_period' => $period,
                    'kanca_key' => $row['kanca_key'],
                    'kanca_label' => $row['kanca_label'],
                    'unit_key' => $row['unit_key'],
                    'unit_label' => $row['unit_label'],
                ],
                collect(self::METRIC_COLUMNS)->mapWithKeys(fn (string $metric) => [$metric => (float) ($metrics[$metric] ?? 0)])->all(),
                [
                    'source_row_count' => $sourceRowCount,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        return [$payload, $sourceRowCount];
    }

    private function fetchSavingsAggregates(string $period, array|string|null $kancaKey = null, array|string|null $unitKey = null): Collection
    {
        $segment = "UPPER(TRIM(COALESCE(ss.segmentasi, '')))";
        $product = "UPPER(TRIM(COALESCE(ss.produk, '')))";

        $microSegment = "{$segment} IN ('MICRO', 'MIKRO')";

        $query = DB::table(self::SAVINGS_TABLE . ' as ss')
            ->whereIn('ss.Month_Day_Year_of_Posisi', $this->sourcePeriodRawCandidates(self::SAVINGS_TABLE, $period))
            ->selectRaw("TRIM(COALESCE(ss.nama_cabang, '')) as raw_kantor_cabang")
            ->selectRaw("TRIM(COALESCE(ss.nama_uker, '')) as raw_unit_kerja")
            ->selectRaw("SUM(CASE WHEN {$segment} = 'RITEL' AND {$product} = 'GIRO' THEN COALESCE(ss.saldo, 0) ELSE 0 END) as giro_ritel")
            ->selectRaw("SUM(CASE WHEN {$segment} = 'RITEL' AND {$product} = 'DEPOSITO' THEN COALESCE(ss.saldo, 0) ELSE 0 END) as deposito_ritel")
            ->selectRaw("SUM(CASE WHEN {$segment} = 'RITEL' AND {$product} = 'TABUNGAN' THEN COALESCE(ss.saldo, 0) ELSE 0 END) as tabungan_ritel")
            ->selectRaw("SUM(CASE WHEN {$microSegment} AND {$product} = 'GIRO' THEN COALESCE(ss.saldo, 0) ELSE 0 END) as giro_mikro")
            ->selectRaw("SUM(CASE WHEN {$microSegment} AND {$product} = 'DEPOSITO' THEN COALESCE(ss.saldo, 0) ELSE 0 END) as deposito_mikro")
            ->selectRaw("SUM(CASE WHEN {$microSegment} AND {$product} = 'TABUNGAN' THEN COALESCE(ss.saldo, 0) ELSE 0 END) as tabungan_mikro")
            ->selectRaw("SUM(CASE WHEN {$segment} = 'WHOLESALE' AND {$product} = 'GIRO' THEN COALESCE(ss.saldo, 0) ELSE 0 END) as giro_wholesale")
            ->selectRaw("SUM(CASE WHEN {$segment} = 'WHOLESALE' AND {$product} = 'DEPOSITO' THEN COALESCE(ss.saldo, 0) ELSE 0 END) as deposito_wholesale")
            ->selectRaw("SUM(CASE WHEN {$segment} = 'WHOLESALE' AND {$product} = 'TABUNGAN' THEN COALESCE(ss.saldo, 0) ELSE 0 END) as tabungan_wholesale")
            ->groupBy('raw_kantor_cabang', 'raw_unit_kerja');

        $normalizedKanca = $this->normalizeFilterValues($kancaKey);
        if ($normalizedKanca !== []) {
            $query->whereIn(DB::raw("TRIM(COALESCE(ss.nama_cabang, ''))"), $normalizedKanca);
        }

        $normalizedUnit = $this->normalizeFilterValues($unitKey);
        if ($normalizedUnit !== []) {
            $query->whereIn(DB::raw("TRIM(COALESCE(ss.nama_uker, ''))"), $normalizedUnit);
        }

        return $query->get();
    }

    private function fetchLoanAggregates(string $period, array|string|null $kancaKey = null, array|string|null $unitKey = null): Collection
    {
        $segment = "UPPER(TRIM(COALESCE(sp.segmen_dashboard, '')))";
        $productDashboard = "UPPER(TRIM(COALESCE(sp.produk_dashboard, '')))";
        $product = "UPPER(TRIM(COALESCE(sp.produk, '')))";
        $balance = 'COALESCE(sp.baki_debet, 0)';
        $kol = "CAST(NULLIF(TRIM(COALESCE(sp.kolektabilitas_one_obligor, '')), '') AS UNSIGNED)";

        $query = DB::table(self::LOAN_TABLE . ' as sp')
            ->whereIn('sp.month_day_year_of_periode', $this->sourcePeriodRawCandidates(self::LOAN_TABLE, $period))
            ->selectRaw("TRIM(COALESCE(sp.nama_cabang, '')) as raw_cabang")
            ->selectRaw("TRIM(COALESCE(sp.nama_uker, '')) as raw_unit");

        $normalizedKanca = $this->normalizeFilterValues($kancaKey);
        if ($normalizedKanca !== []) {
            $query->whereIn(DB::raw("TRIM(COALESCE(sp.nama_cabang, ''))"), $normalizedKanca);
        }

        $normalizedUnit = $this->normalizeFilterValues($unitKey);
        if ($normalizedUnit !== []) {
            $query->whereIn(DB::raw("TRIM(COALESCE(sp.nama_uker, ''))"), $normalizedUnit);
        }

        foreach ($this->loanMetricDefinitions($segment, $productDashboard, $product) as $alias => $condition) {
            $query->selectRaw("SUM(CASE WHEN {$condition} THEN {$balance} ELSE 0 END) as {$alias}_os");
            $query->selectRaw("SUM(CASE WHEN {$condition} AND {$kol} = 2 THEN {$balance} ELSE 0 END) as {$alias}_sml");
            $query->selectRaw("SUM(CASE WHEN {$condition} AND {$kol} > 2 THEN {$balance} ELSE 0 END) as {$alias}_npl");
        }

        return $query
            ->selectRaw("SUM(CASE WHEN {$segment} IN ('SMALL', 'MEDIUM', 'CONSUMER', 'MICRO', 'MIKRO') AND {$kol} = 2 THEN {$balance} ELSE 0 END) as total_sml_abs_non_commercial")
            ->selectRaw("SUM(CASE WHEN {$segment} IN ('SMALL', 'MEDIUM', 'CONSUMER', 'MICRO', 'MIKRO') AND {$kol} > 2 THEN {$balance} ELSE 0 END) as total_npl_abs_non_commercial")
            ->groupBy('raw_cabang', 'raw_unit')
            ->get();
    }

    private function fetchPhAggregates(string $period, array|string|null $kancaKey = null, array|string|null $unitKey = null): Collection
    {
        if (!Schema::hasTable('lw325_ph')) {
            return collect();
        }

        $normalizedCurrentPeriod = $this->normalizeDate($period);
        if ($normalizedCurrentPeriod === null) {
            return collect();
        }

        if (!DB::table('lw325_ph')->where('periode', $normalizedCurrentPeriod)->exists()) {
            return collect();
        }

        $previousPhPeriod = $this->resolvePreviousPhPeriod($normalizedCurrentPeriod);
        
        // If no previous period, we can't calculate delta/recovery normally.
        // However, if it's the start of the series, we might want to return an empty 
        // collection with initialized keys rather than a completely empty result.
        if ($previousPhPeriod === null) {
            // Check if user wants to see at least the segments (they will be 0)
            // or if we should fallback to another logic.
            return collect();
        }

        $normalizedKanca = $this->normalizeFilterValues($kancaKey);
        $normalizedUnit = $this->normalizeFilterValues($unitKey);

        $tupok = DB::table('lw325_ph as n')
            ->join('lw325_ph as o', function ($join) use ($previousPhPeriod, $normalizedCurrentPeriod) {
                $join->on('n.acctno', '=', 'o.acctno')
                    ->where('n.periode', '=', $normalizedCurrentPeriod)
                    ->where('o.periode', '=', $previousPhPeriod);
            })
            ->whereRaw('(COALESCE(o.pokok, 0) - COALESCE(n.pokok, 0)) > 0')
            ->whereNotNull('n.acctno')
            ->where('n.acctno', '<>', '')
            ->selectRaw("TRIM(COALESCE(n.kanca, '')) as raw_kanca")
            ->selectRaw("TRIM(COALESCE(n.unit, '')) as raw_unit")
            ->selectRaw("TRIM(COALESCE(n.segmen_dashboard, '')) as raw_segment")
            // Tupok mengikuti outstanding pokok periode sebelumnya untuk akun yang masih ada
            // dan mengalami penurunan pokok pada periode berjalan.
            ->selectRaw('SUM(COALESCE(o.pokok, 0)) as ph_tupok')
            ->selectRaw('0 as ph_lunas')
            ->selectRaw('SUM(COALESCE(o.pokok, 0)) as ph_amount')
            ->groupBy('raw_kanca', 'raw_unit', 'raw_segment');

        if ($normalizedKanca !== []) {
            $tupok->whereIn(DB::raw("TRIM(COALESCE(n.kanca, ''))"), $normalizedKanca);
        }

        if ($normalizedUnit !== []) {
            $tupok->whereIn(DB::raw("TRIM(COALESCE(n.unit, ''))"), $normalizedUnit);
        }

        $lunas = DB::table('lw325_ph as o')
            ->leftJoin('lw325_ph as n', function ($join) use ($previousPhPeriod, $normalizedCurrentPeriod) {
                $join->on('o.acctno', '=', 'n.acctno')
                    ->where('o.periode', '=', $previousPhPeriod)
                    ->where('n.periode', '=', $normalizedCurrentPeriod);
            })
            ->where('o.periode', $previousPhPeriod)
            ->whereNull('n.acctno')
            ->whereNotNull('o.acctno')
            ->where('o.acctno', '<>', '')
            ->selectRaw("TRIM(COALESCE(o.kanca, '')) as raw_kanca")
            ->selectRaw("TRIM(COALESCE(o.unit, '')) as raw_unit")
            ->selectRaw("TRIM(COALESCE(o.segmen_dashboard, '')) as raw_segment")
            ->selectRaw('0 as ph_tupok')
            ->selectRaw('SUM(COALESCE(o.pokok, 0)) as ph_lunas')
            ->selectRaw('SUM(COALESCE(o.pokok, 0)) as ph_amount')
            ->groupBy('raw_kanca', 'raw_unit', 'raw_segment');

        if ($normalizedKanca !== []) {
            $lunas->whereIn(DB::raw("TRIM(COALESCE(o.kanca, ''))"), $normalizedKanca);
        }

        if ($normalizedUnit !== []) {
            $lunas->whereIn(DB::raw("TRIM(COALESCE(o.unit, ''))"), $normalizedUnit);
        }

        return DB::query()
            ->fromSub($tupok->unionAll($lunas), 'ph_summary')
            ->selectRaw("TRIM(COALESCE(ph_summary.raw_kanca, '')) as raw_kanca")
            ->selectRaw("TRIM(COALESCE(ph_summary.raw_unit, '')) as raw_unit")
            ->selectRaw('SUM(COALESCE(ph_summary.ph_tupok, 0)) as ph_tupok')
            ->selectRaw('SUM(COALESCE(ph_summary.ph_lunas, 0)) as ph_lunas')
            ->selectRaw("
                SUM(
                    CASE
                        WHEN UPPER(TRIM(COALESCE(ph_summary.raw_segment, ''))) = 'SMALL'
                        THEN COALESCE(ph_summary.ph_amount, 0)
                        ELSE 0
                    END
                ) as rec_dh_small
            ")
            ->selectRaw("
                SUM(
                    CASE
                        WHEN UPPER(TRIM(COALESCE(ph_summary.raw_segment, ''))) = 'CONSUMER'
                        THEN COALESCE(ph_summary.ph_amount, 0)
                        ELSE 0
                    END
                ) as rec_dh_consumer
            ")
            ->selectRaw("
                SUM(
                    CASE
                        WHEN UPPER(TRIM(COALESCE(ph_summary.raw_segment, ''))) IN ('MICRO', 'MIKRO')
                        THEN COALESCE(ph_summary.ph_amount, 0)
                        ELSE 0
                    END
                ) as rec_dh_micro
            ")
            ->selectRaw('SUM(COALESCE(ph_summary.ph_amount, 0)) as rec_dh_total')
            ->groupBy('raw_kanca', 'raw_unit')
            ->get();
    }

    private function finalizeMetrics(array $metrics): array
    {
        $final = $this->emptyMetrics();

        foreach (self::METRIC_COLUMNS as $column) {
            $final[$column] = (float) ($metrics[$column] ?? 0);
        }

        $final['simpanan_ritel'] = $final['giro_ritel'] + $final['deposito_ritel'] + $final['tabungan_ritel'];
        $final['simpanan_mikro'] = $final['giro_mikro'] + $final['deposito_mikro'] + $final['tabungan_mikro'];
        $final['simpanan_wholesale'] = $final['giro_wholesale'] + $final['deposito_wholesale'] + $final['tabungan_wholesale'];
        $final['total_simpanan'] = $final['simpanan_ritel'] + $final['simpanan_mikro'] + $final['simpanan_wholesale'];
        $final['casa_ritel'] = $final['giro_ritel'] + $final['tabungan_ritel'];
        $final['casa_mikro'] = $final['giro_mikro'] + $final['tabungan_mikro'];
        $final['total_casa'] = $final['casa_ritel'] + $final['casa_mikro'];
        $final['commercial_os'] = 0.0;
        $final['sme_os'] = $final['kecil_os'];
        $final['sme_sml'] = $final['kecil_sml'];
        $final['sme_npl'] = $final['kecil_npl'];
        $final['total_os_non_commercial'] = $final['kecil_os'] + $final['medium_os'] + $final['consumer_os'] + $final['micro_os'];
        $final['ldr_non_commercial'] = $this->safePercent($final['total_simpanan'], $final['total_os_non_commercial']);
        $final['ldr_ritel_non_commercial'] = $this->safePercent($final['simpanan_ritel'], $final['sme_os'] + $final['consumer_os']);
        $final['ldr_mikro_non_commercial'] = $this->safePercent($final['simpanan_mikro'], $final['micro_os']);
        $final['casa_pct'] = $this->safePercent($final['total_casa'], $final['total_simpanan']);
        $final['rec_dh_total'] = $final['rec_dh_small'] + $final['rec_dh_consumer'] + $final['rec_dh_micro'];

        return $final;
    }

    private function buildRkaMetrics(?string $rkaPeriod, ?string $filterPeriod, array|string|null $kancaKey, array|string|null $unitKey, bool $useDecember): array
    {
        if (!$rkaPeriod) {
            return $this->emptyMetrics();
        }

        $rkaYear = (int) Carbon::parse($rkaPeriod)->format('Y');
        $monthColumn = $useDecember
            ? 'dec'
            : $this->rkaLookupService()->resolveMonthColumn(Carbon::parse($rkaPeriod));

        $definitions = $this->dashboardRkaMetricDefinitions();
        $normalizedKanca = $this->normalizeFilterValues($kancaKey);
        $normalizedUnit = $this->normalizeFilterValues($unitKey);

        if ($normalizedUnit !== []) {
            $rawMetrics = $this->sumGroupedRkaMetrics(
                $this->rkaLookupService()->aggregateByGroup(
                    $definitions,
                    $monthColumn,
                    $normalizedKanca,
                    $normalizedUnit,
                    'uker',
                    $rkaYear
                )
            );
        } elseif (count($normalizedKanca) > 1) {
            $rawMetrics = $this->sumGroupedRkaMetrics(
                $this->rkaLookupService()->aggregateByGroup(
                    $definitions,
                    $monthColumn,
                    $normalizedKanca,
                    [],
                    'kanca',
                    $rkaYear
                )
            );
        } else {
            $kancaLabel = $normalizedKanca !== []
                ? (string) ($normalizedKanca[0] ?? '')
                : null;
            $unitLabel = $normalizedUnit !== []
                ? (string) ($normalizedUnit[0] ?? '')
                : null;

            $rawMetrics = $this->rkaLookupService()->aggregateForScope(
                $definitions,
                $monthColumn,
                $kancaLabel,
                $unitLabel,
                $rkaYear
            );
        }

        return $this->finalizeRkaMetrics($rawMetrics);
    }

    private function sumGroupedRkaMetrics(array $groupedMetrics): array
    {
        $result = [];

        foreach ($groupedMetrics as $metricKey => $groups) {
            $result[$metricKey] = round((float) array_sum($groups), 2);
        }

        return $result;
    }

    private function dashboardRkaMetricDefinitions(): array
    {
        return [
            'total_simpanan' => ['mata_anggaran' => ['A.1. DPK Retail Funding Total']],
            'simpanan_ritel' => ['mata_anggaran' => ['A.1. DPK Retail Funding Total'], 'uker_contains_any' => ['KC', 'KCP']],
            'giro_ritel' => ['mata_anggaran' => ['Giro Retail Funding Total'], 'uker_contains_any' => ['KC', 'KCP']],
            'deposito_ritel' => ['mata_anggaran' => ['Deposito Retail Funding Total'], 'uker_contains_any' => ['KC', 'KCP']],
            'tabungan_ritel' => ['mata_anggaran' => ['Tabungan Retail Funding Total'], 'uker_contains_any' => ['KC', 'KCP']],
            'simpanan_mikro' => ['mata_anggaran' => ['A.1. DPK Retail Funding Total'], 'uker_contains_any' => ['UNIT']],
            'giro_mikro' => ['mata_anggaran' => ['Giro Retail Funding Total'], 'uker_contains_any' => ['UNIT']],
            'deposito_mikro' => ['mata_anggaran' => ['Deposito Retail Funding Total'], 'uker_contains_any' => ['UNIT']],
            'tabungan_mikro' => ['mata_anggaran' => ['Tabungan Retail Funding Total'], 'uker_contains_any' => ['UNIT']],
            'total_os' => ['mata_anggaran' => ['B. KREDIT TOTAL']],
            'kecil_non_cashcoll_os' => ['mata_anggaran' => ['B.2.a. Kredit Kecil Non Cash Collateral']],
            'cashcoll_os' => ['mata_anggaran' => ['B.2.b. Kredit Kecil Cash Collateral']],
            'medium_os' => ['mata_anggaran' => ['B.3. MEDIUM']],
            'briguna_konsumer_os' => ['mata_anggaran' => ['B.5.a. Briguna']],
            'kpr_os' => ['mata_anggaran' => ['B.5.b. KPR']],
            'kkb_os' => ['mata_anggaran' => ['B.5.c. KKB']],
            'micro_os' => ['mata_anggaran' => ['B.1. MIKRO']],
            'briguna_mikro_os' => ['mata_anggaran' => ['B.1.b. Briguna Mikro']],
            'kupedes_os' => ['mata_anggaran' => ['B.1.a. Kupedes Komersial']],
            'kur_mikro_os' => ['mata_anggaran' => ['B.1.c. KUR Mikro']],
            'kur_kecil_os' => ['mata_anggaran' => ['B.1.d. KUR Kecil']],
            'kur_kpp_os' => ['mata_anggaran' => ['B.1.e. KPP']],
            'total_sml_pct_non_commercial' => ['mata_anggaran' => ['DPK % Total']],
            'kecil_non_cashcoll_sml' => ['mata_anggaran' => ['DPK Rp Kecil Non Cash Collateral']],
            'cashcoll_sml' => ['mata_anggaran' => ['DPK Rp Kecil Cash Collateral']],
            'medium_sml' => ['mata_anggaran' => ['DPK Rp Medium']],
            'briguna_konsumer_sml' => ['mata_anggaran' => ['DPK Rp Briguna']],
            'kpr_sml' => ['mata_anggaran' => ['DPK Rp KPR']],
            'kkb_sml' => ['mata_anggaran' => ['DPK Rp KKB']],
            'micro_sml' => ['mata_anggaran' => ['DPK Rp Mikro']],
            'briguna_mikro_sml' => ['mata_anggaran' => ['DPK Rp Briguna Mikro']],
            'kupedes_sml' => ['mata_anggaran' => ['DPK Rp Kupedes Komersial']],
            'kur_mikro_sml' => ['mata_anggaran' => ['DPK Rp KUR Mikro']],
            'kur_kecil_sml' => ['mata_anggaran' => ['DPK Rp KUR Kecil']],
            'kur_kpp_sml' => ['mata_anggaran' => ['DPK Rp KPP']],
            'total_npl_pct_non_commercial' => ['mata_anggaran' => ['NPL % Total', 'DPK % Total']],
            'kecil_non_cashcoll_npl' => ['mata_anggaran' => ['NPL Rp Kecil Non Cash Collateral', 'DPK Rp Kecil Non Cash Collateral']],
            'cashcoll_npl' => ['mata_anggaran' => ['NPL Rp Kecil Cash Collateral', 'DPK Rp Kecil Cash Collateral']],
            'medium_npl' => ['mata_anggaran' => ['NPL Rp Medium', 'DPK Rp Medium']],
            'briguna_konsumer_npl' => ['mata_anggaran' => ['NPL Rp Briguna', 'DPK Rp Briguna']],
            'kpr_npl' => ['mata_anggaran' => ['NPL Rp KPR', 'DPK Rp KPR']],
            'kkb_npl' => ['mata_anggaran' => ['NPL Rp KKB', 'DPK Rp KKB']],
            'micro_npl' => ['mata_anggaran' => ['NPL Rp Mikro', 'DPK Rp Mikro']],
            'briguna_mikro_npl' => ['mata_anggaran' => ['NPL Rp Briguna Mikro', 'DPK Rp Briguna Mikro']],
            'kupedes_npl' => ['mata_anggaran' => ['NPL Rp Kupedes Komersial', 'DPK Rp Kupedes Komersial']],
            'kur_mikro_npl' => ['mata_anggaran' => ['NPL Rp KUR Mikro', 'DPK Rp KUR Mikro']],
            'kur_kecil_npl' => ['mata_anggaran' => ['NPL Rp KUR Kecil', 'DPK Rp KUR Kecil']],
            'kur_kpp_npl' => ['mata_anggaran' => ['NPL Rp KPP', 'DPK Rp KPP']],
        ];
    }

    private function finalizeRkaMetrics(array $metrics): array
    {
        $final = $this->emptyMetrics();

        foreach ($metrics as $key => $value) {
            if (array_key_exists($key, $final)) {
                $final[$key] = (float) $value;
            }
        }

        $final['casa_ritel'] = $final['giro_ritel'] + $final['tabungan_ritel'];
        $final['casa_mikro'] = $final['giro_mikro'] + $final['tabungan_mikro'];
        $final['total_casa'] = $final['casa_ritel'] + $final['casa_mikro'];
        $final['commercial_os'] = 0.0;
        $final['kecil_os'] = $final['kecil_non_cashcoll_os'] + $final['cashcoll_os'];
        $final['sme_os'] = $final['kecil_os'];
        $final['consumer_os'] = $final['briguna_konsumer_os'] + $final['kpr_os'] + $final['kkb_os'];
        $final['total_os_non_commercial'] = $final['kecil_os'] + $final['medium_os'] + $final['consumer_os'] + $final['micro_os'];
        if ((float) ($final['total_os'] ?? 0) <= 0) {
            $final['total_os'] = $final['commercial_os'] + $final['total_os_non_commercial'];
        }
        $final['kecil_sml'] = $final['kecil_non_cashcoll_sml'] + $final['cashcoll_sml'];
        $final['sme_sml'] = $final['kecil_sml'];
        $final['consumer_sml'] = $final['briguna_konsumer_sml'] + $final['kpr_sml'] + $final['kkb_sml'];
        $final['total_sml_abs_non_commercial'] = $final['kecil_sml'] + $final['medium_sml'] + $final['consumer_sml'] + $final['micro_sml'];
        $final['kecil_npl'] = $final['kecil_non_cashcoll_npl'] + $final['cashcoll_npl'];
        $final['sme_npl'] = $final['kecil_npl'];
        $final['consumer_npl'] = $final['briguna_konsumer_npl'] + $final['kpr_npl'] + $final['kkb_npl'];
        $final['total_npl_abs_non_commercial'] = $final['sme_npl'] + $final['consumer_npl'] + $final['micro_npl'];
        $final['casa_pct'] = $this->safePercent($final['total_casa'], $final['total_simpanan']);
        $final['ldr_non_commercial'] = 0.0;
        $final['ldr_ritel_non_commercial'] = 0.0;
        $final['ldr_mikro_non_commercial'] = 0.0;
        $final['rec_dh_total'] = $final['rec_dh_small'] + $final['rec_dh_consumer'] + $final['rec_dh_micro'];

        return $final;
    }

    private function emptyMetrics(): array
    {
        $metrics = [
            'kanca_key' => '',
            'kanca_label' => '',
            'unit_key' => '',
            'unit_label' => '',
            'source_row_count' => 0,
            'casa_pct' => 0.0,
            'total_sml_pct_non_commercial' => 0.0,
            'total_npl_pct_non_commercial' => 0.0,
            'ldr_non_commercial' => 0.0,
            'ldr_ritel_non_commercial' => 0.0,
            'ldr_mikro_non_commercial' => 0.0,
        ];

        foreach (self::METRIC_COLUMNS as $column) {
            $metrics[$column] = 0.0;
        }

        return $metrics;
    }

    private function accumulateMetrics(array &$target, array $source): void
    {
        foreach (self::METRIC_COLUMNS as $column) {
            $target[$column] = (float) ($target[$column] ?? 0) + (float) ($source[$column] ?? 0);
        }

        $target['source_row_count'] = (int) ($target['source_row_count'] ?? 0) + (int) ($source['source_row_count'] ?? 0);
    }

    private function initializeBucket(array &$buckets, string $bucketKey, string $kancaLabel, string $unitLabel): void
    {
        if (isset($buckets[$bucketKey])) {
            return;
        }

        $buckets[$bucketKey] = $this->emptyMetrics();
        $buckets[$bucketKey]['kanca_key'] = $this->slugKey($kancaLabel);
        $buckets[$bucketKey]['kanca_label'] = $kancaLabel;
        $buckets[$bucketKey]['unit_key'] = $this->slugKey($unitLabel);
        $buckets[$bucketKey]['unit_label'] = $unitLabel;
    }

    private function makeBucketKey(string $kancaLabel, string $unitLabel): string
    {
        return $this->slugKey($kancaLabel) . '|' . $this->slugKey($unitLabel);
    }

    private function slugKey(string $value): string
    {
        return Str::slug(trim($value), '-');
    }

    private function normalizeKancaLabel($value): string
    {
        $normalized = strtoupper($this->cleanBranchValue((string) $value));

        if ($normalized === '') {
            return '';
        }

        foreach (['MADIUN', 'MAGETAN', 'NGAWI', 'PONOROGO'] as $branchName) {
            if (str_contains($normalized, $branchName)) {
                return 'KC ' . Str::title(Str::lower($branchName));
            }
        }

        if (preg_match('/\bKC[P]?\b/', $normalized) === 1) {
            return Str::title(Str::lower($normalized));
        }

        return '';
    }

    private function normalizeUnitLabel($value, string $fallbackKanca): string
    {
        $clean = $this->cleanBranchValue((string) $value);

        if ($clean === '') {
            return $fallbackKanca;
        }

        $upper = strtoupper($clean);

        if (str_contains($upper, 'KC ') || str_contains($upper, 'KCP ')) {
            $kanca = $this->normalizeKancaLabel($clean);

            return $kanca !== '' ? $kanca : Str::title(Str::lower($clean));
        }

        if (str_contains($upper, 'UNIT ')) {
            $suffix = trim(substr($clean, stripos($upper, 'UNIT ') + 5));

            return 'UNIT ' . Str::title(Str::lower($suffix));
        }

        return Str::title(Str::lower($clean));
    }

    private function cleanBranchValue(string $value): string
    {
        $clean = trim($value);
        $clean = ltrim($clean, "'");
        $clean = preg_replace('/^\d+\s*[-–]+\s*/', '', $clean) ?? $clean;
        $clean = preg_replace('/\(.+\)$/', '', $clean) ?? $clean;
        $clean = preg_replace('/\s+/', ' ', $clean) ?? $clean;

        return trim($clean);
    }

    private function fetchMonthFilterOptions(?string $contextPeriod = null): array
    {
        $availableYears = $this->availableRkaYears();
        $preferredYear = $contextPeriod ? (int) Carbon::parse($contextPeriod)->format('Y') : null;
        $resolvedYear = $this->resolveRkaYear($preferredYear, null, $availableYears);

        if ($resolvedYear === null) {
            return [];
        }

        return collect(range(1, 12))
            ->map(function (int $month) use ($resolvedYear) {
                $date = Carbon::create($resolvedYear, $month, 1)->toDateString();

                return [
                    'value' => Carbon::parse($date)->format('Y-m'),
                    'label' => $this->formatMonthLabel($date),
                ];
            })
            ->all();
    }

    private function availableRkaYears(): array
    {
        try {
            if (!Schema::hasTable('rka')) {
                return [];
            }

            return $this->rkaLookupService()->availableYears();
        } catch (Throwable) {
            return [];
        }
    }

    private function resolveRkaYear(?int $preferredYear, ?int $fallbackYear, array $availableYears): ?int
    {
        $candidates = array_values(array_unique(array_filter([$preferredYear, $fallbackYear])));

        foreach ($candidates as $candidate) {
            if (in_array((int) $candidate, $availableYears, true)) {
                return (int) $candidate;
            }
        }

        return $availableYears[0] ?? null;
    }

    private function normalizeMonthValue(?string $value): ?string
    {
        $trimmed = trim((string) $value);
        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}$/', $trimmed) === 1) {
            return $trimmed;
        }

        $normalizedDate = $this->normalizeDate($trimmed);

        return $normalizedDate ? Carbon::parse($normalizedDate)->format('Y-m') : null;
    }

    private function resolveSharedPeriods(?string $targetDate = null): array
    {
        $loanPeriods = DB::table(self::LOAN_TABLE)
            ->select('month_day_year_of_periode')
            ->distinct()
            ->pluck('month_day_year_of_periode')
            ->map(fn ($value) => $this->normalizeDate((string) $value))
            ->filter()
            ->values()
            ->all();

        $savingsPeriods = DB::table(self::SAVINGS_TABLE)
            ->select('Month_Day_Year_of_Posisi')
            ->distinct()
            ->pluck('Month_Day_Year_of_Posisi')
            ->map(fn ($value) => $this->normalizeDate((string) $value))
            ->filter()
            ->values()
            ->all();

        $shared = array_values(array_intersect($loanPeriods, $savingsPeriods));
        rsort($shared);

        if ($targetDate) {
            $normalizedTargetDate = $this->normalizeDate($targetDate);
            foreach ($shared as $sharedPeriod) {
                if ($sharedPeriod <= $normalizedTargetDate) {
                    return [$sharedPeriod];
                }
            }

            return [];
        }

        return $shared;
    }

    private function cleanupSnapshotOrphans(array $validPeriods): void
    {
        if (!Schema::hasTable(self::SNAPSHOT_TABLE)) {
            return;
        }

        $query = DB::table(self::SNAPSHOT_TABLE);

        if ($validPeriods !== []) {
            $query->whereNotIn('snapshot_period', $validPeriods)->delete();

            return;
        }

        $query->delete();
    }

    private function resolvePreviousPeriod(string $period): ?string
    {
        return $this->resolvePreviousNthPeriod($period, 1);
    }

    private function resolvePreviousNthPeriod(string $period, int $n = 1): ?string
    {
        try {
            if (Schema::hasTable(self::SNAPSHOT_TABLE) && DB::table(self::SNAPSHOT_TABLE)->exists()) {
                $periods = DB::table(self::SNAPSHOT_TABLE)
                    ->where('snapshot_period', '<', $period)
                    ->select('snapshot_period')
                    ->distinct()
                    ->orderByDesc('snapshot_period')
                    ->limit($n)
                    ->pluck('snapshot_period');

                return $periods->get($n - 1);
            }
        } catch (Throwable) {
            // Fall through to shared periods.
        }

        $shared = $this->resolveSharedPeriods();
        $filtered = array_filter($shared, fn ($p) => $p < $period);
        rsort($filtered);
        $values = array_values($filtered);

        return $values[$n - 1] ?? null;
    }

    private function sourcePeriodExists(string $table, string $period): bool
    {
        return DB::table($table)
            ->whereIn($this->sourcePeriodColumn($table), $this->sourcePeriodRawCandidates($table, $period))
            ->exists();
    }

    private function resolvePreviousPhPeriod(string $period): ?string
    {
        try {
            return DB::table('lw325_ph')
                ->where('periode', '<', $period)
                ->max('periode');
        } catch (Throwable) {
            return null;
        }
    }

    private function canUseSnapshotMetrics(): bool
    {
        if (!Schema::hasTable(self::SNAPSHOT_TABLE)) {
            return false;
        }

        $columns = Schema::getColumnListing(self::SNAPSHOT_TABLE);
        $requiredColumns = array_merge(['snapshot_period', 'source_row_count'], self::METRIC_COLUMNS);

        return array_diff($requiredColumns, $columns) === [];
    }

    private function normalizeDate(?string $value): ?string
    {
        $trimmed = trim((string) $value);
        if ($trimmed === '') {
            return null;
        }

        try {
            return Carbon::parse($trimmed)->toDateString();
        } catch (Throwable) {
            try {
                return Carbon::parse($this->translateIndonesianMonthPhp($trimmed))->toDateString();
            } catch (Throwable) {
                return null;
            }
        }
    }

    private function formatPeriodLabel(?string $period): string
    {
        if (!$period) {
            return 'Belum ada data';
        }

        return Carbon::parse($period)->translatedFormat('d M Y');
    }

    private function formatMonthLabel(?string $period): string
    {
        if (!$period) {
            return 'Belum ada data';
        }

        $normalizedMonth = $this->normalizeMonthValue($period);

        if ($normalizedMonth === null) {
            return 'Belum ada data';
        }

        return Carbon::createFromFormat('Y-m', $normalizedMonth)->translatedFormat('M Y');
    }

    private function applySnapshotFilter($query, string $column, array|string|null $value): void
    {
        $normalized = $this->normalizeFilterValues($value);

        if ($normalized !== []) {
            $query->whereIn($column, $normalized);
        }
    }

    private function normalizeFilterValue(array|string|null $value): ?string
    {
        if (is_array($value)) {
            return $this->normalizeFilterValues($value)[0] ?? null;
        }

        $normalized = trim((string) $value);

        if ($normalized === '' || $normalized === 'all') {
            return null;
        }

        return $normalized;
    }

    private function normalizeFilterValues(array|string|null $value): array
    {
        if (is_array($value)) {
            return collect($value)
                ->map(fn ($item) => trim((string) $item))
                ->filter(fn ($item) => $item !== '' && $item !== 'all')
                ->unique()
                ->values()
                ->all();
        }

        $normalized = $this->normalizeFilterValue($value);

        return $normalized === null ? [] : [$normalized];
    }

    private function displayFilterLabel(array|string|null $value, string $fallback, string $period, string $group, array|string|null $selectedKanca = null, array|string|null $selectedUnit = null): string
    {
        $normalized = $this->normalizeFilterValues($value);
        if ($normalized === []) {
            return $fallback;
        }

        $options = $this->fetchFilterOptions($period, $selectedKanca, $selectedUnit)[$group] ?? [];
        $labels = collect($options)
            ->filter(fn ($option) => in_array((string) ($option['value'] ?? ''), $normalized, true))
            ->pluck('label')
            ->filter()
            ->values();

        if ($labels->isEmpty()) {
            return $fallback;
        }

        if ($labels->count() === 1) {
            return (string) $labels->first();
        }

        return $labels->count() . ' dipilih';
    }

    private function safePercent(float $value, float $base): float
    {
        if ($base == 0.0) {
            return 0.0;
        }

        return ($value / $base) * 100;
    }

    private function periodDateSql(string $alias, string $column): string
    {
        $wrappedColumn = "{$alias}.`" . str_replace('`', '``', $column) . '`';
        $trimmed = "TRIM(COALESCE({$wrappedColumn}, ''))";
        $translatedMonth = $this->translateIndonesianMonthSql($trimmed);

        return "COALESCE(
            DATE(STR_TO_DATE({$trimmed}, '%Y-%m-%d')),
            DATE(STR_TO_DATE({$trimmed}, '%Y/%m/%d')),
            DATE(STR_TO_DATE({$trimmed}, '%m/%d/%Y')),
            DATE(STR_TO_DATE({$trimmed}, '%c/%e/%Y')),
            DATE(STR_TO_DATE({$trimmed}, '%m-%d-%Y')),
            DATE(STR_TO_DATE({$trimmed}, '%c-%e-%Y')),
            DATE(STR_TO_DATE({$trimmed}, '%d/%m/%Y')),
            DATE(STR_TO_DATE({$trimmed}, '%d-%m-%Y')),
            DATE(STR_TO_DATE({$translatedMonth}, '%e %M %Y'))
        )";
    }

    private function translateIndonesianMonthSql(string $expression): string
    {
        $translated = "UPPER({$expression})";

        foreach ([
            'JANUARI' => 'JANUARY',
            'FEBRUARI' => 'FEBRUARY',
            'MARET' => 'MARCH',
            'APRIL' => 'APRIL',
            'MEI' => 'MAY',
            'JUNI' => 'JUNE',
            'JULI' => 'JULY',
            'AGUSTUS' => 'AUGUST',
            'SEPTEMBER' => 'SEPTEMBER',
            'OKTOBER' => 'OCTOBER',
            'NOVEMBER' => 'NOVEMBER',
            'DESEMBER' => 'DECEMBER',
        ] as $from => $to) {
            $translated = "REPLACE({$translated}, '{$from}', '{$to}')";
        }

        return $translated;
    }

    private function loanMetricDefinitions(string $segment, string $productDashboard, string $product): array
    {
        $microSegment = "{$segment} IN ('MICRO', 'MIKRO')";

        return [
            'commercial' => "{$segment} = 'COMMERCIAL'",
            'sme' => "{$segment} = 'SMALL'",
            'kecil' => "{$segment} = 'SMALL'",
            'kecil_non_cashcoll' => "{$segment} = 'SMALL' AND {$productDashboard} = 'COMMERCIAL'",
            'cashcoll' => "{$segment} = 'SMALL' AND {$productDashboard} IN ('CASHCALL', 'CASHCOLL')",
            'medium' => "{$segment} = 'MEDIUM'",
            'consumer' => "{$segment} = 'CONSUMER'",
            'briguna_konsumer' => "{$segment} = 'CONSUMER' AND {$productDashboard} = 'BRIGUNA-KONSUMER'",
            'kpr' => "{$segment} = 'CONSUMER' AND {$productDashboard} = 'KPR'",
            'kkb' => "{$segment} = 'CONSUMER' AND {$productDashboard} = 'KKB'",
            'micro' => "{$microSegment} AND (
                {$productDashboard} = 'BRIGUNA-MIKRO'
                OR {$product} = 'KUPEDES'
                OR ({$productDashboard} = 'KUR-MIKRO' AND {$product} = 'KUR MIKRO')
                OR ({$productDashboard} = 'KUR-MIKRO' AND {$product} IN ('KUR KECIL', 'KREDIT MIKRO - KUR RITEL 2015'))
                OR {$productDashboard} = 'KPR'
            )",
            'briguna_mikro' => "{$microSegment} AND {$productDashboard} = 'BRIGUNA-MIKRO'",
            'kupedes' => "{$microSegment} AND {$product} = 'KUPEDES'",
            'kur_mikro' => "{$microSegment} AND {$productDashboard} = 'KUR-MIKRO' AND {$product} = 'KUR MIKRO'",
            'kur_kecil' => "{$microSegment} AND {$productDashboard} = 'KUR-MIKRO' AND {$product} IN ('KUR KECIL', 'KREDIT MIKRO - KUR RITEL 2015')",
            'kur_kpp' => "{$microSegment} AND {$productDashboard} = 'KPR'",
        ];
    }

    private function loanMetricKeys(): array
    {
        return [
            'commercial_os',
            'sme_os',
            'kecil_os',
            'kecil_non_cashcoll_os',
            'cashcoll_os',
            'medium_os',
            'consumer_os',
            'briguna_konsumer_os',
            'kpr_os',
            'kkb_os',
            'micro_os',
            'briguna_mikro_os',
            'kupedes_os',
            'kur_mikro_os',
            'kur_kecil_os',
            'kur_kpp_os',
            'commercial_sml',
            'sme_sml',
            'kecil_sml',
            'kecil_non_cashcoll_sml',
            'cashcoll_sml',
            'medium_sml',
            'consumer_sml',
            'briguna_konsumer_sml',
            'kpr_sml',
            'kkb_sml',
            'micro_sml',
            'briguna_mikro_sml',
            'kupedes_sml',
            'kur_mikro_sml',
            'kur_kecil_sml',
            'kur_kpp_sml',
            'total_sml_abs_non_commercial',
            'commercial_npl',
            'sme_npl',
            'kecil_npl',
            'kecil_non_cashcoll_npl',
            'cashcoll_npl',
            'medium_npl',
            'consumer_npl',
            'briguna_konsumer_npl',
            'kpr_npl',
            'kkb_npl',
            'micro_npl',
            'briguna_mikro_npl',
            'kupedes_npl',
            'kur_mikro_npl',
            'kur_kecil_npl',
            'kur_kpp_npl',
            'total_npl_abs_non_commercial',
        ];
    }

    private function sourcePeriodColumn(string $table): string
    {
        return $table === self::LOAN_TABLE
            ? 'month_day_year_of_periode'
            : 'Month_Day_Year_of_Posisi';
    }

    private function sourcePeriodRawCandidates(string $table, string $period): array
    {
        $normalizedPeriod = $this->normalizeDate($period);
        if ($normalizedPeriod === null) {
            return [$period];
        }

        if ($table === self::LOAN_TABLE) {
            return array_values(array_unique(array_filter([
                $period,
                $this->formatIndonesianDate($normalizedPeriod),
                Carbon::parse($normalizedPeriod)->format('Y-m-d'),
            ])));
        }

        return array_values(array_unique(array_filter([
            $period,
            Carbon::parse($normalizedPeriod)->format('Y-m-d'),
        ])));
    }

    private function formatIndonesianDate(string $date): string
    {
        $carbon = Carbon::parse($date);

        return sprintf(
            '%d %s %d',
            $carbon->day,
            $this->indonesianMonthName($carbon->month),
            $carbon->year
        );
    }

    private function indonesianMonthName(int $month): string
    {
        return [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ][$month] ?? '';
    }

    private function translateIndonesianMonthPhp(string $value): string
    {
        return str_ireplace(
            ['Januari', 'Februari', 'Maret', 'Mei', 'Juni', 'Juli', 'Agustus', 'Oktober', 'Desember'],
            ['January', 'February', 'March', 'May', 'June', 'July', 'August', 'October', 'December'],
            $value
        );
    }

    private function rkaLookupService(): RkaLookupService
    {
        return app(RkaLookupService::class);
    }
}
