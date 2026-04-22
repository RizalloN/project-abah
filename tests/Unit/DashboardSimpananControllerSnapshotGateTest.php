<?php

namespace Tests\Unit;

use App\Http\Controllers\DashboardSimpananController;
use App\Jobs\EnsureDashboardSimpananSnapshotJob;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class DashboardSimpananControllerSnapshotGateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::dropAllTables();
        Cache::flush();

        Schema::create('dashboard_simpanan_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('snapshot_period')->index();
        });

        Schema::create('simpanan_multipn', function (Blueprint $table) {
            $table->id();
            $table->date('posisi')->index();
            $table->string('kantor_cabang')->nullable()->index();
        });
    }

    public function test_controller_does_not_dispatch_snapshot_job_before_all_area_branches_exist(): void
    {
        Bus::fake();

        DB::table('simpanan_multipn')->insert([
            ['posisi' => '2026-04-30', 'kantor_cabang' => 'KC Madiun'],
            ['posisi' => '2026-04-30', 'kantor_cabang' => 'KC Magetan'],
            ['posisi' => '2026-04-30', 'kantor_cabang' => 'KC Ngawi'],
        ]);

        $controller = new DashboardSimpananController();
        $method = new ReflectionMethod(DashboardSimpananController::class, 'hasSimpananSnapshot');
        $method->setAccessible(true);

        $result = $method->invoke($controller, '2026-04-30');

        $this->assertFalse($result);
        Bus::assertNotDispatched(EnsureDashboardSimpananSnapshotJob::class);
    }

    public function test_controller_dispatches_snapshot_job_once_area_branches_are_complete(): void
    {
        Bus::fake();

        DB::table('simpanan_multipn')->insert([
            ['posisi' => '2026-04-30', 'kantor_cabang' => 'KC Madiun'],
            ['posisi' => '2026-04-30', 'kantor_cabang' => 'KC Magetan'],
            ['posisi' => '2026-04-30', 'kantor_cabang' => 'KC Ngawi'],
            ['posisi' => '2026-04-30', 'kantor_cabang' => 'KC Ponorogo'],
        ]);

        $controller = new DashboardSimpananController();
        $method = new ReflectionMethod(DashboardSimpananController::class, 'hasSimpananSnapshot');
        $method->setAccessible(true);

        $result = $method->invoke($controller, '2026-04-30');

        $this->assertFalse($result);
        Bus::assertDispatched(EnsureDashboardSimpananSnapshotJob::class);
    }
}
