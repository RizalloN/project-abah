<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function indexExists(string $table, string $indexName): bool
    {
        if (!Schema::hasTable($table)) {
            return false;
        }

        $driver = DB::connection()->getDriverName();
        if (!in_array($driver, ['mysql', 'mariadb'], true)) {
            return false;
        }

        $rows = DB::select(
            'SHOW INDEX FROM `' . str_replace('`', '``', $table) . '` WHERE Key_name = ?',
            [$indexName]
        );

        return !empty($rows);
    }

    public function up(): void
    {
        if (
            Schema::hasTable('lw325_ph')
            && Schema::hasColumn('lw325_ph', 'created_at')
            && Schema::hasColumn('lw325_ph', 'uniqueid_namareport')
            && !$this->indexExists('lw325_ph', 'idx_lw325ph_created_delete_scope')
        ) {
            Schema::table('lw325_ph', function (Blueprint $table) {
                $table->index(
                    ['created_at', 'uniqueid_namareport'],
                    'idx_lw325ph_created_delete_scope'
                );
            });
        }
    }

    public function down(): void
    {
        if ($this->indexExists('lw325_ph', 'idx_lw325ph_created_delete_scope')) {
            Schema::table('lw325_ph', function (Blueprint $table) {
                $table->dropIndex('idx_lw325ph_created_delete_scope');
            });
        }
    }
};
