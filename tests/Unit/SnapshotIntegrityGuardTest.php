<?php

namespace Tests\Unit;

use App\Support\SnapshotIntegrityGuard;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SnapshotIntegrityGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::dropAllTables();
    }

    public function test_duplicate_logical_key_is_detected(): void
    {
        Schema::create('dashboard_simpanan_branch_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('snapshot_period');
            $table->string('kantor_cabang');
        });

        DB::table('dashboard_simpanan_branch_snapshots')->insert([
            ['snapshot_period' => '2026-05-20', 'kantor_cabang' => 'KC PONOROGO'],
            ['snapshot_period' => '2026-05-20', 'kantor_cabang' => 'KC PONOROGO'],
            ['snapshot_period' => '2026-05-20', 'kantor_cabang' => 'KC MADIUN'],
        ]);

        $result = app(SnapshotIntegrityGuard::class)->inspectPeriod('dashboard_simpanan_branch_snapshots', '2026-05-20');

        $this->assertSame('anomaly', $result['status']);
        $this->assertSame('duplicate_identity', $result['reason']);
        $this->assertSame(1, $result['duplicate_group_count']);
    }

    public function test_summary_snapshot_allows_only_one_row_per_period(): void
    {
        Schema::create('dashboard_simpanan_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('snapshot_period');
        });

        DB::table('dashboard_simpanan_snapshots')->insert([
            ['snapshot_period' => '2026-05-20'],
            ['snapshot_period' => '2026-05-20'],
        ]);

        $result = app(SnapshotIntegrityGuard::class)->inspectPeriod('dashboard_simpanan_snapshots', '2026-05-20');

        $this->assertSame('anomaly', $result['status']);
        $this->assertSame(2, $result['row_count']);
    }

    public function test_table_with_missing_identity_column_is_skipped_safely(): void
    {
        Schema::create('dashboard_harian_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('snapshot_period');
            $table->string('kanca_key')->nullable();
        });

        $result = app(SnapshotIntegrityGuard::class)->inspectPeriod('dashboard_harian_snapshots', '2026-05-20');

        $this->assertSame('skipped', $result['status']);
        $this->assertSame('missing_identity_column', $result['reason']);
    }

    public function test_purge_deletes_only_anomalous_target_period(): void
    {
        Schema::create('dashboard_simpanan_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('snapshot_period');
        });

        DB::table('dashboard_simpanan_snapshots')->insert([
            ['snapshot_period' => '2026-05-20'],
            ['snapshot_period' => '2026-05-20'],
            ['snapshot_period' => '2026-05-21'],
        ]);

        $purged = app(SnapshotIntegrityGuard::class)->purgePeriodIfAnomalous('dashboard_simpanan_snapshots', '2026-05-20');

        $this->assertTrue($purged);
        $this->assertSame(0, DB::table('dashboard_simpanan_snapshots')->where('snapshot_period', '2026-05-20')->count());
        $this->assertSame(1, DB::table('dashboard_simpanan_snapshots')->where('snapshot_period', '2026-05-21')->count());
    }
}
