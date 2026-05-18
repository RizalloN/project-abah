<?php
$data = DB::table('ssa_pinjaman')
    ->where('month_day_year_of_periode', '2026-05-10')
    ->select('nama_cabang', 'nama_uker', 'baki_debet')
    ->orderByDesc('baki_debet')
    ->take(20)
    ->get();
print_r($data);
