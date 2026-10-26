<?php

namespace Tests\Unit;

use App\Http\Controllers\DashboardSimpananController;
use App\Http\Controllers\Report\KinerjaRmMikroReportController;
use App\Jobs\SyncImportedReportJob;
use App\Support\MantriSourceInputStore;
use App\Support\UserBranchScope;
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
    private array $inputDirectories = [];

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
            $table->string('cabang1')->nullable()->default('KC MADIUN');
            $table->string('rm_normalized')->nullable();
            $table->string('pn_pengelola1')->nullable();
            $table->string('pn_pemrakarsa1')->nullable();
            $table->string('nomor_rekening1')->nullable();
            $table->string('cifno')->nullable();
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
            $table->string('bc')->nullable();
            $table->string('positiondesc')->nullable();
            $table->string('pn_mantri')->nullable();
            $table->string('status')->nullable();
        });
        Schema::create('performance_rm_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('periode');
            $table->string('cabang')->nullable()->default('KC MADIUN');
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

    protected function tearDown(): void
    {
        foreach ($this->inputDirectories as $directory) {
            foreach (glob($directory.'/*') as $file) {
                unlink($file);
            }
            if (is_file($directory.'/.write.lock')) {
                unlink($directory.'/.write.lock');
            }
            rmdir($directory);
        }
        parent::tearDown();
    }

    public function test_verified_complete_source_archive_is_available_only_for_mantri_and_baseline_is_not_selectable(): void
    {
        $this->insertDailyLoan(['periode' => '2026-10-16', 'tgl_realisasi' => '2026-10-05']);
        DB::table('performance_rm_snapshots')->insert([
            'periode' => '2026-10-16', 'segmen' => 'MICRO', 'produk' => 'KUR-MIKRO',
        ]);
        $store = $this->inputStore();
        $store->write('2026-09-30', [
            'source' => ['source_kind' => 'manual_workbook_baseline'], 'complete_presence' => false,
            'presence' => [], 'bookings' => [], 'unclassified_accounts' => [],
        ]);
        $store->write('2026-10-17', $this->archivedPosition('2026-10-17'));
        file_put_contents($this->inputDirectories[0].'/2026-10-18.json.gz', 'invalid archive');
        $controller = new KinerjaRmMikroReportController;

        $this->assertSame(['2026-10-17', '2026-10-16'], $this->invokePrivateMethod($controller, 'fetchAvailablePeriods', true)->all());
        $this->assertSame(['2026-10-16'], $this->invokePrivateMethod($controller, 'fetchAvailablePeriods', false)->all());
        $this->assertSame('2026-10-17', $this->invokePrivateMethod($controller, 'resolveEmbeddedReadyPeriod', '2026-10-17', true));
        $this->assertSame('2026-10-16', $this->invokePrivateMethod($controller, 'resolveEmbeddedReadyPeriod', '2026-10-16', true));
        $this->assertNull($this->invokePrivateMethod($controller, 'resolveEmbeddedReadyPeriod', '2026-10-15', true));
        $this->assertSame('2026-10-16', $this->invokePrivateMethod($controller, 'resolveEmbeddedReadyPeriod', '2026-10-17', false));
    }

    public function test_archive_only_nett_payload_uses_its_exact_period_and_probation_contract_roster(): void
    {
        Queue::fake();
        $this->inputStore()->write('2026-10-17', $this->archivedPosition('2026-10-17'));
        $this->insertBrihcPemasar([
            'uniqueid_namareport' => 'reference_brihc_hc_1', 'pernr' => '1', 'pn_mantri' => '1',
            'completename' => 'Mantri Probation', 'positiondesc' => 'MANTRI', 'esgdesc' => 'Probation',
        ]);

        $payload = (new KinerjaRmMikroReportController)->buildEmbeddedPayload('nett_disbursement', '2026-10-17', true);
        $this->assertSame('2026-10-17', $payload['meta']['requested_period']);
        $this->assertSame('2026-10-17', $payload['meta']['data_period']);
        $this->assertFalse($payload['meta']['refresh_pending']);
        $this->assertSame(100_000_000.0, data_get($payload, 'nett_workbook.mantriPerformance.total.net_disbursement.amount'));
        $this->assertSame(1, data_get($payload, 'nett_workbook.mantriPerformance.total.headcount.contract'));
        $this->assertSame(0, data_get($payload, 'nett_workbook.mantriPerformance.total.headcount.pt_non_briguna'));
        $this->assertSame(0, DB::table('daily_loan_dinamis')->where('periode', '2026-10-17')->count());
        Queue::assertNothingPushed();
    }

    public function test_source_archive_write_invalidates_period_and_nett_caches_without_manual_cache_flush(): void
    {
        Queue::fake();
        $store = $this->inputStore();
        $controller = new KinerjaRmMikroReportController;
        $this->assertSame([], $this->invokePrivateMethod($controller, 'fetchAvailablePeriods', true)->all());
        $this->assertNull($this->invokePrivateMethod($controller, 'resolveEmbeddedReadyPeriod', '2026-12-19', true));
        $this->insertBrihcPemasar([
            'uniqueid_namareport' => 'reference_brihc_hc_1', 'pernr' => '1', 'pn_mantri' => '1',
            'completename' => 'Mantri Aktif', 'positiondesc' => 'MANTRI',
        ]);
        $position = $this->archivedPosition('2026-12-19');
        $store->write('2026-12-19', $position);
        $this->assertSame(['2026-12-19'], $this->invokePrivateMethod($controller, 'fetchAvailablePeriods', true)->all());
        $this->assertSame('2026-12-19', $this->invokePrivateMethod($controller, 'resolveEmbeddedReadyPeriod', '2026-12-19', true));
        $first = $controller->buildEmbeddedPayload('nett_disbursement', '2026-12-19', true);
        $this->assertSame(100_000_000.0, data_get($first, 'nett_workbook.mantriPerformance.total.net_disbursement.amount'));
        $position['bookings'][0]['plafon'] = 125_000_000;
        $store->write('2026-12-19', $position);
        $second = $controller->buildEmbeddedPayload('nett_disbursement', '2026-12-19', true);
        $this->assertSame(125_000_000.0, data_get($second, 'nett_workbook.mantriPerformance.total.net_disbursement.amount'));
        $this->assertSame(1, data_get($second, 'nett_workbook.mantriPerformance.total.net_disbursement.deb'));
        $this->assertSame('2026-12-19', $second['meta']['data_period']);
    }

    public function test_period_options_include_area6_micro_kur_snapshots_and_matching_daily_loan_source(): void
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

    public function test_source_period_discovery_bounds_text_filters_by_date_and_batches_queries(): void
    {
        $this->insertDailyLoan(['periode' => '2026-05-06', 'produk_kinerja' => 'KURMIKRO', 'description' => 'Kredit Mikro - KUR Ritel 2015']);
        $this->insertDailyLoan(['periode' => '2026-05-05', 'produk_kinerja' => 'KURMIKRO', 'description' => 'KUR Mikro Baru']);
        $this->insertDailyLoan(['periode' => '2026-05-07', 'produk_kinerja' => 'KURMIKRO', 'description' => 'Kredit Mikro - KUR Ritel 2015', 'cabang1' => 'KC SURABAYA']);

        DB::enableQueryLog();
        DB::flushQueryLog();
        $controller = new KinerjaRmMikroReportController;
        $this->assertSame(['2026-05-06'], $this->invokePrivateMethod($controller, 'fetchAvailablePeriods')->all());
        $sourceQueries = collect(DB::getQueryLog())->filter(fn ($query) => str_contains($query['query'], 'from "daily_loan_dinamis"'))->values();
        $this->assertCount(2, $sourceQueries);
        $this->assertStringNotContainsString('description', $sourceQueries[0]['query']);
        $this->assertStringContainsString('"periode" = ?', $sourceQueries[1]['query']);
        $this->assertStringContainsString('union all', $sourceQueries[1]['query']);

        DB::flushQueryLog();
        $this->assertSame(['2026-05-06'], $this->invokePrivateMethod($controller, 'fetchAvailablePeriods')->all());
        $this->assertSame([], DB::getQueryLog());
        DB::disableQueryLog();
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

    public function test_mantri_ready_period_requires_area6_nominative_and_discloses_earlier_source_date(): void
    {
        Queue::fake();
        $this->insertDailyLoan(['periode' => '2026-07-13', 'tgl_realisasi' => '2026-07-13']);
        $this->insertDailyLoan([
            'periode' => '2026-07-15', 'tgl_realisasi' => '2026-07-15',
            'cabang1' => 'KC BANYUWANGI', 'cabang_normalized' => 'KC BANYUWANGI',
        ]);
        DB::table('performance_rm_snapshots')->insert([
            'periode' => '2026-07-14', 'segmen' => 'MICRO', 'produk' => 'KUR-MIKRO',
        ]);
        $this->insertBrihcPemasar([
            'pernr' => '0001', 'pn_mantri' => '0001', 'esgdesc' => 'PT',
            'positiondesc' => 'MANTRI', 'psadesc' => 'KC MADIUN', 'orgdesc' => 'UNIT TEST',
        ]);
        $controller = new KinerjaRmMikroReportController;
        $this->assertSame(['2026-07-13'], $this->invokePrivateMethod($controller, 'fetchAvailablePeriods', true)->all());
        $payload = $controller->buildEmbeddedPayload('produktivitas_mantri', '2026-07-14', true);

        $this->assertSame('2026-07-14', $payload['meta']['requested_period']);
        $this->assertSame('2026-07-13', $payload['meta']['data_period']);
        $this->assertTrue($payload['meta']['refresh_pending']);
        $this->assertSame(100_000_000.0, (float) $payload['total']['realisasi_os']);
    }

    public function test_snapshot_without_mantri_nominative_is_not_reported_as_zero_productivity(): void
    {
        DB::table('performance_rm_snapshots')->insert([
            'periode' => '2026-07-14', 'segmen' => 'MICRO', 'produk' => 'KUR-MIKRO',
        ]);
        $controller = new KinerjaRmMikroReportController;
        $payload = $controller->buildEmbeddedPayload('nett_disbursement', '2026-07-14', true);

        $this->assertNull($payload['meta']['data_period']);
        $this->assertSame([], $payload['rows']);
        $this->assertSame([], $payload['total']);
        $this->assertSame('2026-07-14', $this->invokePrivateMethod($controller, 'resolveEmbeddedReadyPeriod', '2026-07-14', false));
    }

    public function test_nett_workbook_scopes_zero_realization_roster_and_cache_per_branch(): void
    {
        foreach (['MADIUN' => '1', 'NGAWI' => '2'] as $branch => $pn) {
            DB::table('brihc_pemasar')->insert(['uniqueid_namareport' => $pn, 'pernr' => $pn, 'completename' => 'Mantri '.$pn, 'positiondesc' => 'MANTRI', 'esgdesc' => 'PT', 'psadesc' => 'KC '.$branch, 'orgdesc' => 'UNIT '.$branch]);
        }
        $controller = new KinerjaRmMikroReportController();
        foreach (['madiun' => 'KC MADIUN', 'ngawi' => 'KC NGAWI'] as $scope => $branch) {
            auth()->setUser(new \Illuminate\Auth\GenericUser(['id' => $scope === 'madiun' ? '0045' : '0057']));
            $payload = $this->invokePrivateMethod($controller, 'nettWorkbookPayload', '2026-10-06', true);
            $workbook = $payload['nett_workbook']['mantriPerformance'];
            $this->assertCount(1, $workbook['sheet_rows']['bo']);
            $this->assertSame($branch, $workbook['sheet_rows']['bo'][0]['branch']);
            $this->assertSame(1, $workbook['total']['headcount']['pt_non_briguna']);
            $this->assertSame(0.0, $workbook['total']['net_disbursement']['amount']);
        }
    }

    public function test_mantri_defaults_to_nett_workbook_and_preserves_productivity_report(): void
    {
        $categories = (new ReflectionClass(KinerjaRmMikroReportController::class))
            ->getConstant('MANTRI_REPORT_CATEGORIES');

        $this->assertSame('nett_disbursement', array_key_first($categories));
        $this->assertArrayHasKey('produktivitas_mantri', $categories);
    }

    public function test_requested_mid_month_uses_exact_micro_kur_period_when_available(): void
    {
        DB::table('performance_rm_snapshots')->insert([
            ['periode' => '2026-05-15', 'segmen' => 'MICRO', 'produk' => 'KUR-MIKRO'],
            ['periode' => '2026-05-17', 'segmen' => 'MICRO', 'produk' => 'KUR-MIKRO'],
            ['periode' => '2026-04-30', 'segmen' => 'MICRO', 'produk' => 'KUR-MIKRO'],
        ]);

        $controller = new KinerjaRmMikroReportController();
        $periods = $this->invokePrivateMethod($controller, 'fetchAvailablePeriods');

        $this->assertSame(['2026-05-17', '2026-05-15', '2026-04-30'], $periods->all());
        $this->assertSame('2026-05-15', $this->invokePrivateMethod($controller, 'resolveSelectedPeriod', $periods, '2026-05-15'));
        $this->assertSame('2026-05-15', $this->invokePrivateMethod($controller, 'resolveSelectedPeriod', $periods, '2026-05-16'));
    }

    public function test_older_snapshot_does_not_mask_newer_ready_source_at_selected_date(): void
    {
        DB::table('performance_rm_snapshots')->insert([
            'periode' => '2026-07-23', 'segmen' => 'MICRO', 'produk' => 'KUR-MIKRO',
        ]);
        $this->insertDailyLoan([
            'periode' => '2026-07-24', 'produk_kinerja' => 'KURMIKRO',
            'description' => 'Kredit Mikro - KUR Ritel 2015',
            'nomor_rekening1' => 'READY-CURRENT', 'tgl_realisasi' => '2026-07-24',
        ]);
        $controller = new KinerjaRmMikroReportController();

        $this->assertSame('2026-07-24', $this->invokePrivateMethod($controller, 'resolveEmbeddedReadyPeriod', '2026-07-24', false));
        $this->assertSame('2026-07-23', $this->invokePrivateMethod($controller, 'resolveEmbeddedReadyPeriod', '2026-07-23', false));
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
        $this->assertSame(340000000.0, (float) $payload['total']['total_os']);
        $this->assertSame(2, (int) $payload['total']['lancar_deb']);
        $this->assertSame(500000000.0, (float) $payload['total']['realisasi_os']);
    }

    public function test_presentation_rm_kur_keeps_unassigned_value_as_rm_lain(): void
    {
        Queue::fake();
        DB::table('performance_rm_snapshots')->insert([
            [
                'periode' => '2026-07-24',
                'cabang' => 'KC MADIUN',
                'unit' => 'UNIT DAILY',
                'branch_code' => '001',
                'rm' => '00000001 - NAMA DAILY LAMA',
                'segmen' => 'MICRO',
                'produk' => 'KUR-MIKRO',
                'total_deb' => 1,
                'loan_os' => 125000000,
                'realisasi_deb' => 1,
                'realisasi_os' => 125000000,
            ],
            [
                'periode' => '2026-07-24',
                'cabang' => 'KC MADIUN',
                'unit' => 'UNIT DAILY',
                'branch_code' => '001',
                'rm' => '00000002 - DAILY BACKUP',
                'segmen' => 'MICRO',
                'produk' => 'KUR-MIKRO',
                'total_deb' => 1,
                'loan_os' => 100000000,
                'realisasi_deb' => 1,
                'realisasi_os' => 100000000,
            ],
        ]);
        $this->insertBrihcPemasar([
            'pernr' => '00000001',
            'completename' => 'NAMA BRIHC UTAMA',
            'positiondesc' => 'RM MIKRO',
            'psadesc' => 'KC Ngawi',
            'orgdesc' => 'UNIT BRIHC',
        ]);

        $controller = new KinerjaRmMikroReportController();
        $dailyAuthority = $this->invokePrivateMethod($controller, 'perRmPayload', '2026-07-24');
        $landingPayload = $this->invokePrivateMethod(
            new DashboardSimpananController(),
            'buildPresentationRmKurMicro',
            '2026-07-24'
        );

        $this->assertSame(
            'NAMA DAILY LAMA',
            data_get(collect($dailyAuthority['rows'])->firstWhere('pn', '00000001'), 'nama')
        );
        $brihcRow = collect($landingPayload['rows'])->firstWhere('nama', 'NAMA BRIHC UTAMA');
        $this->assertSame('KC NGAWI', data_get($brihcRow, 'cabang'));
        $this->assertSame('UNIT BRIHC', data_get($brihcRow, 'unit'));
        $this->assertSame(125000000.0, data_get($brihcRow, 'realisasi_os'));
        $this->assertNull(collect($landingPayload['rows'])->firstWhere('nama', 'DAILY BACKUP'));
        $this->assertSame(100000000.0, (float) data_get(
            collect($landingPayload['rows'])->firstWhere('nama', 'RM lain'),
            'realisasi_os'
        ));

        $rekap = $this->invokePrivateMethod($controller, 'rekapPayload', '2026-07-24');
        $this->assertSame(225000000.0, (float) data_get($rekap, 'total.realisasi_os'));
        $this->assertSame(2, (int) data_get($rekap, 'total.total_rm'));
    }

    public function test_mantri_payload_excludes_briguna_and_rm_kur_ritel_products(): void
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

    public function test_mantri_productivity_payload_uses_initiator_without_approved_evidence(): void
    {
        $this->insertDailyLoan([
            'pn_pengelola1' => '0888 - Pengelola Lama',
            'pn_pemrakarsa1' => '0001 - Mantri Satu',
            'rm_normalized' => '0888 - PENGELOLA LAMA',
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

    public function test_mantri_productivity_uses_growth_roster_and_excludes_briguna_and_collection_roles(): void
    {
        $this->insertBrihcPemasar([
            'uniqueid_namareport' => 'ACTIVE-MANTRI-1',
            'pernr' => '1',
            'pn_mantri' => '1',
            'completename' => 'Mantri Aktif',
            'positiondesc' => 'MANTRI',
        ]);
        $this->insertBrihcPemasar([
            'uniqueid_namareport' => 'ACTIVE-MANTRI-BRIGUNA-3',
            'pernr' => '3',
            'pn_mantri' => '3',
            'completename' => 'Mantri Briguna Aktif',
            'positiondesc' => 'MANTRI BRIGUNA',
        ]);
        $this->insertBrihcPemasar([
            'uniqueid_namareport' => 'CHANGED-MANTRI-COLLECTION-2',
            'pernr' => '2',
            'pn_mantri' => '2',
            'positiondesc' => 'MANTRI COLLECTION',
        ]);

        foreach ([
            ['pn' => '0001', 'name' => 'Mantri Aktif', 'account' => 'ACTIVE-1'],
            ['pn' => '0002', 'name' => 'Mantri Collection', 'account' => 'COLLECTION-1'],
            ['pn' => '0003', 'name' => 'Mantri Briguna Aktif', 'account' => 'BRIGUNA-1'],
        ] as $fixture) {
            $this->insertDailyLoan([
                'pn_pengelola1' => $fixture['pn'].' - '.$fixture['name'],
                'rm_normalized' => $fixture['pn'].' - '.strtoupper($fixture['name']),
                'nomor_rekening1' => $fixture['account'],
                'plafon' => 100000000,
            ]);
        }
        $this->insertDailyLoan([
            'branch_normalized' => '002',
            'unit_normalized' => 'UNIT LAMA',
            'unit1' => 'UNIT LAMA',
            'pn_pengelola1' => '0001 - Mantri Aktif',
            'rm_normalized' => '0001 - MANTRI AKTIF',
            'nomor_rekening1' => 'ACTIVE-2',
            'plafon' => 50000000,
        ]);

        $controller = new KinerjaRmMikroReportController();
        $roster = $this->invokePrivateMethod($controller, 'activeMantriRosterState');
        $sourceRows = $this->invokePrivateMethod($controller, 'mantriSourceQuery', '2026-05-06')->get();

        $this->assertSame(['1'], $roster['keys']);
        $this->assertSame(['1'], $sourceRows->pluck('owner_pn')->unique()->sort()->values()->all());

        $payload = $this->invokePrivateMethod($controller, 'mantriProductivityPayload', '2026-05-06');
        $names = collect($payload['rows'])->pluck('nama_mantri')->all();

        $this->assertSame(['Mantri Aktif'], $names);
        $this->assertNotContains('Mantri Collection', $names);
        $this->assertNotContains('Mantri Briguna Aktif', $names);
        $this->assertSame(1, (int) $payload['total']['jumlah_mantri']);
        $this->assertSame(150000000.0, (float) $payload['total']['realisasi_os']);
        $this->assertSame(150000000.0, (float) collect($payload['rows'])->firstWhere('pn_mantri', '00000001')['realisasi_os']);
    }

    public function test_mantri_hc_productivity_and_quadrants_include_new_roster_and_use_latest_assignment(): void
    {
        foreach ([
            ['uniqueid_namareport' => 'reference_brihc_hc_1', 'pernr' => '1', 'pn_mantri' => '1', 'completename' => 'Mantri Pindah HC', 'psadesc' => 'KC Ngawi', 'orgdesc' => 'UNIT NGAWI BARU', 'bc' => '3201'],
            ['uniqueid_namareport' => 'reference_brihc_hc_2', 'pernr' => '2', 'pn_mantri' => '2', 'completename' => 'Mantri Baru Nol', 'psadesc' => 'KC Ngawi', 'orgdesc' => 'UNIT NGAWI BARU', 'bc' => '3201'],
            ['uniqueid_namareport' => 'reference_brihc_hc_3', 'pernr' => '3', 'pn_mantri' => '3', 'completename' => 'Recovery Terpisah', 'positiondesc' => 'MANTRI RECOVERY', 'psadesc' => 'KC Ngawi', 'orgdesc' => 'UNIT NGAWI BARU', 'bc' => '3201'],
            ['uniqueid_namareport' => 'reference_brihc_hc_4', 'pernr' => '4', 'pn_mantri' => '4', 'completename' => 'Mantri Briguna HC', 'positiondesc' => 'MANTRI BRIGUNA', 'psadesc' => 'KC Magetan', 'orgdesc' => 'KC MAGETAN', 'bc' => '49'],
        ] as $reference) {
            $this->insertBrihcPemasar($reference);
        }
        $this->insertDailyLoan([
            'periode' => '2026-10-13', 'tgl_realisasi' => '2026-10-05', 'pn_pengelola1' => '0001 - Nama Daily Lama',
            'rm_normalized' => '0001 - NAMA DAILY LAMA', 'nomor_rekening1' => 'HC-PRODUCTIVE', 'plafon' => 150_000_000,
        ]);
        $this->insertDailyLoan([
            'periode' => '2026-10-13', 'tgl_realisasi' => '2026-10-05', 'pn_pengelola1' => '0003 - Recovery Terpisah',
            'rm_normalized' => '0003 - RECOVERY TERPISAH', 'nomor_rekening1' => 'HC-RECOVERY', 'plafon' => 500_000_000,
        ]);
        $this->insertDailyLoan([
            'periode' => '2026-10-13', 'tgl_realisasi' => '2026-10-05', 'pn_pengelola1' => '0004 - Nama Daily Briguna',
            'rm_normalized' => '0004 - NAMA DAILY BRIGUNA', 'nomor_rekening1' => 'HC-BRIGUNA', 'plafon' => 100_000_000,
        ]);
        $controller = new KinerjaRmMikroReportController;
        $payload = $this->invokePrivateMethod($controller, 'mantriProductivityPayload', '2026-10-13');
        $rows = collect($payload['rows'])->keyBy('pn_mantri');

        $this->assertSame(2, (int) $payload['total']['jumlah_mantri']);
        $this->assertSame(150_000_000.0, (float) $payload['total']['realisasi_os']);
        $this->assertSame('Mantri Pindah HC', $rows->get('00000001')['nama_mantri']);
        $this->assertSame('KC NGAWI', $rows->get('00000001')['cabang']);
        $this->assertSame('UNIT NGAWI BARU', $rows->get('00000001')['unit']);
        $this->assertSame('3201', $rows->get('00000001')['bc']);
        $this->assertSame(150_000_000.0, (float) $rows->get('00000001')['realisasi_os']);
        $this->assertSame('Mantri Baru Nol', $rows->get('00000002')['nama_mantri']);
        $this->assertSame(0, (int) $rows->get('00000002')['realisasi_deb']);
        $this->assertSame(0.0, (float) $rows->get('00000002')['realisasi_os']);
        $this->assertNull($rows->get('00000004'));
        $this->assertNull($rows->get('00000003'));

        $quadrant = $this->invokePrivateMethod($controller, 'mantriKuadranPayload', '2026-10-13');
        $ngawi = collect($quadrant['rows'])->firstWhere('unit', 'UNIT NGAWI BARU');
        $this->assertSame(2, (int) $ngawi['jumlah_mantri']);
        $this->assertSame(2, (int) $quadrant['total']['jumlah_mantri']);
        $this->assertGreaterThanOrEqual(1, (int) $ngawi['kuadran_4']);
    }

    public function test_other_roles_with_same_pn_cannot_recategorize_growth_mantri_and_source_references_remain_intact(): void
    {
        foreach ([
            ['uniqueid_namareport' => 'reference_brihc_hc_growth_1', 'pernr' => '1', 'pn_mantri' => '1', 'completename' => 'Mantri Tumbuh PT', 'esgdesc' => 'PT'],
            ['uniqueid_namareport' => 'reference_brihc_hc_growth_2', 'pernr' => '2', 'pn_mantri' => '2', 'completename' => 'Mantri Tumbuh Kontrak', 'esgdesc' => 'KONTRAK'],
            ['uniqueid_namareport' => 'legacy_briguna_same_pn_1', 'pernr' => '1', 'pn_mantri' => '1', 'completename' => 'Legacy Briguna PT', 'positiondesc' => 'MANTRI BRIGUNA', 'esgdesc' => 'KONTRAK'],
            ['uniqueid_namareport' => 'legacy_briguna_same_pn_2', 'pernr' => '2', 'pn_mantri' => '2', 'completename' => 'Legacy Briguna Kontrak', 'positiondesc' => 'MANTRI BRIGUNA', 'esgdesc' => 'PT'],
            ['uniqueid_namareport' => 'reference_brihc_hc_briguna_3', 'pernr' => '3', 'pn_mantri' => '3', 'completename' => 'Briguna Terpisah', 'positiondesc' => 'MANTRI BRIGUNA'],
            ['uniqueid_namareport' => 'reference_brihc_hc_recovery_4', 'pernr' => '4', 'pn_mantri' => '4', 'completename' => 'Recovery Terpisah', 'positiondesc' => 'MANTRI RECOVERY'],
        ] as $reference) {
            $this->insertBrihcPemasar($reference);
        }
        $before = DB::table('brihc_pemasar')->orderBy('uniqueid_namareport')->get()->all();
        $controller = new KinerjaRmMikroReportController;
        $roster = $this->invokePrivateMethod($controller, 'activeMantriRosterState');
        $this->assertSame(['1', '2'], $roster['keys']);
        $this->assertSame('pt', $roster['people']['1']['category']);
        $this->assertSame('contract', $roster['people']['2']['category']);
        $landingRoster = $this->invokePrivateMethod(app(\App\Support\LandingMicroPerformanceService::class), 'mantriRoster', null);
        $this->assertCount(2, $landingRoster);
        $this->assertSame('pt', $landingRoster->firstWhere('pn', '1')['category']);
        $this->assertSame('contract', $landingRoster->firstWhere('pn', '2')['category']);

        $payload = $this->invokePrivateMethod($controller, 'nettWorkbookPayload', '2026-10-13', true);
        $headcount = $payload['nett_workbook']['mantriPerformance']['total']['headcount'];
        $this->assertSame(1, $headcount['pt_non_briguna']);
        $this->assertSame(1, $headcount['contract']);
        $this->assertSame(0, $headcount['briguna']);
        $this->assertEquals($before, DB::table('brihc_pemasar')->orderBy('uniqueid_namareport')->get()->all());
    }

    public function test_mantri_hc_recap_counts_complete_current_roster_without_changing_realized_metrics_or_legacy_counts(): void
    {
        foreach ([
            ['uniqueid_namareport' => 'reference_brihc_hc_1', 'pernr' => '1', 'pn_mantri' => '1', 'completename' => 'HC Aktif', 'psadesc' => 'KC Ngawi', 'orgdesc' => 'UNIT NGAWI A', 'bc' => '3201'],
            ['uniqueid_namareport' => 'reference_brihc_hc_2', 'pernr' => '2', 'pn_mantri' => '2', 'completename' => 'HC Nol Satu', 'psadesc' => 'KC Ngawi', 'orgdesc' => 'UNIT NGAWI A', 'bc' => '3201'],
            ['uniqueid_namareport' => 'reference_brihc_hc_3', 'pernr' => '3', 'pn_mantri' => '3', 'completename' => 'HC Nol Dua', 'psadesc' => 'KC Ngawi', 'orgdesc' => 'UNIT NGAWI B', 'bc' => '3202'],
            ['uniqueid_namareport' => 'reference_brihc_hc_4', 'pernr' => '4', 'pn_mantri' => '4', 'completename' => 'HC Cabang Tanpa Rekening', 'psadesc' => 'KC Magetan', 'orgdesc' => 'UNIT MAGETAN', 'bc' => '3203'],
            ['uniqueid_namareport' => 'legacy-mantri-5', 'pernr' => '5', 'pn_mantri' => '5', 'completename' => 'Legacy Aktif', 'psadesc' => 'KC Madiun', 'orgdesc' => 'UNIT MADIUN A', 'bc' => '3204'],
            ['uniqueid_namareport' => 'legacy-mantri-6', 'pernr' => '6', 'pn_mantri' => '6', 'completename' => 'Legacy Nol', 'psadesc' => 'KC Madiun', 'orgdesc' => 'UNIT MADIUN B', 'bc' => '3205'],
        ] as $reference) {
            $this->insertBrihcPemasar($reference);
        }
        $this->insertDailyLoan([
            'periode' => '2026-10-13', 'tgl_realisasi' => '2026-10-05', 'pn_pengelola1' => '0001 - HC Aktif',
            'rm_normalized' => '0001 - HC AKTIF', 'nomor_rekening1' => 'REK-HC', 'plafon' => 100_000_000,
            'cabang1' => 'KC NGAWI', 'cabang_normalized' => 'KC NGAWI',
            'unit1' => 'UNIT NGAWI A', 'unit_normalized' => 'UNIT NGAWI A', 'branch1' => '3201', 'branch_normalized' => '3201',
        ]);
        $this->insertDailyLoan([
            'periode' => '2026-10-13', 'tgl_realisasi' => '2026-10-05', 'pn_pengelola1' => '0005 - Legacy Aktif',
            'rm_normalized' => '0005 - LEGACY AKTIF', 'nomor_rekening1' => 'REK-LEGACY', 'plafon' => 50_000_000,
            'unit1' => 'UNIT MADIUN A', 'unit_normalized' => 'UNIT MADIUN A', 'branch1' => '3204', 'branch_normalized' => '3204',
        ]);
        $payload = $this->invokePrivateMethod(new KinerjaRmMikroReportController, 'mantriRekapPayload', '2026-10-13');
        $rows = collect($payload['rows'])->keyBy('cabang');
        $this->assertCount(3, $rows);
        $this->assertSame(3, (int) $rows->get('KC NGAWI')['jumlah_mantri']);
        $this->assertSame(2, (int) $rows->get('KC NGAWI')['jumlah_unit']);
        $this->assertSame(100_000_000.0, (float) $rows->get('KC NGAWI')['total_realisasi_os']);
        $this->assertEqualsWithDelta(100 / 3 / $payload['working_days'], $rows->get('KC NGAWI')['ratas_mantri_hk'], 0.00001);
        $this->assertSame(1, (int) $rows->get('KC MAGETAN')['jumlah_mantri']);
        $this->assertSame(1, (int) $rows->get('KC MAGETAN')['jumlah_unit']);
        $this->assertSame(0, (int) $rows->get('KC MAGETAN')['total_realisasi_deb']);
        $this->assertSame(0.0, (float) $rows->get('KC MAGETAN')['total_realisasi_os']);
        $this->assertSame(0.0, (float) $rows->get('KC MAGETAN')['ratas_mantri_hk']);
        $this->assertSame(1, (int) $rows->get('KC MADIUN')['jumlah_mantri']);
        $this->assertSame(1, (int) $rows->get('KC MADIUN')['jumlah_unit']);
        $this->assertSame(5, (int) $payload['total']['jumlah_mantri']);
        $this->assertSame(4, (int) $payload['total']['jumlah_unit']);
        $this->assertSame(2, (int) $payload['total']['total_realisasi_deb']);
        $this->assertSame(150_000_000.0, (float) $payload['total']['total_realisasi_os']);
        $this->assertSame(2, (int) $payload['total']['kaunit_pdwk_deb']);
        $this->assertSame(150_000_000.0, (float) $payload['total']['kaunit_pdwk_os']);
        foreach (['kaunit_override_os', 'mbm_total_os', 'pinca_total_os', 'rmbh_override_os'] as $metric) {
            $this->assertSame(0.0, (float) $payload['total'][$metric], $metric);
        }
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

    public function test_extreme_low_mantri_payload_uses_active_brihc_mantri_role_and_pt_unit_status(): void
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
        $this->assertSame(3, (int) $unitRow['total_mantri']);
        $this->assertSame(1, (int) $unitRow['buckets']['none']['deb']);
        $this->assertSame(1, (int) $unitRow['buckets']['el_0_100']['deb']);
        $this->assertSame(1, (int) $unitRow['buckets']['el_200_400']['deb']);
        $this->assertSame(0, (int) $unitRow['buckets']['mid_1000_1200']['deb']);
        $this->assertSame(2, (int) $unitRow['extreme_low']['deb']);
        $this->assertSame(3, (int) $unitRow['under_800']['deb']);
        $this->assertSame(3, (int) $payload['total']['total_mantri']);
        $this->assertSame(1, (int) $payload['total']['buckets']['none']['deb']);
        $this->assertSame(2, (int) $payload['total']['extreme_low']['deb']);

        $branchPayload = $this->invokePrivateMethod(new KinerjaRmMikroReportController(), 'mantriExtremeLowPayload', '2026-05-31', 'per_cabang');
        $branchRow = collect($branchPayload['rows'])->firstWhere('branch_office', 'KC MADIUN');

        $this->assertSame('per_cabang', $branchPayload['view']);
        $this->assertNotNull($branchRow);
        $this->assertSame(3, (int) $branchRow['total_mantri']);
        $this->assertSame(1, (int) $branchRow['buckets']['none']['deb']);
        $this->assertSame(1, (int) $branchRow['buckets']['el_0_100']['deb']);
        $this->assertSame(1, (int) $branchRow['buckets']['el_200_400']['deb']);
        $this->assertSame(0, (int) $branchRow['buckets']['mid_1000_1200']['deb']);
        $this->assertSame(2, (int) $branchRow['extreme_low']['deb']);
        $this->assertSame(3, (int) $branchRow['under_800']['deb']);
        $this->assertEqualsWithDelta(33.3333, $branchRow['buckets']['none']['pct'], 0.0001);
        $this->assertEqualsWithDelta(33.3333, $branchRow['buckets']['el_0_100']['pct'], 0.0001);
        $this->assertEqualsWithDelta(66.6667, $branchRow['extreme_low']['pct'], 0.0001);
        $this->assertEqualsWithDelta(100.0, $branchRow['under_800']['pct'], 0.0001);
    }

    public function test_extreme_low_hc_roster_uses_latest_assignment_and_includes_zero_account_pt_people(): void
    {
        foreach ([
            ['uniqueid_namareport' => 'reference_brihc_hc_1', 'pernr' => '1', 'pn_mantri' => '1', 'completename' => 'Nama HC Terbaru', 'psadesc' => 'KC Ngawi', 'orgdesc' => 'UNIT NGAWI BARU'],
            ['uniqueid_namareport' => 'reference_brihc_hc_2', 'pernr' => '2', 'pn_mantri' => '2', 'completename' => 'Mantri Tanpa Rekening', 'psadesc' => 'KC Ngawi', 'orgdesc' => 'UNIT NGAWI BARU'],
            ['uniqueid_namareport' => 'reference_brihc_hc_3', 'pernr' => '3', 'pn_mantri' => '3', 'completename' => 'Mantri Kontrak', 'esgdesc' => 'KONTRAK', 'psadesc' => 'KC Ngawi', 'orgdesc' => 'UNIT NGAWI BARU'],
            ['uniqueid_namareport' => 'reference_brihc_hc_4', 'pernr' => '4', 'pn_mantri' => '4', 'completename' => 'Mantri Recovery', 'positiondesc' => 'MANTRI RECOVERY', 'psadesc' => 'KC Ngawi', 'orgdesc' => 'UNIT NGAWI BARU'],
            ['uniqueid_namareport' => 'legacy-mantri', 'pernr' => '5', 'pn_mantri' => '5', 'completename' => 'Nama Legacy', 'psadesc' => 'KC Madiun', 'orgdesc' => 'UNIT LEGACY'],
        ] as $reference) {
            $this->insertBrihcPemasar($reference);
        }
        $this->insertDailyLoan([
            'periode' => '2026-10-13', 'tgl_realisasi' => '2026-10-05', 'pn_pengelola1' => '0001 - Nama Loan Lama',
            'rm_normalized' => '0001 - NAMA LOAN LAMA', 'nomor_rekening1' => 'HC-OLD',
            'unit1' => 'UNIT MADIUN LAMA', 'unit_normalized' => 'UNIT MADIUN LAMA', 'plafon' => 50_000_000,
        ]);
        $this->insertDailyLoan([
            'periode' => '2026-10-13', 'tgl_realisasi' => '2026-10-05', 'pn_pengelola1' => '0005 - Nama Daily Legacy',
            'rm_normalized' => '0005 - NAMA DAILY LEGACY', 'nomor_rekening1' => 'LEGACY',
            'unit1' => 'UNIT MADIUN DAILY', 'unit_normalized' => 'UNIT MADIUN DAILY', 'plafon' => 150_000_000,
        ]);
        $controller = new KinerjaRmMikroReportController;
        $roster = $this->invokePrivateMethod($controller, 'extremeLowMantriRosterRows', '2026-10-13');
        $hc = $roster->firstWhere('nama_mantri', 'Nama HC Terbaru');
        $zero = $roster->firstWhere('nama_mantri', 'Mantri Tanpa Rekening');

        $this->assertCount(3, $roster);
        $this->assertNotNull($hc);
        $this->assertSame('KC NGAWI', $hc['branch_office']);
        $this->assertSame('UNIT NGAWI BARU', $hc['nama_uker']);
        $this->assertSame(50_000_000.0, (float) $hc['realisasi_os']);
        $this->assertNotNull($zero);
        $this->assertSame(0.0, (float) $zero['realisasi_os']);
        $this->assertSame('UNIT MADIUN DAILY', $roster->firstWhere('nama_mantri', 'Nama Daily Legacy')['nama_uker']);
        $this->assertNull($roster->firstWhere('nama_mantri', 'Mantri Kontrak'));
        $this->assertNull($roster->firstWhere('nama_mantri', 'Mantri Recovery'));

        $payload = $this->invokePrivateMethod($controller, 'mantriExtremeLowPayload', '2026-10-13', 'per_unit_kerja');
        $ngawi = collect($payload['rows'])->firstWhere('nama_uker', 'UNIT NGAWI BARU');
        $this->assertSame(2, (int) $ngawi['total_mantri']);
        $this->assertSame(1, (int) $ngawi['buckets']['none']['deb']);
        $this->assertSame(1, (int) $ngawi['buckets']['el_0_100']['deb']);
        $this->assertSame(50.0, (float) $ngawi['extreme_low']['pct']);
        $this->assertSame(100.0, (float) $ngawi['under_800']['pct']);
        $this->assertSame(3, (int) $payload['total']['total_mantri']);
    }

    public function test_extreme_low_mantri_view_offers_unit_kerja_and_branch_modes_without_click_detail(): void
    {
        $view = file_get_contents(resource_path('views/report/dashboard-pinjaman/_kinerjarmmikro_partials/_table_mantri_extreme_low.blade.php'));

        $this->assertIsString($view);
        $this->assertStringContainsString('extreme_low_view', $view);
        $this->assertStringContainsString('Unit Kerja', $view);
        $this->assertStringContainsString('Cabang', $view);
        $this->assertStringContainsString('Total Mantri Extreme Low', $view);
        $this->assertStringContainsString('Belum Realisasi', $view);
        $this->assertStringContainsString('Jumlah Mantri pada setiap kategori realisasi', $view);
        $this->assertStringNotContainsString('Per Mantri', $view);
        $this->assertStringNotContainsString('data-mantri-extreme-detail', $view);
        $this->assertStringNotContainsString("row.addEventListener('click'", $view);
    }

    public function test_extreme_low_mantri_separates_exact_zero_without_changing_positive_or_negative_boundaries(): void
    {
        $controller = new KinerjaRmMikroReportController();

        foreach ([
            [-1, 'el_0_100'],
            [0, 'none'],
            [1, 'el_0_100'],
            [100000000, 'el_0_100'],
            [100000001, 'el_100_200'],
            [400000000, 'el_200_400'],
            [400000001, 'low_400_600'],
        ] as [$amount, $expected]) {
            $this->assertSame($expected, $this->invokePrivateMethod($controller, 'extremeLowBucketKey', (float) $amount), (string) $amount);
        }
    }

    public function test_zero_mantri_stay_in_headcount_and_under_800_but_have_separate_details_in_each_branch_scope(): void
    {
        $fixtures = [
            ['pn' => '0101', 'name' => 'Madiun Belum', 'branch' => 'KC MADIUN', 'amount' => 0],
            ['pn' => '0102', 'name' => 'Madiun Satu Rupiah', 'branch' => 'KC MADIUN', 'amount' => 1],
            ['pn' => '0103', 'name' => 'Madiun Seratus Juta', 'branch' => 'KC MADIUN', 'amount' => 100000000],
            ['pn' => '0104', 'name' => 'Madiun Di Atas Seratus', 'branch' => 'KC MADIUN', 'amount' => 100000001],
            ['pn' => '0105', 'name' => 'Ngawi Belum', 'branch' => 'KC NGAWI', 'amount' => 0],
            ['pn' => '0106', 'name' => 'Ngawi Positif', 'branch' => 'KC NGAWI', 'amount' => 200000000],
        ];
        foreach ($fixtures as $fixture) {
            $this->insertBrihcPemasar([
                'uniqueid_namareport' => 'reference_brihc_hc_bucket_'.$fixture['pn'],
                'pernr' => $fixture['pn'], 'pn_mantri' => $fixture['pn'],
                'completename' => $fixture['name'], 'psadesc' => $fixture['branch'],
                'orgdesc' => 'UNIT '.$fixture['branch'],
            ]);
            if ($fixture['amount'] === 0) {
                continue;
            }
            $this->insertDailyLoan([
                'periode' => '2026-05-31', 'tgl_realisasi' => '2026-05-15',
                'pn_pengelola1' => $fixture['pn'].' - '.$fixture['name'],
                'rm_normalized' => $fixture['pn'].' - '.strtoupper($fixture['name']),
                'nomor_rekening1' => 'BUCKET-'.$fixture['pn'],
                'cabang1' => $fixture['branch'], 'cabang_normalized' => $fixture['branch'],
                'unit1' => 'UNIT '.$fixture['branch'], 'unit_normalized' => 'UNIT '.$fixture['branch'],
                'plafon' => $fixture['amount'], 'baki_debet1' => $fixture['amount'],
            ]);
        }
        $controller = new KinerjaRmMikroReportController();
        $area = UserBranchScope::runAs(null, fn () => $this->invokePrivateMethod($controller, 'mantriExtremeLowPayload', '2026-05-31', 'per_cabang'));

        $this->assertSame(['label' => 'Belum Realisasi', 'group' => 'none'], $area['bucket_meta']['none']);
        $this->assertSame(6, $area['total']['total_mantri']);
        $this->assertSame(2, $area['total']['buckets']['none']['deb']);
        $this->assertSame(4, $area['total']['extreme_low']['deb']);
        $this->assertSame(6, $area['total']['under_800']['deb']);
        $this->assertEqualsWithDelta(33.3333, $area['total']['buckets']['none']['pct'], 0.0001);
        $this->assertCount(6, $area['details']);
        $this->assertEqualsCanonicalizing(['Madiun Belum', 'Ngawi Belum'], collect($area['details'])->where('bucket_key', 'none')->pluck('nama')->all());
        $this->assertSame('el_0_100', collect($area['details'])->firstWhere('nama', 'Madiun Satu Rupiah')['bucket_key']);
        $this->assertSame('el_100_200', collect($area['details'])->firstWhere('nama', 'Madiun Di Atas Seratus')['bucket_key']);

        foreach ([['madiun', 'KC MADIUN', 4, 3], ['ngawi', 'KC NGAWI', 2, 1]] as [$scope, $branch, $headcount, $extremeCount]) {
            $payload = UserBranchScope::runAs(UserBranchScope::forKey($scope), fn () => $this->invokePrivateMethod($controller, 'mantriExtremeLowPayload', '2026-05-31', 'per_cabang'));
            $this->assertCount(1, $payload['rows']);
            $this->assertSame($branch, $payload['rows'][0]['branch_office']);
            $this->assertSame($headcount, $payload['total']['total_mantri']);
            $this->assertSame(1, $payload['total']['buckets']['none']['deb']);
            $this->assertSame($extremeCount, $payload['total']['extreme_low']['deb']);
            $this->assertSame($headcount, $payload['total']['under_800']['deb']);
            $this->assertSame($headcount, collect($payload['total']['buckets'])->sum('deb'));
            $this->assertSame([$branch], collect($payload['details'])->pluck('branch_office')->unique()->values()->all());
            $this->assertCount($headcount, $payload['details']);
            $this->assertCount(1, collect($payload['details'])->where('bucket_key', 'none'));
        }
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
                'cabang' => 'KC MADIUN',
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
                'cabang' => 'KC MADIUN',
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

    public function test_landing_rm_kur_productivity_respects_selected_branch_and_recalculates_totals(): void
    {
        Queue::fake();

        foreach ([['0001 - RM SATU', 'KC MADIUN', 2, 250_000_000], ['0002 - RM DUA', 'KC MADIUN', 1, 250_000_000], ['0003 - RM NGAWI', 'KC NGAWI', 4, 200_000_000]] as [$rm, $branch, $count, $amount]) {
            for ($index = 0; $index < $count; $index++) {
                $this->insertDailyLoan([
                    'periode' => '2026-07-24', 'tgl_realisasi' => '2026-07-24',
                    'nomor_rekening1' => 'BOOKING:'.$rm.':'.$index, 'cifno' => 'CIF:'.$rm.':'.$index,
                    'pn_pengelola1' => $rm, 'cabang1' => $branch, 'cabang_normalized' => $branch,
                    'segmen_kinerja' => 'MICRO', 'produk_kinerja' => 'KURKECIL',
                    'description' => 'Kredit Mikro - KUR Ritel 2015', 'plafon' => $amount,
                ]);
            }
        }

        DB::table('performance_rm_snapshots')->insert([
            [
                'periode' => '2026-07-24',
                'cabang' => 'KC MADIUN',
                'unit' => 'FUNGSI BISNIS MIKRO',
                'branch_code' => '45',
                'rm' => '0001 - RM SATU',
                'segmen' => 'MICRO',
                'produk' => 'KUR-MIKRO',
                'realisasi_deb' => 2,
                'realisasi_os' => 500000000,
            ],
            [
                'periode' => '2026-07-24',
                'cabang' => 'KC MADIUN',
                'unit' => 'KC MADIUN',
                'branch_code' => '45',
                'rm' => '0002 - RM DUA',
                'segmen' => 'MICRO',
                'produk' => 'KUR-MIKRO',
                'realisasi_deb' => 1,
                'realisasi_os' => 250000000,
            ],
            [
                'periode' => '2026-07-24',
                'cabang' => 'KC NGAWI',
                'unit' => 'FUNGSI BISNIS MIKRO',
                'branch_code' => '57',
                'rm' => '0003 - RM NGAWI',
                'segmen' => 'MICRO',
                'produk' => 'KUR-MIKRO',
                'realisasi_deb' => 4,
                'realisasi_os' => 800000000,
            ],
        ]);

        $payload = $this->invokePrivateMethod(
            new DashboardSimpananController,
            'buildLandingRmKurProductivity',
            '2026-07-24',
            UserBranchScope::forKey('madiun')
        );

        $this->assertTrue($payload['available']);
        $this->assertSame('2026-07-24', $payload['data_period']);
        $this->assertSame('KC Madiun', $payload['scope_label']);
        $this->assertSame(['RM SATU', 'RM DUA'], collect($payload['rows'])->pluck('nama')->all());
        $this->assertNotContains('RM NGAWI', collect($payload['rows'])->pluck('nama')->all());
        $this->assertSame(2, $payload['total']['rm_count']);
        $this->assertSame(3, $payload['total']['realisasi_deb']);
        $this->assertSame(750000000.0, $payload['total']['realisasi_os']);
        $this->assertSame(375000000.0, $payload['total']['average_per_rm']);
        $this->assertSame(250000000.0, $payload['total']['average_per_debtor']);
    }

    public function test_landing_rm_zero_nett_keeps_booking_count_and_gross_realization(): void
    {
        Queue::fake();
        $common = ['segmen_kinerja' => 'MICRO', 'produk_kinerja' => 'KURKECIL',
            'description' => 'Kredit Mikro - KUR Ritel 2015', 'pn_pengelola1' => '0001 - RM SATU', 'cifno' => 'CIF-EQUAL'];
        $this->insertDailyLoan(array_merge($common, ['periode' => '2026-06-30', 'tgl_realisasi' => '2026-06-05',
            'nomor_rekening1' => 'OLD', 'baki_debet1' => 30_000_000, 'plafon' => 30_000_000]));
        $this->insertDailyLoan(array_merge($common, ['periode' => '2026-07-24', 'tgl_realisasi' => '2026-07-24',
            'nomor_rekening1' => 'NEW', 'baki_debet1' => 30_000_000, 'plafon' => 30_000_000]));
        $controller = new DashboardSimpananController;
        $net = $this->invokePrivateMethod($controller, 'buildLandingRmKurProductivityFresh', '2026-07-24', null);
        $this->assertTrue($net['available']);
        $this->assertCount(1, $net['rows']);
        $this->assertSame(1, $net['total']['realisasi_deb']);
        $this->assertSame(0.0, $net['total']['realisasi_os']);
        $gross = app(KinerjaRmMikroReportController::class)->buildEmbeddedPayload('per_rm', '2026-07-24', false);
        $this->assertSame(30_000_000.0, (float) $gross['total']['realisasi_os']);
    }

    private function insertDailyLoan(array $overrides): void
    {
        foreach (['pn_pengelola1', 'pn_pemrakarsa1'] as $column) {
            if (isset($overrides[$column]) && preg_match('/^(\d+)\s*(-.*)?$/', $overrides[$column], $matches)) {
                $overrides[$column] = str_pad($matches[1], 8, '0', STR_PAD_LEFT).(isset($matches[2]) ? ' '.$matches[2] : '');
            }
        }
        if (array_key_exists('pn_pengelola1', $overrides) && !array_key_exists('pn_pemrakarsa1', $overrides)) {
            $overrides['pn_pemrakarsa1'] = $overrides['pn_pengelola1'];
        }

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
            'pn_pengelola1' => '00000001 - Mantri Satu',
            'pn_pemrakarsa1' => '00000001 - Mantri Satu',
            'nomor_rekening1' => 'TEST-1',
            'cifno' => 'CIF-' . uniqid(),
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
            'positiondesc' => 'MANTRI',
            'pn_mantri' => '6494 | 0001 - Mantri Test',
            'status' => null,
        ], $overrides));
    }

    private function inputStore(): MantriSourceInputStore
    {
        $directory = sys_get_temp_dir().'/mantri-period-resolution-'.bin2hex(random_bytes(8));
        $this->inputDirectories[] = $directory;
        $store = new MantriSourceInputStore($directory);
        $this->app->instance(MantriSourceInputStore::class, $store);

        return $store;
    }

    private function archivedPosition(string $period): array
    {
        return [
            'source' => ['source_kind' => 'manual_workbook_position'], 'complete_presence' => true,
            'presence' => ['ARCHIVED-ACCOUNT'], 'unclassified_accounts' => [], 'bookings' => [[
                'periode' => $period, 'nomor_rekening1' => 'ARCHIVED-ACCOUNT', 'cifno' => 'ARCHIVED-CIF',
                'tgl_realisasi' => substr($period, 0, 7).'-05', 'plafon' => 100_000_000, 'baki_debet1' => 100_000_000,
                'pn_pengelola1' => '00000001 - Mantri Aktif', 'pn_pemrakarsa1' => '00000001 - Mantri Aktif', 'description' => 'KREDIT MIKRO - KUPEDES',
                'cabang1' => 'KC MADIUN', 'unit1' => 'UNIT TEST', 'branch1' => '001',
            ]],
        ];
    }

    private function invokePrivateMethod(object $object, string $method, mixed ...$arguments): mixed
    {
        $reflection = new ReflectionClass($object);
        $reflectedMethod = $reflection->getMethod($method);
        $reflectedMethod->setAccessible(true);

        return $reflectedMethod->invoke($object, ...$arguments);
    }
}
