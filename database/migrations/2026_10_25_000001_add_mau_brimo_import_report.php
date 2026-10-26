<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mau_brimo_r_all')) {
            Schema::create('mau_brimo_r_all', function (Blueprint $table): void {
                $table->string('uniqueid_namareport', 100)->primary();
                $table->unsignedSmallInteger('tahun');
                $table->string('periode', 7);
                $table->date('posisi');
                $table->string('region', 20);
                $table->string('rgdesc', 150);
                $table->string('mainbr', 20);
                $table->string('mbdesc', 150);
                $table->string('branch', 20);
                $table->string('brdesc', 180);
                $table->string('jenis', 100);
                $table->string('kategori', 120);
                $table->string('segmentasi', 120);
                $table->unsignedBigInteger('frequency');
                $table->unsignedBigInteger('jumlah_cifno');
                $table->unsignedBigInteger('jumlah_rek_fin');
                $table->timestamps();
                $table->index(['posisi', 'mbdesc'], 'idx_mau_brimo_position_branch');
                $table->index(['periode', 'mbdesc'], 'idx_mau_brimo_period_branch');
            });
        }

        if (! DB::table('nama_report')->where('table_name', 'mau_brimo_r_all')->exists()) {
            DB::table('nama_report')->insert([
                'id_report' => (int) DB::table('nama_report')->max('id_report') + 1,
                'nama_report' => 'MAU BRImo R All',
                'table_name' => 'mau_brimo_r_all',
                'active' => 1,
                'import_controller' => 'ImportMauBrimoController',
                'requires_manual_periode' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('nama_report')->where('table_name', 'mau_brimo_r_all')->delete();
        Schema::dropIfExists('mau_brimo_r_all');
    }
};
