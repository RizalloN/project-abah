<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add indexes to SSA tables for dashboard filter optimization.
     *
     * These indexes support fast DISTINCT lookups for:
     * - Period dropdown (Month_Day_Year_of_Posisi)
     * - Category dropdown (segmentasi)
     * - Branch/product combinations
     *
     * Without these indexes, DISTINCT queries trigger full table scans,
     * causing 200-400ms latency on every Dashboard Dana page load.
     */

    private const SSA_SIMPANAN_TABLE = 'ssa_simpanan';
    private const SSA_PINJAMAN_TABLE = 'ssa_pinjaman';

    public function up(): void
    {
        // Index 1: For DISTINCT Month_Day_Year_of_Posisi lookups (period dropdown)
        if (Schema::hasTable(self::SSA_SIMPANAN_TABLE) && !$this->indexExists(self::SSA_SIMPANAN_TABLE, 'idx_ssa_simp_periode_filter')) {
            Schema::table(self::SSA_SIMPANAN_TABLE, function ($table) {
                $table->index(
                    ['Month_Day_Year_of_Posisi'],
                    'idx_ssa_simp_periode_filter'
                );
            });
        }

        // Index 2: For DISTINCT segmentasi lookups (category dropdown)
        if (Schema::hasTable(self::SSA_SIMPANAN_TABLE) && !$this->indexExists(self::SSA_SIMPANAN_TABLE, 'idx_ssa_simp_segmentasi_filter')) {
            Schema::table(self::SSA_SIMPANAN_TABLE, function ($table) {
                $table->index(
                    ['segmentasi'],
                    'idx_ssa_simp_segmentasi_filter'
                );
            });
        }

        // Index 3: Covering index for (periodo + cabang + produk) aggregations
        // Supports queries like: SELECT nama_cabang, produk, SUM(saldo) WHERE Month_Day_Year_of_Posisi = ?
        if (Schema::hasTable(self::SSA_SIMPANAN_TABLE) && !$this->indexExists(self::SSA_SIMPANAN_TABLE, 'idx_ssa_simp_period_cabang_produk')) {
            Schema::table(self::SSA_SIMPANAN_TABLE, function ($table) {
                $table->index(
                    ['Month_Day_Year_of_Posisi', 'nama_cabang', 'produk', 'saldo'],
                    'idx_ssa_simp_period_cabang_produk'
                );
            });
        }

        // Index 4: For SSA Pinjaman - similar filter optimization
        if (Schema::hasTable(self::SSA_PINJAMAN_TABLE) && !$this->indexExists(self::SSA_PINJAMAN_TABLE, 'idx_ssa_pinj_periode_filter')) {
            Schema::table(self::SSA_PINJAMAN_TABLE, function ($table) {
                $table->index(['periode'], 'idx_ssa_pinj_periode_filter');
            });
        }

        // Index 5: For SSA Pinjaman segmentation
        if (Schema::hasTable(self::SSA_PINJAMAN_TABLE) && !$this->indexExists(self::SSA_PINJAMAN_TABLE, 'idx_ssa_pinj_segmentasi_filter')) {
            Schema::table(self::SSA_PINJAMAN_TABLE, function ($table) {
                $table->index(['segmentasi'], 'idx_ssa_pinj_segmentasi_filter');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable(self::SSA_SIMPANAN_TABLE)) {
            if ($this->indexExists(self::SSA_SIMPANAN_TABLE, 'idx_ssa_simp_periode_filter')) {
                Schema::table(self::SSA_SIMPANAN_TABLE, fn ($table) => $table->dropIndex('idx_ssa_simp_periode_filter'));
            }
            if ($this->indexExists(self::SSA_SIMPANAN_TABLE, 'idx_ssa_simp_segmentasi_filter')) {
                Schema::table(self::SSA_SIMPANAN_TABLE, fn ($table) => $table->dropIndex('idx_ssa_simp_segmentasi_filter'));
            }
            if ($this->indexExists(self::SSA_SIMPANAN_TABLE, 'idx_ssa_simp_period_cabang_produk')) {
                Schema::table(self::SSA_SIMPANAN_TABLE, fn ($table) => $table->dropIndex('idx_ssa_simp_period_cabang_produk'));
            }
        }

        if (Schema::hasTable(self::SSA_PINJAMAN_TABLE)) {
            if ($this->indexExists(self::SSA_PINJAMAN_TABLE, 'idx_ssa_pinj_periode_filter')) {
                Schema::table(self::SSA_PINJAMAN_TABLE, fn ($table) => $table->dropIndex('idx_ssa_pinj_periode_filter'));
            }
            if ($this->indexExists(self::SSA_PINJAMAN_TABLE, 'idx_ssa_pinj_segmentasi_filter')) {
                Schema::table(self::SSA_PINJAMAN_TABLE, fn ($table) => $table->dropIndex('idx_ssa_pinj_segmentasi_filter'));
            }
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        return array_key_exists($indexName, $this->indexColumnMap($table));
    }

    private function indexColumnMap(string $table): array
    {
        $driver = DB::connection()->getDriverName();
        if (!in_array($driver, ['mysql', 'mariadb'], true)) {
            return [];
        }

        $rows = DB::table('information_schema.statistics')
            ->select('index_name', 'column_name', 'seq_in_index')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->orderBy('index_name')
            ->orderBy('seq_in_index')
            ->get();

        $indexes = [];
        foreach ($rows as $row) {
            $indexes[(string) $row->index_name][] = (string) $row->column_name;
        }

        return $indexes;
    }
};
