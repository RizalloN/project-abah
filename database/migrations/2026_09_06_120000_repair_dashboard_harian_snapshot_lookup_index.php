<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'dashboard_harian_snapshots';

    private const INDEX = 'uq_dhs_period_kanca_unit';

    private const LOOKUP_COLUMNS = ['snapshot_period', 'kanca_key', 'unit_key'];

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE) || ! Schema::hasColumns(self::TABLE, self::LOOKUP_COLUMNS)) {
            return;
        }

        if ($this->hasLeftPrefixCoverage()) {
            return;
        }

        $duplicate = DB::table(self::TABLE)
            ->selectRaw('COUNT(*) AS duplicate_count')
            ->groupBy(self::LOOKUP_COLUMNS)
            ->havingRaw('COUNT(*) > 1')
            ->limit(1)
            ->first();

        if ($duplicate !== null) {
            throw new RuntimeException(
                'Index '.self::INDEX.' tidak dibuat karena snapshot period/kanca/unit masih memiliki duplikat.'
            );
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->unique(self::LOOKUP_COLUMNS, self::INDEX);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE) || ! $this->hasNamedIndex(self::INDEX)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->dropUnique(self::INDEX);
        });
    }

    private function hasLeftPrefixCoverage(): bool
    {
        foreach (Schema::getIndexes(self::TABLE) as $index) {
            $columns = array_map('strtolower', (array) ($index['columns'] ?? []));
            if (array_slice($columns, 0, count(self::LOOKUP_COLUMNS)) === self::LOOKUP_COLUMNS) {
                return true;
            }
        }

        return false;
    }

    private function hasNamedIndex(string $name): bool
    {
        foreach (Schema::getIndexes(self::TABLE) as $index) {
            if (strcasecmp((string) ($index['name'] ?? ''), $name) === 0) {
                return true;
            }
        }

        return false;
    }
};
