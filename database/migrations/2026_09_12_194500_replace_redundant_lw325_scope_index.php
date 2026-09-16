<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'lw325_ph';

    private const REDUNDANT_INDEX = 'idx_dhs_period_kanca_unit';

    private const REPLACEMENT_INDEX = 'idx_lw325ph_updated_period';

    /** @var array<int, string> */
    private const REDUNDANT_COLUMNS = ['periode', 'kanca', 'unit'];

    /** @var array<int, string> */
    private const COVERING_COLUMNS = ['periode', 'kanca', 'unit', 'segmen_dashboard', 'pokok'];

    /** @var array<int, string> */
    private const REPLACEMENT_COLUMNS = ['updated_at', 'periode'];

    /** @var array<int, string> */
    private const ACTIVE_IMPORT_STATUSES = ['queued', 'staging', 'processing'];

    public function up(): void
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        $indexes = $this->indexDefinitionMap();
        $coveringDefinition = $indexes['idx_lw325ph_report_filter_covering'] ?? null;

        if (! $this->matchesAuditedIndex($coveringDefinition, self::COVERING_COLUMNS)) {
            throw new RuntimeException(
                'Index covering lw325_ph berubah atau tidak tersedia; optimasi dibatalkan tanpa mengubah schema.'
            );
        }

        $redundantDefinition = $indexes[self::REDUNDANT_INDEX] ?? null;
        if ($redundantDefinition !== null && ! $this->matchesAuditedIndex($redundantDefinition, self::REDUNDANT_COLUMNS)) {
            throw new RuntimeException(
                'Definisi index legacy lw325_ph berubah; optimasi dibatalkan tanpa menghapus index tersebut.'
            );
        }

        $replacementDefinition = $indexes[self::REPLACEMENT_INDEX] ?? null;
        if ($replacementDefinition !== null && ! $this->matchesAuditedIndex($replacementDefinition, self::REPLACEMENT_COLUMNS)) {
            throw new RuntimeException(
                'Definisi index updated-period lw325_ph berubah; optimasi dibatalkan tanpa mengubah schema.'
            );
        }

        if ($redundantDefinition === null && $replacementDefinition !== null) {
            return;
        }

        $this->assertNoActiveImports();

        if ($redundantDefinition !== null && $replacementDefinition === null) {
            DB::statement(
                'ALTER TABLE `lw325_ph` '
                .'DROP INDEX `idx_dhs_period_kanca_unit`, '
                .'ADD INDEX `idx_lw325ph_updated_period` (`updated_at`, `periode`), '
                .'ALGORITHM=NOCOPY, LOCK=NONE'
            );

            return;
        }

        if ($redundantDefinition !== null) {
            DB::statement(
                'ALTER TABLE `lw325_ph` '
                .'DROP INDEX `idx_dhs_period_kanca_unit`, '
                .'ALGORITHM=NOCOPY, LOCK=NONE'
            );

            return;
        }

        DB::statement(
            'ALTER TABLE `lw325_ph` '
            .'ADD INDEX `idx_lw325ph_updated_period` (`updated_at`, `periode`), '
            .'ALGORITHM=NOCOPY, LOCK=NONE'
        );
    }

    public function down(): void
    {
        // Restoring the audited redundant index would reintroduce bloat and remove
        // the index used by recent-source polling. Use a reviewed forward migration
        // if the production query profile ever requires a different index shape.
    }

    private function assertNoActiveImports(): void
    {
        if (! Schema::hasTable('import_jobs')) {
            throw new RuntimeException('Tabel import_jobs tidak tersedia untuk pemeriksaan writer aktif.');
        }

        $activeJobIds = DB::table('import_jobs')
            ->whereIn('status', self::ACTIVE_IMPORT_STATUSES)
            ->orderBy('id')
            ->pluck('id')
            ->all();

        if ($activeJobIds !== []) {
            throw new RuntimeException(
                'Optimasi index lw325_ph dibatalkan karena masih ada job import aktif: '
                .implode(', ', $activeJobIds)
            );
        }
    }

    /**
     * @param  array{columns: array<int, string>, non_unique: int, index_type: string, sub_parts: array<int, int|null>}|null  $definition
     * @param  array<int, string>  $columns
     */
    private function matchesAuditedIndex(?array $definition, array $columns): bool
    {
        return $definition !== null
            && $definition['columns'] === $columns
            && $definition['non_unique'] === 1
            && $definition['index_type'] === 'BTREE'
            && $definition['sub_parts'] === array_fill(0, count($columns), null);
    }

    /**
     * @return array<string, array{columns: array<int, string>, non_unique: int, index_type: string, sub_parts: array<int, int|null>}>
     */
    private function indexDefinitionMap(): array
    {
        $rows = DB::table('information_schema.statistics')
            ->select('index_name', 'column_name', 'seq_in_index', 'non_unique', 'index_type', 'sub_part')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', self::TABLE)
            ->orderBy('index_name')
            ->orderBy('seq_in_index')
            ->get();

        $indexes = [];
        foreach ($rows as $row) {
            $indexName = (string) $row->index_name;
            $indexes[$indexName] ??= [
                'columns' => [],
                'non_unique' => (int) $row->non_unique,
                'index_type' => strtoupper((string) $row->index_type),
                'sub_parts' => [],
            ];
            $indexes[$indexName]['columns'][] = (string) $row->column_name;
            $indexes[$indexName]['sub_parts'][] = $row->sub_part === null ? null : (int) $row->sub_part;
        }

        return $indexes;
    }
};
