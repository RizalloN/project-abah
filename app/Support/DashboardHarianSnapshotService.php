<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class DashboardHarianSnapshotService
{
    public const SNAPSHOT_TABLE = 'dashboard_harian_snapshots';
    private const LOAN_TABLE = 'ssa_pinjaman';
    private const SAVINGS_TABLE = 'ssa_simpanan';
    private const AUTO_SYNC_RECENT_SOURCE_HOURS = 6;
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
        'total_sml_pct_non_commercial',
        'total_npl_pct_non_commercial',
    ];
    private const SOURCE_METADATA_COLUMNS = [
        'source_signature',
        'source_loan_row_count',
        'source_savings_row_count',
        'source_recovery_row_count',
        'source_recovery_period',
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

    public function syncMissingPeriods(): array
    {
        return $this->syncDuePeriods();
    }

    /**
     * Build Dashboard Harian snapshots that are missing or stale. A snapshot can
     * become stale when lw325_ph arrives after the SSA period was already built.
     */
    public function syncDuePeriods(?array $candidatePeriods = null): array
    {
        try {
            $sharedPeriods = $this->resolveSharedPeriods();
            if ($sharedPeriods === []) {
                return ['built' => 0, 'failed' => 0, 'missing' => [], 'stale' => [], 'checked' => 0];
            }

            $existingSnapshots = DB::table(self::SNAPSHOT_TABLE)
                ->select('snapshot_period')
                ->selectRaw('COUNT(*) as row_count')
                ->groupBy('snapshot_period')
                ->pluck('row_count', 'snapshot_period')
                ->mapWithKeys(fn ($count, $period) => [(string) $period => (int) $count])
                ->all();

            $missingPeriods = array_values(array_filter(
                $sharedPeriods,
                fn (string $period) => ($existingSnapshots[$period] ?? 0) <= 0
            ));
            $staleCandidatePeriods = $this->normalizeCandidatePeriods(
                $candidatePeriods ?? $this->resolveAutomaticStaleCandidatePeriods($sharedPeriods),
                $sharedPeriods
            );

            $periodsToCheck = array_values(array_unique(array_merge($missingPeriods, $staleCandidatePeriods)));
            if ($periodsToCheck === []) {
                return ['built' => 0, 'failed' => 0, 'missing' => [], 'stale' => [], 'checked' => 0];
            }

            $missingPeriods = [];
            $stalePeriods = [];

            foreach ($periodsToCheck as $period) {
                if (($existingSnapshots[$period] ?? 0) <= 0) {
                    $missingPeriods[] = $period;
                    continue;
                }

                $sourceMetadata = $this->buildSourceMetadata($period);
                if (!$this->snapshotSourceIsFresh($period, $sourceMetadata)) {
                    $stalePeriods[] = $period;
                }
            }

            $duePeriods = array_values(array_unique(array_merge($missingPeriods, $stalePeriods)));
            if ($duePeriods === []) {
                return [
                    'built' => 0,
                    'failed' => 0,
                    'missing' => [],
                    'stale' => [],
                    'checked' => count($periodsToCheck),
                ];
            }

            $built = 0;
            $failed = 0;

            foreach ($duePeriods as $period) {
                try {
                    $count = $this->buildPeriodSnapshot($period, false);
                    if ($count > 0) {
                        $built++;
                    }
                } catch (Throwable $e) {
                    $failed++;
                    Log::warning('Failed to sync due Dashboard Harian snapshot.', [
                        'period' => $period,
                        'exception' => $e::class,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return [
                'built' => $built,
                'failed' => $failed,
                'missing' => array_values($missingPeriods),
                'stale' => array_values($stalePeriods),
                'checked' => count($periodsToCheck),
            ];
        } catch (Throwable $e) {
            Log::error('Failed to sync due Dashboard Harian snapshots', ['error' => $e->getMessage()]);
            return ['built' => 0, 'failed' => 0, 'missing' => [], 'stale' => [], 'checked' => 0];
        }
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

        $sharedPeriodsAsc = $sharedPeriods;
        sort($sharedPeriodsAsc);

        foreach ($sharedPeriodsAsc as $sharedPeriod) {
            if ($sharedPeriod > $normalizedPhPeriod) {
                return [$sharedPeriod];
            }
        }

        return [];
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

        $lockName = 'snapshot:dashboard_harian:build:' . $period;

        try {
            return Cache::lock($lockName, 600)->block(15, function () use ($period, $force): int {
                return $this->buildPeriodSnapshotUnlocked($period, $force);
            });
        } catch (LockTimeoutException) {
            return (int) DB::table(self::SNAPSHOT_TABLE)->where('snapshot_period', $period)->count();
        } catch (Throwable $e) {
            Log::warning('Dashboard Harian snapshot build lock unavailable, continuing without lock.', [
                'period' => $period,
                'force' => $force,
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);

            return $this->buildPeriodSnapshotUnlocked($period, $force);
        }
    }

    private function buildPeriodSnapshotUnlocked(string $period, bool $force = false): int
    {
        $sourceMetadata = $this->buildSourceMetadata($period);

        if (!$force) {
            $existingCount = (int) DB::table(self::SNAPSHOT_TABLE)->where('snapshot_period', $period)->count();
            if ($existingCount > 0 && $this->snapshotSourceIsFresh($period, $sourceMetadata)) {
                return $existingCount;
            }
        }

        if ($force) {
            DB::table(self::SNAPSHOT_TABLE)->where('snapshot_period', $period)->delete();
        }

        if (!$this->sourcePeriodExists(self::LOAN_TABLE, $period) || !$this->sourcePeriodExists(self::SAVINGS_TABLE, $period)) {
            DB::table(self::SNAPSHOT_TABLE)->where('snapshot_period', $period)->delete();

            return 0;
        }

        [$payload] = $this->buildAggregatedRowsForPeriod($period, null, null, $sourceMetadata);

        $payload = $this->deduplicateSnapshotPayload($payload);

        if ($payload === []) {
            DB::table(self::SNAPSHOT_TABLE)->where('snapshot_period', $period)->delete();

            return 0;
        }

        foreach (array_chunk($payload, 250) as $chunk) {
            DB::table(self::SNAPSHOT_TABLE)->upsert(
                $chunk,
                ['snapshot_period', 'kanca_key', 'unit_key'],
                array_merge(
                    ['kanca_label', 'unit_label'],
                    self::METRIC_COLUMNS,
                    ['source_row_count'],
                    $this->availableSourceMetadataColumns(),
                    ['updated_at']
                )
            );
        }

        $validIds = array_column($payload, 'uniqueid_dhs');
        if (!$force) {
            DB::table(self::SNAPSHOT_TABLE)
                ->where('snapshot_period', $period)
                ->whereNotIn('uniqueid_dhs', $validIds)
                ->delete();
        }

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
            if (Schema::hasTable(self::SNAPSHOT_TABLE)) {
                $kancas = DB::table(self::SNAPSHOT_TABLE)
                    ->where('snapshot_period', $effectivePeriod)
                    ->selectRaw("kanca_label as label, kanca_label as value")
                    ->distinct()
                    ->orderBy('label')
                    ->get();

                $unitQuery = DB::table(self::SNAPSHOT_TABLE)
                    ->where('snapshot_period', $effectivePeriod)
                    ->selectRaw("unit_label as label, unit_label as value, kanca_label as kanca_value")
                    ->whereRaw('unit_label <> kanca_label'); // Exclude summary rows

                if ($normalizedKanca !== []) {
                    $unitQuery->whereIn('kanca_label', $normalizedKanca);
                }

                $units = $unitQuery
                    ->distinct()
                    ->orderBy('label')
                    ->get();
            }
        } catch (Throwable $e) {
            \Log::error("Failed to fetch filter options from snapshot", ['error' => $e->getMessage()]);
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
                ->filter(fn (array $row) => !$this->isSummaryScopeRow($row))
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
        $h1Metrics = $comparisonPeriods['h1'] ? ($metricsByPeriod[$comparisonPeriods['h1']] ?? $this->finalizeMetrics($this->emptyMetrics())) : $this->finalizeMetrics($this->emptyMetrics());
        $rkaMetrics = $this->buildRkaMetrics($comparisonPeriods['rka'], $selectedPeriod, $kancaKey, $unitKey, false);
        $rkaDecMetrics = $this->buildRkaMetrics($comparisonPeriods['rka'], $selectedPeriod, $kancaKey, $unitKey, true);

        $rows = collect(self::ROW_DEFINITIONS)->map(function (array $definition) use (
            $currentMetrics,
            $yoyMetrics,
            $ytdMetrics,
            $m2Metrics,
            $mtmMetrics,
            $mtdMetrics,
            $h1Metrics,
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
                    'h1' => (float) ($h1Metrics[$metricKey] ?? 0),
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
                    'dtd' => (float) ($currentMetrics[$metricKey] ?? 0) - (float) ($h1Metrics[$metricKey] ?? 0),
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

        // Use snapshot when:
        // 1. No filters at all, OR
        // 2. Kanca filter only (no unit filter)
        $hasKancaFilter = $normalizedKanca !== [];
        $hasUnitFilter = $normalizedUnit !== [];
        $useSnapshot = $this->canUseSnapshotMetrics() && !$hasUnitFilter;

        if ($useSnapshot) {
            $selects = collect(self::METRIC_COLUMNS)
                ->map(fn (string $column) => "COALESCE(SUM({$column}), 0) as {$column}")
                ->implode(",\n");

            $query = DB::table(self::SNAPSHOT_TABLE)
                ->whereIn('snapshot_period', $normalizedPeriods)
                ->whereColumn('kanca_key', 'unit_key');

            // If kanca filter applied, filter by kanca_key (use slug format)
            if ($hasKancaFilter) {
                // Convert both raw and normalized kanca values to slugified keys
                $slugifiedKanca = collect($normalizedKanca)
                    ->map(function (string $value) {
                        // First try to normalize as a kanca label (handles raw db values)
                        $normalized = $this->normalizeKancaLabel($value);
                        if ($normalized !== '') {
                            return $this->slugKey($normalized);
                        }
                        // If that doesn't work, just slugify the value directly
                        return $this->slugKey($value);
                    })
                    ->unique()
                    ->all();
                $query->whereIn('kanca_key', $slugifiedKanca);
            }

            $query->groupBy('snapshot_period')
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
        $payload = $this->filterPayloadForMetricRollup($payload, $kancaKey, $unitKey);
        $metrics = $this->emptyMetrics();

        foreach ($payload as $row) {
            $this->accumulateMetrics($metrics, $row);
        }

        return $this->finalizeMetrics($metrics);
    }

    private function buildAggregatedRowsForPeriod(
        string $period,
        array|string|null $kancaKey = null,
        array|string|null $unitKey = null,
        ?array $sourceMetadata = null
    ): array
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
                'total_simpanan',
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

        foreach ($this->fetchRecoveryAggregates($period, $kancaKey, $unitKey) as $row) {
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
        $detailByKanca = [];

        // First pass: collect all rows and group them by kanca
        foreach ($buckets as $row) {
            $payload[] = $row;
            
            // Track all rows for each kanca (including the kanca's own summary bucket)
            if (!isset($detailByKanca[$row['kanca_key']])) {
                $detailByKanca[$row['kanca_key']] = [];
            }
            $detailByKanca[$row['kanca_key']][] = $row;
        }

        // Second pass: build final payload with only DETAIL rows (skip rows that would be summary rows)
        // Summary rows will be created explicitly in the third pass to ensure proper aggregation
        $finalPayload = [];

        foreach ($payload as $row) {
            // Skip any rows where kanca_key === unit_key; those will be created in third pass
            if (($row['kanca_key'] ?? '') === ($row['unit_key'] ?? '')) {
                continue;
            }

            $metrics = $this->finalizeMetrics($row);
            $finalPayload[] = array_merge(
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
                ],
                $this->filterSourceMetadataForPayload($sourceMetadata)
            );
        }

        // Third pass: create summary rows by aggregating all detail rows
        foreach ($detailByKanca as $kancaKey => $detailRows) {
            $aggregated = $this->emptyMetrics();
            $aggregated['kanca_key'] = $kancaKey;

            $firstDetail = $detailRows[0];
            $aggregated['kanca_label'] = $firstDetail['kanca_label'];
            $aggregated['unit_key'] = $kancaKey;
            $aggregated['unit_label'] = $firstDetail['kanca_label'];

            foreach ($detailRows as $detail) {
                $this->accumulateMetrics($aggregated, $detail);
            }

            $metrics = $this->finalizeMetrics($aggregated);

            $finalPayload[] = array_merge(
                [
                    'uniqueid_dhs' => md5(implode('|', ['dhs', $period, $aggregated['kanca_key'], $aggregated['unit_key']])),
                    'snapshot_period' => $period,
                    'kanca_key' => $aggregated['kanca_key'],
                    'kanca_label' => $aggregated['kanca_label'],
                    'unit_key' => $aggregated['unit_key'],
                    'unit_label' => $aggregated['unit_label'],
                ],
                collect(self::METRIC_COLUMNS)->mapWithKeys(fn (string $metric) => [$metric => (float) ($metrics[$metric] ?? 0)])->all(),
                [
                    'source_row_count' => $sourceRowCount,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                $this->filterSourceMetadataForPayload($sourceMetadata)
            );
        }

        return [$finalPayload, $sourceRowCount];
    }

    private function filterPayloadForMetricRollup(array $payload, array|string|null $kancaKey, array|string|null $unitKey): array
    {
        $normalizedUnit = $this->normalizeFilterValues($unitKey);

        if ($normalizedUnit !== []) {
            return array_values(array_filter(
                $payload,
                fn (array $row) => !$this->isSummaryScopeRow($row)
            ));
        }

        return array_values(array_filter(
            $payload,
            fn (array $row) => $this->isSummaryScopeRow($row)
        ));
    }

    private function deduplicateSnapshotPayload(array $payload): array
    {
        $deduplicated = [];

        foreach ($payload as $row) {
            $compositeKey = implode('|', [
                (string) ($row['snapshot_period'] ?? ''),
                (string) ($row['kanca_key'] ?? ''),
                (string) ($row['unit_key'] ?? ''),
            ]);

            if (!isset($deduplicated[$compositeKey])) {
                $deduplicated[$compositeKey] = $row;
                continue;
            }

            foreach (self::METRIC_COLUMNS as $metric) {
                $deduplicated[$compositeKey][$metric] = (float) ($deduplicated[$compositeKey][$metric] ?? 0)
                    + (float) ($row[$metric] ?? 0);
            }

            $deduplicated[$compositeKey]['source_row_count'] = max(
                (int) ($deduplicated[$compositeKey]['source_row_count'] ?? 0),
                (int) ($row['source_row_count'] ?? 0)
            );
            $deduplicated[$compositeKey]['updated_at'] = $row['updated_at'] ?? $deduplicated[$compositeKey]['updated_at'] ?? now();
        }

        return array_values($deduplicated);
    }

    private function isSummaryScopeRow(array $row): bool
    {
        return (string) ($row['kanca_key'] ?? '') !== ''
            && (string) ($row['kanca_key'] ?? '') === (string) ($row['unit_key'] ?? '');
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
            ->selectRaw("SUM(COALESCE(ss.saldo, 0)) as total_simpanan")
            ->groupBy('raw_kantor_cabang', 'raw_unit_kerja');

        $normalizedKanca = $this->normalizeFilterValues($kancaKey);
        if ($normalizedKanca !== []) {
            // Build WHERE clauses that match raw db values against normalized filter values
            $kancaConditions = collect($normalizedKanca)
                ->map(fn (string $value) => $this->buildFilterCondition('ss.nama_cabang', $value))
                ->filter()
                ->all();
            
            if (!empty($kancaConditions)) {
                $query->where(function ($q) use ($kancaConditions) {
                    foreach ($kancaConditions as $condition) {
                        $q->orWhereRaw($condition);
                    }
                });
            }
        }

        $normalizedUnit = $this->normalizeFilterValues($unitKey);
        if ($normalizedUnit !== []) {
            // Build WHERE clauses that match raw db values against normalized filter values
            $unitConditions = collect($normalizedUnit)
                ->map(fn (string $value) => $this->buildFilterCondition('ss.nama_uker', $value))
                ->filter()
                ->all();
            
            if (!empty($unitConditions)) {
                $query->where(function ($q) use ($unitConditions) {
                    foreach ($unitConditions as $condition) {
                        $q->orWhereRaw($condition);
                    }
                });
            }
        }

        return $query->get();
    }

    private function fetchLoanAggregates(string $period, array|string|null $kancaKey = null, array|string|null $unitKey = null): Collection
    {
        $segment = "UPPER(TRIM(COALESCE(sp.segmen_dashboard, '')))";
        $productDashboard = "UPPER(TRIM(COALESCE(sp.produk_dashboard, '')))";
        $product = "UPPER(TRIM(COALESCE(sp.produk, '')))";
        $segmen_2025 = "UPPER(TRIM(COALESCE(sp.segmen_2025, '')))";
        $balance = 'COALESCE(sp.baki_debet, 0)';
        $kol = "CAST(NULLIF(TRIM(COALESCE(sp.kolektabilitas_one_obligor, '')), '') AS UNSIGNED)";

        $query = DB::table(self::LOAN_TABLE . ' as sp')
            ->whereIn('sp.month_day_year_of_periode', $this->sourcePeriodRawCandidates(self::LOAN_TABLE, $period))
            ->selectRaw("TRIM(COALESCE(sp.nama_cabang, '')) as raw_cabang")
            ->selectRaw("TRIM(COALESCE(sp.nama_uker, '')) as raw_unit");

        $normalizedKanca = $this->normalizeFilterValues($kancaKey);
        if ($normalizedKanca !== []) {
            // Build WHERE clauses that match raw db values against normalized filter values
            $kancaConditions = collect($normalizedKanca)
                ->map(fn (string $value) => $this->buildFilterCondition('sp.nama_cabang', $value))
                ->filter()
                ->all();
            
            if (!empty($kancaConditions)) {
                $query->where(function ($q) use ($kancaConditions) {
                    foreach ($kancaConditions as $condition) {
                        $q->orWhereRaw($condition);
                    }
                });
            }
        }

        $normalizedUnit = $this->normalizeFilterValues($unitKey);
        if ($normalizedUnit !== []) {
            // Build WHERE clauses that match raw db values against normalized filter values
            $unitConditions = collect($normalizedUnit)
                ->map(fn (string $value) => $this->buildFilterCondition('sp.nama_uker', $value))
                ->filter()
                ->all();
            
            if (!empty($unitConditions)) {
                $query->where(function ($q) use ($unitConditions) {
                    foreach ($unitConditions as $condition) {
                        $q->orWhereRaw($condition);
                    }
                });
            }
        }

        foreach ($this->loanMetricDefinitions($segment, $productDashboard, $product, $segmen_2025) as $alias => $condition) {
            // Handle metrics with multiple conditions (array) vs single condition (string)
            if (is_array($condition)) {
                // Multiple conditions: combine them with CASE statements
                $orConditions = implode(' OR ', $condition);
                $combinedCondition = "({$orConditions})";
            } else {
                // Single condition
                $combinedCondition = $condition;
            }
            
            $query->selectRaw("SUM(CASE WHEN {$combinedCondition} THEN {$balance} ELSE 0 END) as {$alias}_os");
            $query->selectRaw("SUM(CASE WHEN {$combinedCondition} AND {$kol} = 2 THEN {$balance} ELSE 0 END) as {$alias}_sml");
            $query->selectRaw("SUM(CASE WHEN {$combinedCondition} AND {$kol} > 2 THEN {$balance} ELSE 0 END) as {$alias}_npl");
        }

        // NOTE: total_os, total_sml_abs_non_commercial, total_npl_abs_non_commercial are computed in finalizeMetrics
        return $query
            ->groupBy('raw_cabang', 'raw_unit')
            ->get();
    }

    private function fetchRecoveryAggregates(string $period, array|string|null $kancaKey = null, array|string|null $unitKey = null): Collection
    {
        $normalizedPeriod = $this->normalizeDate($period);

        // Tier 1: Check Cognos Recovery Table
        if ($normalizedPeriod && Schema::hasTable('cognos_recovery')) {
            $exists = DB::table('cognos_recovery')->where('periode', $normalizedPeriod)->exists();
            if ($exists) {
                return $this->fetchCognosRecoveryAggregates($normalizedPeriod, $kancaKey, $unitKey);
            }
        }

        // Tier 2: Fallback to DH Recovery logic (PH-based)
        return $this->fetchPhAggregates($period, $kancaKey, $unitKey);
    }

    private function fetchCognosRecoveryAggregates(string $normalizedPeriod, array|string|null $kancaKey = null, array|string|null $unitKey = null): Collection
    {
        $normalizedKanca = $this->normalizeFilterValues($kancaKey);
        $normalizedUnit = $this->normalizeFilterValues($unitKey);

        $query = DB::table('cognos_recovery')
            ->where('periode', $normalizedPeriod);

        if ($normalizedKanca !== []) {
            $query->whereIn(
                DB::raw("UPPER(TRIM(COALESCE(cabang, '')))"), 
                array_map('strtoupper', $normalizedKanca)
            );
        }

        if ($normalizedUnit !== []) {
            $query->whereIn(
                DB::raw("UPPER(TRIM(COALESCE(unit_kerja, '')))"), 
                array_map('strtoupper', $normalizedUnit)
            );
        }

        return $query
            ->selectRaw("TRIM(COALESCE(cabang, '')) as raw_kanca")
            ->selectRaw("TRIM(COALESCE(unit_kerja, '')) as raw_unit")
            ->selectRaw("SUM(CASE WHEN UPPER(TRIM(COALESCE(segmen_bisnis_2025, ''))) = 'SMALL' THEN COALESCE(total_recovery, 0) ELSE 0 END) as rec_dh_small")
            ->selectRaw("SUM(CASE WHEN UPPER(TRIM(COALESCE(segmen_bisnis_2025, ''))) = 'CONSUMER' THEN COALESCE(total_recovery, 0) ELSE 0 END) as rec_dh_consumer")
            ->selectRaw("SUM(CASE WHEN UPPER(TRIM(COALESCE(segmen_bisnis_2025, ''))) IN ('MICRO', 'MIKRO') THEN COALESCE(total_recovery, 0) ELSE 0 END) as rec_dh_micro")
            ->selectRaw("SUM(COALESCE(total_recovery, 0)) as rec_dh_total")
            ->selectRaw("0 as ph_tupok")
            ->selectRaw("0 as ph_lunas")
            ->groupBy('raw_kanca', 'raw_unit')
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

        // Instead of exact match, find latest available PH period <= requested period
        $currentPhPeriod = DB::table('lw325_ph')
            ->where('periode', '<=', $normalizedCurrentPeriod)
            ->orderBy('periode', 'desc')
            ->pluck('periode')
            ->first();

        if ($currentPhPeriod === null) {
            return collect();
        }

        $previousPhPeriod = $this->resolvePreviousPhPeriod($currentPhPeriod);

        if ($previousPhPeriod === null) {
            return collect();
        }

        $normalizedKanca = $this->normalizeFilterValues($kancaKey);
        $normalizedUnit = $this->normalizeFilterValues($unitKey);

        // OPTIMIZATION: Single combined query for TUPOK + LUNAS
        // Instead of 2 separate queries with UNION ALL, we create a single subquery
        // that identifies both types, then aggregate once. This reduces:
        // - Query execution from 3 (tupok + lunas + final aggregation) to 1
        // - Result set processing overhead
        // - Database buffer pool pressure
        // Expected performance gain: 10-15%

        $tupokQuery = DB::table('lw325_ph as n')
            ->join('lw325_ph as o', function ($join) use ($previousPhPeriod, $currentPhPeriod) {
                $join->on('n.acctno', '=', 'o.acctno')
                    ->on('n.kanca', '=', 'o.kanca')
                    ->on('n.unit', '=', 'o.unit')
                    ->where('n.periode', '=', $currentPhPeriod)
                    ->where('o.periode', '=', $previousPhPeriod);
            })
            ->selectRaw("n.kanca as n_kanca")
            ->selectRaw("n.unit as n_unit")
            ->selectRaw("n.segmen_dashboard as n_segment")
            ->selectRaw("COALESCE(o.pokok, 0) as amount")
            ->selectRaw("'tupok' as recovery_type")
            ->whereRaw('(COALESCE(o.pokok, 0) - COALESCE(n.pokok, 0)) > 0')
            ->whereNotNull('n.acctno')
            ->where('n.acctno', '<>', '');

        // Apply index-friendly filters directly on source columns (not on TRIM/COALESCE results)
        if ($normalizedKanca !== []) {
            $tupokQuery->whereIn('n.kanca', $normalizedKanca);
        }
        if ($normalizedUnit !== []) {
            $tupokQuery->whereIn('n.unit', $normalizedUnit);
        }

        $lumasQuery = DB::table('lw325_ph as o')
            ->leftJoin('lw325_ph as n', function ($join) use ($previousPhPeriod, $currentPhPeriod) {
                $join->on('o.acctno', '=', 'n.acctno')
                    ->on('o.kanca', '=', 'n.kanca')
                    ->on('o.unit', '=', 'n.unit')
                    ->where('o.periode', '=', $previousPhPeriod)
                    ->where('n.periode', '=', $currentPhPeriod);
            })
            ->where('o.periode', $previousPhPeriod)
            ->whereNull('n.acctno')
            ->whereNotNull('o.acctno')
            ->where('o.acctno', '<>', '')
            ->selectRaw("o.kanca as n_kanca")
            ->selectRaw("o.unit as n_unit")
            ->selectRaw("o.segmen_dashboard as n_segment")
            ->selectRaw("COALESCE(o.pokok, 0) as amount")
            ->selectRaw("'lunas' as recovery_type");

        // Apply index-friendly filters directly on source columns
        if ($normalizedKanca !== []) {
            $lumasQuery->whereIn('o.kanca', $normalizedKanca);
        }
        if ($normalizedUnit !== []) {
            $lumasQuery->whereIn('o.unit', $normalizedUnit);
        }

        $combinedSubquery = $tupokQuery->unionAll($lumasQuery);

        // Final aggregation: single pass over combined recovery data
        // TRIM/COALESCE now applied only to SELECT for output formatting, not for filtering
        return DB::query()
            ->fromSub($combinedSubquery, 'ph_summary')
            ->selectRaw("TRIM(COALESCE(n_kanca, '')) as raw_kanca")
            ->selectRaw("TRIM(COALESCE(n_unit, '')) as raw_unit")
            ->selectRaw("
                SUM(
                    CASE
                        WHEN recovery_type = 'tupok'
                        THEN amount
                        ELSE 0
                    END
                ) as ph_tupok
            ")
            ->selectRaw("
                SUM(
                    CASE
                        WHEN recovery_type = 'lunas'
                        THEN amount
                        ELSE 0
                    END
                ) as ph_lunas
            ")
            ->selectRaw("
                SUM(
                    CASE
                        WHEN UPPER(TRIM(COALESCE(n_segment, ''))) = 'SMALL'
                        THEN amount
                        ELSE 0
                    END
                ) as rec_dh_small
            ")
            ->selectRaw("
                SUM(
                    CASE
                        WHEN UPPER(TRIM(COALESCE(n_segment, ''))) = 'CONSUMER'
                        THEN amount
                        ELSE 0
                    END
                ) as rec_dh_consumer
            ")
            ->selectRaw("
                SUM(
                    CASE
                        WHEN UPPER(TRIM(COALESCE(n_segment, ''))) IN ('MICRO', 'MIKRO')
                        THEN amount
                        ELSE 0
                    END
                ) as rec_dh_micro
            ")
            ->selectRaw('SUM(amount) as rec_dh_total')
            ->groupBy('n_kanca', 'n_unit')
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
        $calcTotalSimpanan = $final['simpanan_ritel'] + $final['simpanan_mikro'] + $final['simpanan_wholesale'];
        if ($final['total_simpanan'] < $calcTotalSimpanan) {
            $final['total_simpanan'] = $calcTotalSimpanan;
        }
        $final['casa_ritel'] = $final['giro_ritel'] + $final['tabungan_ritel'];
        $final['casa_mikro'] = $final['giro_mikro'] + $final['tabungan_mikro'];
        $final['total_casa'] = $final['casa_ritel'] + $final['casa_mikro'];
        
        // Compute KECIL from subsegments (kecil_non_cashcoll + cashcoll)
        $final['kecil_os'] = $final['kecil_non_cashcoll_os'] + $final['cashcoll_os'];
        $final['kecil_sml'] = $final['kecil_non_cashcoll_sml'] + $final['cashcoll_sml'];
        $final['kecil_npl'] = $final['kecil_non_cashcoll_npl'] + $final['cashcoll_npl'];
        
        // Compute CONSUMER from subsegments (briguna_konsumer + kpr + kkb)
        $final['consumer_os'] = $final['briguna_konsumer_os'] + $final['kpr_os'] + $final['kkb_os'];
        $final['consumer_sml'] = $final['briguna_konsumer_sml'] + $final['kpr_sml'] + $final['kkb_sml'];
        $final['consumer_npl'] = $final['briguna_konsumer_npl'] + $final['kpr_npl'] + $final['kkb_npl'];
        
        // Compute MICRO from subsegments (briguna_mikro + kupedes + kur_mikro + kur_kecil + kur_kpp)
        $final['micro_os'] = $final['briguna_mikro_os'] + $final['kupedes_os'] + $final['kur_mikro_os'] + $final['kur_kecil_os'] + $final['kur_kpp_os'];
        $final['micro_sml'] = $final['briguna_mikro_sml'] + $final['kupedes_sml'] + $final['kur_mikro_sml'] + $final['kur_kecil_sml'] + $final['kur_kpp_sml'];
        $final['micro_npl'] = $final['briguna_mikro_npl'] + $final['kupedes_npl'] + $final['kur_mikro_npl'] + $final['kur_kecil_npl'] + $final['kur_kpp_npl'];
        
        // Compute SME from KECIL only (not including MEDIUM)
        $final['sme_os'] = $final['kecil_os'];
        $final['sme_sml'] = $final['kecil_sml'];
        $final['sme_npl'] = $final['kecil_npl'];
        
        $final['commercial_os'] = 0.0;
        // Compute TOTALS from subsegments (kecil, consumer, micro ONLY - medium excluded)
        $final['total_os_non_commercial'] = $final['kecil_os'] + $final['consumer_os'] + $final['micro_os'];
        $final['total_os'] = $final['commercial_os'] + $final['total_os_non_commercial'];
        $final['total_sml_abs_non_commercial'] = $final['kecil_sml'] + $final['consumer_sml'] + $final['micro_sml'];
        $final['total_npl_abs_non_commercial'] = $final['kecil_npl'] + $final['consumer_npl'] + $final['micro_npl'];
        
        // Compute percentages from totals
        $final['total_sml_pct_non_commercial'] = $this->safePercent($final['total_sml_abs_non_commercial'], $final['total_os_non_commercial']);
        $final['total_npl_pct_non_commercial'] = $this->safePercent($final['total_npl_abs_non_commercial'], $final['total_os_non_commercial']);

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
            'kecil_non_cashcoll_os' => ['mata_anggaran' => ['B.2.a. Kredit Kecil Non Cash Collateral'], 'uker_contains_any' => ['KC', 'KCP']],
            'cashcoll_os' => ['mata_anggaran' => ['B.2.b. Kredit Kecil Cash Collateral'], 'uker_contains_any' => ['KC', 'KCP']],
            'medium_os' => ['mata_anggaran' => ['B.3. MEDIUM']],
            'briguna_konsumer_os' => ['mata_anggaran' => ['B.5.a. Briguna'], 'uker_contains_any' => ['KC', 'KCP']],
            'kpr_os' => ['mata_anggaran' => ['B.5.b. KPR'], 'uker_contains_any' => ['KC', 'KCP']],
            'kkb_os' => ['mata_anggaran' => ['B.5.c. KKB'], 'uker_contains_any' => ['KC', 'KCP']],
            'micro_os' => ['mata_anggaran' => ['B.1. MIKRO'], 'uker_contains_any' => ['UNIT']],
            'briguna_mikro_os' => ['mata_anggaran' => ['B.1.b. Briguna Mikro'], 'uker_contains_any' => ['UNIT']],
            'kupedes_os' => ['mata_anggaran' => ['B.1.a. Kupedes Komersial'], 'uker_contains_any' => ['UNIT']],
            'kur_mikro_os' => ['mata_anggaran' => ['B.1.c. KUR Mikro'], 'uker_contains_any' => ['UNIT']],
            'kur_kecil_os' => ['mata_anggaran' => ['B.1.d. KUR Kecil'], 'uker_contains_any' => ['UNIT']],
            'kur_kpp_os' => ['mata_anggaran' => ['B.1.e. KPP'], 'uker_contains_any' => ['UNIT']],
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
        $final['simpanan_ritel'] = $final['giro_ritel'] + $final['deposito_ritel'] + $final['tabungan_ritel'];
        $final['simpanan_mikro'] = $final['giro_mikro'] + $final['deposito_mikro'] + $final['tabungan_mikro'];
        $final['simpanan_wholesale'] = $final['giro_wholesale'] + $final['deposito_wholesale'] + $final['tabungan_wholesale'];
        $final['total_simpanan'] = $final['simpanan_ritel'] + $final['simpanan_mikro'] + $final['simpanan_wholesale'];
        $final['casa_ritel'] = $final['giro_ritel'] + $final['tabungan_ritel'];
        $final['casa_mikro'] = $final['giro_mikro'] + $final['tabungan_mikro'];
        $final['total_casa'] = $final['casa_ritel'] + $final['casa_mikro'];
        $final['commercial_os'] = 0.0;
        $final['casa_pct'] = $this->safePercent($final['total_casa'], $final['total_simpanan']);
        // RKA LDR follows loan / savings, consistent with the live snapshot metrics.
        $final['ldr_non_commercial'] = $this->safePercent($final['total_os'], $final['total_simpanan']);
        $final['ldr_ritel_non_commercial'] = $this->safePercent($final['sme_os'] + $final['consumer_os'], $final['simpanan_ritel']);
        $final['ldr_mikro_non_commercial'] = $this->safePercent($final['micro_os'], $final['simpanan_mikro']);
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

    private function buildFilterCondition(string $column, string $filterValue): ?string
    {
        // filterValue can be:
        // 1. Raw db value: "00070 -- KC PONOROGO (Konsolidasi-MB)"
        // 2. Slugified key: "kc-ponorogo" or "unit-babadan-ponorogo"
        // 3. Clean name: "KC Ponorogo" or "UNIT Babadan Ponorogo"
        
        $filterValue = trim($filterValue);
        if ($filterValue === '') {
            return null;
        }

        // Try to extract meaningful parts from the filter value
        $parts = [];
        
        // If it looks like a slug (contains hyphens), un-slug it
        if (str_contains($filterValue, '-')) {
            // "kc-ponorogo" -> "ponorogo", "unit-babadan-ponorogo" -> "babadan ponorogo"
            $unsluggedParts = explode('-', $filterValue);
            if (str_starts_with($filterValue, 'unit-')) {
                // Remove 'unit' prefix for extraction
                $parts = array_slice($unsluggedParts, 1);
            } else {
                $parts = array_slice($unsluggedParts, 1); // Skip 'kc'
            }
        } else {
            // Clean the value and extract parts
            $cleaned = $this->cleanBranchValue($filterValue);
            
            // Extract keywords: branch names, unit keywords
            foreach (['PONOROGO', 'MADIUN', 'MAGETAN', 'NGAWI'] as $branch) {
                if (str_contains(strtoupper($cleaned), $branch)) {
                    $parts[] = strtolower($branch);
                }
            }
            
            // Extract unit names
            if (preg_match('/UNIT\s+(\w+)/i', $cleaned, $matches)) {
                $parts[] = strtolower($matches[1]);
            }
            
            // If no parts extracted yet, use the cleaned value
            if (empty($parts)) {
                $parts = array_map('strtolower', array_filter(explode(' ', $cleaned)));
            }
        }

        // Build a WHERE clause that matches if any part is found in the column
        if (empty($parts)) {
            return null;
        }

        $conditions = collect($parts)
            ->map(fn (string $part) => "UPPER({$column}) LIKE '%" . strtoupper($part) . "%'")
            ->implode(' OR ');

        return "({$conditions})";
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

    /**
     * @param array<int, string>|null $candidatePeriods
     * @param array<int, string> $sharedPeriods
     * @return array<int, string>
     */
    private function normalizeCandidatePeriods(?array $candidatePeriods, array $sharedPeriods): array
    {
        if ($candidatePeriods === null) {
            return $sharedPeriods;
        }

        $sharedLookup = array_flip($sharedPeriods);
        $normalized = [];

        foreach ($candidatePeriods as $period) {
            $value = $this->normalizeDate((string) $period);
            if ($value !== null && isset($sharedLookup[$value])) {
                $normalized[$value] = $value;
            }
        }

        $periods = array_values($normalized);
        rsort($periods);

        return $periods;
    }

    /**
     * @param array<int, string> $sharedPeriods
     * @return array<int, string>
     */
    private function resolveAutomaticStaleCandidatePeriods(array $sharedPeriods): array
    {
        $candidates = [];

        if (($sharedPeriods[0] ?? null) !== null) {
            $candidates[] = $sharedPeriods[0];
        }

        foreach ($this->resolveRecentSourcePeriods(self::LOAN_TABLE, $this->sourcePeriodColumn(self::LOAN_TABLE)) as $period) {
            $candidates[] = $period;
        }

        foreach ($this->resolveRecentSourcePeriods(self::SAVINGS_TABLE, $this->sourcePeriodColumn(self::SAVINGS_TABLE)) as $period) {
            $candidates[] = $period;
        }

        foreach ($this->resolveRecentSourcePeriods('lw325_ph', 'periode') as $phPeriod) {
            foreach ($this->resolveAffectedSnapshotPeriodsForPh($phPeriod) as $snapshotPeriod) {
                $candidates[] = $snapshotPeriod;
            }
        }

        $latestPhPeriod = $this->resolveLatestSourcePeriod('lw325_ph', 'periode');
        if ($latestPhPeriod !== null) {
            foreach ($this->resolveAffectedSnapshotPeriodsForPh($latestPhPeriod) as $snapshotPeriod) {
                $candidates[] = $snapshotPeriod;
            }
        }

        return array_values(array_unique($candidates));
    }

    /**
     * @return array<int, string>
     */
    private function resolveRecentSourcePeriods(string $table, string $periodColumn): array
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $periodColumn)) {
            return [];
        }

        $query = DB::table($table)
            ->select($periodColumn)
            ->distinct()
            ->orderByDesc($periodColumn)
            ->limit(10);

        if (Schema::hasColumn($table, 'updated_at')) {
            $query->where('updated_at', '>=', now()->subHours(self::AUTO_SYNC_RECENT_SOURCE_HOURS));
        }

        return $query
            ->pluck($periodColumn)
            ->map(fn ($value) => $this->normalizeDate((string) $value))
            ->filter()
            ->values()
            ->all();
    }

    private function resolveLatestSourcePeriod(string $table, string $periodColumn): ?string
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $periodColumn)) {
            return null;
        }

        try {
            $value = DB::table($table)->max($periodColumn);

            return $this->normalizeDate((string) $value);
        } catch (Throwable) {
            return null;
        }
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

    private function buildSourceMetadata(string $period): ?array
    {
        if (!$this->sourceMetadataColumnsAvailable()) {
            return null;
        }

        try {
            $loanState = $this->sourceAggregateState(
                self::LOAN_TABLE,
                $this->sourcePeriodColumn(self::LOAN_TABLE),
                $this->sourcePeriodRawCandidates(self::LOAN_TABLE, $period),
                ['baki_debet']
            );

            $savingsState = $this->sourceAggregateState(
                self::SAVINGS_TABLE,
                $this->sourcePeriodColumn(self::SAVINGS_TABLE),
                $this->sourcePeriodRawCandidates(self::SAVINGS_TABLE, $period),
                ['saldo']
            );

            [$recoverySource, $recoveryPeriod, $recoveryState] = $this->sourceRecoveryState($period);

            $signaturePayload = [
                'period' => $this->normalizeDate($period) ?? $period,
                'loan' => $loanState,
                'savings' => $savingsState,
                'recovery_source' => $recoverySource,
                'recovery_period' => $recoveryPeriod,
                'recovery' => $recoveryState,
            ];

            return [
                'source_signature' => hash('sha256', json_encode($signaturePayload, JSON_UNESCAPED_UNICODE)),
                'source_loan_row_count' => (int) ($loanState['row_count'] ?? 0),
                'source_savings_row_count' => (int) ($savingsState['row_count'] ?? 0),
                'source_recovery_row_count' => (int) ($recoveryState['row_count'] ?? 0),
                'source_recovery_period' => $recoveryPeriod,
            ];
        } catch (Throwable $e) {
            Log::warning('Failed to build Dashboard Harian source metadata.', [
                'period' => $period,
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function sourceAggregateState(string $table, string $periodColumn, array $periodValues, array $numericColumns = []): array
    {
        if (!Schema::hasTable($table)) {
            return ['row_count' => 0];
        }

        $query = DB::table($table)
            ->whereIn($periodColumn, $periodValues)
            ->selectRaw('COUNT(*) as row_count');

        foreach ($numericColumns as $column) {
            if (Schema::hasColumn($table, $column)) {
                $alias = 'sum_' . $column;
                $query->selectRaw("COALESCE(SUM(COALESCE({$column}, 0)), 0) as {$alias}");
            }
        }

        foreach (['updated_at', 'created_at', 'id', 'uniqueid_namareport'] as $column) {
            if (Schema::hasColumn($table, $column)) {
                $query->selectRaw("MAX({$column}) as max_{$column}");
            }
        }

        $row = (array) $query->first();
        ksort($row);

        return $row;
    }

    private function sourceRecoveryState(string $period): array
    {
        $normalizedPeriod = $this->normalizeDate($period);

        if ($normalizedPeriod && Schema::hasTable('cognos_recovery')) {
            $exists = DB::table('cognos_recovery')->where('periode', $normalizedPeriod)->exists();
            if ($exists) {
                return [
                    'cognos_recovery',
                    $normalizedPeriod,
                    $this->sourceAggregateState('cognos_recovery', 'periode', [$normalizedPeriod], ['total_recovery']),
                ];
            }
        }

        if (!$normalizedPeriod || !Schema::hasTable('lw325_ph')) {
            return ['none', null, ['row_count' => 0]];
        }

        $currentPhPeriod = DB::table('lw325_ph')
            ->where('periode', '<', $normalizedPeriod)
            ->orderBy('periode', 'desc')
            ->value('periode');

        if (!$currentPhPeriod) {
            return ['lw325_ph', null, ['row_count' => 0]];
        }

        $previousPhPeriod = $this->resolvePreviousPhPeriod((string) $currentPhPeriod);
        $periods = array_values(array_filter([(string) $currentPhPeriod, $previousPhPeriod]));

        return [
            'lw325_ph',
            (string) $currentPhPeriod,
            $this->sourceAggregateState('lw325_ph', 'periode', $periods, ['pokok']),
        ];
    }

    private function snapshotSourceIsFresh(string $period, ?array $sourceMetadata): bool
    {
        if ($sourceMetadata === null || !$this->sourceMetadataColumnsAvailable()) {
            return true;
        }

        $signatures = DB::table(self::SNAPSHOT_TABLE)
            ->where('snapshot_period', $period)
            ->select('source_signature')
            ->distinct()
            ->pluck('source_signature')
            ->filter(fn ($value) => trim((string) $value) !== '')
            ->values()
            ->all();

        if ($signatures === []) {
            return false;
        }

        return count($signatures) === 1
            && (string) $signatures[0] === (string) ($sourceMetadata['source_signature'] ?? '');
    }

    private function filterSourceMetadataForPayload(?array $sourceMetadata): array
    {
        if ($sourceMetadata === null) {
            return [];
        }

        $availableColumns = $this->availableSourceMetadataColumns();
        if ($availableColumns === []) {
            return [];
        }

        return array_intersect_key($sourceMetadata, array_flip($availableColumns));
    }

    private function availableSourceMetadataColumns(): array
    {
        if (!Schema::hasTable(self::SNAPSHOT_TABLE)) {
            return [];
        }

        return array_values(array_filter(
            self::SOURCE_METADATA_COLUMNS,
            fn (string $column) => Schema::hasColumn(self::SNAPSHOT_TABLE, $column)
        ));
    }

    private function sourceMetadataColumnsAvailable(): bool
    {
        return count($this->availableSourceMetadataColumns()) === count(self::SOURCE_METADATA_COLUMNS);
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

    private function loanMetricDefinitions(string $segment, string $productDashboard, string $product, string $segmen_2025): array
    {
        $microSegment = "{$segment} IN ('MICRO', 'MIKRO')";
        $microSegment_2025 = "{$segmen_2025} IN ('MICRO', 'MIKRO')";

        return [
            'commercial' => "{$segment} = 'COMMERCIAL'",
            // NOTE: 'sme' and 'kecil' are computed in finalizeMetrics from subsegments, not queried
            'kecil_non_cashcoll' => "{$segment} = 'SMALL' AND {$productDashboard} = 'COMMERCIAL' AND {$segmen_2025} = 'SMALL'",
            'cashcoll' => "{$segment} = 'SMALL' AND {$productDashboard} IN ('CASHCALL', 'CASHCOLL') AND {$segmen_2025} = 'SMALL'",
            'medium' => [
                "{$segment} = 'MEDIUM' AND {$productDashboard} = 'MEDIUM'",
                "{$segment} = 'SMALL' AND {$segmen_2025} = 'MEDIUM'",
                "{$segment} = 'MEDIUM' AND {$segmen_2025} = 'COMMERCIAL'"
            ],
            // NOTE: 'consumer' is computed in finalizeMetrics from subsegments, not queried
            'briguna_konsumer' => "{$segment} = 'CONSUMER' AND {$productDashboard} = 'BRIGUNA-KONSUMER'",
            'kpr' => "{$segment} = 'CONSUMER' AND {$productDashboard} = 'KPR'",
            'kkb' => "{$segment} = 'CONSUMER' AND {$productDashboard} = 'KKB'",
            // NOTE: 'micro' is computed in finalizeMetrics from subsegments, not queried
            'briguna_mikro' => "{$microSegment} AND {$productDashboard} = 'BRIGUNA-MIKRO'",
            'kupedes' => [
                "{$microSegment} AND {$product} = 'KUPEDES'",
                "{$microSegment} AND {$productDashboard} = 'CASH COLLATERAL'"
            ],
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
            'total_os',
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

    public function fetchTimeseriesTrend(array $months, string $category, array|string|null $kancaKey = null, array|string|null $unitKey = null): array
    {
        if (!$this->canUseSnapshotMetrics() || $months === []) {
            return [
                'series' => [],
                'area_total' => [],
            ];
        }

        $columnMap = [
            'simpanan' => 'total_simpanan',
            'pinjaman' => 'total_os_non_commercial',
            'sml' => 'total_sml_abs_non_commercial',
            'npl' => 'total_npl_abs_non_commercial',
        ];

        $metric = $columnMap[$category] ?? 'total_simpanan';
        $normalizedKanca = $this->normalizeFilterValues($kancaKey);
        $normalizedUnit = $this->normalizeFilterValues($unitKey);

        $query = DB::table(self::SNAPSHOT_TABLE)
            ->selectRaw('snapshot_period')
            ->selectRaw('kanca_label')
            ->selectRaw("SUM({$metric}) as value")
            ->where(function ($q) use ($months) {
                foreach ($months as $month) {
                    $start = "{$month}-01";
                    $end = "{$month}-31";
                    $q->orWhereBetween('snapshot_period', [$start, $end]);
                }
            });

        if ($normalizedUnit !== []) {
            // Filter by specific units
            $query->whereIn('unit_key', array_map([$this, 'slugKey'], $normalizedUnit));
        } elseif ($normalizedKanca !== []) {
            // Filter by kanca, but only take the kanca-level summary row (kanca_key == unit_key)
            $query->whereIn('kanca_key', array_map([$this, 'slugKey'], $normalizedKanca))
                  ->whereRaw('kanca_key = unit_key');
        } else {
            // Total Area (All Kanca) - Only take summary rows to avoid double counting
            $query->whereRaw('kanca_key = unit_key');
        }

        $results = $query->groupBy('snapshot_period', 'kanca_label')
            ->orderBy('snapshot_period')
            ->get();

        $series = [];
        $areaTotal = [];

        foreach ($results as $row) {
            $month = substr($row->snapshot_period, 0, 7);
            $day = (int) substr($row->snapshot_period, 8, 2);
            $kanca = $row->kanca_label;

            if ($day < 1 || $day > 31) continue;

            if (!isset($series[$kanca])) {
                $series[$kanca] = [];
            }
            if (!isset($series[$kanca][$month])) {
                $series[$kanca][$month] = array_fill(1, 31, null);
            }
            // Scale to Billions (Rp M)
            $scaledValue = (float) $row->value / 1000000000;
            $series[$kanca][$month][$day] = $scaledValue;

            if (!isset($areaTotal[$month])) {
                $areaTotal[$month] = array_fill(1, 31, null);
            }
            $areaTotal[$month][$day] = ($areaTotal[$month][$day] ?? 0) + $scaledValue;
        }

        // Convert series to flat 0-indexed arrays [0...30] for Chart.js
        $finalSeries = [];
        foreach ($series as $kanca => $monthData) {
            $finalSeries[$kanca] = [];
            foreach ($monthData as $month => $days) {
                $finalSeries[$kanca][$month] = array_values($days);
            }
        }

        $finalAreaTotal = [];
        foreach ($areaTotal as $month => $days) {
            $finalAreaTotal[$month] = array_values($days);
        }

        return [
            'series' => $finalSeries,
            'area_total' => $finalAreaTotal,
        ];
    }
}
