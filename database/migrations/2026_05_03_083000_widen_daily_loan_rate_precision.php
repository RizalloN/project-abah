<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('daily_loan_dinamis') || !Schema::hasColumn('daily_loan_dinamis', 'rate')) {
            return;
        }

        DB::statement('ALTER TABLE `daily_loan_dinamis` MODIFY `rate` DECIMAL(20,6) NULL');
    }

    public function down(): void
    {
        if (!Schema::hasTable('daily_loan_dinamis') || !Schema::hasColumn('daily_loan_dinamis', 'rate')) {
            return;
        }

        DB::statement('ALTER TABLE `daily_loan_dinamis` MODIFY `rate` DECIMAL(20,2) NULL');
    }
};
