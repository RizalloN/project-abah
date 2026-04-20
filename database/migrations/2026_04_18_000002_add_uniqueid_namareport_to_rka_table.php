<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('rka')) {
            return;
        }

        $hasLegacyId = Schema::hasColumn('rka', 'id');

        if (!Schema::hasColumn('rka', 'uniqueid_namareport')) {
            Schema::table('rka', function (Blueprint $table) {
                $table->string('uniqueid_namareport', 255)->nullable()->after('id');
            });
        }

        if ($hasLegacyId && DB::getDriverName() === 'mysql') {
            DB::statement("
                UPDATE `rka`
                SET `uniqueid_namareport` = CONCAT('uuid_rka_', LPAD(CAST(`id` AS CHAR), 10, '0'))
                WHERE `uniqueid_namareport` IS NULL OR TRIM(`uniqueid_namareport`) = ''
            ");
        } elseif ($hasLegacyId) {
            DB::table('rka')
                ->select(['id', 'uniqueid_namareport'])
                ->orderBy('id')
                ->get()
                ->each(function (object $row): void {
                    $current = trim((string) ($row->uniqueid_namareport ?? ''));
                    if ($current !== '') {
                        return;
                    }

                    DB::table('rka')
                        ->where('id', $row->id)
                        ->update([
                            'uniqueid_namareport' => 'uuid_rka_' . str_pad((string) $row->id, 10, '0', STR_PAD_LEFT),
                        ]);
                });
        }

        if (!$hasLegacyId && $this->isUniqueIdAlreadyPrimaryKey()) {
            if ($this->hasIndex('rka', 'rka_uniqueid_namareport_unique')) {
                DB::statement("ALTER TABLE `rka` DROP INDEX `rka_uniqueid_namareport_unique`");
            }

            return;
        }

        if (!$this->hasIndex('rka', 'rka_uniqueid_namareport_unique')) {
            Schema::table('rka', function (Blueprint $table) {
                $table->unique('uniqueid_namareport', 'rka_uniqueid_namareport_unique');
            });
        }

        $this->promoteUniqueIdAsPrimaryKey();
    }

    public function down(): void
    {
        if (!Schema::hasTable('rka') || !Schema::hasColumn('rka', 'uniqueid_namareport')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
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

            if ($this->hasIndex('rka', 'rka_uniqueid_namareport_unique')) {
                DB::statement("ALTER TABLE `rka` DROP INDEX `rka_uniqueid_namareport_unique`");
            }

            Schema::table('rka', function (Blueprint $table) {
                $table->dropColumn('uniqueid_namareport');
            });

            return;
        }

        Schema::table('rka', function (Blueprint $table) {
            $table->dropColumn('uniqueid_namareport');
        });
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return false;
        }

        $result = DB::selectOne(
            'SELECT COUNT(*) AS aggregate FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
            [$table, $indexName]
        );

        return (int) ($result->aggregate ?? 0) > 0;
    }

    private function promoteUniqueIdAsPrimaryKey(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if (!Schema::hasColumn('rka', 'uniqueid_namareport')) {
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
        if ($primaryColumn === 'uniqueid_namareport' && !Schema::hasColumn('rka', 'id')) {
            if ($this->hasIndex('rka', 'rka_uniqueid_namareport_unique')) {
                DB::statement("ALTER TABLE `rka` DROP INDEX `rka_uniqueid_namareport_unique`");
            }

            return;
        }

        if (Schema::hasColumn('rka', 'id')) {
            DB::statement("ALTER TABLE `rka` DROP PRIMARY KEY, DROP COLUMN `id`");
        }

        if ($this->hasIndex('rka', 'rka_uniqueid_namareport_unique')) {
            DB::statement("ALTER TABLE `rka` DROP INDEX `rka_uniqueid_namareport_unique`");
        }

        DB::statement("ALTER TABLE `rka` ADD PRIMARY KEY (`uniqueid_namareport`)");
    }

    private function isUniqueIdAlreadyPrimaryKey(): bool
    {
        if (DB::getDriverName() !== 'mysql' || !Schema::hasColumn('rka', 'uniqueid_namareport')) {
            return false;
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

        return strtolower((string) ($primaryKey->column_name ?? '')) === 'uniqueid_namareport';
    }
};
