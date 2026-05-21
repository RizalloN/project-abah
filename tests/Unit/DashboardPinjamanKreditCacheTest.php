<?php

namespace Tests\Unit;

use App\Http\Controllers\DashboardPinjamanReportController;
use App\Support\ReportCacheVersion;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class DashboardPinjamanKreditCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        Config::set('cache.default', 'array');

        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Cache::flush();

        Schema::dropAllTables();
        Schema::create('dashboard_harian_snapshots', function (Blueprint $table): void {
            $table->date('snapshot_period')->nullable();
            $table->string('kanca_key')->nullable();
            $table->string('unit_key')->nullable();
            $table->string('kanca_label')->nullable();
            $table->string('source_signature')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function test_kredit_snapshot_signature_changes_when_dashboard_harian_source_changes(): void
    {
        DB::table('dashboard_harian_snapshots')->insert([
            'snapshot_period' => '2026-05-18',
            'kanca_key' => 'kc-madiun',
            'unit_key' => 'kc-madiun',
            'kanca_label' => 'KC Madiun',
            'source_signature' => 'source-a',
            'updated_at' => '2026-05-18 08:00:00',
        ]);

        $controller = new DashboardPinjamanReportController();
        $method = new ReflectionMethod($controller, 'kreditSnapshotSignature');
        $method->setAccessible(true);

        $periods = [
            'selected' => '2026-05-18',
            'ytd' => '2025-12-31',
            'm2' => '2026-03-31',
            'mtm' => '2026-04-18',
            'mtd' => '2026-04-30',
        ];

        $before = $method->invoke($controller, $periods, 'KC Madiun');

        DB::table('dashboard_harian_snapshots')
            ->where('snapshot_period', '2026-05-18')
            ->where('kanca_label', 'KC Madiun')
            ->update([
                'source_signature' => 'source-b',
                'updated_at' => '2026-05-18 09:00:00',
            ]);

        $after = $method->invoke($controller, $periods, 'KC Madiun');

        $this->assertNotSame($before, $after);
    }

    public function test_kredit_cache_version_follows_dashboard_harian_scope(): void
    {
        $controller = new DashboardPinjamanReportController();
        $method = new ReflectionMethod($controller, 'kreditCacheVersion');
        $method->setAccessible(true);

        $before = $method->invoke($controller);
        ReportCacheVersion::bump('harian');
        $after = $method->invoke($controller);

        $this->assertGreaterThan($before, $after);
    }
}
