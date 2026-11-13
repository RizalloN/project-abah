<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportIndexController;
use App\Services\Import\EvaluasiKunjunganSourceService;
use App\Support\ReportDataSyncService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

class EvaluasiKunjunganDeleteTest extends TestCase
{
    private int $reportId;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Cache::flush();
        Queue::fake();

        Schema::create('nama_report', function (Blueprint $table): void {
            $table->integer('id_report')->primary();
            $table->string('nama_report');
            $table->string('table_name');
            $table->boolean('active');
            $table->string('import_controller')->nullable();
            $table->boolean('requires_manual_periode')->default(false);
            $table->timestamps();
        });
        Schema::create('import_jobs', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('id_report');
            $table->string('status');
            $table->timestamps();
        });
        Schema::create('jobs', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('queue');
            $table->integer('reserved_at')->nullable();
            $table->integer('available_at')->nullable();
            $table->integer('created_at')->nullable();
            $table->longText('payload');
        });
        (require database_path('migrations/2026_11_12_000001_create_evaluasi_aktivitas_kunjungan_pemasar_report.php'))->up();
        (require database_path('migrations/2026_11_12_000002_add_printed_period_to_evaluasi_kunjungan_pemasar.php'))->up();
        $this->reportId = (int) DB::table('nama_report')->where('table_name', EvaluasiKunjunganSourceService::TABLE)->value('id_report');

        $sync = Mockery::mock(ReportDataSyncService::class);
        $sync->shouldReceive('resolvePostDeleteMaintenanceMode')->with(EvaluasiKunjunganSourceService::TABLE)->andReturn('lightweight');
        $sync->shouldReceive('syncAfterDeleteLightweight')->andReturnNull();
        app()->instance(ReportDataSyncService::class, $sync);
    }

    public function test_management_groups_by_printed_period_despite_different_visit_dates_with_branch_name_label(): void
    {
        $this->seedRows();
        $response = app(ImportIndexController::class)->reportManagementData(Request::create('/import/report-management/data', 'POST', [
            'id_report' => $this->reportId,
        ]));
        $payload = $response->getData(true);

        $this->assertSame(200, $response->status());
        $this->assertSame('periode', $payload['period_column']);
        $this->assertSame('kode_main_branch', $payload['kanca_column']);
        $this->assertSame(4, $payload['total_groups']);
        $madiun = collect($payload['rows'])->first(fn (array $row): bool => $row['period'] === '2026-11-12' && $row['kanca'] === '0045');
        $this->assertSame('KC Madiun', $madiun['kanca_label']);
        $this->assertSame(2, $madiun['row_count']);
        $this->assertSame(['2026-01-05', '2026-10-23'], DB::table(EvaluasiKunjunganSourceService::TABLE)->where('periode', '2026-11-12')->where('kode_main_branch', '0045')->orderBy('id_aktivitas')->pluck('tgl_kunjungan')->all());
    }

    public function test_one_printed_scope_delete_preserves_other_periods_branches_and_original_survivor_values(): void
    {
        $this->seedRows();
        $survivors = DB::table(EvaluasiKunjunganSourceService::TABLE)->whereIn('id_aktivitas', ['3', '4', '5'])->orderBy('id_aktivitas')->get()->toArray();

        $response = $this->deleteScopes([['period' => '2026-11-12', 'kanca' => '0045']]);

        $this->assertSame('completed', $response->getData(true)['status']);
        $this->assertSame(2, $response->getData(true)['deleted_rows']);
        $this->assertEquals($survivors, DB::table(EvaluasiKunjunganSourceService::TABLE)->orderBy('id_aktivitas')->get()->toArray());
        Queue::assertNothingPushed();
    }

    public function test_multiple_selected_scopes_delete_only_their_union_and_allow_reimport_of_deleted_activity_id(): void
    {
        $this->seedRows();
        $original = (array) DB::table(EvaluasiKunjunganSourceService::TABLE)->where('id_aktivitas', '1')->first();
        $response = $this->deleteScopes([
            ['period' => '2026-11-12', 'kanca' => '0045'],
            ['period' => '2026-11-11', 'kanca' => '0070'],
        ]);

        $this->assertSame('completed', $response->getData(true)['status']);
        $this->assertSame(3, $response->getData(true)['deleted_rows']);
        $this->assertSame(['3', '5'], DB::table(EvaluasiKunjunganSourceService::TABLE)->orderBy('id_aktivitas')->pluck('id_aktivitas')->all());
        DB::table(EvaluasiKunjunganSourceService::TABLE)->insert($original);
        $this->assertEquals($original, (array) DB::table(EvaluasiKunjunganSourceService::TABLE)->where('id_aktivitas', '1')->first());
    }

    public function test_month_scope_uses_date_range_and_preserves_rows_outside_the_month(): void
    {
        $this->seedRows();
        DB::table(EvaluasiKunjunganSourceService::TABLE)->insert($this->row('6', '2026-10-31', '0045', 'KC Madiun'));
        $response = $this->deleteScopes([['period' => '2026-11', 'kanca' => '0045']]);

        $this->assertSame('completed', $response->getData(true)['status']);
        $this->assertSame(3, $response->getData(true)['deleted_rows']);
        $this->assertSame(['3', '4', '6'], DB::table(EvaluasiKunjunganSourceService::TABLE)->orderBy('id_aktivitas')->pluck('id_aktivitas')->all());
    }

    public function test_all_selected_rows_keep_existing_final_confirmation_guard(): void
    {
        $this->seedRows();
        $scopes = [['period' => '2026-11']];
        $warning = $this->deleteScopes($scopes, false)->getData(true);
        $this->assertTrue($warning['requires_hard_force']);
        $this->assertSame(5, DB::table(EvaluasiKunjunganSourceService::TABLE)->count());

        $finished = $this->deleteScopes($scopes)->getData(true);
        $this->assertSame('completed', $finished['status']);
        $this->assertSame(5, $finished['deleted_rows']);
        $this->assertSame(0, DB::table(EvaluasiKunjunganSourceService::TABLE)->count());
    }

    #[DataProvider('invalidScopes')]
    public function test_invalid_scope_fails_closed_without_removing_any_rows(array $scopes): void
    {
        $this->seedRows();
        $response = $this->deleteScopes($scopes);
        $this->assertSame(422, $response->status());
        $this->assertSame(5, DB::table(EvaluasiKunjunganSourceService::TABLE)->count());
    }

    public static function invalidScopes(): array
    {
        return [
            'missing dimensions' => [[[]]],
            'branch without printed period' => [[['kanca' => '0045']]],
            'invalid day' => [[['period' => '2026-02-30', 'kanca' => '0045']]],
            'invalid month' => [[['period' => '2026-13', 'kanca' => '0045']]],
            'text period' => [[['period' => 'not-a-date', 'kanca' => '0045']]],
            'mixed valid and invalid scopes' => [[['period' => '2026-11-12', 'kanca' => '0045'], ['period' => 'not-a-date', 'kanca' => '0070']]],
        ];
    }

    #[DataProvider('activeStatuses')]
    public function test_same_report_active_import_blocks_delete_before_any_mutation(string $status): void
    {
        $this->seedRows();
        DB::table('import_jobs')->insert(['id_report' => $this->reportId, 'status' => $status]);

        $response = $this->deleteScopes([['period' => '2026-11-12', 'kanca' => '0045']]);

        $this->assertSame(409, $response->status());
        $this->assertSame(5, DB::table(EvaluasiKunjunganSourceService::TABLE)->count());
        Queue::assertNothingPushed();
    }

    public static function activeStatuses(): array
    {
        return [['queued'], ['staging'], ['processing']];
    }

    public function test_other_report_active_import_and_completed_own_import_do_not_block_scoped_delete(): void
    {
        $this->seedRows();
        DB::table('import_jobs')->insert([
            ['id_report' => $this->reportId + 1, 'status' => 'processing'],
            ['id_report' => $this->reportId, 'status' => 'completed'],
        ]);

        $payload = $this->deleteScopes([['period' => '2026-11-12', 'kanca' => '0045']])->getData(true);

        $this->assertSame('completed', $payload['status']);
        $this->assertSame(2, $payload['deleted_rows']);
    }

    public function test_cancelled_delete_batch_does_not_mutate_report_data(): void
    {
        $this->seedRows();
        $controller = app(ImportIndexController::class);
        (new ReflectionMethod($controller, 'putDeleteState'))->invoke($controller, 'cancelled-kunjungan', [
            'delete_id' => 'cancelled-kunjungan', 'table_name' => EvaluasiKunjunganSourceService::TABLE,
            'status' => 'cancelled', 'stage' => 'cancelled', 'cancel_requested' => true,
        ]);
        $deleted = (new ReflectionMethod($controller, 'deleteScopedRows'))->invoke(
            $controller, EvaluasiKunjunganSourceService::TABLE,
            DB::table(EvaluasiKunjunganSourceService::TABLE)->where('periode', '2026-11-12'),
            'uniqueid_namareport', 2, 'periode', 'kode_main_branch',
            ['period_filter' => '2026-11-12', 'kanca_filter' => '0045'], 'cancelled-kunjungan',
        );
        $this->assertSame(0, $deleted);
        $this->assertSame(5, DB::table(EvaluasiKunjunganSourceService::TABLE)->count());
    }

    public function test_import_that_starts_after_delete_preparation_is_rechecked_before_batch_mutation(): void
    {
        $this->seedRows();
        $controller = app(ImportIndexController::class);
        $request = Request::create('/import/report-management/delete', 'POST', [
            'id_report' => $this->reportId,
            'scopes' => [['period' => '2026-11-12', 'kanca' => '0045']],
            'force' => true, 'hard_force' => true,
        ]);
        [$prepared, $error] = (new ReflectionMethod($controller, 'prepareManagedDelete'))->invoke($controller, $request);
        $this->assertNull($error);
        $this->assertSame(2, $prepared['candidate_rows']);
        DB::table('import_jobs')->insert(['id_report' => $this->reportId, 'status' => 'processing']);

        try {
            (new ReflectionMethod($controller, 'deleteScopedRows'))->invoke(
                $controller, EvaluasiKunjunganSourceService::TABLE,
                DB::table(EvaluasiKunjunganSourceService::TABLE)->where('periode', '2026-11-12')->where('kode_main_branch', '0045'),
                'uniqueid_namareport', 2, 'periode', 'kode_main_branch',
                ['period_filter' => '2026-11-12', 'kanca_filter' => '0045'],
            );
            $this->fail('An import that started after preparation must stop deletion.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('import', strtolower($error->getMessage()));
        }
        $this->assertSame(5, DB::table(EvaluasiKunjunganSourceService::TABLE)->count());
    }

    public function test_month_delete_sql_preserves_half_open_range_and_exact_code_bindings(): void
    {
        $controller = app(ImportIndexController::class);
        $variants = (new ReflectionMethod($controller, 'buildDeleteConstraintVariants'))->invoke(
            $controller, EvaluasiKunjunganSourceService::TABLE, 'periode', 'kode_main_branch',
            ['period_filter' => '2026-12', 'kanca_filter' => '0045'],
        );
        $this->assertCount(1, $variants);
        [$sql, $bindings] = (new ReflectionMethod($controller, 'buildDeleteWhereSql'))->invoke($controller, $variants[0], 'target');

        $this->assertSame(['2026-12-01', '2027-01-01', '0045'], $bindings);
        $this->assertStringContainsString('`target`.`periode` >= ?', $sql);
        $this->assertStringContainsString('`target`.`periode` < ?', $sql);
        $this->assertStringContainsString('`target`.`kode_main_branch` = ?', $sql);
        $this->assertStringNotContainsString('LIKE', strtoupper($sql));
    }

    public function test_printed_period_and_month_predicates_preserve_indexable_sql(): void
    {
        $controller = app(ImportIndexController::class);
        $method = new ReflectionMethod($controller, 'buildDeleteScopeQuery');
        [$day] = $method->invoke($controller, EvaluasiKunjunganSourceService::TABLE, 'periode', 'kode_main_branch', '12-11-2026', '0045', false, false);
        [$month] = $method->invoke($controller, EvaluasiKunjunganSourceService::TABLE, 'periode', 'kode_main_branch', '2026-11', '0045', false, false);

        $this->assertSame(['2026-11-12', '0045'], $day->getBindings());
        $this->assertSame(['2026-11-01', '2026-12-01', '0045'], $month->getBindings());
        foreach ([$day, $month] as $query) {
            $sql = strtoupper($query->toSql());
            $this->assertStringNotContainsString('DATE(', $sql);
            $this->assertStringNotContainsString('SUBSTR(', $sql);
            $this->assertStringNotContainsString('CAST(', $sql);
        }
        $hints = (new ReflectionClass($controller))->getConstant('DELETE_INDEX_HINTS');
        $this->assertSame('idx_kunjungan_period_branch', $hints[EvaluasiKunjunganSourceService::TABLE]['index']);
        $this->assertSame('kode_main_branch', $hints[EvaluasiKunjunganSourceService::TABLE]['kanca']);
    }

    public function test_delete_and_control_routes_keep_superadmin_authorization(): void
    {
        $routes = app('router')->getRoutes();
        foreach (['import.report-management.delete', 'import.report-management.delete.process', 'import.report-management.delete.status', 'import.report-management.delete.cancel', 'import.report-management.delete.force-stop'] as $name) {
            $this->assertContains('role:superadmin', $routes->getByName($name)->gatherMiddleware());
        }
    }

    private function deleteScopes(array $scopes, bool $hardForce = true)
    {
        return app(ImportIndexController::class)->deleteManagedReportRows(Request::create('/import/report-management/delete', 'POST', [
            'id_report' => $this->reportId, 'scopes' => $scopes, 'force' => true, 'hard_force' => $hardForce,
        ]));
    }

    private function seedRows(): void
    {
        DB::table(EvaluasiKunjunganSourceService::TABLE)->insert([
            $this->row('1', '2026-11-12', '0045', 'KC Madiun'),
            $this->row('2', '2026-11-12', '0045', 'KC Madiun'),
            $this->row('3', '2026-11-12', '0070', 'KC Ponorogo'),
            $this->row('4', '2026-11-11', '0070', 'KC Ponorogo'),
            $this->row('5', '2026-11-11', '0045', 'KC Madiun'),
        ]);
    }

    private function row(string $id, string $date, string $code, string $name): array
    {
        $row = array_fill_keys(EvaluasiKunjunganSourceService::COLUMNS, 'contoh');

        return [...$row, 'uniqueid_namareport' => 'kunjungan_'.$id, 'nomor_sumber' => $id, 'id_aktivitas' => $id,
            'periode' => $date, 'date_printed' => $date.' 09:16:45',
            'tgl_kunjungan' => $id === '1' ? '2026-01-05' : '2026-10-23', 'kode_main_branch' => $code, 'main_branch' => $name,
            'latitude' => '-7.123456', 'longitude' => '111.123456', 'perkiraan_plafond' => '123.45', 'pn' => '00144675'];
    }
}
