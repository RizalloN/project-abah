<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('rka')) {
            Schema::create('rka', function (Blueprint $table) {
                $table->string('uniqueid_namareport', 255)->primary();
                $table->string('desc_kanwil', 150)->nullable();
                $table->string('kanca', 100)->nullable();
                $table->string('desc_uker', 150)->nullable();
                $table->string('rka_key', 50)->nullable();
                $table->string('mata_anggaran', 255)->nullable();
                $table->decimal('jan', 20, 2)->nullable();
                $table->decimal('feb', 20, 2)->nullable();
                $table->decimal('mar', 20, 2)->nullable();
                $table->decimal('apr', 20, 2)->nullable();
                $table->decimal('may', 20, 2)->nullable();
                $table->decimal('jun', 20, 2)->nullable();
                $table->decimal('jul', 20, 2)->nullable();
                $table->decimal('aug', 20, 2)->nullable();
                $table->decimal('sep', 20, 2)->nullable();
                $table->decimal('oct', 20, 2)->nullable();
                $table->decimal('nov', 20, 2)->nullable();
                $table->decimal('dec', 20, 2)->nullable();
                $table->timestamps();

                $table->index(['desc_uker', 'rka_key'], 'idx_rka_uker_rka_key');
            });
        }

        if (Schema::hasTable('nama_report')) {
            DB::table('nama_report')->updateOrInsert(
                ['id_report' => 16],
                [
                    'nama_report' => 'RKA',
                    'table_name' => 'rka',
                    'active' => 1,
                    'import_controller' => 'ImportExcelController',
                    'requires_manual_periode' => 0,
                    'manual_periode_type' => null,
                    'manual_periode_label' => null,
                    'manual_periode_help' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('nama_report')) {
            DB::table('nama_report')
                ->where('id_report', 16)
                ->where('table_name', 'rka')
                ->delete();
        }

        Schema::dropIfExists('rka');
    }
};
