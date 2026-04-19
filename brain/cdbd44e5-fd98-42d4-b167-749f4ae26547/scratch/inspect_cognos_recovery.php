<?php
require __DIR__ . '/../../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    $rkaFirst = DB::table('rka')->first();
    echo "RKA Columns: " . implode(', ', array_keys((array)$rkaFirst)) . "\n";
    
    $area6Raw = DB::table('cognos_recovery')
        ->select('cabang', 'unit_kerja')
        ->distinct()
        ->whereIn('cabang', ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'])
        ->limit(5)
        ->get();
    echo "Area 6 Sample from cognos_recovery:\n" . json_encode($area6Raw, JSON_PRETTY_PRINT) . "\n";

    $segmen2 = DB::table('cognos_recovery')->distinct()->pluck('segmen_2');
    echo "Segmen 2 values: " . json_encode($segmen2) . "\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
