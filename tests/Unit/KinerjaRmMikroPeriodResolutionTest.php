<?php

namespace Tests\Unit;

use App\Http\Controllers\DashboardSimpananController;
use App\Http\Controllers\Report\KinerjaRmMikroReportController;
use App\Jobs\SyncImportedReportJob;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
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
        Schema::create('brihc_pemasar', function (Blueprint $table) {
            $table->string('uniqueid_namareport')->primary();
            $table->string('completename')->nullable();
            $table->string('nip')->nullable();
            $table->string('pernr')->nullable();
            $table->string('esgdesc')->nullable();
            $table->string('psadesc')->nullable();
            $table->string('orgdesc')->nullable();
            $table->string('positiondesc')->nullable();
            $table->string('pn_mantri')->nullable();
            $table->string('status')->nullable();
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

    public function test_period_options_use_micro_kur_snapshot_periods_without_scanning_daily_loan_source(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            ['periode' => '2026-05-06', 'segmen_kinerja' => 'MICRO', 'produk_kinerja' => 'KURMIKRO', 'description' => 'Kredit Mikro - KUR Ritel 2015'],
            ['periode' => '2026-05-04', 'segmen_kinerja' => 'MICRO', 'produk_kinerja' => 'KURMIKRO', 'description' => 'KUR Mikro Baru'],
            ['periode' => '2026-05-05', 'segmen_kinerja' => 'CONSUMER', 'produk_kinerja' => 'KPR', 'description' => null],
        ]);
        DB::table('performance_rm_snapshots')->insert([
            ['periode' => '2026-05-06', 'segmen' => 'MICRO', 'produk' => 'KUR-MIKRO'],
            ['periode' => '2026-04-30', 'segmen' => 'MICRO', 'produk' => 'KUR-MIKRO'],
        ]);

        $periods = $this->invokePrivateMethod(new KinerjaRmMikroReportController(), 'fetchAvailablePeriods');

        $this->assertSame(['2026-05-06', '2026-04-30'], $periods->all());
    }

    public function test_mantri_period_options_include_latest_daily_loan_period_when_snapshot_lags(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            'periode' => '2026-07-14',
            'segmen_kinerja' => 'MICRO',
            'produk_kinerja' => 'KUPEDES',
            'description' => 'Kupedes',
        ]);
        DB::table('performance_rm_snapshots')->insert([
            'periode' => '2026-07-12',
            'segmen' => 'MICRO',
            'produk' => 'KUR-MIKRO',
        ]);

        $periods = $this->invokePrivateMethod(new KinerjaRmMikroReportController(), 'fetchAvailablePeriods', true);

        $this->assertSame(['2026-07-14'], $periods->all());
    }

    public function test_mantri_defaults_to_productivity_report(): void
    {
        $categories = (new ReflectionClass(KinerjaRmMikroReportController::class))
            ->getConstant('MANTRI_REPORT_CATEGORIES');

        $this->assertSame('produktivitas_mantri', array_key_first($categories));
    }

    public function test_requested_mid_month_uses_latest_micro_kur_period_in_that_month(): void
    {
        DB::table('performance_rm_snapshots')->insert([
            ['periode' => '2026-05-15', 'segmen' => 'MICRO', 'produk' => 'KUR-MIKRO'],
            ['periode' => '2026-05-17', 'segmen' => 'MICRO', 'produk' => 'KUR-MIKRO'],
            ['periode' => '2026-04-30', 'segmen' => 'MICRO', 'produk' => 'KUR-MIKRO'],
        ]);

        $controller = new KinerjaRmMikroReportController();
        $periods = $this->invokePrivateMethod($controller, 'fetchAvailablePeriods');

        $this->assertSame(['2026-05-17', '2026-04-30'], $periods->all());
        $this->assertSame('2026-05-17', $this->invokePrivateMethod($controller, 'resolveSelectedPeriod', $periods, '2026-05-15'));
    }

    public function test_embedded_payload_serves_last_ready_period_while_latest_daily_loan_is_synced(): void
    {
        Queue::fake();

        DB::table('daily_loan_dinamis')->insert([
            'periode' => '2026-07-24',
            'segmen_kinerja' => null,
            'produk_kinerja' => null,
            'description' => 'Kupedes',
            'branch1' => '001',
            'unit1' => 'UNIT BARU',
            'cabang1' => 'KC MADIUN',
            'pn_pengelola1' => '0009 - Mantri Belum Normal',
            'nomor_rekening1' => 'LATEST-UNREADY',
            'plafon' => 200000000,
            'baki_debet1' => 180000000,
            'kol_adk1' => 1,
            'kolek' => 1,
            'tgl_realisasi' => '2026-07-24',
        ]);
        $this->insertDailyLoan([
            'periode' => '2026-07-23',
            'produk_kinerja' => 'KUPEDES',
            'description' => 'Kupedes',
            'pn_pemutus_normalized' => '123',
            'nomor_rekening1' => 'READY-MANTRI',
            'tgl_realisasi' => '2026-07-23',
        ]);
        DB::table('brihc')->insert([
            'pn' => '123',
            'jabatan' => 'KAUNIT',
        ]);
        DB::table('performance_rm_snapshots')->insert([
            'periode' => '2026-07-23',
            'cabang' => 'KC MADIUN',
            'unit' => 'UNIT TEST',
            'branch_code' => '001',
            'rm' => '0001 - RM READY',
            'segmen' => 'MICRO',
            'produk' => 'KUR-MIKRO',
            'lancar_deb' => 1,
            'lancar_os' => 90000000,
            'total_deb' => 1,
            'loan_os' => 90000000,
            'realisasi_deb' => 1,
            'realisasi_os' => 100000000,
        ]);

        $controller = new KinerjaRmMikroReportController();
        $decision = $controller->buildEmbeddedPayload('unit_pemutus', '2026-07-24', true);
        $rmKur = $controller->buildEmbeddedPayload('per_rm', '2026-07-24', false);

        $this->assertSame('2026-07-24', $decision['meta']['requested_period']);
        $this->assertSame('2026-07-23', $decision['meta']['data_period']);
        $this->assertTrue($decision['meta']['refresh_pending']);
        $this->assertNotEmpty($decision['rows']);
        $this->assertSame('2026-07-23', $rmKur['meta']['data_period']);
        $this->assertSame('RM READY', $rmKur['rows'][0]['nama']);
        Queue::assertPushedOn('snapshots-priority', SyncImportedReportJob::class, function (SyncImportedReportJob $job): bool {
            return $job->tableName === 'daily_loan_dinamis'
                && $job->periodHint === '2026-07-24';
        });
    }

    public function test_rm_mikro_kur_payload_uses_kur_ritel_2015_and_excludes_kur_mikro_baru_when_snapshot_is_missing(): void
    {
        $this->insertDailyLoan([
            'periode' => '2026-05-15',
            'produk_kinerja' => 'KURMIKRO',
            'description' => 'KUR Mikro Baru',
            'nomor_rekening1' => 'KUR-BARU-1',
            'plafon' => 150000000,
            'baki_debet1' => 120000000,
            'tgl_realisasi' => '2026-05-15',
        ]);
        $this->insertDailyLoan([
            'periode' => '2026-05-15',
            'produk_kinerja' => 'KURMIKRO',
            'description' => 'Kredit Mikro - KUR Ritel 2015',
            'nomor_rekening1' => 'KUR-RITEL-1',
            'plafon' => 200000000,
            'baki_debet1' => 130000000,
            'tgl_realisasi' => '2026-05-15',
        ]);
        $this->insertDailyLoan([
            'periode' => '2026-05-15',
            'produk_kinerja' => 'KURKECIL',
            'description' => 'Kredit Mikro - KUR Ritel 2015',
            'nomor_rekening1' => 'KUR-RITEL-2',
            'plafon' => 300000000,
            'baki_debet1' => 210000000,
            'tgl_realisasi' => '2026-05-15',
        ]);

        $payload = $this->invokePrivateMethod(new KinerjaRmMikroReportController(), 'perUkerPayload', '2026-05-15');

        $this->assertCount(1, $payload['rows']);
        $this->assertSame('UNIT TEST', $payload['rows'][0]['unit']);
        $this->assertSame(2, (int) $payload['total']['total_deb']);
        $this->assertSame(500000000.0, (float) $payload['total']['total_os']);
        $this->assertSame(2, (int) $payload['total']['lancar_deb']);
        $this->assertSame(500000000.0, (float) $payload['total']['realisasi_os']);
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

        $payload = $this->invokePrivateMethod(new KinerjaRmMikroReportController(), 'mantriProductivityPayload', '2026-05-06');

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
        $this->assertSame(100000000.0, (float) $rows->get('MANTRI SATU')['realisasi_os']);
        $this->assertSame('00000001', $rows->get('MANTRI SATU')['pn_mantri']);
        $this->assertSame(250000000.0, (float) $rows->get('MANTRI DUA')['realisasi_os']);
        $this->assertSame('00000002', $rows->get('MANTRI DUA')['pn_mantri']);
        $this->assertSame(2, (int) $payload['total']['jumlah_mantri']);
    }

    public function test_mantri_quadrant_payload_counts_each_mantri_per_unit(): void
    {
        $this->insertDailyLoan([
            'pn_pengelola1' => '0001 - Mantri Kuadran Satu',
            'rm_normalized' => '0001 - MANTRI KUADRAN SATU',
            'nomor_rekening1' => 'Q1-1',
            'plafon' => 400000000,
        ]);

        foreach (range(1, 8) as $index) {
            $this->insertDailyLoan([
                'pn_pengelola1' => '0002 - Mantri Kuadran Dua',
                'rm_normalized' => '0002 - MANTRI KUADRAN DUA',
                'nomor_rekening1' => 'Q2-' . $index,
                'plafon' => 40000000,
            ]);
        }

        $this->insertDailyLoan([
            'pn_pengelola1' => '0003 - Mantri Kuadran Tiga',
            'rm_normalized' => '0003 - MANTRI KUADRAN TIGA',
            'nomor_rekening1' => 'Q3-1',
            'plafon' => 100000000,
        ]);
        $this->insertDailyLoan([
            'pn_pengelola1' => '0004 - Mantri Kuadran Empat',
            'rm_normalized' => '0004 - MANTRI KUADRAN EMPAT',
            'nomor_rekening1' => 'Q4-1',
            'plafon' => 40000000,
        ]);

        $payload = $this->invokePrivateMethod(new KinerjaRmMikroReportController(), 'mantriKuadranPayload', '2026-05-06');

        $this->assertCount(1, $payload['rows']);
        $this->assertSame('KC MADIUN', $payload['rows'][0]['cabang']);
        $this->assertSame('001', $payload['rows'][0]['bc']);
        $this->assertSame('UNIT TEST', $payload['rows'][0]['unit']);
        $this->assertSame(4, (int) $payload['rows'][0]['jumlah_mantri']);
        $this->assertSame(1, (int) $payload['rows'][0]['kuadran_1']);
        $this->assertSame(1, (int) $payload['rows'][0]['kuadran_2']);
        $this->assertSame(1, (int) $payload['rows'][0]['kuadran_3']);
        $this->assertSame(1, (int) $payload['rows'][0]['kuadran_4']);
        $this->assertSame(4, (int) $payload['total']['jumlah_mantri']);
    }

    public function test_extreme_low_mantri_payload_starts_from_daily_loan_and_only_checks_brihc_pt_unit_status(): void
    {
        $this->insertBrihcPemasar([
            'uniqueid_namareport' => 'MANTRI-1',
            'completename' => 'Nama BRIHC Berbeda',
            'orgdesc' => 'Unit BRIHC Berbeda',
            'pn_mantri' => '6494 | 0001 - Mantri Satu',
        ]);
        $this->insertBrihcPemasar([
            'uniqueid_namareport' => 'MANTRI-2',
            'completename' => 'Mantri Dua',
            'pn_mantri' => '6494 | 0002 - Mantri Dua',
        ]);
        $this->insertBrihcPemasar([
            'uniqueid_namareport' => 'MANTRI-3',
            'completename' => 'Mantri Nol',
            'pn_mantri' => '6494 | 0003 - Mantri Nol',
        ]);
        $this->insertBrihcPemasar([
            'uniqueid_namareport' => 'NON-PT',
            'completename' => 'Kontrak',
            'pn_mantri' => '6494 | 0004 - Kontrak',
            'esgdesc' => 'TKWT',
        ]);
        $this->insertBrihcPemasar([
            'uniqueid_namareport' => 'NON-MANTRI',
            'completename' => 'Kaunit',
            'pn_mantri' => '6494 | 0005 - Kaunit',
            'positiondesc' => 'KAUNIT',
        ]);
        $this->insertBrihcPemasar([
            'uniqueid_namareport' => 'MANTRI-TANPA-DAILY',
            'completename' => 'Mantri Tanpa Daily',
            'pn_mantri' => '6494 | 0006 - Mantri Tanpa Daily',
        ]);

        $this->insertDailyLoan([
            'periode' => '2026-05-31',
            'tgl_realisasi' => '2026-05-05',
            'pn_pengelola1' => '0001 - Mantri Satu',
            'rm_normalized' => '0001 - MANTRI SATU',
            'nomor_rekening1' => 'EL-1',
            'plafon' => 50000000,
        ]);
        $this->insertDailyLoan([
            'periode' => '2026-05-31',
            'tgl_realisasi' => '2026-05-07',
            'pn_pengelola1' => '0002 - Mantri Dua',
            'rm_normalized' => '0002 - MANTRI DUA',
            'nomor_rekening1' => 'EL-2A',
            'plafon' => 150000000,
        ]);
        $this->insertDailyLoan([
            'periode' => '2026-05-31',
            'tgl_realisasi' => '2026-05-11',
            'pn_pengelola1' => '0002 - Mantri Dua',
            'rm_normalized' => '0002 - MANTRI DUA',
            'nomor_rekening1' => 'EL-2B',
            'plafon' => 100000000,
        ]);
        $this->insertDailyLoan([
            'periode' => '2026-05-31',
            'tgl_realisasi' => '2026-04-30',
            'pn_pengelola1' => '0002 - Mantri Dua',
            'rm_normalized' => '0002 - MANTRI DUA',
            'nomor_rekening1' => 'EL-LUAR-BULAN',
            'plafon' => 999000000,
        ]);
        $this->insertDailyLoan([
            'periode' => '2026-05-31',
            'tgl_realisasi' => '2026-04-30',
            'pn_pengelola1' => '0003 - Mantri Nol',
            'rm_normalized' => '0003 - MANTRI NOL',
            'nomor_rekening1' => 'EL-NOL',
            'plafon' => 100000000,
        ]);
        $this->insertDailyLoan([
            'periode' => '2026-05-31',
            'tgl_realisasi' => '2026-04-30',
            'pn_pengelola1' => '0004 - Pengelola Non PT',
            'rm_normalized' => '0004 - PENGELOLA NON PT',
            'nomor_rekening1' => 'EL-NON-PT',
            'plafon' => 100000000,
        ]);
        $this->insertDailyLoan([
            'periode' => '2026-05-31',
            'tgl_realisasi' => '2026-04-30',
            'pn_pengelola1' => '0005 - Pengelola Lima',
            'rm_normalized' => '0005 - PENGELOLA LIMA',
            'nomor_rekening1' => 'EL-POSISI-DIABAIKAN',
            'plafon' => 100000000,
        ]);
        $this->insertDailyLoan([
            'periode' => '2026-05-31',
            'tgl_realisasi' => '2026-05-12',
            'pn_pengelola1' => '0001 - Mantri Satu',
            'rm_normalized' => '0001 - MANTRI SATU',
            'produk_kinerja' => 'KURMIKRO',
            'description' => 'Kredit Mikro - KUR Ritel 2015',
            'nomor_rekening1' => 'EL-RITEL',
            'plafon' => 999000000,
        ]);

        $payload = $this->invokePrivateMethod(new KinerjaRmMikroReportController(), 'mantriExtremeLowPayload', '2026-05-31', 'per_unit_kerja');
        $unitRow = collect($payload['rows'])->firstWhere('nama_uker', 'UNIT TEST');

        $this->assertSame('per_unit_kerja', $payload['view']);
        $this->assertCount(1, $payload['rows']);
        $this->assertNotNull($unitRow);
        $this->assertSame(4, (int) $unitRow['total_mantri']);
        $this->assertSame(3, (int) $unitRow['buckets']['el_0_100']['deb']);
        $this->assertSame(1, (int) $unitRow['buckets']['el_200_400']['deb']);
        $this->assertSame(4, (int) $unitRow['extreme_low']['deb']);
        $this->assertSame(4, (int) $payload['total']['total_mantri']);
        $this->assertSame(4, (int) $payload['total']['extreme_low']['deb']);

        $branchPayload = $this->invokePrivateMethod(new KinerjaRmMikroReportController(), 'mantriExtremeLowPayload', '2026-05-31', 'per_cabang');
        $branchRow = collect($branchPayload['rows'])->firstWhere('branch_office', 'KC MADIUN');

        $this->assertSame('per_cabang', $branchPayload['view']);
        $this->assertNotNull($branchRow);
        $this->assertSame(4, (int) $branchRow['total_mantri']);
        $this->assertSame(3, (int) $branchRow['buckets']['el_0_100']['deb']);
        $this->assertSame(1, (int) $branchRow['buckets']['el_200_400']['deb']);
        $this->assertSame(4, (int) $branchRow['extreme_low']['deb']);
        $this->assertSame(4, (int) $branchRow['under_800']['deb']);
        $this->assertEqualsWithDelta(75.0, $branchRow['buckets']['el_0_100']['pct'], 0.0001);
        $this->assertEqualsWithDelta(100.0, $branchRow['extreme_low']['pct'], 0.0001);
        $this->assertEqualsWithDelta(100.0, $branchRow['under_800']['pct'], 0.0001);
    }

    public function test_extreme_low_mantri_view_offers_unit_kerja_and_branch_modes_without_click_detail(): void
    {
        $view = file_get_contents(resource_path('views/report/dashboard-pinjaman/_kinerjarmmikro_partials/_table_mantri_extreme_low.blade.php'));

        $this->assertIsString($view);
        $this->assertStringContainsString('extreme_low_view', $view);
        $this->assertStringContainsString('Unit Kerja', $view);
        $this->assertStringContainsString('Cabang', $view);
        $this->assertStringContainsString('Total Mantri Extreme Low', $view);
        $this->assertStringContainsString('Jumlah Mantri pada setiap kategori realisasi', $view);
        $this->assertStringNotContainsString('Per Mantri', $view);
        $this->assertStringNotContainsString('data-mantri-extreme-detail', $view);
        $this->assertStringNotContainsString("row.addEventListener('click'", $view);
    }

    public function test_presentation_mantri_categories_are_aggregated_per_branch(): void
    {
        Queue::fake();

        $fixtures = [
            ['pn' => '0101', 'name' => 'Extreme', 'amount' => 100000000],
            ['pn' => '0102', 'name' => 'Low', 'amount' => 500000000],
            ['pn' => '0103', 'name' => 'Mid', 'amount' => 900000000],
            ['pn' => '0104', 'name' => 'High', 'amount' => 1300000000],
        ];

        foreach ($fixtures as $index => $fixture) {
            $this->insertBrihcPemasar([
                'uniqueid_namareport' => 'PRESENTATION-CATEGORY-' . $fixture['pn'],
                'completename' => 'Mantri ' . $fixture['name'],
                'pn_mantri' => '6494 | ' . $fixture['pn'] . ' - Mantri ' . $fixture['name'],
            ]);
            $this->insertDailyLoan([
                'periode' => '2026-05-31',
                'tgl_realisasi' => '2026-05-15',
                'pn_pengelola1' => $fixture['pn'] . ' - Mantri ' . $fixture['name'],
                'rm_normalized' => $fixture['pn'] . ' - MANTRI ' . strtoupper($fixture['name']),
                'nomor_rekening1' => 'CATEGORY-' . ($index + 1),
                'plafon' => $fixture['amount'],
            ]);
        }

        $payload = $this->invokePrivateMethod(
            new DashboardSimpananController(),
            'buildPresentationExtremeLowMantri',
            '2026-05-31'
        );
        $madiun = collect($payload['rows'])->firstWhere('branch_office', 'KC MADIUN');
        $emptyBranch = collect($payload['rows'])
            ->first(fn (array $row): bool => (int) ($row['total_mantri'] ?? 0) === 0);

        $this->assertTrue($payload['available']);
        $this->assertCount(4, $payload['rows']);
        $this->assertNotNull($madiun);
        $this->assertSame(4, $madiun['total_mantri']);
        $this->assertSame(1, $madiun['extreme_low']['deb']);
        $this->assertSame(1, $madiun['low']['deb']);
        $this->assertSame(2, $madiun['under_800']['deb']);
        $this->assertSame(1, $madiun['mid']['deb']);
        $this->assertSame(1, $madiun['high']['deb']);
        $this->assertSame(
            $madiun['total_mantri'],
            $madiun['extreme_low']['deb']
                + $madiun['low']['deb']
                + $madiun['mid']['deb']
                + $madiun['high']['deb']
        );
        $this->assertSame(
            $madiun['under_800']['deb'],
            $madiun['extreme_low']['deb'] + $madiun['low']['deb']
        );
        $this->assertEqualsWithDelta(25.0, $madiun['extreme_low']['pct'], 0.0001);
        $this->assertEqualsWithDelta(50.0, $madiun['under_800']['pct'], 0.0001);
        $this->assertNotNull($emptyBranch);
        $this->assertSame(0, $emptyBranch['low']['deb']);
        $this->assertSame(0, $emptyBranch['mid']['deb']);
        $this->assertSame(0, $emptyBranch['high']['deb']);
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
        $this->assertSame(2, (int) $seriesHarian['rows'][0]['w1_deb']);
        $this->assertSame(250000000.0, (float) $seriesHarian['rows'][0]['w1_os']);
        $this->assertSame(0, (int) $seriesHarian['rows'][0]['w2_deb']);
        $this->assertSame(250000000.0, (float) $seriesHarian['rows'][0]['total_os']);
        $this->assertSame(['RM AKTIF'], collect($tiering['rows'])->pluck('nama')->all());
    }

    public function test_rm_mikro_realisasi_heat_scale_keeps_quadrant_colors_unchanged(): void
    {
        $controller = new KinerjaRmMikroReportController();
        $formatAmount = fn ($value, int $decimals = 0): string => $this->invokePrivateMethod($controller, 'formatAmount', $value, $decimals);
        $formatJuta = fn ($value, int $decimals = 0): string => $this->invokePrivateMethod($controller, 'formatJuta', $value, $decimals);
        $gradientClass = fn ($value, float $min, float $max, bool $higherIsBetter = true): string => $this->invokePrivateMethod($controller, 'gradientClass', (float) $value, $min, $max, $higherIsBetter);

        $rows = collect([
            [
                'cabang' => 'KC TEST',
                'pn' => '0001',
                'nama' => 'RM RENDAH',
                'branch_code' => '001',
                'unit' => 'UNIT A',
                'lancar_deb' => 0,
                'lancar_os' => 0,
                'sml_deb' => 0,
                'sml_os' => 0,
                'npl_deb' => 0,
                'npl_os' => 0,
                'total_deb' => 0,
                'total_os' => 0,
                'realisasi_deb' => 0,
                'realisasi_os' => 0,
            ],
            [
                'cabang' => 'KC TEST',
                'pn' => '0002',
                'nama' => 'RM TINGGI',
                'branch_code' => '002',
                'unit' => 'UNIT B',
                'lancar_deb' => 0,
                'lancar_os' => 0,
                'sml_deb' => 0,
                'sml_os' => 0,
                'npl_deb' => 0,
                'npl_os' => 0,
                'total_deb' => 0,
                'total_os' => 0,
                'realisasi_deb' => 5,
                'realisasi_os' => 500000000,
            ],
        ]);

        $html = view('report.dashboard-pinjaman._kinerjarmmikro_partials._table_per_rm', [
            'rows' => $rows,
            'selectedPeriodLabel' => '31 Mei 2026',
            'selectedPeriodShortLabel' => '31 Mei 26',
            'formatAmount' => $formatAmount,
            'formatJuta' => $formatJuta,
            'gradientClass' => $gradientClass,
        ])->render();

        $this->assertStringContainsString('class="text-right heat-red">0</td><td class="text-right heat-red">0</td>', $html);
        $this->assertStringContainsString('class="text-right heat-green">5</td><td class="text-right heat-green">500</td>', $html);

        $quadrantHtml = view('report.dashboard-pinjaman._kinerjarmmikro_partials._table_mantri_kuadran', [
            'rows' => collect([
                [
                    'cabang' => 'KC TEST',
                    'bc' => '001',
                    'unit' => 'UNIT A',
                    'jumlah_mantri' => 10,
                    'kuadran_1' => 4,
                    'kuadran_2' => 3,
                    'kuadran_3' => 2,
                    'kuadran_4' => 1,
                ],
            ]),
            'total' => [
                'jumlah_mantri' => 10,
                'kuadran_1' => 4,
                'kuadran_2' => 3,
                'kuadran_3' => 2,
                'kuadran_4' => 1,
            ],
            'formatAmount' => $formatAmount,
        ])->render();

        $this->assertStringContainsString('class="text-right heat-green">4</td>', $quadrantHtml);
        $this->assertStringContainsString('class="text-right heat-lime">3</td>', $quadrantHtml);
        $this->assertStringContainsString('class="text-right heat-orange">2</td>', $quadrantHtml);
        $this->assertStringContainsString('class="text-right heat-red">1</td>', $quadrantHtml);
    }

    public function test_rm_mikro_view_registers_sortable_header_script(): void
    {
        $blade = file_get_contents(resource_path('views/report/dashboard-pinjaman/kinerjarmmikro.blade.php'));

        $this->assertStringContainsString('rm-mikro-sortable', $blade);
        $this->assertStringContainsString('sortTableByColumn', $blade);
        $this->assertStringContainsString('aria-sort', $blade);
    }

    public function test_presentation_micro_period_falls_back_until_latest_shadow_columns_are_ready(): void
    {
        $this->insertDailyLoan([
            'periode' => '2026-07-23',
            'segmen_kinerja' => 'MICRO',
            'produk_kinerja' => 'KUPEDES',
            'rm_normalized' => '0001 - MANTRI SIAP',
        ]);
        $this->insertDailyLoan([
            'periode' => '2026-07-24',
            'segmen_kinerja' => null,
            'produk_kinerja' => null,
            'rm_normalized' => null,
        ]);

        $controller = new DashboardSimpananController();

        $this->assertSame(
            '2026-07-23',
            $this->invokePrivateMethod($controller, 'resolvePresentationMicroPeriod', '2026-07-24')
        );

        DB::table('daily_loan_dinamis')
            ->where('periode', '2026-07-24')
            ->update([
                'segmen_kinerja' => 'MICRO',
                'produk_kinerja' => 'KUPEDES',
                'rm_normalized' => '0002 - MANTRI TERBARU',
            ]);
        Cache::flush();

        $this->assertSame(
            '2026-07-24',
            $this->invokePrivateMethod($controller, 'resolvePresentationMicroPeriod', '2026-07-24')
        );
    }

    public function test_presentation_rm_kur_tiering_is_recapped_per_branch(): void
    {
        Queue::fake();

        DB::table('performance_rm_snapshots')->insert([
            [
                'periode' => '2026-07-24',
                'cabang' => 'KC MADIUN',
                'unit' => 'UNIT A',
                'branch_code' => '001',
                'rm' => '0001 - RM SATU',
                'segmen' => 'MICRO',
                'produk' => 'KUR-MIKRO',
                'lt_250_realisasi_deb' => 2,
                'lt_250_realisasi_os' => 200000000,
                'gt_250_realisasi_deb' => 1,
                'gt_250_realisasi_os' => 300000000,
            ],
            [
                'periode' => '2026-07-24',
                'cabang' => 'KC MADIUN',
                'unit' => 'UNIT B',
                'branch_code' => '002',
                'rm' => '0002 - RM DUA',
                'segmen' => 'MICRO',
                'produk' => 'KUR-MIKRO',
                'lt_250_realisasi_deb' => 3,
                'lt_250_realisasi_os' => 400000000,
                'gt_250_realisasi_deb' => 2,
                'gt_250_realisasi_os' => 600000000,
            ],
            [
                'periode' => '2026-07-24',
                'cabang' => 'KC NGAWI',
                'unit' => 'UNIT C',
                'branch_code' => '003',
                'rm' => '0003 - RM TIGA',
                'segmen' => 'MICRO',
                'produk' => 'KUR-MIKRO',
                'lt_250_realisasi_deb' => 4,
                'lt_250_realisasi_os' => 500000000,
                'gt_250_realisasi_deb' => 1,
                'gt_250_realisasi_os' => 350000000,
            ],
        ]);

        $payload = $this->invokePrivateMethod(
            new DashboardSimpananController(),
            'buildPresentationRmKurTiering',
            '2026-07-24'
        );

        $this->assertTrue($payload['available']);
        $this->assertSame('2026-07-24', $payload['data_period']);
        $this->assertCount(4, $payload['rows']);
        $this->assertSame('KC MADIUN', $payload['rows'][0]['cabang']);
        $this->assertSame(5, $payload['rows'][0]['lt_250']['deb']);
        $this->assertSame(600000000.0, $payload['rows'][0]['lt_250']['os']);
        $this->assertSame(3, $payload['rows'][0]['gt_250']['deb']);
        $this->assertSame(900000000.0, $payload['rows'][0]['gt_250']['os']);
        $this->assertSame(0, $payload['rows'][1]['total']['deb']);
        $this->assertSame('KC NGAWI', $payload['rows'][2]['cabang']);
        $this->assertSame(13, $payload['total']['total']['deb']);
        $this->assertSame(2350000000.0, $payload['total']['total']['os']);
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
            'cabang_normalized' => 'KC MADIUN',
            'cabang1' => 'KC MADIUN',
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

    private function insertBrihcPemasar(array $overrides): void
    {
        DB::table('brihc_pemasar')->insert(array_merge([
            'uniqueid_namareport' => 'MANTRI-' . uniqid(),
            'completename' => 'Mantri Test',
            'nip' => null,
            'pernr' => null,
            'esgdesc' => 'PT',
            'psadesc' => 'KC Madiun',
            'orgdesc' => 'Unit Test',
            'positiondesc' => 'Associate Mantri 1',
            'pn_mantri' => '6494 | 0001 - Mantri Test',
            'status' => null,
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
