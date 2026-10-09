<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gi405_singlerow', function (Blueprint $table): void {
            $table->string('source_periode', 32)->nullable()->after('periode');
            foreach (['begining_balance', 'equivalents_idr', 'equivalents_usd', 'today_debit', 'today_credit', 'ending_balance'] as $column) {
                $table->string($column, 80)->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Rollback GI405 Single Row memerlukan pemulihan data sumber; konversi otomatis ke DECIMAL(24,2) akan mengubah angka.');
    }
};
