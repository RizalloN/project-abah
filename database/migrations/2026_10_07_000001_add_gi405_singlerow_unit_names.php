<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gi405_singlerow', function (Blueprint $table): void {
            $table->string('nama_cabang', 180)->nullable()->after('branch');
            $table->string('nama_uker', 180)->nullable()->after('nama_cabang');
        });

        if (! Schema::hasTable('referensi_uker')) {
            return;
        }

        foreach (DB::table('referensi_uker')->get(['kode_uker', 'nama_cabang', 'nama_uker']) as $reference) {
            $code = (string) $reference->kode_uker;
            DB::table('gi405_singlerow')
                ->whereIn('branch', array_unique([$code, ltrim($code, '0') ?: '0']))
                ->update([
                    'nama_cabang' => $reference->nama_cabang,
                    'nama_uker' => $reference->nama_uker,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('gi405_singlerow', function (Blueprint $table): void {
            $table->dropColumn(['nama_cabang', 'nama_uker']);
        });
    }
};
