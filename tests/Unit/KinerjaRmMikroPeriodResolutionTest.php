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
            $table->string('segmen');
            $table->string('produk');
        });

        Cache::forget('report_cache_version:pinjaman');
        Cache::forget('report_cache_version:simpanan');
    }

    public function test_period_options_include_micro_kur_daily_loan_source_periods(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            ['periode' => '2026-05-06', 'segmen_kinerja' => 'MICRO', 'produk_kinerja' => 'KURMIKRO'],
            ['periode' => '2026-05-05', 'segmen_kinerja' => 'CONSUMER', 'produk_kinerja' => 'KPR'],
        ]);
        DB::table('performance_rm_snapshots')->insert([
            ['periode' => '2026-04-30', 'segmen' => 'MICRO', 'produk' => 'KUR-MIKRO'],
        ]);

        $periods = $this->invokePrivateMethod(new KinerjaRmMikroReportController(), 'fetchAvailablePeriods');

        $this->assertSame(['2026-05-06', '2026-04-30'], $periods->all());
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
