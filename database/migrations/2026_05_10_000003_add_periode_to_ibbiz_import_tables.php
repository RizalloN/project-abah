<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = [
        'ibbisniz_corp',
        'usak_ibbiz_uker',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            if (!Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'periode')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->date('periode')->nullable()->after('uniqueid_namareport');
            });

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->index('periode', $tableName . '_periode_index');
            });
        }

        if (Schema::hasTable('nama_report')) {
            DB::table('nama_report')
                ->where('table_name', 'ibbisniz_corp')
                ->update([
                    'requires_manual_periode' => 1,
                    'manual_periode_type' => 'date',
                    'manual_periode_label' => 'Tanggal Periode',
                    'manual_periode_help' => 'Wajib isi tanggal periode manual (YYYY-MM-DD) untuk IB Biz Kinerja By FCORPID.',
                    'updated_at' => now(),
                ]);

            DB::table('nama_report')
                ->where('table_name', 'usak_ibbiz_uker')
                ->update([
                    'requires_manual_periode' => 0,
                    'manual_periode_type' => null,
                    'manual_periode_label' => null,
                    'manual_periode_help' => null,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('nama_report')) {
            DB::table('nama_report')
                ->whereIn('table_name', self::TABLES)
                ->update([
                    'requires_manual_periode' => 0,
                    'manual_periode_type' => null,
                    'manual_periode_label' => null,
                    'manual_periode_help' => null,
                    'updated_at' => now(),
                ]);
        }

        foreach (self::TABLES as $tableName) {
            if (!Schema::hasTable($tableName) || !Schema::hasColumn($tableName, 'periode')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->dropIndex($tableName . '_periode_index');
                $table->dropColumn('periode');
            });
        }
    }
};
