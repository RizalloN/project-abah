<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportIndexController;
use App\Services\Import\MySqlBulkLoadService;
use App\Support\ManagedReportDeleteRecoveryService;
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

        Schema::create('jobs', function (Blueprint $table) {
            $table->increments('id');
            $table->string('queue')->index();
            $table->integer('reserved_at')->nullable();
            $table->integer('available_at')->nullable();
            $table->integer('created_at')->nullable();
            $table->longText('payload');
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
        $syncService->shouldReceive('cleanupDerivedArtifactsAfterDelete')
            ->once()
            ->with('daily_loan_dinamis', '2026-04-04', \Mockery::type('string'), \Mockery::type('string'))
            ->andReturn([]);
        $syncService->shouldReceive('syncAfterDeleteLightweight')
            ->once()
            ->with('daily_loan_dinamis', null, \Mockery::type('string'), \Mockery::type('string'))
            ->andReturnNull();
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
        $this->assertSame('syncing', $secondPayload['stage']);
        $this->assertSame('cleanup', $secondPayload['batch_state']);
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
        $this->assertSame('snapshot_cleanup', $finalPayload['cleanup']['mode']);
        $this->assertSame(0, DB::table('daily_loan_dinamis')->where('periode', '2026-04-04')->whereIn('cabang1', ['KC Madiun', 'KC Magetan'])->count());
        $this->assertSame(1, DB::table('daily_loan_dinamis')->where('periode', '2026-04-04')->where('cabang1', 'KC Ponorogo')->count());
    }

    public function test_build_delete_where_sql_can_qualify_constraints_for_delete_target_alias(): void
    {
        $controller = app(ImportIndexController::class);
        $method = new \ReflectionMethod($controller, 'buildDeleteWhereSql');
        $method->setAccessible(true);

        [$sql, $bindings] = $method->invoke($controller, [
            ['column' => 'periode', 'mode' => 'equal', 'value' => '2026-04-04'],
            ['column' => 'cabang1', 'mode' => 'empty'],
            ['column' => 'payload', 'mode' => 'trim'],
        ], 'target');

        $this->assertStringContainsString('(`target`.`periode` = ?)', $sql);
        $this->assertStringContainsString('(`target`.`cabang1` = \'\')', $sql);
        $this->assertStringContainsString('TRIM(`target`.`payload`) = \'\'', $sql);
        $this->assertSame(['2026-04-04'], $bindings);
    }

    public function test_delete_management_uses_partition_shortcut_for_period_only_scopes_when_available(): void
    {
        DB::table('nama_report')->insert([
            'id_report' => 8,
            'nama_report' => 'Daily Loan Dinamis',
            'table_name' => 'daily_loan_dinamis',
            'active' => 1,
        ]);

        DB::table('daily_loan_dinamis')->insert([
            [
                'uniqueid_namareport' => 'ROW-1',
                'periode' => '2026-04-04',
                'cabang1' => 'KC Madiun',
                'payload' => 'row-1',
            ],
            [
                'uniqueid_namareport' => 'ROW-2',
                'periode' => '2026-04-04',
                'cabang1' => 'KC Magetan',
                'payload' => 'row-2',
            ],
            [
                'uniqueid_namareport' => 'ROW-3',
                'periode' => '2026-04-05',
                'cabang1' => 'KC Ponorogo',
                'payload' => 'row-3',
            ],
        ]);

        $partitionMaintenance = \Mockery::mock(\App\Support\PartitionMaintenanceService::class);
        $partitionMaintenance->shouldReceive('supportsPartitionDdl')->andReturnTrue();
        $partitionMaintenance->shouldReceive('resolveSinglePartitionForValue')
            ->once()
            ->with('daily_loan_dinamis', 'periode', '2026-04-04')
            ->andReturn('p202604');
        $partitionMaintenance->shouldReceive('truncatePartition')
            ->once()
            ->with('daily_loan_dinamis', 'p202604')
            ->andReturnNull();

        $bulkLoadService = \Mockery::mock(MySqlBulkLoadService::class);
        $bulkLoadService->shouldReceive('assertTransactionalTable')->once()->with('daily_loan_dinamis', 'delete data report')->andReturnNull();
        $bulkLoadService->shouldReceive('withTableWriteLock')
            ->once()
            ->with('daily_loan_dinamis', \Mockery::type('callable'))
            ->andReturnUsing(function (string $tableName, callable $callback) {
                return $callback();
            });

        app()->instance(MySqlBulkLoadService::class, $bulkLoadService);

        try {
            $controller = new ImportIndexController($partitionMaintenance);
            $method = new \ReflectionMethod($controller, 'deleteScopedRows');
            $method->setAccessible(true);

            $baseQuery = DB::table('daily_loan_dinamis')->where('periode', '2026-04-04');
            $affected = $method->invoke(
                $controller,
                'daily_loan_dinamis',
                $baseQuery,
                'uniqueid_namareport',
                10000,
                'periode',
                'cabang1',
                [
                    'period_filter' => '2026-04-04',
                    'kanca_filter' => null,
                    'period_is_null' => false,
                    'kanca_is_null' => false,
                ]
            );

            $this->assertSame(2, $affected);
            $this->assertSame(3, DB::table('daily_loan_dinamis')->count());
        } finally {
            app()->forgetInstance(MySqlBulkLoadService::class);
        }
    }

    public function test_delete_management_writes_audit_for_processed_chunk(): void
    {
        Schema::create('report_sync_audits', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('import_job_id')->nullable();
            $table->string('source', 150)->nullable();
            $table->string('table_name', 120);
            $table->date('period_hint')->nullable();
            $table->string('action', 80);
            $table->string('status', 30);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedInteger('affected_rows')->nullable();
            $table->text('message')->nullable();
            $table->text('context')->nullable();
            $table->timestamps();
        });

        DB::table('nama_report')->insert([
            'id_report' => 18,
            'nama_report' => 'Daily Loan Audit',
            'table_name' => 'daily_loan_dinamis',
            'active' => 1,
        ]);

        DB::table('daily_loan_dinamis')->insert([
            ['uniqueid_namareport' => 'AUDIT-1', 'periode' => '2026-04-04', 'cabang1' => 'KC Madiun', 'payload' => 'row-1'],
            ['uniqueid_namareport' => 'AUDIT-2', 'periode' => '2026-04-04', 'cabang1' => 'KC Madiun', 'payload' => 'row-2'],
            ['uniqueid_namareport' => 'AUDIT-3', 'periode' => '2026-04-04', 'cabang1' => 'KC Ponorogo', 'payload' => 'keep'],
        ]);

        $controller = app(ImportIndexController::class);
        $deleteId = 'delete-audit-1';
        Cache::store('file')->put('report_management_delete:' . $deleteId, [
            'delete_id' => $deleteId,
            'status' => 'running',
            'stage' => 'deleting',
            'batch_state' => 'deleting_pending',
            'message' => 'Memproses batch 10.000 baris... Grup 1/1 (Periode 2026-04-04 | Kanca KC Madiun).',
            'table_name' => 'daily_loan_dinamis',
            'id_report' => 18,
            'period_column' => 'periode',
            'kanca_column' => 'cabang1',
            'scopes' => [
                ['period_filter' => '2026-04-04', 'kanca_filter' => 'KC Madiun', 'period_is_null' => false, 'kanca_is_null' => false],
            ],
            'period_hint' => '2026-04-04',
            'identity_column' => 'uniqueid_namareport',
            'total_rows' => 2,
            'deleted_rows' => 0,
            'remaining_rows' => 2,
            'chunk_size' => 10000,
            'current_scope_index' => 0,
            'is_waiting_on_batch' => true,
            'active_batch_size' => 10000,
            'last_batch_deleted_rows' => 0,
            'created_at' => now()->subSeconds(5)->toIso8601String(),
            'updated_at' => now()->subSeconds(5)->toIso8601String(),
        ], now()->addMinutes(5));

        $method = new \ReflectionMethod($controller, 'processDeleteChunk');
        $method->setAccessible(true);
        $result = $method->invoke($controller, Cache::store('file')->get('report_management_delete:' . $deleteId));

        $this->assertSame(2, $result['deleted_rows']);
        $this->assertDatabaseHas('report_sync_audits', [
            'table_name' => 'daily_loan_dinamis',
            'action' => 'managed_delete_chunk',
            'status' => 'success',
            'affected_rows' => 2,
        ]);
    }

    public function test_delete_management_uses_full_table_shortcut_for_simpanan_multipn(): void
    {
        Schema::create('simpanan_multipn', function (Blueprint $table) {
            $table->string('uniqueid_SMPN')->primary();
            $table->date('posisi')->nullable();
            $table->string('kantor_cabang')->nullable();
            $table->string('unit_kerja')->nullable();
            $table->string('status')->nullable();
            $table->decimal('saldo_idr', 18, 2)->nullable();
        });

        Schema::create('report_sync_audits', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('import_job_id')->nullable();
            $table->string('source', 150)->nullable();
            $table->string('table_name', 120);
            $table->date('period_hint')->nullable();
            $table->string('action', 80);
            $table->string('status', 30);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedInteger('affected_rows')->nullable();
            $table->text('message')->nullable();
            $table->text('context')->nullable();
            $table->timestamps();
        });

        DB::table('nama_report')->insert([
            'id_report' => 9,
            'nama_report' => 'Simpanan MultiPN',
            'table_name' => 'simpanan_multipn',
            'active' => 1,
        ]);

        DB::table('simpanan_multipn')->insert([
            ['uniqueid_SMPN' => 'SMP-1', 'posisi' => '2026-04-04', 'kantor_cabang' => 'KC Ponorogo', 'unit_kerja' => 'Unit A', 'status' => '1', 'saldo_idr' => 1000],
            ['uniqueid_SMPN' => 'SMP-2', 'posisi' => '2026-04-04', 'kantor_cabang' => 'KC Ponorogo', 'unit_kerja' => 'Unit B', 'status' => '1', 'saldo_idr' => 2000],
            ['uniqueid_SMPN' => 'SMP-3', 'posisi' => '2026-04-04', 'kantor_cabang' => 'KC Ponorogo', 'unit_kerja' => 'Unit C', 'status' => '9', 'saldo_idr' => 3000],
        ]);

        $syncService = \Mockery::mock(ReportDataSyncService::class);
        $syncService->shouldReceive('resolvePostDeleteMaintenanceMode')->with('simpanan_multipn')->andReturn('snapshot');
        $syncService->shouldReceive('cleanupDerivedArtifactsAfterDelete')
            ->once()
            ->with('simpanan_multipn', null, \Mockery::type('string'), null)
            ->andReturn([]);
        $syncService->shouldReceive('syncAfterDeleteLightweight')
            ->once()
            ->with('simpanan_multipn', null, \Mockery::type('string'), null)
            ->andReturnNull();
        app()->instance(ReportDataSyncService::class, $syncService);

        $bulkLoadService = \Mockery::mock(MySqlBulkLoadService::class);
        $bulkLoadService->shouldReceive('assertTransactionalTable')->twice()->with('simpanan_multipn', 'delete data report')->andReturnNull();
        $bulkLoadService->shouldReceive('withTableWriteLock')
            ->once()
            ->with('simpanan_multipn', \Mockery::type('callable'))
            ->andReturnUsing(function (string $tableName, callable $callback) {
                return $callback();
            });
        $this->app->instance(MySqlBulkLoadService::class, $bulkLoadService);

        $controller = app(ImportIndexController::class);
        $request = Request::create('/import/report-management/delete', 'POST', [
            'id_report' => 9,
            'scopes' => [
                ['period' => '2026-04-04', 'kanca' => 'KC Ponorogo'],
            ],
            'force' => true,
            'hard_force' => true,
        ]);

        $response = $controller->deleteManagedReportRows($request);
        $payload = $response->getData(true);

        $this->assertSame(200, $response->status());
        $this->assertSame('completed', $payload['status']);
        $this->assertSame(3, $payload['deleted_rows']);
        $this->assertSame('full_table_delete_fallback', $payload['delete_strategy']);
        $this->assertSame(0, DB::table('simpanan_multipn')->count());
        $this->assertDatabaseHas('report_sync_audits', [
            'table_name' => 'simpanan_multipn',
            'action' => 'managed_delete_shortcut',
            'status' => 'success',
            'affected_rows' => 3,
        ]);
        Queue::assertNothingPushed();
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

    public function test_zero_error_codes_are_not_exposed_in_delete_failures(): void
    {
        $controller = app(ImportIndexController::class);
        $method = new \ReflectionMethod($controller, 'resolveManagedDeleteErrorCode');
        $method->setAccessible(true);

        $this->assertNull($method->invoke($controller, new \RuntimeException('Generic failure', 0)));
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

        $this->assertSame('POSISI', $periodColumn);
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
        $this->assertSame('2026-04', $formatter->invoke($controller, '2026-04-30 23:59:59', 'tanggal'));
        $this->assertSame('2026-04', $formatter->invoke($controller, '2026-04', 'tanggal'));
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
        $syncService->shouldReceive('syncAfterDeleteLightweight')->once()->with('lw325_ph', null, \Mockery::type('string'), 'delete-lightweight-1')->andReturnNull();

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

    public function test_lw325_report_management_groups_blank_period_and_blank_kanca_by_created_at(): void
    {
        Schema::create('lw325_ph', function (Blueprint $table) {
            $table->string('uniqueid_namareport')->primary();
            $table->date('periode')->nullable();
            $table->string('kanca')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        DB::table('nama_report')->insert([
            'id_report' => 101,
            'nama_report' => 'LW325 PH Blank Scope',
            'table_name' => 'lw325_ph',
            'active' => 1,
        ]);

        DB::table('lw325_ph')->insert([
            ['uniqueid_namareport' => 'PH-BLANK-A1', 'periode' => null, 'kanca' => null, 'created_at' => '2026-04-18 09:31:22', 'updated_at' => '2026-04-18 09:31:22'],
            ['uniqueid_namareport' => 'PH-BLANK-A2', 'periode' => '', 'kanca' => '', 'created_at' => '2026-04-18 09:31:22', 'updated_at' => '2026-04-18 09:31:22'],
            ['uniqueid_namareport' => 'PH-BLANK-B1', 'periode' => null, 'kanca' => '', 'created_at' => '2026-04-18 10:41:10', 'updated_at' => '2026-04-18 10:41:10'],
            ['uniqueid_namareport' => 'PH-NORMAL-1', 'periode' => '2026-04-30', 'kanca' => 'KC Madiun', 'created_at' => '2026-04-18 11:00:00', 'updated_at' => '2026-04-18 11:00:00'],
        ]);

        $controller = app(ImportIndexController::class);
        $request = Request::create('/import/report-management/data', 'POST', [
            'id_report' => 101,
            'max_rows' => 100,
            'page' => 1,
            'per_page' => 8,
        ]);

        $response = $controller->reportManagementData($request);
        $payload = $response->getData(true);

        $this->assertSame(200, $response->status());
        $this->assertSame('success', $payload['status']);

        $blankBuckets = collect($payload['rows'])
            ->filter(fn (array $row): bool => ($row['fallback_mode'] ?? null) === 'lw325_blank_created_at')
            ->values();

        $this->assertCount(2, $blankBuckets);
        $this->assertSame('created_at', $blankBuckets[0]['fallback_period_column']);
        $this->assertSame('2026-04-18 10:41:10', $blankBuckets[0]['fallback_period_filter']);
        $this->assertSame('Import 2026-04-18 10:41:10', $blankBuckets[0]['period_label']);
        $this->assertSame(1, $blankBuckets[0]['row_count']);
        $this->assertSame('2026-04-18 09:31:22', $blankBuckets[1]['fallback_period_filter']);
        $this->assertSame(2, $blankBuckets[1]['row_count']);
    }

    public function test_delete_management_lw325_blank_created_at_scope_only_deletes_selected_import_batch(): void
    {
        Schema::create('lw325_ph', function (Blueprint $table) {
            $table->string('uniqueid_namareport')->primary();
            $table->date('periode')->nullable();
            $table->string('kanca')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        DB::table('nama_report')->insert([
            'id_report' => 102,
            'nama_report' => 'LW325 PH Blank Delete',
            'table_name' => 'lw325_ph',
            'active' => 1,
        ]);

        DB::table('lw325_ph')->insert([
            ['uniqueid_namareport' => 'PH-A1', 'periode' => null, 'kanca' => null, 'created_at' => '2026-04-18 09:31:22', 'updated_at' => '2026-04-18 09:31:22'],
            ['uniqueid_namareport' => 'PH-A2', 'periode' => '', 'kanca' => '', 'created_at' => '2026-04-18 09:31:22', 'updated_at' => '2026-04-18 09:31:22'],
            ['uniqueid_namareport' => 'PH-B1', 'periode' => null, 'kanca' => '', 'created_at' => '2026-04-18 10:41:10', 'updated_at' => '2026-04-18 10:41:10'],
            ['uniqueid_namareport' => 'PH-C1', 'periode' => null, 'kanca' => 'KC Madiun', 'created_at' => '2026-04-18 09:31:22', 'updated_at' => '2026-04-18 09:31:22'],
            ['uniqueid_namareport' => 'PH-D1', 'periode' => '2026-04-30', 'kanca' => null, 'created_at' => '2026-04-18 09:31:22', 'updated_at' => '2026-04-18 09:31:22'],
        ]);

        $syncService = \Mockery::mock(ReportDataSyncService::class);
        $syncService->shouldReceive('resolvePostDeleteMaintenanceMode')->with('lw325_ph')->andReturn('lightweight');
        $syncService->shouldReceive('syncAfterDeleteLightweight')->once()->with('lw325_ph', null, \Mockery::type('string'))->andReturnNull();
        app()->instance(ReportDataSyncService::class, $syncService);

        $controller = app(ImportIndexController::class);
        $request = Request::create('/import/report-management/delete', 'POST', [
            'id_report' => 102,
            'scopes' => [[
                'period_filter' => null,
                'period_label' => 'Import 2026-04-18 09:31:22',
                'kanca_filter' => null,
                'kanca_label' => '(Blank)',
                'period_is_null' => true,
                'kanca_is_null' => true,
                'fallback_mode' => 'lw325_blank_created_at',
                'fallback_period_column' => 'created_at',
                'fallback_period_filter' => '2026-04-18 09:31:22',
                'fallback_period_label' => 'Import 2026-04-18 09:31:22',
            ]],
            'force' => true,
            'hard_force' => true,
        ]);

        $response = $controller->deleteManagedReportRows($request);
        $payload = $response->getData(true);

        $this->assertSame(200, $response->status());
        $this->assertSame('completed', $payload['status']);
        $this->assertSame(2, $payload['deleted_rows']);
        $this->assertSame(0, DB::table('lw325_ph')->where('created_at', '2026-04-18 09:31:22')->whereNull('periode')->whereNull('kanca')->count());
        $this->assertSame(0, DB::table('lw325_ph')->where('created_at', '2026-04-18 09:31:22')->where('periode', '')->where('kanca', '')->count());
        $this->assertSame(1, DB::table('lw325_ph')->where('created_at', '2026-04-18 10:41:10')->whereNull('periode')->where('kanca', '')->count());
        $this->assertSame(1, DB::table('lw325_ph')->whereNull('periode')->where('kanca', 'KC Madiun')->count());
        $this->assertSame(1, DB::table('lw325_ph')->where('periode', '2026-04-30')->whereNull('kanca')->count());
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

    public function test_delete_management_matches_ssa_pinjaman_by_month_scope(): void
    {
        Schema::create('ssa_pinjaman', function (Blueprint $table) {
            $table->string('uniqueid_namareport')->primary();
            $table->string('month_day_year_of_periode')->nullable();
            $table->string('nama_cabang')->nullable();
            $table->string('nama_uker')->nullable();
        });

        DB::table('nama_report')->insert([
            'id_report' => 18,
            'nama_report' => 'SSA Pinjaman',
            'table_name' => 'ssa_pinjaman',
            'active' => 1,
        ]);

        DB::table('ssa_pinjaman')->insert([
            ['uniqueid_namareport' => 'SSA-P-1', 'month_day_year_of_periode' => '2026-03-01', 'nama_cabang' => '00045 -- KC Madiun (Konsolidasi-MB)', 'nama_uker' => 'Unit A'],
            ['uniqueid_namareport' => 'SSA-P-2', 'month_day_year_of_periode' => '2026-03-15', 'nama_cabang' => '00045 -- KC Madiun (Konsolidasi-MB)', 'nama_uker' => 'Unit B'],
            ['uniqueid_namareport' => 'SSA-P-3', 'month_day_year_of_periode' => '2026-03-31', 'nama_cabang' => '00049 -- KC Magetan (Konsolidasi-MB)', 'nama_uker' => 'Unit C'],
            ['uniqueid_namareport' => 'SSA-P-4', 'month_day_year_of_periode' => '2026-04-01', 'nama_cabang' => '00045 -- KC Madiun (Konsolidasi-MB)', 'nama_uker' => 'Unit D'],
        ]);

        $controller = app(ImportIndexController::class);
        $buildMethod = new \ReflectionMethod($controller, 'buildDeleteScopeQueryFromScopes');
        $buildMethod->setAccessible(true);
        [$scopeQuery, $hasWhereClause] = $buildMethod->invoke(
            $controller,
            'ssa_pinjaman',
            'month_day_year_of_periode',
            'nama_cabang',
            [[
                'period_filter' => '2026-03',
                'kanca_filter' => '00045 -- KC Madiun (Konsolidasi-MB)',
                'period_is_null' => false,
                'kanca_is_null' => false,
            ]]
        );

        $this->assertTrue($hasWhereClause);

        $deleteMethod = new \ReflectionMethod($controller, 'deleteScopedRows');
        $deleteMethod->setAccessible(true);
        $deletedRows = $deleteMethod->invoke(
            $controller,
            'ssa_pinjaman',
            $scopeQuery,
            'uniqueid_namareport',
            10000,
            'month_day_year_of_periode',
            'nama_cabang',
            [
                'period_filter' => '2026-03',
                'kanca_filter' => '00045 -- KC Madiun (Konsolidasi-MB)',
                'period_is_null' => false,
                'kanca_is_null' => false,
            ]
        );

        $this->assertSame(2, $deletedRows);
        $this->assertSame(2, DB::table('ssa_pinjaman')->count());
        $this->assertSame(0, DB::table('ssa_pinjaman')->where('month_day_year_of_periode', 'like', '2026-03%')->where('nama_cabang', '00045 -- KC Madiun (Konsolidasi-MB)')->count());
        $this->assertSame(1, DB::table('ssa_pinjaman')->where('month_day_year_of_periode', 'like', '2026-03%')->where('nama_cabang', '00049 -- KC Magetan (Konsolidasi-MB)')->count());
        $this->assertSame(1, DB::table('ssa_pinjaman')->where('month_day_year_of_periode', 'like', '2026-04%')->where('nama_cabang', '00045 -- KC Madiun (Konsolidasi-MB)')->count());
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
                    'kanca_filter' => 'Unit Sudirman',
                    'kanca_label' => 'Unit Sudirman',
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
        $this->assertSame(1, $payload['deleted_rows']);
        $this->assertSame(2, DB::table('user_brimo_rpt_v2')->where('mbdesc', 'Unit Ngrayun')->count() + DB::table('user_brimo_rpt_v2')->where('mbdesc', 'Unit Ponorogo')->count());
        $this->assertSame(0, DB::table('user_brimo_rpt_v2')->where('mbdesc', 'Unit Sudirman')->count());
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
                    'kanca_filter' => 'Unit Sudirman',
                    'kanca_label' => 'Unit Sudirman',
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
        $this->assertSame(1, $payload['deleted_rows']);
        $this->assertSame(2, DB::table('user_brimo_fin')->where('mbdesc', 'Unit Ngrayun')->count() + DB::table('user_brimo_fin')->where('mbdesc', 'Unit Ponorogo')->count());
        $this->assertSame(0, DB::table('user_brimo_fin')->where('mbdesc', 'Unit Sudirman')->count());
        Queue::assertNothingPushed();
    }

    public function test_delete_management_matches_date_period_columns_with_month_scope_filters(): void
    {
        Schema::create('gi405_rec_dh', function (Blueprint $table) {
            $table->string('uniqueid_namareport')->primary();
            $table->date('tanggal')->nullable();
            $table->string('kc_konsol')->nullable();
            $table->string('kode')->nullable();
        });

        DB::table('nama_report')->insert([
            'id_report' => 14,
            'nama_report' => 'GI405 REC DH',
            'table_name' => 'gi405_rec_dh',
            'active' => 1,
        ]);

        DB::table('gi405_rec_dh')->insert([
            ['uniqueid_namareport' => 'REC-1', 'tanggal' => '2026-04-04', 'kc_konsol' => 'KANWIL MALANG', 'kode' => '001'],
            ['uniqueid_namareport' => 'REC-2', 'tanggal' => '2026-04-17', 'kc_konsol' => 'KC Banyuwangi', 'kode' => '002'],
            ['uniqueid_namareport' => 'REC-3', 'tanggal' => '2026-05-01', 'kc_konsol' => 'KC Banyuwangi', 'kode' => '003'],
        ]);

        $syncService = \Mockery::mock(ReportDataSyncService::class);
        $syncService->shouldReceive('resolvePostDeleteMaintenanceMode')->with('gi405_rec_dh')->andReturn('lightweight');
        $syncService->shouldReceive('syncAfterDeleteLightweight')->once()->with('gi405_rec_dh', '2026-04', \Mockery::type('string'))->andReturnNull();
        app()->instance(ReportDataSyncService::class, $syncService);

        $controller = app(ImportIndexController::class);
        $request = Request::create('/import/report-management/delete', 'POST', [
            'id_report' => 14,
            'scopes' => [
                [
                    'period_filter' => '2026-04',
                    'period_label' => '2026-04',
                    'kanca_filter' => 'KC Banyuwangi',
                    'kanca_label' => 'KC Banyuwangi',
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
        $this->assertSame(1, $payload['deleted_rows']);
        $this->assertSame(0, DB::table('gi405_rec_dh')->where('tanggal', '2026-04-17')->where('kc_konsol', 'KC Banyuwangi')->count());
        $this->assertSame(1, DB::table('gi405_rec_dh')->where('tanggal', '2026-04-04')->where('kc_konsol', 'KANWIL MALANG')->count());
        $this->assertSame(1, DB::table('gi405_rec_dh')->where('tanggal', '2026-05-01')->where('kc_konsol', 'KC Banyuwangi')->count());
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
        $syncService->shouldReceive('cleanupDerivedArtifactsAfterDelete')
            ->once()
            ->with('daily_loan_dinamis', '2026-04-30', \Mockery::type('string'), $deleteId)
            ->andReturn([]);
        $syncService->shouldReceive('syncAfterDeleteLightweight')
            ->once()
            ->with('daily_loan_dinamis', '2026-04-30', \Mockery::type('string'), $deleteId)
            ->andReturnNull();

        $controller = app(ImportIndexController::class);
        $response = $controller->processManagedReportDelete($deleteId, $syncService);
        $payload = $response->getData(true);

        $this->assertSame('running', $payload['status']);
        $this->assertSame('syncing', $payload['stage']);
        $this->assertSame('cleanup', $payload['batch_state']);
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

    public function test_reconcile_managed_delete_state_marks_stale_state_failed_without_queue_row(): void
    {
        $deleteId = (string) \Illuminate\Support\Str::uuid();
        $controller = app(ImportIndexController::class);

        $method = new \ReflectionMethod($controller, 'reconcileManagedDeleteStateWithQueueRow');
        $method->setAccessible(true);

        $state = [
            'delete_id' => $deleteId,
            'status' => 'running',
            'stage' => 'deleting',
            'table_name' => 'daily_loan_dinamis',
            'deleted_rows' => 0,
            'period_hint' => '2026-04-30',
            'created_at' => now()->subMinutes(20)->toIso8601String(),
            'updated_at' => now()->subMinutes(20)->toIso8601String(),
        ];

        $resolved = $method->invoke($controller, $deleteId, $state, null);

        $this->assertIsArray($resolved);
        $this->assertSame('failed', $resolved['status']);
        $this->assertSame('failed', $resolved['stage']);
        $this->assertArrayHasKey('error', $resolved);
    }

    public function test_reconcile_managed_delete_state_with_missing_queue_row_keeps_recoverable_progress_alive(): void
    {
        $deleteId = (string) \Illuminate\Support\Str::uuid();
        $controller = app(ImportIndexController::class);

        $method = new \ReflectionMethod($controller, 'reconcileManagedDeleteStateWithQueueRow');
        $method->setAccessible(true);

        $state = [
            'delete_id' => $deleteId,
            'status' => 'running',
            'stage' => 'deleting',
            'batch_state' => 'deleting_committed',
            'table_name' => 'daily_loan_dinamis',
            'deleted_rows' => 20000,
            'total_rows' => 40000,
            'period_hint' => '2025-03-31',
            'message' => 'Batch selesai, melanjutkan penghapusan berikutnya.',
            'created_at' => now()->subMinutes(3)->toIso8601String(),
            'updated_at' => now()->subSeconds(45)->toIso8601String(),
        ];

        $resolved = $method->invoke($controller, $deleteId, $state, null);

        $this->assertIsArray($resolved);
        $this->assertSame('running', $resolved['status']);
        $this->assertSame('deleting', $resolved['stage']);
        $this->assertSame(20000, $resolved['deleted_rows']);
    }

    public function test_resolve_managed_report_delete_jobs_exposes_status_labels_without_crashing(): void
    {
        $deleteId = (string) \Illuminate\Support\Str::uuid();
        $controller = app(ImportIndexController::class);

        $putState = new \ReflectionMethod($controller, 'putDeleteState');
        $putState->setAccessible(true);
        $putState->invoke($controller, $deleteId, [
            'delete_id' => $deleteId,
            'status' => 'running',
            'stage' => 'deleting',
            'batch_state' => 'deleting_pending',
            'table_name' => 'daily_loan_dinamis',
            'deleted_rows' => 12,
            'total_rows' => 20,
            'remaining_rows' => 8,
            'progress_percent' => 60,
            'message' => 'Delete sedang diproses.',
            'created_at' => now()->subMinute()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ]);

        $jobs = $controller->resolveManagedReportDeleteJobs();
        $job = collect($jobs)->firstWhere('id', $deleteId);

        $this->assertIsArray($job);
        $this->assertSame('processing', $job['status']);
        $this->assertSame('Processing', $job['status_label']);
        $this->assertSame('info', $job['status_tone']);
    }

    public function test_force_stop_managed_delete_terminates_queue_only_job_when_progress_cache_is_missing(): void
    {
        $deleteId = '123e4567-e89b-12d3-a456-426614174111';

        DB::table('jobs')->insert([
            'queue' => 'imports-high',
            'reserved_at' => null,
            'available_at' => now()->subMinute()->timestamp,
            'created_at' => now()->subMinute()->timestamp,
            'payload' => 'a:2:{s:7:"jobClass";s:24:"RunManagedReportDeleteJob";s:8:"deleteId";s:36:"' . $deleteId . '";}',
        ]);

        Cache::store('file')->forget('report_management_delete:' . $deleteId);

        $controller = app(ImportIndexController::class);
        $response = $controller->forceStopManagedReportDelete($deleteId);
        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('warning', $payload['status']);
        $this->assertSame('cancelled', $payload['stage']);
        $this->assertSame('Delete dihentikan paksa dengan aman.', $payload['message']);
        $this->assertSame(0, DB::table('jobs')->count());

        $storedState = Cache::store('file')->get('report_management_delete:' . $deleteId);
        $this->assertIsArray($storedState);
        $this->assertSame('warning', $storedState['status']);
        $this->assertSame('cancelled', $storedState['stage']);

        $jobs = $controller->resolveManagedReportDeleteJobs();
        $this->assertNull(collect($jobs)->firstWhere('id', $deleteId));
    }

    public function test_classify_managed_delete_plan_keeps_normal_daily_loan_scope_on_plan_a(): void
    {
        $controller = app(ImportIndexController::class);
        $method = new \ReflectionMethod($controller, 'classifyManagedDeletePlan');
        $method->setAccessible(true);

        $result = $method->invoke($controller, [
            'table_name' => 'daily_loan_dinamis',
            'period_column' => 'periode',
            'kanca_column' => 'cabang1',
            'candidate_rows' => 261684,
            'scopes' => [[
                'period_filter' => '2025-03-31',
                'kanca_filter' => 'KC Madiun',
                'period_is_null' => false,
                'kanca_is_null' => false,
            ]],
        ]);

        $this->assertSame('normal', $result['delete_plan']);
        $this->assertNull($result['problem_signature']);
    }

    public function test_classify_managed_delete_plan_marks_large_blank_scope_as_plan_b(): void
    {
        $controller = app(ImportIndexController::class);
        $method = new \ReflectionMethod($controller, 'classifyManagedDeletePlan');
        $method->setAccessible(true);

        $result = $method->invoke($controller, [
            'table_name' => 'daily_loan_dinamis',
            'period_column' => 'periode',
            'kanca_column' => 'cabang1',
            'candidate_rows' => 261684,
            'scopes' => [[
                'period_filter' => '2025-03-31',
                'kanca_filter' => null,
                'period_is_null' => false,
                'kanca_is_null' => true,
            ]],
        ]);

        $this->assertSame('recovery_blank_scope', $result['delete_plan']);
        $this->assertSame('daily_loan_blank_kanca_large_scope', $result['problem_signature']);
    }

    public function test_delete_scoped_rows_uses_plan_b_recovery_service_for_large_blank_scope(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            ['uniqueid_namareport' => 'BLANK-1', 'periode' => '2025-03-31', 'cabang1' => null, 'payload' => 'row-1'],
            ['uniqueid_namareport' => 'BLANK-2', 'periode' => '2025-03-31', 'cabang1' => '', 'payload' => 'row-2'],
            ['uniqueid_namareport' => 'KEEP-1', 'periode' => '2025-03-31', 'cabang1' => 'KC Madiun', 'payload' => 'keep-1'],
            ['uniqueid_namareport' => 'KEEP-2', 'periode' => '2025-12-31', 'cabang1' => null, 'payload' => 'keep-2'],
        ]);

        $recoveryService = \Mockery::mock(ManagedReportDeleteRecoveryService::class);
        $recoveryService->shouldReceive('deleteBlankKancaPeriodScope')
            ->once()
            ->with(
                'daily_loan_dinamis',
                'periode',
                'cabang1',
                '2025-03-31',
                'uniqueid_namareport',
                10000,
                \Mockery::type('callable'),
                \Mockery::type('callable')
            )
            ->andReturn(['deleted_rows' => 2, 'batch_count' => 1]);
        app()->instance(ManagedReportDeleteRecoveryService::class, $recoveryService);

        $controller = app(ImportIndexController::class);
        $method = new \ReflectionMethod($controller, 'deleteScopedRows');
        $method->setAccessible(true);

        $baseQuery = DB::table('daily_loan_dinamis')->where('periode', '2025-03-31');
        $deleted = $method->invoke(
            $controller,
            'daily_loan_dinamis',
            $baseQuery,
            'uniqueid_namareport',
            10000,
            'periode',
            'cabang1',
            [
                'period_filter' => '2025-03-31',
                'kanca_filter' => null,
                'period_is_null' => false,
                'kanca_is_null' => true,
            ],
            'delete-plan-b-1',
            'recovery_blank_scope'
        );

        $this->assertSame(2, $deleted);
    }

    public function test_delete_blank_period_scope_with_specific_kanca_only_deletes_matching_blank_period_rows(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            ['uniqueid_namareport' => 'BLANK-MADIUN-1', 'periode' => null, 'cabang1' => 'KC Madiun', 'payload' => 'delete-1'],
            ['uniqueid_namareport' => 'BLANK-MADIUN-2', 'periode' => '', 'cabang1' => 'KC Madiun', 'payload' => 'delete-2'],
            ['uniqueid_namareport' => 'BLANK-PONO-1', 'periode' => null, 'cabang1' => 'KC Ponorogo', 'payload' => 'keep-1'],
            ['uniqueid_namareport' => 'FILLED-MADIUN-1', 'periode' => '2026-04-04', 'cabang1' => 'KC Madiun', 'payload' => 'keep-2'],
        ]);

        $service = app(ManagedReportDeleteRecoveryService::class);

        $result = $service->deleteBlankPeriodScope(
            'daily_loan_dinamis',
            'periode',
            'cabang1',
            [
                'period_is_null' => true,
                'kanca_is_null' => false,
                'kanca_filter' => 'KC Madiun',
            ],
            'uniqueid_namareport',
            1
        );

        $this->assertSame(2, $result['deleted_rows']);
        $this->assertGreaterThanOrEqual(2, $result['batch_count']);
        $this->assertSame(0, DB::table('daily_loan_dinamis')->whereNull('periode')->where('cabang1', 'KC Madiun')->count());
        $this->assertSame(0, DB::table('daily_loan_dinamis')->where('periode', '')->where('cabang1', 'KC Madiun')->count());
        $this->assertSame(1, DB::table('daily_loan_dinamis')->whereNull('periode')->where('cabang1', 'KC Ponorogo')->count());
        $this->assertSame(1, DB::table('daily_loan_dinamis')->where('periode', '2026-04-04')->where('cabang1', 'KC Madiun')->count());
    }

    public function test_delete_blank_period_scope_with_blank_kanca_only_deletes_blank_intersection(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            ['uniqueid_namareport' => 'BLANK-BLANK-1', 'periode' => null, 'cabang1' => null, 'payload' => 'delete-1'],
            ['uniqueid_namareport' => 'BLANK-BLANK-2', 'periode' => '', 'cabang1' => '', 'payload' => 'delete-2'],
            ['uniqueid_namareport' => 'BLANK-MADIUN-1', 'periode' => null, 'cabang1' => 'KC Madiun', 'payload' => 'keep-1'],
            ['uniqueid_namareport' => 'FILLED-BLANK-1', 'periode' => '2026-04-04', 'cabang1' => null, 'payload' => 'keep-2'],
        ]);

        $service = app(ManagedReportDeleteRecoveryService::class);

        $result = $service->deleteBlankPeriodScope(
            'daily_loan_dinamis',
            'periode',
            'cabang1',
            [
                'period_is_null' => true,
                'kanca_is_null' => true,
            ],
            'uniqueid_namareport',
            10
        );

        $this->assertSame(2, $result['deleted_rows']);
        $this->assertSame(0, DB::table('daily_loan_dinamis')->whereNull('periode')->whereNull('cabang1')->count());
        $this->assertSame(0, DB::table('daily_loan_dinamis')->where('periode', '')->where('cabang1', '')->count());
        $this->assertSame(1, DB::table('daily_loan_dinamis')->whereNull('periode')->where('cabang1', 'KC Madiun')->count());
        $this->assertSame(1, DB::table('daily_loan_dinamis')->where('periode', '2026-04-04')->whereNull('cabang1')->count());
    }

    public function test_delete_blank_kanca_period_scope_only_deletes_target_period(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            ['uniqueid_namareport' => 'TARGET-1', 'periode' => '2026-04-04', 'cabang1' => null, 'payload' => 'delete-1'],
            ['uniqueid_namareport' => 'TARGET-2', 'periode' => '2026-04-04', 'cabang1' => '', 'payload' => 'delete-2'],
            ['uniqueid_namareport' => 'OTHER-PERIOD-1', 'periode' => '2026-04-05', 'cabang1' => null, 'payload' => 'keep-1'],
            ['uniqueid_namareport' => 'TARGET-FILLED-1', 'periode' => '2026-04-04', 'cabang1' => 'KC Madiun', 'payload' => 'keep-2'],
        ]);

        $service = app(ManagedReportDeleteRecoveryService::class);

        $result = $service->deleteBlankKancaPeriodScope(
            'daily_loan_dinamis',
            'periode',
            'cabang1',
            '2026-04-04',
            'uniqueid_namareport',
            1
        );

        $this->assertSame(2, $result['deleted_rows']);
        $this->assertGreaterThanOrEqual(2, $result['batch_count']);
        $this->assertSame(0, DB::table('daily_loan_dinamis')->where('periode', '2026-04-04')->whereNull('cabang1')->count());
        $this->assertSame(0, DB::table('daily_loan_dinamis')->where('periode', '2026-04-04')->where('cabang1', '')->count());
        $this->assertSame(1, DB::table('daily_loan_dinamis')->where('periode', '2026-04-05')->whereNull('cabang1')->count());
        $this->assertSame(1, DB::table('daily_loan_dinamis')->where('periode', '2026-04-04')->where('cabang1', 'KC Madiun')->count());
    }
}

