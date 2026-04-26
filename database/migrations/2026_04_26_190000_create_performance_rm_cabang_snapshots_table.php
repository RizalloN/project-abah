<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create performance_rm_cabang_snapshots table for fast dashboard aggregation.
     *
     * This table stores pre-aggregated performance metrics at the cabang level,
     * derived from performance_rm_snapshots. It eliminates the overhead of
     * pivoting thousands of RM rows in PHP when the dashboard only needs
     * cabang-level summary data.
     *
     * Data flow:
     * daily_loan_dinamis
     *   → (Aggregated by RM level)
     *   → performance_rm_snapshots (detailed: per cabang, unit, rm, produk)
     *   → (Aggregated by cabang level)
     *   → performance_rm_cabang_snapshots (summary: per cabang, produk) ← Dashboard uses this
     *
     * Expected performance improvement: 10-20x faster dashboard load
     */

    public function up(): void
    {
        Schema::create('performance_rm_cabang_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('periode')->index();
            $table->string('cabang', 100);
            $table->string('segmen', 50);
            $table->string('produk', 100);

            // Aggregated metrics (SUM across all RM in this cabang/segmen/produk combo)
            $table->decimal('loan_os', 20, 2)->default(0);
            $table->decimal('lancar_os', 20, 2)->default(0);
            $table->decimal('sml_os', 20, 2)->default(0);
            $table->decimal('npl_os', 20, 2)->default(0);
            $table->integer('total_deb')->default(0);
            $table->integer('lancar_deb')->default(0);
            $table->integer('sml_deb')->default(0);
            $table->integer('npl_deb')->default(0);
            $table->decimal('restruk_os', 20, 2)->default(0);
            $table->integer('realisasi_deb')->default(0);
            $table->decimal('realisasi_os', 20, 2)->default(0);
            $table->decimal('total_deposit', 20, 2)->default(0);
            $table->decimal('plafon', 20, 2)->default(0);

            $table->timestamps();

            // Primary filter index: periode + cabang + segmen
            // Used by: Dashboard when fetching all data for a period and cabang
            $table->index(['periode', 'cabang', 'segmen'], 'idx_pcs_periode_cabang_segmen');

            // Secondary filter index: periode + segmen + produk
            // Used by: Dashboard when filtering by product across all cabangs
            $table->index(['periode', 'segmen', 'produk'], 'idx_pcs_periode_segmen_produk');

            // Unique constraint: Prevent duplicate aggregates
            $table->unique(['periode', 'cabang', 'segmen', 'produk'], 'unique_pcs_snapshot');
        });

        // Backfill historical data from performance_rm_snapshots
        $this->backfillFromRmSnapshots();
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_rm_cabang_snapshots');
    }

    private function backfillFromRmSnapshots(): void
    {
        if (!Schema::hasTable('performance_rm_snapshots')) {
            return;
        }

        // Aggregate existing RM snapshots to cabang level
        DB::statement(
            <<<'SQL'
            INSERT INTO performance_rm_cabang_snapshots (
                periode, cabang, segmen, produk,
                loan_os, lancar_os, sml_os, npl_os,
                total_deb, lancar_deb, sml_deb, npl_deb,
                restruk_os, realisasi_deb, realisasi_os,
                total_deposit, plafon,
                created_at, updated_at
            )
            SELECT
                p.periode,
                p.cabang,
                p.segmen,
                p.produk,
                SUM(COALESCE(p.loan_os, 0)) as loan_os,
                SUM(COALESCE(p.lancar_os, 0)) as lancar_os,
                SUM(COALESCE(p.sml_os, 0)) as sml_os,
                SUM(COALESCE(p.npl_os, 0)) as npl_os,
                SUM(COALESCE(p.total_deb, 0)) as total_deb,
                SUM(COALESCE(p.lancar_deb, 0)) as lancar_deb,
                SUM(COALESCE(p.sml_deb, 0)) as sml_deb,
                SUM(COALESCE(p.npl_deb, 0)) as npl_deb,
                SUM(COALESCE(p.restruk_os, 0)) as restruk_os,
                SUM(COALESCE(p.realisasi_deb, 0)) as realisasi_deb,
                SUM(COALESCE(p.realisasi_os, 0)) as realisasi_os,
                SUM(COALESCE(p.total_deposit, 0)) as total_deposit,
                SUM(COALESCE(p.plafon, 0)) as plafon,
                NOW(),
                NOW()
            FROM performance_rm_snapshots p
            WHERE p.segmen IS NOT NULL
            GROUP BY p.periode, p.cabang, p.segmen, p.produk
            ON DUPLICATE KEY UPDATE
                loan_os = VALUES(loan_os),
                lancar_os = VALUES(lancar_os),
                sml_os = VALUES(sml_os),
                npl_os = VALUES(npl_os),
                total_deb = VALUES(total_deb),
                lancar_deb = VALUES(lancar_deb),
                sml_deb = VALUES(sml_deb),
                npl_deb = VALUES(npl_deb),
                restruk_os = VALUES(restruk_os),
                realisasi_deb = VALUES(realisasi_deb),
                realisasi_os = VALUES(realisasi_os),
                total_deposit = VALUES(total_deposit),
                plafon = VALUES(plafon),
                updated_at = NOW()
            SQL
        );
    }
};
