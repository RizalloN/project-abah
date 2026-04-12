<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE_NAME = 'simpanan_multipn';
    private const INDEX_NAME = 'idx_smp_delete_scope';

    public function up(): void
    {
        if (!Schema::hasTable(self::TABLE_NAME)) {
            return;
        }

        if ($this->indexExists(self::TABLE_NAME, self::INDEX_NAME)) {
            return;
        }

        Schema::table(self::TABLE_NAME, function (Blueprint $table) {
            $table->index(['posisi', 'kantor_cabang', 'uniqueid_SMPN'], self::INDEX_NAME);
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable(self::TABLE_NAME) || !$this->indexExists(self::TABLE_NAME, self::INDEX_NAME)) {
            return;
        }

        Schema::table(self::TABLE_NAME, function (Blueprint $table) {
            $table->dropIndex(self::INDEX_NAME);
        });
    }

    private function indexExists(string $tableName, string $indexName): bool
    {
        $rows = DB::select(
            'SHOW INDEX FROM `' . str_replace('`', '``', $tableName) . '` WHERE Key_name = ?',
            [$indexName]
        );

        return !empty($rows);
    }
};
