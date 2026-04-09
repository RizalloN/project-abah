<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportIndexController;
use App\Support\ReportDataSyncService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ManagedReportDeleteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::dropAllTables();

        Schema::create('nama_report', function (Blueprint $table) {
            $table->integer('id_report')->primary();
            $table->string('nama_report');
            $table->string('table_name');
            $table->boolean('active')->default(true);
        });

        Schema::create('daily_loan_dinamis', function (Blueprint $table) {
            $table->string('uniqueid_namareport')->primary();
            $table->date('periode')->nullable();
            $table->string('cabang1')->nullable();
            $table->string('payload')->nullable();
        });
    }

    protected function tearDown(): void
    {
        \Mockery::close();

        parent::tearDown();
    }

    public function test_delete_management_processes_selected_scopes_per_chunk_without_touching_other_scopes(): void
    {
        DB::table('nama_report')->insert([
            'id_report' => 8,
            'nama_report' => 'Daily Loan Dinamis',
            'table_name' => 'daily_loan_dinamis',
            'active' => 1,
        ]);

        $rows = [];
        for ($i = 1; $i <= 5001; $i++) {
            $rows[] = [
                'uniqueid_namareport' => 'MADIUN-' . $i,
                'periode' => '2026-04-04',
                'cabang1' => 'KC Madiun',
                'payload' => 'row-' . $i,
            ];
        }

        for ($i = 1; $i <= 10; $i++) {
            $rows[] = [
                'uniqueid_namareport' => 'MAGETAN-' . $i,
                'periode' => '2026-04-04',
                'cabang1' => 'KC Magetan',
                'payload' => 'row-b-' . $i,
            ];
        }

        $rows[] = [
            'uniqueid_namareport' => 'PONOROGO-1',
            'periode' => '2026-04-04',
            'cabang1' => 'KC Ponorogo',
            'payload' => 'keep-me',
        ];

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('daily_loan_dinamis')->insert($chunk);
        }

        $controller = app(ImportIndexController::class);
        $initRequest = Request::create('/import/report-management/delete', 'POST', [
            'id_report' => 8,
            'scopes' => [
                ['period' => '2026-04-04', 'kanca' => 'KC Madiun'],
                ['period' => '2026-04-04', 'kanca' => 'KC Magetan'],
            ],
            'force' => true,
        ]);

        $initPayload = $controller->deleteManagedReportRows($initRequest)->getData(true);

        $this->assertSame('running', $initPayload['status']);
        $this->assertSame(5011, $initPayload['total_rows']);

        $syncService = \Mockery::mock(ReportDataSyncService::class);
        $syncService->shouldReceive('cleanupDerivedArtifactsAfterDelete')->once()->andReturn([]);
        $syncService->shouldReceive('syncAfterDelete')->once()->andReturnNull();

        $firstPayload = $controller->processManagedReportDelete($initPayload['delete_id'], $syncService)->getData(true);

        $this->assertSame('running', $firstPayload['status']);
        $this->assertSame(5000, $firstPayload['deleted_rows']);
        $this->assertSame(1, DB::table('daily_loan_dinamis')->where('periode', '2026-04-04')->where('cabang1', 'KC Madiun')->count());
        $this->assertSame(10, DB::table('daily_loan_dinamis')->where('periode', '2026-04-04')->where('cabang1', 'KC Magetan')->count());

        $secondPayload = $controller->processManagedReportDelete($initPayload['delete_id'], $syncService)->getData(true);

        $this->assertSame('running', $secondPayload['status']);
        $this->assertSame(5001, $secondPayload['deleted_rows']);
        $this->assertSame(0, DB::table('daily_loan_dinamis')->where('periode', '2026-04-04')->where('cabang1', 'KC Madiun')->count());
        $this->assertSame(10, DB::table('daily_loan_dinamis')->where('periode', '2026-04-04')->where('cabang1', 'KC Magetan')->count());

        $finalPayload = $controller->processManagedReportDelete($initPayload['delete_id'], $syncService)->getData(true);

        $this->assertSame('completed', $finalPayload['status']);
        $this->assertSame(5011, $finalPayload['deleted_rows']);
        $this->assertSame(0, DB::table('daily_loan_dinamis')->where('periode', '2026-04-04')->whereIn('cabang1', ['KC Madiun', 'KC Magetan'])->count());
        $this->assertSame(1, DB::table('daily_loan_dinamis')->where('periode', '2026-04-04')->where('cabang1', 'KC Ponorogo')->count());
    }
}
