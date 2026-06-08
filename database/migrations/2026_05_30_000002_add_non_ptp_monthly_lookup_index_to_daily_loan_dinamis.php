<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'daily_loan_dinamis';
    private const INDEX = 'idx_dld_nonptp_monthly_lookup';

    public function up(): void
    {
        if (!Schema::hasTable(self::TABLE) || $this->indexExists(self::INDEX)) {
            return;
        }

        foreach (['periode', 'segmen_kinerja', 'freq_payment', 'cabang_normalized', 'nomor_rekening1'] as $column) {
            if (!Schema::hasColumn(self::TABLE, $column)) {
                return;
            }
        }

        DB::statement(sprintf(
            'ALTER TABLE `%s` ADD INDEX `%s` (`periode`, `segmen_kinerja`, `freq_payment`, `cabang_normalized`, `nomor_rekening1`)',
            self::TABLE,
            self::INDEX
        ));
    }

    public function down(): void
    {
        if (!Schema::hasTable(self::TABLE) || !$this->indexExists(self::INDEX)) {
            return;
        }

        DB::statement(sprintf('ALTER TABLE `%s` DROP INDEX `%s`', self::TABLE, self::INDEX));
    }

    private function indexExists(string $indexName): bool
    {
        $rows = DB::select('SHOW INDEX FROM `' . self::TABLE . '` WHERE Key_name = ?', [$indexName]);

        return $rows !== [];
    }
};
