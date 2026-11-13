<?php

use App\Services\Import\EvaluasiKunjunganSourceService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $name = EvaluasiKunjunganSourceService::TABLE;
        if (! Schema::hasTable($name)) {
            Schema::create($name, function (Blueprint $table): void {
                $table->engine = 'InnoDB';
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_unicode_ci';
                // Deterministic activity identity also prevents overlapping uploads.
                $table->string('uniqueid_namareport', 80)->primary();
                foreach (EvaluasiKunjunganSourceService::COLUMNS as $column) {
                    if ($column === 'tgl_kunjungan') {
                        $table->date($column);
                    } elseif (in_array($column, ['latitude', 'longitude'], true)) {
                        $table->decimal($column, 18, 6)->nullable();
                    } elseif ($column === 'perkiraan_plafond') {
                        $table->decimal($column, 30, 2)->nullable();
                    } elseif (in_array($column, EvaluasiKunjunganSourceService::LONG_TEXT_COLUMNS, true)) {
                        $table->mediumText($column);
                    } else {
                        $table->string($column, in_array($column, EvaluasiKunjunganSourceService::CODE_COLUMNS, true) ? 64 : 255);
                    }
                }
                $table->timestamps();
                $table->index(['tgl_kunjungan', 'kode_main_branch'], 'idx_kunjungan_date_branch');
            });
        }
        if (! DB::table('nama_report')->where('table_name', $name)->exists()) {
            DB::table('nama_report')->insert([
                'id_report' => (int) DB::table('nama_report')->max('id_report') + 1,
                'nama_report' => 'Evaluasi Aktivitas Kunjungan Pemasar',
                'table_name' => $name,
                'active' => 1,
                'import_controller' => 'ImportExcelController',
                'requires_manual_periode' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('nama_report')->where('table_name', EvaluasiKunjunganSourceService::TABLE)->delete();
        Schema::dropIfExists(EvaluasiKunjunganSourceService::TABLE);
    }
};
