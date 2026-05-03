<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$period = '2026-04-30';

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║  DUPLICATE INVESTIGATION - DAILY LOAN DINAMIS                 ║\n";
echo "║  Period: {$period}                                           ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

// Method 1: Check uniqueid_namareport
echo "[1] Checking uniqueid_namareport duplicates...\n";
$dupUniqueid = DB::table('daily_loan_dinamis')
    ->where('periode', $period)
    ->selectRaw('uniqueid_namareport, COUNT(*) as cnt')
    ->groupBy('uniqueid_namareport')
    ->having('cnt', '>', 1)
    ->orderBy('cnt', 'DESC')
    ->get();

echo "    Found: " . $dupUniqueid->count() . " duplicate uniqueid_namareport\n";
if ($dupUniqueid->count() > 0) {
    echo "\n    Details:\n";
    foreach ($dupUniqueid as $dup) {
        echo "      - {$dup->uniqueid_namareport}: {$dup->cnt} occurrences\n";
    }
}
echo "\n";

// Method 2: Check nomor_rekening (account number) duplicates
echo "[2] Checking nomor_rekening duplicates...\n";
$dupRekening = DB::table('daily_loan_dinamis')
    ->where('periode', $period)
    ->selectRaw('nomor_rekening1, COUNT(*) as cnt, GROUP_CONCAT(DISTINCT uniqueid_namareport) as ids')
    ->groupBy('nomor_rekening1')
    ->having('cnt', '>', 1)
    ->orderBy('cnt', 'DESC')
    ->limit(20)
    ->get();

echo "    Found: " . $dupRekening->count() . " duplicate nomor_rekening\n";
if ($dupRekening->count() > 0) {
    echo "\n    Top duplicates (by nomor_rekening):\n";
    foreach ($dupRekening as $dup) {
        echo "      - Account: {$dup->nomor_rekening1}: {$dup->cnt} occurrences\n";
        echo "        IDs: " . substr($dup->ids, 0, 100) . "...\n";
    }
}
echo "\n";

// Method 3: Check cifno (customer ID) duplicates
echo "[3] Checking cifno (customer ID) duplicates...\n";
$dupCif = DB::table('daily_loan_dinamis')
    ->where('periode', $period)
    ->selectRaw('cifno, COUNT(*) as cnt')
    ->groupBy('cifno')
    ->having('cnt', '>', 1)
    ->orderBy('cnt', 'DESC')
    ->limit(20)
    ->get();

echo "    Found: " . $dupCif->count() . " duplicate cifno\n";
if ($dupCif->count() > 0) {
    echo "\n    Top 10 CIF duplicates:\n";
    $i = 1;
    foreach ($dupCif as $dup) {
        echo "      {$i}. CIF: {$dup->cifno} - {$dup->cnt} records\n";
        $i++;
    }
}
echo "\n";

// Method 4: Check row count per batch/import (by created_at)
echo "[4] Checking import batches (by created_at timestamp)...\n";
$batches = DB::table('daily_loan_dinamis')
    ->where('periode', $period)
    ->selectRaw('DATE_FORMAT(created_at, "%Y-%m-%d %H:%i") as batch_time, COUNT(*) as cnt')
    ->groupBy('batch_time')
    ->orderBy('batch_time')
    ->get();

echo "    Found: " . $batches->count() . " different import batches\n";
echo "\n    Batch details:\n";
$totalRows = 0;
foreach ($batches as $batch) {
    echo "      - {$batch->batch_time}: {$batch->cnt} rows\n";
    $totalRows += $batch->cnt;
}
echo "    Total: {$totalRows} rows\n\n";

// Method 5: Find exact duplicate rows
echo "[5] Finding exact duplicate rows (all fields identical)...\n";
$exactDups = DB::table('daily_loan_dinamis')
    ->where('periode', $period)
    ->selectRaw('nomor_rekening1, cifno, nama_debitur1, baki_debet1, kol_adk1, COUNT(*) as cnt')
    ->groupBy('nomor_rekening1', 'cifno', 'nama_debitur1', 'baki_debet1', 'kol_adk1')
    ->having('cnt', '>', 1)
    ->orderBy('cnt', 'DESC')
    ->limit(20)
    ->get();

echo "    Found: " . $exactDups->count() . " exact duplicate combinations\n";
if ($exactDups->count() > 0) {
    echo "\n    Examples (loan + customer + amount + status):\n";
    $i = 1;
    foreach ($exactDups as $dup) {
        echo "      {$i}. {$dup->nama_debitur1} (CIF: {$dup->cifno})\n";
        echo "         Account: {$dup->nomor_rekening1} | Balance: {$dup->baki_debet1} | Status: {$dup->kol_adk1}\n";
        echo "         Occurrences: {$dup->cnt}\n\n";
        $i++;
    }
}
