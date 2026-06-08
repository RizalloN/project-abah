<?php

define('LARAVEL_START', microtime(true));
require 'd:/XAMPP/htdocs/project-ABAH/vendor/autoload.php';
$app = require_once 'd:/XAMPP/htdocs/project-ABAH/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

$selectedDate = Carbon::parse('2026-05-31');
$currStart   = $selectedDate->copy()->startOfMonth()->toDateString();
$currEnd     = $selectedDate->copy()->endOfMonth()->toDateString();
$prevStart   = $selectedDate->copy()->subMonthNoOverflow()->startOfMonth()->toDateString();
$prevEnd     = Carbon::parse($prevStart)->endOfMonth()->toDateString();
$yoyStart    = $selectedDate->copy()->subYearNoOverflow()->startOfMonth()->toDateString();
$yoyEnd      = Carbon::parse($yoyStart)->endOfMonth()->toDateString();

$effectiveSnapshot = DB::table('performance_pis_per_produk')
    ->whereDate('posisi', '<=', $selectedDate->toDateString())
    ->max('posisi');

echo "Effective Snapshot: $effectiveSnapshot\n";
echo "curr: $currStart to $currEnd\n";
echo "prev: $prevStart to $prevEnd\n";
echo "yoy: $yoyStart to $yoyEnd\n\n";

$rows = DB::table('performance_pis_per_produk')
    ->selectRaw("UPPER(TRIM(kanca)) as branch")
    ->selectRaw('COUNT(CASE WHEN tanggal_pembuatan_rekening BETWEEN ? AND ? THEN 1 END) as rekening_curr', [$currStart, $currEnd])
    ->selectRaw('COUNT(CASE WHEN tanggal_pembuatan_rekening BETWEEN ? AND ? THEN 1 END) as rekening_prev', [$prevStart, $prevEnd])
    ->selectRaw('COUNT(CASE WHEN tanggal_pembuatan_rekening BETWEEN ? AND ? THEN 1 END) as rekening_yoy_prev', [$yoyStart, $yoyEnd])
    ->selectRaw('SUM(CASE WHEN tanggal_pembuatan_rekening BETWEEN ? AND ? THEN saldo_britama_kerjasama ELSE 0 END) as saldo_curr', [$currStart, $currEnd])
    ->selectRaw('SUM(CASE WHEN tanggal_pembuatan_rekening BETWEEN ? AND ? THEN saldo_britama_kerjasama ELSE 0 END) as saldo_prev', [$prevStart, $prevEnd])
    ->selectRaw('SUM(CASE WHEN tanggal_pembuatan_rekening BETWEEN ? AND ? THEN saldo_britama_kerjasama ELSE 0 END) as saldo_yoy_prev', [$yoyStart, $yoyEnd])
    ->whereDate('posisi', $effectiveSnapshot)
    ->whereIn(DB::raw('UPPER(TRIM(kanca))'), ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'])
    ->groupBy('branch')
    ->get();

foreach ($rows as $row) {
    echo "Branch: {$row->branch}\n";
    echo "  Rekening Curr: {$row->rekening_curr}\n";
    echo "  Rekening Prev: {$row->rekening_prev}\n";
    echo "  Rekening YoY Prev: {$row->rekening_yoy_prev}\n";
    echo "  Saldo Curr: {$row->saldo_curr}\n";
    echo "  Saldo Prev: {$row->saldo_prev}\n";
    echo "  Saldo YoY Prev: {$row->saldo_yoy_prev}\n";
    echo "\n";
}
