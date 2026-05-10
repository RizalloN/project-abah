<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportReportPhController;
use App\Services\Import\ExcelImportJobService;
use App\Services\Import\ImportExecutionService;
use App\Services\Import\ImportProgressService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Schema\Blueprint;
use Mockery;
use Tests\TestCase;

class ImportReportPhQueueTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'sqlite') {
            $this->markTestSkipped('Test ini hanya boleh berjalan di SQLite. Bukan di MySQL production. Periksa phpunit.xml.');
        }
    }

    protected function tearDown(): void
    {
        Storage::disk('local')->delete('testing/report_ph_queue.csv');
        Mockery::close();

        parent::tearDown();
    }

    public function test_init_import_creates_queue_compatible_job_state(): void
    {
        $this->ensureNamaReportTable();
        $this->ensureLw325Table();

        DB::table('nama_report')->where('id_report', 15)->delete();
        DB::table('nama_report')->insert([
            'id_report' => 15,
            'nama_report' => 'Report Nominatif Rekening Pinjaman PH',
            'table_name' => 'lw325_ph',
            'active' => 1,
        ]);

        $relativePath = 'testing/report_ph_queue.csv';
        Storage::disk('local')->put($relativePath, implode("\n", [
            'Textbox3,PERIODE,ACCTNO,KANWIL,KANCA,UNIT,NAMA_DEBITUR,CIF1,FKSEGMEN,SEGMEN_DASHBOARD,DESCRIPTION,PRODUK_DASHBOARD,TGL_PH,TGL_REALISASI,CURTYP,POKOK,BUNGA',
            '1,4/4/2026 12:00:00 AM,814601007586100,KANWIL MALANG,KC Ponorogo,UNIT PASAR CONDONG PONOROGO,SUMIHAR PANJAITAN,SIWZ507,11100,Micro,Kupedes,Kupedes,16/10/2025,11/09/2024,IDR,"219,000.00","1,267,362.25"',
        ]));

        $capturedState = null;
        $capturedQueuedPayload = null;

        $jobService = Mockery::mock(ExcelImportJobService::class);
        $jobService->shouldReceive('createImportJobRecord')
            ->once()
            ->andReturn(901);
        $jobService->shouldReceive('putImportJobState')
            ->once()
            ->with(901, Mockery::on(function (array $state) use (&$capturedState): bool {
                $capturedState = $state;
                return true;
            }));
        $this->app->instance(ExcelImportJobService::class, $jobService);

        $progressService = Mockery::mock(ImportProgressService::class);
        $progressService->shouldReceive('markQueued')
            ->once()
            ->with(901, Mockery::on(function (array $payload) use (&$capturedQueuedPayload): bool {
                $capturedQueuedPayload = $payload;
                return true;
            }));
        $this->app->instance(ImportProgressService::class, $progressService);

        $request = Request::create('/import/report-ph/init', 'POST', [
            'file_path' => $relativePath,
            'selected_columns' => [0, 1, 15, 16],
            'active_filters_json' => json_encode([3 => ['KC Ponorogo']]),
            'delimiter' => ',',
        ]);
        $session = app('session.store');
        $session->put('active_id_report', 15);
        $session->put('report_ph_file', $relativePath);
        $request->setLaravelSession($session);
        app()->instance('request', $request);

        $controller = app(ImportReportPhController::class);
        $response = $controller->initImport($request);

        $payload = $response->getData(true);

        $this->assertSame('success', $payload['status']);
        $this->assertSame(901, $payload['job_id']);
        $this->assertNotNull($capturedState);
        $this->assertSame('lw325_ph', $capturedState['params']['table_name']);
        $this->assertTrue($capturedState['params']['disable_inline_fallback']);
        $this->assertSame($relativePath, $capturedState['params']['file_path']);
        $this->assertArrayHasKey('headers', $capturedState);
        $this->assertContains('acctno', array_map('strtolower', $capturedState['headers']));
        $this->assertNotEmpty($capturedState['params']['active_filters']);
        $this->assertSame('imports-high', $capturedQueuedPayload['queue']);
    }

    public function test_process_import_stream_dispatches_queue_and_uses_shared_status_stream(): void
    {
        $jobId = 902;
        $request = Request::create('/import/report-ph/stream', 'GET', ['job_id' => $jobId]);
        $session = app('session.store');
        $session->put('report_ph_import_params', ['job_id' => $jobId]);
        $request->setLaravelSession($session);
        app()->instance('request', $request);

        $expectedResponse = response()->stream(function (): void {
        });

        $executionService = Mockery::mock(ImportExecutionService::class);
        $executionService->shouldReceive('dispatch')
            ->once()
            ->with($jobId, Mockery::type('string'))
            ->andReturn(true);
        $executionService->shouldReceive('streamStatus')
            ->once()
            ->with(Mockery::type(Request::class), $jobId, false)
            ->andReturn($expectedResponse);
        $this->app->instance(ImportExecutionService::class, $executionService);

        $controller = app(ImportReportPhController::class);
        $response = $controller->processImportStream($request);

        $this->assertSame($expectedResponse, $response);
    }

    private function ensureNamaReportTable(): void
    {
        if (Schema::hasTable('nama_report')) {
            return;
        }

        Schema::create('nama_report', function (Blueprint $table): void {
            $table->unsignedInteger('id_report')->primary();
            $table->string('nama_report');
            $table->string('table_name')->nullable();
            $table->boolean('active')->default(true);
        });
    }

    private function ensureLw325Table(): void
    {
        if (Schema::hasTable('lw325_ph')) {
            return;
        }

        Schema::create('lw325_ph', function (Blueprint $table): void {
            $table->string('uniqueid_namareport')->primary();
            $table->date('periode')->nullable();
            $table->string('acctno')->nullable();
            $table->timestamps();
        });
    }
}
