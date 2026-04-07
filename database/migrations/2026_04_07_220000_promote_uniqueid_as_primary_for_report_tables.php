<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * @var array<string, string>
     */
    private array $reportIdentityColumns = [
        'jumlah_merchant_detail' => 'uniqueid_namareport',
        'sv_merchant' => 'uniqueid_namareport',
        'merchant_qris' => 'uniqueid_namareport',
        'merchant_qris_volume' => 'uniqueid_namareport',
        'user_brimo_rpt_v2' => 'uniqueid_namareport',
        'user_brimo_fin' => 'uniqueid_namareport',
        'brilink_web_laporan_summary_transaksi_brilink_web' => 'uniqueid_namareport',
        'daily_loan_dinamis' => 'uniqueid_namareport',
        'simpanan_multipn' => 'uniqueid_SMPN',
        'performance_pis_per_produk' => 'uniqueid_namareport',
        'casa_brilink_web' => 'uniqueid_namareport',
        'casa_brilink_edc' => 'uniqueid_namareport',
        'lw325_ph' => 'uniqueid_namareport',
    ];

    public function up(): void
    {
        foreach ($this->reportIdentityColumns as $table => $identityColumn) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, $identityColumn)) {
                continue;
            }

            $this->ensureIdentityValuesExist($table, $identityColumn);
            $this->assertNoDuplicateIdentity($table, $identityColumn);
            $this->ensureIdentityNotNull($table, $identityColumn);
            $this->removeAutoIncrementFromId($table);

            if ($this->hasPrimaryKey($table)) {
                DB::statement('ALTER TABLE `' . $table . '` DROP PRIMARY KEY');
            }

            if (Schema::hasColumn($table, 'id')) {
                DB::statement('ALTER TABLE `' . $table . '` DROP COLUMN `id`');
            }

            foreach ($this->resolveRedundantUniqueIndexes($table, $identityColumn) as $indexName) {
                DB::statement('ALTER TABLE `' . $table . '` DROP INDEX `' . $indexName . '`');
            }

            if (!$this->hasPrimaryKey($table)) {
                DB::statement('ALTER TABLE `' . $table . '` ADD PRIMARY KEY (`' . $identityColumn . '`)');
            }
        }
    }

    public function down(): void
    {
        foreach ($this->reportIdentityColumns as $table => $identityColumn) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, $identityColumn)) {
                continue;
            }

            if ($this->hasPrimaryKey($table)) {
                DB::statement('ALTER TABLE `' . $table . '` DROP PRIMARY KEY');
            }

            DB::statement('ALTER TABLE `' . $table . '` ADD COLUMN `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST');
            DB::statement('ALTER TABLE `' . $table . '` ADD UNIQUE KEY `' . $identityColumn . '` (`' . $identityColumn . '`)');
        }
    }

    private function ensureIdentityValuesExist(string $table, string $identityColumn): void
    {
        if (!Schema::hasColumn($table, 'id')) {
            return;
        }

        DB::statement("UPDATE `{$table}` SET `{$identityColumn}` = CONCAT(LEFT(REPLACE(UUID(), '-', ''), 20), '_', COALESCE(`id`, 0)) WHERE `{$identityColumn}` IS NULL OR `{$identityColumn}` = ''");
    }

    private function assertNoDuplicateIdentity(string $table, string $identityColumn): void
    {
        $duplicate = DB::table($table)
            ->select($identityColumn, DB::raw('COUNT(*) as total'))
            ->groupBy($identityColumn)
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if ($duplicate) {
            throw new RuntimeException(
                'Cannot promote identity key on ' . $table . ': duplicate ' . $identityColumn . ' found (' .
                (string) ($duplicate->{$identityColumn} ?? 'unknown') . ').'
            );
        }
    }

    private function ensureIdentityNotNull(string $table, string $identityColumn): void
    {
        $safeIdentityColumn = str_replace("'", "''", $identityColumn);
        $column = DB::selectOne("SHOW COLUMNS FROM `{$table}` LIKE '{$safeIdentityColumn}'");
        if (!$column || !isset($column->Type)) {
            return;
        }

        DB::statement('ALTER TABLE `' . $table . '` MODIFY `' . $identityColumn . '` ' . $column->Type . ' NOT NULL');
    }

    private function hasPrimaryKey(string $table): bool
    {
        return collect(DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = 'PRIMARY'"))->isNotEmpty();
    }

    private function removeAutoIncrementFromId(string $table): void
    {
        if (!Schema::hasColumn($table, 'id')) {
            return;
        }

        $idColumn = DB::selectOne("SHOW COLUMNS FROM `{$table}` LIKE 'id'");
        if (!$idColumn || !isset($idColumn->Type)) {
            return;
        }

        $extra = strtolower((string) ($idColumn->Extra ?? ''));
        if (!str_contains($extra, 'auto_increment')) {
            return;
        }

        DB::statement('ALTER TABLE `' . $table . '` MODIFY `id` ' . $idColumn->Type . ' NOT NULL');
    }

    /**
     * @return array<int, string>
     */
    private function resolveRedundantUniqueIndexes(string $table, string $identityColumn): array
    {
        return collect(DB::select('SHOW INDEX FROM `' . $table . '`'))
            ->filter(fn ($index) => ($index->Key_name ?? null) !== 'PRIMARY')
            ->filter(fn ($index) => ((int) ($index->Non_unique ?? 1)) === 0)
            ->filter(fn ($index) => strcasecmp((string) ($index->Column_name ?? ''), $identityColumn) === 0)
            ->map(fn ($index) => (string) $index->Key_name)
            ->unique()
            ->values()
            ->all();
    }
};
