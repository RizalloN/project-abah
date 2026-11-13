<?php

use App\Services\Import\SuspectReaktivasiSourceService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('suspect_reaktivasi_pinjaman')) {
            Schema::create('suspect_reaktivasi_pinjaman', function (Blueprint $table): void {
                $table->string('uniqueid_namareport', 100)->primary();
                foreach (SuspectReaktivasiSourceService::columns() as $column) {
                    if ($column === 'periode' || str_starts_with($column, 'bulan_laporan')) {
                        $table->date($column)->nullable($column !== 'periode');
                    } elseif ($column === 'outstanding') {
                        $table->decimal($column, 24, 2);
                    } else {
                        $table->string($column, $column === 'ao_pengelola' ? 500 : 180);
                    }
                }
                $table->timestamps();
                $table->unique(['periode', 'norek'], 'uq_suspect_position_account');
                $table->index(['periode', 'cabang', 'nama_uker'], 'idx_suspect_position_branch_unit');
            });
        }
        if (! DB::table('nama_report')->where('table_name', 'suspect_reaktivasi_pinjaman')->exists()) {
            DB::table('nama_report')->insert([
                'id_report' => (int) DB::table('nama_report')->max('id_report') + 1,
                'nama_report' => 'Suspect Reaktivasi Pinjaman', 'table_name' => 'suspect_reaktivasi_pinjaman',
                'active' => 1, 'import_controller' => 'ImportExcelController', 'requires_manual_periode' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('nama_report')->where('table_name', 'suspect_reaktivasi_pinjaman')->delete();
        Schema::dropIfExists('suspect_reaktivasi_pinjaman');
    }
};
