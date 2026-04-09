<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('input_rekanan')) {
            if (Schema::hasColumn('input_rekanan', 'cif')) {
                $this->addIndexIfMissing('input_rekanan', 'idx_input_rekanan_cif', ['cif']);
            }

            if (Schema::hasColumn('input_rekanan', 'periode') && Schema::hasColumn('input_rekanan', 'cif')) {
                $this->addIndexIfMissing('input_rekanan', 'idx_input_rekanan_periode_cif', ['periode', 'cif']);
            }
        }

        if (Schema::hasTable('bod_boc')) {
            if (Schema::hasColumn('bod_boc', 'cif')) {
                $this->addIndexIfMissing('bod_boc', 'idx_bod_boc_cif', ['cif']);
            }

            if (Schema::hasColumn('bod_boc', 'periode') && Schema::hasColumn('bod_boc', 'cif')) {
                $this->addIndexIfMissing('bod_boc', 'idx_bod_boc_periode_cif', ['periode', 'cif']);
            }
        }

        if (Schema::hasTable('brilink_web_laporan_summary_transaksi_brilink_web')
            && Schema::hasColumn('brilink_web_laporan_summary_transaksi_brilink_web', 'periode')
            && Schema::hasColumn('brilink_web_laporan_summary_transaksi_brilink_web', 'cabang')) {
            $this->addIndexIfMissing(
                'brilink_web_laporan_summary_transaksi_brilink_web',
                'idx_brilink_summary_periode_cabang',
                ['periode', 'cabang']
            );
        }

        if (Schema::hasTable('casa_brilink_web')
            && Schema::hasColumn('casa_brilink_web', 'periode')
            && Schema::hasColumn('casa_brilink_web', 'mbdesc')) {
            $this->addIndexIfMissing('casa_brilink_web', 'idx_casa_web_periode_mbdesc', ['periode', 'mbdesc']);
        }

        if (Schema::hasTable('casa_brilink_edc')
            && Schema::hasColumn('casa_brilink_edc', 'periode')
            && Schema::hasColumn('casa_brilink_edc', 'mbdesc')) {
            $this->addIndexIfMissing('casa_brilink_edc', 'idx_casa_edc_periode_mbdesc', ['periode', 'mbdesc']);
        }

        if (Schema::hasTable('simpanan_multipn')
            && Schema::hasColumn('simpanan_multipn', 'posisi')
            && Schema::hasColumn('simpanan_multipn', 'updated_at')) {
            $this->addIndexIfMissing('simpanan_multipn', 'idx_smp_posisi_updated', ['posisi', 'updated_at']);
        }
    }

    public function down(): void
    {
        $this->dropIndexIfExists('input_rekanan', 'idx_input_rekanan_cif');
        $this->dropIndexIfExists('input_rekanan', 'idx_input_rekanan_periode_cif');
        $this->dropIndexIfExists('bod_boc', 'idx_bod_boc_cif');
        $this->dropIndexIfExists('bod_boc', 'idx_bod_boc_periode_cif');
        $this->dropIndexIfExists('brilink_web_laporan_summary_transaksi_brilink_web', 'idx_brilink_summary_periode_cabang');
        $this->dropIndexIfExists('casa_brilink_web', 'idx_casa_web_periode_mbdesc');
        $this->dropIndexIfExists('casa_brilink_edc', 'idx_casa_edc_periode_mbdesc');
        $this->dropIndexIfExists('simpanan_multipn', 'idx_smp_posisi_updated');
    }

    private function addIndexIfMissing(string $table, string $indexName, array $columns): void
    {
        if (!Schema::hasTable($table) || $this->hasIndex($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($indexName, $columns) {
            $blueprint->index($columns, $indexName);
        });
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (!Schema::hasTable($table) || !$this->hasIndex($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($indexName) {
            $blueprint->dropIndex($indexName);
        });
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $result = DB::select('SHOW INDEX FROM `' . str_replace('`', '``', $table) . '` WHERE Key_name = ?', [$indexName]);

        return !empty($result);
    }
};

