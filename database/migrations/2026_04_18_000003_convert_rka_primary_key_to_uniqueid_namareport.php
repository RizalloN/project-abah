<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql' || !Schema::hasTable('rka') || !Schema::hasColumn('rka', 'uniqueid_namareport')) {
            return;
        }

        DB::statement("ALTER TABLE `rka` MODIFY `uniqueid_namareport` VARCHAR(255) NOT NULL");

        $primaryKey = DB::selectOne("
            SELECT kcu.COLUMN_NAME AS column_name
            FROM information_schema.TABLE_CONSTRAINTS tc
            INNER JOIN information_schema.KEY_COLUMN_USAGE kcu
                ON tc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA
                AND tc.TABLE_NAME = kcu.TABLE_NAME
                AND tc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
            WHERE tc.CONSTRAINT_SCHEMA = DATABASE()
                AND tc.TABLE_NAME = 'rka'
                AND tc.CONSTRAINT_TYPE = 'PRIMARY KEY'
            LIMIT 1
        ");

        $primaryColumn = strtolower((string) ($primaryKey->column_name ?? ''));

        if ($primaryColumn !== 'uniqueid_namareport') {
            if (Schema::hasColumn('rka', 'id')) {
                DB::statement("ALTER TABLE `rka` DROP PRIMARY KEY, DROP COLUMN `id`");
            } else {
                DB::statement("ALTER TABLE `rka` DROP PRIMARY KEY");
            }
        } elseif (Schema::hasColumn('rka', 'id')) {
            DB::statement("ALTER TABLE `rka` DROP COLUMN `id`");
        }

        if ($this->hasIndex('rka', 'rka_uniqueid_namareport_unique')) {
            DB::statement("ALTER TABLE `rka` DROP INDEX `rka_uniqueid_namareport_unique`");
        }

        $primaryKey = DB::selectOne("
            SELECT kcu.COLUMN_NAME AS column_name
            FROM information_schema.TABLE_CONSTRAINTS tc
            INNER JOIN information_schema.KEY_COLUMN_USAGE kcu
                ON tc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA
                AND tc.TABLE_NAME = kcu.TABLE_NAME
                AND tc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
            WHERE tc.CONSTRAINT_SCHEMA = DATABASE()
                AND tc.TABLE_NAME = 'rka'
                AND tc.CONSTRAINT_TYPE = 'PRIMARY KEY'
            LIMIT 1
        ");

        if (strtolower((string) ($primaryKey->column_name ?? '')) !== 'uniqueid_namareport') {
            DB::statement("ALTER TABLE `rka` ADD PRIMARY KEY (`uniqueid_namareport`)");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql' || !Schema::hasTable('rka') || !Schema::hasColumn('rka', 'uniqueid_namareport')) {
            return;
        }

        $primaryKey = DB::selectOne("
            SELECT kcu.COLUMN_NAME AS column_name
            FROM information_schema.TABLE_CONSTRAINTS tc
            INNER JOIN information_schema.KEY_COLUMN_USAGE kcu
                ON tc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA
                AND tc.TABLE_NAME = kcu.TABLE_NAME
                AND tc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
            WHERE tc.CONSTRAINT_SCHEMA = DATABASE()
                AND tc.TABLE_NAME = 'rka'
                AND tc.CONSTRAINT_TYPE = 'PRIMARY KEY'
            LIMIT 1
        ");

        if (strtolower((string) ($primaryKey->column_name ?? '')) === 'uniqueid_namareport') {
            DB::statement("ALTER TABLE `rka` DROP PRIMARY KEY");
        }

        if (!Schema::hasColumn('rka', 'id')) {
            DB::statement("ALTER TABLE `rka` ADD COLUMN `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST");
        }

        if (!$this->hasIndex('rka', 'rka_uniqueid_namareport_unique')) {
            DB::statement("ALTER TABLE `rka` ADD UNIQUE INDEX `rka_uniqueid_namareport_unique` (`uniqueid_namareport`)");
        }
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $result = DB::selectOne(
            'SELECT COUNT(*) AS aggregate FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
            [$table, $indexName]
        );

        return (int) ($result->aggregate ?? 0) > 0;
    }
};
