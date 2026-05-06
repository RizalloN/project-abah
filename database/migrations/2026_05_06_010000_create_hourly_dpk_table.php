<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'hourly_dpk';
    private const REPORT_NAME = 'Hourly DPK';

    public function up(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            Schema::create(self::TABLE, function (Blueprint $table): void {
                $table->id();
                $table->foreignId('id_report')
                    ->nullable()
                    ->constrained('nama_report', 'id_report')
                    ->nullOnDelete();
                $table->date('posisi')->nullable();
                $table->string('mbname', 150)->nullable();
                $table->string('segmen2', 50)->nullable();
                $table->string('produk', 100)->nullable();
                $table->decimal('saldo', 24, 2)->nullable();
                $table->timestamps();

                $table->index(['posisi', 'mbname'], 'idx_hourly_dpk_posisi_mbname');
                $table->index(['posisi', 'segmen2', 'produk'], 'idx_hourly_dpk_posisi_segmen_produk');
            });
        }

        $this->upsertReport();
    }

    public function down(): void
    {
        if (Schema::hasTable('nama_report')) {
            DB::table('nama_report')->where('table_name', self::TABLE)->delete();
        }

        Schema::dropIfExists(self::TABLE);
    }

    private function upsertReport(): void
    {
        if (!Schema::hasTable('nama_report')) {
            return;
        }

        $now = now();
        $existingId = DB::table('nama_report')->where('table_name', self::TABLE)->value('id_report');

        if ($existingId) {
            DB::table('nama_report')
                ->where('id_report', $existingId)
                ->update([
                    'nama_report' => self::REPORT_NAME,
                    'table_name' => self::TABLE,
                    'active' => 1,
                    'import_controller' => 'ImportExcelController',
                    'requires_manual_periode' => 0,
                    'manual_periode_type' => null,
                    'manual_periode_label' => null,
                    'manual_periode_help' => null,
                    'updated_at' => $now,
                ]);

            return;
        }

        DB::table('nama_report')->insert([
            'nama_report' => self::REPORT_NAME,
            'table_name' => self::TABLE,
            'active' => 1,
            'import_controller' => 'ImportExcelController',
            'requires_manual_periode' => 0,
            'manual_periode_type' => null,
            'manual_periode_label' => null,
            'manual_periode_help' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
};
