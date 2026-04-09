<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('jumlah_merchant_detail')) {
            return;
        }

        $existingIndexes = collect(DB::select('SHOW INDEX FROM jumlah_merchant_detail'))
            ->pluck('Key_name');

        if ($existingIndexes->contains('jmd_periode_tid_index')) {
            return;
        }

        Schema::table('jumlah_merchant_detail', function (Blueprint $table) {
            $table->index(['PERIODE', 'TID'], 'jmd_periode_tid_index');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('jumlah_merchant_detail')) {
            return;
        }

        $existingIndexes = collect(DB::select('SHOW INDEX FROM jumlah_merchant_detail'))
            ->pluck('Key_name');

        if (!$existingIndexes->contains('jmd_periode_tid_index')) {
            return;
        }

        Schema::table('jumlah_merchant_detail', function (Blueprint $table) {
            $table->dropIndex('jmd_periode_tid_index');
        });
    }
};
