<?php

namespace Tests\Unit;

use App\Support\ManagedReportManagementService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ImportDataFixtureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        Config::set('cache.default', 'array');

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::dropAllTables();
    }

    protected function tearDown(): void
    {
        Schema::dropAllTables();

        parent::tearDown();
    }

    public function test_daily_loan_dinamis_fixture_inserts_and_resolves_scope(): void
    {
        Schema::create('daily_loan_dinamis', function (Blueprint $table) {
            $table->string('uniqueid_namareport')->primary();
            $table->date('periode');
            $table->string('kode_cabang1')->nullable();
            $table->string('cabang1');
            $table->decimal('baki_debet1', 18, 2)->nullable();
            $table->string('nama_debitur1')->nullable();
        });

        $filled = [];
        for ($i = 1; $i <= 5; $i++) {
            $filled[] = [
                'uniqueid_namareport' => "DL-FULL-{$i}",
                'periode' => '2026-04-30',
                'kode_cabang1' => "649{$i}",
                'cabang1' => 'KC Madiun',
                'baki_debet1' => 15000000.00 * $i,
                'nama_debitur1' => "Debitur {$i}",
            ];
        }

        $nullable = [];
        for ($i = 1; $i <= 5; $i++) {
            $nullable[] = [
                'uniqueid_namareport' => "DL-NULL-{$i}",
                'periode' => '2026-04-30',
                'kode_cabang1' => "650{$i}",
                'cabang1' => 'KC Madiun',
                'baki_debet1' => null,
                'nama_debitur1' => null,
            ];
        }

        DB::table('daily_loan_dinamis')->insert(array_merge($filled, $nullable));

        $this->assertSame(10, DB::table('daily_loan_dinamis')->count());
        $this->assertSame(10, DB::table('daily_loan_dinamis')->whereNotNull('periode')->count());
        $this->assertSame(5, DB::table('daily_loan_dinamis')->whereNull('baki_debet1')->count());

        $service = new ManagedReportManagementService();
        [$periodColumn, $kancaColumn] = $service->resolveManagementScopeColumns(
            'daily_loan_dinamis',
            Schema::getColumnListing('daily_loan_dinamis')
        );

        $this->assertSame('periode', $periodColumn);
        $this->assertSame('cabang1', $kancaColumn);
    }

    public function test_simpanan_multipn_fixture_inserts_and_resolves_scope(): void
    {
        Schema::create('simpanan_multipn', function (Blueprint $table) {
            $table->string('uniqueid_namareport')->primary();
            $table->string('posisi');
            $table->string('kantor_cabang');
            $table->decimal('saldo_idr', 18, 2)->nullable();
            $table->string('jenis_simpanan')->nullable();
        });

        $filled = [];
        for ($i = 1; $i <= 5; $i++) {
            $filled[] = [
                'uniqueid_namareport' => "SMP-FULL-{$i}",
                'posisi' => '2026-04-30',
                'kantor_cabang' => 'KC Ponorogo',
                'saldo_idr' => 5000000.00 * $i,
                'jenis_simpanan' => 'TABUNGAN',
            ];
        }

        $nullable = [];
        for ($i = 1; $i <= 5; $i++) {
            $nullable[] = [
                'uniqueid_namareport' => "SMP-NULL-{$i}",
                'posisi' => '2026-04-30',
                'kantor_cabang' => 'KC Ponorogo',
                'saldo_idr' => null,
                'jenis_simpanan' => null,
            ];
        }

        DB::table('simpanan_multipn')->insert(array_merge($filled, $nullable));

        $this->assertSame(10, DB::table('simpanan_multipn')->count());
        $this->assertSame(10, DB::table('simpanan_multipn')->whereNotNull('posisi')->count());
        $this->assertSame(5, DB::table('simpanan_multipn')->whereNull('saldo_idr')->count());

        $service = new ManagedReportManagementService();
        [$periodColumn, $kancaColumn] = $service->resolveManagementScopeColumns(
            'simpanan_multipn',
            Schema::getColumnListing('simpanan_multipn')
        );

        $this->assertSame('posisi', $periodColumn);
        $this->assertSame('kantor_cabang', $kancaColumn);
    }

    public function test_lw325_ph_fixture_inserts_and_resolves_scope(): void
    {
        Schema::create('lw325_ph', function (Blueprint $table) {
            $table->string('uniqueid_namareport')->primary();
            $table->string('periode');
            $table->string('kanca');
            $table->string('nama_debitur')->nullable();
            $table->string('acctno')->nullable();
        });

        $filled = [];
        for ($i = 1; $i <= 5; $i++) {
            $filled[] = [
                'uniqueid_namareport' => "LW-FULL-{$i}",
                'periode' => '2026-04-30',
                'kanca' => 'KC Madiun',
                'nama_debitur' => "Debitur LW {$i}",
                'acctno' => "401000{$i}",
            ];
        }

        $nullable = [];
        for ($i = 1; $i <= 5; $i++) {
            $nullable[] = [
                'uniqueid_namareport' => "LW-NULL-{$i}",
                'periode' => '2026-04-30',
                'kanca' => 'KC Madiun',
                'nama_debitur' => null,
                'acctno' => null,
            ];
        }

        DB::table('lw325_ph')->insert(array_merge($filled, $nullable));

        $this->assertSame(10, DB::table('lw325_ph')->count());
        $this->assertSame(10, DB::table('lw325_ph')->whereNotNull('periode')->count());
        $this->assertSame(5, DB::table('lw325_ph')->whereNull('acctno')->count());

        $service = new ManagedReportManagementService();
        [$periodColumn, $kancaColumn] = $service->resolveManagementScopeColumns(
            'lw325_ph',
            Schema::getColumnListing('lw325_ph')
        );

        $this->assertSame('periode', $periodColumn);
        $this->assertSame('kanca', $kancaColumn);
    }

    public function test_gi405_singlerow_fixture_inserts_and_resolves_scope(): void
    {
        Schema::create('gi405_singlerow', function (Blueprint $table) {
            $table->string('uniqueid_namareport')->primary();
            $table->string('periode');
            $table->string('branch');
            $table->string('posting_control')->nullable();
            $table->string('account_number')->nullable();
        });

        $filled = [];
        for ($i = 1; $i <= 5; $i++) {
            $filled[] = [
                'uniqueid_namareport' => "GI-FULL-{$i}",
                'periode' => '2026-05-01',
                'branch' => '45',
                'posting_control' => '*POST',
                'account_number' => "10001099200{$i}",
            ];
        }

        $nullable = [];
        for ($i = 1; $i <= 5; $i++) {
            $nullable[] = [
                'uniqueid_namareport' => "GI-NULL-{$i}",
                'periode' => '2026-05-01',
                'branch' => '45',
                'posting_control' => null,
                'account_number' => null,
            ];
        }

        DB::table('gi405_singlerow')->insert(array_merge($filled, $nullable));

        $this->assertSame(10, DB::table('gi405_singlerow')->count());
        $this->assertSame(10, DB::table('gi405_singlerow')->whereNotNull('periode')->count());
        $this->assertSame(5, DB::table('gi405_singlerow')->whereNull('account_number')->count());

        $service = new ManagedReportManagementService();
        [$periodColumn, $kancaColumn] = $service->resolveManagementScopeColumns(
            'gi405_singlerow',
            Schema::getColumnListing('gi405_singlerow')
        );

        $this->assertSame('periode', $periodColumn);
        $this->assertSame('branch', $kancaColumn);
    }

    public function test_ssa_simpanan_fixture_inserts_and_resolves_scope(): void
    {
        Schema::create('ssa_simpanan', function (Blueprint $table) {
            $table->string('uniqueid_namareport')->primary();
            $table->string('month_day_year_of_posisi');
            $table->string('nama_cabang');
            $table->decimal('saldo', 18, 2)->nullable();
            $table->string('produk')->nullable();
        });

        $filled = [];
        for ($i = 1; $i <= 5; $i++) {
            $filled[] = [
                'uniqueid_namareport' => "SSA-S-FULL-{$i}",
                'month_day_year_of_posisi' => '2026-04-30',
                'nama_cabang' => 'KC Ponorogo',
                'saldo' => 10000000.00 * $i,
                'produk' => 'SIMPEDES',
            ];
        }

        $nullable = [];
        for ($i = 1; $i <= 5; $i++) {
            $nullable[] = [
                'uniqueid_namareport' => "SSA-S-NULL-{$i}",
                'month_day_year_of_posisi' => '2026-04-30',
                'nama_cabang' => 'KC Ponorogo',
                'saldo' => null,
                'produk' => null,
            ];
        }

        DB::table('ssa_simpanan')->insert(array_merge($filled, $nullable));

        $this->assertSame(10, DB::table('ssa_simpanan')->count());
        $this->assertSame(10, DB::table('ssa_simpanan')->whereNotNull('month_day_year_of_posisi')->count());
        $this->assertSame(5, DB::table('ssa_simpanan')->whereNull('saldo')->count());

        $service = new ManagedReportManagementService();
        [$periodColumn, $kancaColumn] = $service->resolveManagementScopeColumns(
            'ssa_simpanan',
            Schema::getColumnListing('ssa_simpanan')
        );

        $this->assertSame('month_day_year_of_posisi', $periodColumn);
        $this->assertSame('nama_cabang', $kancaColumn);
    }

    public function test_ssa_pinjaman_fixture_inserts_and_resolves_scope(): void
    {
        Schema::create('ssa_pinjaman', function (Blueprint $table) {
            $table->string('uniqueid_namareport')->primary();
            $table->string('month_day_year_of_periode');
            $table->string('nama_cabang');
            $table->decimal('baki_debet', 18, 2)->nullable();
            $table->string('produk')->nullable();
        });

        $filled = [];
        for ($i = 1; $i <= 5; $i++) {
            $filled[] = [
                'uniqueid_namareport' => "SSA-P-FULL-{$i}",
                'month_day_year_of_periode' => '2026-03-31',
                'nama_cabang' => 'KC Madiun',
                'baki_debet' => 25000000.00 * $i,
                'produk' => 'KUR MIKRO',
            ];
        }

        $nullable = [];
        for ($i = 1; $i <= 5; $i++) {
            $nullable[] = [
                'uniqueid_namareport' => "SSA-P-NULL-{$i}",
                'month_day_year_of_periode' => '2026-03-31',
                'nama_cabang' => 'KC Madiun',
                'baki_debet' => null,
                'produk' => null,
            ];
        }

        DB::table('ssa_pinjaman')->insert(array_merge($filled, $nullable));

        $this->assertSame(10, DB::table('ssa_pinjaman')->count());
        $this->assertSame(10, DB::table('ssa_pinjaman')->whereNotNull('month_day_year_of_periode')->count());
        $this->assertSame(5, DB::table('ssa_pinjaman')->whereNull('baki_debet')->count());

        $service = new ManagedReportManagementService();
        [$periodColumn, $kancaColumn] = $service->resolveManagementScopeColumns(
            'ssa_pinjaman',
            Schema::getColumnListing('ssa_pinjaman')
        );

        $this->assertSame('month_day_year_of_periode', $periodColumn);
        $this->assertSame('nama_cabang', $kancaColumn);
    }

    public function test_cognos_ph_fixture_inserts_and_resolves_scope(): void
    {
        Schema::create('cognos_ph', function (Blueprint $table) {
            $table->string('uniqueid_namareport')->primary();
            $table->string('periode');
            $table->string('kanca');
            $table->string('unit_kerja')->nullable();
            $table->string('acctno')->nullable();
        });

        $filled = [];
        for ($i = 1; $i <= 5; $i++) {
            $filled[] = [
                'uniqueid_namareport' => "CPH-FULL-{$i}",
                'periode' => '2026-04-30',
                'kanca' => '6499',
                'unit_kerja' => "00070 -- KC Ponorogo (Konsolidasi-MB)",
                'acctno' => "1000{$i}",
            ];
        }

        $nullable = [];
        for ($i = 1; $i <= 5; $i++) {
            $nullable[] = [
                'uniqueid_namareport' => "CPH-NULL-{$i}",
                'periode' => '2026-04-30',
                'kanca' => '6499',
                'unit_kerja' => null,
                'acctno' => null,
            ];
        }

        DB::table('cognos_ph')->insert(array_merge($filled, $nullable));

        $this->assertSame(10, DB::table('cognos_ph')->count());
        $this->assertSame(10, DB::table('cognos_ph')->whereNotNull('periode')->count());
        $this->assertSame(5, DB::table('cognos_ph')->whereNull('unit_kerja')->count());

        $service = new ManagedReportManagementService();
        [$periodColumn, $kancaColumn] = $service->resolveManagementScopeColumns(
            'cognos_ph',
            Schema::getColumnListing('cognos_ph')
        );

        $this->assertSame('periode', $periodColumn);
        $this->assertSame('kanca', $kancaColumn);
    }

    public function test_cognos_recovery_fixture_inserts_and_resolves_scope(): void
    {
        Schema::create('cognos_recovery', function (Blueprint $table) {
            $table->string('uniqueid_namareport')->primary();
            $table->string('periode');
            $table->string('cabang');
            $table->decimal('saldo_ph', 18, 2)->nullable();
        });

        $filled = [];
        for ($i = 1; $i <= 5; $i++) {
            $filled[] = [
                'uniqueid_namareport' => "CR-FULL-{$i}",
                'periode' => '2026-04-30',
                'cabang' => 'KC Madiun',
                'saldo_ph' => 30000000.00 * $i,
            ];
        }

        $nullable = [];
        for ($i = 1; $i <= 5; $i++) {
            $nullable[] = [
                'uniqueid_namareport' => "CR-NULL-{$i}",
                'periode' => '2026-04-30',
                'cabang' => 'KC Madiun',
                'saldo_ph' => null,
            ];
        }

        DB::table('cognos_recovery')->insert(array_merge($filled, $nullable));

        $this->assertSame(10, DB::table('cognos_recovery')->count());
        $this->assertSame(10, DB::table('cognos_recovery')->whereNotNull('periode')->count());
        $this->assertSame(5, DB::table('cognos_recovery')->whereNull('saldo_ph')->count());

        $service = new ManagedReportManagementService();
        [$periodColumn, $kancaColumn] = $service->resolveManagementScopeColumns(
            'cognos_recovery',
            Schema::getColumnListing('cognos_recovery')
        );

        $this->assertSame('periode', $periodColumn);
        $this->assertSame('cabang', $kancaColumn);
    }
}
