<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'ssa_almafacts';

    public function up(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            Schema::create(self::TABLE, function (Blueprint $table): void {
                $table->string('uniqueid_namareport', 255)->primary();
                $table->date('month_day_year_of_posisi')->nullable()->index();
                $table->string('kanca_konsolidasi', 100)->nullable();
                $table->string('kode_unit_kerja', 50)->nullable();
                $table->string('unit_kerja', 150)->nullable();
                $table->string('keterangan', 150)->nullable();
                $table->decimal('saldo', 24, 2)->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('nama_report')) {
            $now = now();
            $existingId = DB::table('nama_report')->where('table_name', self::TABLE)->value('id_report');
            $reportId = $existingId ?: ((int) DB::table('nama_report')->max('id_report')) + 1;

            DB::table('nama_report')->updateOrInsert(
                ['table_name' => self::TABLE],
                [
                    'id_report' => $reportId,
                    'nama_report' => 'SSA Almafacts',
                    'active' => 1,
                    'import_controller' => 'ImportExcelController',
                    'requires_manual_periode' => 0,
                    'manual_periode_type' => null,
                    'manual_periode_label' => null,
                    'manual_periode_help' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
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
