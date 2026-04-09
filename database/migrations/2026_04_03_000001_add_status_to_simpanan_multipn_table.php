<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('simpanan_multipn') || Schema::hasColumn('simpanan_multipn', 'status')) {
            return;
        }

        Schema::table('simpanan_multipn', function (Blueprint $table) {
            $table->string('status', 50)->nullable()->after('jenis_simpanan');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('simpanan_multipn') || !Schema::hasColumn('simpanan_multipn', 'status')) {
            return;
        }

        Schema::table('simpanan_multipn', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
