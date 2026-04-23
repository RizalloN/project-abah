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
            $table->decimal('loan_os', 20, 2)->default(0);
            $table->decimal('lancar_os', 20, 2)->default(0);
            $table->decimal('sml_os', 20, 2)->default(0);
            $table->decimal('npl_os', 20, 2)->default(0);
            $table->integer('total_deb')->default(0);
            $table->decimal('total_deposit', 20, 2)->default(0);
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

    private function invokePrivateMethod(object $object, string $method, array $arguments = []): mixed
    {
        $reflection = new ReflectionClass($object);
        $reflectedMethod = $reflection->getMethod($method);
        $reflectedMethod->setAccessible(true);

        return $reflectedMethod->invokeArgs($object, $arguments);
    }
}
