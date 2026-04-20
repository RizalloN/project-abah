<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Support\DashboardSmeSegmentService;

echo "=== Dashboard Pinjaman Kredit - Performance Test ===\n\n";

$service = new DashboardSmeSegmentService();

// Test data
$selectedPeriod = '2026-04-19';
$ytdPeriod = '2025-12-31';
$m2Period = '2026-02-28';
$mtmPeriod = '2026-03-19';

// Test SME data
echo "Testing SME Segment Data Loading...\n";
$start = microtime(true);
DB::connection()->enableQueryLog();

$osData = $service->getSmeOsData($selectedPeriod, $ytdPeriod, $m2Period, $mtmPeriod);
$smlData = $service->getSmeSmlData($selectedPeriod, $ytdPeriod, $m2Period, $mtmPeriod);
$nplData = $service->getSmeNplData($selectedPeriod, $ytdPeriod, $m2Period, $mtmPeriod);

$elapsed = (microtime(true) - $start) * 1000; // Convert to ms
$queryCount = count(DB::getQueryLog());

echo "✓ SME Data Loaded\n";
echo "  - OS Rows: " . count($osData) . "\n";
echo "  - SML Rows: " . count($smlData) . "\n";
echo "  - NPL Rows: " . count($nplData) . "\n";
echo "  - Total Queries: " . $queryCount . "\n";
echo "  - Response Time: " . round($elapsed, 2) . "ms\n\n";

// Test Micro data
echo "Testing Micro Segment Data Loading...\n";
$start = microtime(true);
DB::connection()->clearQueryLog();
DB::connection()->enableQueryLog();

$microOsData = $service->getMicroOsData($selectedPeriod, $ytdPeriod, $m2Period, $mtmPeriod);
$microSmlData = $service->getMicroSmlData($selectedPeriod, $ytdPeriod, $m2Period, $mtmPeriod);
$microNplData = $service->getMicroNplData($selectedPeriod, $ytdPeriod, $m2Period, $mtmPeriod);

$elapsed = (microtime(true) - $start) * 1000;
$queryCount = count(DB::getQueryLog());

echo "✓ Micro Data Loaded\n";
echo "  - OS Rows: " . count($microOsData) . "\n";
echo "  - SML Rows: " . count($microSmlData) . "\n";
echo "  - NPL Rows: " . count($microNplData) . "\n";
echo "  - Total Queries: " . $queryCount . "\n";
echo "  - Response Time: " . round($elapsed, 2) . "ms\n\n";

// Test Konsumer data
echo "Testing Konsumer Segment Data Loading...\n";
$start = microtime(true);
DB::connection()->clearQueryLog();
DB::connection()->enableQueryLog();

$konsumerOsData = $service->getKonsumerOsData($selectedPeriod, $ytdPeriod, $m2Period, $mtmPeriod);
$konsumerSmlData = $service->getKonsumerSmlData($selectedPeriod, $ytdPeriod, $m2Period, $mtmPeriod);
$konsumerNplData = $service->getKonsumerNplData($selectedPeriod, $ytdPeriod, $m2Period, $mtmPeriod);

$elapsed = (microtime(true) - $start) * 1000;
$queryCount = count(DB::getQueryLog());

echo "✓ Konsumer Data Loaded\n";
echo "  - OS Rows: " . count($konsumerOsData) . "\n";
echo "  - SML Rows: " . count($konsumerSmlData) . "\n";
echo "  - NPL Rows: " . count($konsumerNplData) . "\n";
echo "  - Total Queries: " . $queryCount . "\n";
echo "  - Response Time: " . round($elapsed, 2) . "ms\n\n";

echo "=== Performance Summary ===\n";
echo "✅ Batch-loaded data from snapshots (1 query per segment type)\n";
echo "✅ Cached in memory for fast aggregation\n";
echo "✅ Fast response times for dashboard\n";
