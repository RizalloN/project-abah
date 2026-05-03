<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('jumlah_merchant_detail')) {
            return;
        }

        Schema::table('jumlah_merchant_detail', function (Blueprint $table) {
            if (!Schema::hasColumn('jumlah_merchant_detail', 'AKUMULASI_SALES_VOLUME')) {
                $table->decimal('AKUMULASI_SALES_VOLUME', 25, 2)->nullable()->after('SALES_VOLUME');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('jumlah_merchant_detail') || !Schema::hasColumn('jumlah_merchant_detail', 'AKUMULASI_SALES_VOLUME')) {
            return;
        }

        Schema::table('jumlah_merchant_detail', function (Blueprint $table) {
            $table->dropColumn('AKUMULASI_SALES_VOLUME');
        });
    }
};
