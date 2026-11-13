<?php

use App\Services\Import\MonitoringKsbkSourceService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $name = MonitoringKsbkSourceService::TABLE;
        if (! Schema::hasTable($name)) {
            Schema::create($name, function (Blueprint $table): void {
                $table->engine = 'InnoDB';
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_unicode_ci';
                $table->string('uniqueid_namareport', 80)->primary();
                foreach (MonitoringKsbkSourceService::BASE_COLUMNS as $column) {
                    if ($column === 'plafond_pengajuan') {
                        $table->decimal($column, 30, 2)->nullable();
                    } elseif ($column === 'rencana_real_date') {
                        $table->dateTime($column)->nullable();
                    } elseif ($column === 'tgl_inject') {
                        $table->date($column)->nullable();
                    } else {
                        $table->string($column, in_array($column, ['branch', 'pernr', 'nik', 'cifno', 'bo', 'ro', 'batch'], true) ? 64 : 255)->nullable();
                    }
                }
                $table->string('cek_real', 64)->nullable();
                $table->decimal('plafond', 30, 2)->nullable();
                $table->string('ln_type', 64)->nullable();
                $table->string('ksbk_non', 64)->nullable();
                $table->timestamps();
                // New-table index only: the primary identity provides no BO/CIF prefix coverage.
                $table->index(['bo', 'cifno'], 'idx_monitoring_ksbk_bo_cif');
            });
        }
        if (! DB::table('nama_report')->where('table_name', $name)->exists()) {
            DB::table('nama_report')->insert([
                'id_report' => (int) DB::table('nama_report')->max('id_report') + 1,
                'nama_report' => 'Monitoring Realisasi KSBK',
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
        DB::table('nama_report')->where('table_name', MonitoringKsbkSourceService::TABLE)->delete();
        Schema::dropIfExists(MonitoringKsbkSourceService::TABLE);
    }
};
