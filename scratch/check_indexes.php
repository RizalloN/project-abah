<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$indexes = DB::select('SHOW INDEX FROM daily_loan_dinamis');
foreach ($indexes as $idx) {
    echo "{$idx->Key_name}: {$idx->Column_name} ({$idx->Seq_in_index})\n";
}
