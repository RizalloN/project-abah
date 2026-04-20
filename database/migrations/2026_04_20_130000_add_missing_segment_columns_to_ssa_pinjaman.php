<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ssa_pinjaman')) {
            return;
        }

        Schema::table('ssa_pinjaman', function (Blueprint $table) {
            if (!Schema::hasColumn('ssa_pinjaman', 'segmen')) {
                $table->string('segmen', 100)->nullable()->after('produk_dashboard');
            }

            if (!Schema::hasColumn('ssa_pinjaman', 'segmen_lama')) {
                $table->string('segmen_lama', 100)->nullable()->after('segmen');
            }

            if (!Schema::hasColumn('ssa_pinjaman', 'segmen_2025')) {
                $table->string('segmen_2025', 100)->nullable()->after('segmen_lama');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('ssa_pinjaman')) {
            return;
        }

        Schema::table('ssa_pinjaman', function (Blueprint $table) {
            $dropColumns = [];

            if (Schema::hasColumn('ssa_pinjaman', 'segmen')) {
                $dropColumns[] = 'segmen';
            }

            if (Schema::hasColumn('ssa_pinjaman', 'segmen_lama')) {
                $dropColumns[] = 'segmen_lama';
            }

            if ($dropColumns !== []) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};
