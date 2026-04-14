<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        $this->ensureDailyLoanIndexes();
        $this->ensureSimpananIndexes();
        $this->ensurePhIndexes();
    }

    public function down(): void
    {
        $this->dropIndexIfExists('daily_loan_dinamis', 'idx_dld_periode_rekening');
        $this->dropIndexIfExists('daily_loan_dinamis', 'idx_dld_periode_segmen_produk_cabang_unit');
        $this->dropIndexIfExists('daily_loan_dinamis', 'idx_dld_periode_cabang_unit');
        $this->dropIndexIfExists('daily_loan_dinamis', 'idx_dld_periode_cif_cabang');
        $this->dropIndexIfExists('daily_loan_dinamis', 'idx_dld_delete_scope');

        $this->dropIndexIfExists('simpanan_multipn', 'idx_smp_posisi_cif_jenis');
        $this->dropIndexIfExists('simpanan_multipn', 'idx_smp_posisi_status_cabang_unit');

        $this->dropIndexIfExists('lw325_ph', 'idx_lw325ph_periode_acctno_pokok');
        $this->dropIndexIfExists('lw325_ph', 'idx_lw325ph_delete_scope');
    }

    private function ensureDailyLoanIndexes(): void
    {
        if (!Schema::hasTable('daily_loan_dinamis')) {
            return;
        }

        Schema::table('daily_loan_dinamis', function (Blueprint $table) {
            $this->addIndexIfMissing($table, 'daily_loan_dinamis', 'idx_dld_periode_rekening', ['periode', 'nomor_rekening1']);
            $this->addIndexIfMissing($table, 'daily_loan_dinamis', 'idx_dld_periode_segmen_produk_cabang_unit', ['periode', 'segmen_dashboard', 'produk_dashboard', 'cabang1', 'unit1']);
            $this->addIndexIfMissing($table, 'daily_loan_dinamis', 'idx_dld_periode_cabang_unit', ['periode', 'cabang1', 'unit1']);
            $this->addIndexIfMissing($table, 'daily_loan_dinamis', 'idx_dld_periode_cif_cabang', ['periode', 'cifno', 'cabang1']);
            $this->addIndexIfMissing($table, 'daily_loan_dinamis', 'idx_dld_delete_scope', ['periode', 'cabang1', 'uniqueid_namareport']);
        });
    }

    private function ensureSimpananIndexes(): void
    {
        if (!Schema::hasTable('simpanan_multipn')) {
            return;
        }

        Schema::table('simpanan_multipn', function (Blueprint $table) {
            $this->addIndexIfMissing($table, 'simpanan_multipn', 'idx_smp_posisi_cif_jenis', ['posisi', 'CIFNO', 'jenis_simpanan']);
            $this->addIndexIfMissing($table, 'simpanan_multipn', 'idx_smp_posisi_status_cabang_unit', ['posisi', 'status', 'kantor_cabang', 'unit_kerja']);
        });
    }

    private function ensurePhIndexes(): void
    {
        if (!Schema::hasTable('lw325_ph')) {
            return;
        }

        Schema::table('lw325_ph', function (Blueprint $table) {
            $this->addIndexIfMissing($table, 'lw325_ph', 'idx_lw325ph_periode_acctno_pokok', ['periode', 'acctno', 'pokok']);
            $this->addIndexIfMissing($table, 'lw325_ph', 'idx_lw325ph_delete_scope', ['periode', 'kanca', 'uniqueid_namareport']);
        });
    }

    private function addIndexIfMissing(Blueprint $table, string $tableName, string $indexName, array $columns): void
    {
        if ($this->indexExists($tableName, $indexName) || !$this->hasColumns($tableName, $columns)) {
            return;
        }

        try {
            $table->index($columns, $indexName);
        } catch (QueryException $e) {
            if ($this->isDuplicateKeyException($e)) {
                return;
            }

            throw $e;
        }
    }

    private function dropIndexIfExists(string $tableName, string $indexName): void
    {
        if (!Schema::hasTable($tableName) || !$this->indexExists($tableName, $indexName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($indexName) {
            $table->dropIndex($indexName);
        });
    }

    private function hasColumns(string $tableName, array $columns): bool
    {
        foreach ($columns as $column) {
            if (!Schema::hasColumn($tableName, $column)) {
                return false;
            }
        }

        return true;
    }

    private function indexExists(string $tableName, string $indexName): bool
    {
        $rows = DB::select(
            'SHOW INDEX FROM `' . str_replace('`', '``', $tableName) . '` WHERE Key_name = ?',
            [$indexName]
        );

        return !empty($rows);
    }

    private function isDuplicateKeyException(QueryException $e): bool
    {
        $errorInfo = $e->errorInfo ?? [];

        return (string) ($errorInfo[1] ?? '') === '1061'
            || str_contains(strtolower($e->getMessage()), 'duplicate key name');
    }
};
