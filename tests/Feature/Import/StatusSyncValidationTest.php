<?php

namespace Tests\Feature\Import;

use App\Models\ImportJob;
use App\Services\Import\ImportProgressService;
use App\Services\Import\ImportExecutionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StatusSyncValidationTest extends TestCase
{
    private ImportProgressService $progressService;
    private ImportExecutionService $executionService;
    private string $originalDefaultConnection;
    private mixed $originalSqliteDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalDefaultConnection = (string) Config::get('database.default');
        $this->originalSqliteDatabase = Config::get('database.connections.sqlite.database');

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        $this->createImportJobsTable();
        Cache::flush();
        $this->progressService = app(ImportProgressService::class);
        $this->executionService = app(ImportExecutionService::class);
    }

    protected function tearDown(): void
    {
        Cache::flush();
        Config::set('database.default', $this->originalDefaultConnection);
        Config::set('database.connections.sqlite.database', $this->originalSqliteDatabase);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        parent::tearDown();
    }

    /**
     * Skenario 1: Inline Fallback - Verifikasi pesan cache tersinkronisasi
     *
     * Ekspektasi: Ketika inline fallback dijalankan, pesan di cache harus match dengan
     * yang dikirim ke SSE stream (Modal Preview dan Dashboard menampilkan pesan sama).
     */
    public function test_inline_fallback_message_synced_to_cache(): void
    {
        $jobId = 1;

        // Setup: Create job dengan status queued
        $this->createTestJob($jobId, 'queued', [
            'total_files' => 100,
            'file_name' => 'test.csv',
            'folder_path' => 'imports',
            'id_report' => 1,
        ]);

        // Simulasi: Fallback message di-cache
        $fallbackPayload = [
            'status' => 'processing',
            'phase' => 'polars',
            'mode' => 'polars',
            'percent' => 6,
            'message' => 'Worker sibuk, mencoba menjalankan langsung...',
            'processed_rows' => 0,
            'total_rows' => 100,
        ];
        $this->progressService->cacheProgress($jobId, $fallbackPayload);

        // Verifikasi: Cache harus memiliki pesan yang lengkap
        $cachedData = Cache::get('import_job_progress:' . $jobId);
        $this->assertNotNull($cachedData);
        $this->assertEquals('Worker sibuk, mencoba menjalankan langsung...', $cachedData['message']);
        $this->assertEquals('processing', $cachedData['status']);

        // Verifikasi: getStatusPayload harus mengembalikan cache message
        $payload = $this->progressService->getStatusPayload($jobId);
        $this->assertEquals('Worker sibuk, mencoba menjalankan langsung...', $payload['message']);
    }

    /**
     * Skenario 2: Cache Expiry - Verifikasi fallback ke database message
     *
     * Ekspektasi: Setelah cache expired (dihapus), Dashboard harus menampilkan status
     * yang relevan berdasarkan data di database, bukan pesan default generik.
     */
    public function test_message_fallback_to_database_after_cache_expiry(): void
    {
        $jobId = 2;

        // Setup: Create completed job dengan specific message di database
        $this->createTestJob($jobId, 'completed', [
            'total_files' => 100,
            'file_name' => 'test.csv',
            'folder_path' => 'imports',
            'id_report' => 1,
            'message' => 'Memproses filter via Polars: 95 baris berhasil dari 100.',
        ]);

        // Simulasi: Hapus cache (cache expired)
        Cache::forget('import_job_progress:' . $jobId);

        // Verifikasi: getStatusPayload harus fallback ke database message
        $payload = $this->progressService->getStatusPayload($jobId);
        $this->assertEquals('completed', $payload['status']);
        // Harus menggunakan pesan dari database, bukan generic default
        $expectedMessage = 'Memproses filter via Polars: 95 baris berhasil dari 100.';
        $this->assertEquals($expectedMessage, $payload['message']);
    }

    /**
     * Skenario 3: Default Message Resolution
     *
     * Ekspektasi: Saat cache hilang DAN database juga tidak ada pesan spesifik,
     * sistem harus menampilkan pesan default yang relevan dengan status.
     */
    public function test_default_message_per_status(): void
    {
        // Test: Queued status
        $queued = $this->progressService->getStatusPayload($this->createTestJob(3, 'queued', [
            'total_files' => 50,
            'file_name' => 'test.csv',
            'folder_path' => 'imports',
            'id_report' => 1,
        ]));
        $this->assertStringContainsString('antrian', $queued['message']);

        // Test: Processing status
        $processing = $this->progressService->getStatusPayload($this->createTestJob(4, 'processing', [
            'total_files' => 50,
            'file_name' => 'test.csv',
            'folder_path' => 'imports',
            'id_report' => 1,
        ]));
        $this->assertStringContainsString('diproses', $processing['message']);

        // Test: Completed status
        $completed = $this->progressService->getStatusPayload($this->createTestJob(5, 'completed', [
            'total_files' => 50,
            'file_name' => 'test.csv',
            'folder_path' => 'imports',
            'id_report' => 1,
        ]));
        $this->assertStringContainsString('selesai', $completed['message']);
    }

    /**
     * Skenario 4: Real-Time Progress Message Consistency
     *
     * Ekspektasi: Saat import berjalan dengan detailed phase messages,
     * pesan harus tetap konsisten antara cache dan database.
     */
    public function test_realtime_progress_message_consistency(): void
    {
        $jobId = 6;
        $this->createTestJob($jobId, 'processing', [
            'total_files' => 100,
            'file_name' => 'test.csv',
            'folder_path' => 'imports',
            'id_report' => 1,
        ]);

        // Simulasi: Update dengan detailed message
        $detailedMessage = 'Memproses filter via Polars untuk branch: 45 baris tersanitasi';
        $this->progressService->cacheProgress($jobId, [
            'status' => 'processing',
            'phase' => 'polars',
            'mode' => 'polars',
            'percent' => 45,
            'message' => $detailedMessage,
            'processed_rows' => 45,
            'total_rows' => 100,
        ]);

        // Verifikasi: Cache harus mempertahankan detailed message
        $payload1 = $this->progressService->getStatusPayload($jobId);
        $this->assertEquals($detailedMessage, $payload1['message']);

        // Simulasi: Hapus cache
        Cache::forget('import_job_progress:' . $jobId);

        // Verifikasi: Fallback harus tetap menggunakan status-based message, bukan generik
        $payload2 = $this->progressService->getStatusPayload($jobId);
        $this->assertEquals('processing', $payload2['status']);
        // Harus fallback dengan message yang relevan untuk status 'processing'
        $this->assertStringContainsString('diproses', $payload2['message']);
    }

    public function test_reserved_queue_row_does_not_change_job_to_processing_before_worker_starts(): void
    {
        $jobId = 7;
        $this->createTestJob($jobId, 'queued', [
            'total_files' => 100,
            'file_name' => 'ssa.xlsx',
            'folder_path' => 'imports',
            'id_report' => 17,
        ]);

        DB::table('jobs')->insert([
            'queue' => 'imports-high',
            'reserved_at' => now()->timestamp,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
            'payload' => 'O:21:"App\\Jobs\\RunImportJob":1:{s:5:"jobId";i:7;}',
        ]);

        $payload = $this->progressService->getStatusPayload($jobId);

        $this->assertSame('queued', $payload['status']);
        $this->assertTrue($payload['queue_present']);
        $this->assertTrue($payload['queue_reserved']);
        $this->assertFalse($payload['is_stale_queue']);
        $this->assertStringContainsString('menyiapkan proses', $payload['message']);
        $this->assertSame(
            'queued',
            DB::table('import_jobs')->where('id', $jobId)->value('status')
        );
    }

    public function test_old_queued_job_with_active_queue_row_is_not_marked_failed(): void
    {
        $jobId = 9;
        $this->createTestJob($jobId, 'queued', [
            'total_files' => 100,
            'file_name' => 'ssa.xlsx',
            'folder_path' => 'imports',
            'id_report' => 17,
            'updated_at' => now()->subMinutes(20),
        ]);

        DB::table('jobs')->insert([
            'queue' => 'imports-high',
            'reserved_at' => null,
            'available_at' => now()->subMinutes(20)->timestamp,
            'created_at' => now()->subMinutes(20)->timestamp,
            'payload' => 'O:21:"App\\Jobs\\RunImportJob":1:{s:5:"jobId";i:9;}',
        ]);

        $payload = $this->progressService->getStatusPayload($jobId);
        $purged = $this->progressService->purgeStaleQueuedJobs();

        $this->assertSame('queued', $payload['status']);
        $this->assertTrue($payload['queue_present']);
        $this->assertFalse($payload['queue_reserved']);
        $this->assertFalse($payload['is_stale_queue']);
        $this->assertSame(0, $purged);
        $this->assertSame(
            'queued',
            DB::table('import_jobs')->where('id', $jobId)->value('status')
        );
    }

    public function test_orphaned_zero_progress_job_is_automatically_requeued(): void
    {
        Bus::fake();
        $jobId = 8;
        $state = [
            'params' => [
                'job_id' => $jobId,
                'table_name' => 'ssa_simpanan',
                'file_path' => 'excel_imports/ssa.xlsx',
            ],
            'headers' => [],
        ];

        $this->createTestJob($jobId, 'processing', [
            'total_files' => 0,
            'file_name' => 'ssa.xlsx',
            'folder_path' => 'imports',
            'id_report' => 17,
            'job_context' => json_encode([
                'table_name' => 'ssa_simpanan',
                'state' => $state,
            ]),
            'updated_at' => now()->subMinutes(2),
        ]);

        $recovered = $this->executionService->recoverOrphanedZeroProgressJobs(60);

        $this->assertSame([$jobId], $recovered);
        $this->assertSame(
            'queued',
            DB::table('import_jobs')->where('id', $jobId)->value('status')
        );
        Bus::assertDispatched(\App\Jobs\RunImportJob::class, fn ($job): bool => $job->jobId === $jobId);
    }

    /**
     * Helper: Create test job
     */
    private function createTestJob(int $jobId, string $status, array $attributes): int
    {
        $defaults = [
            'id' => $jobId,
            'status' => $status,
            'created_by' => 'test-user',
            'total_success' => 0,
            'total_failed' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        \DB::table('import_jobs')->insert(array_merge($defaults, $attributes));

        return $jobId;
    }

    private function createImportJobsTable(): void
    {
        Schema::dropIfExists('import_jobs');
        Schema::dropIfExists('jobs');

        Schema::create('import_jobs', function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->integer('id_report')->nullable();
            $table->string('file_name')->nullable();
            $table->string('folder_path')->nullable();
            $table->string('status')->nullable();
            $table->integer('total_files')->default(0);
            $table->integer('total_success')->default(0);
            $table->integer('total_failed')->default(0);
            $table->string('created_by')->nullable();
            $table->text('message')->nullable();
            $table->longText('job_context')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('jobs', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('queue')->index();
            $table->integer('reserved_at')->nullable();
            $table->integer('available_at')->nullable();
            $table->integer('created_at')->nullable();
            $table->longText('payload');
        });
    }
}
