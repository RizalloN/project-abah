<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'performance_rm_snapshots';
    private const INDEXES = [
        'idx_p_s_c' => ['periode', 'segmen', 'cabang', 'plafon'],
        'idx_p_s_rm' => ['periode', 'segmen', 'rm', 'plafon'],
    ];
    private const LEGACY_INDEXES = [
        'idx_p_s_c' => ['periode', 'segmen', 'cabang'],
        'idx_p_s_rm' => ['periode', 'segmen', 'rm'],
    ];

    public function up(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        if (!Schema::hasColumn(self::TABLE, 'plafon')) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->decimal('plafon', 20, 2)->default(0)->after('produk');
            });
        }

        foreach (self::INDEXES as $indexName => $columns) {
            if ($this->indexColumns($indexName) === $columns) {
                continue;
            }

            if ($this->indexExists($indexName)) {
                Schema::table(self::TABLE, function (Blueprint $table) use ($indexName): void {
                    $table->dropIndex($indexName);
                });
            }

            Schema::table(self::TABLE, function (Blueprint $table) use ($indexName, $columns): void {
                $table->index($columns, $indexName);
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        foreach (array_keys(self::INDEXES) as $indexName) {
            if ($this->indexExists($indexName)) {
                Schema::table(self::TABLE, function (Blueprint $table) use ($indexName): void {
                    $table->dropIndex($indexName);
                });
            }
        }

        foreach (self::LEGACY_INDEXES as $indexName => $columns) {
            Schema::table(self::TABLE, function (Blueprint $table) use ($indexName, $columns): void {
                $table->index($columns, $indexName);
            });
        }

        if (Schema::hasColumn(self::TABLE, 'plafon')) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->dropColumn('plafon');
            });
        }
    }

    private function indexExists(string $indexName): bool
    {
        return $this->indexColumns($indexName) !== [];
    }

    /**
     * @return array<int, string>
     */
    private function indexColumns(string $indexName): array
    {
        $driver = DB::connection()->getDriverName();
        if (!in_array($driver, ['mysql', 'mariadb'], true)) {
            return [];
        }

        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', self::TABLE)
            ->where('index_name', $indexName)
            ->orderBy('seq_in_index')
            ->pluck('column_name')
            ->map(static fn ($column): string => (string) $column)
            ->all();
    }
};
