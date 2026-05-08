<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('brimo_fin_all')) {
            Schema::create('brimo_fin_all', function (Blueprint $table): void {
                $table->string('uniqueid_namareport', 255)->primary();
                $table->string('tahun', 10)->nullable()->index();
                $table->string('periode', 20)->nullable()->index();
                $table->date('posisi')->nullable()->index();
                $table->string('region', 20)->nullable()->index();
                $table->string('rgdesc', 150)->nullable();
                $table->string('mainbr', 20)->nullable()->index();
                $table->string('mbdesc', 150)->nullable()->index();
                $table->string('branch', 20)->nullable()->index();
                $table->string('brdesc', 180)->nullable()->index();
                $table->string('jenis', 100)->nullable()->index();
                $table->string('kategori', 120)->nullable()->index();
                $table->string('segmentasi', 120)->nullable()->index();
                $table->string('fiturid', 50)->nullable()->index();
                $table->string('fitur', 255)->nullable()->index();
                $table->string('subfitur', 255)->nullable();
                $table->decimal('sales_volume', 25, 2)->nullable();
                $table->decimal('jumlah_transaksi', 25, 2)->nullable();
                $table->decimal('fee', 25, 2)->nullable();
                $table->timestamps();

                $table->index(['posisi', 'mbdesc', 'brdesc', 'uniqueid_namareport'], 'idx_bfa_delete_scope');
                $table->index(['periode', 'mbdesc', 'brdesc'], 'idx_bfa_period_cab_unit');
            });
        }

        $existing = DB::table('nama_report')
            ->where('table_name', 'brimo_fin_all')
            ->orWhere('nama_report', 'Brimo Fin All')
            ->exists();

        if (!$existing) {
            $nextId = (int) DB::table('nama_report')->max('id_report') + 1;

            DB::table('nama_report')->insert([
                'id_report' => $nextId,
                'nama_report' => 'Brimo Fin All',
                'table_name' => 'brimo_fin_all',
                'active' => 1,
                'import_controller' => 'ImportFileBrimoController',
                'requires_manual_periode' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('nama_report')
            ->where('table_name', 'brimo_fin_all')
            ->delete();

        Schema::dropIfExists('brimo_fin_all');
    }
};
