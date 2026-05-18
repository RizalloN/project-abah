<?php
$data = DB::table('ssa_simpanan')
    ->where('month_day_year_of_posisi', 'like', '2026-05-%')
    ->where('raw_unit_kerja', 'like', '%Sudirman Madiun%')
    ->select('month_day_year_of_posisi', 'raw_unit_kerja')
    ->take(5)
    ->get();
print_r($data);
