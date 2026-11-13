<?php

use App\Services\Import\PipelinePrewashMbmSourceService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $name = PipelinePrewashMbmSourceService::TABLE;
        if (! Schema::hasTable($name)) {
            Schema::create($name, function (Blueprint $table): void {
                $table->engine = 'InnoDB';
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_unicode_ci';
                $table->string('uniqueid_namareport', 80)->primary();
                foreach (PipelinePrewashMbmSourceService::BASE_COLUMNS as $column) {
                    if (in_array($column, ['plafond', 'os'], true)) {
                        $table->decimal($column, 30, 2);
                    } elseif ($column === 'alamat') {
                        $table->text($column)->nullable();
                    } else {
                        $table->string($column, in_array($column, ['nomorhandphone', 'cif', 'norek_pinjaman', 'branch', 'pn_mantri', 'idpipeline_lama', 'score'], true) ? 64 : 255)->nullable();
                    }
                }
                $table->unsignedSmallInteger('tahun');
                foreach (PipelinePrewashMbmSourceService::MONTH_COLUMNS as $column) {
                    $table->string($column, 64)->nullable();
                }
                $table->unsignedTinyInteger('jumlah_kunjungan')->nullable();
                $table->string('kunjungan', 64)->nullable();
                $table->decimal('plafond_real', 30, 2)->nullable();
                $table->string('status', 64)->nullable();
                $table->timestamps();
                // Only a new table receives this index; its primary key has no prefix coverage.
                $table->index(['tahun', 'nama_kanca', 'cif'], 'idx_pipeline_mbm_year_branch_cif');
            });
        }
        if (! DB::table('nama_report')->where('table_name', $name)->exists()) {
            DB::table('nama_report')->insert([
                'id_report' => (int) DB::table('nama_report')->max('id_report') + 1,
                'nama_report' => 'Monitoring TL Pipeline Pre_Wash PIC MBM',
                'table_name' => $name,
                'active' => 1,
                'import_controller' => 'ImportExcelController',
                'requires_manual_periode' => 1,
                'manual_periode_type' => 'number',
                'manual_periode_label' => 'Tahun Pipeline Prewash PIC MBM',
                'manual_periode_help' => 'Isi tahun laporan empat digit, misalnya 2026. Nilai kunjungan dan realisasi dihitung ulang dari sumber tahun tersebut.',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('nama_report')->where('table_name', PipelinePrewashMbmSourceService::TABLE)->delete();
        Schema::dropIfExists(PipelinePrewashMbmSourceService::TABLE);
    }
};
