<?php

namespace Tests\Unit;

use App\Support\SimpananMultiPnSnapshotGate;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SimpananMultiPnSnapshotGateTest extends TestCase
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

    public function test_snapshot_gate_accepts_partial_period_and_reports_missing_branches(): void
    {
        DB::table('simpanan_multipn')->insert([
            ['posisi' => '2026-04-30', 'kantor_cabang' => 'KC Madiun'],
            ['posisi' => '2026-04-30', 'kantor_cabang' => 'kc magetan'],
            ['posisi' => '2026-04-30', 'kantor_cabang' => 'KC Ngawi (Konsolidasi)'],
        ]);

        $gate = app(SimpananMultiPnSnapshotGate::class);

        $this->assertTrue($gate->isReady('2026-04-30'));
        $this->assertEqualsCanonicalizing(['PONOROGO'], $gate->getMissingBranches('2026-04-30'));
        $this->assertEqualsCanonicalizing(['MADIUN', 'MAGETAN', 'NGAWI'], $gate->getAvailableBranches('2026-04-30'));
    }

    public function test_snapshot_gate_accepts_period_when_all_four_area_branches_exist(): void
    {
        DB::table('simpanan_multipn')->insert([
            ['posisi' => '2026-04-30', 'kantor_cabang' => 'KC Madiun'],
            ['posisi' => '2026-04-30', 'kantor_cabang' => 'KC Magetan'],
            ['posisi' => '2026-04-30', 'kantor_cabang' => 'KC Ngawi'],
            ['posisi' => '2026-04-30', 'kantor_cabang' => 'KC Ponorogo (Unit Layanan)'],
        ]);

        $gate = app(SimpananMultiPnSnapshotGate::class);

        $this->assertTrue($gate->isReady('2026-04-30'));
        $this->assertSame([], $gate->getMissingBranches('2026-04-30'));
        $this->assertEqualsCanonicalizing(['MADIUN', 'MAGETAN', 'NGAWI', 'PONOROGO'], $gate->getAvailableBranches('2026-04-30'));
    }
}
