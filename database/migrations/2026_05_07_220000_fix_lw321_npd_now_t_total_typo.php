<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'lw321_npd';

    public function up(): void
    {
        $this->renameColumn('now_t_totak', 'now_t_total');
    }

    public function down(): void
    {
        $this->renameColumn('now_t_total', 'now_t_totak');
    }

    private function renameColumn(string $from, string $to): void
    {
        if (!Schema::hasTable(self::TABLE) || !Schema::hasColumn(self::TABLE, $from) || Schema::hasColumn(self::TABLE, $to)) {
            return;
        }

        $column = DB::selectOne('SHOW COLUMNS FROM `' . self::TABLE . '` WHERE Field = ?', [$from]);
        $type = (string) ($column->Type ?? '');
        if ($type === '') {
            return;
        }

        $nullable = strtoupper((string) ($column->Null ?? 'YES')) === 'NO' ? ' NOT NULL' : ' NULL';
        $default = $column->Default ?? null;
        $defaultSql = $default === null ? '' : ' DEFAULT ' . DB::getPdo()->quote((string) $default);
        $extra = trim((string) ($column->Extra ?? ''));

        DB::statement(sprintf(
            'ALTER TABLE `%s` CHANGE `%s` `%s` %s%s%s%s',
            self::TABLE,
            $from,
            $to,
            $type,
            $nullable,
            $defaultSql,
            $extra !== '' ? ' ' . $extra : ''
        ));
    }
};
