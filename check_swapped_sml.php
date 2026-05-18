<?php
$data = DB::table('ssa_pinjaman')
    ->whereIn('month_day_year_of_periode', ['2026-01-05', '2026-02-05', '2026-03-05', '2026-04-05', '2026-01-06', '2026-02-06', '2026-03-06', '2026-04-06'])
    ->select('month_day_year_of_periode', DB::raw("SUM(CASE WHEN kolektabilitas_one_obligor = 2 THEN baki_debet ELSE 0 END) as sml"))
    ->groupBy('month_day_year_of_periode')
    ->get();
print_r($data);
