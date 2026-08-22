<?php

namespace Tests\Unit;

use App\Http\Controllers\Report\KinerjaRmReportController;
use App\Support\ReportSnapshotBuilder;
use App\Support\RkaLookupService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use ReflectionClass;
use Tests\TestCase;

class KinerjaRmSnapshotPeriodResolutionTest extends TestCase
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
        Schema::create('daily_loan_dinamis', function (Blueprint $table) {
            $table->id();
            $table->date('periode')->nullable();
        });
        Schema::create('performance_rm_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('periode');
            $table->string('cabang', 100);
            $table->string('unit', 100);
            $table->string('branch_code', 50)->nullable();
            $table->string('rm', 255);
            $table->string('segmen', 50);
            $table->string('produk', 100);
            $table->decimal('plafon', 20, 2)->default(0);
            $table->decimal('loan_os', 20, 2)->default(0);
            $table->decimal('lancar_os', 20, 2)->default(0);
            $table->decimal('sml_os', 20, 2)->default(0);
            $table->decimal('npl_os', 20, 2)->default(0);
            $table->decimal('restruk_os', 20, 2)->default(0);
            $table->integer('total_deb')->default(0);
            $table->integer('realisasi_deb')->default(0);
            $table->decimal('realisasi_os', 20, 2)->default(0);
            $table->decimal('total_deposit', 20, 2)->default(0);
            $table->tinyInteger('quadrant')->nullable();
            $table->timestamps();
        });

        Cache::forget('report_cache_version:pinjaman');
        Cache::forget('report_cache_version:simpanan');
    }

    public function test_performance_rm_period_resolution_tracks_daily_loan_source_periods(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            ['periode' => '2026-04-04'],
            ['periode' => '2026-04-17'],
            ['periode' => null],
        ]);

        $builder = app(ReportSnapshotBuilder::class);
        $resolved = $this->invokePrivateMethod($builder, 'resolvePerformanceRmPeriods', ['2026-04-18']);

        $this->assertSame(['2026-04-17'], $resolved);

        $resolvedEarly = $this->invokePrivateMethod($builder, 'resolvePerformanceRmPeriods', ['2026-04-01']);

        $this->assertSame(['2026-04-04'], $resolvedEarly);

        $resolvedAll = $this->invokePrivateMethod($builder, 'resolvePerformanceRmPeriods', [null]);

        $this->assertSame(['2026-04-17'], $resolvedAll);
    }

    public function test_kinerja_rm_cache_keys_refresh_after_scoped_report_cache_version_bump(): void
    {
        DB::table('performance_rm_snapshots')->insert([
            [
                'periode' => '2026-04-17',
                'cabang' => 'KC MADIUN',
                'unit' => 'UNIT A',
                'rm' => 'RM A',
                'segmen' => 'CONSUMER',
                'produk' => 'BRIGUNA-KONSUMER',
                'loan_os' => 1000,
                'lancar_os' => 1000,
                'sml_os' => 0,
                'npl_os' => 0,
                'total_deb' => 1,
                'total_deposit' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        Cache::put('report_cache_version:pinjaman', 1);
        Cache::put('report_cache_version:simpanan', 1);

        $controller = new KinerjaRmReportController(Mockery::mock(RkaLookupService::class));

        $periodsV1 = $this->invokePrivateMethod($controller, 'fetchAvailablePeriods', []);
        $this->assertSame(['2026-04-17'], $periodsV1->all());

        DB::table('performance_rm_snapshots')->insert([
            [
                'periode' => '2026-04-18',
                'cabang' => 'KC MADIUN',
                'unit' => 'UNIT A',
                'rm' => 'RM A',
                'segmen' => 'CONSUMER',
                'produk' => 'BRIGUNA-KONSUMER',
                'loan_os' => 1000,
                'lancar_os' => 1000,
                'sml_os' => 0,
                'npl_os' => 0,
                'total_deb' => 1,
                'total_deposit' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $periodsCached = $this->invokePrivateMethod($controller, 'fetchAvailablePeriods', []);
        $this->assertSame(['2026-04-17'], $periodsCached->all());

        Cache::put('report_cache_version:pinjaman', 2);

        $periodsRefreshed = $this->invokePrivateMethod($controller, 'fetchAvailablePeriods', []);
        $this->assertSame(['2026-04-18'], $periodsRefreshed->all());
    }

    public function test_kinerja_rm_period_options_include_daily_loan_source_periods(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            ['periode' => '2026-04-20'],
            ['periode' => '2026-04-10'],
            ['periode' => '2026-03-31'],
            ['periode' => '2025-12-31'],
        ]);

        DB::table('performance_rm_snapshots')->insert([
            [
                'periode' => '2026-04-20',
                'cabang' => 'KC MADIUN',
                'unit' => 'UNIT A',
                'rm' => 'RM A',
                'segmen' => 'CONSUMER',
                'produk' => 'BRIGUNA-KONSUMER',
                'loan_os' => 1000,
                'lancar_os' => 1000,
                'sml_os' => 0,
                'npl_os' => 0,
                'restruk_os' => 0,
                'total_deb' => 1,
                'realisasi_deb' => 0,
                'realisasi_os' => 0,
                'total_deposit' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $controller = new KinerjaRmReportController(Mockery::mock(RkaLookupService::class));

        $periods = $this->invokePrivateMethod($controller, 'fetchAvailablePeriods', []);

        $this->assertSame(['2026-04-20', '2026-03-31', '2025-12-31'], $periods->all());
    }

    public function test_kinerja_rm_requested_mid_month_uses_latest_period_in_that_month(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            ['periode' => '2026-05-15'],
            ['periode' => '2026-05-17'],
            ['periode' => '2026-04-30'],
        ]);

        $controller = new KinerjaRmReportController(Mockery::mock(RkaLookupService::class));
        $periods = $this->invokePrivateMethod($controller, 'fetchAvailablePeriods', []);

        $this->assertSame(['2026-05-17', '2026-04-30'], $periods->all());
        $this->assertSame('2026-05-17', $this->invokePrivateMethod($controller, 'resolveSelectedPeriod', [
            $periods,
            '2026-05-15',
        ]));
    }

    public function test_kinerja_rm_history_modal_includes_previous_and_current_year_until_selected_period(): void
    {
        DB::table('performance_rm_snapshots')->insert([
            $this->snapshotRow('2024-12-31', 1000000000, 1, 100000000, [
                'cabang' => 'KC DES 2024',
                'segmen' => 'SMALL',
                'produk' => 'COMMERCIAL',
            ]),
            $this->snapshotRow('2025-07-31', 1000000000, 1, 200000000, [
                'cabang' => 'KC JUL 2025',
                'segmen' => 'SMALL',
                'produk' => 'COMMERCIAL',
                'sml_os' => 10000000,
            ]),
            $this->snapshotRow('2026-01-31', 1000000000, 1, 300000000, [
                'cabang' => 'KC JAN 2026',
                'segmen' => 'SMALL',
                'produk' => 'COMMERCIAL',
                'npl_os' => 10000000,
            ]),
            $this->snapshotRow('2026-08-31', 1000000000, 1, 400000000, [
                'cabang' => 'KC AUG 2026',
                'segmen' => 'SMALL',
                'produk' => 'COMMERCIAL',
                'npl_os' => 10000000,
            ]),
        ]);

        $controller = new KinerjaRmReportController(Mockery::mock(RkaLookupService::class));
        $view = $controller->historyDetails(\Illuminate\Http\Request::create(
            '/report/dashboard-pinjaman/kinerjarm/history',
            'GET',
            ['rm' => 'RM A', 'segmen' => 'SMALL', 'periode' => '2026-05-18']
        ));

        $html = $view->render();

        $this->assertStringContainsString('Tahun', $html);
        $this->assertStringContainsString('KC JUL 2025', $html);
        $this->assertStringContainsString('KC JAN 2026', $html);
        $this->assertStringContainsString('2025', $html);
        $this->assertStringContainsString('2026', $html);
        $this->assertStringContainsString('2026 (tahun berjalan)', $html);
        $this->assertStringContainsString('2025 (tahun lalu)', $html);
        $this->assertStringContainsString('TOTAL 2026', $html);
        $this->assertStringContainsString('TOTAL 2025', $html);
        $this->assertStringNotContainsString('KC DES 2024', $html);
        $this->assertStringNotContainsString('KC AUG 2026', $html);
    }

    public function test_kinerja_rm_main_page_no_longer_accepts_micro_segment(): void
    {
        $controller = new KinerjaRmReportController(Mockery::mock(RkaLookupService::class));

        $this->assertSame('CONSUMER', $this->invokePrivateMethod($controller, 'resolveSelectedSegmen', ['MICRO']));
    }

    public function test_kinerja_rm_rows_use_comparison_periods_and_realisasi_values(): void
    {
        DB::table('performance_rm_snapshots')->insert([
            $this->snapshotRow('2026-04-20', 1600000000, 11, 250000000, ['branch_code' => '45']),
            $this->snapshotRow('2026-03-31', 1200000000, 0, 0),
            $this->snapshotRow('2025-12-31', 1000000000, 0, 0),
            $this->snapshotRow('2025-03-31', 900000000, 0, 0),
        ]);

        $controller = new KinerjaRmReportController(Mockery::mock(RkaLookupService::class));

        $result = $this->invokePrivateMethod($controller, 'fetchBranchRows', [
            'CONSUMER',
            '2026-04-20',
            $this->comparisonPeriods('2025-03-31', '2025-12-31', null, '2026-03-31'),
            '2026-04-20',
            null,
            null,
            null,
        ]);

        $item = $result['rows'][0]['rms']['RM A']['items'][0];

        $this->assertSame(700000000.0, $item['delta_yoy']);
        $this->assertSame(600000000.0, $item['delta_ytd']);
        $this->assertSame(400000000.0, $item['delta_mtd']);
        $this->assertSame(3, $item['ach_deb']);
        $this->assertSame(62500000.0, $item['ach_os']);
        $this->assertSame('45', $result['rows'][0]['rms']['RM A']['rm_unit_code']);
    }

    public function test_kinerja_rm_realisasi_period_uses_selected_daily_position(): void
    {
        $controller = new KinerjaRmReportController(Mockery::mock(RkaLookupService::class));

        $resolved = $this->invokePrivateMethod($controller, 'resolveKinerjaRealisasiPeriod', [
            '2026-05-15',
            $this->comparisonPeriods('2025-05-15', '2025-12-31', '2026-03-31', '2026-04-30'),
        ]);

        $this->assertSame('2026-05-15', $resolved);
    }

    public function test_kinerja_rm_consumer_quadrant_uses_monthly_target_achievement(): void
    {
        DB::table('performance_rm_snapshots')->insert([
            $this->snapshotRow('2026-05-20', 1600000000, 25, 5741000000, [
                'rm' => 'Rons Rohana Talibata',
            ]),
        ]);

        $controller = new KinerjaRmReportController(Mockery::mock(RkaLookupService::class));

        $result = $this->invokePrivateMethod($controller, 'fetchBranchRows', [
            'CONSUMER',
            '2026-05-20',
            $this->comparisonPeriods('2026-05-20', '2026-05-20', null, '2026-05-20'),
            '2026-05-20',
            null,
            null,
            null,
        ]);

        $this->assertSame(4, $result['rows'][0]['rms']['Rons Rohana Talibata']['quadrant']);
        $this->assertSame(1148200000.0, $result['rows'][0]['rms']['Rons Rohana Talibata']['items'][0]['ach_os']);
    }

    public function test_kinerja_rm_small_quadrant_uses_ratas_and_lar_when_snapshot_quadrant_is_missing(): void
    {
        DB::table('performance_rm_snapshots')->insert([
            $this->snapshotRow('2026-01-31', 1000000000, 1, 100000000, [
                'segmen' => 'SMALL',
                'produk' => 'SMALL',
                'sml_os' => 300000000,
            ]),
            $this->snapshotRow('2026-02-28', 1000000000, 0, 0, [
                'segmen' => 'SMALL',
                'produk' => 'SMALL',
                'sml_os' => 300000000,
            ]),
        ]);

        $controller = new KinerjaRmReportController(Mockery::mock(RkaLookupService::class));

        $result = $this->invokePrivateMethod($controller, 'fetchBranchRows', [
            'SMALL',
            '2026-02-28',
            $this->comparisonPeriods('2026-01-31', '2026-01-31', null, '2026-01-31'),
            '2026-02-28',
            null,
            null,
            null,
        ]);

        $rm = collect($result['rows'][0]['rms'])->first();
        $this->assertSame(4, $rm['quadrant']);
        $this->assertSame(50000000.0, $rm['items'][0]['ach_os']);
    }

    public function test_kinerja_rm_small_uses_closed_month_ratas_and_last_closed_lar(): void
    {
        DB::table('performance_rm_snapshots')->insert([
            $this->snapshotRow('2026-01-31', 1000000000, 1, 2750000000, [
                'segmen' => 'SMALL', 'produk' => 'SMALL', 'quadrant' => 4,
            ]),
            $this->snapshotRow('2026-02-28', 1000000000, 1, 2500000000, [
                'segmen' => 'SMALL', 'produk' => 'SMALL', 'quadrant' => 4,
            ]),
            $this->snapshotRow('2026-03-31', 1000000000, 1, 5150000000, [
                'segmen' => 'SMALL', 'produk' => 'SMALL', 'quadrant' => 4,
            ]),
            $this->snapshotRow('2026-04-30', 1000000000, 1, 10900000000, [
                'segmen' => 'SMALL', 'produk' => 'SMALL', 'quadrant' => 4,
            ]),
            $this->snapshotRow('2026-05-31', 1000000000, 1, 2350000000, [
                'segmen' => 'SMALL', 'produk' => 'SMALL', 'sml_os' => 130700000, 'quadrant' => 4,
            ]),
            $this->snapshotRow('2026-06-20', 1000000000, 1, 2500000000, [
                'segmen' => 'SMALL', 'produk' => 'SMALL', 'sml_os' => 300000000, 'quadrant' => 4,
            ]),
        ]);

        $controller = new KinerjaRmReportController(Mockery::mock(RkaLookupService::class));
        $result = $this->invokePrivateMethod($controller, 'fetchBranchRows', [
            'SMALL',
            '2026-06-20',
            $this->comparisonPeriods('2026-01-31', '2026-01-31', '2026-03-31', '2026-05-31'),
            '2026-06-20',
            null,
            null,
            null,
        ]);

        $rm = collect($result['rows'][0]['rms'])->first();
        $this->assertSame(1, $rm['quadrant']);
        $this->assertSame(4730000000.0, $rm['items'][0]['ach_os']);
        $this->assertEqualsWithDelta(13.07, $rm['items'][0]['lar_pct'], 0.0001);
    }

    public function test_kinerja_rm_comparison_periods_resolve_yoy_ytd_m2_and_m1(): void
    {
        $controller = new KinerjaRmReportController(Mockery::mock(RkaLookupService::class));
        $periods = collect([
            '2026-06-20',
            '2026-05-31',
            '2026-04-30',
            '2025-12-31',
            '2025-06-18',
        ]);

        $resolved = $this->invokePrivateMethod($controller, 'resolveKinerjaComparisonPeriods', [
            $periods,
            '2026-06-20',
        ]);

        $this->assertSame(['yoy', 'ytd', 'm2', 'm1'], array_keys($resolved));
        $this->assertSame('2025-06-18', $resolved['yoy']['period']);
        $this->assertSame('2025-12-31', $resolved['ytd']['period']);
        $this->assertSame('2026-04-30', $resolved['m2']['period']);
        $this->assertSame('2026-05-31', $resolved['m1']['period']);
        $this->assertSame('18 Jun 25', $resolved['yoy']['short_label']);
        $this->assertSame('31 Dec 25', $resolved['ytd']['short_label']);
    }

    public function test_kinerja_rm_quality_series_uses_detailed_daily_loan_buckets(): void
    {
        DB::table('performance_rm_snapshots')->insert([
            $this->snapshotRow('2025-06-20', 800000000, 0, 0, [
                'lancar_os' => 600000000,
                'restruk_os' => 100000000,
                'sml_os' => 120000000,
                'npl_os' => 80000000,
            ]),
            $this->snapshotRow('2025-12-31', 900000000, 0, 0, [
                'lancar_os' => 680000000,
                'restruk_os' => 90000000,
                'sml_os' => 130000000,
                'npl_os' => 90000000,
            ]),
            $this->snapshotRow('2026-04-30', 950000000, 0, 0, [
                'lancar_os' => 710000000,
                'restruk_os' => 80000000,
                'sml_os' => 140000000,
                'npl_os' => 100000000,
            ]),
            $this->snapshotRow('2026-05-31', 980000000, 0, 0, [
                'lancar_os' => 730000000,
                'restruk_os' => 70000000,
                'sml_os' => 150000000,
                'npl_os' => 100000000,
            ]),
            $this->snapshotRow('2026-06-20', 1000000000, 0, 0, [
                'lancar_os' => 750000000,
                'restruk_os' => 60000000,
                'sml_os' => 140000000,
                'npl_os' => 110000000,
            ]),
        ]);

        $qualityRows = collect([
            ['periode' => '2025-06-20', 'sml_1_os' => 10000000, 'sml_2_os' => 20000000, 'sml_3_os' => 30000000, 'kl_os' => 40000000, 'd1_os' => 50000000, 'd2_os' => 60000000, 'm_os' => 70000000],
            ['periode' => '2025-12-31', 'sml_1_os' => 11000000, 'sml_2_os' => 21000000, 'sml_3_os' => 31000000, 'kl_os' => 41000000, 'd1_os' => 51000000, 'd2_os' => 61000000, 'm_os' => 71000000],
            ['periode' => '2026-04-30', 'sml_1_os' => 12000000, 'sml_2_os' => 22000000, 'sml_3_os' => 32000000, 'kl_os' => 42000000, 'd1_os' => 52000000, 'd2_os' => 62000000, 'm_os' => 72000000],
            ['periode' => '2026-05-31', 'sml_1_os' => 13000000, 'sml_2_os' => 23000000, 'sml_3_os' => 33000000, 'kl_os' => 43000000, 'd1_os' => 53000000, 'd2_os' => 63000000, 'm_os' => 73000000],
            ['periode' => '2026-06-20', 'sml_1_os' => 15000000, 'sml_2_os' => 25000000, 'sml_3_os' => 35000000, 'kl_os' => 45000000, 'd1_os' => 55000000, 'd2_os' => 65000000, 'm_os' => 75000000],
        ])->map(function (array $values): object {
            return (object) array_merge([
                'cabang' => 'KC MADIUN',
                'unit' => 'UNIT A',
                'rm' => 'RM A',
                'segmen' => 'CONSUMER',
                'produk' => 'BRIGUNAKONSUMER',
                'loan_os' => 1000000000,
                'lancar_os' => 700000000,
                'sml_os' => 75000000,
                'npl_os' => 225000000,
                'restruk_os' => 0,
                'total_deb' => 1,
                'realisasi_deb' => 0,
                'realisasi_os' => 0,
                'quadrant' => null,
            ], $values);
        });

        $controller = new KinerjaRmReportController(Mockery::mock(RkaLookupService::class));
        $periods = $this->comparisonPeriods('2025-06-20', '2025-12-31', '2026-04-30', '2026-05-31');
        $currentFor = function (string $qualityType) use ($controller, $periods, $qualityRows): array {
            $result = $this->invokePrivateMethod($controller, 'fetchBranchRows', [
                'CONSUMER',
                '2026-06-20',
                $periods,
                '2026-06-20',
                null,
                null,
                $qualityType,
                null,
                $qualityRows,
            ]);

            return $result['rows'][0]['rms']['RM A']['items'][0];
        };

        $this->assertSame(15000000.0, $currentFor('sml_1')['curr']);
        $this->assertSame(25000000.0, $currentFor('sml_2')['curr']);
        $this->assertSame(35000000.0, $currentFor('sml_3')['curr']);
        $this->assertSame(45000000.0, $currentFor('kl')['curr']);
        $this->assertSame(55000000.0, $currentFor('d1')['curr']);
        $this->assertSame(65000000.0, $currentFor('d2')['curr']);

        $macet = $currentFor('m');
        $this->assertSame(75000000.0, $macet['curr']);
        $this->assertSame(1000000000.0, $macet['loan_os_reference']);
        $this->assertSame(2000000.0, $macet['comparison_deltas']['m1']);
    }

    public function test_kinerja_rm_quality_splits_lancar_lnr_lr_and_all_restructured_accounts(): void
    {
        $pdo = DB::connection('sqlite')->getPdo();
        $pdo->sqliteCreateFunction('DATEDIFF', static function ($a, $b): ?int {
            if ($a === null || $b === null) {
                return null;
            }

            return (int) floor((strtotime((string) $a) - strtotime((string) $b)) / 86400);
        }, 2);
        $pdo->sqliteCreateFunction('LEAST', static function (...$values) {
            $values = array_values(array_filter($values, static fn ($value): bool => $value !== null));

            return $values === [] ? null : min($values);
        });

        Schema::table('daily_loan_dinamis', function (Blueprint $table): void {
            $table->string('nomor_rekening1')->nullable();
            $table->decimal('baki_debet1', 20, 2)->nullable();
            $table->string('kolek_detail')->nullable();
            $table->string('kolek')->nullable();
            $table->integer('umur_tunggakan')->nullable();
            $table->string('flag_restruk')->nullable();
            $table->date('next_pmt_date')->nullable();
            $table->date('next_pmt_int_date')->nullable();
            $table->string('segmen_kinerja')->nullable();
            $table->string('produk_kinerja')->nullable();
            $table->string('cabang_normalized')->nullable();
            $table->string('unit_normalized')->nullable();
            $table->string('branch_normalized')->nullable();
            $table->string('rm_normalized')->nullable();
        });

        $base = [
            'periode' => '2026-08-16',
            'kolek_detail' => null,
            'next_pmt_date' => null,
            'next_pmt_int_date' => null,
            'segmen_kinerja' => 'CONSUMER',
            'produk_kinerja' => 'BRIGUNAKONSUMER',
            'cabang_normalized' => 'KC MADIUN',
            'unit_normalized' => 'UNIT A',
            'branch_normalized' => '45',
            'rm_normalized' => 'RM A',
        ];

        DB::table('daily_loan_dinamis')->insert(['periode' => '2026-07-31']);
        DB::table('daily_loan_dinamis')->insert([
            array_merge($base, ['nomor_rekening1' => 'LNR-1', 'baki_debet1' => 100, 'kolek' => '1', 'umur_tunggakan' => 0, 'flag_restruk' => 'N']),
            array_merge($base, ['nomor_rekening1' => 'LR-1', 'baki_debet1' => 200, 'kolek' => '1', 'umur_tunggakan' => 0, 'flag_restruk' => 'Y']),
            array_merge($base, ['nomor_rekening1' => 'AR-KL-1', 'baki_debet1' => 300, 'kolek' => '3', 'umur_tunggakan' => 100, 'flag_restruk' => 'Y']),
            array_merge($base, ['nomor_rekening1' => 'SML-1', 'baki_debet1' => 400, 'kolek' => '2', 'umur_tunggakan' => 15, 'flag_restruk' => 'N']),
        ]);

        $controller = new KinerjaRmReportController(Mockery::mock(RkaLookupService::class));
        $details = $this->invokePrivateMethod($controller, 'fetchDetailedQualityRows', [
            'CONSUMER',
            '2026-08-16',
            [],
            null,
            null,
            null,
        ]);

        $this->assertCount(1, $details);
        $detail = $details->first();
        $this->assertSame(1000.0, $detail->loan_os);
        $this->assertSame(300.0, $detail->lancar_os);
        $this->assertSame(100.0, $detail->lancar_non_restruk_os);
        $this->assertSame(200.0, $detail->restruk_os);
        $this->assertSame(500.0, $detail->account_restruk_os);
        $this->assertSame(400.0, $detail->sml_1_os);
        $this->assertSame(300.0, $detail->kl_os);

        $currentFor = function (string $qualityType) use ($controller, $details): float {
            $series = $this->invokePrivateMethod($controller, 'fetchBranchRows', [
                'CONSUMER', '2026-08-16', [], '2026-08-16', null, null,
                $qualityType, null, $details, true,
            ]);

            return (float) $series['rows'][0]['rms']['RM A']['items'][0]['curr'];
        };

        $this->assertSame(300.0, $currentFor('lancar'));
        $this->assertSame(200.0, $currentFor('lr'));
        $this->assertSame(100.0, $currentFor('lnr'));
        $this->assertSame(500.0, $currentFor('account_restruk'));
    }

    public function test_kinerja_rm_small_separates_same_rm_between_kc_and_kcp(): void
    {
        DB::table('performance_rm_snapshots')->insert([
            $this->snapshotRow('2026-06-20', 400000000, 0, 0, [
                'segmen' => 'SMALL',
                'produk' => 'SMALL',
                'unit' => 'KC MADIUN',
                'rm' => 'RM BERSAMA',
            ]),
            $this->snapshotRow('2026-06-20', 250000000, 0, 0, [
                'segmen' => 'SMALL',
                'produk' => 'SMALL',
                'unit' => 'KCP CARUBAN',
                'rm' => 'RM BERSAMA',
            ]),
            $this->snapshotRow('2026-06-20', 150000000, 0, 0, [
                'segmen' => 'SMALL',
                'produk' => 'SMALL',
                'unit' => 'KCP DOLOPO',
                'rm' => 'RM BERSAMA',
            ]),
            $this->snapshotRow('2026-05-31', 400000000, 1, 100000000, [
                'segmen' => 'SMALL',
                'produk' => 'SMALL',
                'unit' => 'KC MADIUN',
                'rm' => 'RM BERSAMA',
            ]),
            $this->snapshotRow('2026-05-31', 250000000, 1, 100000000, [
                'segmen' => 'SMALL',
                'produk' => 'SMALL',
                'unit' => 'KCP CARUBAN',
                'rm' => 'RM BERSAMA',
            ]),
            $this->snapshotRow('2026-05-31', 150000000, 1, 100000000, [
                'segmen' => 'SMALL',
                'produk' => 'SMALL',
                'unit' => 'KCP DOLOPO',
                'rm' => 'RM BERSAMA',
            ]),
        ]);

        $controller = new KinerjaRmReportController(Mockery::mock(RkaLookupService::class));
        $periods = $this->comparisonPeriods(null, null, null, null);

        $all = $this->invokePrivateMethod($controller, 'fetchBranchRows', [
            'SMALL', '2026-06-20', $periods, '2026-06-20', null, null, null, null,
        ]);
        $rms = $all['rows'][0]['rms'];

        $this->assertCount(3, $rms);
        $this->assertSame(['KC MADIUN', 'KCP CARUBAN', 'KCP DOLOPO'], collect($rms)->pluck('rm_unit')->sort()->values()->all());
        $this->assertSame(800000000.0, $all['total']['curr']);

        $kc = $this->invokePrivateMethod($controller, 'fetchBranchRows', [
            'SMALL', '2026-06-20', $periods, '2026-06-20', null, null, null, 'KC',
        ]);
        $this->assertSame(400000000.0, $kc['total']['curr']);
        $this->assertSame('KC', collect($kc['rows'][0]['rms'])->first()['rm_category']);

        $kcp = $this->invokePrivateMethod($controller, 'fetchBranchRows', [
            'SMALL', '2026-06-20', $periods, '2026-06-20', null, null, null, 'KCP',
        ]);
        $this->assertSame(400000000.0, $kcp['total']['curr']);
        $this->assertSame(['KCP CARUBAN', 'KCP DOLOPO'], collect($kcp['rows'][0]['rms'])->pluck('rm_unit')->sort()->values()->all());
    }

    public function test_kinerja_rm_category_filter_only_applies_to_small(): void
    {
        $controller = new KinerjaRmReportController(Mockery::mock(RkaLookupService::class));

        $this->assertSame('KC', $this->invokePrivateMethod($controller, 'resolveSelectedRmCategory', ['SMALL', 'kc']));
        $this->assertSame('KCP', $this->invokePrivateMethod($controller, 'resolveSelectedRmCategory', ['SMALL', 'KCP']));
        $this->assertNull($this->invokePrivateMethod($controller, 'resolveSelectedRmCategory', ['SMALL', 'ALL']));
        $this->assertNull($this->invokePrivateMethod($controller, 'resolveSelectedRmCategory', ['CONSUMER', 'KCP']));
    }

    public function test_retail_performance_uses_latest_report_per_month_and_filters_inactive_rm(): void
    {
        DB::table('daily_loan_dinamis')->insert(collect([
            '2026-01-31',
            '2026-06-30',
            '2026-07-31',
            '2026-08-02',
            '2026-08-03',
            '2026-08-07',
            '2026-08-08',
        ])->map(fn (string $period): array => ['periode' => $period])->all());

        DB::table('performance_rm_snapshots')->insert([
            $this->snapshotRow('2026-01-31', 100000000, 2, 100000000, [
                'branch_code' => '10',
                'unit' => 'UNIT SEPULUH',
                'rm' => 'ARIS SULISTYAWAN',
            ]),
            $this->snapshotRow('2026-07-31', 500000000, 5, 500000000, [
                'branch_code' => '10',
                'unit' => 'UNIT SEPULUH',
                'rm' => 'ARIS SULISTYAWAN',
            ]),
            $this->snapshotRow('2026-08-07', 300000000, 3, 300000000, [
                'branch_code' => '10',
                'unit' => 'UNIT SEPULUH',
                'rm' => 'ARIS SULISTYAWAN',
            ]),
            $this->snapshotRow('2026-08-08', 450000000, 4, 450000000, [
                'branch_code' => '10',
                'unit' => 'UNIT SEPULUH',
                'rm' => 'ARIS SULISTYAWAN',
            ]),
            $this->snapshotRow('2026-07-31', 250000000, 1, 250000000, [
                'branch_code' => '2',
                'unit' => 'UNIT DUA',
                'rm' => 'RM JULI',
            ]),
            $this->snapshotRow('2026-06-30', 900000000, 9, 900000000, [
                'branch_code' => '1',
                'unit' => 'UNIT SATU',
                'rm' => 'RM TIDAK AKTIF',
            ]),
        ]);

        $controller = new KinerjaRmReportController(Mockery::mock(RkaLookupService::class));
        $performance = $this->invokePrivateMethod($controller, 'fetchRetailRealizationPerformance', [
            'CONSUMER', '2026-08-08', null, null, null,
        ]);

        $this->assertSame('2026-08-08', $performance['meta']['latest_period']);
        $this->assertSame('2026-08-07', $performance['meta']['previous_report_period']);
        $this->assertSame(1, $performance['meta']['hidden_inactive_count']);
        $this->assertSame(['2', '10'], collect($performance['rows'])->pluck('unit_code')->all());

        $aris = collect($performance['rows'])->firstWhere('rm', 'ARIS SULISTYAWAN');
        $this->assertNotNull($aris);
        $this->assertSame('ARIS SULISTYAWAN', $aris['rm_display']);
        $this->assertSame(4, $aris['months']['2026-08']['deb']);
        $this->assertSame(450000000.0, $aris['months']['2026-08']['rp']);
        $this->assertSame(11, $aris['accumulated']['deb']);
        $this->assertSame(1050000000.0, $aris['accumulated']['rp']);
        $this->assertSame(-50000000.0, $aris['delta']['mom']);
        $this->assertSame(150000000.0, $aris['delta']['dtd']);
        $this->assertSame(-3250000000.0, $aris['delta']['mtd']);
        $this->assertSame(-28550000000.0, $aris['delta']['ytd']);
        $this->assertSame(4, $aris['quadrant']);
    }

    public function test_small_retail_performance_uses_closed_month_ratas_and_last_closed_lar(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            ['periode' => '2026-01-31'],
            ['periode' => '2026-07-31'],
            ['periode' => '2026-08-09'],
        ]);

        DB::table('performance_rm_snapshots')->insert([
            $this->snapshotRow('2026-01-31', 1000000000, 1, 1000000000, [
                'segmen' => 'SMALL',
                'produk' => 'SMALL',
                'sml_os' => 50000000,
                'branch_code' => '45',
                'rm' => '0001 - RM SMALL',
            ]),
            $this->snapshotRow('2026-07-31', 2000000000, 2, 3000000000, [
                'segmen' => 'SMALL',
                'produk' => 'SMALL',
                'restruk_os' => 100000000,
                'sml_os' => 100000000,
                'npl_os' => 100000000,
                'branch_code' => '45',
                'rm' => '0001 - RM SMALL',
            ]),
            $this->snapshotRow('2026-08-09', 2000000000, 3, 10000000000, [
                'segmen' => 'SMALL',
                'produk' => 'SMALL',
                'npl_os' => 1000000000,
                'branch_code' => '45',
                'rm' => '0001 - RM SMALL',
            ]),
        ]);

        $controller = new KinerjaRmReportController(Mockery::mock(RkaLookupService::class));
        $performance = $this->invokePrivateMethod($controller, 'fetchRetailRealizationPerformance', [
            'SMALL', '2026-08-09', null, null, null,
        ]);

        $row = $performance['rows'][0];

        $this->assertSame(2, $performance['meta']['closed_month_count']);
        $this->assertSame('2026-07-31', $performance['meta']['closed_through_period']);
        $this->assertSame('Jan 26 - Jul 26', $performance['meta']['closed_range_label']);
        $this->assertTrue($performance['months'][0]['is_closed']);
        $this->assertFalse($performance['months'][7]['is_closed']);
        $this->assertEqualsWithDelta(5.0, $row['months']['2026-01']['lar_pct'], 0.0001);
        $this->assertEqualsWithDelta(15.0, $row['months']['2026-07']['lar_pct'], 0.0001);
        $this->assertEqualsWithDelta(50.0, $row['months']['2026-08']['lar_pct'], 0.0001);
        $this->assertSame(2000000000.0, $row['accumulated']['ratas_rp']);
        $this->assertEqualsWithDelta(15.0, $row['accumulated']['lar_pct'], 0.0001);
        $this->assertSame(1, $row['quadrant']);
        $this->assertSame(2000000000.0, $performance['total']['accumulated']['ratas_rp']);
        $this->assertEqualsWithDelta(15.0, $performance['total']['accumulated']['lar_pct'], 0.0001);
    }

    public function test_retail_performance_plain_numbers_have_no_grouping_delimiter(): void
    {
        $controller = new KinerjaRmReportController(Mockery::mock(RkaLookupService::class));

        $this->assertSame('1235', $this->invokePrivateMethod($controller, 'formatPlainAmountInJuta', [1234567890]));
        $this->assertSame('1235', $this->invokePrivateMethod($controller, 'formatPlainCount', [1234.6]));
        $this->assertSame('+1235', $this->invokePrivateMethod($controller, 'formatPlainDeltaInJuta', [1234567890]));
        $this->assertSame('-1235', $this->invokePrivateMethod($controller, 'formatPlainDeltaInJuta', [-1234567890]));
    }

    private function snapshotRow(
        string $period,
        float $loanOs,
        int $realisasiDeb,
        float $realisasiOs,
        array $overrides = []
    ): array
    {
        return array_merge([
            'periode' => $period,
            'cabang' => 'KC MADIUN',
            'unit' => 'UNIT A',
            'branch_code' => null,
            'rm' => 'RM A',
            'segmen' => 'CONSUMER',
            'produk' => 'BRIGUNA-KONSUMER',
            'plafon' => $loanOs,
            'loan_os' => $loanOs,
            'lancar_os' => $loanOs,
            'sml_os' => 0,
            'npl_os' => 0,
            'restruk_os' => 0,
            'total_deb' => 1,
            'realisasi_deb' => $realisasiDeb,
            'realisasi_os' => $realisasiOs,
            'total_deposit' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);
    }

    private function comparisonPeriods(?string $yoy, ?string $ytd, ?string $m2, ?string $m1): array
    {
        return [
            'yoy' => ['key' => 'yoy', 'label' => $yoy ?? '-', 'period' => $yoy, 'short_label' => $yoy ?? '-'],
            'ytd' => ['key' => 'ytd', 'label' => 'YTD', 'period' => $ytd, 'short_label' => $ytd ?? '-'],
            'm2' => ['key' => 'm2', 'label' => 'M-2', 'period' => $m2, 'short_label' => $m2 ?? '-'],
            'm1' => ['key' => 'm1', 'label' => 'M-1', 'period' => $m1, 'short_label' => $m1 ?? '-'],
        ];
    }

    private function invokePrivateMethod(object $object, string $method, array $arguments = []): mixed
    {
        $reflection = new ReflectionClass($object);
        $reflectedMethod = $reflection->getMethod($method);
        $reflectedMethod->setAccessible(true);

        return $reflectedMethod->invokeArgs($object, $arguments);
    }
}
