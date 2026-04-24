<?php

namespace Tests\Unit;

use App\Jobs\SmartPartialSnapshotRebuildJob;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SmartPartialSnapshotRebuildJobTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::dropAllTables();

        Schema::create('dashboard_pinjaman_snapshots', function (Blueprint $table): void {
            $table->string('uniqueid_dps')->primary();
            $table->date('periode');
        });

        Schema::create('dashboard_pinjaman_chart_periodik_snapshots', function (Blueprint $table): void {
            $table->string('uniqueid_dpcs')->primary();
            $table->date('periode');
        });

        Schema::create('dashboard_harian_snapshots', function (Blueprint $table): void {
            $table->string('uniqueid_dhs')->primary();
            $table->date('snapshot_period');
        });
    }

    public function test_daily_loan_partial_rebuild_deletes_period_scoped_snapshots_using_table_specific_period_columns(): void
    {
        DB::table('dashboard_pinjaman_snapshots')->insert([
            ['uniqueid_dps' => 'loan-old', 'periode' => '2026-04-20'],
            ['uniqueid_dps' => 'loan-keep', 'periode' => '2026-04-21'],
        ]);
        DB::table('dashboard_pinjaman_chart_periodik_snapshots')->insert([
            ['uniqueid_dpcs' => 'chart-old', 'periode' => '2026-04-20'],
            ['uniqueid_dpcs' => 'chart-keep', 'periode' => '2026-04-21'],
        ]);
        DB::table('dashboard_harian_snapshots')->insert([
            ['uniqueid_dhs' => 'harian-old', 'snapshot_period' => '2026-04-20'],
            ['uniqueid_dhs' => 'harian-keep', 'snapshot_period' => '2026-04-21'],
        ]);

        $job = new SmartPartialSnapshotRebuildJob('daily_loan_dinamis', ['2026-04-20']);
        $method = new \ReflectionMethod($job, 'deleteAffectedSnapshots');
        $method->setAccessible(true);
        $method->invoke($job, 'daily_loan_dinamis', ['2026-04-20']);

        $this->assertSame(['loan-keep'], DB::table('dashboard_pinjaman_snapshots')->pluck('uniqueid_dps')->all());
        $this->assertSame(['chart-keep'], DB::table('dashboard_pinjaman_chart_periodik_snapshots')->pluck('uniqueid_dpcs')->all());
        $this->assertSame(['harian-keep'], DB::table('dashboard_harian_snapshots')->pluck('uniqueid_dhs')->all());
    }
}
