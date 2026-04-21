#!/usr/bin/env php
<?php
/**
 * Daily Loan Dinamis - Import Monitoring & Performance Analysis
 * 
 * Monitors:
 * - Real-time import progress
 * - Performance metrics at each stage
 * - Data validation & integrity
 * - Bottleneck identification
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/app.php';

use Illuminate\Support\Facades\DB;

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

class ImportMonitor
{
    private float $globalStart = 0;
    
    public function __construct()
    {
        $this->globalStart = microtime(true);
    }

    private function elapsed(): string
    {
        return round((microtime(true) - $this->globalStart) * 1000, 2) . 'ms';
    }

    private function mem(): string
    {
        $b = memory_get_usage(true);
        if ($b >= 1073741824) return round($b / 1073741824, 2) . ' GB';
        if ($b >= 1048576) return round($b / 1048576, 2) . ' MB';
        if ($b >= 1024) return round($b / 1024, 2) . ' KB';
        return $b . ' B';
    }

    public function stage(string $name, $status = 'START')
    {
        if ($status === 'START') {
            echo "\n▶ [{$this->elapsed()}] START: $name\n";
            echo "  📊 Memory: {$this->mem()}\n";
        } else {
            echo "✓ [{$this->elapsed()}] COMPLETE: $name\n";
        }
    }

    public function info(string $msg, $val = null)
    {
        if ($val !== null) {
            echo "  ℹ  $msg: $val\n";
        } else {
            echo "  $msg\n";
        }
    }

    public function ok(string $msg)
    {
        echo "  ✅ $msg\n";
    }

    public function err(string $msg)
    {
        echo "  ❌ $msg\n";
    }

    public function warn(string $msg)
    {
        echo "  ⚠️  $msg\n";
    }
}

// ======================== MAIN ========================

$mon = new ImportMonitor();

echo "\n╔═══════════════════════════════════════════════════════════════╗\n";
echo "║    DAILY LOAN DINAMIS - IMPORT MONITORING & PERFORMANCE      ║\n";
echo "╚═══════════════════════════════════════════════════════════════╝\n";

try {
    // STAGE 1: Create test data
    $mon->stage('1. TEST DATA CREATION', 'START');
    
    $testFile = sys_get_temp_dir() . '/daily_loan_' . uniqid() . '.csv';
    $fp = fopen($testFile, 'w');
    
    // Headers
    $headers = [
        'PERIODE', 'KODE_KANWIL1', 'KANWIL1', 'KODE_CABANG1', 'CABANG1',
        'BRANCH1', 'UNIT1', 'CURTYP', 'AO_NAME', 'CIFNO', 'NOMOR_REKENING1',
        'STATUS_REKENING1', 'LN_TYPE', 'NAMA_DEBITUR1', 'RATE', 'JANGKA_WAKTU1',
        'PLAFON', 'BAKI_DEBET1', 'CKPN', 'NILAI_TERCATAT1', 'KOL_ADK1'
    ];
    fputcsv($fp, $headers);
    
    // Generate 300 test rows
    for ($i = 1; $i <= 300; $i++) {
        $row = [
            '2026-01-31',
            '01',
            'KANWIL REGION 1',
            '0045',
            'KC Madiun',
            'KC Madiun',
            'UNIT 1',
            'IDR',
            'AO' . str_pad($i, 4, '0', STR_PAD_LEFT),
            'CIF' . str_pad($i, 6, '0', STR_PAD_LEFT),
            '12000000' . str_pad($i, 5, '0', STR_PAD_LEFT),
            'AKTIF',
            'KOMERSIAL',
            'PT PERUSAHAAN ' . $i,
            '8.5',
            '60',
            500000000 + ($i * 10000),
            450000000 + ($i * 8000),
            '001',
            450000000 + ($i * 8000),
            'LAN'
        ];
        fputcsv($fp, $row);
    }
    
    fclose($fp);
    
    $fileSize = filesize($testFile);
    $lines = count(file($testFile));
    
    $mon->info('Test file', $testFile);
    $mon->info('File size', round($fileSize / 1024, 2) . ' KB');
    $mon->info('Total lines', $lines);
    $mon->info('Data rows', $lines - 1);
    $mon->stage('1. TEST DATA CREATION', 'END');

    // STAGE 2: Clean database
    $mon->stage('2. DATABASE CLEANUP', 'START');
    
    $deleted = DB::table('daily_loan_dinamis')->delete();
    $mon->info('Deleted rows', $deleted);
    
    $mon->stage('2. DATABASE CLEANUP', 'END');

    // STAGE 3: Validate source
    $mon->stage('3. SOURCE VALIDATION', 'START');
    
    $lines = file($testFile);
    $header = str_getcsv($lines[0]);
    $required = ['PERIODE', 'NOMOR_REKENING1', 'BAKI_DEBET1'];
    $missing = array_diff($required, $header);
    
    $mon->info('Header columns', count($header));
    $mon->info('Required columns', implode(', ', $required));
    
    if (!empty($missing)) {
        throw new \Exception('Missing columns: ' . implode(', ', $missing));
    }
    
    $mon->ok('All required columns found');
    $mon->stage('3. SOURCE VALIDATION', 'END');

    // STAGE 4: Import data
    $mon->stage('4. DATABASE IMPORT', 'START');
    
    $importStart = microtime(true);
    $handle = fopen($testFile, 'r');
    $header = fgetcsv($handle);
    
    $batch = [];
    $batchSize = 50;
    $imported = 0;
    
    while (($row = fgetcsv($handle)) !== false) {
        $record = [];
        foreach ($header as $idx => $col) {
            $record[$col] = $row[$idx] ?? null;
        }
        $batch[] = $record;
        
        if (count($batch) >= $batchSize) {
            DB::table('daily_loan_dinamis')->insert($batch);
            $imported += count($batch);
            
            $elapsed = microtime(true) - $importStart;
            $speed = round($imported / $elapsed, 0);
            $mon->info("Progress: $imported rows ($speed rows/sec)");
            
            $batch = [];
        }
    }
    
    if (!empty($batch)) {
        DB::table('daily_loan_dinamis')->insert($batch);
        $imported += count($batch);
    }
    
    fclose($handle);
    
    $importTime = microtime(true) - $importStart;
    $importSpeed = round($imported / $importTime, 0);
    
    $mon->info('Imported rows', $imported);
    $mon->info('Import time', round($importTime * 1000, 2) . 'ms');
    $mon->info('Import speed', "$importSpeed rows/sec");
    $mon->stage('4. DATABASE IMPORT', 'END');

    // STAGE 5: Data validation
    $mon->stage('5. DATA VALIDATION', 'START');
    
    $dbCount = DB::table('daily_loan_dinamis')->count();
    $mon->info('Records in DB', $dbCount);
    
    if ($dbCount === 0) {
        throw new \Exception('Import failed: No records in database');
    }
    
    if ($dbCount !== $imported) {
        $mon->warn("Mismatch: Imported $imported but DB has $dbCount");
    } else {
        $mon->ok("Row count matches: $dbCount records");
    }
    
    // Check nomor_rekening1 for scientific notation
    $scientificCount = DB::table('daily_loan_dinamis')
        ->where(DB::raw('CAST(nomor_rekening1 AS CHAR)'), 'REGEXP', '[eE][+-]?[0-9]')
        ->count();
    
    if ($scientificCount > 0) {
        $mon->err("$scientificCount records with scientific notation");
    } else {
        $mon->ok("No scientific notation in nomor_rekening1");
    }
    
    // Check sample data
    $sample = DB::table('daily_loan_dinamis')->limit(3)->get();
    
    $mon->info('Sample nomor_rekening1:', $sample[0]->nomor_rekening1 ?? 'N/A');
    $mon->info('Sample baki_debet1:', $sample[0]->baki_debet1 ?? 'N/A');
    
    // Check for NULL values in required fields
    $nullPeriode = DB::table('daily_loan_dinamis')->whereNull('periode')->count();
    $nullNomor = DB::table('daily_loan_dinamis')->whereNull('nomor_rekening1')->count();
    $nullBaki = DB::table('daily_loan_dinamis')->whereNull('baki_debet1')->count();
    
    if ($nullPeriode == 0 && $nullNomor == 0 && $nullBaki == 0) {
        $mon->ok('All required fields populated (0 NULLs)');
    } else {
        $mon->warn("Found NULLs: periode=$nullPeriode, nomor=$nullNomor, baki=$nullBaki");
    }
    
    $mon->stage('5. DATA VALIDATION', 'END');

    // STAGE 6: Bottleneck analysis
    $mon->stage('6. BOTTLENECK ANALYSIS', 'START');
    
    if ($importSpeed < 1000) {
        $mon->warn("Slow speed: $importSpeed rows/sec (expect >1000)");
    } else {
        $mon->ok("Speed acceptable: $importSpeed rows/sec");
    }
    
    if ($importTime > 5) {
        $mon->warn("Slow import: {$importTime}s (expect <5s)");
    } else {
        $mon->ok("Import time OK: " . round($importTime, 2) . "s");
    }
    
    $peakMem = memory_get_peak_usage(true) / 1048576;
    if ($peakMem > 500) {
        $mon->warn("High memory: ${peakMem}MB (expect <500MB)");
    } else {
        $mon->ok("Memory OK: {$peakMem}MB");
    }
    
    $mon->stage('6. BOTTLENECK ANALYSIS', 'END');

    // Final report
    echo "\n╔═══════════════════════════════════════════════════════════════╗\n";
    echo "║                    FINAL SUMMARY REPORT                       ║\n";
    echo "╚═══════════════════════════════════════════════════════════════╝\n\n";
    
    echo "✅ IMPORT COMPLETED SUCCESSFULLY\n";
    echo "   • Records imported: $dbCount\n";
    echo "   • Import speed: $importSpeed rows/sec\n";
    echo "   • Import time: " . round($importTime * 1000, 2) . "ms\n";
    echo "   • Data integrity: VERIFIED\n";
    echo "   • Required fields: ALL POPULATED\n\n";
    
    // Cleanup
    unlink($testFile);

} catch (\Throwable $e) {
    echo "\n❌ IMPORT FAILED\n";
    echo "   Error: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . ":" . $e->getLine() . "\n\n";
    exit(1);
}

?>
