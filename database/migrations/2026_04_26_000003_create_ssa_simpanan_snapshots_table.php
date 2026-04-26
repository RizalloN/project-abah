<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'ssa_simpanan_snapshots';

    /**
     * Snapshot table untuk ssa_simpanan aggregations.
     *
     * Tujuan:
     * - Menyimpan pre-computed SUM(saldo) grouped by Month_Day_Year_of_Posisi, nama_cabang, produk
     * - Menghilangkan kebutuhan untuk SUM/GROUP BY pada raw table setiap kali user filter
     * - Expected improvement: 80%+ faster Dashboard Dana loads
     *
     * Rebuilding Strategy:
     * - Setiap import ssa_simpanan, snapshot diperbarui melalui job background
     * - Tidak mempengaruhi import performance (job queue)
     * - Zero downtime untuk user (old snapshot tetap available saat rebuild)
     */

    public function up(): void
    {
        if (Schema::hasTable(self::TABLE)) {
            return;
        }

        Schema::create(self::TABLE, function (Blueprint $table) {
            // Primary keys untuk lookup optimal
            $table->string('periode', 20)->index();
            $table->string('Month_Day_Year_of_Posisi', 50)->nullable()->index();
            $table->string('nama_cabang', 150)->nullable();
            $table->string('produk', 100)->nullable();
            $table->string('segmentasi', 100)->nullable()->index();

            // Aggregated value
            $table->decimal('total_saldo', 20, 2)->nullable()->default(0);

            // Metadata
            $table->unsignedInteger('record_count')->nullable()->default(0);
            $table->timestamp('snapshot_at')->useCurrent();
            $table->string('snapshot_version', 20)->nullable()->default('1');

            // Composite indexes untuk optimal query performance
            $table->index(
                ['periode', 'Month_Day_Year_of_Posisi', 'nama_cabang', 'produk'],
                'idx_ssa_snap_period_cabang_produk'
            );
            $table->index(['periode', 'segmentasi'], 'idx_ssa_snap_periode_segmen');

            // Unique constraint untuk data integrity (no duplicate aggregations)
            $table->unique(
                ['periode', 'Month_Day_Year_of_Posisi', 'nama_cabang', 'produk', 'segmentasi'],
                'uq_ssa_snap_combination'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(self::TABLE);
    }
};
