<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('input_rekanan') && !Schema::hasColumn('input_rekanan', 'periode')) {
            Schema::table('input_rekanan', function (Blueprint $table) {
                $table->date('periode')->nullable()->after('id');
            });
        }

        if (Schema::hasTable('bod_boc') && !Schema::hasColumn('bod_boc', 'periode')) {
            Schema::table('bod_boc', function (Blueprint $table) {
                $table->date('periode')->nullable()->after('id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('input_rekanan') && Schema::hasColumn('input_rekanan', 'periode')) {
            Schema::table('input_rekanan', function (Blueprint $table) {
                $table->dropColumn('periode');
            });
        }

        if (Schema::hasTable('bod_boc') && Schema::hasColumn('bod_boc', 'periode')) {
            Schema::table('bod_boc', function (Blueprint $table) {
                $table->dropColumn('periode');
            });
        }
    }
};
