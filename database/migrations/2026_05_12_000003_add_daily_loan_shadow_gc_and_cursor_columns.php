<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'daily_loan_dinamis';

    /**
     * @var array<string, array{type:string, expression:string, legacy:string}>
     */
    private array $generatedColumns = [
        'cabang_normalized_gc' => [
            'type' => 'VARCHAR(150)',
            'expression' => "UPPER(TRIM(COALESCE(`cabang1`, '')))",
            'legacy' => 'cabang_normalized',
        ],
        'unit_normalized_gc' => [
            'type' => 'VARCHAR(150)',
            'expression' => "UPPER(TRIM(COALESCE(`unit1`, '')))",
            'legacy' => 'unit_normalized',
        ],
        'branch_normalized_gc' => [
            'type' => 'VARCHAR(100)',
            'expression' => "UPPER(TRIM(COALESCE(`branch1`, '')))",
            'legacy' => 'branch_normalized',
        ],
        'rm_normalized_gc' => [
            'type' => 'VARCHAR(190)',
            'expression' => "UPPER(TRIM(COALESCE(`pn_pengelola1`, '')))",
            'legacy' => 'rm_normalized',
        ],
        'pn_pemutus_normalized_gc' => [
            'type' => 'VARCHAR(100)',
            'expression' => "NULLIF(TRIM(LEADING '0' FROM TRIM(SUBSTRING_INDEX(COALESCE(`pn_pemutus1`, ''), '-', 1))), '')",
            'legacy' => 'pn_pemutus_normalized',
        ],
        'cifno_clean_gc' => [
            'type' => 'VARCHAR(50)',
            'expression' => "REGEXP_REPLACE(COALESCE(`cifno`, ''), '[^0-9]', '')",
            'legacy' => 'cifno_clean',
        ],
    ];

    public function up(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        if (!Schema::hasColumn(self::TABLE, 'shadow_built_at')) {
            DB::statement('ALTER TABLE `' . self::TABLE . '` ADD COLUMN `shadow_built_at` DATETIME(6) NULL');
        }

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true) && !$this->indexExists('idx_dld_periode_shadow_built_at')) {
            DB::statement('CREATE INDEX `idx_dld_periode_shadow_built_at` ON `' . self::TABLE . '` (`periode`, `shadow_built_at`)');
        }

        if (!in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            foreach ($this->generatedColumns as $column => $definition) {
                if (!Schema::hasColumn(self::TABLE, $column)) {
                    DB::statement('ALTER TABLE `' . self::TABLE . '` ADD COLUMN `' . $column . '` ' . $definition['type'] . ' NULL');
                }
            }

            return;
        }

        $this->assertGeneratedColumnSupported();
        $this->assertInnoDb();

        foreach ($this->generatedColumns as $column => $definition) {
            if (Schema::hasColumn(self::TABLE, $column)) {
                continue;
            }

            DB::statement(sprintf(
                'ALTER TABLE `%s` ADD COLUMN `%s` %s GENERATED ALWAYS AS (%s) STORED',
                self::TABLE,
                $column,
                $definition['type'],
                $definition['expression']
            ));
        }

        $this->backfillLegacyColumnsFromGeneratedColumns();
    }

    public function down(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->dropIndexIfExists('idx_dld_periode_shadow_built_at');
        }

        if (Schema::hasColumn(self::TABLE, 'shadow_built_at')) {
            DB::statement('ALTER TABLE `' . self::TABLE . '` DROP COLUMN `shadow_built_at`');
        }

        foreach (array_keys($this->generatedColumns) as $column) {
            if (Schema::hasColumn(self::TABLE, $column)) {
                DB::statement('ALTER TABLE `' . self::TABLE . '` DROP COLUMN `' . $column . '`');
            }
        }
    }

    private function assertGeneratedColumnSupported(): void
    {
        $version = (string) DB::selectOne('SELECT VERSION() as version')->version;
        $isMaria = stripos($version, 'mariadb') !== false;
        preg_match('/(\d+)\.(\d+)\.(\d+)/', $version, $matches);
        $normalized = sprintf('%d.%d.%d', (int) ($matches[1] ?? 0), (int) ($matches[2] ?? 0), (int) ($matches[3] ?? 0));
        $minimum = $isMaria ? '10.0.5' : '8.0.4';

        if (version_compare($normalized, $minimum, '<')) {
            throw new RuntimeException(sprintf(
                'Generated shadow columns require %s >= %s; detected %s.',
                $isMaria ? 'MariaDB' : 'MySQL',
                $minimum,
                $version
            ));
        }
    }

    private function assertInnoDb(): void
    {
        $engine = DB::selectOne(
            'SELECT ENGINE as engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [self::TABLE]
        );

        if (strtoupper((string) ($engine->engine ?? '')) !== 'INNODB') {
            throw new RuntimeException(self::TABLE . ' must use InnoDB before generated shadow columns are added.');
        }
    }

    private function backfillLegacyColumnsFromGeneratedColumns(): void
    {
        $assignments = [];
        $conditions = [];

        foreach ($this->generatedColumns as $column => $definition) {
            $legacy = $definition['legacy'];
            if (!Schema::hasColumn(self::TABLE, $legacy) || !Schema::hasColumn(self::TABLE, $column)) {
                continue;
            }

            $assignments[] = "`{$legacy}` = COALESCE(NULLIF(`{$legacy}`, ''), `{$column}`)";
            $conditions[] = "`{$legacy}` IS NULL OR `{$legacy}` = ''";
        }

        if ($assignments === [] || $conditions === []) {
            return;
        }

        DB::statement(sprintf(
            'UPDATE `%s` SET %s WHERE %s',
            self::TABLE,
            implode(', ', $assignments),
            implode(' OR ', $conditions)
        ));
    }

    private function indexExists(string $index): bool
    {
        return (int) DB::selectOne(
            'SELECT COUNT(*) as aggregate FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
            [self::TABLE, $index]
        )->aggregate > 0;
    }

    private function dropIndexIfExists(string $index): void
    {
        if ($this->indexExists($index)) {
            DB::statement('ALTER TABLE `' . self::TABLE . '` DROP INDEX `' . $index . '`');
        }
    }
};
