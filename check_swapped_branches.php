<?php
$dates = ['2026-01-05', '2026-02-05', '2026-03-05', '2026-04-05', '2026-01-06', '2026-02-06', '2026-03-06', '2026-04-06'];

foreach ($dates as $d) {
    $row = DB::table('ssa_pinjaman')
        ->where('month_day_year_of_periode', $d)
        ->select('month_day_year_of_periode', 'nama_cabang', DB::raw('SUM(baki_debet) as baki'))
        ->groupBy('month_day_year_of_periode', 'nama_cabang')
        ->get();
    echo "Date: $d\n";
    foreach ($row as $r) {
        echo "  {$r->nama_cabang}: {$r->baki}\n";
    }
}
