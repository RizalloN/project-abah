<?php

namespace Tests\Unit;

use App\Jobs\EnsureImportedSnapshotsFreshJob;
use App\Support\DashboardHarianSnapshotService;
use App\Support\ReportSnapshotBuilder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class EnsureImportedSnapshotsFreshJobTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        Config::set('cache.default', 'array');
        Config::set('import.snapshot.enable_analyze_table', false);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::dropAllTables();
        Schema::create('daily_loan_dinamis', function (Blueprint $table) {
            $table->id();
            $table->date('periode');
            $table->timestamps();
        });

        foreach ([
            'dashboard_pinjaman_snapshots' => 'periode',
            'dashboard_pinjaman_chart_periodik_snapshots' => 'periode',
            'performance_rm_snapshots' => 'periode',
            'rasio_casa_debitur_snapshots' => 'loan_period',
        ] as $tableName => $periodColumn) {
            Schema::create($tableName, function (Blueprint $table) use ($periodColumn) {
                $table->id();
                $table->date($periodColumn);
                $table->timestamps();
            });
        }
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_daily_loan_freshness_rebuilds_stale_performance_rm_snapshot(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            'periode' => '2026-05-06',
            'created_at' => '2026-05-08 10:00:00',
            'updated_at' => '2026-05-08 10:00:00',
        ]);

        DB::table('dashboard_pinjaman_snapshots')->insert($this->snapshotRow('periode', '2026-05-06', '2026-05-08 11:00:00'));
        DB::table('dashboard_pinjaman_chart_periodik_snapshots')->insert($this->snapshotRow('periode', '2026-05-06', '2026-05-08 11:00:00'));
        DB::table('performance_rm_snapshots')->insert($this->snapshotRow('periode', '2026-05-06', '2026-05-08 09:00:00'));
        DB::table('rasio_casa_debitur_snapshots')->insert($this->snapshotRow('loan_period', '2026-05-06', '2026-05-08 11:00:00'));
        Cache::put('report_cache_version:global', 3, now()->addHours(24));

        $builder = Mockery::mock(ReportSnapshotBuilder::class);
        $builder->shouldNotReceive('rebuildDashboard');
        $builder->shouldNotReceive('rebuildChartPeriodik');
        $builder->shouldReceive('rebuildPerformanceRm')
            ->once()
            ->with('2026-05-06', true)
            ->andReturn(['2026-05-06' => 10]);
        $builder->shouldNotReceive('rebuildRasioCasa');

        $dashboardHarian = Mockery::mock(DashboardHarianSnapshotService::class);
        $dashboardHarian->shouldNotReceive('rebuild');

        (new EnsureImportedSnapshotsFreshJob('daily_loan_dinamis', '2026-05-06', 'unit-test'))
            ->handle($builder, $dashboardHarian);

        $this->assertSame(4, (int) Cache::get('report_cache_version:global'));
    }

    private function snapshotRow(string $periodColumn, string $period, string $updatedAt): array
    {
        return [
            $periodColumn => $period,
            'created_at' => $updatedAt,
            'updated_at' => $updatedAt,
        ];
    }
}
