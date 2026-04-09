<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        if (!Schema::hasTable('jumlah_merchant_detail')) {
            return;
        }

        $columnsToDrop = array_values(array_filter([
            Schema::hasColumn('jumlah_merchant_detail', 'created_at') ? 'created_at' : null,
            Schema::hasColumn('jumlah_merchant_detail', 'updated_at') ? 'updated_at' : null,
        ]));

        if (empty($columnsToDrop)) {
            return;
        }

        Schema::table('jumlah_merchant_detail', function (Blueprint $table) {
            $table->dropColumn($columnsToDrop);
        });
    }

    public function down()
    {
        if (!Schema::hasTable('jumlah_merchant_detail')) {
            return;
        }

        Schema::table('jumlah_merchant_detail', function (Blueprint $table) {
            if (!Schema::hasColumn('jumlah_merchant_detail', 'created_at')) {
                $table->timestamp('created_at')->nullable();
            }
            if (!Schema::hasColumn('jumlah_merchant_detail', 'updated_at')) {
                $table->timestamp('updated_at')->nullable();
            }
        });
    }
};
