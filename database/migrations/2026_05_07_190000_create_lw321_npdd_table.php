<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'lw321_npdd';

    public function up(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            Schema::create(self::TABLE, function (Blueprint $table): void {
                $table->string('uniqueid_namareport', 255)->primary();
                $table->date('periode')->nullable();
                $table->string('billing', 50)->nullable()->index();
                $table->string('kanca', 150)->nullable()->index();
                $table->string('bc', 20)->nullable()->index();
                $table->string('mbm', 150)->nullable();
                $table->string('uker', 150)->nullable()->index();
                $table->string('pn', 50)->nullable()->index();
                $table->string('mantri', 150)->nullable();
                $table->string('no_rekening', 50)->nullable()->index();
                $table->string('nama_debitur', 255)->nullable();
                $table->decimal('plafon', 22, 2)->nullable();
                $table->date('npdd')->nullable()->index();
                $table->date('npdd_update')->nullable();
                $table->date('tgl_realisasi')->nullable();
                $table->date('tgl_jatuh_tempo')->nullable();
                $table->string('jangka_waktu', 30)->nullable();
                $table->string('flag_restruk', 10)->nullable();
                $table->string('kol', 20)->nullable();
                $table->string('detail', 100)->nullable();
                $table->decimal('os', 22, 2)->nullable();
                $table->decimal('wba', 22, 2)->nullable();
                $table->string('now_kol', 20)->nullable();
                $table->string('now_detail', 100)->nullable();
                $table->decimal('now_os', 22, 2)->nullable();
                $table->decimal('now_t_pokok', 22, 2)->nullable();
                $table->decimal('now_t_bunga', 22, 2)->nullable();
                $table->decimal('now_t_total', 22, 2)->nullable();
                $table->string('ptp', 50)->nullable()->index();
                $table->timestamps();

                $table->index(['periode', 'kanca', 'uker'], 'idx_lw321_npdd_period_kanca_uker');
                $table->index(['periode', 'no_rekening'], 'idx_lw321_npdd_period_rekening');
            });
        }

        if (Schema::hasTable('nama_report')) {
            $exists = DB::table('nama_report')
                ->where('table_name', self::TABLE)
                ->orWhere('nama_report', 'LW321 NPDD Micro')
                ->exists();

            if (!$exists) {
                DB::table('nama_report')->insert([
                    'id_report' => (int) DB::table('nama_report')->max('id_report') + 1,
                    'nama_report' => 'LW321 NPDD Micro',
                    'table_name' => self::TABLE,
                    'active' => 1,
                    'import_controller' => 'ImportExcelController',
                    'requires_manual_periode' => 0,
                    'manual_periode_type' => null,
                    'manual_periode_label' => null,
                    'manual_periode_help' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('nama_report')) {
            DB::table('nama_report')->where('table_name', self::TABLE)->delete();
        }

        Schema::dropIfExists(self::TABLE);
    }
};
