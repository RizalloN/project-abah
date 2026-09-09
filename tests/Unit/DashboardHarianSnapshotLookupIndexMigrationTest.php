<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DashboardHarianSnapshotLookupIndexMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('dashboard_harian_snapshots', function (Blueprint $table): void {
            $table->date('snapshot_period');
            $table->string('kanca_key');
            $table->string('unit_key');
        });
    }

    public function test_migration_adds_only_the_covering_unique_lookup_index_and_is_idempotent(): void
    {
        $migration = require database_path('migrations/2026_09_06_120000_repair_dashboard_harian_snapshot_lookup_index.php');

        $migration->up();
        $migration->up();

        $indexes = collect(Schema::getIndexes('dashboard_harian_snapshots'));
        $lookup = $indexes->firstWhere('name', 'uq_dhs_period_kanca_unit');

        $this->assertNotNull($lookup);
        $this->assertTrue((bool) $lookup['unique']);
        $this->assertSame(['snapshot_period', 'kanca_key', 'unit_key'], $lookup['columns']);
        $this->assertCount(1, $indexes->filter(
            fn (array $index): bool => array_slice($index['columns'], 0, 3) === ['snapshot_period', 'kanca_key', 'unit_key']
        ));
    }
}
