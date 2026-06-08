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

class RasioCasaDebiturPerRmAllUnitTest extends TestCase
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

        Schema::create('daily_loan_dinamis', function (Blueprint $table) {
            $table->date('periode')->nullable();
            $table->string('cabang1')->nullable();
            $table->string('unit1')->nullable();
            $table->string('pn_pengelola1')->nullable();
            $table->string('cifno')->nullable();
            $table->decimal('baki_debet1', 24, 2)->nullable();
            $table->string('segmen_dashboard')->nullable();
            $table->string('produk_dashboard')->nullable();
        });

        Schema::create('simpanan_multipn', function (Blueprint $table) {
            $table->date('posisi')->nullable();
            $table->string('CIFNO')->nullable();
            $table->string('kantor_cabang')->nullable();
            $table->string('unit_kerja')->nullable();
            $table->string('jenis_simpanan')->nullable();
            $table->decimal('saldo_idr', 24, 2)->nullable();
        });

        DB::table('daily_loan_dinamis')->insert([
            [
                'periode' => '2026-05-31',
                'cabang1' => 'MADIUN',
                'unit1' => 'UNIT A',
                'pn_pengelola1' => 'RM ALPHA',
                'cifno' => 'CIF001',
                'baki_debet1' => 1000,
                'segmen_dashboard' => 'Consumer',
                'produk_dashboard' => 'Briguna',
            ],
            [
                'periode' => '2026-05-31',
                'cabang1' => 'MADIUN',
                'unit1' => 'UNIT B',
                'pn_pengelola1' => 'RM BETA',
                'cifno' => 'CIF002',
                'baki_debet1' => 2000,
                'segmen_dashboard' => 'Micro',
                'produk_dashboard' => 'Kupedes',
            ],
            [
                'periode' => '2026-05-31',
                'cabang1' => 'MADIUN',
                'unit1' => 'UNIT B',
                'pn_pengelola1' => 'RM ALPHA',
                'cifno' => 'CIF003',
                'baki_debet1' => 500,
                'segmen_dashboard' => 'Consumer',
                'produk_dashboard' => 'KPR',
            ],
            [
                'periode' => '2026-05-31',
                'cabang1' => 'MAGETAN',
                'unit1' => 'UNIT X',
                'pn_pengelola1' => 'RM OUTSIDE',
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
                'kantor_cabang' => 'KC MADIUN',
                'unit_kerja' => 'UNIT A',
                'jenis_simpanan' => 'TABUNGAN',
                'saldo_idr' => 100,
            ],
            [
                'posisi' => '2026-05-31',
                'CIFNO' => 'CIF002',
                'kantor_cabang' => 'KC MADIUN',
                'unit_kerja' => 'UNIT B',
                'jenis_simpanan' => 'GIRO',
                'saldo_idr' => 400,
            ],
            [
                'posisi' => '2026-05-31',
                'CIFNO' => 'CIF003',
                'kantor_cabang' => 'KC MADIUN',
                'unit_kerja' => 'UNIT B',
                'jenis_simpanan' => 'TABUNGAN',
                'saldo_idr' => 50,
            ],
            [
                'posisi' => '2026-05-31',
                'CIFNO' => 'CIF999',
                'kantor_cabang' => 'KC MAGETAN',
                'unit_kerja' => 'UNIT X',
                'jenis_simpanan' => 'TABUNGAN',
                'saldo_idr' => 900,
            ],
        ]);
    }

    public function test_all_unit_kerja_scope_combines_rm_rows_from_every_unit_in_selected_branch(): void
    {
        $snapshot = $this->buildRmSnapshot('2026-05-31', 'MADIUN', 'ALL UKER');

        $this->assertSame(3, $snapshot['row_count']);
        $this->assertArrayHasKey('RM ALPHA', $snapshot['os']);
        $this->assertArrayHasKey('RM BETA', $snapshot['os']);
        $this->assertArrayNotHasKey('RM OUTSIDE', $snapshot['os']);
        $this->assertEqualsWithDelta(1500.0, $snapshot['os']['RM ALPHA']['total'], 0.001);
        $this->assertEqualsWithDelta(2000.0, $snapshot['os']['RM BETA']['total'], 0.001);
        $this->assertEqualsWithDelta(150.0, $snapshot['casa']['RM ALPHA']['total'], 0.001);
        $this->assertEqualsWithDelta(400.0, $snapshot['casa']['RM BETA']['total'], 0.001);
    }

    public function test_specific_unit_kerja_scope_keeps_existing_unit_filter_behavior(): void
    {
        $snapshot = $this->buildRmSnapshot('2026-05-31', 'MADIUN', 'UNIT A');

        $this->assertSame(1, $snapshot['row_count']);
        $this->assertArrayHasKey('RM ALPHA', $snapshot['os']);
        $this->assertArrayNotHasKey('RM BETA', $snapshot['os']);
        $this->assertEqualsWithDelta(1000.0, $snapshot['os']['RM ALPHA']['total'], 0.001);
        $this->assertEqualsWithDelta(100.0, $snapshot['casa']['RM ALPHA']['total'], 0.001);
    }

    private function buildRmSnapshot(string $period, string $branch, string $unit): array
    {
        $controller = new RasioCasaDebiturController();
        $method = new ReflectionMethod(RasioCasaDebiturController::class, 'buildRmSnapshot');
        $method->setAccessible(true);

        return $method->invoke($controller, $period, $branch, $unit);
    }
}
