<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jumlah_merchant_detail', function (Blueprint $table) {
            $table->index(['PERIODE', 'TID'], 'jmd_periode_tid_index');
        });
    }

    public function down(): void
    {
        Schema::table('jumlah_merchant_detail', function (Blueprint $table) {
            $table->dropIndex('jmd_periode_tid_index');
        });
    }
};
