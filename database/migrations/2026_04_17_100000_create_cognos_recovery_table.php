<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cognos_recovery')) {
            Schema::create('cognos_recovery', function (Blueprint $table) {
                $table->string('uniqueid_namareport')->primary();
                $table->date('periode')->nullable();
                $table->string('keterangan', 100)->nullable();
                $table->string('cifno', 50)->nullable();
                $table->string('bc', 20)->nullable();
                $table->string('sub_bc', 20)->nullable();
                $table->string('kanwil', 150)->nullable();
                $table->string('ro_fix', 150)->nullable();
                $table->string('region', 100)->nullable();
                $table->string('cabang', 150)->nullable();
                $table->string('unit_kerja', 180)->nullable();
                $table->string('gl_account', 50)->nullable();
                $table->string('produk_code', 30)->nullable();
                $table->string('segmen_fpsl', 100)->nullable();
                $table->string('rekening', 100)->nullable();
                $table->string('status', 30)->nullable();
                $table->string('stsdt_dt_raw', 50)->nullable();
                $table->string('sname', 255)->nullable();
                $table->string('segmen', 100)->nullable();
                $table->string('segmen_bisnis', 100)->nullable();
                $table->string('segmen_bisnis_2025', 100)->nullable();
                $table->string('produk', 150)->nullable();
                $table->string('segmen_kur', 100)->nullable();
                $table->string('segmen_repeat', 100)->nullable();
                $table->string('segmen_2', 100)->nullable();
                $table->string('compliance', 150)->nullable();
                $table->decimal('recovery', 20, 2)->nullable();
                $table->decimal('recovery_klaim', 20, 2)->nullable();
                $table->decimal('recovery_olsib', 20, 2)->nullable();
                $table->decimal('total_recovery', 20, 2)->nullable();
                $table->decimal('recovery_non_klaim', 20, 2)->nullable();
                $table->timestamps();

                $table->index('periode', 'idx_cognos_recovery_periode');
                $table->index('kanwil', 'idx_cognos_recovery_kanwil');
                $table->index('cabang', 'idx_cognos_recovery_cabang');
                $table->index('unit_kerja', 'idx_cognos_recovery_unit_kerja');
                $table->index('rekening', 'idx_cognos_recovery_rekening');
                $table->index(['periode', 'cabang', 'uniqueid_namareport'], 'idx_cognos_recovery_delete_scope');
            });
        }

        if (Schema::hasColumn('cognos_recovery', 'source_row_num')) {
            Schema::table('cognos_recovery', function (Blueprint $table) {
                $table->dropColumn('source_row_num');
            });
        }

        if (Schema::hasColumn('cognos_recovery', 'periode_raw')) {
            Schema::table('cognos_recovery', function (Blueprint $table) {
                $table->dropColumn('periode_raw');
            });
        }

        $existing = DB::table('nama_report')->where('id_report', 16)->first();
        $payload = [
            'nama_report' => 'Cognos Recovery',
            'table_name' => 'cognos_recovery',
            'active' => 1,
            'import_controller' => 'ImportCognosRecoveryController',
            'requires_manual_periode' => 0,
            'manual_periode_type' => null,
            'manual_periode_label' => null,
            'manual_periode_help' => null,
        ];

        if ($existing) {
            DB::table('nama_report')->where('id_report', 16)->update(array_merge($payload, [
                'updated_at' => now(),
            ]));
        } else {
            DB::table('nama_report')->insert(array_merge(['id_report' => 16], $payload, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    public function down(): void
    {
        DB::table('nama_report')->where('id_report', 16)->delete();
        Schema::dropIfExists('cognos_recovery');
    }
};
