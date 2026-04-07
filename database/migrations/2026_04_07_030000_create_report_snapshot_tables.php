<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_pinjaman_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('periode')->index();
            $table->string('account_number', 50)->index();
            $table->decimal('loan_balance', 20, 2)->default(0);
            $table->string('quality_bucket', 20)->nullable()->index();
            $table->string('segmen_dashboard', 100)->nullable();
            $table->string('produk_dashboard', 150)->nullable();
            $table->string('cabang1', 150)->nullable();
            $table->string('unit1', 180)->nullable();
            $table->timestamps();

            $table->index(
                ['periode', 'segmen_dashboard', 'produk_dashboard', 'cabang1', 'unit1'],
                'idx_dps_period_filter_chain'
            );
            $table->index(['periode', 'account_number'], 'idx_dps_period_account');
            $table->index(['periode', 'cabang1', 'unit1'], 'idx_dps_period_branch_unit');
        });

        Schema::create('rasio_casa_debitur_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('loan_period')->index();
            $table->date('casa_period')->nullable()->index();
            $table->string('branch_key', 50)->index();
            $table->string('branch_label', 100)->nullable();
            $table->string('segment_key', 30)->index();
            $table->decimal('os_amount', 20, 2)->default(0);
            $table->decimal('casa_amount', 20, 2)->default(0);
            $table->unsignedInteger('source_row_count')->default(0);
            $table->timestamps();

            $table->unique(['loan_period', 'branch_key', 'segment_key'], 'uq_rcds_period_branch_segment');
        });

        Schema::create('rekening_dormant_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('posisi')->index();
            $table->string('branch_label', 100)->index();
            $table->string('raw_branch', 180)->index();
            $table->string('unit_kerja', 180)->default('');
            $table->unsignedInteger('dormant_count')->default(0);
            $table->timestamps();

            $table->unique(['posisi', 'raw_branch', 'unit_kerja'], 'uq_rds_posisi_branch_unit');
            $table->index(['posisi', 'branch_label', 'unit_kerja'], 'idx_rds_posisi_label_unit');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rekening_dormant_snapshots');
        Schema::dropIfExists('rasio_casa_debitur_snapshots');
        Schema::dropIfExists('dashboard_pinjaman_snapshots');
    }
};
