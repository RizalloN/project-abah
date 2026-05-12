<?php

namespace Tests\Unit;

use App\Jobs\EnsureDashboardSimpananSnapshotJob;
use App\Support\SnapshotBatchAggregator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class EnsureDashboardSimpananSnapshotJobTest extends TestCase
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
            $table->date('posisi')->index();
            $table->string('kantor_cabang')->nullable()->index();
        });
    }

    public function test_job_registers_partial_snapshot_sync_when_area_branches_are_incomplete(): void
    {
        DB::table('simpanan_multipn')->insert([
            ['posisi' => '2026-04-30', 'kantor_cabang' => 'KC Madiun'],
            ['posisi' => '2026-04-30', 'kantor_cabang' => 'KC Magetan'],
            ['posisi' => '2026-04-30', 'kantor_cabang' => 'KC Ngawi'],
        ]);

        $aggregator = Mockery::mock(SnapshotBatchAggregator::class);
        $aggregator->shouldReceive('registerSyncRequest')
            ->once()
            ->andReturn(['batched' => true]);
        $this->app->instance(SnapshotBatchAggregator::class, $aggregator);

        (new EnsureDashboardSimpananSnapshotJob('2026-04-30', 'unit-test'))->handle($aggregator);

        $this->assertTrue(true);
    }

    public function test_job_registers_snapshot_sync_when_area_branches_are_complete(): void
    {
        DB::table('simpanan_multipn')->insert([
            ['posisi' => '2026-04-30', 'kantor_cabang' => 'KC Madiun'],
            ['posisi' => '2026-04-30', 'kantor_cabang' => 'KC Magetan'],
            ['posisi' => '2026-04-30', 'kantor_cabang' => 'KC Ngawi'],
            ['posisi' => '2026-04-30', 'kantor_cabang' => 'KC Ponorogo'],
        ]);

        $aggregator = Mockery::mock(SnapshotBatchAggregator::class);
        $aggregator->shouldReceive('registerSyncRequest')
            ->once()
            ->andReturn(['batched' => true]);

        $this->app->instance(SnapshotBatchAggregator::class, $aggregator);

        (new EnsureDashboardSimpananSnapshotJob('2026-04-30', 'unit-test'))->handle($aggregator);

        $this->assertTrue(true);
    }
}
