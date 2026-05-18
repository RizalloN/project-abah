<?php
$dates = ['2026-05-07', '2026-05-08', '2026-05-09', '2026-05-10'];
$res = DB::table('l1133')
    ->whereIn('periode', $dates)
    ->select('periode', DB::raw('SUM(baki_debet) as baki'))
    ->groupBy('periode')
    ->get();
print_r($res);
