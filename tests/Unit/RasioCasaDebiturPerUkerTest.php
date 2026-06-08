<?php

namespace Tests\Unit;

use App\Http\Controllers\RasioCasaDebiturController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class RasioCasaDebiturPerUkerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');

        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Cache::flush();

        Schema::dropAllTables();

        Schema::create('daily_loan_dinamis', function (Blueprint $table): void {
            $table->date('periode')->nullable();
            $table->string('cabang1')->nullable();
            $table->string('unit1')->nullable();
            $table->string('cifno')->nullable();
            $table->decimal('baki_debet1', 24, 2)->nullable();
            $table->string('segmen_dashboard')->nullable();
            $table->string('produk_dashboard')->nullable();
        });

        Schema::create('simpanan_multipn', function (Blueprint $table): void {
            $table->date('posisi')->nullable();
            $table->string('CIFNO')->nullable();
            $table->string('jenis_simpanan')->nullable();
            $table->decimal('saldo_idr', 24, 2)->nullable();
        });

        DB::table('daily_loan_dinamis')->insert([
            [
                'periode' => '2026-05-31',
                'cabang1' => 'MADIUN',
                'unit1' => 'UNIT A',
                'cifno' => 'CIF001',
                'baki_debet1' => 1000,
                'segmen_dashboard' => 'Consumer',
                'produk_dashboard' => 'Briguna',
            ],
            [
                'periode' => '2026-05-31',
                'cabang1' => 'MADIUN',
                'unit1' => 'UNIT B',
                'cifno' => 'CIF002',
                'baki_debet1' => 2000,
                'segmen_dashboard' => 'Micro',
                'produk_dashboard' => 'Kupedes',
            ],
            [
                'periode' => '2026-05-31',
                'cabang1' => 'SURABAYA',
                'unit1' => 'UNIT OUTSIDE',
                'cifno' => 'CIF999',
                'baki_debet1' => 9000,
                'segmen_dashboard' => 'Consumer',
                'produk_dashboard' => 'Briguna',
            ],
        ]);

        DB::table('simpanan_multipn')->insert([
            [
                'posisi' => '2026-05-31',
                'CIFNO' => 'CIF001',
                'jenis_simpanan' => 'TABUNGAN',
                'saldo_idr' => 100,
            ],
            [
                'posisi' => '2026-05-31',
                'CIFNO' => 'CIF002',
                'jenis_simpanan' => 'GIRO',
                'saldo_idr' => 400,
            ],
            [
                'posisi' => '2026-05-31',
                'CIFNO' => 'CIF999',
                'jenis_simpanan' => 'TABUNGAN',
                'saldo_idr' => 900,
            ],
        ]);
    }

    public function test_unit_submenu_only_exposes_managed_area_6_branches(): void
    {
        $view = (new RasioCasaDebiturController())->index();
        $unitBranchOptions = $view->getData()['unitBranchOptions'];

        $this->assertSame(['MADIUN'], $unitBranchOptions->all());
    }

    public function test_branch_scope_groups_the_same_casa_logic_per_unit_kerja(): void
    {
        $snapshot = $this->computeFilteredSnapshot('2026-05-31', ['MADIUN']);

        $this->assertSame(2, $snapshot['row_count']);
        $this->assertArrayHasKey('UNIT A', $snapshot['os']);
        $this->assertArrayHasKey('UNIT B', $snapshot['os']);
        $this->assertArrayNotHasKey('UNIT OUTSIDE', $snapshot['os']);

        $this->assertEqualsWithDelta(1000.0, $snapshot['os']['UNIT A']['total'], 0.001);
        $this->assertEqualsWithDelta(1000.0, $snapshot['os']['UNIT A']['briguna'], 0.001);
        $this->assertEqualsWithDelta(100.0, $snapshot['casa']['UNIT A']['total'], 0.001);
        $this->assertEqualsWithDelta(100.0, $snapshot['casa']['UNIT A']['briguna'], 0.001);

        $this->assertEqualsWithDelta(2000.0, $snapshot['os']['UNIT B']['total'], 0.001);
        $this->assertEqualsWithDelta(2000.0, $snapshot['os']['UNIT B']['mikro'], 0.001);
        $this->assertEqualsWithDelta(400.0, $snapshot['casa']['UNIT B']['total'], 0.001);
        $this->assertEqualsWithDelta(400.0, $snapshot['casa']['UNIT B']['mikro'], 0.001);
    }

    public function test_view_wires_unit_submenu_to_existing_casa_endpoint(): void
    {
        $blade = file_get_contents(resource_path('views/report/Rasiocasadebitur.blade.php'));

        $this->assertStringContainsString('id="tab-per-uker"', $blade);
        $this->assertStringContainsString('id="filter_cabang1_uker"', $blade);
        $this->assertStringContainsString("branch_office: [selectedCabang]", $blade);
        $this->assertStringContainsString("nama_uker: []", $blade);
        $this->assertStringContainsString("route('report.data.rasiocasa')", $blade);
    }

    private function computeFilteredSnapshot(string $period, array $branches): array
    {
        $controller = new RasioCasaDebiturController();
        $method = new ReflectionMethod(RasioCasaDebiturController::class, 'computeFilteredSummarySnapshot');
        $method->setAccessible(true);

        return $method->invoke($controller, $period, $branches, []);
    }
}
