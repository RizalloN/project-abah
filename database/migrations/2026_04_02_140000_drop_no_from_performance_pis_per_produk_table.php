<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('performance_pis_per_produk')) {
            return;
        }

        if (!Schema::hasColumn('performance_pis_per_produk', 'no')) {
            return;
        }

        try {
            Schema::table('performance_pis_per_produk', function (Blueprint $table) {
                $table->dropColumn('no');
            });
        } catch (\Throwable $e) {
            DB::statement('ALTER TABLE performance_pis_per_produk DROP COLUMN `no`');
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('performance_pis_per_produk')) {
            return;
        }

        if (Schema::hasColumn('performance_pis_per_produk', 'no')) {
            return;
        }

        Schema::table('performance_pis_per_produk', function (Blueprint $table) {
            $table->unsignedInteger('no')->nullable()->after('posisi');
        });
    }
};
