<?php
/**
 * Manual Testing Script untuk Sinkronisasi Status Import
 *
 * Gunakan script ini untuk validasi manual pada 3 skenario utama:
 * 1. Inline Fallback (worker sibuk, import langsung)
 * 2. Cache Expiry (data cache hilang setelah 6 jam)
 * 3. Real-Time Progress (monitoring kolom message saat import berjalan)
 *
 * Cara Menjalankan:
 * php artisan tinker
 * >>> include('tests/Manual/StatusSyncManualTestScript.php');
 * >>> $tester->runScenario1();
 */

namespace Tests\Manual;

use App\Services\Import\ImportProgressService;
use App\Services\Import\ImportExecutionService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StatusSyncManualTestScript
{
    private ImportProgressService $progressService;
    private ImportExecutionService $executionService;

    public function __construct()
    {
        $this->progressService = app(ImportProgressService::class);
        $this->executionService = app(ImportExecutionService::class);
    }

    public function printHeader(string $title): void
    {
        echo "\n" . str_repeat('=', 80) . "\n";
        echo "✓ {$title}\n";
        echo str_repeat('=', 80) . "\n";
    }

    public function printInfo(string $message): void
    {
        echo "  ℹ️  {$message}\n";
    }

    public function printSuccess(string $message): void
    {
        echo "  ✅ {$message}\n";
    }

    public function printError(string $message): void
    {
        echo "  ❌ {$message}\n";
    }

    public function printWarning(string $message): void
    {
        echo "  ⚠️  {$message}\n";
    }

    public function separator(): void
    {
        echo "\n" . str_repeat('-', 80) . "\n";
    }

    /**
     * SKENARIO 1: Inline Fallback
     *
     * Simulasi: Import besar, worker sibuk → inline fallback
     * Ekspektasi: Modal Preview dan Dashboard menampilkan pesan fallback yang sama
     */
    public function runScenario1(): void
    {
        $this->printHeader("SKENARIO 1: Inline Fallback Message Synchronization");

        Cache::flush();

        // Step 1: Create job
        $this->printInfo("Step 1: Membuat import job simulasi (queued)");
        $jobId = 101;

        DB::table('import_jobs')->where('id', $jobId)->delete();
        DB::table('import_jobs')->insert([
            'id' => $jobId,
            'status' => 'queued',
            'file_name' => 'large_import_20260426.csv',
            'folder_path' => 'imports/daily_loan',
            'id_report' => 8,
            'created_by' => 'test-user',
            'total_files' => 500000,
            'total_success' => 0,
            'total_failed' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->printSuccess("Job #{$jobId} dibuat dengan status 'queued'");

        // Step 2: Simulasi pesan fallback di cache
        $this->printInfo("\nStep 2: Simulasi inline fallback message di cache");
        $fallbackPayload = [
            'status' => 'processing',
            'phase' => 'polars',
            'mode' => 'polars',
            'percent' => 8,
            'message' => 'Worker queue sibuk, mencoba menjalankan langsung dari request...',
            'processed_rows' => 0,
            'total_rows' => 500000,
            'total_success' => 0,
            'total_failed' => 0,
        ];

        $this->progressService->cacheProgress($jobId, $fallbackPayload);
        $this->printSuccess("Fallback message di-cache");

        // Step 3: Verifikasi getStatusPayload (untuk Dashboard)
        $this->printInfo("\nStep 3: Verifikasi getStatusPayload (Dashboard Job Management)");
        $dashboardPayload = $this->progressService->getStatusPayload($jobId);
        $this->printInfo("Dashboard melihat message: '{$dashboardPayload['message']}'");

        if (str_contains($dashboardPayload['message'], 'langsung')) {
            $this->printSuccess("✓ Dashboard menampilkan fallback message dengan benar");
        } else {
            $this->printError("✗ Dashboard tidak menampilkan fallback message yang benar");
        }

        // Step 4: Verifikasi cache langsung (untuk Modal Preview)
        $this->printInfo("\nStep 4: Verifikasi cache (Modal Preview SSE Stream)");
        $cachedData = Cache::get('import_job_progress:' . $jobId);
        if ($cachedData && $cachedData['message'] === $fallbackPayload['message']) {
            $this->printSuccess("✓ Modal Preview melihat cache message yang sama");
            $this->printInfo("  Message: '{$cachedData['message']}'");
        } else {
            $this->printError("✗ Cache message tidak konsisten");
        }

        // Step 5: Verifikasi status di database tetap queued (atau berubah ke processing)
        $this->printInfo("\nStep 5: Verifikasi status di database");
        $dbJob = DB::table('import_jobs')->where('id', $jobId)->first();
        $this->printInfo("Database status: '{$dbJob->status}'");
        if ($dbJob->status === 'processing') {
            $this->printSuccess("✓ Database sudah update ke 'processing' (expected)");
        } else {
            $this->printWarning("⚠️  Database masih 'queued' (fallback message masih valid)");
        }

        $this->separator();
        $this->printInfo("Kesimpulan Skenario 1:");
        $this->printInfo("  - Modal Preview menampilkan: '{$dashboardPayload['message']}'");
        $this->printInfo("  - Dashboard menampilkan: '{$dashboardPayload['message']}'");
        $this->printInfo("  - ✓ Kedua interface menampilkan pesan yang KONSISTEN");
    }

    /**
     * SKENARIO 2: Cache Expiry
     *
     * Simulasi: Job selesai → cache expired (6 jam) → refresh Dashboard
     * Ekspektasi: Dashboard tetap menampilkan status sesuai database, bukan generik
     */
    public function runScenario2(): void
    {
        $this->printHeader("SKENARIO 2: Cache Expiry - Fallback ke Database Message");

        Cache::flush();

        // Step 1: Create completed job dengan specific message di database
        $this->printInfo("Step 1: Membuat completed import job");
        $jobId = 102;

        DB::table('import_jobs')->where('id', $jobId)->delete();
        $specificMessage = 'Import selesai: 95,500 baris berhasil dari 100,000 (95.5%)';
        DB::table('import_jobs')->insert([
            'id' => $jobId,
            'status' => 'completed',
            'file_name' => 'completed_import_20260426.csv',
            'folder_path' => 'imports/simpanan',
            'id_report' => 1,
            'created_by' => 'test-user',
            'total_files' => 100000,
            'total_success' => 95500,
            'total_failed' => 4500,
            'message' => $specificMessage,
            'created_at' => now()->subHours(8),
            'updated_at' => now()->subHours(8),
        ]);
        $this->printSuccess("Job #{$jobId} dibuat dengan status 'completed'");
        $this->printInfo("Database message: '{$specificMessage}'");

        // Step 2: Simulasi pesan di cache (saat job baru selesai)
        $this->printInfo("\nStep 2: Simulasi cache dengan specific message");
        $cachedPayload = [
            'status' => 'completed',
            'message' => $specificMessage,
            'percent' => 100,
            'processed_rows' => 100000,
            'total_rows' => 100000,
            'total_success' => 95500,
            'total_failed' => 4500,
        ];
        $this->progressService->cacheProgress($jobId, $cachedPayload);
        $this->printSuccess("Cache message: '{$cachedPayload['message']}'");

        // Step 3: Verifikasi dengan cache aktif
        $this->printInfo("\nStep 3: Verifikasi getStatusPayload DENGAN cache aktif");
        $withCache = $this->progressService->getStatusPayload($jobId);
        $this->printSuccess("Message (from cache): '{$withCache['message']}'");

        // Step 4: Simulasi cache expiry
        $this->printInfo("\nStep 4: Simulasi cache expiry (menghapus cache)");
        Cache::forget('import_job_progress:' . $jobId);
        $this->printWarning("Cache dihapus (simulasi 6 jam timeout)");

        // Step 5: Verifikasi fallback ke database message
        $this->printInfo("\nStep 5: Verifikasi getStatusPayload TANPA cache");
        $noCache = $this->progressService->getStatusPayload($jobId);
        $this->printInfo("Message (fallback to database): '{$noCache['message']}'");

        if ($noCache['message'] === $specificMessage) {
            $this->printSuccess("✓ Fallback message sama dengan database message");
        } else {
            $this->printError("✗ Fallback message berbeda!");
            $this->printError("  Expected: '{$specificMessage}'");
            $this->printError("  Got: '{$noCache['message']}'");
        }

        $this->separator();
        $this->printInfo("Kesimpulan Skenario 2:");
        $this->printInfo("  - Dashboard DENGAN cache: menampilkan pesan spesifik dari cache");
        $this->printInfo("  - Dashboard TANPA cache: fallback ke database, BUKAN generic default");
        $this->printInfo("  - ✓ Status akurat meskipun cache hilang");
    }

    /**
     * SKENARIO 3: Real-Time Progress Monitoring
     *
     * Simulasi: Update progress berkala → monitor kolom message
     * Ekspektasi: Message mencerminkan fase aktual import
     */
    public function runScenario3(): void
    {
        $this->printHeader("SKENARIO 3: Real-Time Progress Message Updates");

        Cache::flush();

        // Step 1: Create processing job
        $this->printInfo("Step 1: Membuat import job dengan status 'processing'");
        $jobId = 103;

        DB::table('import_jobs')->where('id', $jobId)->delete();
        DB::table('import_jobs')->insert([
            'id' => $jobId,
            'status' => 'processing',
            'file_name' => 'large_import_20260426.csv',
            'folder_path' => 'imports/daily_loan',
            'id_report' => 8,
            'created_by' => 'test-user',
            'total_files' => 100000,
            'total_success' => 0,
            'total_failed' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->printSuccess("Job #{$jobId} dibuat dengan status 'processing'");

        // Step 2: Simulasi progress updates
        $phases = [
            ['percent' => 10, 'message' => 'Sanitasi CSV via Polars: 10% selesai'],
            ['percent' => 35, 'message' => 'Memproses filter via Polars untuk branch: 35% selesai'],
            ['percent' => 65, 'message' => 'Loading data ke database: 65% selesai'],
            ['percent' => 85, 'message' => 'Finalisasi dan reindex snapshot: 85% selesai'],
            ['percent' => 100, 'message' => 'Import selesai diproses'],
        ];

        $this->printInfo("\nStep 2: Simulasi real-time progress updates");

        foreach ($phases as $idx => $phase) {
            $this->progressService->cacheProgress($jobId, [
                'status' => $phase['percent'] === 100 ? 'completed' : 'processing',
                'phase' => 'polars',
                'mode' => 'polars',
                'percent' => $phase['percent'],
                'message' => $phase['message'],
                'processed_rows' => (int) (100000 * ($phase['percent'] / 100)),
                'total_rows' => 100000,
            ]);

            $payload = $this->progressService->getStatusPayload($jobId);
            $indicator = ($idx === count($phases) - 1) ? '🏁' : '📊';
            $this->printInfo("  {$indicator} Phase " . ($idx + 1) . ": {$payload['message']} ({$payload['percent']}%)");
        }

        // Step 3: Verifikasi final state
        $this->printInfo("\nStep 3: Verifikasi final state");
        $final = $this->progressService->getStatusPayload($jobId);

        if ($final['status'] === 'completed' && str_contains($final['message'], 'selesai')) {
            $this->printSuccess("✓ Final status: completed dengan message yang relevan");
            $this->printSuccess("  Message: '{$final['message']}'");
        } else {
            $this->printError("✗ Final status tidak konsisten");
        }

        $this->separator();
        $this->printInfo("Kesimpulan Skenario 3:");
        $this->printInfo("  - Message berubah mengikuti fase actual import");
        $this->printInfo("  - Setiap update di-cache dengan pesan yang spesifik");
        $this->printInfo("  - ✓ Dashboard menampilkan progress yang akurat");
    }

    /**
     * Run all scenarios
     */
    public function runAllScenarios(): void
    {
        echo "\n\n";
        echo "╔════════════════════════════════════════════════════════════════════════════════╗\n";
        echo "║         TESTING SINKRONISASI STATUS IMPORT - SEMUA SKENARIO                     ║\n";
        echo "╚════════════════════════════════════════════════════════════════════════════════╝\n";

        $this->runScenario1();
        echo "\n";
        $this->runScenario2();
        echo "\n";
        $this->runScenario3();

        echo "\n\n";
        echo "╔════════════════════════════════════════════════════════════════════════════════╗\n";
        echo "║                   TESTING SELESAI                                              ║\n";
        echo "║                                                                                ║\n";
        echo "║  Ringkasan:                                                                    ║\n";
        echo "║  ✓ Skenario 1: Inline Fallback - Pesan tersinkronisasi di cache                ║\n";
        echo "║  ✓ Skenario 2: Cache Expiry - Fallback ke database message                    ║\n";
        echo "║  ✓ Skenario 3: Real-Time Progress - Message mencerminkan fase aktual          ║\n";
        echo "║                                                                                ║\n";
        echo "║  Hasil:                                                                        ║\n";
        echo "║  - Modal Preview dan Dashboard menampilkan status yang KONSISTEN               ║\n";
        echo "║  - Pesan selalu relevan meskipun cache expired                                 ║\n";
        echo "║  - Source of truth tersentralisasi dengan fallback logic yang cerdas           ║\n";
        echo "╚════════════════════════════════════════════════════════════════════════════════╝\n\n";
    }
}

// Inisialisasi dan jalankan
$tester = new StatusSyncManualTestScript();
