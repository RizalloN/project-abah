<?php
/**
 * Verify recovery DH formula:
 * TUPOK: accounts dengan principal berkurang (o.pokok - n.pokok > 0)
 * LUNAS: accounts yang di periode sebelumnya ada, periode sekarang tidak ada
 * 
 * Recovery Amount = Previous period principal (o.pokok)
 */

require_once 'bootstrap/app.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== RECOVERY DH FORMULA VERIFICATION ===\n\n";

// Use April 19 and April 18
$current_period = '2026-04-19';
$previous_period = '2026-04-18';

echo "Current Period: $current_period\n";
echo "Previous Period: $previous_period\n\n";

// Check if data exists
$currentExists = DB::table('lw325_ph')->where('periode', $current_period)->count();
$prevExists = DB::table('lw325_ph')->where('periode', $previous_period)->count();

echo "LW325_PH Data Check:\n";
echo "  Current ($current_period): $currentExists rows\n";
echo "  Previous ($previous_period): $prevExists rows\n\n";

if ($currentExists == 0 || $prevExists == 0) {
    echo "❌ Missing period data - cannot proceed\n";
    exit(1);
}

// Area 6 kancas
$area6_kancas = ['Madiun', 'Magetan', 'Ngawi', 'Ponorogo'];

echo "=== 1. TUPOK CALCULATION (Principal Decreased) ===\n";
echo "Logic: WHERE (o.pokok - n.pokok) > 0\n";
echo "Formula: Recovery Amount = o.pokok (previous period principal)\n\n";

$tupok = DB::table('lw325_ph as n')
    ->join('lw325_ph as o', function ($join) use ($current_period, $previous_period) {
        $join->on('n.acctno', '=', 'o.acctno')
            ->on('n.kanca', '=', 'o.kanca')
            ->on('n.unit', '=', 'o.unit')
            ->where('n.periode', $current_period)
            ->where('o.periode', $previous_period);
    })
    ->whereRaw('(COALESCE(o.pokok, 0) - COALESCE(n.pokok, 0)) > 0');

// Get count and total
$tupok_count = (clone $tupok)->count();
$tupok_total = (clone $tupok)->sum(DB::raw('COALESCE(o.pokok, 0)'));

// Per segment breakdown
$tupok_by_segment = (clone $tupok)
    ->select(DB::raw("UPPER(TRIM(COALESCE(n.segmen_dashboard, ''))) as segment"))
    ->selectRaw('COUNT(*) as count')
    ->selectRaw('SUM(COALESCE(o.pokok, 0)) as amount')
    ->groupBy(DB::raw("UPPER(TRIM(COALESCE(n.segmen_dashboard, '')))"))
    ->get();

echo "TUPOK - Total Accounts: $tupok_count\n";
echo "TUPOK - Total Amount: " . number_format($tupok_total, 2) . "\n";
echo "TUPOK - By Segment:\n";
foreach ($tupok_by_segment as $row) {
    $seg = $row->segment ?: 'UNKNOWN';
    echo "  $seg: {$row->count} accounts, " . number_format($row->amount, 2) . "\n";
}

echo "\n=== 2. LUNAS CALCULATION (Paid Off) ===\n";
echo "Logic: WHERE o.periode = previous AND n.acctno IS NULL (not in current)\n";
echo "Formula: Recovery Amount = o.pokok (previous period principal)\n\n";

$lunas = DB::table('lw325_ph as o')
    ->leftJoin('lw325_ph as n', function ($join) use ($current_period, $previous_period) {
        $join->on('o.acctno', '=', 'n.acctno')
            ->on('o.kanca', '=', 'n.kanca')
            ->on('o.unit', '=', 'n.unit')
            ->where('o.periode', $previous_period)
            ->where('n.periode', $current_period);
    })
    ->where('o.periode', $previous_period)
    ->whereNull('n.acctno');

// Get count and total
$lunas_count = (clone $lunas)->count();
$lunas_total = (clone $lunas)->sum(DB::raw('COALESCE(o.pokok, 0)'));

// Per segment breakdown
$lunas_by_segment = (clone $lunas)
    ->select(DB::raw("UPPER(TRIM(COALESCE(o.segmen_dashboard, ''))) as segment"))
    ->selectRaw('COUNT(*) as count')
    ->selectRaw('SUM(COALESCE(o.pokok, 0)) as amount')
    ->groupBy(DB::raw("UPPER(TRIM(COALESCE(o.segmen_dashboard, '')))"))
    ->get();

echo "LUNAS - Total Accounts: $lunas_count\n";
echo "LUNAS - Total Amount: " . number_format($lunas_total, 2) . "\n";
echo "LUNAS - By Segment:\n";
foreach ($lunas_by_segment as $row) {
    $seg = $row->segment ?: 'UNKNOWN';
    echo "  $seg: {$row->count} accounts, " . number_format($row->amount, 2) . "\n";
}

echo "\n=== 3. TOTAL RECOVERY (TUPOK + LUNAS) ===\n";
$total_recovery = $tupok_total + $lunas_total;
echo "Total Recovery: " . number_format($total_recovery, 2) . "\n";
echo "Total Accounts: " . ($tupok_count + $lunas_count) . "\n";

// Combined by segment
$combined_by_segment = DB::query()
    ->fromSub(
        DB::table('lw325_ph as n')
            ->join('lw325_ph as o', function ($join) use ($current_period, $previous_period) {
                $join->on('n.acctno', '=', 'o.acctno')
                    ->on('n.kanca', '=', 'o.kanca')
                    ->on('n.unit', '=', 'o.unit')
                    ->where('n.periode', $current_period)
                    ->where('o.periode', $previous_period);
            })
            ->whereRaw('(COALESCE(o.pokok, 0) - COALESCE(n.pokok, 0)) > 0')
            ->select(DB::raw("UPPER(TRIM(COALESCE(n.segmen_dashboard, ''))) as segment"))
            ->selectRaw('COALESCE(o.pokok, 0) as amount')
            ->unionAll(
                DB::table('lw325_ph as o')
                    ->leftJoin('lw325_ph as n', function ($join) use ($current_period, $previous_period) {
                        $join->on('o.acctno', '=', 'n.acctno')
                            ->on('o.kanca', '=', 'n.kanca')
                            ->on('o.unit', '=', 'n.unit')
                            ->where('o.periode', $previous_period)
                            ->where('n.periode', $current_period);
                    })
                    ->where('o.periode', $previous_period)
                    ->whereNull('n.acctno')
                    ->select(DB::raw("UPPER(TRIM(COALESCE(o.segmen_dashboard, ''))) as segment"))
                    ->selectRaw('COALESCE(o.pokok, 0) as amount')
            ),
        'combined_recovery'
    )
    ->select('segment')
    ->selectRaw('COUNT(*) as accounts')
    ->selectRaw('SUM(amount) as total')
    ->groupBy('segment')
    ->get();

echo "\nCombined By Segment:\n";
foreach ($combined_by_segment as $row) {
    $seg = $row->segment ?: 'UNKNOWN';
    echo "  $seg: {$row->accounts} accounts, " . number_format($row->total, 2) . "\n";
}

echo "\n=== 4. VERIFY IN SNAPSHOT ===\n";
$snapshot = DB::table('dashboard_harian_snapshots')
    ->where('snapshot_period', $current_period)
    ->whereColumn('kanca_key', 'unit_key')
    ->select(
        'kanca_key',
        DB::raw('SUM(rec_dh_small) as rec_small'),
        DB::raw('SUM(rec_dh_consumer) as rec_consumer'),
        DB::raw('SUM(rec_dh_micro) as rec_micro'),
        DB::raw('SUM(rec_dh_total) as rec_total')
    )
    ->groupBy('kanca_key')
    ->get();

echo "Snapshot Recovery (Branch Level):\n";
$snap_total = 0;
foreach ($snapshot as $row) {
    echo "  {$row->kanca_key}: " . number_format($row->rec_total, 2);
    echo " (Small: " . number_format($row->rec_small, 2);
    echo ", Consumer: " . number_format($row->rec_consumer, 2);
    echo ", Micro: " . number_format($row->rec_micro, 2) . ")\n";
    $snap_total += $row->rec_total;
}
echo "  TOTAL: " . number_format($snap_total, 2) . "\n";

echo "\n=== 5. COMPARISON ===\n";
echo "Calculated Recovery: " . number_format($total_recovery, 2) . "\n";
echo "Snapshot Recovery:   " . number_format($snap_total, 2) . "\n";
echo "Difference:          " . number_format($total_recovery - $snap_total, 2) . "\n";
echo "Match: " . (abs($total_recovery - $snap_total) < 1 ? "✅ YES" : "❌ NO") . "\n";

echo "\n=== 6. CHECK IF 1.73M MATCHES ANY CALCULATION ===\n";
$target = 1730000000; // 1.73M
echo "Looking for 1.73M in calculations...\n";
echo "  Tupok Total: " . number_format($tupok_total, 2) . " (" . ($tupok_total == $target ? "✅ MATCH" : "❌") . ")\n";
echo "  Lunas Total: " . number_format($lunas_total, 2) . " (" . ($lunas_total == $target ? "✅ MATCH" : "❌") . ")\n";
echo "  Total Recovery: " . number_format($total_recovery, 2) . " (" . ($total_recovery == $target ? "✅ MATCH" : "❌") . ")\n";

// Check if 1.73M appears in ANY branch
$branch_totals = DB::table('dashboard_harian_snapshots')
    ->where('snapshot_period', $current_period)
    ->select('kanca_key')
    ->selectRaw('SUM(rec_dh_total) as total')
    ->groupBy('kanca_key')
    ->get();

echo "\n  Snapshot by Branch:\n";
foreach ($branch_totals as $row) {
    if (abs($row->total - $target) < 1) {
        echo "    {$row->kanca_key}: " . number_format($row->total, 2) . " ✅ MATCHES 1.73M\n";
    } else {
        echo "    {$row->kanca_key}: " . number_format($row->total, 2) . "\n";
    }
}
