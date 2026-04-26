<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const REPORT_ID = 24;

    public function up(): void
    {
        if (!Schema::hasTable('brihc')) {
            Schema::create('brihc', function (Blueprint $table) {
                $table->string('uniqueid_brihc', 255)->primary();
                $table->string('pn', 50)->nullable();
                $table->string('nama', 255)->nullable();
                $table->string('jabatan', 100)->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('nama_report')) {
            $now = now();
            DB::table('nama_report')->updateOrInsert(
                ['id_report' => self::REPORT_ID],
                [
                    'nama_report' => 'BRIHC',
                    'table_name' => 'brihc',
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
            DB::table('nama_report')->where('id_report', self::REPORT_ID)->delete();
        }

        Schema::dropIfExists('brihc');
    }
};
