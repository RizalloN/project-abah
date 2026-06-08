<?php

namespace Tests\Unit;

use App\Http\Controllers\Report\KinerjaRmMikroReportController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use Tests\TestCase;

class KinerjaRmMikroPeriodResolutionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        Config::set('cache.default', 'array');

        DB::purge('sqlite');
        DB::reconnect('sqlite');
        DB::connection()->getPdo()->sqliteCreateFunction('CONCAT', fn (...$values) => implode('', $values), -1);

        Schema::dropAllTables();
        Schema::create('daily_loan_dinamis', function (Blueprint $table) {
            $table->id();
            $table->date('periode')->nullable();
            $table->string('segmen_kinerja')->nullable();
            $table->string('produk_kinerja')->nullable();
            $table->string('description')->nullable();
            $table->string('branch_normalized')->nullable();
            $table->string('branch1')->nullable();
            $table->string('unit_normalized')->nullable();
            $table->string('unit1')->nullable();
            $table->string('cabang_normalized')->nullable();
            $table->string('cabang1')->nullable();
            $table->string('rm_normalized')->nullable();
            $table->string('pn_pengelola1')->nullable();
            $table->string('nomor_rekening1')->nullable();
            $table->decimal('plafon', 20, 2)->nullable();
            $table->decimal('baki_debet1', 20, 2)->nullable();
            $table->integer('kol_adk1')->nullable();
            $table->integer('kolek')->nullable();
            $table->date('tgl_realisasi')->nullable();
            $table->string('pn_pemutus_normalized')->nullable();
        });
        Schema::create('brihc', function (Blueprint $table) {
            $table->id();
            $table->string('pn')->nullable();
            $table->string('jabatan')->nullable();
        });
        Schema::create('performance_rm_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('periode');
            $table->string('cabang')->nullable();
            $table->string('unit')->nullable();
            $table->string('branch_code')->nullable();
            $table->string('rm')->nullable();
            $table->string('segmen');
            $table->string('produk');
            $table->integer('lancar_deb')->default(0);
            $table->decimal('lancar_os', 20, 2)->default(0);
            $table->integer('sml_deb')->default(0);
            $table->decimal('sml_os', 20, 2)->default(0);
            $table->integer('npl_deb')->default(0);
            $table->decimal('npl_os', 20, 2)->default(0);
            $table->integer('total_deb')->default(0);
            $table->decimal('loan_os', 20, 2)->default(0);
            $table->integer('realisasi_deb')->default(0);
            $table->decimal('realisasi_os', 20, 2)->default(0);
            foreach (['w1', 'w2', 'w3', 'w4'] as $week) {
                $table->integer($week . '_realisasi_deb')->default(0);
                $table->decimal($week . '_realisasi_os', 20, 2)->default(0);
            }
            $table->integer('lt_250_realisasi_deb')->default(0);
            $table->decimal('lt_250_realisasi_os', 20, 2)->default(0);
            $table->integer('gt_250_realisasi_deb')->default(0);
            $table->decimal('gt_250_realisasi_os', 20, 2)->default(0);
        });

        Cache::forget('report_cache_version:pinjaman');
        Cache::forget('report_cache_version:simpanan');
    }

    public function test_period_options_include_micro_kur_daily_loan_source_periods(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            ['periode' => '2026-05-06', 'segmen_kinerja' => 'MICRO', 'produk_kinerja' => 'KURMIKRO'],
            ['periode' => '2026-05-04', 'segmen_kinerja' => 'MICRO', 'produk_kinerja' => 'KURMIKRO'],
            ['periode' => '2026-05-05', 'segmen_kinerja' => 'CONSUMER', 'produk_kinerja' => 'KPR'],
        ]);
        DB::table('performance_rm_snapshots')->insert([
            ['periode' => '2026-04-30', 'segmen' => 'MICRO', 'produk' => 'KUR-MIKRO'],
        ]);

        $periods = $this->invokePrivateMethod(new KinerjaRmMikroReportController(), 'fetchAvailablePeriods');

        $this->assertSame(['2026-05-06', '2026-04-30'], $periods->all());
    }

    public function test_requested_mid_month_uses_latest_micro_kur_period_in_that_month(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            ['periode' => '2026-05-15', 'segmen_kinerja' => 'MICRO', 'produk_kinerja' => 'KURMIKRO'],
            ['periode' => '2026-05-17', 'segmen_kinerja' => 'MICRO', 'produk_kinerja' => 'KURMIKRO'],
            ['periode' => '2026-04-30', 'segmen_kinerja' => 'MICRO', 'produk_kinerja' => 'KURMIKRO'],
        ]);

        $controller = new KinerjaRmMikroReportController();
        $periods = $this->invokePrivateMethod($controller, 'fetchAvailablePeriods');

        $this->assertSame(['2026-05-17', '2026-04-30'], $periods->all());
        $this->assertSame('2026-05-17', $this->invokePrivateMethod($controller, 'resolveSelectedPeriod', $periods, '2026-05-15'));
    }

    public function test_mantri_payload_excludes_kur_ritel_but_includes_kur_mikro_baru(): void
    {
        $this->insertDailyLoan([
            'produk_kinerja' => 'KURMIKRO',
            'description' => 'Kredit Mikro - KUR Ritel 2015',
            'nomor_rekening1' => 'RITEL-1',
            'plafon' => 100000000,
        ]);
        $this->insertDailyLoan([
            'produk_kinerja' => 'KURMIKRO',
            'description' => 'KUR Mikro Baru',
            'nomor_rekening1' => 'BARU-1',
            'plafon' => 200000000,
        ]);
        $this->insertDailyLoan([
            'produk_kinerja' => 'KUPEDES',
            'description' => 'Kupedes',
            'nomor_rekening1' => 'KUPEDES-1',
            'plafon' => 300000000,
        ]);

        $payload = $this->invokePrivateMethod(new KinerjaRmMikroReportController(), 'mantriKuadranPayload', '2026-05-06');

        $this->assertCount(1, $payload['rows']);
        $this->assertSame(2, (int) $payload['rows'][0]['realisasi_deb']);
        $this->assertSame(500000000.0, (float) $payload['rows'][0]['realisasi_os']);
    }

    public function test_mantri_productivity_payload_is_grouped_by_pn_pengelola(): void
    {
        $this->insertDailyLoan([
            'pn_pengelola1' => '0001 - Mantri Satu',
            'rm_normalized' => '0001 - MANTRI SATU',
            'produk_kinerja' => 'KUPEDES',
            'description' => 'Kupedes',
            'nomor_rekening1' => 'RM1-1',
            'plafon' => 100000000,
        ]);
        $this->insertDailyLoan([
            'pn_pengelola1' => '0002 - Mantri Dua',
            'rm_normalized' => '0002 - MANTRI DUA',
            'produk_kinerja' => 'KURMIKRO',
            'description' => 'KUR Mikro Baru',
            'nomor_rekening1' => 'RM2-1',
            'plafon' => 250000000,
        ]);
        $this->insertDailyLoan([
            'pn_pengelola1' => '0002 - Mantri Dua',
            'rm_normalized' => '0002 - MANTRI DUA',
            'produk_kinerja' => 'KURMIKRO',
            'description' => 'Kredit Mikro - KUR Ritel 2015',
            'nomor_rekening1' => 'RM2-RITEL',
            'plafon' => 999000000,
        ]);

        $payload = $this->invokePrivateMethod(new KinerjaRmMikroReportController(), 'mantriProductivityPayload', '2026-05-06');
        $rows = collect($payload['rows'])->keyBy('nama_mantri');

        $this->assertCount(2, $payload['rows']);
        $this->assertSame(100000000.0, (float) $rows->get('0001 - MANTRI SATU')['realisasi_os']);
        $this->assertSame(250000000.0, (float) $rows->get('0002 - MANTRI DUA')['realisasi_os']);
        $this->assertSame(2, (int) $payload['total']['jumlah_mantri']);
    }

    public function test_rm_mikro_kur_payload_hides_rm_with_zero_monthly_realisasi(): void
    {
        DB::table('performance_rm_snapshots')->insert([
            [
                'periode' => '2026-05-31',
                'cabang' => 'KC TEST',
                'unit' => 'UNIT TEST',
                'branch_code' => '001',
                'rm' => '0001 - RM AKTIF',
                'segmen' => 'MICRO',
                'produk' => 'KUR-MIKRO',
                'total_deb' => 10,
                'loan_os' => 1000000000,
                'realisasi_deb' => 2,
                'realisasi_os' => 250000000,
                'w1_realisasi_deb' => 2,
                'w1_realisasi_os' => 250000000,
                'lt_250_realisasi_deb' => 1,
                'lt_250_realisasi_os' => 100000000,
                'gt_250_realisasi_deb' => 1,
                'gt_250_realisasi_os' => 150000000,
            ],
            [
                'periode' => '2026-05-31',
                'cabang' => 'KC TEST',
                'unit' => 'UNIT TEST',
                'branch_code' => '001',
                'rm' => '0002 - RM PINDAH',
                'segmen' => 'MICRO',
                'produk' => 'KUR-MIKRO',
                'total_deb' => 8,
                'loan_os' => 800000000,
                'realisasi_deb' => 0,
                'realisasi_os' => 0,
                'w1_realisasi_deb' => 0,
                'w1_realisasi_os' => 0,
                'lt_250_realisasi_deb' => 0,
                'lt_250_realisasi_os' => 0,
                'gt_250_realisasi_deb' => 0,
                'gt_250_realisasi_os' => 0,
            ],
        ]);

        $controller = new KinerjaRmMikroReportController();
        $perRm = $this->invokePrivateMethod($controller, 'perRmPayload', '2026-05-31');
        $rekap = $this->invokePrivateMethod($controller, 'rekapPayload', '2026-05-31');
        $seriesHarian = $this->invokePrivateMethod($controller, 'seriesHarianPayload', '2026-05-31');
        $tiering = $this->invokePrivateMethod($controller, 'tieringPayload', '2026-05-31');

        $this->assertSame(['RM AKTIF'], collect($perRm['rows'])->pluck('nama')->all());
        $this->assertSame(250000000.0, (float) $perRm['total']['realisasi_os']);
        $this->assertSame(1, (int) $rekap['total']['total_rm']);
        $this->assertSame(0, (int) $rekap['total']['belum_real']);
        $this->assertSame(['RM AKTIF'], collect($seriesHarian['rows'])->pluck('nama')->all());
        $this->assertSame(['RM AKTIF'], collect($tiering['rows'])->pluck('nama')->all());
    }

    private function insertDailyLoan(array $overrides): void
    {
        DB::table('daily_loan_dinamis')->insert(array_merge([
            'periode' => '2026-05-06',
            'segmen_kinerja' => 'MICRO',
            'produk_kinerja' => 'KUPEDES',
            'description' => 'Kupedes',
            'branch_normalized' => '001',
            'branch1' => '001',
            'unit_normalized' => 'UNIT TEST',
            'unit1' => 'UNIT TEST',
            'cabang_normalized' => 'KC TEST',
            'cabang1' => 'KC TEST',
            'rm_normalized' => '0001 - MANTRI SATU',
            'pn_pengelola1' => '0001 - Mantri Satu',
            'nomor_rekening1' => 'TEST-1',
            'plafon' => 100000000,
            'baki_debet1' => 90000000,
            'kol_adk1' => 1,
            'kolek' => 1,
            'tgl_realisasi' => '2026-05-06',
            'pn_pemutus_normalized' => null,
        ], $overrides));
    }

    private function invokePrivateMethod(object $object, string $method, mixed ...$arguments): mixed
    {
        $reflection = new ReflectionClass($object);
        $reflectedMethod = $reflection->getMethod($method);
        $reflectedMethod->setAccessible(true);

        return $reflectedMethod->invoke($object, ...$arguments);
    }
}
