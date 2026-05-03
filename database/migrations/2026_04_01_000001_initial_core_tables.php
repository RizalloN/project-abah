<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Framework: Users
        if (!Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('pn', 20)->unique();
                $table->string('email')->nullable()->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->string('role', 20)->default('user');
                $table->rememberToken();
                $table->timestamps();
            });
        }

        // 2. Framework: Sessions
        if (!Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->foreignId('user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });
        }

        // 3. Framework: Jobs & Queues
        if (!Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table) {
                $table->id();
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }

        if (!Schema::hasTable('job_batches')) {
            Schema::create('job_batches', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('name');
                $table->integer('total_jobs');
                $table->integer('pending_jobs');
                $table->integer('failed_jobs');
                $table->longText('failed_job_ids');
                $table->mediumText('options')->nullable();
                $table->integer('cancelled_at')->nullable();
                $table->integer('created_at');
                $table->integer('finished_at')->nullable();
            });
        }

        if (!Schema::hasTable('failed_jobs')) {
            Schema::create('failed_jobs', function (Blueprint $table) {
                $table->id();
                $table->string('uuid')->unique();
                $table->text('connection');
                $table->text('queue');
                $table->longText('payload');
                $table->longText('exception');
                $table->timestamp('failed_at')->useCurrent();
            });
        }

        // 4. Framework: Cache
        if (!Schema::hasTable('cache')) {
            Schema::create('cache', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->mediumText('value');
                $table->integer('expiration');
            });
        }

        if (!Schema::hasTable('cache_locks')) {
            Schema::create('cache_locks', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->string('owner');
                $table->integer('expiration');
            });
        }

        // 5. Framework: Password Resets
        if (!Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function (Blueprint $table) {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }

        // 6. System Metadata: Nama Report
        if (!Schema::hasTable('nama_report')) {
            Schema::create('nama_report', function (Blueprint $table) {
                $table->id('id_report');
                $table->string('nama_report');
                $table->string('table_name');
                $table->boolean('active')->default(true);
                $table->string('import_controller', 150)->nullable();
                $table->boolean('requires_manual_periode')->default(false);
                $table->string('manual_periode_type', 20)->nullable();
                $table->string('manual_periode_label', 100)->nullable();
                $table->string('manual_periode_help', 255)->nullable();
                $table->timestamps();
            });
        }

        // 7. System Metadata: Import Jobs
        if (!Schema::hasTable('import_jobs')) {
            Schema::create('import_jobs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('id_report');
                $table->string('file_name');
                $table->text('folder_path');
                $table->string('status')->default('uploaded');
                $table->integer('total_files')->nullable();
                $table->unsignedInteger('total_success')->default(0);
                $table->unsignedInteger('total_failed')->default(0);
                $table->unsignedBigInteger('created_by');
                $table->text('message')->nullable();
                $table->longText('job_context')->nullable();
                $table->string('job_fingerprint', 64)->nullable()->unique();
                $table->timestamps();

                $table->index(['status', 'updated_at'], 'idx_import_jobs_status_upd');
                $table->index(['id_report', 'created_at'], 'idx_import_jobs_report_create');
            });
        }

        // 8. System Metadata: Audit Sync
        if (!Schema::hasTable('report_sync_audits')) {
            Schema::create('report_sync_audits', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('import_job_id')->nullable();
                $table->string('source', 150)->nullable();
                $table->string('table_name', 120)->index();
                $table->date('period_hint')->nullable()->index();
                $table->string('action', 80)->index();
                $table->string('status', 30)->index();
                $table->unsignedInteger('duration_ms')->nullable();
                $table->unsignedInteger('affected_rows')->nullable();
                $table->text('message')->nullable();
                $table->longText('context')->nullable();
                $table->timestamps();
            });
        }

        $this->seedNamaReport();
        $this->seedAdminUser();
    }

    public function down(): void
    {
        Schema::dropIfExists('report_sync_audits');
        Schema::dropIfExists('import_jobs');
        Schema::dropIfExists('nama_report');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');
    }

    private function seedNamaReport(): void
    {
        $seeds = [
            ['id_report' => 1, 'nama_report' => 'Jumlah Merchant Detail', 'table_name' => 'jumlah_merchant_detail', 'active' => 1, 'import_controller' => 'ImportFileController', 'requires_manual_periode' => 0],
            ['id_report' => 2, 'nama_report' => 'SV Merchant', 'table_name' => 'sv_merchant', 'active' => 1, 'import_controller' => 'ImportFileController', 'requires_manual_periode' => 0],
            ['id_report' => 3, 'nama_report' => 'Merchant_QRIS', 'table_name' => 'merchant_qris', 'active' => 1, 'import_controller' => 'ImportFileController', 'requires_manual_periode' => 0],
            ['id_report' => 4, 'nama_report' => 'Merchant QRIS Volume', 'table_name' => 'merchant_qris_volume', 'active' => 1, 'import_controller' => 'ImportFileController', 'requires_manual_periode' => 0],
            ['id_report' => 5, 'nama_report' => 'User Brimo RPT v2', 'table_name' => 'user_brimo_rpt_v2', 'active' => 1, 'import_controller' => 'ImportFileBrimoController', 'requires_manual_periode' => 0],
            ['id_report' => 6, 'nama_report' => 'User Brimo Fin', 'table_name' => 'user_brimo_fin', 'active' => 1, 'import_controller' => 'ImportFileBrimoController', 'requires_manual_periode' => 0],
            ['id_report' => 7, 'nama_report' => 'BRILINK Web - Laporan Summary Transaksi', 'table_name' => 'brilink_web_laporan_summary_transaksi_brilink_web', 'active' => 1, 'import_controller' => 'ImportFileController', 'requires_manual_periode' => 0],
            ['id_report' => 8, 'nama_report' => 'Daily Loan Dinamis ', 'table_name' => 'daily_loan_dinamis', 'active' => 1, 'import_controller' => 'ImportExcelController', 'requires_manual_periode' => 0],
            ['id_report' => 9, 'nama_report' => 'Simpanan MultiPN', 'table_name' => 'simpanan_multipn', 'active' => 1, 'import_controller' => 'ImportExcelController|ImportSimpananMultiPnCsvController', 'requires_manual_periode' => 0],
            ['id_report' => 10, 'nama_report' => 'Input Rekanan', 'table_name' => 'input_rekanan', 'active' => 1, 'import_controller' => 'InputRekananController', 'requires_manual_periode' => 1, 'manual_periode_type' => 'date', 'manual_periode_label' => 'Tanggal Periode', 'manual_periode_help' => 'Input Rekanan wajib diisi tanggal periode manual (YYYY-MM-DD).'],
            ['id_report' => 11, 'nama_report' => 'Nasabah Prioritas BOD BOC', 'table_name' => 'bod_boc', 'active' => 1, 'import_controller' => 'BodBocController', 'requires_manual_periode' => 1, 'manual_periode_type' => 'date', 'manual_periode_label' => 'Tanggal Periode', 'manual_periode_help' => 'Nasabah Prioritas BOD/BOC wajib diisi tanggal periode manual (YYYY-MM-DD).'],
            ['id_report' => 12, 'nama_report' => 'CASA BRILINK WEB', 'table_name' => 'casa_brilink_web', 'active' => 1, 'import_controller' => 'ImportCasaBrilinkController', 'requires_manual_periode' => 1, 'manual_periode_type' => 'month', 'manual_periode_label' => 'Periode Bulan', 'manual_periode_help' => 'Wajib isi periode manual dalam format bulan (YYYY-MM) untuk CASA BRILINK WEB.'],
            ['id_report' => 13, 'nama_report' => 'CASA BRILINK EDC', 'table_name' => 'casa_brilink_edc', 'active' => 1, 'import_controller' => 'ImportCasaBrilinkController', 'requires_manual_periode' => 1, 'manual_periode_type' => 'month', 'manual_periode_label' => 'Periode Bulan', 'manual_periode_help' => 'Wajib isi periode manual dalam format bulan (YYYY-MM) untuk CASA BRILINK EDC.'],
            ['id_report' => 14, 'nama_report' => 'Performance PIS per Produk', 'table_name' => 'performance_pis_per_produk', 'active' => 1, 'import_controller' => 'ImportPerformancePisPerProdukController', 'requires_manual_periode' => 1, 'manual_periode_type' => 'date', 'manual_periode_label' => 'Tanggal Periode', 'manual_periode_help' => 'Wajib isi tanggal periode manual (YYYY-MM-DD) untuk Performance PIS per Produk.'],
            ['id_report' => 15, 'nama_report' => 'Report Nominatif Rekening Pinjaman PH', 'table_name' => 'lw325_ph', 'active' => 1, 'import_controller' => 'ImportReportPhController', 'requires_manual_periode' => 0],
            ['id_report' => 16, 'nama_report' => 'Rencana Kerja Anggaran (RKA)', 'table_name' => 'rka', 'active' => 1, 'import_controller' => 'ImportRkaController', 'requires_manual_periode' => 1, 'manual_periode_type' => 'year', 'manual_periode_label' => 'Tahun RKA', 'manual_periode_help' => 'Pilih tahun untuk target RKA.'],
            ['id_report' => 17, 'nama_report' => 'SSA Simpanan (Export)', 'table_name' => 'ssa_simpanan', 'active' => 1, 'import_controller' => 'ImportSsaSimpananController', 'requires_manual_periode' => 0],
            ['id_report' => 18, 'nama_report' => 'SSA Pinjaman (Export)', 'table_name' => 'ssa_pinjaman', 'active' => 1, 'import_controller' => 'ImportSsaPinjamanController', 'requires_manual_periode' => 0],
            ['id_report' => 19, 'nama_report' => 'GI405 Single Row', 'table_name' => 'gi405_singlerow', 'active' => 1, 'import_controller' => 'Gi405RecDhImportExcelController', 'requires_manual_periode' => 0],
            ['id_report' => 20, 'nama_report' => 'Cognos Recovery', 'table_name' => 'cognos_recovery', 'active' => 1, 'import_controller' => 'ImportCognosController', 'requires_manual_periode' => 0],
            ['id_report' => 21, 'nama_report' => 'Cognos PH', 'table_name' => 'cognos_ph', 'active' => 1, 'import_controller' => 'ImportCognosController', 'requires_manual_periode' => 0],
        ];

        $now = now();
        foreach ($seeds as $row) {
            DB::table('nama_report')->updateOrInsert(
                ['id_report' => $row['id_report']],
                array_merge($row, ['created_at' => $now, 'updated_at' => $now])
            );
        }
    }
    private function seedAdminUser(): void
    {
        DB::table('users')->updateOrInsert(
            ['pn' => '90179583'],
            [
                'id' => 1,
                'name' => 'admin',
                'password' => '$2y$12$Q.YPC/lCsSrn6vvObGIhvuUi2Zs5SHT4MtPtwkxzT3iB5cipWCg52',
                'remember_token' => null,
                'created_at' => null,
                'updated_at' => null,
                'role' => 'admin',
            ]
        );
    }
};
