<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const IBBISNIZ_CORP_TABLE = 'ibbisniz_corp';
    private const USAK_IBBIZ_UKER_TABLE = 'usak_ibbiz_uker';

    public function up(): void
    {
        if (!Schema::hasTable(self::IBBISNIZ_CORP_TABLE)) {
            Schema::create(self::IBBISNIZ_CORP_TABLE, function (Blueprint $table): void {
                $table->string('uniqueid_namareport', 255)->primary();
                $table->string('wilayah', 150)->nullable()->index();
                $table->string('cabang', 150)->nullable()->index();
                $table->string('uker', 180)->nullable()->index();
                $table->string('corporate_id', 50)->nullable()->index();
                $table->string('nama_perusahaan', 255)->nullable();
                $table->decimal('jml_trx_sukses', 20, 2)->nullable();
                $table->decimal('nominal', 25, 2)->nullable();
                $table->decimal('fee_transaksi', 20, 2)->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable(self::USAK_IBBIZ_UKER_TABLE)) {
            Schema::create(self::USAK_IBBIZ_UKER_TABLE, function (Blueprint $table): void {
                $table->string('uniqueid_namareport', 255)->primary();
                $table->string('kanwil', 150)->nullable()->index();
                $table->string('kanca', 150)->nullable()->index();
                $table->string('uker', 180)->nullable()->index();
                $table->string('corporate_id', 50)->nullable()->index();
                $table->string('nama_perusahaan', 255)->nullable();
                $table->string('status', 50)->nullable()->index();
                $table->string('deskripsi', 100)->nullable()->index();
                $table->string('referral', 50)->nullable()->index();
                $table->timestamps();
            });
        }

        $this->ensureReport(
            'IB Bisnis - Laporan Kinerja By FCORPID',
            self::IBBISNIZ_CORP_TABLE
        );

        $this->ensureReport(
            'IB Bisnis - Laporan User Aktif By Uker',
            self::USAK_IBBIZ_UKER_TABLE
        );
    }

    public function down(): void
    {
        DB::table('nama_report')
            ->whereIn('table_name', [self::IBBISNIZ_CORP_TABLE, self::USAK_IBBIZ_UKER_TABLE])
            ->delete();

        Schema::dropIfExists(self::USAK_IBBIZ_UKER_TABLE);
        Schema::dropIfExists(self::IBBISNIZ_CORP_TABLE);
    }

    private function ensureReport(string $reportName, string $tableName): void
    {
        if (!Schema::hasTable('nama_report')) {
            return;
        }

        $now = now();
        $existing = DB::table('nama_report')->where('table_name', $tableName)->first();

        if ($existing) {
            DB::table('nama_report')
                ->where('table_name', $tableName)
                ->update([
                    'nama_report' => $reportName,
                    'active' => 1,
                    'import_controller' => 'ImportFileController',
                    'requires_manual_periode' => 0,
                    'updated_at' => $now,
                ]);

            return;
        }

        DB::table('nama_report')->insert([
            'id_report' => ((int) DB::table('nama_report')->max('id_report')) + 1,
            'nama_report' => $reportName,
            'table_name' => $tableName,
            'active' => 1,
            'import_controller' => 'ImportFileController',
            'requires_manual_periode' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
};
