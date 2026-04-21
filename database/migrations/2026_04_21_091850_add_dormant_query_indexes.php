<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $database = DB::connection()->getDatabaseName();

        // Use raw SQL to create indexes with error suppression
        try {
            DB::statement("
                ALTER TABLE simpanan_multipn 
                ADD INDEX idx_smp_posisi_status (posisi, status)
            ");
        } catch (\Exception) {
            // Index might already exist
        }

        try {
            DB::statement("
                ALTER TABLE simpanan_multipn 
                ADD INDEX idx_smp_posisi_status_cabang (posisi, status, kantor_cabang)
            ");
        } catch (\Exception) {
            // Index might already exist
        }

        try {
            DB::statement("
                ALTER TABLE simpanan_multipn 
                ADD INDEX idx_smp_posisi_status_cabang_unit_new (posisi, status, kantor_cabang, unit_kerja)
            ");
        } catch (\Exception) {
            // Index might already exist
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            DB::statement("ALTER TABLE simpanan_multipn DROP INDEX idx_smp_posisi_status");
        } catch (\Exception) {
            // Already dropped or doesn't exist
        }

        try {
            DB::statement("ALTER TABLE simpanan_multipn DROP INDEX idx_smp_posisi_status_cabang");
        } catch (\Exception) {
            // Already dropped or doesn't exist
        }

        try {
            DB::statement("ALTER TABLE simpanan_multipn DROP INDEX idx_smp_posisi_status_cabang_unit_new");
        } catch (\Exception) {
            // Already dropped or doesn't exist
        }
    }
};
