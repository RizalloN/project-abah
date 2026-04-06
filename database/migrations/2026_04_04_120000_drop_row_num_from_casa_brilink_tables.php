<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('casa_brilink_web') && Schema::hasColumn('casa_brilink_web', 'row_num')) {
            Schema::table('casa_brilink_web', function (Blueprint $table) {
                $table->dropColumn('row_num');
            });
        }

        if (Schema::hasTable('casa_brilink_edc') && Schema::hasColumn('casa_brilink_edc', 'row_num')) {
            Schema::table('casa_brilink_edc', function (Blueprint $table) {
                $table->dropColumn('row_num');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('casa_brilink_web') && !Schema::hasColumn('casa_brilink_web', 'row_num')) {
            Schema::table('casa_brilink_web', function (Blueprint $table) {
                $table->unsignedInteger('row_num')->nullable()->after('periode');
            });
        }

        if (Schema::hasTable('casa_brilink_edc') && !Schema::hasColumn('casa_brilink_edc', 'row_num')) {
            Schema::table('casa_brilink_edc', function (Blueprint $table) {
                $table->unsignedInteger('row_num')->nullable()->after('periode');
            });
        }
    }
};
