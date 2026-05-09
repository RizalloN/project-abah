<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations - Disable hourly_dpk report import
     */
    public function up(): void
    {
        // Set hourly_dpk report as inactive - report no longer needed
        DB::table('nama_report')
            ->where('table_name', 'hourly_dpk')
            ->update(['active' => 0]);
    }

    /**
     * Reverse the migrations - Re-enable hourly_dpk if needed
     */
    public function down(): void
    {
        // Restore hourly_dpk report as active if rollback
        DB::table('nama_report')
            ->where('table_name', 'hourly_dpk')
            ->update(['active' => 1]);
    }
};
