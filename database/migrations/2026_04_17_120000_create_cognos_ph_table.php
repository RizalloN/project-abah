<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cognos_ph')) {
            Schema::create('cognos_ph', function (Blueprint $table) {
                $table->string('uniqueid_namareport')->primary();
                $table->date('periode')->nullable();
                $table->string('kanwil', 150)->nullable();
                $table->string('region', 100)->nullable();
                $table->string('ro_fix', 150)->nullable();
                $table->string('bc', 20)->nullable();
                $table->string('sub_bc', 20)->nullable();
                $table->string('kanca', 180)->nullable();
                $table->string('unit_kerja', 180)->nullable();
                $table->string('acctno', 100)->nullable();
                $table->string('cifno', 50)->nullable();
                $table->string('sname', 255)->nullable();
                $table->string('segmen', 100)->nullable();
                $table->string('segmen_bisnis_2025', 100)->nullable();
                $table->string('produk', 150)->nullable();
                $table->string('segmen_kur', 100)->nullable();
                $table->string('segmen_repeat', 100)->nullable();
                $table->string('segmen_2', 100)->nullable();
                $table->string('compliance', 150)->nullable();
                $table->decimal('saldo_ph', 20, 2)->nullable();
                $table->timestamps();

                $table->index('periode', 'idx_cognos_ph_periode');
                $table->index('kanwil', 'idx_cognos_ph_kanwil');
                $table->index('kanca', 'idx_cognos_ph_kanca');
                $table->index('unit_kerja', 'idx_cognos_ph_unit_kerja');
                $table->index('acctno', 'idx_cognos_ph_acctno');
                $table->index(['periode', 'kanca', 'uniqueid_namareport'], 'idx_cognos_ph_delete_scope');
            });
        }

        $existing = DB::table('nama_report')->where('id_report', 17)->first();
        $payload = [
            'nama_report' => 'Cognos PH',
            'table_name' => 'cognos_ph',
            'active' => 1,
            'import_controller' => 'ImportCognosPhController',
            'requires_manual_periode' => 0,
            'manual_periode_type' => null,
            'manual_periode_label' => null,
            'manual_periode_help' => null,
        ];

        if ($existing) {
            DB::table('nama_report')->where('id_report', 17)->update(array_merge($payload, [
                'updated_at' => now(),
            ]));
        } else {
            DB::table('nama_report')->insert(array_merge(['id_report' => 17], $payload, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    public function down(): void
    {
        DB::table('nama_report')->where('id_report', 17)->delete();
        Schema::dropIfExists('cognos_ph');
    }
};
