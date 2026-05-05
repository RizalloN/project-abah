<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('dly_kap_resegmentasi') || Schema::hasColumn('dly_kap_resegmentasi', 'segmen_kategori')) {
            return;
        }

        Schema::table('dly_kap_resegmentasi', function (Blueprint $table) {
            $table->string('segmen_kategori', 30)->nullable()->after('kode_unit');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('dly_kap_resegmentasi') || !Schema::hasColumn('dly_kap_resegmentasi', 'segmen_kategori')) {
            return;
        }

        Schema::table('dly_kap_resegmentasi', function (Blueprint $table) {
            $table->dropColumn('segmen_kategori');
        });
    }
};
