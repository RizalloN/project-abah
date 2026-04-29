<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Support\RkaLookupService;

$service = app(RkaLookupService::class);

$monthColumn = 'apr';
$year = 2026;
$kanca = 'KC Madiun';

// Load rows manually as the service does
$reflection = new ReflectionClass($service);
$methodLoad = $reflection->getMethod('loadRows');
$methodLoad->setAccessible(true);
$rows = $methodLoad->invoke($service, [$monthColumn], $year);

$sum = 0;
echo "Rows matching 'A.2. DPK Korporasi' for $kanca:\n";
foreach ($rows as $row) {
    // Check scope match
    $methodScope = $reflection->getMethod('matchesScope');
    $methodScope->setAccessible(true);
    
    if ($methodScope->invoke($service, $row, $kanca, null)) {
        if ($row['mata_anggaran_key'] === 'A.2. DPK KORPORASI') {
            $val = $row['months'][$monthColumn] ?? 0;
            echo "Uker: {$row['uker_key']} | Kanca: {$row['kanca_key']} | Val: " . number_format($val, 2) . "\n";
            $sum += $val;
        }
    }
}

echo "Total Sum: " . number_format($sum, 2) . "\n";
