<?php

namespace Tests\Unit;

use App\Support\DashboardHarianSnapshotService;
use App\Support\ReportSnapshotBuilder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class ReportSnapshotBuilderSimpananFreshnessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::dropAllTables();
        Cache::flush();

        Schema::create('simpanan_multipn', function (Blueprint $table) {
            $table->id();
            $table->date('posisi');
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('dashboard_simpanan_snapshots', function (Blueprint $table) {
            $table->string('uniqueid_dss')->primary();
            $table->date('snapshot_period');
            $table->decimal('total_balance', 20, 2)->default(0);
            $table->integer('source_row_count')->default(0);
            $table->timestamp('source_updated_at')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_dashboard_simpanan_snapshot_skips_rebuild_when_source_metadata_is_unchanged(): void
    {
        DB::table('simpanan_multipn')->insert([
            'posisi' => '2026-04-30',
            'updated_at' => '2026-04-24 10:00:00',
        ]);

        DB::table('dashboard_simpanan_snapshots')->insert([
            'uniqueid_dss' => md5('fresh-snapshot'),
            'snapshot_period' => '2026-04-30',
            'total_balance' => 12345.67,
            'source_row_count' => 1,
            'source_updated_at' => '2026-04-24 10:00:00',
            'created_at' => '2026-04-24 10:01:00',
            'updated_at' => '2026-04-24 10:01:00',
        ]);

        $builder = new ReportSnapshotBuilder(Mockery::mock(DashboardHarianSnapshotService::class));

        $result = $builder->rebuildDashboardSimpanan('2026-04-30', false);

        $this->assertSame(['2026-04-30' => 1], $result);
        $this->assertSame('12345.67', (string) DB::table('dashboard_simpanan_snapshots')->value('total_balance'));
    }
}
