<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tables = ['casa_brilink_web', 'casa_brilink_edc'];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                if (!Schema::hasColumn($table->getTable(), 'region')) {
                    $table->string('region', 50)->nullable()->after('periode');
                }
                if (!Schema::hasColumn($table->getTable(), 'rgdesc')) {
                    $table->string('rgdesc', 150)->nullable()->after('region');
                }
                if (!Schema::hasColumn($table->getTable(), 'mainbr')) {
                    $table->string('mainbr', 50)->nullable()->after('rgdesc');
                }
                if (!Schema::hasColumn($table->getTable(), 'branch')) {
                    $table->string('branch', 50)->nullable()->after('mbdesc');
                }
                if (!Schema::hasColumn($table->getTable(), 'kode_agen')) {
                    $table->string('kode_agen', 100)->nullable()->after('brdesc');
                }
                if (!Schema::hasColumn($table->getTable(), 'mid_code')) {
                    $table->string('mid_code', 100)->nullable()->after('kode_agen');
                }
                if (!Schema::hasColumn($table->getTable(), 'keterangan')) {
                    $table->string('keterangan', 255)->nullable()->after('account');
                }
                if (!Schema::hasColumn($table->getTable(), 'sumber')) {
                    $table->string('sumber', 100)->nullable()->after('keterangan');
                }
                if (!Schema::hasColumn($table->getTable(), 'textbox9')) {
                    $table->decimal('textbox9', 20, 2)->nullable()->after('jml_nominal_casa');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = ['casa_brilink_web', 'casa_brilink_edc'];
        $columns = [
            'region', 'rgdesc', 'mainbr', 'branch', 
            'kode_agen', 'mid_code', 'keterangan', 'sumber', 'textbox9'
        ];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};
