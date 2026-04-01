<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('daily_loan_dinamis') || !Schema::hasColumn('daily_loan_dinamis', 'baki_debet')) {
            return;
        }

        DB::statement("
            UPDATE daily_loan_dinamis
            SET baki_debet = CASE
                WHEN baki_debet IS NULL OR TRIM(baki_debet) = '' THEN NULL
                WHEN TRIM(baki_debet) REGEXP '^-?[0-9]+(\\.[0-9]+)?$' THEN TRIM(baki_debet)
                WHEN TRIM(baki_debet) REGEXP '^-?[0-9]{1,3}(,[0-9]{3})+(\\.[0-9]+)?$' THEN REPLACE(TRIM(baki_debet), ',', '')
                WHEN TRIM(baki_debet) REGEXP '^-?[0-9]{1,3}(\\.[0-9]{3})+(,[0-9]+)?$' THEN REPLACE(REPLACE(TRIM(baki_debet), '.', ''), ',', '.')
                WHEN TRIM(baki_debet) REGEXP '^-?[0-9]+,[0-9]+$' THEN REPLACE(TRIM(baki_debet), ',', '.')
                ELSE NULL
            END
        ");

        DB::statement("
            ALTER TABLE daily_loan_dinamis
            MODIFY baki_debet DECIMAL(18,2) NULL
        ");
    }

    public function down(): void
    {
        if (!Schema::hasTable('daily_loan_dinamis') || !Schema::hasColumn('daily_loan_dinamis', 'baki_debet')) {
            return;
        }

        DB::statement("
            ALTER TABLE daily_loan_dinamis
            MODIFY baki_debet VARCHAR(100) NULL
        ");
    }
};
