<?php

namespace Tests\Unit;

use App\Support\SsaSimpananSnapshotBuilder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SsaSimpananSnapshotBuilderGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::dropAllTables();

        Schema::create('ssa_simpanan', function (Blueprint $table) {
            $table->id();
            $table->date('Month_Day_Year_of_Posisi');
            $table->string('nama_cabang')->nullable();
            $table->string('produk')->nullable();
            $table->string('segmentasi')->nullable();
            $table->decimal('saldo', 20, 2)->default(0);
        });

        Schema::create('ssa_simpanan_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('periode');
            $table->date('Month_Day_Year_of_Posisi');
            $table->string('nama_cabang')->nullable();
            $table->string('produk')->nullable();
            $table->string('segmentasi')->nullable();
            $table->decimal('total_saldo', 20, 2)->default(0);
            $table->integer('record_count')->default(0);
            $table->string('snapshot_version')->nullable();
            $table->timestamp('snapshot_at')->nullable();
        });
    }

    public function test_rebuild_purges_duplicate_snapshot_rows_before_upsert(): void
    {
        DB::table('ssa_simpanan')->insert([
            'Month_Day_Year_of_Posisi' => '2026-05-20',
            'nama_cabang' => 'KC PONOROGO',
            'produk' => 'TABUNGAN',
            'segmentasi' => 'CASA',
            'saldo' => 7000,
        ]);

        foreach ([5000, 6000] as $saldo) {
            DB::table('ssa_simpanan_snapshots')->insert([
                'periode' => '2026-05-20',
                'Month_Day_Year_of_Posisi' => '2026-05-20',
                'nama_cabang' => 'KC PONOROGO',
                'produk' => 'TABUNGAN',
                'segmentasi' => 'CASA',
                'total_saldo' => $saldo,
                'record_count' => 1,
                'snapshot_version' => '1',
            ]);
        }

        $result = app(SsaSimpananSnapshotBuilder::class)->rebuild('2026-05-20', false);

        $this->assertTrue($result['success']);
        $this->assertSame(1, DB::table('ssa_simpanan_snapshots')->where('periode', '2026-05-20')->count());
        $this->assertSame('7000', (string) DB::table('ssa_simpanan_snapshots')->value('total_saldo'));
    }
}
