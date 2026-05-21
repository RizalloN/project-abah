<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Throwable;

class DashboardCrossAlignmentGuard
{
    private const BRANCHES = [
        'KC Madiun',
        'KC Magetan',
        'KC Ngawi',
        'KC Ponorogo',
    ];

    /**
     * Automatically aligns Funds (Dana) dashboard rows and totals with Harian dashboard metrics.
     */
    public static function alignFunds(array $payload, string $selectedPeriod, ?string $category, ?string $rkaPeriod): array
    {
        try {
            $harianService = app(DashboardHarianSnapshotService::class);
            $rows = $payload['rows'] ?? [];

            foreach ($rows as &$row) {
                if ($row['is_total'] ?? false) {
                    continue;
                }

                $branchName = self::getStandardBranch($row['nama_cabang'] ?? '');
                if (!$branchName) {
                    continue;
                }

                $kategori = $row['kategori'] ?? '';
                $harianKey = self::getHarianKeyForDana($category, $kategori);
                if (!$harianKey) {
                    continue;
                }

                // Fetch metrics from Daily Performance Dashboard
                $harianPayload = $harianService->buildDashboardPayload($selectedPeriod, $rkaPeriod, $branchName);
                $harianRows = $harianPayload['rows'] ?? [];

                $selectedVal = self::getHarianMetric($harianRows, $category, $harianKey, 'current');
                $ytdVal = self::getHarianMetric($harianRows, $category, $harianKey, 'ytd');
                $mtdVal = self::getHarianMetric($harianRows, $category, $harianKey, 'mtd');
                $rkaVal = self::getHarianMetric($harianRows, $category, $harianKey, 'rka');

                // Auto-fix discrepancies
                $row['selected'] = $selectedVal;
                $row['ytd'] = $ytdVal;
                $row['mtd'] = $mtdVal;
                $row['delta_ytd'] = $selectedVal - $ytdVal;
                $row['delta_mtd'] = $selectedVal - $mtdVal;

                $row['rka_rp'] = $selectedVal - $rkaVal;
                $row['rka_pct'] = $rkaVal > 0 ? ($selectedVal / $rkaVal) * 100 : 0;
            }
            unset($row);

            // Re-calculate Branch Total rows (TOTAL CABANG) for mathematical consistency
            foreach (self::BRANCHES as $branch) {
                $totalRowIdx = null;
                $branchRows = [];

                foreach ($rows as $idx => $row) {
                    if (self::getStandardBranch($row['nama_cabang'] ?? '') === $branch) {
                        if ($row['is_total'] ?? false) {
                            $totalRowIdx = $idx;
                        } else {
                            $branchRows[] = $row;
                        }
                    }
                }

                if ($totalRowIdx !== null && !empty($branchRows)) {
                    // Summarize Giro + Tabungan + Deposito
                    $giro = collect($branchRows)->firstWhere('kategori', 'Giro');
                    $tab = collect($branchRows)->firstWhere('kategori', 'Tabungan');
                    $dep = collect($branchRows)->firstWhere('kategori', 'Deposito');

                    $giroSel = $giro['selected'] ?? 0.0;
                    $tabSel = $tab['selected'] ?? 0.0;
                    $depSel = $dep['selected'] ?? 0.0;

                    $giroYtd = $giro['ytd'] ?? 0.0;
                    $tabYtd = $tab['ytd'] ?? 0.0;
                    $depYtd = $dep['ytd'] ?? 0.0;

                    $giroMtd = $giro['mtd'] ?? 0.0;
                    $tabMtd = $tab['mtd'] ?? 0.0;
                    $depMtd = $dep['mtd'] ?? 0.0;

                    $rows[$totalRowIdx]['selected'] = $giroSel + $tabSel + $depSel;
                    $rows[$totalRowIdx]['ytd'] = $giroYtd + $tabYtd + $depYtd;
                    $rows[$totalRowIdx]['mtd'] = $giroMtd + $tabMtd + $depMtd;

                    $rows[$totalRowIdx]['delta_ytd'] = $rows[$totalRowIdx]['selected'] - $rows[$totalRowIdx]['ytd'];
                    $rows[$totalRowIdx]['delta_mtd'] = $rows[$totalRowIdx]['selected'] - $rows[$totalRowIdx]['mtd'];

                    // Target RKA
                    $harianPayload = $harianService->buildDashboardPayload($selectedPeriod, $rkaPeriod, $branch);
                    $harianRows = $harianPayload['rows'] ?? [];
                    $totalHarianKey = self::getHarianKeyForDana($category, 'TOTAL CABANG');
                    $rkaVal = self::getHarianMetric($harianRows, $category, $totalHarianKey, 'rka');

                    $rows[$totalRowIdx]['rka_rp'] = $rows[$totalRowIdx]['selected'] - $rkaVal;
                    $rows[$totalRowIdx]['rka_pct'] = $rkaVal > 0 ? ($rows[$totalRowIdx]['selected'] / $rkaVal) * 100 : 0;
                }
            }

            $payload['rows'] = $rows;

            // Re-calculate Grand Totals
            $grandTotal = [
                'selected' => 0.0, 'ytd' => 0.0, 'mtd' => 0.0,
                'delta_ytd' => 0.0, 'delta_mtd' => 0.0,
                'rka_rp' => 0.0, 'rka_pct' => 0.0
            ];

            foreach ($rows as $row) {
                if ($row['is_total'] === true) {
                    $grandTotal['selected'] += (float) ($row['selected'] ?? 0);
                    $grandTotal['ytd'] += (float) ($row['ytd'] ?? 0);
                    $grandTotal['mtd'] += (float) ($row['mtd'] ?? 0);
                    $grandTotal['delta_ytd'] += (float) ($row['delta_ytd'] ?? 0);
                    $grandTotal['delta_mtd'] += (float) ($row['delta_mtd'] ?? 0);
                    $grandTotal['rka_rp'] += (float) ($row['rka_rp'] ?? 0);
                }
            }

            $rkaTotalVal = $grandTotal['selected'] - $grandTotal['rka_rp'];
            $grandTotal['rka_pct'] = $rkaTotalVal > 0 ? ($grandTotal['selected'] / $rkaTotalVal) * 100 : 0;

            $payload['total'] = $grandTotal;

        } catch (Throwable $e) {
            Log::warning('DashboardCrossAlignmentGuard Funds alignment failed', ['error' => $e->getMessage()]);
        }

        return $payload;
    }

    /**
     * Automatically aligns Credit (Pinjaman) dashboard segments with Harian dashboard metrics.
     */
    public static function alignCredit(array $payload, string $selectedPeriod, string $segment): array
    {
        try {
            $harianService = app(DashboardHarianSnapshotService::class);

            foreach (['os', 'sml', 'npl'] as $type) {
                if (!isset($payload[$type])) {
                    continue;
                }

                $rows = $payload[$type];

                foreach ($rows as &$row) {
                    if (($row['is_total'] ?? false) || ($row['branch'] ?? '') === 'TOTAL') {
                        continue;
                    }

                    $branchName = self::getStandardBranch($row['branch'] ?? '');
                    if (!$branchName) {
                        continue;
                    }

                    $category = $row['category'] ?? '';
                    $harianKey = self::getHarianKeyForCredit($segment, $category, $type);
                    if (!$harianKey) {
                        continue;
                    }

                    // Fetch metric values from Daily Performance Dashboard
                    $harianPayload = $harianService->buildDashboardPayload($selectedPeriod, null, $branchName);
                    $harianRows = $harianPayload['rows'] ?? [];

                    $selectedVal = self::getHarianMetric($harianRows, null, $harianKey, 'current');
                    $ytdVal = self::getHarianMetric($harianRows, null, $harianKey, 'ytd');
                    $m2Val = self::getHarianMetric($harianRows, null, $harianKey, 'm2');
                    $mtmVal = self::getHarianMetric($harianRows, null, $harianKey, 'mtm');
                    $mtdVal = self::getHarianMetric($harianRows, null, $harianKey, 'mtd');
                    $rkaDec = self::getHarianMetric($harianRows, null, $harianKey, 'rka_dec');
                    $rkaCur = self::getHarianMetric($harianRows, null, $harianKey, 'rka');

                    // Auto-fix discrepancies
                    $row['selected'] = $selectedVal;
                    $row['ytd'] = $ytdVal;
                    $row['m2'] = $m2Val;
                    $row['mtm'] = $mtmVal;
                    $row['mtd'] = $mtdVal;

                    $row['delta_ytd'] = $selectedVal - $ytdVal;
                    $row['delta_mom'] = $selectedVal - $mtmVal;
                    $row['delta_mtd'] = $selectedVal - $mtdVal;

                    $row['rka_m1'] = $rkaDec;
                    $row['rka_current'] = $rkaCur;

                    $row['penc_m1_rp'] = $selectedVal - $rkaDec;
                    $row['penc_m1_pct'] = self::calculateRkaPct($selectedVal, $rkaDec, $type);

                    $row['penc_cur_rp'] = $selectedVal - $rkaCur;
                    $row['penc_cur_pct'] = self::calculateRkaPct($selectedVal, $rkaCur, $type);
                }
                unset($row);

                // Re-calculate the Grand Total row
                $totalRowIdx = null;
                foreach ($rows as $idx => $row) {
                    if (($row['is_total'] ?? false) || ($row['branch'] ?? '') === 'TOTAL') {
                        $totalRowIdx = $idx;
                        break;
                    }
                }

                if ($totalRowIdx !== null) {
                    $totalRow = [
                        'no' => 'TOTAL',
                        'branch' => 'TOTAL',
                        'area_head' => '',
                        'category' => '',
                        'ytd' => 0.0,
                        'm2' => 0.0,
                        'mtm' => 0.0,
                        'mtd' => 0.0,
                        'selected' => 0.0,
                        'delta_ytd' => 0.0,
                        'delta_mom' => 0.0,
                        'delta_mtd' => 0.0,
                        'rka_m1' => 0.0,
                        'rka_current' => 0.0,
                        'penc_m1_rp' => 0.0,
                        'penc_m1_pct' => 0.0,
                        'penc_cur_rp' => 0.0,
                        'penc_cur_pct' => 0.0,
                        'is_total' => true,
                    ];

                    foreach ($rows as $row) {
                        if (($row['is_total'] ?? false) || ($row['branch'] ?? '') === 'TOTAL') {
                            continue;
                        }

                        if (!self::shouldIncludeInGrandTotal($segment, (string) ($row['category'] ?? ''))) {
                            continue;
                        }

                        $totalRow['ytd'] += (float) ($row['ytd'] ?? 0);
                        $totalRow['m2'] += (float) ($row['m2'] ?? 0);
                        $totalRow['mtm'] += (float) ($row['mtm'] ?? 0);
                        $totalRow['mtd'] += (float) ($row['mtd'] ?? 0);
                        $totalRow['selected'] += (float) ($row['selected'] ?? 0);
                        $totalRow['delta_ytd'] += (float) ($row['delta_ytd'] ?? 0);
                        $totalRow['delta_mom'] += (float) ($row['delta_mom'] ?? 0);
                        $totalRow['delta_mtd'] += (float) ($row['delta_mtd'] ?? 0);
                        $totalRow['rka_m1'] += (float) ($row['rka_m1'] ?? 0);
                        $totalRow['rka_current'] += (float) ($row['rka_current'] ?? 0);
                    }

                    $totalRow['penc_m1_rp'] = $totalRow['selected'] - $totalRow['rka_m1'];
                    $totalRow['penc_m1_pct'] = self::calculateRkaPct($totalRow['selected'], $totalRow['rka_m1'], $type);

                    $totalRow['penc_cur_rp'] = $totalRow['selected'] - $totalRow['rka_current'];
                    $totalRow['penc_cur_pct'] = self::calculateRkaPct($totalRow['selected'], $totalRow['rka_current'], $type);

                    $rows[$totalRowIdx] = $totalRow;
                }

                $payload[$type] = $rows;
            }

        } catch (Throwable $e) {
            Log::warning('DashboardCrossAlignmentGuard Credit alignment failed', ['error' => $e->getMessage()]);
        }

        return $payload;
    }

    private static function getStandardBranch(string $branch): ?string
    {
        $upper = strtoupper(trim($branch));
        if (str_contains($upper, 'MADIUN')) return 'KC Madiun';
        if (str_contains($upper, 'MAGETAN')) return 'KC Magetan';
        if (str_contains($upper, 'NGAWI')) return 'KC Ngawi';
        if (str_contains($upper, 'PONOROGO')) return 'KC Ponorogo';
        return null;
    }

    private static function getHarianKeyForDana(?string $category, string $kategori): ?string
    {
        $category = strtolower(trim((string) $category));
        if ($category === 'ritel') {
            return match ($kategori) {
                'Giro' => 'giro_ritel',
                'Tabungan' => 'tabungan_ritel',
                'Deposito' => 'deposito_ritel',
                'CASA' => 'casa_ritel',
                'TOTAL CABANG' => 'simpanan_ritel',
                default => null,
            };
        } elseif ($category === 'mikro' || $category === 'micro') {
            return match ($kategori) {
                'Giro' => 'giro_mikro',
                'Tabungan' => 'tabungan_mikro',
                'Deposito' => 'deposito_mikro',
                'CASA' => 'casa_mikro',
                'TOTAL CABANG' => 'simpanan_mikro',
                default => null,
            };
        } elseif ($category === 'wholesale') {
            return match ($kategori) {
                'Giro' => 'giro_wholesale',
                'Tabungan' => 'tabungan_wholesale',
                'Deposito' => 'deposito_wholesale',
                'CASA' => 'giro_wholesale',
                'TOTAL CABANG' => 'simpanan_wholesale',
                default => null,
            };
        } else {
            return match ($kategori) {
                'Giro' => 'giro_all',
                'Tabungan' => 'tabungan_all',
                'Deposito' => 'deposito_all',
                'CASA' => 'casa_all',
                'TOTAL CABANG' => 'total_simpanan',
                default => null,
            };
        }
    }

    private static function getHarianKeyForCredit(string $segment, string $category, string $type): ?string
    {
        $segment = strtoupper(trim($segment));
        if ($segment === 'SME') {
            return match ($category) {
                'Kecil non Cashcoll' => "kecil_non_cashcoll_{$type}",
                'Cashcoll' => "cashcoll_{$type}",
                default => null,
            };
        } elseif ($segment === 'CONSUMER') {
            return match ($category) {
                'Briguna Konsumer' => "briguna_konsumer_{$type}",
                'KPR' => "kpr_{$type}",
                'KKB' => "kkb_{$type}",
                default => null,
            };
        } elseif ($segment === 'MIKRO') {
            return match ($category) {
                'Micro' => "micro_{$type}",
                'Briguna Mikro' => "briguna_mikro_{$type}",
                'Kupedes' => "kupedes_{$type}",
                'KUR Mikro' => "kur_mikro_{$type}",
                'KUR Kecil' => "kur_kecil_{$type}",
                'KUR KPP' => "kur_kpp_{$type}",
                default => null,
            };
        }
        return null;
    }

    private static function getHarianMetric(array $harianRows, ?string $category, string $key, string $type = 'current'): float
    {
        if (str_ends_with($key, '_all')) {
            $base = substr($key, 0, -4);
            return self::getHarianMetric($harianRows, null, "{$base}_ritel", $type) +
                   self::getHarianMetric($harianRows, null, "{$base}_mikro", $type) +
                   self::getHarianMetric($harianRows, null, "{$base}_wholesale", $type);
        }

        if ($key === 'casa_all') {
            return self::getHarianMetric($harianRows, null, 'casa_ritel', $type) +
                   self::getHarianMetric($harianRows, null, 'casa_mikro', $type);
        }

        $row = collect($harianRows)->firstWhere('key', $key);
        if (!$row) {
            return 0.0;
        }

        if ($type === 'rka') {
            return (float) ($row['values']['rka'] ?? 0);
        }
        if ($type === 'rka_dec') {
            return (float) ($row['values']['rka_dec'] ?? 0);
        }
        return (float) ($row['values'][$type] ?? 0);
    }

    private static function calculateRkaPct(float $selected, float $rka, string $type): float
    {
        $isQuality = in_array(strtolower($type), ['sml', 'npl'], true);
        if ($isQuality) {
            return $selected > 0 ? ($rka / $selected) * 100 : 100;
        }
        return $rka > 0 ? ($selected / $rka) * 100 : 0;
    }

    private static function shouldIncludeInGrandTotal(string $segment, string $category): bool
    {
        if (strtoupper($segment) === 'MIKRO' && $category === 'Micro') {
            return false;
        }
        return true;
    }
}
