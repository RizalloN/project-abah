<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('simpanan_multipn')) {
            return;
        }

        $this->addIndexIfMissing(
            'simpanan_multipn',
            'idx_smp_posisi_status_cab_unit',
            ['posisi', 'status', 'kantor_cabang', 'unit_kerja']
        );

        $this->dropIndexIfExists('simpanan_multipn', 'idx_smp_posisi_status_cabang_unit_new');
        $this->dropIndexIfExists('simpanan_multipn', 'idx_smp_posisi_status_cabang');
        $this->dropIndexIfExists('simpanan_multipn', 'idx_smp_posisi_status');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->dropIndexIfExists('simpanan_multipn', 'idx_smp_posisi_status_cab_unit');
    }

    private function indexExists(string $table, string $indexName): bool
    {
        try {
            return DB::table('information_schema.statistics')
                ->where('table_schema', DB::connection()->getDatabaseName())
                ->where('table_name', $table)
                ->where('index_name', $indexName)
                ->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param array<int, string> $columns
     */
    private function addIndexIfMissing(string $table, string $indexName, array $columns): void
    {
        if ($this->indexExists($table, $indexName)) {
            return;
        }

        $columnSql = implode(', ', array_map(
            fn (string $column): string => '`' . str_replace('`', '``', $column) . '`',
            $columns
        ));

        DB::statement(sprintf(
            'ALTER TABLE `%s` ADD INDEX `%s` (%s)',
            str_replace('`', '``', $table),
            str_replace('`', '``', $indexName),
            $columnSql
        ));
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (!$this->indexExists($table, $indexName)) {
            return;
        }

        DB::statement(sprintf(
            'ALTER TABLE `%s` DROP INDEX `%s`',
            str_replace('`', '``', $table),
            str_replace('`', '``', $indexName)
        ));
    }
};
