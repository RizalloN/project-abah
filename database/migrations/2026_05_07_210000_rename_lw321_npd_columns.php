<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'lw321_npd';

    private const RENAMES = [
        'posisi_30_april_2026_kol' => 'm_min_1_kol',
        'posisi_30_april_2026_detail' => 'm_min_1_detail',
        'posisi_30_april_2026_os' => 'm_min_1_os',
        'ref_kol' => 'now_kol',
        'ref_detail' => 'now_detail',
        'ref_detai' => 'now_detail',
        'ref_os' => 'now_os',
        't_pokok' => 'now_t_pokok',
        't_bunga' => 'now_t_bunga',
        't_total' => 'now_t_total',
        'now_t_totak' => 'now_t_total',
    ];

    public function up(): void
    {
        foreach (self::RENAMES as $from => $to) {
            $this->renameColumn($from, $to);
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::RENAMES, true) as $from => $to) {
            if (in_array($from, ['ref_detai', 'now_t_totak'], true)) {
                continue;
            }

            $this->renameColumn($to, $from);
        }
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
