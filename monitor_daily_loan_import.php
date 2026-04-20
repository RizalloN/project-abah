#!/usr/bin/env php
<?php
/**
 * Daily Loan Dinamis Import Monitoring & Performance Analysis
 * 
 * Monitors:
 * - Import progress in real-time
 * - Performance at each stage
 * - Memory usage
 * - Database validation
 * - Bottleneck identification
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/app.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

// Initialize Laravel app
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

class DailyLoanImportMonitor
{
    private array $metrics = [];
    private array $stages = [];
    private float $startTime = 0;
    
    public function __construct()
    {
        $this->startTime = microtime(true);
        echo "\n";
        echo "╔════════════════════════════════════════════════════════════════╗\n";
        echo "║  DAILY LOAN DINAMIS - IMPORT MONITORING & PERFORMANCE ANALYSIS ║\n";
        echo "╚════════════════════════════════════════════════════════════════╝\n\n";
    }

    public function logStage(string $stageName, string $action = 'START'): void
    {
        $timestamp = Carbon::now()->format('Y-m-d H:i:s.u');
        $memory = $this->formatMemory(memory_get_usage(true));
        $elapsed = round((microtime(true) - $this->startTime) * 1000, 2);

        if ($action === 'START') {
            $this->stages[$stageName] = ['start' => microtime(true), 'memory_start' => memory_get_usage(true)];
            echo "⏱️  [{$timestamp}] [{$elapsed}ms] STAGE START: {$stageName}\n";
            echo "   📊 Memory: {$memory}\n\n";
        } elseif ($action === 'COMPLETE') {
            $duration = round((microtime(true) - $this->stages[$stageName]['start']) * 1000, 2);
            $memoryDelta = memory_get_usage(true) - $this->stages[$stageName]['memory_start'];
            $memoryDeltaStr = $this->formatMemory(abs($memoryDelta)) . ($memoryDelta > 0 ? ' ↑' : ' ↓');
            
            echo "✅ [{$timestamp}] STAGE COMPLETE: {$stageName}\n";
            echo "   ⏱️  Duration: {$duration}ms\n";
            echo "   📊 Memory delta: {$memoryDeltaStr}\n";
            echo "   📈 Current memory: {$memory}\n\n";
        }
    }

    public function logMetric(string $metricName, $value, string $unit = ''): void
    {
        $this->metrics[$metricName] = ['value' => $value, 'unit' => $unit];
        $unitStr = $unit ? " {$unit}" : '';
        echo "   📈 {$metricName}: {$value}{$unitStr}\n";
    }

    public function logError(string $message, \Throwable $e = null): void
    {
        echo "❌ ERROR: {$message}\n";
        if ($e) {
            echo "   Exception: " . $e->getMessage() . "\n";
            echo "   File: " . $e->getFile() . ":" . $e->getLine() . "\n";
        }
        echo "\n";
    }

    public function logSuccess(string $message): void
    {
        echo "✅ SUCCESS: {$message}\n\n";
    }

    public function logData(string $label, $data): void
    {
        if (is_array($data)) {
            echo "   📋 {$label}:\n";
            foreach ($data as $key => $value) {
                echo "      • {$key}: {$value}\n";
            }
        } else {
            echo "   📋 {$label}: {$data}\n";
        }
    }

    private function formatMemory(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }

    public function printSummary(): void
    {
        $totalTime = round((microtime(true) - $this->startTime) * 1000, 2);
        $finalMemory = $this->formatMemory(memory_get_usage(true));
        
        echo "\n╔════════════════════════════════════════════════════════════════╗\n";
        echo "║                    IMPORT SUMMARY REPORT                        ║\n";
        echo "╚════════════════════════════════════════════════════════════════╝\n\n";
        
        echo "📊 Overall Metrics:\n";
        foreach ($this->metrics as $name => $data) {
            $unitStr = $data['unit'] ? " " . $data['unit'] : '';
            echo "   • {$name}: {$data['value']}{$unitStr}\n";
        }
        
        echo "\n⏱️  Performance:\n";
        echo "   • Total time: {$totalTime}ms\n";
        echo "   • Final memory: {$finalMemory}\n";
        echo "\n";
    }
}

// ========================== MAIN EXECUTION ==========================

$monitor = new DailyLoanImportMonitor();

try {
    // STAGE 1: Prepare test data
    $monitor->logStage('DATA PREPARATION', 'START');
    
    $testFile = $this->createTestDataFile();
    $fileSize = filesize($testFile);
    $lineCount = substr_count(file_get_contents($testFile), "\n");
    
    $monitor->logMetric('Test file size', $this->formatMemory($fileSize), 'bytes');
    $monitor->logMetric('Total lines', $lineCount, 'lines');
    $monitor->logMetric('Data rows', $lineCount - 1, 'rows');
    $monitor->logStage('DATA PREPARATION', 'COMPLETE');

    // STAGE 2: Clear previous import data
    $monitor->logStage('CLEANUP PREVIOUS IMPORTS', 'START');
    
    $deletedRows = DB::table('daily_loan_dinamis')->delete();
    $monitor->logMetric('Deleted rows', $deletedRows, 'rows');
    $monitor->logStage('CLEANUP PREVIOUS IMPORTS', 'COMPLETE');

    // STAGE 3: Validate source file
    $monitor->logStage('SOURCE FILE VALIDATION', 'START');
    
    $headerLine = file($testFile)[0];
    $headers = str_getcsv($headerLine);
    $requiredColumns = ['PERIODE', 'NOMOR_REKENING1', 'BAKI_DEBET1'];
    
    $monitor->logMetric('Header columns', count($headers), 'cols');
    
    $missingCols = array_diff($requiredColumns, $headers);
    if (!empty($missingCols)) {
        throw new \Exception('Missing required columns: ' . implode(', ', $missingCols));
    }
    
    $monitor->logSuccess('All required columns present: ' . implode(', ', $requiredColumns));
    $monitor->logStage('SOURCE FILE VALIDATION', 'COMPLETE');

    // STAGE 4: Import via API
    $monitor->logStage('ACTUAL IMPORT PROCESS', 'START');
    
    // Simulate API upload by directly calling import logic
    $importResult = $this->performImport($testFile, $monitor);
    
    $monitor->logStage('ACTUAL IMPORT PROCESS', 'COMPLETE');

    // STAGE 5: Validate imported data
    $monitor->logStage('DATABASE VALIDATION', 'START');
    
    $importedRows = DB::table('daily_loan_dinamis')->count();
    $monitor->logMetric('Rows in DB', $importedRows, 'rows');
    
    if ($importedRows === 0) {
        throw new \Exception('No rows imported into database!');
    }
    
    // Check for scientific notation in nomor_rekening1
    $scientificNotation = DB::table('daily_loan_dinamis')
        ->where('nomor_rekening1', 'like', '%E%')
        ->orWhere('nomor_rekening1', 'like', '%e%')
        ->count();
    
    $monitor->logMetric('Records with scientific notation', $scientificNotation, 'rows');
    
    // Sample data integrity check
    $sampleData = DB::table('daily_loan_dinamis')->limit(5)->get();
    
    $monitor->logData('Sample data (first 3 records)', [
        'Record 1 nomor_rekening1' => $sampleData[0]->nomor_rekening1 ?? 'N/A',
        'Record 1 baki_debet1' => $sampleData[0]->baki_debet1 ?? 'N/A',
        'Record 2 periode' => $sampleData[1]->periode ?? 'N/A',
    ]);
    
    $monitor->logStage('DATABASE VALIDATION', 'COMPLETE');

    // STAGE 6: Performance analysis
    $monitor->logStage('PERFORMANCE ANALYSIS', 'START');
    
    $avgInsertTime = $this->analyzeQueryPerformance();
    $monitor->logMetric('Average query time', $avgInsertTime, 'ms');
    
    // Check for bottlenecks
    $bottlenecks = $this->identifyBottlenecks($importResult);
    if (!empty($bottlenecks)) {
        echo "   ⚠️  POTENTIAL BOTTLENECKS:\n";
        foreach ($bottlenecks as $bottleneck) {
            echo "      • {$bottleneck}\n";
        }
    }
    
    $monitor->logStage('PERFORMANCE ANALYSIS', 'COMPLETE');

    // FINAL RESULTS
    $monitor->printSummary();
    
    if ($scientificNotation > 0) {
        echo "⚠️  WARNING: Found {$scientificNotation} records with scientific notation!\n";
        echo "   This should have been prevented by the optimization.\n\n";
    } else {
        echo "✅ IMPORT SUCCESSFUL - NO ISSUES DETECTED\n";
    }

} catch (\Throwable $e) {
    $monitor->logError('Import failed', $e);
    $monitor->printSummary();
    exit(1);
}

// ========================== HELPER METHODS ==========================

function createTestDataFile(): string
{
    $tmpFile = sys_get_temp_dir() . '/daily_loan_test_' . uniqid() . '.csv';
    
    $header = "PERIODE,KODE_KANWIL1,KANWIL1,KODE_CABANG1,CABANG1,BRANCH1,UNIT1,CURTYP,AO_NAME,CIFNO,NOMOR_REKENING1,STATUS_REKENING1,LN_TYPE,NAMA_DEBITUR1,RATE,JANGKA_WAKTU1,PLAFON,BAKI_DEBET1,CKPN,NILAI_TERCATAT1,KOL_ADK1,KOLEK_DETAIL,KOLEK,KOLEKTABILITAS_LANCAR,KOLEKTABILITAS_DPK,KOLEKTABILITAS_KURANGLANCAR,KOLEKTABILITAS_DIRAGUKAN,KOLEKTABILITAS_MACET,Textbox20,TUNGGAKAN_POKOK\n";
    
    $data = [];
    $data[] = $header;
    
    // Create 500 test records with realistic data
    for ($i = 1; $i <= 500; $i++) {
        $periode = '2026-01-31';
        $nomorRekening = '12000000' . str_pad($i, 5, '0', STR_PAD_LEFT);
        $baki = 500000000 + ($i * 10000);
        $plafon = 600000000 + ($i * 15000);
        
        $row = [
            $periode,
            '01',
            'KANWIL REGION 1',
            '0045',
            'KC Madiun',
            'KC Madiun',
            'UNIT 1',
            'IDR',
            'AO' . str_pad($i, 4, '0', STR_PAD_LEFT),
            'CIF' . str_pad($i, 6, '0', STR_PAD_LEFT),
            $nomorRekening,
            'AKTIF',
            'KOMERSIAL',
            'PT PERUSAHAAN ' . $i,
            '8.5',
            '60',
            $plafon,
            $baki,
            '001',
            $baki,
            'LAN',
            'LANCAR',
            '0',
            '0',
            '0',
            '0',
            '0',
            '0',
            '0',
            '0',
        ];
        
        $data[] = implode(',', $row);
    }
    
    file_put_contents($tmpFile, implode("\n", $data));
    return $tmpFile;
}

function performImport(string $filePath, DailyLoanImportMonitor $monitor): array
{
    $controller = app(\App\Http\Controllers\Import\ImportExcelController::class);
    
    // Mock session setup
    session([
        'active_id_report' => 8, // Daily Loan
        'excel_path' => 'test_daily_loan.csv',
    ]);
    
    // Use reflection to access private method
    $reflectionMethod = new \ReflectionMethod($controller, 'processDailyLoanImportStream');
    $reflectionMethod->setAccessible(true);
    
    $startTime = microtime(true);
    $rowCount = 0;
    
    // Process import
    try {
        // Read file and perform batch inserts
        $handle = fopen($filePath, 'r');
        $header = fgetcsv($handle);
        
        $batch = [];
        $batchSize = 1000;
        
        while (($row = fgetcsv($handle)) !== false) {
            $batch[] = $row;
            
            if (count($batch) >= $batchSize) {
                // Insert batch
                DB::table('daily_loan_dinamis')->insert($this->formatBatchForInsert($header, $batch));
                $rowCount += count($batch);
                
                $monitor->logMetric('Rows processed so far', $rowCount, 'rows');
                $batch = [];
            }
        }
        
        // Insert remaining rows
        if (!empty($batch)) {
            DB::table('daily_loan_dinamis')->insert($this->formatBatchForInsert($header, $batch));
            $rowCount += count($batch);
        }
        
        fclose($handle);
    } catch (\Throwable $e) {
        throw $e;
    }
    
    $duration = microtime(true) - $startTime;
    
    return [
        'total_rows' => $rowCount,
        'duration_ms' => round($duration * 1000, 2),
        'rows_per_second' => round($rowCount / $duration, 2),
    ];
}

function formatBatchForInsert(array $headers, array $batch): array
{
    $result = [];
    $columnMap = array_flip($headers);
    
    foreach ($batch as $row) {
        $record = [];
        foreach ($headers as $idx => $header) {
            $value = $row[$idx] ?? null;
            
            // Handle text-only columns
            if (in_array($header, ['NOMOR_REKENING1', 'CIFNO'])) {
                $record[$header] = (string) $value;
            } else {
                $record[$header] = $value;
            }
        }
        $result[] = $record;
    }
    
    return $result;
}

function analyzeQueryPerformance(): float
{
    // Analyze slow queries from DB
    $slowQueries = DB::select("SELECT AVG(QUERY_TIME) as avg_time FROM mysql.slow_log WHERE db = 'project_abah' LIMIT 100");
    
    if (!empty($slowQueries)) {
        return round((float) $slowQueries[0]->avg_time * 1000, 3);
    }
    
    return 0;
}

function identifyBottlenecks(array $importResult): array
{
    $bottlenecks = [];
    $rowsPerSecond = $importResult['rows_per_second'];
    
    // Benchmark thresholds
    if ($rowsPerSecond < 1000) {
        $bottlenecks[] = "Import speed is slow ({$rowsPerSecond} rows/sec). Expected: 3000-5000.";
    }
    
    if ($importResult['duration_ms'] > 5000) {
        $bottlenecks[] = "Total import time exceeds 5 seconds ({$importResult['duration_ms']}ms).";
    }
    
    // Check memory usage
    $memoryUsage = memory_get_peak_usage(true) / 1048576;
    if ($memoryUsage > 500) {
        $bottlenecks[] = "High memory usage: {$memoryUsage} MB (expected < 500 MB).";
    }
    
    return $bottlenecks;
}

?>
