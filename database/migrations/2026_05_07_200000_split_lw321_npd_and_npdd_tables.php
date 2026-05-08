<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const NPD_TABLE = 'lw321_npd';
    private const NPDD_TABLE = 'lw321_npdd';

    public function up(): void
    {
        if (!Schema::hasTable(self::NPD_TABLE) && Schema::hasTable(self::NPDD_TABLE)) {
            Schema::rename(self::NPDD_TABLE, self::NPD_TABLE);

            $this->renameColumn(self::NPD_TABLE, 'npdd', 'next_pmt_date');
            $this->renameColumn(self::NPD_TABLE, 'npdd_update', 'update_npd');
            $this->renameColumn(self::NPD_TABLE, 'kol', 'm_min_1_kol');
            $this->renameColumn(self::NPD_TABLE, 'detail', 'm_min_1_detail');
            $this->renameColumn(self::NPD_TABLE, 'os', 'm_min_1_os');

            $this->renameIndex(self::NPD_TABLE, 'idx_lw321_npdd_period_kanca_uker', 'idx_lw321_npd_period_kanca_uker');
            $this->renameIndex(self::NPD_TABLE, 'idx_lw321_npdd_period_rekening', 'idx_lw321_npd_period_rekening');
            $this->renameIndex(self::NPD_TABLE, 'lw321_npdd_npdd_index', 'lw321_npd_next_pmt_date_index');
        }

        $this->ensureNpddTable();
        $this->ensureReportRows();
    }

    public function down(): void
    {
        if (Schema::hasTable('nama_report')) {
            DB::table('nama_report')->where('table_name', self::NPDD_TABLE)->delete();
        }

        Schema::dropIfExists(self::NPDD_TABLE);
    }

    private function ensureNpddTable(): void
    {
        if (Schema::hasTable(self::NPDD_TABLE)) {
            return;
        }

        Schema::create(self::NPDD_TABLE, function (Blueprint $table): void {
            $table->string('uniqueid_namareport', 255)->primary();
            $table->date('periode')->nullable();
            $table->string('billing', 50)->nullable()->index();
            $table->string('kanca', 150)->nullable()->index();
            $table->string('bc', 20)->nullable()->index();
            $table->string('mbm', 150)->nullable();
            $table->string('uker', 150)->nullable()->index();
            $table->string('pn', 50)->nullable()->index();
            $table->string('mantri', 150)->nullable();
            $table->string('no_rekening', 50)->nullable()->index();
            $table->string('nama_debitur', 255)->nullable();
            $table->decimal('plafon', 22, 2)->nullable();
            $table->date('npdd')->nullable()->index();
            $table->date('npdd_update')->nullable();
            $table->date('tgl_realisasi')->nullable();
            $table->date('tgl_jatuh_tempo')->nullable();
            $table->string('jangka_waktu', 30)->nullable();
            $table->string('flag_restruk', 10)->nullable();
            $table->string('kol', 20)->nullable();
            $table->string('detail', 100)->nullable();
            $table->decimal('os', 22, 2)->nullable();
            $table->decimal('wba', 22, 2)->nullable();
            $table->string('now_kol', 20)->nullable();
            $table->string('now_detail', 100)->nullable();
            $table->decimal('now_os', 22, 2)->nullable();
            $table->decimal('now_t_pokok', 22, 2)->nullable();
            $table->decimal('now_t_bunga', 22, 2)->nullable();
            $table->decimal('now_t_total', 22, 2)->nullable();
            $table->string('ptp', 50)->nullable()->index();
            $table->timestamps();

            $table->index(['periode', 'kanca', 'uker'], 'idx_lw321_npdd_period_kanca_uker');
            $table->index(['periode', 'no_rekening'], 'idx_lw321_npdd_period_rekening');
        });
    }

    private function ensureReportRows(): void
    {
        if (!Schema::hasTable('nama_report')) {
            return;
        }

        DB::table('nama_report')
            ->where('table_name', self::NPDD_TABLE)
            ->where('nama_report', 'LW321 NPDD Micro')
            ->update([
                'nama_report' => 'LW321 NPD Micro',
                'table_name' => self::NPD_TABLE,
                'updated_at' => now(),
            ]);

        $npdExists = DB::table('nama_report')->where('table_name', self::NPD_TABLE)->exists();
        if (!$npdExists) {
            DB::table('nama_report')->insert($this->reportPayload('LW321 NPD Micro', self::NPD_TABLE));
        }

        $npddExists = DB::table('nama_report')->where('table_name', self::NPDD_TABLE)->exists();
        if (!$npddExists) {
            DB::table('nama_report')->insert($this->reportPayload('LW321 NPDD Micro', self::NPDD_TABLE));
        }
    }

    private function reportPayload(string $name, string $table): array
    {
        return [
            'id_report' => (int) DB::table('nama_report')->max('id_report') + 1,
            'nama_report' => $name,
            'table_name' => $table,
            'active' => 1,
            'import_controller' => 'ImportExcelController',
            'requires_manual_periode' => 0,
            'manual_periode_type' => null,
            'manual_periode_label' => null,
            'manual_periode_help' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function renameColumn(string $table, string $from, string $to): void
    {
        if (!Schema::hasColumn($table, $from) || Schema::hasColumn($table, $to)) {
            return;
        }

        $column = DB::selectOne('SHOW COLUMNS FROM `' . $table . '` WHERE Field = ?', [$from]);
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
            $table,
            $from,
            $to,
            $type,
            $nullable,
            $defaultSql,
            $extra !== '' ? ' ' . $extra : ''
        ));
    }

    private function renameIndex(string $table, string $from, string $to): void
    {
        if (DB::getDriverName() !== 'mysql' || !$this->indexExists($table, $from) || $this->indexExists($table, $to)) {
            return;
        }

        $rows = DB::select(
            "SELECT column_name, non_unique
             FROM information_schema.statistics
             WHERE table_schema = DATABASE()
               AND table_name = ?
               AND index_name = ?
             ORDER BY seq_in_index",
            [$table, $from]
        );

        if ($rows === []) {
            return;
        }

        $columns = array_map(
            static fn ($row): string => '`' . str_replace('`', '``', (string) $row->column_name) . '`',
            $rows
        );
        $unique = ((int) ($rows[0]->non_unique ?? 1)) === 0 ? 'UNIQUE ' : '';

        DB::statement(sprintf('ALTER TABLE `%s` DROP INDEX `%s`', $table, $from));
        DB::statement(sprintf(
            'ALTER TABLE `%s` ADD %sINDEX `%s` (%s)',
            $table,
            $unique,
            $to,
            implode(', ', $columns)
        ));
    }

    private function indexExists(string $table, string $indexName): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return false;
        }

        $result = DB::selectOne(
            "SELECT COUNT(1) AS aggregate_count
             FROM information_schema.statistics
             WHERE table_schema = DATABASE()
               AND table_name = ?
               AND index_name = ?",
            [$table, $indexName]
        );

        return (int) ($result->aggregate_count ?? 0) > 0;
    }
};
