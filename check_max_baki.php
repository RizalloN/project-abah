<?php
$data = DB::table('ssa_pinjaman')
    ->where('month_day_year_of_periode', 'like', '2026-05-%')
    ->orderByDesc('baki_debet')
    ->take(10)
    ->get(['month_day_year_of_periode', 'nama_uker', 'baki_debet', 'kolektabilitas_one_obligor']);
print_r($data);
