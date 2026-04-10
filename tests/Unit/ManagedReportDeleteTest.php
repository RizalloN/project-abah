<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportIndexController;
use App\Support\ReportDataSyncService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
            'hard_force' => true,
        ]);

        $initPayload = $controller->deleteManagedReportRows($initRequest)->getData(true);

        $this->assertSame('running', $initPayload['status']);
        $this->assertSame(5011, $initPayload['total_rows']);
        $this->assertFalse($initPayload['is_waiting_on_batch']);
        $this->assertSame('idle', $initPayload['batch_state']);

        Cache::put('report_management_delete:' . $initPayload['delete_id'], array_merge($initPayload, [
            'delete_id' => $initPayload['delete_id'],
            'status' => 'running',
            'stage' => 'deleting',
            'batch_state' => 'deleting_pending',
            'message' => 'Memproses batch 10.000 baris... Grup 1/2 (Periode 2026-04-04 | Kanca KC Madiun).',
            'table_name' => 'daily_loan_dinamis',
            'is_waiting_on_batch' => true,
            'active_batch_size' => 10000,
            'last_batch_deleted_rows' => 0,
            'last_batch_started_at' => now()->toIso8601String(),
            'last_batch_finished_at' => null,
            'scopes' => [
                ['period_filter' => '2026-04-04', 'kanca_filter' => 'KC Madiun', 'period_is_null' => false, 'kanca_is_null' => false],
                ['period_filter' => '2026-04-04', 'kanca_filter' => 'KC Magetan', 'period_is_null' => false, 'kanca_is_null' => false],
            ],
            'period_column' => 'periode',
            'kanca_column' => 'cabang1',
            'identity_column' => 'uniqueid_namareport',
            'remaining_rows' => 5011,
            'chunk_size' => 10000,
            'current_scope_index' => 0,
            'cleanup' => null,
        ]), now()->addMinutes(5));

        $pendingPayload = $controller->managedReportDeleteStatus($initPayload['delete_id'])->getData(true);
        $this->assertSame('deleting_pending', $pendingPayload['batch_state']);
        $this->assertTrue($pendingPayload['is_waiting_on_batch']);
        $this->assertSame(10000, $pendingPayload['active_batch_size']);
        $this->assertSame(0, $pendingPayload['last_batch_deleted_rows']);

        $syncService = \Mockery::mock(ReportDataSyncService::class);
        $syncService->shouldReceive('cleanupDerivedArtifactsAfterDelete')->once()->andReturn([]);
        $syncService->shouldReceive('syncAfterDelete')->once()->andReturnNull();

        $firstPayload = $controller->processManagedReportDelete($initPayload['delete_id'], $syncService)->getData(true);

        $this->assertSame('running', $firstPayload['status']);
        $this->assertSame(5001, $firstPayload['deleted_rows']);
        $this->assertSame('deleting_committed', $firstPayload['batch_state']);
        $this->assertFalse($firstPayload['is_waiting_on_batch']);
        $this->assertSame(5001, $firstPayload['last_batch_deleted_rows']);
        $this->assertSame(0, DB::table('daily_loan_dinamis')->where('periode', '2026-04-04')->where('cabang1', 'KC Madiun')->count());
        $this->assertSame(10, DB::table('daily_loan_dinamis')->where('periode', '2026-04-04')->where('cabang1', 'KC Magetan')->count());

        $secondPayload = $controller->processManagedReportDelete($initPayload['delete_id'], $syncService)->getData(true);

        $this->assertSame('completed', $secondPayload['status']);
        $this->assertSame(5011, $secondPayload['deleted_rows']);
        $this->assertSame('completed', $secondPayload['batch_state']);
        $this->assertFalse($secondPayload['is_waiting_on_batch']);
        $this->assertSame(0, DB::table('daily_loan_dinamis')->where('periode', '2026-04-04')->where('cabang1', 'KC Madiun')->count());
        $this->assertSame(0, DB::table('daily_loan_dinamis')->where('periode', '2026-04-04')->where('cabang1', 'KC Magetan')->count());

        $finalPayload = $controller->processManagedReportDelete($initPayload['delete_id'], $syncService)->getData(true);

        $this->assertSame('completed', $finalPayload['status']);
        $this->assertSame(5011, $finalPayload['deleted_rows']);
        $this->assertSame(0, DB::table('daily_loan_dinamis')->where('periode', '2026-04-04')->whereIn('cabang1', ['KC Madiun', 'KC Magetan'])->count());
        $this->assertSame(1, DB::table('daily_loan_dinamis')->where('periode', '2026-04-04')->where('cabang1', 'KC Ponorogo')->count());
    }

    public function test_lock_timeout_error_is_mapped_to_short_user_facing_message(): void
    {
        $controller = app(ImportIndexController::class);
        $method = new \ReflectionMethod($controller, 'buildManagedDeleteFailure');
        $method->setAccessible(true);

        $result = $method->invoke(
            $controller,
            ['deleted_rows' => 0],
            new \RuntimeException('SQLSTATE[HY000]: General error: 1205 Lock wait timeout exceeded; try restarting transaction')
        );

        $this->assertSame('1205', $result['error_code']);
        $this->assertSame('Batch delete gagal karena lock timeout saat menunggu trigger atau snapshot.', $result['message']);
        $this->assertSame('Lock timeout saat delete batch. Coba ulang setelah proses lain selesai.', $result['error']);
    }

    public function test_snapshot_skip_flag_is_only_enabled_for_mysql_like_drivers(): void
    {
        $controller = app(ImportIndexController::class);
        $method = new \ReflectionMethod($controller, 'shouldToggleSnapshotInvalidationFlag');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($controller, 'mysql'));
        $this->assertTrue($method->invoke($controller, 'mariadb'));
        $this->assertFalse($method->invoke($controller, 'sqlite'));
    }
}
