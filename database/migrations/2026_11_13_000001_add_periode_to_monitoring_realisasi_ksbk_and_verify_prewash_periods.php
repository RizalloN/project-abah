<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('monitoring_realisasi_ksbk')) {
            if (! Schema::hasColumn('monitoring_realisasi_ksbk', 'periode')) {
                Schema::table('monitoring_realisasi_ksbk', function (Blueprint $table): void {
                    $table->date('periode')->nullable()->after('uniqueid_namareport');
                });
            }

            DB::table('monitoring_realisasi_ksbk')
                ->whereNull('periode')
                ->update(['periode' => '2026-11-01']);

            Schema::table('monitoring_realisasi_ksbk', function (Blueprint $table): void {
                $table->date('periode')->nullable(false)->change();
            });

            if (! $this->hasIndex('monitoring_realisasi_ksbk', 'idx_monitoring_ksbk_periode_bo', ['periode', 'bo', 'cifno'])) {
                Schema::table('monitoring_realisasi_ksbk', function (Blueprint $table): void {
                    $table->index(['periode', 'bo', 'cifno'], 'idx_monitoring_ksbk_periode_bo');
                });
            }
        }

        if (Schema::hasTable('nama_report')) {
            DB::table('nama_report')
                ->where('table_name', 'monitoring_realisasi_ksbk')
                ->update([
                    'requires_manual_periode' => 1,
                    'manual_periode_type' => 'month',
                    'manual_periode_label' => 'Periode Bulan',
                    'manual_periode_help' => 'Pilih bulan dan tahun laporan Monitoring Realisasi KSBK (format YYYY-MM, misalnya 2026-11 untuk November 2026).',
                ]);

            DB::table('nama_report')
                ->whereIn('table_name', ['pipeline_prewash', 'pipeline_prewash_boh', 'monitoring_tl_pipeline_mbm'])
                ->update([
                    'requires_manual_periode' => 1,
                    'manual_periode_type' => 'number',
                    'manual_periode_label' => 'Tahun Laporan',
                ]);
        }

        foreach (['pipeline_prewash', 'pipeline_prewash_boh', 'monitoring_tl_pipeline_mbm'] as $prewashTable) {
            if (Schema::hasTable($prewashTable) && Schema::hasColumn($prewashTable, 'tahun')) {
                DB::table($prewashTable)
                    ->where(function ($query): void {
                        $query->whereNull('tahun')->orWhere('tahun', 0);
                    })
                    ->update(['tahun' => 2026]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('nama_report')) {
            DB::table('nama_report')
                ->where('table_name', 'monitoring_realisasi_ksbk')
                ->update([
                    'requires_manual_periode' => 0,
                    'manual_periode_type' => null,
                    'manual_periode_label' => null,
                    'manual_periode_help' => null,
                ]);
        }

        if (Schema::hasTable('monitoring_realisasi_ksbk')) {
            if ($this->hasIndex('monitoring_realisasi_ksbk', 'idx_monitoring_ksbk_periode_bo', ['periode', 'bo', 'cifno'])) {
                Schema::table('monitoring_realisasi_ksbk', function (Blueprint $table): void {
                    $table->dropIndex('idx_monitoring_ksbk_periode_bo');
                });
            }

            if (Schema::hasColumn('monitoring_realisasi_ksbk', 'periode')) {
                Schema::table('monitoring_realisasi_ksbk', function (Blueprint $table): void {
                    $table->dropColumn('periode');
                });
            }
        }
    }

    private function hasIndex(string $table, string $indexName, array $columns): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (($index['name'] ?? null) === $indexName || ($index['columns'] ?? null) === $columns) {
                return true;
            }
        }

        return false;
    }
};
