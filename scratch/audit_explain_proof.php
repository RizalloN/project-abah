<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$periode = DB::table('daily_loan_dinamis')->max('periode');

if (!$periode) {
    echo "Data tidak ditemukan.\n";
    exit;
}

echo "AUDIT VERIFICATION: EXPLAIN Query fetchSegmentRmAggregates\n";
echo str_repeat("=", 80) . "\n";

// Mengambil rule dari konstanta (simulasi)
$segment = 'MICRO';
$rules = [
    ['source_segment' => 'MICRO', 'products' => ['KUR-MIKRO', 'BRIGUNA-MIKRO', 'KUPEDES', 'CASHCOLLATERAL', 'KPR']],
    ['source_segment' => 'SMALL', 'products' => ['KUR-SMALL', 'KUR-MIKRO']],
];

$normalizedSegmenSql = "UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(COALESCE(segmen_dashboard, '')), ' ', ''), '-', ''), '_', ''), '/', ''), '.', ''))";
$normalizedProductSql = "UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(COALESCE(produk_dashboard, '')), ' ', ''), '-', ''), '_', ''), '/', ''), '.', ''))";

$query = DB::table('daily_loan_dinamis')
    ->where('periode', $periode)
    ->where(function ($scope) use ($rules, $normalizedSegmenSql, $normalizedProductSql) {
        foreach ($rules as $rule) {
            $scope->orWhere(function ($ruleScope) use ($rule, $normalizedSegmenSql, $normalizedProductSql) {
                // Simulasi pembersihan token di PHP
                $segToken = preg_replace('/[^A-Z0-9]/', '', strtoupper($rule['source_segment']));
                $ruleScope->whereRaw("{$normalizedSegmenSql} = ?", [$segToken]);
            });
        }
    })
    ->selectRaw("COUNT(*)")
    ->toSql();

$bindings = [
    $periode,
    'MICRO',
    'SMALL'
];

echo "Query SQL: $query\n";
echo "Bindings: " . implode(', ', $bindings) . "\n\n";

$explain = DB::select("EXPLAIN " . $query, $bindings);

foreach ($explain as $row) {
    echo "ID: {$row->id} | Type: {$row->type} | Key: " . ($row->key ?? 'NULL') . " | Rows: {$row->rows} | Extra: {$row->Extra}\n";
}

echo str_repeat("=", 80) . "\n";

if (isset($explain[0]->Extra) && str_contains($explain[0]->Extra, 'Using temporary')) {
    echo "🚨 BUKTI 1: 'Using temporary' terdeteksi. MySQL dipaksa membuat tabel sementara di memory/disk karena grouping/normalisasi.\n";
}

if (isset($explain[0]->type) && $explain[0]->type == 'ALL') {
    echo "🚨 BUKTI 2: 'Type: ALL' terdeteksi. Full Table Scan terjadi karena whereRaw mematikan Indeks.\n";
} elseif (isset($explain[0]->key) && $explain[0]->key == 'idx_daily_loan_report_filter_covering') {
    echo "✅ INFO: Indeks periode terpakai, tapi perhatikan kolom 'Extra'.\n";
}

if (isset($explain[0]->Extra) && str_contains($explain[0]->Extra, 'Using where')) {
     echo "🚨 BUKTI 3: 'Using where' pada kolom ter-indeks menunjukkan MySQL melakukan filtrasi SETELAH membaca baris, bukan mencari langsung di indeks (Sargon Issue).\n";
}
