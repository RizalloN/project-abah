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
    private const DLY_KAP_TABLE = 'dly_kap_resegmentasi';
    private const L1133_TABLE = 'l1133';
    private const SAVINGS_TABLE = 'ssa_simpanan';
    private const HOURLY_DPK_TABLE = 'hourly_dpk';
    private const SOURCE_SIGNATURE_VERSION = 'ssa-loan-l1133-micro-overlay-v3';
    private const AUTO_SYNC_RECENT_SOURCE_HOURS = 6;
    private const AREA_6_LABEL = 'Area 6';
    private const ALL_UNIT_LABEL = 'Semua Unit Kerja';
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
    private ?array $availableSourceMetadataColumnsCache = null;
    private ?array $availableSnapshotColumnsCache = null;
    private ?bool $canUseSnapshotMetricsCache = null;
    private array $unitScopeMapCache = [];
    /** Memoize resolveSharedPeriods result within a single request lifecycle. */
    private ?array $sharedPeriodsRequestCache = null;
    /** Per-request existence cache: avoids repeated COUNT/EXISTS per period. */
    private array $snapshotExistenceCache = [];
    private bool $snapshotExistenceCacheWarmed = false;
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
            $this->reportProgress($progress, $snapshotPeriod, $index, $totalPeriods);
            $results[$snapshotPeriod] = $this->buildPeriodSnapshot($snapshotPeriod, $force);
            $this->reportProgress($progress, $snapshotPeriod, $index + 1, $totalPeriods, (int) ($results[$snapshotPeriod] ?? 0));
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
            if ($candidatePeriods !== null) {
                $periodsToCheck = $this->normalizeExplicitCandidatePeriods($candidatePeriods);
                $existingSnapshots = $this->snapshotCountsForPeriods($periodsToCheck);

                $missingPeriods = [];
                $staleCandidatePeriods = $periodsToCheck;
            } else {
                $sharedPeriods = $this->resolveSharedPeriods();
                if ($sharedPeriods === []) {
                    return ['built' => 0, 'failed' => 0, 'missing' => [], 'stale' => [], 'checked' => 0];
                }

                $existingSnapshots = $this->snapshotCountsForPeriods();

                $missingPeriods = array_values(array_filter(
                    $sharedPeriods,
                    fn (string $period) => ($existingSnapshots[$period] ?? 0) <= 0
                ));
                $staleCandidatePeriods = $this->normalizeCandidatePeriods(
                    $this->resolveAutomaticStaleCandidatePeriods($sharedPeriods),
                    $sharedPeriods
                );

                $periodsToCheck = array_values(array_unique(array_merge($missingPeriods, $staleCandidatePeriods)));
            }

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
                $hasDuplicateKeys = $this->snapshotPeriodHasDuplicateKeys($period);
                if ($hasDuplicateKeys || !$this->snapshotSourceIsFresh($period, $sourceMetadata)) {
                    $stalePeriods[] = $period;

                    if ($hasDuplicateKeys) {
                        Log::warning('Dashboard Harian snapshot duplicate keys detected; scheduling rebuild.', [
                            'period' => $period,
                        ]);
                    }
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

        $nextMonthEnd = Carbon::parse($normalizedPhPeriod)->addMonthNoOverflow()->endOfMonth()->toDateString();

        return array_values(array_filter(
            $sharedPeriodsAsc,
            fn(string $p) => $p >= $normalizedPhPeriod && $p <= $nextMonthEnd
        ));
    }

    public function resolveAffectedSnapshotPeriodsForLoanFallback(string $sourceTable, ?string $sourcePeriod = null): array
    {
        $sharedPeriods = $this->resolveSharedPeriods();
        if ($sharedPeriods === []) {
            return [];
        }

        $normalizedSourcePeriod = $this->normalizeDate($sourcePeriod);
        if ($normalizedSourcePeriod === null) {
            return $sharedPeriods;
        }

        $sharedPeriodsAsc = $sharedPeriods;
        sort($sharedPeriodsAsc);

        return match ($sourceTable) {
            self::DLY_KAP_TABLE => array_values(array_filter(
                $sharedPeriodsAsc,
                fn (string $period): bool => $period === $normalizedSourcePeriod
            )),
            self::L1133_TABLE => $this->resolveAffectedSnapshotPeriodsForL1133($normalizedSourcePeriod, $sharedPeriodsAsc),
            default => [],
        };
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
        if (!Schema::hasTable(self::SNAPSHOT_TABLE) || !Schema::hasTable(self::LOAN_TABLE) || !$this->hasAnySavingsSourceTable()) {
            return 0;
        }

        $lockName = 'snapshot:dashboard_harian:build:' . $period;

        try {
            return Cache::lock($lockName, 600)->block(15, function () use ($period, $force): int {
                return $this->buildPeriodSnapshotUnlocked($period, $force);
            });
        } catch (LockTimeoutException) {
            // Another worker is building this period; return existence state (1 = has data, 0 = empty).
            return $this->snapshotPeriodHasData($period) ? 1 : 0;
        } catch (Throwable $e) {
            Log::warning('Dashboard Harian snapshot build skipped because snapshot lock is unavailable.', [
                'period' => $period,
                'force' => $force,
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);

            return $this->snapshotPeriodHasData($period) ? 1 : 0;
        }
    }

    private function buildPeriodSnapshotUnlocked(string $period, bool $force = false): int
    {
        $sourceMetadata = $this->buildSourceMetadata($period);
        $hasDuplicateKeys = $this->snapshotPeriodHasDuplicateKeys($period);

        if (!$force && $this->snapshotPeriodHasData($period)) {
            if (!$hasDuplicateKeys && $this->snapshotSourceIsFresh($period, $sourceMetadata)) {
                // Snapshot exists and source has not changed; skip rebuild.
                return (int) DB::table(self::SNAPSHOT_TABLE)->where('snapshot_period', $period)->count();
            }

            if ($hasDuplicateKeys) {
                Log::warning('Dashboard Harian snapshot duplicate keys detected; rebuilding period.', [
                    'period' => $period,
                ]);
            }
        }

        if (!$this->dashboardHarianSourceCombinationAvailable($period)) {
            // Guard: only remove the existing snapshot on an explicit forced rebuild.
            // For auto/web-triggered rebuilds (force=false) the source may be absent because
            // an import is still in progress. Preserving stale snapshot data is safer than
            // serving an empty response to concurrent web requests during that window.
            if ($force) {
                DB::table(self::SNAPSHOT_TABLE)->where('snapshot_period', $period)->delete();
                $this->snapshotExistenceCache[$period] = false;
                $this->bumpReportCacheVersion();
            }

            return 0;
        }

        [$payload] = $this->buildAggregatedRowsForPeriod($period, null, null, $sourceMetadata);

        $payload = $this->deduplicateSnapshotPayload($payload);
        $availableColumns = array_flip($this->availableSnapshotColumns());
        $payload = array_map(
            static fn (array $row): array => array_intersect_key($row, $availableColumns),
            $payload
        );

        if ($payload === []) {
            // Same guard: only discard the snapshot when the caller explicitly asks for it.
            // An empty aggregation result can indicate a transient import window, not a
            // permanent absence of data for this period.
            if ($force) {
                DB::table(self::SNAPSHOT_TABLE)->where('snapshot_period', $period)->delete();
                $this->snapshotExistenceCache[$period] = false;
                $this->bumpReportCacheVersion();
            }

            return 0;
        }

        // Atomic swap: InnoDB MVCC ensures concurrent readers continue seeing the
        // previous committed rows until this transaction commits — no visible gap.
        // Using INSERT (not UPSERT) because we always DELETE first inside the same
        // transaction, eliminating duplicate-key conflicts and the orphan-cleanup step.
        DB::transaction(function () use ($payload, $period): void {
            DB::table(self::SNAPSHOT_TABLE)->where('snapshot_period', $period)->delete();
            foreach (array_chunk($payload, 500) as $chunk) {
                DB::table(self::SNAPSHOT_TABLE)->insert($chunk);
            }
        });

        $this->snapshotExistenceCache[$period] = true;
        $this->bumpReportCacheVersion();

        return count($payload);
    }

    public function fetchPeriods(): Collection
    {
        $version = ReportCacheVersion::composite(['harian', 'pinjaman', 'simpanan']);

        return Cache::remember("dh:periods:v{$version}", now()->addMinutes(10), function (): Collection {
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
        });
    }

    public function resolveEffectivePeriod(?string $requestedPeriod): ?string
    {
        $targetDate = $this->normalizeDate($requestedPeriod);
        $sourcePeriod = $this->resolveSharedPeriods($targetDate)[0] ?? null;

        if ($sourcePeriod !== null) {
            try {
                $hasSnapshot = $this->snapshotPeriodHasData($sourcePeriod);

                if (!$hasSnapshot) {
                    if (app()->runningInConsole() || app()->runningUnitTests()) {
                        $builtCount = $this->buildPeriodSnapshot($sourcePeriod, false);
                        $hasSnapshot = $builtCount > 0;
                        $this->snapshotExistenceCache[$sourcePeriod] = $hasSnapshot;
                    } else {
                        $this->dispatchSnapshotRebuild($sourcePeriod);
                    }
                }

                if ($hasSnapshot) {
                    return $sourcePeriod;
                }
            } catch (Throwable) {
                // Fall through to existing snapshot lookup.
            }
        }

        try {
            if (Schema::hasTable(self::SNAPSHOT_TABLE) && DB::table(self::SNAPSHOT_TABLE)->exists()) {
                $query = DB::table(self::SNAPSHOT_TABLE);

                if ($targetDate) {
                    $query->where('snapshot_period', '<=', $targetDate);
                }

                $snapshotPeriod = $this->normalizeDate((string) $query->max('snapshot_period'));
                if ($snapshotPeriod !== null) {
                    return $snapshotPeriod;
                }
            }
        } catch (Throwable) {
            // Fall through to source lookup.
        }

        return $sourcePeriod;
    }

    private function dispatchSnapshotRebuild(string $period): void
    {
        try {
            if (!class_exists(\App\Jobs\RebuildDashboardHarianSnapshotJob::class)) {
                return;
            }

            // Deduplicate: skip if a rebuild for this period was dispatched in the last 60s.
            $dispatchKey = 'dh_snapshot:dispatch:' . $period;
            if (!Cache::add($dispatchKey, 1, 60)) {
                return;
            }

            \App\Jobs\RebuildDashboardHarianSnapshotJob::dispatch($period)
                ->onQueue('snapshots-parallel');
        } catch (Throwable $e) {
            Log::warning('Failed to dispatch Dashboard Harian snapshot rebuild from web request.', [
                'period' => $period,
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Pre-warm the per-request existence cache with a single DISTINCT query.
     * Call before any loop that resolves multiple comparison periods so the
     * individual snapshotPeriodHasData() calls hit the in-memory map instead
     * of issuing one round-trip per period.
     */
    private function prewarmSnapshotExistenceCache(): void
    {
        if ($this->snapshotExistenceCacheWarmed || !Schema::hasTable(self::SNAPSHOT_TABLE)) {
            return;
        }

        $this->snapshotExistenceCacheWarmed = true;

        try {
            DB::table(self::SNAPSHOT_TABLE)
                ->select('snapshot_period')
                ->distinct()
                ->pluck('snapshot_period')
                ->each(fn ($p) => $this->snapshotExistenceCache[(string) $p] = true);
        } catch (Throwable) {
            // Non-critical; snapshotPeriodHasData() falls back to per-period EXISTS.
        }
    }

    /**
     * Returns true if the snapshot table has at least one row for $period.
     * Uses the per-request existence cache; falls back to an EXISTS query.
     */
    private function snapshotPeriodHasData(string $period): bool
    {
        if (!array_key_exists($period, $this->snapshotExistenceCache)) {
            try {
                $this->snapshotExistenceCache[$period] = Schema::hasTable(self::SNAPSHOT_TABLE)
                    && DB::table(self::SNAPSHOT_TABLE)
                        ->where('snapshot_period', $period)
                        ->exists();
            } catch (Throwable) {
                $this->snapshotExistenceCache[$period] = false;
            }
        }

        return $this->snapshotExistenceCache[$period];
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
        $version      = ReportCacheVersion::composite(['harian', 'pinjaman', 'simpanan']);
        $kancaHash    = md5(json_encode($selectedKanca));
        $unitHash     = md5(json_encode($selectedUnit));
        $periodNorm   = $period ?? 'latest';
        $cacheKey     = "dh:filter_options:v{$version}:{$periodNorm}:{$kancaHash}:{$unitHash}";

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($period, $selectedKanca, $selectedUnit): array {
            return $this->computeFilterOptions($period, $selectedKanca, $selectedUnit);
        });
    }

    private function computeFilterOptions(?string $period, array|string|null $selectedKanca, array|string|null $selectedUnit): array
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
                'kanca' => [['value' => 'all', 'label' => self::AREA_6_LABEL]],
                'unit_kerja' => [['value' => 'all', 'label' => self::ALL_UNIT_LABEL]],
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
                    ->selectRaw("unit_label as label, unit_key as value, kanca_label as kanca_value")
                    ->whereColumn('unit_key', '<>', 'kanca_key'); // Exclude summary rows, not KC/KCP detail labels

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

        if (($kancas->isEmpty() || $units->isEmpty()) && $this->allowSourceFallbackForDashboardRead()) {
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

        $isArea6Scope = $this->isArea6KancaSelection($normalizedKanca, $kancas);

        $scopedUnits = $units
            ->filter(function ($row) use ($normalizedKanca, $isArea6Scope) {
                if ($isArea6Scope) {
                    return false;
                }

                if ($normalizedKanca === []) {
                    return false;
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
            'kanca' => array_values(array_merge([['value' => 'all', 'label' => self::AREA_6_LABEL]], $kancas->map(fn ($row) => (array) $row)->all())),
            'unit_kerja' => array_values(array_merge([['value' => 'all', 'label' => self::ALL_UNIT_LABEL]], $scopedUnits->map(fn ($row) => (array) $row)->all())),
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
                    'kanca_label' => self::AREA_6_LABEL,
                    'unit_label' => self::ALL_UNIT_LABEL,
                    'row_count' => 0,
                    'current_total_simpanan' => 0,
                    'current_total_os_non_commercial' => 0,
                    'current_casa_pct' => 0,
                ],
            ];
        }

        // Pre-warm existence cache with a single DISTINCT query so the upcoming
        // resolveComparisonPeriods() calls (9 periods) each hit the in-memory map
        // instead of issuing one COUNT/EXISTS round-trip per period.
        $this->prewarmSnapshotExistenceCache();

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
                    'mtm' => (float) ($currentMetrics[$metricKey] ?? 0) - (float) ($mtmMetrics[$metricKey] ?? 0),
                    'mtd' => (float) ($currentMetrics[$metricKey] ?? 0) - (float) ($mtdMetrics[$metricKey] ?? 0),
                    'dtd' => (float) ($currentMetrics[$metricKey] ?? 0) - (float) ($h1Metrics[$metricKey] ?? 0),
                ],
            ];
        })->values()->all();

        $source = $this->canUseSnapshotMetrics()
            ? self::SNAPSHOT_TABLE
            : 'snapshot_unavailable';

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
                'kanca_label' => $this->displayFilterLabel($kancaKey, self::AREA_6_LABEL, $selectedPeriod, 'kanca', $kancaKey, $unitKey),
                'unit_label' => $this->displayFilterLabel($unitKey, self::ALL_UNIT_LABEL, $selectedPeriod, 'unit_kerja', $kancaKey, $unitKey),
                'row_count' => count($rows),
                'current_total_simpanan' => (float) ($currentMetrics['total_simpanan'] ?? 0),
                'current_total_os_non_commercial' => (float) ($currentMetrics['total_os_non_commercial'] ?? 0),
                'current_casa_pct' => (float) ($currentMetrics['casa_pct'] ?? 0),
            ],
        ];
    }

    private function isArea6KancaSelection(array $normalizedKanca, Collection $kancas): bool
    {
        if ($normalizedKanca === []) {
            return true;
        }

        $availableKancas = $kancas
            ->map(fn ($row) => (string) data_get($row, 'value'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($availableKancas === []) {
            return false;
        }

        sort($availableKancas);
        $selected = array_values(array_unique($normalizedKanca));
        sort($selected);

        return $selected === $availableKancas;
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

        $hasKancaFilter = $normalizedKanca !== [];
        $hasUnitFilter = $normalizedUnit !== [];
        $useSnapshot = $this->canUseSnapshotMetrics();
        $loadedFromSnapshot = [];

        if ($useSnapshot) {
            $selects = collect(self::METRIC_COLUMNS)
                ->map(fn (string $column) => "COALESCE(SUM({$column}), 0) as {$column}")
                ->implode(",\n");

            $query = DB::table(self::SNAPSHOT_TABLE)
                ->whereIn('snapshot_period', $normalizedPeriods);

            if ($hasKancaFilter) {
                $slugifiedKanca = collect($normalizedKanca)
                    ->map(function (string $value) {
                        $normalized = $this->normalizeKancaLabel($value);
                        if ($normalized !== '') {
                            return $this->slugKey($normalized);
                        }

                        return $this->slugKey($value);
                    })
                    ->unique()
                    ->all();
                $query->whereIn('kanca_key', $slugifiedKanca);
            }

            if ($hasUnitFilter) {
                $query->whereIn('unit_key', $this->normalizeUnitFilterKeys($normalizedUnit));
            } else {
                $query->whereColumn('kanca_key', 'unit_key');
            }

            $query->groupBy('snapshot_period')
                ->orderBy('snapshot_period')
                ->selectRaw('snapshot_period')
                ->selectRaw($selects)
                ->selectRaw('MAX(source_row_count) as source_row_count');

            foreach ($query->get() as $row) {
                $metricsByPeriod[$row->snapshot_period] = $this->finalizeMetrics((array) $row);
                $loadedFromSnapshot[(string) $row->snapshot_period] = true;
            }
        }

        foreach ($normalizedPeriods as $period) {
            if (!isset($metricsByPeriod[$period])) {
                if ($this->allowSourceFallbackForDashboardRead()) {
                    $metricsByPeriod[$period] = $this->buildMetricsFromSource($period, $normalizedKanca, $normalizedUnit);
                } else {
                    $this->dispatchSnapshotRebuild($period);
                    $metricsByPeriod[$period] = $this->finalizeMetrics($this->emptyMetrics());
                }
            }

            if ($this->allowSourceFallbackForDashboardRead() && !isset($loadedFromSnapshot[$period])) {
                $metricsByPeriod[$period] = $this->overlayRecoveryMetricsFromSource(
                    $metricsByPeriod[$period],
                    $period,
                    $normalizedKanca,
                    $normalizedUnit
                );
            }
        }

        return $metricsByPeriod;
    }

    private function overlayRecoveryMetricsFromSource(array $metrics, string $period, array|string|null $kancaKey, array|string|null $unitKey): array
    {
        $recoveryMetrics = $this->emptyMetrics();

        foreach ($this->fetchRecoveryAggregates($period, $kancaKey, $unitKey) as $row) {
            $recoveryMetrics['ph_tupok'] += (float) ($row->ph_tupok ?? 0);
            $recoveryMetrics['ph_lunas'] += (float) ($row->ph_lunas ?? 0);
            $recoveryMetrics['rec_dh_small'] += (float) ($row->rec_dh_small ?? 0);
            $recoveryMetrics['rec_dh_consumer'] += (float) ($row->rec_dh_consumer ?? 0);
            $recoveryMetrics['rec_dh_micro'] += (float) ($row->rec_dh_micro ?? 0);
            $recoveryMetrics['rec_dh_total'] += (float) ($row->rec_dh_total ?? 0);
        }

        foreach (['ph_tupok', 'ph_lunas', 'rec_dh_small', 'rec_dh_consumer', 'rec_dh_micro', 'rec_dh_total'] as $metricKey) {
            $metrics[$metricKey] = (float) ($recoveryMetrics[$metricKey] ?? 0);
        }

        return $this->finalizeMetrics($metrics);
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

    private function allowSourceFallbackForDashboardRead(): bool
    {
        return app()->runningUnitTests();
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

        foreach ($this->resolveSavingsAggregates($period, $kancaKey, $unitKey) as $row) {
            $kancaLabel = $this->normalizeKancaLabel($row->raw_kantor_cabang ?? $row->raw_unit_kerja ?? null);
            if ($kancaLabel === '') {
                continue;
            }

            $rawUnit = $row->raw_unit_kerja ?? null;
            $unitLabel = $this->normalizeUnitLabel($rawUnit, $kancaLabel);
            $unitKey = $this->resolveDetailUnitKey($rawUnit, $unitLabel, $kancaLabel);
            $bucketKey = $this->makeBucketKey($kancaLabel, $unitLabel, $unitKey);
            $this->initializeBucket($buckets, $bucketKey, $kancaLabel, $unitLabel, $unitKey);

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

            $rawUnit = $row->raw_unit ?? null;
            $unitLabel = $this->normalizeUnitLabel($rawUnit, $kancaLabel);
            $unitKey = $this->resolveDetailUnitKey($rawUnit, $unitLabel, $kancaLabel);
            $bucketKey = $this->makeBucketKey($kancaLabel, $unitLabel, $unitKey);
            $this->initializeBucket($buckets, $bucketKey, $kancaLabel, $unitLabel, $unitKey);

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

            $rawUnit = $row->raw_unit ?? null;
            $unitLabel = $this->normalizeUnitLabel($rawUnit, $kancaLabel);
            $unitKey = $this->resolveDetailUnitKey($rawUnit, $unitLabel, $kancaLabel);
            $bucketKey = $this->makeBucketKey($kancaLabel, $unitLabel, $unitKey);
            $this->initializeBucket($buckets, $bucketKey, $kancaLabel, $unitLabel, $unitKey);

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
        $table = $this->savingsSourceTableForPeriod($period);
        $periodColumn = $this->sourcePeriodColumn($table);
        $kancaColumn = $table === self::HOURLY_DPK_TABLE ? 'mbname' : 'nama_cabang';
        $unitColumn = $table === self::HOURLY_DPK_TABLE ? 'brname' : 'nama_uker';
        $segmentColumn = $table === self::HOURLY_DPK_TABLE ? 'segmen' : 'segmentasi';

        $rawSegment = "UPPER(TRIM(COALESCE(ss.{$segmentColumn}, '')))";
        $segment = "CASE WHEN {$rawSegment} = 'KORPORASI' THEN 'WHOLESALE' WHEN {$rawSegment} = 'MIKRO' THEN 'MICRO' ELSE {$rawSegment} END";
        $product = "UPPER(TRIM(COALESCE(ss.produk, '')))";

        $microSegment = "{$segment} = 'MICRO'";

        $query = DB::table($table . ' as ss')
            ->whereIn("ss.{$periodColumn}", $this->sourcePeriodRawCandidates($table, $period))
            ->selectRaw("TRIM(COALESCE(ss.{$kancaColumn}, '')) as raw_kantor_cabang")
            ->selectRaw("TRIM(COALESCE(ss.{$unitColumn}, '')) as raw_unit_kerja")
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
                ->map(fn (string $value) => $this->buildFilterCondition("ss.{$kancaColumn}", $value))
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
                ->map(fn (string $value) => $this->buildFilterCondition("ss.{$unitColumn}", $value))
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
        $rows = $query
            ->groupBy('raw_cabang', 'raw_unit')
            ->get();

        if ($rows->isNotEmpty()) {
            return $this->overlayL1133MicroLoanAggregates($period, $rows, $kancaKey, $unitKey);
        }

        $normalizedPeriod = $this->normalizeDate($period) ?? $period;
        $fallbackRows = collect();

        if ($this->dlyKapResegmentasiAvailable($normalizedPeriod)) {
            $fallbackRows = $this->overlayPriorityLoanRows(
                $fallbackRows,
                $this->fetchDlyKapPriorityLoanAggregates($normalizedPeriod, $kancaKey, $unitKey),
                $this->dlyKapPriorityMetricKeys()
            );
        }

        if ($this->l1133Available($normalizedPeriod)) {
            $fallbackRows = $this->overlayPriorityLoanRows(
                $fallbackRows,
                $this->fetchL1133MicroLoanAggregates($normalizedPeriod, $kancaKey, $unitKey),
                $this->l1133MicroMetricKeys()
            );
        }

        return $fallbackRows;
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

    private function overlayDlyKapPriorityLoanAggregates(string $period, Collection $ssaRows, array|string|null $kancaKey = null, array|string|null $unitKey = null): Collection
    {
        $normalizedPeriod = $this->normalizeDate($period);
        if (!$normalizedPeriod || !$this->dlyKapResegmentasiAvailable($normalizedPeriod)) {
            return $ssaRows;
        }

        $dlyRows = $this->fetchDlyKapPriorityLoanAggregates($normalizedPeriod, $kancaKey, $unitKey);
        if ($dlyRows->isEmpty()) {
            return $ssaRows;
        }

        return $this->overlayPriorityLoanRows($ssaRows, $dlyRows, $this->dlyKapPriorityMetricKeys());
    }

    private function overlayPriorityLoanRows(Collection $baseRows, Collection $priorityRows, array $priorityMetrics): Collection
    {
        $allLoanMetrics = $this->loanMetricKeys();
        $rowsByUnit = collect();

        foreach ($baseRows as $baseRow) {
            $key = $this->loanRowMergeKey($baseRow);
            if ($key === '') {
                continue;
            }

            $existing = $rowsByUnit->get($key);
            if (!$existing) {
                $rowsByUnit->put($key, $baseRow);
                continue;
            }

            foreach ($allLoanMetrics as $metric) {
                $existing->{$metric} = (float) ($existing->{$metric} ?? 0) + (float) ($baseRow->{$metric} ?? 0);
            }
        }

        foreach ($priorityRows as $priorityRow) {
            $key = $this->loanRowMergeKey($priorityRow);
            if ($key === '') {
                continue;
            }

            $row = $rowsByUnit->get($key);
            if (!$row) {
                $row = (object) array_merge(
                    [
                        'raw_cabang' => $priorityRow->raw_cabang ?? null,
                        'raw_unit' => $priorityRow->raw_unit ?? null,
                    ],
                    array_fill_keys($allLoanMetrics, 0.0)
                );
            }

            foreach ($priorityMetrics as $metric) {
                $row->{$metric} = (float) ($priorityRow->{$metric} ?? 0);
            }

            $rowsByUnit->put($key, $row);
        }

        return $rowsByUnit->values();
    }

    private function loanRowMergeKey(object $row): string
    {
        $unitCode = trim((string) ($row->unit_code ?? ''));
        if ($unitCode !== '' && $unitCode !== '0') {
            return (string) ((int) $unitCode);
        }

        $extractedUnitCode = $this->extractUnitCode($row->raw_unit ?? null);
        if ($extractedUnitCode !== '') {
            return $extractedUnitCode;
        }

        return $this->slugKey(implode('|', [
            (string) ($row->raw_cabang ?? ''),
            (string) ($row->raw_unit ?? ''),
        ]));
    }

    private function fetchDlyKapPriorityLoanAggregates(string $period, array|string|null $kancaKey = null, array|string|null $unitKey = null): Collection
    {
        $unitMap = $this->dlyKapUnitScopeMap($period);
        if ($unitMap->isEmpty()) {
            return collect();
        }

        $category = "UPPER(TRIM(COALESCE(d.segmen_kategori, '')))";
        $description = "UPPER(COALESCE(d.keterangan, ''))";
        $smallSegment = "{$category} = 'SEGMEN SMALL'";
        $kecilNonCashcoll = "{$smallSegment} AND {$description} NOT LIKE '%CASHCOL%' AND {$description} NOT LIKE '%- CASH COLATERAL%'";
        $cashcoll = "{$smallSegment} AND {$description} NOT LIKE '%NON CASH COLATERAL%' AND ({$description} LIKE '%CASHCOL%' OR {$description} LIKE '%- CASH COLATERAL%')";
        $medium = "{$category} IN ('SEGMEN MEDIUM', 'SEGMEN COMMERCIAL')";
        $briguna = "{$category} = 'SEGMEN CONSUMER' AND ({$description} LIKE '%BRIGUNA%' OR {$description} LIKE '%KREDIT PEGAWAI BRI%')";
        $kpr = "{$category} = 'SEGMEN CONSUMER' AND {$description} LIKE '%(KPR)%'";
        $kkb = "{$category} = 'SEGMEN CONSUMER' AND {$description} LIKE '%KKB%'";
        $kurRitel = "{$category} = 'SEGMEN MICRO' AND {$description} LIKE '%KUR RITEL 2015%'";

        $rows = DB::table(self::DLY_KAP_TABLE . ' as d')
            ->where('d.periode', $period)
            ->selectRaw("CAST(TRIM(COALESCE(d.kode_unit, '')) AS UNSIGNED) as unit_code")
            ->selectRaw("SUM(CASE WHEN {$kecilNonCashcoll} THEN COALESCE(d.tl_rp, 0) ELSE 0 END) as kecil_non_cashcoll_os")
            ->selectRaw("SUM(CASE WHEN {$kecilNonCashcoll} THEN COALESCE(d.dpk_rp, 0) ELSE 0 END) as kecil_non_cashcoll_sml")
            ->selectRaw("SUM(CASE WHEN {$kecilNonCashcoll} THEN COALESCE(d.npl_rp, 0) ELSE 0 END) as kecil_non_cashcoll_npl")
            ->selectRaw("SUM(CASE WHEN {$cashcoll} THEN COALESCE(d.tl_rp, 0) ELSE 0 END) as cashcoll_os")
            ->selectRaw("SUM(CASE WHEN {$cashcoll} THEN COALESCE(d.dpk_rp, 0) ELSE 0 END) as cashcoll_sml")
            ->selectRaw("SUM(CASE WHEN {$cashcoll} THEN COALESCE(d.npl_rp, 0) ELSE 0 END) as cashcoll_npl")
            ->selectRaw("SUM(CASE WHEN {$medium} THEN COALESCE(d.tl_rp, 0) ELSE 0 END) as medium_os")
            ->selectRaw("SUM(CASE WHEN {$medium} THEN COALESCE(d.dpk_rp, 0) ELSE 0 END) as medium_sml")
            ->selectRaw("SUM(CASE WHEN {$medium} THEN COALESCE(d.npl_rp, 0) ELSE 0 END) as medium_npl")
            ->selectRaw("SUM(CASE WHEN {$briguna} THEN COALESCE(d.tl_rp, 0) ELSE 0 END) as briguna_konsumer_os")
            ->selectRaw("SUM(CASE WHEN {$briguna} THEN COALESCE(d.dpk_rp, 0) ELSE 0 END) as briguna_konsumer_sml")
            ->selectRaw("SUM(CASE WHEN {$briguna} THEN COALESCE(d.npl_rp, 0) ELSE 0 END) as briguna_konsumer_npl")
            ->selectRaw("SUM(CASE WHEN {$kpr} THEN COALESCE(d.tl_rp, 0) ELSE 0 END) as kpr_os")
            ->selectRaw("SUM(CASE WHEN {$kpr} THEN COALESCE(d.dpk_rp, 0) ELSE 0 END) as kpr_sml")
            ->selectRaw("SUM(CASE WHEN {$kpr} THEN COALESCE(d.npl_rp, 0) ELSE 0 END) as kpr_npl")
            ->selectRaw("SUM(CASE WHEN {$kkb} THEN COALESCE(d.tl_rp, 0) ELSE 0 END) as kkb_os")
            ->selectRaw("SUM(CASE WHEN {$kkb} THEN COALESCE(d.dpk_rp, 0) ELSE 0 END) as kkb_sml")
            ->selectRaw("SUM(CASE WHEN {$kkb} THEN COALESCE(d.npl_rp, 0) ELSE 0 END) as kkb_npl")
            ->selectRaw("SUM(CASE WHEN {$kurRitel} THEN COALESCE(d.tl_rp, 0) ELSE 0 END) as kur_kecil_os")
            ->selectRaw("SUM(CASE WHEN {$kurRitel} THEN COALESCE(d.dpk_rp, 0) ELSE 0 END) as kur_kecil_sml")
            ->selectRaw("SUM(CASE WHEN {$kurRitel} THEN COALESCE(d.npl_rp, 0) ELSE 0 END) as kur_kecil_npl")
            ->groupBy('unit_code')
            ->get();

        $normalizedKanca = $this->normalizeFilterValues($kancaKey);
        $normalizedUnit = $this->normalizeFilterValues($unitKey);

        return $rows
            ->map(function ($row) use ($unitMap) {
                $scope = $unitMap->get((string) ($row->unit_code ?? ''));
                if (!$scope) {
                    return null;
                }

                $row->raw_cabang = $scope['raw_cabang'];
                $row->raw_unit = $scope['raw_unit'];
                $row->unit_key = $scope['unit_key'];
                $row->kanca_label = $scope['kanca_label'];

                return $row;
            })
            ->filter()
            ->filter(function ($row) use ($normalizedKanca, $normalizedUnit) {
                if ($normalizedKanca !== []) {
                    $kancaMatches = collect($normalizedKanca)->contains(function (string $value) use ($row): bool {
                        $normalized = $this->normalizeKancaLabel($value);
                        $expected = $normalized !== '' ? $this->slugKey($normalized) : $this->slugKey($value);

                        return $this->slugKey((string) ($row->kanca_label ?? '')) === $expected;
                    });

                    if (!$kancaMatches) {
                        return false;
                    }
                }

                if ($normalizedUnit !== []) {
                    $unitKey = (string) ($row->unit_key ?? '');

                    return in_array($unitKey, $this->normalizeUnitFilterKeys($normalizedUnit), true);
                }

                return true;
            })
            ->values();
    }

    private function dlyKapUnitScopeMap(string $period): Collection
    {
        $period = $this->normalizeDate($period) ?: $period;
        if (isset($this->unitScopeMapCache[$period])) {
            return $this->unitScopeMapCache[$period];
        }

        if (!Schema::hasTable(self::LOAN_TABLE)) {
            return $this->unitScopeMapCache[$period] = $this->buildUnitScopeMapFromL1133($period);
        }

        $periods = DB::table(self::LOAN_TABLE)
            ->where('month_day_year_of_periode', '<=', $period)
            ->select('month_day_year_of_periode')
            ->distinct()
            ->orderByDesc('month_day_year_of_periode')
            ->limit(7)
            ->pluck('month_day_year_of_periode')
            ->all();

        if ($periods === []) {
            return $this->unitScopeMapCache[$period] = $this->buildUnitScopeMapFromL1133($period);
        }

        return $this->unitScopeMapCache[$period] = DB::table(self::LOAN_TABLE)
            ->whereIn('month_day_year_of_periode', $periods)
            ->selectRaw("CAST(COALESCE(NULLIF(TRIM(id_uker), ''), REGEXP_SUBSTR(TRIM(COALESCE(nama_uker, '')), '^[0-9]+')) AS UNSIGNED) as unit_code")
            ->selectRaw("SUBSTRING_INDEX(MAX(CONCAT(month_day_year_of_periode, '|', TRIM(COALESCE(nama_cabang, '')))), '|', -1) as raw_cabang")
            ->selectRaw("SUBSTRING_INDEX(MAX(CONCAT(month_day_year_of_periode, '|', TRIM(COALESCE(nama_uker, '')))), '|', -1) as raw_unit")
            ->groupBy('unit_code')
            ->get()
            ->filter(fn ($row) => (string) ($row->unit_code ?? '') !== '')
            ->mapWithKeys(function ($row): array {
                $kancaLabel = $this->normalizeKancaLabel($row->raw_cabang ?? $row->raw_unit ?? null);
                $unitLabel = $this->normalizeUnitLabel($row->raw_unit ?? null, $kancaLabel);
                $unitKey = $this->resolveDetailUnitKey($row->raw_unit ?? null, $unitLabel, $kancaLabel) ?: $this->slugKey($unitLabel);

                return [
                    (string) $row->unit_code => [
                        'raw_cabang' => $row->raw_cabang,
                        'raw_unit' => $row->raw_unit,
                        'kanca_label' => $kancaLabel,
                        'unit_key' => $unitKey,
                    ],
                ];
            });
    }

    private function buildUnitScopeMapFromL1133(string $period): Collection
    {
        if (!Schema::hasTable(self::L1133_TABLE)) {
            return collect();
        }

        $periods = DB::table(self::L1133_TABLE)
            ->where('periode', '<=', $period)
            ->select('periode')
            ->distinct()
            ->orderByDesc('periode')
            ->limit(7)
            ->pluck('periode')
            ->all();

        if ($periods === []) {
            return collect();
        }

        return DB::table(self::L1133_TABLE)
            ->whereIn('periode', $periods)
            ->selectRaw("CAST(TRIM(COALESCE(kode_uker, '')) AS UNSIGNED) as unit_code")
            ->selectRaw("SUBSTRING_INDEX(MAX(CONCAT(periode, '|', TRIM(COALESCE(nama_kanca, '')))), '|', -1) as raw_cabang")
            ->selectRaw("SUBSTRING_INDEX(MAX(CONCAT(periode, '|', TRIM(COALESCE(nama_uker, '')))), '|', -1) as raw_unit")
            ->groupBy('unit_code')
            ->get()
            ->filter(fn ($row) => (string) ($row->unit_code ?? '') !== '')
            ->mapWithKeys(function ($row): array {
                $kancaLabel = $this->normalizeKancaLabel($row->raw_cabang ?? $row->raw_unit ?? null);
                $unitLabel = $this->normalizeUnitLabel($row->raw_unit ?? null, $kancaLabel);
                $unitKey = $this->resolveDetailUnitKey($row->raw_unit ?? null, $unitLabel, $kancaLabel) ?: $this->slugKey($unitLabel);

                return [
                    (string) $row->unit_code => [
                        'raw_cabang' => $row->raw_cabang,
                        'raw_unit' => $row->raw_unit,
                        'kanca_label' => $kancaLabel,
                        'unit_key' => $unitKey,
                    ],
                ];
            });
    }

    private function dlyKapResegmentasiAvailable(string $period): bool
    {
        return Schema::hasTable(self::DLY_KAP_TABLE)
            && DB::table(self::DLY_KAP_TABLE)->where('periode', $period)->exists();
    }

    private function dlyKapPriorityMetricKeys(): array
    {
        return [
            'kecil_non_cashcoll_os',
            'kecil_non_cashcoll_sml',
            'kecil_non_cashcoll_npl',
            'cashcoll_os',
            'cashcoll_sml',
            'cashcoll_npl',
            'medium_os',
            'medium_sml',
            'medium_npl',
            'briguna_konsumer_os',
            'briguna_konsumer_sml',
            'briguna_konsumer_npl',
            'kpr_os',
            'kpr_sml',
            'kpr_npl',
            'kkb_os',
            'kkb_sml',
            'kkb_npl',
            'kur_kecil_os',
            'kur_kecil_sml',
            'kur_kecil_npl',
        ];
    }

    private function overlayL1133MicroLoanAggregates(string $period, Collection $loanRows, array|string|null $kancaKey = null, array|string|null $unitKey = null): Collection
    {
        $normalizedPeriod = $this->normalizeDate($period);
        if (!$normalizedPeriod || !$this->l1133Available($normalizedPeriod)) {
            return $loanRows;
        }

        $l1133Rows = $this->fetchL1133MicroLoanAggregates($normalizedPeriod, $kancaKey, $unitKey);
        if ($l1133Rows->isEmpty()) {
            return $loanRows;
        }

        return $this->overlayPriorityLoanRows($loanRows, $l1133Rows, $this->l1133MicroMetricKeys());
    }

    private function fetchL1133MicroLoanAggregates(string $period, array|string|null $kancaKey = null, array|string|null $unitKey = null): Collection
    {
        $resolvedPeriod = $this->resolvePreviousL1133Period($period);
        if ($resolvedPeriod === null) {
            return collect();
        }

        $unitMap = $this->dlyKapUnitScopeMap($period);
        if ($unitMap->isEmpty()) {
            return collect();
        }

        $jenis = "UPPER(TRIM(COALESCE(l.jenis, '')))";
        $ukerName = "UPPER(TRIM(COALESCE(l.nama_uker, '')))";
        $microUker = "{$ukerName} LIKE '%UNIT%'";
        $brigunaMikro = "{$microUker} AND {$jenis} = 'KUPEDES GBT'";
        $kupedes = "{$microUker} AND {$jenis} IN ('KUPEDES KOMERSIAL', 'KUPEDES RAKYAT', 'RITEL KOMERSIAL FULLY CASH COLLATERAL')";
        $kurMikro = "{$microUker} AND {$jenis} = 'KUR MIKRO BARU'";
        $kurKpp = "{$microUker} AND {$jenis} = 'KPR'";

        $rows = DB::table(self::L1133_TABLE . ' as l')
            ->where('l.periode', $resolvedPeriod)
            ->selectRaw("CAST(TRIM(COALESCE(l.kode_uker, '')) AS UNSIGNED) as unit_code")
            ->selectRaw("MAX(TRIM(COALESCE(l.nama_kanca, ''))) as raw_cabang")
            ->selectRaw("MAX(TRIM(COALESCE(l.nama_uker, ''))) as raw_unit")
            ->selectRaw("SUM(CASE WHEN {$brigunaMikro} THEN COALESCE(l.outstanding, 0) ELSE 0 END) as briguna_mikro_os")
            ->selectRaw("SUM(CASE WHEN {$brigunaMikro} THEN COALESCE(l.dpk, 0) ELSE 0 END) as briguna_mikro_sml")
            ->selectRaw("SUM(CASE WHEN {$brigunaMikro} THEN COALESCE(l.npl, 0) ELSE 0 END) as briguna_mikro_npl")
            ->selectRaw("SUM(CASE WHEN {$kupedes} THEN COALESCE(l.outstanding, 0) ELSE 0 END) as kupedes_os")
            ->selectRaw("SUM(CASE WHEN {$kupedes} THEN COALESCE(l.dpk, 0) ELSE 0 END) as kupedes_sml")
            ->selectRaw("SUM(CASE WHEN {$kupedes} THEN COALESCE(l.npl, 0) ELSE 0 END) as kupedes_npl")
            ->selectRaw("SUM(CASE WHEN {$kurMikro} THEN COALESCE(l.outstanding, 0) ELSE 0 END) as kur_mikro_os")
            ->selectRaw("SUM(CASE WHEN {$kurMikro} THEN COALESCE(l.dpk, 0) ELSE 0 END) as kur_mikro_sml")
            ->selectRaw("SUM(CASE WHEN {$kurMikro} THEN COALESCE(l.npl, 0) ELSE 0 END) as kur_mikro_npl")
            ->selectRaw("SUM(CASE WHEN {$kurKpp} THEN COALESCE(l.outstanding, 0) ELSE 0 END) as kur_kpp_os")
            ->selectRaw("SUM(CASE WHEN {$kurKpp} THEN COALESCE(l.dpk, 0) ELSE 0 END) as kur_kpp_sml")
            ->selectRaw("SUM(CASE WHEN {$kurKpp} THEN COALESCE(l.npl, 0) ELSE 0 END) as kur_kpp_npl")
            ->groupBy('unit_code')
            ->get()
            ->filter(function ($row): bool {
                foreach ($this->l1133MicroMetricKeys() as $metric) {
                    if ((float) ($row->{$metric} ?? 0) != 0.0) {
                        return true;
                    }
                }

                return false;
            })
            ->values();

        $normalizedKanca = $this->normalizeFilterValues($kancaKey);
        $normalizedUnit = $this->normalizeFilterValues($unitKey);

        return $rows
            ->map(function ($row) use ($unitMap) {
                $scope = $unitMap->get((string) ($row->unit_code ?? ''));
                if (!$scope) {
                    return null;
                }

                $row->raw_cabang = $scope['raw_cabang'];
                $row->raw_unit = $scope['raw_unit'];
                $row->kanca_label = $scope['kanca_label'];
                $row->unit_key = $scope['unit_key'];

                return $row;
            })
            ->filter()
            ->filter(function ($row) use ($normalizedKanca, $normalizedUnit) {
                if ($normalizedKanca !== []) {
                    $kancaMatches = collect($normalizedKanca)->contains(function (string $value) use ($row): bool {
                        $normalized = $this->normalizeKancaLabel($value);
                        $expected = $normalized !== '' ? $this->slugKey($normalized) : $this->slugKey($value);

                        return $this->slugKey((string) ($row->kanca_label ?? '')) === $expected;
                    });

                    if (!$kancaMatches) {
                        return false;
                    }
                }

                if ($normalizedUnit !== []) {
                    return in_array((string) ($row->unit_key ?? ''), $this->normalizeUnitFilterKeys($normalizedUnit), true);
                }

                return true;
            })
            ->values();
    }

    private function l1133Available(string $period): bool
    {
        return Schema::hasTable(self::L1133_TABLE)
            && $this->resolvePreviousL1133Period($period) !== null;
    }

    private function resolvePreviousL1133Period(string $period): ?string
    {
        if (!Schema::hasTable(self::L1133_TABLE)) {
            return null;
        }

        $normalizedPeriod = $this->normalizeDate($period);
        if ($normalizedPeriod === null) {
            return null;
        }

        return DB::table(self::L1133_TABLE)
            ->where('periode', '<=', $normalizedPeriod)
            ->orderByDesc('periode')
            ->value('periode');
    }

    /**
     * L1133 is carried forward by resolvePreviousL1133Period(). When a new L1133
     * period is imported, every later shared snapshot period uses it until the
     * next L1133 period starts.
     *
     * @param array<int, string> $sharedPeriodsAsc
     * @return array<int, string>
     */
    private function resolveAffectedSnapshotPeriodsForL1133(string $sourcePeriod, array $sharedPeriodsAsc): array
    {
        $nextL1133Period = null;

        if (Schema::hasTable(self::L1133_TABLE)) {
            $nextL1133Period = DB::table(self::L1133_TABLE)
                ->where('periode', '>', $sourcePeriod)
                ->orderBy('periode')
                ->value('periode');

            $nextL1133Period = $nextL1133Period !== null
                ? $this->normalizeDate((string) $nextL1133Period)
                : null;
        }

        return array_values(array_filter(
            $sharedPeriodsAsc,
            fn (string $period): bool => $period >= $sourcePeriod
                && ($nextL1133Period === null || $period < $nextL1133Period)
        ));
    }

    private function l1133MicroMetricKeys(): array
    {
        return [
            'briguna_mikro_os',
            'briguna_mikro_sml',
            'briguna_mikro_npl',
            'kupedes_os',
            'kupedes_sml',
            'kupedes_npl',
            'kur_mikro_os',
            'kur_mikro_sml',
            'kur_mikro_npl',
            'kur_kpp_os',
            'kur_kpp_sml',
            'kur_kpp_npl',
        ];
    }

    private function extractUnitCode($value): string
    {
        $clean = trim((string) $value);

        return preg_match('/^0*(\d+)/', $clean, $matches) === 1 ? (string) ((int) $matches[1]) : '';
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

        $normalizedSnapshotPeriod = $this->normalizeDate($period);
        if ($normalizedSnapshotPeriod === null) {
            return collect();
        }

        $currentPhPeriod = DB::table('lw325_ph')
            ->where('periode', $normalizedSnapshotPeriod)
            ->value('periode');

        if ($currentPhPeriod === null) {
            return collect();
        }

        $previousPhPeriod = $this->resolvePreviousMonthPhPeriod($currentPhPeriod);

        if (!$this->isPreviousMonthEndPhPeriod($currentPhPeriod, $previousPhPeriod)) {
            Log::warning('Skipping LW325 PH recovery because the comparison period is not the previous month-end.', [
                'current_period' => $currentPhPeriod,
                'comparison_period' => $previousPhPeriod,
            ]);

            return collect();
        }

        $normalizedKanca = $this->normalizeFilterValues($kancaKey);
        $normalizedUnit = $this->normalizeFilterValues($unitKey);
        $currentAccountKeySql = $this->phAccountKeySql('n');
        $previousAccountKeySql = $this->phAccountKeySql('o');

        // OPTIMIZATION: Single combined query for TUPOK + LUNAS
        // Instead of 2 separate queries with UNION ALL, we create a single subquery
        // that identifies both types, then aggregate once. This reduces:
        // - Query execution from 3 (tupok + lunas + final aggregation) to 1
        // - Result set processing overhead
        // - Database buffer pool pressure
        // Expected performance gain: 10-15%

        $tupokQuery = DB::table('lw325_ph as n')
            ->join('lw325_ph as o', function ($join) use ($previousPhPeriod, $currentPhPeriod, $currentAccountKeySql, $previousAccountKeySql) {
                $join->whereRaw("{$currentAccountKeySql} = {$previousAccountKeySql}")
                    ->on('n.kanca', '=', 'o.kanca')
                    ->on('n.unit', '=', 'o.unit')
                    ->whereRaw('n.periode = ?', [$currentPhPeriod])
                    ->whereRaw('o.periode = ?', [$previousPhPeriod]);
            })
            ->selectRaw("o.kanca as n_kanca")
            ->selectRaw("o.unit as n_unit")
            ->selectRaw("o.segmen_dashboard as n_segment")
            ->selectRaw("(COALESCE(o.pokok, 0) - COALESCE(n.pokok, 0)) as amount")
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
            ->leftJoin('lw325_ph as n', function ($join) use ($currentPhPeriod, $currentAccountKeySql, $previousAccountKeySql) {
                $join->whereRaw("{$previousAccountKeySql} = {$currentAccountKeySql}")
                    ->on('o.kanca', '=', 'n.kanca')
                    ->on('o.unit', '=', 'n.unit')
                    ->whereRaw('n.periode = ?', [$currentPhPeriod]);
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

        $final['ldr_non_commercial'] = $this->safePercent($final['total_os_non_commercial'], $final['total_simpanan']);
        $final['ldr_ritel_non_commercial'] = $this->safePercent($final['sme_os'] + $final['consumer_os'], $final['simpanan_ritel']);
        $final['ldr_mikro_non_commercial'] = $this->safePercent($final['micro_os'], $final['simpanan_mikro']);
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
            'simpanan_ritel' => ['mata_anggaran' => ['A.1. DPK Retail Funding Total'], 'uker_contains_any' => ['KC', 'KCP'], 'include_kanca_summary' => true],
            'giro_ritel' => ['mata_anggaran' => ['Giro Retail Funding Total'], 'uker_contains_any' => ['KC', 'KCP'], 'include_kanca_summary' => true],
            'deposito_ritel' => ['mata_anggaran' => ['Deposito Retail Funding Total'], 'uker_contains_any' => ['KC', 'KCP'], 'include_kanca_summary' => true],
            'tabungan_ritel' => ['mata_anggaran' => ['Tabungan Retail Funding Total'], 'uker_contains_any' => ['KC', 'KCP'], 'include_kanca_summary' => true],
            'simpanan_mikro' => ['mata_anggaran' => ['A.1. DPK Retail Funding Total'], 'uker_contains_any' => ['UNIT']],
            'giro_mikro' => ['mata_anggaran' => ['Giro Retail Funding Total'], 'uker_contains_any' => ['UNIT']],
            'deposito_mikro' => ['mata_anggaran' => ['Deposito Retail Funding Total'], 'uker_contains_any' => ['UNIT']],
            'tabungan_mikro' => ['mata_anggaran' => ['Tabungan Retail Funding Total'], 'uker_contains_any' => ['UNIT']],
            'simpanan_wholesale' => ['mata_anggaran' => ['A.2. DPK Korporasi']],
            'giro_wholesale' => ['mata_anggaran' => ['A.2.a. Giro Korporasi']],
            'deposito_wholesale' => ['mata_anggaran' => ['A.2.b. Deposito Korporasi']],
            'tabungan_wholesale' => ['mata_anggaran' => []],
            'total_os' => ['mata_anggaran' => ['B. KREDIT TOTAL']],
            'kecil_non_cashcoll_os' => ['mata_anggaran' => ['B.2.a. Kredit Kecil Non Cash Collateral'], 'uker_contains_any' => ['KC', 'KCP'], 'include_kanca_summary' => true],
            'cashcoll_os' => ['mata_anggaran' => ['B.2.b. Kredit Kecil Cash Collateral'], 'uker_contains_any' => ['KC', 'KCP'], 'include_kanca_summary' => true],
            'medium_os' => ['mata_anggaran' => ['B.3. MEDIUM']],
            'briguna_konsumer_os' => ['mata_anggaran' => ['B.5.a. Briguna'], 'uker_contains_any' => ['KC', 'KCP'], 'include_kanca_summary' => true],
            'kpr_os' => ['mata_anggaran' => ['B.5.b. KPR'], 'uker_contains_any' => ['KC', 'KCP'], 'include_kanca_summary' => true],
            'kkb_os' => ['mata_anggaran' => ['B.5.c. KKB'], 'uker_contains_any' => ['KC', 'KCP'], 'include_kanca_summary' => true],
            'micro_os' => ['mata_anggaran' => ['B.1. MIKRO']],
            'briguna_mikro_os' => ['mata_anggaran' => ['B.1.b. Briguna Mikro']],
            'kupedes_os' => ['mata_anggaran' => ['B.1.a. Kupedes Komersial']],
            'kur_mikro_os' => ['mata_anggaran' => ['B.1.c. KUR Mikro']],
            'kur_kecil_os' => ['mata_anggaran' => ['B.1.d. KUR Kecil']],
            'kur_kpp_os' => ['mata_anggaran' => ['B.1.e. KPP']],
            'total_sml_pct_non_commercial' => ['mata_anggaran' => []],
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
            'total_npl_pct_non_commercial' => ['mata_anggaran' => []],
            'kecil_non_cashcoll_npl' => ['mata_anggaran' => ['NPL Rp Kecil Non Cash Collateral']],
            'cashcoll_npl' => ['mata_anggaran' => ['NPL Rp Kecil Cash Collateral']],
            'medium_npl' => ['mata_anggaran' => ['NPL Rp Medium']],
            'briguna_konsumer_npl' => ['mata_anggaran' => ['NPL Rp Briguna']],
            'kpr_npl' => ['mata_anggaran' => ['NPL Rp KPR']],
            'kkb_npl' => ['mata_anggaran' => ['NPL Rp KKB']],
            'micro_npl' => ['mata_anggaran' => ['NPL Rp Mikro']],
            'briguna_mikro_npl' => ['mata_anggaran' => ['NPL Rp Briguna Mikro']],
            'kupedes_npl' => ['mata_anggaran' => ['NPL Rp Kupedes Komersial']],
            'kur_mikro_npl' => ['mata_anggaran' => ['NPL Rp KUR Mikro']],
            'kur_kecil_npl' => ['mata_anggaran' => ['NPL Rp KUR Kecil']],
            'kur_kpp_npl' => ['mata_anggaran' => ['NPL Rp KPP']],
            'rec_dh_total' => ['mata_anggaran' => ['C. RECOVERY EKSTRAKOMTABEL']],
            'rec_dh_small' => ['mata_anggaran' => ['C. 2. Recovery Ekstrakomtabel Small']],
            'rec_dh_consumer' => ['mata_anggaran' => ['C. 4. Recovery Ekstrakomtabel Konsumer']],
            'rec_dh_micro' => ['mata_anggaran' => ['C. 1. a. Recovery Ekstrakomtabel Mikro', 'C. 1. b. Recovery Ekstrakomtabel Kece']],
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
        $final['total_os_non_commercial'] = $final['kecil_os'] + $final['consumer_os'] + $final['micro_os'];
        if ((float) ($final['total_os'] ?? 0) <= 0) {
            $final['total_os'] = $final['commercial_os'] + $final['total_os_non_commercial'];
        }
        $final['kecil_sml'] = $final['kecil_non_cashcoll_sml'] + $final['cashcoll_sml'];
        $final['sme_sml'] = $final['kecil_sml'];
        $final['consumer_sml'] = $final['briguna_konsumer_sml'] + $final['kpr_sml'] + $final['kkb_sml'];
        $final['total_sml_abs_non_commercial'] = $final['kecil_sml'] + $final['consumer_sml'] + $final['micro_sml'];
        $final['kecil_npl'] = $final['kecil_non_cashcoll_npl'] + $final['cashcoll_npl'];
        $final['sme_npl'] = $final['kecil_npl'];
        $final['consumer_npl'] = $final['briguna_konsumer_npl'] + $final['kpr_npl'] + $final['kkb_npl'];
        $final['total_npl_abs_non_commercial'] = $final['sme_npl'] + $final['consumer_npl'] + $final['micro_npl'];
        $final['total_sml_pct_non_commercial'] = $this->safePercent($final['total_sml_abs_non_commercial'], $final['total_os_non_commercial']);
        $final['total_npl_pct_non_commercial'] = $this->safePercent($final['total_npl_abs_non_commercial'], $final['total_os_non_commercial']);
        $final['simpanan_ritel'] = $final['giro_ritel'] + $final['deposito_ritel'] + $final['tabungan_ritel'];
        $final['simpanan_mikro'] = $final['giro_mikro'] + $final['deposito_mikro'] + $final['tabungan_mikro'];
        $final['simpanan_wholesale'] = $final['giro_wholesale'] + $final['deposito_wholesale'] + $final['tabungan_wholesale'];
        $computedTotalSimpanan = $final['simpanan_ritel'] + $final['simpanan_mikro'] + $final['simpanan_wholesale'];
        if ($computedTotalSimpanan > (float) ($final['total_simpanan'] ?? 0)) {
            $final['total_simpanan'] = $computedTotalSimpanan;
        }
        $final['casa_ritel'] = $final['giro_ritel'] + $final['tabungan_ritel'];
        $final['casa_mikro'] = $final['giro_mikro'] + $final['tabungan_mikro'];
        $final['total_casa'] = $final['casa_ritel'] + $final['casa_mikro'];
        $final['commercial_os'] = 0.0;
        $final['casa_pct'] = $this->safePercent($final['total_casa'], $final['total_simpanan']);
        // RKA LDR follows loan / savings, consistent with the live snapshot metrics.
        $final['ldr_non_commercial'] = $this->safePercent($final['total_os_non_commercial'], $final['total_simpanan']);
        $final['ldr_ritel_non_commercial'] = $this->safePercent($final['sme_os'] + $final['consumer_os'], $final['simpanan_ritel']);
        $final['ldr_mikro_non_commercial'] = $this->safePercent($final['micro_os'], $final['simpanan_mikro']);
        
        // If rec_dh_total was explicitly loaded from RKA, keep it. 
        // Otherwise fallback to sum of segments.
        if ((float) ($final['rec_dh_total'] ?? 0) <= 0) {
            $final['rec_dh_total'] = $final['rec_dh_small'] + $final['rec_dh_consumer'] + $final['rec_dh_micro'];
        }

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

    private function initializeBucket(array &$buckets, string $bucketKey, string $kancaLabel, string $unitLabel, ?string $unitKey = null): void
    {
        if (isset($buckets[$bucketKey])) {
            return;
        }

        $buckets[$bucketKey] = $this->emptyMetrics();
        $buckets[$bucketKey]['kanca_key'] = $this->slugKey($kancaLabel);
        $buckets[$bucketKey]['kanca_label'] = $kancaLabel;
        $buckets[$bucketKey]['unit_key'] = $unitKey ?: $this->slugKey($unitLabel);
        $buckets[$bucketKey]['unit_label'] = $unitLabel;
    }

    private function makeBucketKey(string $kancaLabel, string $unitLabel, ?string $unitKey = null): string
    {
        return $this->slugKey($kancaLabel) . '|' . ($unitKey ?: $this->slugKey($unitLabel));
    }

    private function resolveDetailUnitKey($rawUnit, string $unitLabel, string $kancaLabel): ?string
    {
        $cleanRawUnit = $this->cleanBranchValue((string) $rawUnit);

        if ($cleanRawUnit === '') {
            return null;
        }

        $unitSlug = $this->slugKey($unitLabel);
        if ($unitSlug === $this->slugKey($kancaLabel)) {
            return $unitSlug . '-detail';
        }

        return null;
    }

    private function normalizeUnitFilterKeys(array $normalizedUnit): array
    {
        return collect($normalizedUnit)
            ->flatMap(function (string $value): array {
                $slug = $this->slugKey($value);
                $aliases = [$slug];

                // Some upstream unit labels are truncated before the full region
                // suffix (for example "... MADI" instead of "... MADIUN"). Keep
                // current/snapshot filtering aligned with the RKA lookup aliases.
                if (str_ends_with($slug, '-madiun')) {
                    $aliases[] = substr($slug, 0, -2);
                }

                return $aliases;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
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
            return $this->normalizeOfficeUnitLabel($clean);
        }

        if (str_contains($upper, 'UNIT ')) {
            $suffix = trim(substr($clean, stripos($upper, 'UNIT ') + 5));

            return 'UNIT ' . Str::title(Str::lower($suffix));
        }

        return Str::title(Str::lower($clean));
    }

    private function normalizeOfficeUnitLabel(string $value): string
    {
        $clean = $this->cleanBranchValue($value);
        if ($clean === '') {
            return '';
        }

        if (preg_match('/\bKCP\b\s*(.+)$/i', $clean, $matches) === 1) {
            return 'KCP ' . Str::title(Str::lower(trim($matches[1])));
        }

        if (preg_match('/\bKC\b\s*(.+)$/i', $clean, $matches) === 1) {
            return 'KC ' . Str::title(Str::lower(trim($matches[1])));
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

        $parts = [];
        
        // If it looks like a slug (contains hyphens), un-slug it
        if (str_contains($filterValue, '-')) {
            $parts = array_values(array_filter(
                explode('-', Str::lower($filterValue)),
                fn (string $part) => $part !== '' && $part !== 'detail'
            ));
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

        if (empty($parts)) {
            return null;
        }

        $conditions = collect($parts)
            ->map(fn (string $part) => "UPPER({$column}) LIKE '%" . strtoupper($part) . "%'")
            ->implode(' AND ');

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
        // Layer 1: in-request memoization — eliminates repeated DB hits within the same request.
        // Layer 2: file cache (5 min) — eliminates repeated DB hits across rapid successive requests.
        if ($this->sharedPeriodsRequestCache === null) {
            $this->sharedPeriodsRequestCache = app()->runningUnitTests()
                ? $this->computeSharedPeriods()
                : Cache::remember(
                    'dh:shared_periods:all:' . md5(DB::connection()->getName() . '|' . DB::connection()->getDatabaseName()) . ':v' . ReportCacheVersion::composite(['harian', 'pinjaman', 'simpanan']),
                    now()->addMinutes(5),
                    fn (): array => $this->computeSharedPeriods()
                );
        }

        $shared = $this->sharedPeriodsRequestCache;

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

    private function computeSharedPeriods(): array
    {
        $loanPeriods = DB::table(self::LOAN_TABLE)
            ->select('month_day_year_of_periode')
            ->distinct()
            ->pluck('month_day_year_of_periode')
            ->map(fn ($value) => $this->normalizeDate((string) $value))
            ->filter()
            ->values()
            ->all();

        if (Schema::hasTable(self::DLY_KAP_TABLE)) {
            $loanPeriods = array_values(array_unique(array_merge(
                $loanPeriods,
                DB::table(self::DLY_KAP_TABLE)
                    ->select('periode')
                    ->distinct()
                    ->pluck('periode')
                    ->map(fn ($value) => $this->normalizeDate((string) $value))
                    ->filter()
                    ->values()
                    ->all()
            )));
        }

        if (Schema::hasTable(self::L1133_TABLE)) {
            $loanPeriods = array_values(array_unique(array_merge(
                $loanPeriods,
                DB::table(self::L1133_TABLE)
                    ->select('periode')
                    ->distinct()
                    ->pluck('periode')
                    ->map(fn ($value) => $this->normalizeDate((string) $value))
                    ->filter()
                    ->values()
                    ->all()
            )));
        }

        $savingsPeriods = DB::table(self::SAVINGS_TABLE)
            ->select('Month_Day_Year_of_Posisi')
            ->distinct()
            ->pluck('Month_Day_Year_of_Posisi')
            ->map(fn ($value) => $this->normalizeDate((string) $value))
            ->filter()
            ->values()
            ->all();

        if ($this->hourlyDpkEnabled() && Schema::hasTable(self::HOURLY_DPK_TABLE)) {
            $savingsPeriods = array_values(array_unique(array_merge(
                $savingsPeriods,
                DB::table(self::HOURLY_DPK_TABLE)
                    ->select($this->sourcePeriodColumn(self::HOURLY_DPK_TABLE))
                    ->distinct()
                    ->pluck($this->sourcePeriodColumn(self::HOURLY_DPK_TABLE))
                    ->map(fn ($value) => $this->normalizeDate((string) $value))
                    ->filter()
                    ->values()
                    ->all()
            )));
        }

        $shared = array_values(array_filter(
            array_intersect($loanPeriods, $savingsPeriods),
            fn (string $period): bool => $this->dashboardHarianSourceCombinationAvailable($period)
        ));
        rsort($shared);

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
     * @param array<int, string|null> $candidatePeriods
     * @return array<int, string>
     */
    private function normalizeExplicitCandidatePeriods(array $candidatePeriods): array
    {
        $normalized = [];

        foreach ($candidatePeriods as $period) {
            $value = $this->normalizeDate((string) $period);
            if ($value !== null && $this->dashboardHarianSourceCombinationAvailable($value)) {
                $normalized[$value] = $value;
            }
        }

        $periods = array_values($normalized);
        rsort($periods);

        return $periods;
    }

    /**
     * @param array<int, string>|null $periods
     * @return array<string, int>
     */
    private function snapshotCountsForPeriods(?array $periods = null): array
    {
        if (!Schema::hasTable(self::SNAPSHOT_TABLE)) {
            return [];
        }

        $query = DB::table(self::SNAPSHOT_TABLE)
            ->select('snapshot_period')
            ->selectRaw('COUNT(*) as row_count')
            ->groupBy('snapshot_period');

        if ($periods !== null) {
            if ($periods === []) {
                return [];
            }

            $query->whereIn('snapshot_period', $periods);
        }

        return $query
            ->pluck('row_count', 'snapshot_period')
            ->mapWithKeys(fn ($count, $period) => [(string) $period => (int) $count])
            ->all();
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

        foreach ($this->resolveRecentSourcePeriods(self::DLY_KAP_TABLE, $this->sourcePeriodColumn(self::DLY_KAP_TABLE)) as $period) {
            foreach ($this->resolveAffectedSnapshotPeriodsForLoanFallback(self::DLY_KAP_TABLE, $period) as $snapshotPeriod) {
                $candidates[] = $snapshotPeriod;
            }
        }

        foreach ($this->resolveRecentSourcePeriods(self::L1133_TABLE, $this->sourcePeriodColumn(self::L1133_TABLE)) as $period) {
            foreach ($this->resolveAffectedSnapshotPeriodsForLoanFallback(self::L1133_TABLE, $period) as $snapshotPeriod) {
                $candidates[] = $snapshotPeriod;
            }
        }

        foreach ($this->resolveRecentSourcePeriods(self::SAVINGS_TABLE, $this->sourcePeriodColumn(self::SAVINGS_TABLE)) as $period) {
            $candidates[] = $period;
        }

        if ($this->hourlyDpkEnabled() && Schema::hasTable(self::HOURLY_DPK_TABLE)) {
            foreach ($this->resolveRecentSourcePeriods(self::HOURLY_DPK_TABLE, $this->sourcePeriodColumn(self::HOURLY_DPK_TABLE)) as $period) {
                $candidates[] = $period;
            }
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

    private function loanSourcePeriodExists(string $period): bool
    {
        return Schema::hasTable(self::LOAN_TABLE) && $this->sourcePeriodExists(self::LOAN_TABLE, $period);
    }

    private function loanDashboardSourcePeriodExists(string $period): bool
    {
        $normalizedPeriod = $this->normalizeDate($period) ?? $period;

        return $this->loanSourcePeriodExists($period)
            || $this->dlyKapResegmentasiAvailable($normalizedPeriod)
            || $this->l1133Available($normalizedPeriod);
    }

    private function savingsSourcePeriodExists(string $period): bool
    {
        return (Schema::hasTable(self::SAVINGS_TABLE) && $this->sourcePeriodExists(self::SAVINGS_TABLE, $period))
            || $this->hourlyDpkSourcePeriodExists($period);
    }

    private function dashboardHarianSourceCombinationAvailable(string $period): bool
    {
        return $this->resolveDataSourceForPeriod($period) !== 'none';
    }

    private function resolveDataSourceForPeriod(string $period): string
    {
        $normalizedPeriod = $this->normalizeDate($period) ?? $period;
        $hasSsaSavings = Schema::hasTable(self::SAVINGS_TABLE) && $this->sourcePeriodExists(self::SAVINGS_TABLE, $period);
        $hasHourlySavings = $this->hourlyDpkSourcePeriodExists($period);
        $hasFallbackLoan = $this->dlyKapResegmentasiAvailable($normalizedPeriod) || $this->l1133Available($normalizedPeriod);

        if ($this->loanSourcePeriodExists($period) && $hasSsaSavings) {
            return 'option1';
        }

        if ($hasFallbackLoan && $hasSsaSavings) {
            return 'option2';
        }

        if ($hasFallbackLoan && $hasHourlySavings) {
            return 'option3';
        }

        return 'none';
    }



    private function resolveSavingsAggregates(string $period, array|string|null $kancaKey, array|string|null $unitKey): Collection
    {
        return $this->fetchSavingsAggregates($period, $kancaKey, $unitKey);
    }

    private function savingsSourceTableForPeriod(string $period): string
    {
        return Schema::hasTable(self::SAVINGS_TABLE) && $this->sourcePeriodExists(self::SAVINGS_TABLE, $period)
            ? self::SAVINGS_TABLE
            : self::HOURLY_DPK_TABLE;
    }

    private function hourlyDpkSourcePeriodExists(string $period): bool
    {
        return $this->hourlyDpkEnabled()
            && Schema::hasTable(self::HOURLY_DPK_TABLE)
            && $this->sourcePeriodExists(self::HOURLY_DPK_TABLE, $period);
    }

    private function hasAnySavingsSourceTable(): bool
    {
        return Schema::hasTable(self::SAVINGS_TABLE)
            || ($this->hourlyDpkEnabled() && Schema::hasTable(self::HOURLY_DPK_TABLE));
    }

    private function buildSourceMetadata(string $period): ?array
    {
        if (!$this->sourceMetadataColumnsAvailable()) {
            return null;
        }

        try {
            $normalizedPeriod = $this->normalizeDate($period) ?? $period;
            $loanState = $this->sourceAggregateState(
                self::LOAN_TABLE,
                $this->sourcePeriodColumn(self::LOAN_TABLE),
                $this->sourcePeriodRawCandidates(self::LOAN_TABLE, $period),
                ['baki_debet']
            );
            $hasSsaLoan = (int) ($loanState['row_count'] ?? 0) > 0;
            $dlyKapState = $hasSsaLoan
                ? ['row_count' => 0, 'inactive_because' => 'ssa_pinjaman_available']
                : $this->sourceAggregateState(
                    self::DLY_KAP_TABLE,
                    $this->sourcePeriodColumn(self::DLY_KAP_TABLE),
                    $this->sourcePeriodRawCandidates(self::DLY_KAP_TABLE, $period),
                    ['tl_rp', 'dpk_rp', 'npl_rp']
                );
            $l1133Period = $this->resolvePreviousL1133Period($normalizedPeriod);
            $l1133State = $l1133Period === null
                ? ['row_count' => 0]
                : $this->sourceAggregateState(
                    self::L1133_TABLE,
                    $this->sourcePeriodColumn(self::L1133_TABLE),
                    $this->sourcePeriodRawCandidates(self::L1133_TABLE, $l1133Period),
                    ['outstanding', 'dpk', 'npl']
                );

            $savingsTable = $this->savingsSourceTableForPeriod($period);
            $savingsState = $this->sourceAggregateState(
                $savingsTable,
                $this->sourcePeriodColumn($savingsTable),
                $this->sourcePeriodRawCandidates($savingsTable, $period),
                ['saldo']
            );

            [$recoverySource, $recoveryPeriod, $recoveryState] = $this->sourceRecoveryState($period);

            $signaturePayload = [
                'version' => self::SOURCE_SIGNATURE_VERSION,
                'period' => $normalizedPeriod,
                'source_option' => $this->resolveDataSourceForPeriod($normalizedPeriod),
                'loan' => $loanState,
                'dly_kap' => $dlyKapState,
                'l1133' => $l1133State,
                'savings_table' => $savingsTable,
                'savings' => $savingsState,
                'recovery_source' => $recoverySource,
                'recovery_period' => $recoveryPeriod,
                'recovery' => $recoveryState,
            ];

            return [
                'source_signature' => hash('sha256', json_encode($signaturePayload, JSON_UNESCAPED_UNICODE)),
                'source_loan_row_count' => (int) ($loanState['row_count'] ?? 0)
                    + (int) ($dlyKapState['row_count'] ?? 0)
                    + (int) ($l1133State['row_count'] ?? 0),
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
            ->where('periode', $normalizedPeriod)
            ->value('periode');

        if ($currentPhPeriod === null) {
            return ['lw325_ph', null, ['row_count' => 0]];
        }

        $previousPhPeriod = $this->resolvePreviousMonthPhPeriod($currentPhPeriod);
        if (!$this->isPreviousMonthEndPhPeriod($currentPhPeriod, $previousPhPeriod)) {
            Log::warning('Skipping LW325 PH recovery metadata because the comparison period is not the previous month-end.', [
                'current_period' => $currentPhPeriod,
                'comparison_period' => $previousPhPeriod,
            ]);

            return ['lw325_ph', null, ['row_count' => 0, 'guard' => 'previous_month_end_required']];
        }

        return [
            'lw325_ph',
            $previousPhPeriod,
            $this->sourceAggregateState('lw325_ph', 'periode', [$previousPhPeriod], ['pokok']),
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

    private function snapshotPeriodHasDuplicateKeys(string $period): bool
    {
        return app(SnapshotIntegrityGuard::class)->periodHasDuplicateKeys(self::SNAPSHOT_TABLE, $period);
    }

    private function bumpReportCacheVersion(): void
    {
        try {
            ReportCacheVersion::bump('harian');
        } catch (Throwable) {
            // Cache refresh is best-effort; the snapshot rows remain the source of truth.
        }
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
        if ($this->availableSourceMetadataColumnsCache !== null) {
            return $this->availableSourceMetadataColumnsCache;
        }

        if (!Schema::hasTable(self::SNAPSHOT_TABLE)) {
            return $this->availableSourceMetadataColumnsCache = [];
        }

        return $this->availableSourceMetadataColumnsCache = array_values(array_filter(
            self::SOURCE_METADATA_COLUMNS,
            fn (string $column) => Schema::hasColumn(self::SNAPSHOT_TABLE, $column)
        ));
    }

    private function availableSnapshotColumns(): array
    {
        if ($this->availableSnapshotColumnsCache !== null) {
            return $this->availableSnapshotColumnsCache;
        }

        if (!Schema::hasTable(self::SNAPSHOT_TABLE)) {
            return $this->availableSnapshotColumnsCache = [];
        }

        return $this->availableSnapshotColumnsCache = Schema::getColumnListing(self::SNAPSHOT_TABLE);
    }

    private function availableMetricColumns(): array
    {
        $availableColumns = array_flip($this->availableSnapshotColumns());

        return array_values(array_filter(
            self::METRIC_COLUMNS,
            static fn (string $column): bool => isset($availableColumns[$column])
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

    private function resolvePreviousMonthPhPeriod(string $period): ?string
    {
        try {
            $current = Carbon::parse($period);
            $monthEnd = $current->copy()
                ->startOfMonth()
                ->subDay()
                ->toDateString();

            return DB::table('lw325_ph')
                ->where('periode', $monthEnd)
                ->value('periode');
        } catch (Throwable) {
            return null;
        }
    }

    private function isPreviousMonthEndPhPeriod(string $currentPeriod, ?string $comparisonPeriod): bool
    {
        if ($comparisonPeriod === null) {
            return false;
        }

        try {
            $expectedPreviousMonthEnd = Carbon::parse($currentPeriod)
                ->startOfMonth()
                ->subDay()
                ->toDateString();

            return Carbon::parse($comparisonPeriod)->toDateString() === $expectedPreviousMonthEnd;
        } catch (Throwable) {
            return false;
        }
    }

    private function phAccountKeySql(string $alias): string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return "LTRIM(TRIM(COALESCE({$alias}.acctno, '')), '0')";
        }

        return "TRIM(LEADING '0' FROM TRIM(COALESCE({$alias}.acctno, '')))";
    }

    private function canUseSnapshotMetrics(): bool
    {
        if ($this->canUseSnapshotMetricsCache !== null) {
            return $this->canUseSnapshotMetricsCache;
        }

        if (!Schema::hasTable(self::SNAPSHOT_TABLE)) {
            return $this->canUseSnapshotMetricsCache = false;
        }

        $columns = Schema::getColumnListing(self::SNAPSHOT_TABLE);
        $requiredColumns = array_merge(['snapshot_period', 'source_row_count'], self::METRIC_COLUMNS);

        return $this->canUseSnapshotMetricsCache = array_diff($requiredColumns, $columns) === [];
    }

    private function reportProgress(
        ?callable $progress,
        string $snapshotPeriod,
        int $completedUnits,
        int $totalUnits,
        int $currentResultCount = 0
    ): void {
        if ($progress === null) {
            return;
        }

        $progress([
            'current_period' => $snapshotPeriod,
            'completed_units' => max(0, $completedUnits),
            'total_units' => $totalUnits,
            'current_result_count' => max(0, $currentResultCount),
        ]);
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
        if ($group === 'kanca' && $this->isArea6KancaSelection($normalized, collect($options)->filter(fn ($option) => ($option['value'] ?? null) !== 'all'))) {
            return $fallback;
        }

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
            'briguna_konsumer' => "{$segment} = 'CONSUMER' AND {$productDashboard} IN ('BRIGUNA-KONSUMER', 'BRIGUNA-MIKRO')",
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
        return match ($table) {
            self::LOAN_TABLE => 'month_day_year_of_periode',
            self::DLY_KAP_TABLE => 'periode',
            self::L1133_TABLE => 'periode',
            self::HOURLY_DPK_TABLE => 'posisi',
            default => 'Month_Day_Year_of_Posisi',
        };
    }

    private function hourlyDpkEnabled(): bool
    {
        return (bool) config('reports.dashboard_harian.use_hourly_dpk', false);
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

        if ($table === self::DLY_KAP_TABLE || $table === self::L1133_TABLE || $table === self::HOURLY_DPK_TABLE) {
            return array_values(array_unique(array_filter([
                $period,
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
            'simpanan_casa' => 'total_casa',
            'pinjaman' => 'total_os_non_commercial',
            'sml' => 'total_sml_pct_non_commercial',
            'npl' => 'total_npl_abs_non_commercial',
        ];

        $metric = $columnMap[$category] ?? 'total_simpanan';
        $valueType = $category === 'sml' ? 'percent' : 'currency';
        $normalizedKanca = $this->normalizeFilterValues($kancaKey);
        $normalizedUnit = $this->normalizeFilterValues($unitKey);

        $query = DB::table(self::SNAPSHOT_TABLE)
            ->selectRaw('snapshot_period')
            ->selectRaw('kanca_label')
            ->where(function ($q) use ($months) {
                foreach ($months as $month) {
                    $start = "{$month}-01";
                    $end = "{$month}-31";
                    $q->orWhereBetween('snapshot_period', [$start, $end]);
                }
            });

        if ($valueType === 'percent') {
            $query->selectRaw('SUM(total_sml_abs_non_commercial) as numerator')
                ->selectRaw('SUM(total_os_non_commercial) as denominator')
                ->selectRaw('CASE WHEN SUM(total_os_non_commercial) > 0 THEN (SUM(total_sml_abs_non_commercial) * 100.0) / SUM(total_os_non_commercial) ELSE 0 END as value');
        } else {
            $query->selectRaw("SUM({$metric}) as value");
        }

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
        $areaNumerator = [];
        $areaDenominator = [];

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
            $scaledValue = $valueType === 'percent'
                ? (float) $row->value
                : (float) $row->value / 1000000000;
            $series[$kanca][$month][$day] = $scaledValue;

            if (!isset($areaTotal[$month])) {
                $areaTotal[$month] = array_fill(1, 31, null);
            }
            if ($valueType === 'percent') {
                if (!isset($areaNumerator[$month])) {
                    $areaNumerator[$month] = array_fill(1, 31, 0.0);
                    $areaDenominator[$month] = array_fill(1, 31, 0.0);
                }

                $areaNumerator[$month][$day] += (float) ($row->numerator ?? 0);
                $areaDenominator[$month][$day] += (float) ($row->denominator ?? 0);
                $areaTotal[$month][$day] = $areaDenominator[$month][$day] > 0
                    ? ($areaNumerator[$month][$day] / $areaDenominator[$month][$day]) * 100
                    : null;
            } else {
                $areaTotal[$month][$day] = ($areaTotal[$month][$day] ?? 0) + $scaledValue;
            }
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
            'value_type' => $valueType,
        ];
    }
}
