<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'dashboard_simpanan_snapshots';

    public function up(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        if (!Schema::hasColumn(self::TABLE, 'snapshot_completeness')) {
            DB::statement("ALTER TABLE `" . self::TABLE . "` ADD COLUMN `snapshot_completeness` VARCHAR(20) NOT NULL DEFAULT 'complete'");
        }

        if (!Schema::hasColumn(self::TABLE, 'partial_branches')) {
            DB::statement("ALTER TABLE `" . self::TABLE . "` ADD COLUMN `partial_branches` TEXT NULL");
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        foreach (['partial_branches', 'snapshot_completeness'] as $column) {
            if (Schema::hasColumn(self::TABLE, $column)) {
                DB::statement("ALTER TABLE `" . self::TABLE . "` DROP COLUMN `{$column}`");
            }
        }
    }
};
