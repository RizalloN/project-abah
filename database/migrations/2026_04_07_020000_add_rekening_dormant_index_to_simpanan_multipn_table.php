<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('simpanan_multipn')) {
            return;
        }

        $indexes = collect(DB::select('SHOW INDEX FROM simpanan_multipn'))
            ->pluck('Key_name');

        if (!$indexes->contains('idx_simp_dormant_posisi_status_cabang')) {
            Schema::table('simpanan_multipn', function (Blueprint $table) {
                $table->index(
                    ['posisi', 'status', 'kantor_cabang'],
                    'idx_simp_dormant_posisi_status_cabang'
                );
            });
        }

        if (!$indexes->contains('idx_simp_dormant_posisi_status_cabang_unit')) {
            Schema::table('simpanan_multipn', function (Blueprint $table) {
                $table->index(
                    ['posisi', 'status', 'kantor_cabang', 'unit_kerja'],
                    'idx_simp_dormant_posisi_status_cabang_unit'
                );
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('simpanan_multipn')) {
            return;
        }

        $indexes = collect(DB::select('SHOW INDEX FROM simpanan_multipn'))
            ->pluck('Key_name');

        if ($indexes->contains('idx_simp_dormant_posisi_status_cabang')) {
            Schema::table('simpanan_multipn', function (Blueprint $table) {
                $table->dropIndex('idx_simp_dormant_posisi_status_cabang');
            });
        }

        if ($indexes->contains('idx_simp_dormant_posisi_status_cabang_unit')) {
            Schema::table('simpanan_multipn', function (Blueprint $table) {
                $table->dropIndex('idx_simp_dormant_posisi_status_cabang_unit');
            });
        }
    }
};
