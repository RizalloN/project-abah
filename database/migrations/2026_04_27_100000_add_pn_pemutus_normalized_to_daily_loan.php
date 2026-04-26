<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_loan_dinamis', function (Blueprint $table) {
            $table->string('pn_pemutus_normalized', 20)->nullable()->after('rm_normalized');
            $table->index('pn_pemutus_normalized', 'idx_pn_pemutus_normalized');
        });

        // Backfill existing rows
        DB::statement("
            UPDATE daily_loan_dinamis
            SET pn_pemutus_normalized = NULLIF(
                TRIM(LEADING '0' FROM TRIM(SUBSTRING_INDEX(COALESCE(pn_pemutus1, ''), '-', 1))),
                ''
            )
            WHERE pn_pemutus_normalized IS NULL
              AND pn_pemutus1 IS NOT NULL
              AND pn_pemutus1 != ''
        ");
    }

    public function down(): void
    {
        Schema::table('daily_loan_dinamis', function (Blueprint $table) {
            $table->dropIndex('idx_pn_pemutus_normalized');
            $table->dropColumn('pn_pemutus_normalized');
        });
    }
};
