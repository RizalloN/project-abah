<?php

namespace Tests\Unit;

use App\Models\BodBoc;
use App\Models\InputRekanan;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReusableIdModelsTest extends TestCase
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

        Schema::create('bod_boc', function (Blueprint $table): void {
            $table->id();
            $table->date('periode')->nullable();
            $table->string('instansi')->nullable();
            $table->string('bod_boc')->nullable();
            $table->string('nama_nasabah')->nullable();
            $table->string('ket_nasabah')->nullable();
            $table->string('cif')->nullable();
            $table->string('fasilitas_1')->nullable();
            $table->string('fasilitas_2')->nullable();
            $table->string('fasilitas_3')->nullable();
            $table->timestamps();
        });

        Schema::create('input_rekanan', function (Blueprint $table): void {
            $table->id();
            $table->date('periode')->nullable();
            $table->string('perusahaan_anak')->nullable();
            $table->string('rekanan_level_1')->nullable();
            $table->string('rekanan_level_2')->nullable();
            $table->string('status_nasabah')->nullable();
            $table->string('cif')->nullable();
            $table->string('produk_1')->nullable();
            $table->string('produk_2')->nullable();
            $table->string('produk_3')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropAllTables();

        parent::tearDown();
    }

    public function test_bod_boc_reuses_missing_id_hole(): void
    {
        DB::table('bod_boc')->insert([
            [
                'id' => 1,
                'periode' => '2026-04-23',
                'instansi' => 'Instansi A',
                'bod_boc' => 'BOD',
                'nama_nasabah' => 'Nasabah 1',
                'ket_nasabah' => 'aktif',
                'cif' => 'CIF-1',
            ],
            [
                'id' => 3,
                'periode' => '2026-04-23',
                'instansi' => 'Instansi B',
                'bod_boc' => 'BOC',
                'nama_nasabah' => 'Nasabah 3',
                'ket_nasabah' => 'aktif',
                'cif' => 'CIF-3',
            ],
        ]);

        $created = BodBoc::create([
            'periode' => '2026-04-23',
            'instansi' => 'Instansi C',
            'bod_boc' => 'BOD',
            'nama_nasabah' => 'Nasabah 2',
            'ket_nasabah' => 'aktif',
            'cif' => 'CIF-2',
        ]);

        $this->assertSame(2, $created->id);
        $this->assertSame([1, 2, 3], DB::table('bod_boc')->orderBy('id')->pluck('id')->all());
    }

    public function test_input_rekanan_starts_from_one_when_empty(): void
    {
        $created = InputRekanan::create([
            'periode' => '2026-04-23',
            'perusahaan_anak' => 'Anak Usaha',
            'rekanan_level_1' => 'Level 1',
            'rekanan_level_2' => 'Level 2',
            'status_nasabah' => 'aktif',
            'cif' => 'CIF-10',
            'produk_1' => 'Produk A',
        ]);

        $this->assertSame(1, $created->id);
        $this->assertSame(1, DB::table('input_rekanan')->count());
    }
}
