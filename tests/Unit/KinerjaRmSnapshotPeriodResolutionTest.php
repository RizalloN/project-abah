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

        Cache::forget('report_cache_version:global');
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

        $this->assertSame(['2026-04-04', '2026-04-17'], $resolvedAll);
    }

    public function test_kinerja_rm_cache_keys_refresh_after_global_report_cache_version_bump(): void
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

        Cache::put('report_cache_version:global', 1);

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

        Cache::put('report_cache_version:global', 2);

        $periodsRefreshed = $this->invokePrivateMethod($controller, 'fetchAvailablePeriods', []);
        $this->assertSame(['2026-04-18', '2026-04-17'], $periodsRefreshed->all());
    }

    public function test_kinerja_rm_period_options_include_daily_loan_source_periods(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            ['periode' => '2026-04-20'],
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

    public function test_kinerja_rm_main_page_no_longer_accepts_micro_segment(): void
    {
        $controller = new KinerjaRmReportController(Mockery::mock(RkaLookupService::class));

        $this->assertSame('CONSUMER', $this->invokePrivateMethod($controller, 'resolveSelectedSegmen', ['MICRO']));
    }

    public function test_kinerja_rm_rows_use_comparison_periods_and_realisasi_values(): void
    {
        DB::table('performance_rm_snapshots')->insert([
            $this->snapshotRow('2026-04-20', 1600000000, 11, 250000000),
            $this->snapshotRow('2026-03-31', 1200000000, 0, 0),
            $this->snapshotRow('2025-12-31', 1000000000, 0, 0),
            $this->snapshotRow('2025-03-31', 900000000, 0, 0),
        ]);

        $controller = new KinerjaRmReportController(Mockery::mock(RkaLookupService::class));

        $result = $this->invokePrivateMethod($controller, 'fetchBranchRows', [
            'CONSUMER',
            '2026-04-20',
            '2025-03-31',
            '2025-12-31',
            '2026-03-31',
            null,
            null,
            null,
        ]);

        $item = $result['rows'][0]['rms']['RM A']['items'][0];

        $this->assertSame(700000000.0, $item['delta_yoy']);
        $this->assertSame(600000000.0, $item['delta_ytd']);
        $this->assertSame(400000000.0, $item['delta_mtd']);
        $this->assertSame(11, $item['ach_deb']);
        $this->assertSame(250000000.0, $item['ach_os']);
    }

    public function test_kinerja_rm_does_not_synthesize_quadrants_for_non_small_segments(): void
    {
        DB::table('performance_rm_snapshots')->insert([
            $this->snapshotRow('2026-04-20', 1600000000, 1, 250000000),
        ]);

        $controller = new KinerjaRmReportController(Mockery::mock(RkaLookupService::class));

        $result = $this->invokePrivateMethod($controller, 'fetchBranchRows', [
            'CONSUMER',
            '2026-04-20',
            '2026-04-20',
            '2026-04-20',
            '2026-04-20',
            null,
            null,
            null,
        ]);

        $this->assertNull($result['rows'][0]['rms']['RM A']['quadrant']);
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
            '2026-01-31',
            '2026-01-31',
            '2026-01-31',
            null,
            null,
            null,
        ]);

        $this->assertSame(4, $result['rows'][0]['rms']['RM A']['quadrant']);
        $this->assertSame(50000000.0, $result['rows'][0]['rms']['RM A']['items'][0]['ach_os']);
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

    private function invokePrivateMethod(object $object, string $method, array $arguments = []): mixed
    {
        $reflection = new ReflectionClass($object);
        $reflectedMethod = $reflection->getMethod($method);
        $reflectedMethod->setAccessible(true);

        return $reflectedMethod->invokeArgs($object, $arguments);
    }
}
