<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nama_report')) {
            DB::table('nama_report')
                ->whereIn('table_name', ['performance_kurkecil_mikro', 'performance_mantri'])
                ->delete();
        }

        Schema::dropIfExists('performance_kurkecil_mikro');
        Schema::dropIfExists('performance_mantri');
    }

    public function down(): void
    {
        // These Excel-copy dummy tables were intentionally retired in favor of
        // Kinerja RM and Kinerja RM Mikro sourced from Daily Loan Dinamis.
    }
};
