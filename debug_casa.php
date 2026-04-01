<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== DEBUG CASA QUERY ===\n\n";

$targetDate = '2026-02-28';
$branchName = 'KC Madiun';

// 1. Check CIFs in daily_loan_dinamis for this branch
$loanCifs = DB::table('daily_loan_dinamis')
    ->where('periode', $targetDate)
    ->where('cabang', 'LIKE', '%Madiun%')
    ->select('cifno', 'cabang', 'segmen_dashboard')
    ->distinct()
    ->limit(20)
    ->get();

echo "1. CIFs in daily_loan_dinamis for Madiun (sample):\n";
foreach ($loanCifs as $c) {
    echo "   CIF: " . $c->cifno . " | Cabang: " . $c->cabang . " | Segmen: " . $c->segmen_dashboard . "\n";
}

// 2. Check if any of these CIFs exist in simpanan_multipn
$cifList = $loanCifs->pluck('cifno')->toArray();
echo "\n2. Checking " . count($cifList) . " CIFs in simpanan_multipn...\n";

if (!empty($cifList)) {
    $casaData = DB::table('simpanan_multipn')
        ->where('posisi', $targetDate)
        ->whereIn('cifno', $cifList)
        ->whereIn('jenis_simpanan', ['GIRO', 'TABUNGAN', 'Giro', 'Tabungan'])
        ->select('cifno', 'jenis_simpanan', 'saldo_idr')
        ->limit(20)
        ->get();
    
    echo "   Found " . $casaData->count() . " matching CASA records:\n";
    foreach ($casaData as $c) {
        echo "   CIF: " . $c->cifno . " | Jenis: " . $c->jenis_simpanan . " | Saldo: " . $c->saldo_idr . "\n";
    }
}

// 3. Test the actual query with COLLATE
echo "\n3. Testing actual CASA query with COLLATE:\n";
try {
    $result = DB::table('simpanan_multipn as s')
        ->where('s.posisi', $targetDate)
        ->whereIn('s.jenis_simpanan', ['GIRO', 'TABUNGAN', 'Giro', 'Tabungan'])
        ->whereExists(function ($query) use ($targetDate, $branchName) {
            $query->select(DB::raw(1))
                  ->from('daily_loan_dinamis as d')
                  ->whereRaw('UPPER(d.cifno) COLLATE utf8mb4_unicode_ci = UPPER(s.CIFNO) COLLATE utf8mb4_unicode_ci')
                  ->where('d.periode', $targetDate)
                  ->where('d.cabang', 'LIKE', '%' . $branchName . '%');
        })
        ->selectRaw('SUM(CAST(s.saldo_idr AS DECIMAL(20,2))) as total_casa')
        ->first();
    
    echo "   CASA Result: " . ($result->total_casa ?? 0) . "\n";
} catch (\Exception $e) {
    echo "   ERROR: " . $e->getMessage() . "\n";
}

// 4. Check simpanan_multipn CIFNO format
echo "\n4. Sample CIFNOs from simpanan_multipn:\n";
$simpCifs = DB::table('simpanan_multipn')
    ->where('posisi', $targetDate)
    ->select('CIFNO', 'jenis_simpanan', 'saldo_idr')
    ->limit(10)
    ->get();

foreach ($simpCifs as $s) {
    echo "   CIFNO: '" . $s->CIFNO . "' | Jenis: " . $s->jenis_simpanan . " | Saldo: " . $s->saldo_idr . "\n";
}

// 5. Compare CIF formats
echo "\n5. CIF Format Comparison:\n";
echo "   Loan CIFs (sample): ";
foreach (array_slice($cifList, 0, 5) as $c) {
    echo "'" . $c . "', ";
}
echo "\n";
echo "   Simpanan CIFNOs (sample): ";
if ($simpCifs->count() > 0) {
    foreach ($simpCifs->slice(0, 5) as $s) {
        echo "'" . $s->CIFNO . "', ";
    }
} else {
    echo "NO DATA FOUND";
}
echo "\n";

// 6. Check available dates in simpanan_multipn
echo "\n6. Available dates in simpanan_multipn:\n";
$dates = DB::table('simpanan_multipn')
    ->select('posisi', DB::raw('COUNT(*) as count'))
    ->groupBy('posisi')
    ->orderBy('posisi', 'desc')
    ->limit(10)
    ->get();

foreach ($dates as $d) {
    echo "   Date: " . $d->posisi . " | Records: " . $d->count . "\n";
}

// 7. Check total records in simpanan_multipn
$totalSimpanan = DB::table('simpanan_multipn')->count();
echo "\n7. Total records in simpanan_multipn: " . $totalSimpanan . "\n";

// 8. Check if any CIF matches at all (without date filter)
echo "\n8. Checking CIF matches without date filter:\n";
if (!empty($cifList)) {
    $matches = DB::table('simpanan_multipn')
        ->whereIn('CIFNO', $cifList)
        ->select('CIFNO', 'posisi', 'jenis_simpanan')
        ->limit(10)
        ->get();
    
    echo "   Found " . $matches->count() . " CIF matches (any date):\n";
    foreach ($matches as $m) {
        echo "   CIFNO: " . $m->CIFNO . " | Date: " . $m->posisi . " | Jenis: " . $m->jenis_simpanan . "\n";
    }
}
