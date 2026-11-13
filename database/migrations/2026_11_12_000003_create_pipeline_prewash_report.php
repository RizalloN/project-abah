<?php

use App\Services\Import\PipelinePrewashSourceService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $name = PipelinePrewashSourceService::TABLE;
        if (! Schema::hasTable($name)) {
            Schema::create($name, function (Blueprint $table): void {
                $table->engine = 'InnoDB';
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_unicode_ci';
                $table->string('uniqueid_namareport', 80)->primary();
                foreach (PipelinePrewashSourceService::BASE_COLUMNS as $column) {
                    $required = in_array($column, ['nama_debt', 'branch', 'nama_unit', 'nama_kanca', 'plafond', 'os'], true);
                    if (in_array($column, ['plafond', 'os'], true)) {
                        $table->decimal($column, 30, 2);
                    } elseif ($column === 'alamat') {
                        $table->text($column)->nullable();
                    } else {
                        $codes = ['nomorhandphone', 'cif', 'norek_pinjaman', 'branch', 'pn_mantri', 'score', 'idpipeline_lama'];
                        $table->string($column, in_array($column, $codes, true) ? 64 : 255)->nullable(! $required);
                    }
                }
                $table->string('kanwil', 255)->nullable();
                $table->unsignedSmallInteger('tahun');
                // These fields are materialized from visit/loan reports, never copied from the workbook.
                $table->unsignedTinyInteger('jumlah_kunjungan')->nullable();
                $table->string('kunjungan', 8)->nullable();
                $table->decimal('plafond_real', 30, 2)->nullable();
                $table->string('status', 20)->nullable();
                foreach (PipelinePrewashSourceService::MONTH_COLUMNS as $column) {
                    $table->string($column, 12)->nullable();
                }
                $table->timestamps();
            });
        }
        // Inspect exact and left-prefix coverage, including an already existing installation.
        $indexes = Schema::getIndexes($name);
        foreach ([
            'idx_prewash_year_branch' => ['tahun', 'nama_kanca', 'branch'],
            'idx_prewash_year_cif' => ['tahun', 'cif'],
        ] as $indexName => $columns) {
            $covered = false;
            foreach ($indexes as $index) {
                if (array_slice(array_map('strtolower', $index['columns']), 0, count($columns)) === $columns) {
                    $covered = true;
                    break;
                }
            }
            if (! $covered) {
                Schema::table($name, static fn (Blueprint $table) => $table->index($columns, $indexName));
                $indexes[] = ['columns' => $columns];
            }
        }
        if (! DB::table('nama_report')->where('table_name', $name)->exists()) {
            DB::table('nama_report')->insert([
                'id_report' => (int) DB::table('nama_report')->max('id_report') + 1,
                'nama_report' => 'Pipeline Prewash', 'table_name' => $name, 'active' => 1,
                'import_controller' => 'ImportExcelController', 'requires_manual_periode' => 1,
                'manual_periode_type' => 'number', 'manual_periode_label' => 'Tahun laporan',
                'manual_periode_help' => 'Masukkan tahun laporan dalam format YYYY (1900-2199).',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('nama_report')->where('table_name', PipelinePrewashSourceService::TABLE)->delete();
        Schema::dropIfExists(PipelinePrewashSourceService::TABLE);
    }
};
