<?php
$data = DB::table('ssa_pinjaman')
    ->where('month_day_year_of_periode', 'like', '2026-05-%')
    ->where('nama_uker', 'like', '%Sudirman Madiun%')
    ->select('month_day_year_of_periode', 'nama_uker', 'baki_debet', 'kolektabilitas_one_obligor')
    ->take(10)
    ->get();
print_r($data);

$data2 = DB::table('ssa_pinjaman')
    ->where('month_day_year_of_periode', 'like', '2026-05-%')
    ->where('nama_uker', 'like', '%Pasar Bajang%')
    ->select('month_day_year_of_periode', 'nama_uker', 'baki_debet', 'kolektabilitas_one_obligor')
    ->take(10)
    ->get();
print_r($data2);
