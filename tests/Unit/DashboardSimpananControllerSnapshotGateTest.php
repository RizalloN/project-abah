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

    public function test_controller_dispatches_partial_snapshot_job_before_all_area_branches_exist(): void
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
        Bus::assertDispatched(EnsureDashboardSimpananSnapshotJob::class);
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

    public function test_qris_performance_card_uses_qris_detail_table_without_legacy_tables(): void
    {
        Schema::create('jumlah_merchant_qris_detail', function (Blueprint $table) {
            $table->date('POSISI')->index();
            $table->string('MBDESC')->nullable()->index();
            $table->string('STOREID')->nullable();
            $table->string('AKUMULASI_SV_TOTAL')->nullable();
        });

        DB::table('jumlah_merchant_qris_detail')->insert([
            [
                'POSISI' => '2026-03-31',
                'MBDESC' => 'KC MADIUN',
                'STOREID' => 'STORE-OLD',
                'AKUMULASI_SV_TOTAL' => '50000',
            ],
            [
                'POSISI' => '2026-04-30',
                'MBDESC' => 'KC MADIUN',
                'STOREID' => 'STORE-001',
                'AKUMULASI_SV_TOTAL' => '100000',
            ],
            [
                'POSISI' => '2026-04-30',
                'MBDESC' => 'KC MAGETAN',
                'STOREID' => 'STORE-002',
                'AKUMULASI_SV_TOTAL' => '0',
            ],
            [
                'POSISI' => '2026-04-30',
                'MBDESC' => 'KC LUAR AREA',
                'STOREID' => 'STORE-003',
                'AKUMULASI_SV_TOTAL' => '999999',
            ],
        ]);

        $controller = new DashboardSimpananController();
        $method = new ReflectionMethod(DashboardSimpananController::class, 'buildQrisPerformanceCard');
        $method->setAccessible(true);

        $card = $method->invoke($controller);

        $this->assertFalse(Schema::hasTable('merchant_qris'));
        $this->assertFalse(Schema::hasTable('merchant_qris_volume'));
        $this->assertSame('qris', $card['key']);
        $this->assertSame('Sales Volume', $card['current_label']);
        $this->assertSame('Rp100.000', $card['current_value']);
        $this->assertSame('2', $card['secondary_value']);
        $this->assertSame('Merchant Tercatat', $card['secondary_label']);
        $this->assertSame('Merchant Produktif', $card['stats'][0]['label']);
        $this->assertSame('1', $card['stats'][0]['value']);
        $this->assertSame(100000.0, $card['series'][array_key_last($card['series'])]);
    }
}
