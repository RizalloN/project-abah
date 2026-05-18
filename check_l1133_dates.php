<?php
$dates = DB::table('l1133')->select('periode', DB::raw('COUNT(*) as count'))->groupBy('periode')->get();
print_r($dates);
