<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

$period = '2026-05-19';

// We will replicate resolveHistoryDateRange
$selectedDate = Carbon::parse($period);
$historyStart = $selectedDate->copy()->subYearNoOverflow()->startOfYear()->toDateString();
$historyEnd = $selectedDate->toDateString();

echo "History Date Range: $historyStart to $historyEnd\n\n";

// --- CONSUMER HISTORY ---
echo "=== CONSUMER HISTORY FOR 00079608 - ARIS SULISTYAWAN ===\n";
// Let's run consumerRmLookupKeys
$rm = '00079608 - ARIS SULISTYAWAN';
$normalized = strtoupper(trim($rm));
$rmKeys = [$normalized];
if (str_starts_with($normalized, '00385844 - GLAGAH')) {
    $rmKeys[] = '00385844 -';
}

$periods = DB::table('daily_loan_dinamis')
    ->whereBetween('periode', [$historyStart, $historyEnd])
    ->whereIn('segmen_kinerja', ['CONSUMER'])
    ->whereIn('produk_kinerja', ['BRIGUNAKONSUMER', 'KPR'])
    ->whereIn('rm_normalized', $rmKeys)
    ->select('periode')
    ->distinct()
    ->orderBy('periode')
    ->pluck('periode')
    ->map(fn ($period) => (string) $period)
    ->all();

echo "Distinct Periods found: " . implode(', ', $periods) . "\n";

$latestByMonth = [];
foreach ($periods as $p) {
    $latestByMonth[substr($p, 0, 7)] = $p;
}
echo "Latest by month: " . implode(', ', $latestByMonth) . "\n";

$details = collect();
foreach (array_values($latestByMonth) as $p) {
    // resolvePreviousMonthSourcePeriod
    $periodDate = Carbon::parse($p);
    $previousStart = $periodDate->copy()->subMonthNoOverflow()->startOfMonth()->toDateString();
    $previousEnd = $periodDate->copy()->subMonthNoOverflow()->endOfMonth()->toDateString();

    $previousPeriod = DB::table('daily_loan_dinamis')
        ->whereBetween('periode', [$previousStart, $previousEnd])
        ->max('periode');
        
    echo "Period: $p => Previous Period: " . ($previousPeriod ?? 'NONE') . "\n";
    if ($previousPeriod === null) {
        continue;
    }
    
    // fetchConsumerSurplusAccountDetails
    $productSql = "CASE WHEN produk_kinerja = 'BRIGUNAKONSUMER' THEN 'BRIGUNA-KONSUMER' ELSE produk_kinerja END";

    $previousPlafonByGroup = DB::table('daily_loan_dinamis')
        ->where('periode', $previousPeriod)
        ->whereIn('segmen_kinerja', ['CONSUMER'])
        ->whereIn('produk_kinerja', ['BRIGUNAKONSUMER', 'KPR'])
        ->whereIn('rm_normalized', $rmKeys)
        ->whereNotNull('pn_pengelola1')
        ->where('pn_pengelola1', '<>', '')
        ->selectRaw("COALESCE(cabang_normalized, UPPER(TRIM(cabang1)), '') as cabang")
        ->selectRaw("COALESCE(unit_normalized, UPPER(TRIM(unit1)), '') as unit")
        ->selectRaw("COALESCE(branch_normalized, '') as branch_code")
        ->selectRaw("COALESCE(rm_normalized, UPPER(TRIM(pn_pengelola1)), '') as rm")
        ->selectRaw("{$productSql} as produk")
        ->selectRaw('SUM(COALESCE(plafon, 0)) as plafon')
        ->groupByRaw("COALESCE(cabang_normalized, UPPER(TRIM(cabang1)), ''), COALESCE(unit_normalized, UPPER(TRIM(unit1)), ''), COALESCE(branch_normalized, ''), COALESCE(rm_normalized, UPPER(TRIM(pn_pengelola1)), ''), {$productSql}")
        ->get()
        ->mapWithKeys(fn ($row): array => [
            implode('|', [
                (string) ($row->cabang ?? ''),
                (string) ($row->unit ?? ''),
                (string) ($row->branch_code ?? ''),
                (string) ($row->rm ?? ''),
                (string) ($row->produk ?? ''),
            ]) => (float) $row->plafon,
        ]);

    $currentRows = DB::table('daily_loan_dinamis')
        ->where('periode', $p)
        ->whereIn('segmen_kinerja', ['CONSUMER'])
        ->whereIn('produk_kinerja', ['BRIGUNAKONSUMER', 'KPR'])
        ->whereIn('rm_normalized', $rmKeys)
        ->whereNotNull('pn_pengelola1')
        ->where('pn_pengelola1', '<>', '')
        ->whereNotNull('nomor_rekening1')
        ->where('nomor_rekening1', '<>', '')
        ->selectRaw("COALESCE(cabang_normalized, UPPER(TRIM(cabang1)), '') as cabang")
        ->selectRaw("COALESCE(unit_normalized, UPPER(TRIM(unit1)), '') as unit")
        ->selectRaw("COALESCE(branch_normalized, '') as branch_code")
        ->selectRaw("COALESCE(rm_normalized, UPPER(TRIM(pn_pengelola1)), '') as rm")
        ->selectRaw("{$productSql} as produk")
        ->selectRaw('COUNT(DISTINCT nomor_rekening1) as debitur')
        ->selectRaw('SUM(COALESCE(plafon, 0)) as plafon')
        ->groupByRaw("COALESCE(cabang_normalized, UPPER(TRIM(cabang1)), ''), COALESCE(unit_normalized, UPPER(TRIM(unit1)), ''), COALESCE(branch_normalized, ''), COALESCE(rm_normalized, UPPER(TRIM(pn_pengelola1)), ''), {$productSql}")
        ->get();
        
    echo "  Current rows count: " . count($currentRows) . "\n";
    
    $monthDetails = $currentRows->map(function ($row) use ($previousPlafonByGroup, $p, $previousPeriod) {
        $key = implode('|', [
            (string) ($row->cabang ?? ''),
            (string) ($row->unit ?? ''),
            (string) ($row->branch_code ?? ''),
            (string) ($row->rm ?? ''),
            (string) ($row->produk ?? ''),
        ]);

        if (!$previousPlafonByGroup->has($key)) {
            return null;
        }

        $previous = (float) $previousPlafonByGroup[$key];
        if ($previous <= 0.0) {
            return null;
        }

        $current = (float) ($row->plafon ?? 0);
        $surplus = max(0.0, $current - $previous);
        if ($surplus <= 0.0) {
            return null;
        }

        return [
            'periode' => $p,
            'previous_period' => $previousPeriod,
            'produk' => $row->produk,
            'previous_plafon' => $previous,
            'current_plafon' => $current,
            'surplus_plafon' => $surplus,
        ];
    })->filter()->values();
    
    echo "  Surplus rows count: " . count($monthDetails) . "\n";
    foreach ($monthDetails as $md) {
        echo "    Prod: {$md['produk']} | Prev Plafon: {$md['previous_plafon']} | Curr Plafon: {$md['current_plafon']} | Surplus: {$md['surplus_plafon']}\n";
    }
    
    $details = $details->merge($monthDetails);
}

echo "\n--- SMALL HISTORY FOR 00320720 - IRVAN ROZIQIN ---\n";
$rmSmall = '00320720 - IRVAN ROZIQIN';
$historySmall = DB::table('performance_rm_snapshots')
    ->where('rm', $rmSmall)
    ->where('segmen', 'SMALL')
    ->whereBetween('periode', [$historyStart, $historyEnd])
    ->orderByDesc('periode')
    ->get();

echo "Snapshot history records found: " . count($historySmall) . "\n";
// Group by Month and Branch
$groupsSmall = $historySmall->groupBy(function ($row) {
    return Carbon::parse($row->periode)->format('Y-m') . '|' . $row->cabang;
});

echo "Unique Month-Branch groups: " . implode(', ', array_keys($groupsSmall->toArray())) . "\n";

foreach ($groupsSmall as $gKey => $group) {
    $latestDate = $group->first()->periode;
    $latestDateRows = $group->where('periode', $latestDate);

    $loanOs = $latestDateRows->sum('loan_os');
    $smlOs = $latestDateRows->sum('sml_os');
    $nplOs = $latestDateRows->sum('npl_os');
    $restrukOs = $latestDateRows->sum('restruk_os');
    $realisasiOs = $latestDateRows->sum('realisasi_os');

    $lar = (float)$restrukOs + (float)$smlOs + (float)$nplOs;
    $pctLar = $loanOs > 0 ? ($lar / $loanOs) * 100 : 0;
    
    $isRealizA = ($realisasiOs / 1000000) >= 1600;
    $isLarA = $pctLar < 17.5;
    
    echo "  Group $gKey (Latest Date: $latestDate):\n";
    echo "    loan_os: $loanOs, realisasi_os: $realisasiOs (Realisasi A: " . ($isRealizA ? 'YES' : 'NO') . ")\n";
    echo "    LAR os: $lar, % LAR: $pctLar% (LAR A: " . ($isLarA ? 'YES' : 'NO') . ")\n";
}
