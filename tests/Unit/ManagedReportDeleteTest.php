<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportIndexController;
use App\Services\Import\MySqlBulkLoadService;
use App\Support\ReportDataSyncService;
use Illuminate\Contracts\Bus\Dispatcher as BusDispatcher;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ManagedReportDeleteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        Config::set('cache.default', 'array');
        Queue::fake();

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
        $this->assertSame('queued', $initPayload['batch_state']);

        Cache::store('file')->put('report_management_delete:' . $initPayload['delete_id'], array_merge($initPayload, [
            'delete_id' => $initPayload['delete_id'],
            'status' => 'running',
            'stage' => 'deleting',
            'batch_state' => 'deleting_pending',
            'message' => 'Memproses batch 10.000 baris... Grup 1/2 (Periode 2026-04-04 | Kanca KC Madiun).',
            'table_name' => 'daily_loan_dinamis',
            'is_waiting_on_batch' => true,
            'active_batch_size' => 10000,
            'last_batch_deleted_rows' => 0,
            'created_at' => now()->subSeconds(10)->toIso8601String(),
            'updated_at' => now()->subSeconds(10)->toIso8601String(),
            'last_batch_started_at' => now()->subSeconds(10)->toIso8601String(),
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

        $advanceMethod = new \ReflectionMethod($controller, 'advanceManagedReportDelete');
        $advanceMethod->setAccessible(true);

        $syncService = \Mockery::mock(ReportDataSyncService::class);
        $syncService->shouldReceive('resolvePostDeleteMaintenanceMode')->with('daily_loan_dinamis')->andReturn('snapshot');
        $syncService->shouldReceive('syncAfterDelete')->once()->andReturnNull();

        $firstPayload = $advanceMethod->invoke(
            $controller,
            $initPayload['delete_id'],
            $syncService,
            Cache::store('file')->get('report_management_delete:' . $initPayload['delete_id'])
        );

        $this->assertSame('running', $firstPayload['status']);
        $this->assertSame(5001, $firstPayload['deleted_rows']);
        $this->assertSame('deleting_committed', $firstPayload['batch_state']);
        $this->assertFalse($firstPayload['is_waiting_on_batch']);
        $this->assertSame(5001, $firstPayload['last_batch_deleted_rows']);
        $this->assertSame(0, DB::table('daily_loan_dinamis')->where('periode', '2026-04-04')->where('cabang1', 'KC Madiun')->count());
        $this->assertSame(10, DB::table('daily_loan_dinamis')->where('periode', '2026-04-04')->where('cabang1', 'KC Magetan')->count());

        $secondPayload = $advanceMethod->invoke(
            $controller,
            $initPayload['delete_id'],
            $syncService,
            Cache::store('file')->get('report_management_delete:' . $initPayload['delete_id'])
        );

        $this->assertSame('running', $secondPayload['status']);
        $this->assertSame(5011, $secondPayload['deleted_rows']);
        $this->assertSame('cleanup', $secondPayload['stage']);
        $this->assertSame('deleting_committed', $secondPayload['batch_state']);
        $this->assertFalse($secondPayload['is_waiting_on_batch']);
        $this->assertSame(0, DB::table('daily_loan_dinamis')->where('periode', '2026-04-04')->where('cabang1', 'KC Madiun')->count());
        $this->assertSame(0, DB::table('daily_loan_dinamis')->where('periode', '2026-04-04')->where('cabang1', 'KC Magetan')->count());

        $finalPayload = $advanceMethod->invoke(
            $controller,
            $initPayload['delete_id'],
            $syncService,
            Cache::store('file')->get('report_management_delete:' . $initPayload['delete_id'])
        );

        $finalPayload = $advanceMethod->invoke(
            $controller,
            $initPayload['delete_id'],
            $syncService,
            Cache::store('file')->get('report_management_delete:' . $initPayload['delete_id'])
        );

        $this->assertSame('completed', $finalPayload['status']);
        $this->assertSame(5011, $finalPayload['deleted_rows']);
        $this->assertSame('snapshot_refresh', $finalPayload['cleanup']['mode']);
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

    public function test_management_scope_prefers_branch_name_column_over_code_column(): void
    {
        Schema::create('loan_scope_resolution', function (Blueprint $table) {
            $table->date('periode')->nullable();
            $table->string('kode_cabang1')->nullable();
            $table->string('cabang1')->nullable();
        });

        $controller = app(ImportIndexController::class);
        $method = new \ReflectionMethod($controller, 'resolveManagementScopeColumns');
        $method->setAccessible(true);

        [$periodColumn, $kancaColumn] = $method->invoke(
            $controller,
            'loan_scope_resolution',
            Schema::getColumnListing('loan_scope_resolution')
        );

        $this->assertSame('periode', $periodColumn);
        $this->assertSame('cabang1', $kancaColumn);
    }

    public function test_management_scope_prefers_populated_period_column_when_periode_is_blank(): void
    {
        Schema::create('period_scope_resolution', function (Blueprint $table) {
            $table->string('periode')->nullable();
            $table->date('posisi')->nullable();
            $table->string('nama_kci')->nullable();
        });

        DB::table('period_scope_resolution')->insert([
            ['periode' => null, 'posisi' => '2026-04-30', 'nama_kci' => 'KC Madiun'],
            ['periode' => null, 'posisi' => '2026-04-30', 'nama_kci' => 'KC Magetan'],
        ]);

        $controller = app(ImportIndexController::class);
        $method = new \ReflectionMethod($controller, 'resolveManagementScopeColumns');
        $method->setAccessible(true);

        [$periodColumn, $kancaColumn] = $method->invoke(
            $controller,
            'period_scope_resolution',
            Schema::getColumnListing('period_scope_resolution')
        );

        $this->assertSame('posisi', $periodColumn);
        $this->assertSame('nama_kci', $kancaColumn);
    }

    public function test_management_scope_recognizes_semantic_kanca_aliases(): void
    {
        Schema::create('brimo_scope_resolution', function (Blueprint $table) {
            $table->date('posisi')->nullable();
            $table->string('NAMA_KCI')->nullable();
        });

        Schema::create('simpanan_scope_resolution', function (Blueprint $table) {
            $table->date('periode')->nullable();
            $table->string('nama_kantor_cabang')->nullable();
        });

        $controller = app(ImportIndexController::class);
        $method = new \ReflectionMethod($controller, 'resolveManagementScopeColumns');
        $method->setAccessible(true);

        [$brimoPeriodColumn, $brimoKancaColumn] = $method->invoke(
            $controller,
            'brimo_scope_resolution',
            Schema::getColumnListing('brimo_scope_resolution')
        );

        [$simpananPeriodColumn, $simpananKancaColumn] = $method->invoke(
            $controller,
            'simpanan_scope_resolution',
            Schema::getColumnListing('simpanan_scope_resolution')
        );

        $this->assertSame('posisi', $brimoPeriodColumn);
        $this->assertSame('NAMA_KCI', $brimoKancaColumn);
        $this->assertSame('periode', $simpananPeriodColumn);
        $this->assertSame('nama_kantor_cabang', $simpananKancaColumn);
    }

    public function test_management_scope_does_not_use_unit_kerja_as_kanca_when_branch_name_exists(): void
    {
        Schema::create('uker_scope_resolution', function (Blueprint $table) {
            $table->date('posisi')->nullable();
            $table->string('unit_kerja')->nullable();
            $table->string('NAMA_BRANCH')->nullable();
        });

        DB::table('uker_scope_resolution')->insert([
            ['posisi' => '2026-04-30', 'unit_kerja' => 'Unit Ngrayun', 'NAMA_BRANCH' => 'KC Ponorogo'],
        ]);

        $controller = app(ImportIndexController::class);
        $method = new \ReflectionMethod($controller, 'resolveManagementScopeColumns');
        $method->setAccessible(true);

        [$periodColumn, $kancaColumn] = $method->invoke(
            $controller,
            'uker_scope_resolution',
            Schema::getColumnListing('uker_scope_resolution')
        );

        $this->assertSame('posisi', $periodColumn);
        $this->assertSame('NAMA_BRANCH', $kancaColumn);
    }

    public function test_management_scope_uses_nama_kci_for_merchant_qris_reports(): void
    {
        Schema::create('merchant_qris', function (Blueprint $table) {
            $table->string('PERIODE')->nullable();
            $table->date('POSISI')->nullable();
            $table->string('NAMA_KCI')->nullable();
            $table->string('NAMA_BRANCH')->nullable();
        });

        DB::table('merchant_qris')->insert([
            ['PERIODE' => '2026-04', 'POSISI' => '2026-04-30', 'NAMA_KCI' => 'KC Madiun', 'NAMA_BRANCH' => 'Unit Sudirman'],
        ]);

        $controller = app(ImportIndexController::class);
        $method = new \ReflectionMethod($controller, 'resolveManagementScopeColumns');
        $method->setAccessible(true);

        [$periodColumn, $kancaColumn] = $method->invoke(
            $controller,
            'merchant_qris',
            Schema::getColumnListing('merchant_qris')
        );

        $this->assertSame('PERIODE', $periodColumn);
        $this->assertSame('NAMA_KCI', $kancaColumn);
    }

    public function test_management_scope_treats_tanggal_as_period_column_and_normalizes_display_to_short_date(): void
    {
        Schema::create('tanggal_scope_resolution', function (Blueprint $table) {
            $table->string('tanggal')->nullable();
            $table->string('nama_kci')->nullable();
        });

        DB::table('tanggal_scope_resolution')->insert([
            ['tanggal' => '2026-04-30 23:59:59', 'nama_kci' => 'KC Ponorogo'],
        ]);

        $controller = app(ImportIndexController::class);
        $resolver = new \ReflectionMethod($controller, 'resolveManagementScopeColumns');
        $resolver->setAccessible(true);

        [$periodColumn, $kancaColumn] = $resolver->invoke(
            $controller,
            'tanggal_scope_resolution',
            Schema::getColumnListing('tanggal_scope_resolution')
        );

        $formatter = new \ReflectionMethod($controller, 'formatManagementPeriodLabel');
        $formatter->setAccessible(true);

        $this->assertSame('tanggal', $periodColumn);
        $this->assertSame('nama_kci', $kancaColumn);
        $this->assertSame('2026-04-30', $formatter->invoke($controller, '2026-04-30 23:59:59', 'tanggal'));
        $this->assertSame('2026-04-01', $formatter->invoke($controller, '2026-04', 'tanggal'));
    }

    public function test_delete_management_allows_full_table_delete_with_hard_force_confirmation(): void
    {
        DB::table('nama_report')->insert([
            'id_report' => 9,
            'nama_report' => 'Daily Loan Dinamis Full Guard',
            'table_name' => 'daily_loan_dinamis',
            'active' => 1,
        ]);

        DB::table('daily_loan_dinamis')->insert([
            [
                'uniqueid_namareport' => 'ALL-1',
                'periode' => '2026-04-30',
                'cabang1' => 'KC Madiun',
                'payload' => 'row-1',
            ],
            [
                'uniqueid_namareport' => 'ALL-2',
                'periode' => '2026-04-30',
                'cabang1' => 'KC Madiun',
                'payload' => 'row-2',
            ],
        ]);

        $controller = app(ImportIndexController::class);
        $request = Request::create('/import/report-management/delete', 'POST', [
            'id_report' => 9,
            'scopes' => [
                ['period' => '2026-04-30', 'kanca' => 'KC Madiun'],
            ],
            'force' => true,
            'hard_force' => true,
        ]);

        $response = $controller->deleteManagedReportRows($request);
        $payload = $response->getData(true);

        $this->assertSame(200, $response->status());
        $this->assertSame('running', $payload['status']);
        $this->assertSame(2, $payload['total_rows']);
        $this->assertSame(2, DB::table('daily_loan_dinamis')->count());
        $this->assertSame(2, DB::table('daily_loan_dinamis')->count());
        Queue::assertPushed(\App\Jobs\RunManagedReportDeleteJob::class);
    }

    public function test_delete_management_uses_lightweight_sync_for_reports_without_snapshot(): void
    {
        $controller = app(ImportIndexController::class);
        $method = new \ReflectionMethod($controller, 'advanceManagedReportDelete');
        $method->setAccessible(true);

        $syncService = \Mockery::mock(ReportDataSyncService::class);
        $syncService->shouldReceive('resolvePostDeleteMaintenanceMode')->once()->with('lw325_ph')->andReturn('lightweight');
        $syncService->shouldReceive('syncAfterDeleteLightweight')->once()->with('lw325_ph', null, \Mockery::type('string'))->andReturnNull();

        $state = [
            'delete_id' => 'delete-lightweight-1',
            'status' => 'running',
            'stage' => 'cleanup',
            'batch_state' => 'cleanup',
            'table_name' => 'lw325_ph',
            'period_hint' => null,
            'skip_derived_sync' => false,
            'skip_snapshot_cleanup' => false,
        ];

        $result = $method->invoke($controller, 'delete-lightweight-1', $syncService, $state);

        $this->assertSame('completed', $result['status']);
        $this->assertSame('completed', $result['stage']);
        $this->assertSame('lightweight', $result['cleanup']['mode']);
    }

    public function test_delete_management_normalizes_period_filter_from_scopes_payload(): void
    {
        $controller = app(ImportIndexController::class);
        $method = new \ReflectionMethod($controller, 'normalizeDeleteScopes');
        $method->setAccessible(true);

        $scopes = $method->invoke($controller, [
            'scopes' => [
                [
                    'period_filter' => '2026-04-30',
                    'period_label' => '2026-04-30',
                    'kanca_filter' => 'KC Madiun',
                    'kanca_label' => 'KC Madiun',
                    'period_is_null' => false,
                    'kanca_is_null' => false,
                ],
            ],
        ]);

        $this->assertSame('2026-04-30', $scopes[0]['period_filter']);
        $this->assertSame('KC Madiun', $scopes[0]['kanca_filter']);
        $this->assertSame('2026-04-30', $scopes[0]['period_label']);
        $this->assertSame('KC Madiun', $scopes[0]['kanca_label']);
    }

    public function test_delete_management_completes_immediately_for_lightweight_reports_without_queue(): void
    {
        Schema::create('lw325_ph', function (Blueprint $table) {
            $table->string('uniqueid_namareport')->primary();
            $table->date('periode')->nullable();
            $table->string('kanca')->nullable();
            $table->string('payload')->nullable();
        });

        DB::table('nama_report')->insert([
            'id_report' => 10,
            'nama_report' => 'LW325 PH',
            'table_name' => 'lw325_ph',
            'active' => 1,
        ]);

        DB::table('lw325_ph')->insert([
            ['uniqueid_namareport' => 'PH-1', 'periode' => '2026-04-30', 'kanca' => 'KC Madiun', 'payload' => 'row-1'],
            ['uniqueid_namareport' => 'PH-2', 'periode' => '2026-04-30', 'kanca' => 'KC Madiun', 'payload' => 'row-2'],
            ['uniqueid_namareport' => 'PH-3', 'periode' => '2026-04-30', 'kanca' => 'KC Madiun', 'payload' => 'row-3'],
            ['uniqueid_namareport' => 'PH-4', 'periode' => '2026-04-30', 'kanca' => 'KC Ponorogo', 'payload' => 'keep-me'],
        ]);

        $syncService = \Mockery::mock(ReportDataSyncService::class);
        $syncService->shouldReceive('resolvePostDeleteMaintenanceMode')->with('lw325_ph')->andReturn('lightweight');
        $syncService->shouldReceive('syncAfterDeleteLightweight')->once()->with('lw325_ph', '2026-04-30', \Mockery::type('string'))->andReturnNull();
        app()->instance(ReportDataSyncService::class, $syncService);

        $controller = app(ImportIndexController::class);
        $request = Request::create('/import/report-management/delete', 'POST', [
            'id_report' => 10,
            'scopes' => [
                ['period' => '2026-04-30', 'kanca' => 'KC Madiun'],
            ],
            'force' => true,
            'hard_force' => true,
        ]);

        $response = $controller->deleteManagedReportRows($request);
        $payload = $response->getData(true);

        $this->assertSame(200, $response->status());
        $this->assertSame('completed', $payload['status']);
        $this->assertSame(3, $payload['deleted_rows']);
        $this->assertSame(1, DB::table('lw325_ph')->count());
        $this->assertSame(1, DB::table('lw325_ph')->where('kanca', 'KC Ponorogo')->count());
        Queue::assertNothingPushed();
    }

    public function test_delete_management_blocks_non_transactional_tables_before_processing(): void
    {
        DB::table('nama_report')->insert([
            'id_report' => 13,
            'nama_report' => 'Daily Loan Unsafe Engine',
            'table_name' => 'daily_loan_dinamis',
            'active' => 1,
        ]);

        $bulkLoadService = \Mockery::mock(MySqlBulkLoadService::class);
        $bulkLoadService->shouldReceive('assertTransactionalTable')
            ->once()
            ->with('daily_loan_dinamis', 'delete data report')
            ->andThrow(new \RuntimeException('Operasi delete data report diblokir karena tabel `daily_loan_dinamis` memakai engine `MyISAM`.'));
        app()->instance(MySqlBulkLoadService::class, $bulkLoadService);

        $controller = app(ImportIndexController::class);
        $request = Request::create('/import/report-management/delete', 'POST', [
            'id_report' => 13,
            'scopes' => [
                ['period' => '2026-04-30', 'kanca' => 'KC Madiun'],
            ],
            'force' => true,
            'hard_force' => true,
        ]);

        $response = $controller->deleteManagedReportRows($request);
        $payload = $response->getData(true);

        $this->assertSame(422, $response->status());
        $this->assertSame('error', $payload['status']);
        $this->assertStringContainsString('daily_loan_dinamis', $payload['message']);
        Queue::assertNothingPushed();
    }

    public function test_delete_management_accepts_period_and_kanca_filters_from_scopes_payload(): void
    {
        Schema::create('user_brimo_rpt_v2', function (Blueprint $table) {
            $table->string('uniqueid_namareport')->primary();
            $table->string('periode')->nullable();
            $table->date('posisi')->nullable();
            $table->string('brdesc')->nullable();
            $table->string('branch')->nullable();
            $table->string('mbdesc')->nullable();
        });

        DB::table('nama_report')->insert([
            'id_report' => 11,
            'nama_report' => 'BRIMO RPT V2',
            'table_name' => 'user_brimo_rpt_v2',
            'active' => 1,
        ]);

        DB::table('user_brimo_rpt_v2')->insert([
            ['uniqueid_namareport' => 'BR-1', 'periode' => '2026-04', 'posisi' => '2026-04-30', 'brdesc' => 'KC Madiun', 'branch' => 'MADIUN', 'mbdesc' => 'Unit Sudirman'],
            ['uniqueid_namareport' => 'BR-2', 'periode' => '2026-04', 'posisi' => '2026-04-30', 'brdesc' => 'KC Madiun', 'branch' => 'MADIUN', 'mbdesc' => 'Unit Ngrayun'],
            ['uniqueid_namareport' => 'BR-3', 'periode' => '2026-04', 'posisi' => '2026-04-30', 'brdesc' => 'KC Ponorogo', 'branch' => 'PONOROGO', 'mbdesc' => 'Unit Ponorogo'],
        ]);

        $syncService = \Mockery::mock(ReportDataSyncService::class);
        $syncService->shouldReceive('resolvePostDeleteMaintenanceMode')->with('user_brimo_rpt_v2')->andReturn('lightweight');
        $syncService->shouldReceive('syncAfterDeleteLightweight')->once()->with('user_brimo_rpt_v2', '2026-04-30', \Mockery::type('string'))->andReturnNull();
        app()->instance(ReportDataSyncService::class, $syncService);

        $controller = app(ImportIndexController::class);
        $request = Request::create('/import/report-management/delete', 'POST', [
            'id_report' => 11,
            'scopes' => [
                [
                    'period_filter' => '2026-04-30',
                    'period_label' => '2026-04-30',
                    'kanca_filter' => 'KC Madiun',
                    'kanca_label' => 'KC Madiun',
                    'period_is_null' => false,
                    'kanca_is_null' => false,
                ],
            ],
            'force' => true,
            'hard_force' => true,
        ]);

        $response = $controller->deleteManagedReportRows($request);
        $payload = $response->getData(true);

        $this->assertSame(200, $response->status());
        $this->assertSame('completed', $payload['status']);
        $this->assertSame(2, $payload['deleted_rows']);
        $this->assertSame(1, DB::table('user_brimo_rpt_v2')->where('brdesc', 'KC Ponorogo')->count());
        $this->assertSame(0, DB::table('user_brimo_rpt_v2')->where('brdesc', 'KC Madiun')->count());
        Queue::assertNothingPushed();
    }

    public function test_delete_management_accepts_brimo_fin_scope_filters_from_scopes_payload(): void
    {
        Schema::create('user_brimo_fin', function (Blueprint $table) {
            $table->string('uniqueid_namareport')->primary();
            $table->string('periode')->nullable();
            $table->date('posisi')->nullable();
            $table->string('brdesc')->nullable();
            $table->string('branch')->nullable();
            $table->string('mbdesc')->nullable();
        });

        DB::table('nama_report')->insert([
            'id_report' => 12,
            'nama_report' => 'BRIMO FIN',
            'table_name' => 'user_brimo_fin',
            'active' => 1,
        ]);

        DB::table('user_brimo_fin')->insert([
            ['uniqueid_namareport' => 'FIN-1', 'periode' => '2026-04', 'posisi' => '2026-04-30', 'brdesc' => 'KC Madiun', 'branch' => 'MADIUN', 'mbdesc' => 'Unit Sudirman'],
            ['uniqueid_namareport' => 'FIN-2', 'periode' => '2026-04', 'posisi' => '2026-04-30', 'brdesc' => 'KC Madiun', 'branch' => 'MADIUN', 'mbdesc' => 'Unit Ngrayun'],
            ['uniqueid_namareport' => 'FIN-3', 'periode' => '2026-04', 'posisi' => '2026-04-30', 'brdesc' => 'KC Ponorogo', 'branch' => 'PONOROGO', 'mbdesc' => 'Unit Ponorogo'],
        ]);

        $syncService = \Mockery::mock(ReportDataSyncService::class);
        $syncService->shouldReceive('resolvePostDeleteMaintenanceMode')->with('user_brimo_fin')->andReturn('lightweight');
        $syncService->shouldReceive('syncAfterDeleteLightweight')->once()->with('user_brimo_fin', '2026-04-30', \Mockery::type('string'))->andReturnNull();
        app()->instance(ReportDataSyncService::class, $syncService);

        $controller = app(ImportIndexController::class);
        $request = Request::create('/import/report-management/delete', 'POST', [
            'id_report' => 12,
            'scopes' => [
                [
                    'period_filter' => '2026-04-30',
                    'period_label' => '2026-04-30',
                    'kanca_filter' => 'KC Madiun',
                    'kanca_label' => 'KC Madiun',
                    'period_is_null' => false,
                    'kanca_is_null' => false,
                ],
            ],
            'force' => true,
            'hard_force' => true,
        ]);

        $response = $controller->deleteManagedReportRows($request);
        $payload = $response->getData(true);

        $this->assertSame(200, $response->status());
        $this->assertSame('completed', $payload['status']);
        $this->assertSame(2, $payload['deleted_rows']);
        $this->assertSame(1, DB::table('user_brimo_fin')->where('brdesc', 'KC Ponorogo')->count());
        $this->assertSame(0, DB::table('user_brimo_fin')->where('brdesc', 'KC Madiun')->count());
        Queue::assertNothingPushed();
    }

    public function test_delete_management_falls_back_to_http_processor_when_queue_dispatch_fails(): void
    {
        DB::table('nama_report')->insert([
            'id_report' => 16,
            'nama_report' => 'Daily Loan Queue Fallback',
            'table_name' => 'daily_loan_dinamis',
            'active' => 1,
        ]);

        DB::table('daily_loan_dinamis')->insert([
            [
                'uniqueid_namareport' => 'FALLBACK-1',
                'periode' => '2026-04-30',
                'cabang1' => 'KC Madiun',
                'payload' => 'row-1',
            ],
            [
                'uniqueid_namareport' => 'FALLBACK-2',
                'periode' => '2026-04-30',
                'cabang1' => 'KC Madiun',
                'payload' => 'row-2',
            ],
        ]);

        $dispatcher = \Mockery::mock(BusDispatcher::class);
        $dispatcher->shouldReceive('dispatch')
            ->once()
            ->andThrow(new \RuntimeException('Field `id` doesn\'t have a default value.'));
        app()->instance(BusDispatcher::class, $dispatcher);

        $controller = app(ImportIndexController::class);
        $request = Request::create('/import/report-management/delete', 'POST', [
            'id_report' => 16,
            'scopes' => [
                ['period' => '2026-04-30', 'kanca' => 'KC Madiun'],
            ],
            'force' => true,
            'hard_force' => true,
        ]);

        $response = $controller->deleteManagedReportRows($request);
        $payload = $response->getData(true);

        $this->assertSame(200, $response->status());
        $this->assertSame('running', $payload['status']);
        $this->assertSame('queued', $payload['stage']);
        $this->assertTrue($payload['can_process_fallback']);
        $this->assertStringContainsString('fallback controller', $payload['message']);
        $this->assertSame(2, DB::table('daily_loan_dinamis')->count());
        Queue::assertNothingPushed();
    }

    public function test_delete_management_process_endpoint_advances_queued_state_to_cleanup(): void
    {
        DB::table('nama_report')->insert([
            'id_report' => 17,
            'nama_report' => 'Daily Loan Process Fallback',
            'table_name' => 'daily_loan_dinamis',
            'active' => 1,
        ]);

        DB::table('daily_loan_dinamis')->insert([
            [
                'uniqueid_namareport' => 'PROCESS-1',
                'periode' => '2026-04-30',
                'cabang1' => 'KC Madiun',
                'payload' => 'row-1',
            ],
            [
                'uniqueid_namareport' => 'PROCESS-2',
                'periode' => '2026-04-30',
                'cabang1' => 'KC Madiun',
                'payload' => 'row-2',
            ],
        ]);

        $deleteId = 'delete-process-fallback-1';
        Cache::store('file')->put('report_management_delete:' . $deleteId, [
            'delete_id' => $deleteId,
            'status' => 'running',
            'stage' => 'queued',
            'batch_state' => 'queued',
            'message' => 'Delete dimulai. Sistem akan memproses langsung dan fallback otomatis bila diperlukan...',
            'table_name' => 'daily_loan_dinamis',
            'id_report' => 17,
            'period_column' => 'periode',
            'kanca_column' => 'cabang1',
            'scopes' => [
                ['period_filter' => '2026-04-30', 'kanca_filter' => 'KC Madiun', 'period_is_null' => false, 'kanca_is_null' => false],
            ],
            'period_filter' => '2026-04-30',
            'kanca_filter' => 'KC Madiun',
            'period_is_null' => false,
            'kanca_is_null' => false,
            'period_hint' => '2026-04-30',
            'skip_derived_sync' => false,
            'skip_snapshot_cleanup' => false,
            'identity_column' => 'uniqueid_namareport',
            'total_rows' => 2,
            'deleted_rows' => 0,
            'remaining_rows' => 2,
            'chunk_size' => 10000,
            'current_scope_index' => 0,
            'is_waiting_on_batch' => false,
            'active_batch_size' => 0,
            'last_batch_deleted_rows' => 0,
            'last_batch_started_at' => null,
            'last_batch_finished_at' => null,
            'cleanup' => null,
            'created_at' => now()->subSeconds(10)->toIso8601String(),
            'updated_at' => now()->subSeconds(10)->toIso8601String(),
        ], now()->addMinutes(5));

        $syncService = \Mockery::mock(ReportDataSyncService::class);
        $syncService->shouldReceive('resolvePostDeleteMaintenanceMode')->with('daily_loan_dinamis')->andReturn('snapshot');

        $controller = app(ImportIndexController::class);
        $response = $controller->processManagedReportDelete($deleteId, $syncService);
        $payload = $response->getData(true);

        $this->assertSame('running', $payload['status']);
        $this->assertSame('cleanup', $payload['stage']);
        $this->assertSame('deleting_committed', $payload['batch_state']);
        $this->assertSame(2, $payload['deleted_rows']);
        $this->assertSame(0, DB::table('daily_loan_dinamis')->count());
    }

    public function test_report_management_uses_mbdesc_for_casa_brilink_web_and_edc(): void
    {
        foreach (['casa_brilink_web', 'casa_brilink_edc'] as $index => $tableName) {
            Schema::create($tableName, function (Blueprint $table) {
                $table->string('uniqueid_namareport')->primary();
                $table->date('periode')->nullable();
                $table->string('mbdesc')->nullable();
                $table->string('branch')->nullable();
                $table->string('brdesc')->nullable();
            });

            DB::table('nama_report')->insert([
                'id_report' => 20 + $index,
                'nama_report' => strtoupper(str_replace('_', ' ', $tableName)),
                'table_name' => $tableName,
                'active' => 1,
            ]);

            DB::table($tableName)->insert([
                ['uniqueid_namareport' => $tableName . '-1', 'periode' => '2026-04-30', 'mbdesc' => 'KC Madiun', 'branch' => 'BRANCH A', 'brdesc' => 'DESC A'],
                ['uniqueid_namareport' => $tableName . '-2', 'periode' => '2026-04-30', 'mbdesc' => 'KC Madiun', 'branch' => 'BRANCH B', 'brdesc' => 'DESC B'],
                ['uniqueid_namareport' => $tableName . '-3', 'periode' => '2026-04-30', 'mbdesc' => 'KC Madiun', 'branch' => 'BRANCH C', 'brdesc' => 'DESC C'],
            ]);

            $controller = app(ImportIndexController::class);
            $request = Request::create('/import/report-management/data', 'POST', [
                'id_report' => 20 + $index,
                'max_rows' => 100,
                'page' => 1,
                'per_page' => 8,
            ]);

            $response = $controller->reportManagementData($request);
            $payload = $response->getData(true);

            $this->assertSame(200, $response->status());
            $this->assertSame('success', $payload['status']);
            $this->assertSame('periode', $payload['period_column']);
            $this->assertSame('mbdesc', $payload['kanca_column']);
            $this->assertSame(1, $payload['total_groups']);
            $this->assertSame('KC Madiun', $payload['periods'][0]['rows'][0]['kanca_label']);
        }

        Queue::assertNothingPushed();
    }
}
