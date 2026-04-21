<?php
require dirname(__DIR__) . '/vendor/autoload.php';
$app = require dirname(__DIR__) . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo json_encode([
  'rows' => Illuminate\Support\Facades\DB::table('daily_loan_dinamis')->where('periode', '2026-04-19')->count(),
  'distinct_cif' => Illuminate\Support\Facades\DB::table('daily_loan_dinamis')->where('periode', '2026-04-19')->distinct('cifno')->count('cifno'),
  'distinct_rekening' => Illuminate\Support\Facades\DB::table('daily_loan_dinamis')->where('periode', '2026-04-19')->distinct('nomor_rekening1')->count('nomor_rekening1')
]);
