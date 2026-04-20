<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Dashboard Harian Snapshots (Main Dashboard)
        if (!Schema::hasTable('dashboard_harian_snapshots')) {
            Schema::create('dashboard_harian_snapshots', function (Blueprint $table) {
            $table->string('uniqueid_dhs', 255)->primary();
            $table->date('snapshot_period')->index();
            $table->string('branch_label', 150)->nullable();
            $table->string('uker_label', 150)->nullable();
            $table->string('scope', 20)->default('total'); // total, branch, unit
            
            // Metric Columns from DashboardHarianSnapshotService
            $table->decimal('ph_tupok', 20, 2)->default(0);
            $table->decimal('ph_lunas', 20, 2)->default(0);
            $table->decimal('rec_dh_total', 20, 2)->default(0);
            $table->decimal('rec_dh_small', 20, 2)->default(0);
            $table->decimal('rec_dh_consumer', 20, 2)->default(0);
            $table->decimal('rec_dh_micro', 20, 2)->default(0);
            $table->decimal('total_simpanan', 20, 2)->default(0);
            $table->decimal('simpanan_ritel', 20, 2)->default(0);
            $table->decimal('giro_ritel', 20, 2)->default(0);
            $table->decimal('deposito_ritel', 20, 2)->default(0);
            $table->decimal('tabungan_ritel', 20, 2)->default(0);
            $table->decimal('simpanan_mikro', 20, 2)->default(0);
            $table->decimal('giro_mikro', 20, 2)->default(0);
            $table->decimal('deposito_mikro', 20, 2)->default(0);
            $table->decimal('tabungan_mikro', 20, 2)->default(0);
            $table->decimal('simpanan_wholesale', 20, 2)->default(0);
            $table->decimal('giro_wholesale', 20, 2)->default(0);
            $table->decimal('deposito_wholesale', 20, 2)->default(0);
            $table->decimal('tabungan_wholesale', 20, 2)->default(0);
            $table->decimal('total_casa', 20, 2)->default(0);
            $table->decimal('casa_ritel', 20, 2)->default(0);
            $table->decimal('casa_mikro', 20, 2)->default(0);
            $table->decimal('total_os', 20, 2)->default(0);
            $table->decimal('total_os_non_commercial', 20, 2)->default(0);
            $table->decimal('commercial_os', 20, 2)->default(0);
            $table->decimal('sme_os', 20, 2)->default(0);
            $table->decimal('kecil_os', 20, 2)->default(0);
            $table->decimal('kecil_non_cashcoll_os', 20, 2)->default(0);
            $table->decimal('cashcoll_os', 20, 2)->default(0);
            $table->decimal('medium_os', 20, 2)->default(0);
            $table->decimal('consumer_os', 20, 2)->default(0);
            $table->decimal('briguna_konsumer_os', 20, 2)->default(0);
            $table->decimal('kpr_os', 20, 2)->default(0);
            $table->decimal('kkb_os', 20, 2)->default(0);
            $table->decimal('micro_os', 20, 2)->default(0);
            $table->decimal('briguna_mikro_os', 20, 2)->default(0);
            $table->decimal('kupedes_os', 20, 2)->default(0);
            $table->decimal('kur_mikro_os', 20, 2)->default(0);
            $table->decimal('kur_kecil_os', 20, 2)->default(0);
            $table->decimal('kur_kpp_os', 20, 2)->default(0);
            $table->decimal('total_sml_abs_non_commercial', 20, 2)->default(0);
            $table->decimal('commercial_sml', 20, 2)->default(0);
            $table->decimal('sme_sml', 20, 2)->default(0);
            $table->decimal('kecil_sml', 20, 2)->default(0);
            $table->decimal('kecil_non_cashcoll_sml', 20, 2)->default(0);
            $table->decimal('cashcoll_sml', 20, 2)->default(0);
            $table->decimal('medium_sml', 20, 2)->default(0);
            $table->decimal('consumer_sml', 20, 2)->default(0);
            $table->decimal('briguna_konsumer_sml', 20, 2)->default(0);
            $table->decimal('kpr_sml', 20, 2)->default(0);
            $table->decimal('kkb_sml', 20, 2)->default(0);
            $table->decimal('micro_sml', 20, 2)->default(0);
            $table->decimal('briguna_mikro_sml', 20, 2)->default(0);
            $table->decimal('kupedes_sml', 20, 2)->default(0);
            $table->decimal('kur_mikro_sml', 20, 2)->default(0);
            $table->decimal('kur_kecil_sml', 20, 2)->default(0);
            $table->decimal('kur_kpp_sml', 20, 2)->default(0);
            $table->decimal('total_npl_abs_non_commercial', 20, 2)->default(0);
            $table->decimal('commercial_npl', 20, 2)->default(0);
            $table->decimal('sme_npl', 20, 2)->default(0);
            $table->decimal('kecil_npl', 20, 2)->default(0);
            $table->decimal('kecil_non_cashcoll_npl', 20, 2)->default(0);
            $table->decimal('cashcoll_npl', 20, 2)->default(0);
            $table->decimal('medium_npl', 20, 2)->default(0);
            $table->decimal('consumer_npl', 20, 2)->default(0);
            $table->decimal('briguna_konsumer_npl', 20, 2)->default(0);
            $table->decimal('kpr_npl', 20, 2)->default(0);
            $table->decimal('kkb_npl', 20, 2)->default(0);
            $table->decimal('micro_npl', 20, 2)->default(0);
            $table->decimal('briguna_mikro_npl', 20, 2)->default(0);
            $table->decimal('kupedes_npl', 20, 2)->default(0);
            $table->decimal('kur_mikro_npl', 20, 2)->default(0);
            $table->decimal('kur_kecil_npl', 20, 2)->default(0);
            $table->decimal('kur_kpp_npl', 20, 2)->default(0);
            
            $table->timestamps();
                $table->index(['snapshot_period', 'scope'], 'idx_dhs_period_scope');
            });
        }

        // 2. Dashboard Pinjaman Snapshots
        if (!Schema::hasTable('dashboard_pinjaman_snapshots')) {
            Schema::create('dashboard_pinjaman_snapshots', function (Blueprint $table) {
            $table->string('uniqueid_dps', 255)->primary();
            $table->date('periode')->index();
            $table->string('account_number', 50)->nullable();
            $table->decimal('loan_balance', 20, 2)->default(0);
            $table->string('quality_bucket', 20)->nullable();
            $table->string('segmen_dashboard', 100)->nullable();
            $table->string('produk_dashboard', 100)->nullable();
            $table->string('cabang1', 150)->nullable();
            $table->string('unit1', 150)->nullable();
            $table->timestamps();
                $table->index(['periode', 'cabang1', 'unit1'], 'idx_dps_period_cab_unit');
            });
        }

        // 3. Dashboard Simpanan Snapshots
        if (!Schema::hasTable('dashboard_simpanan_snapshots')) {
            Schema::create('dashboard_simpanan_snapshots', function (Blueprint $table) {
            $table->string('uniqueid_dss', 255)->primary();
            $table->date('snapshot_period')->index();
            $table->decimal('total_balance', 20, 2)->default(0);
            $table->integer('account_count')->default(0);
            $table->integer('cif_count')->default(0);
            $table->integer('branch_count')->default(0);
            $table->integer('unit_count')->default(0);
            $table->decimal('tabungan_balance', 20, 2)->default(0);
            $table->decimal('giro_balance', 20, 2)->default(0);
            $table->decimal('other_balance', 20, 2)->default(0);
            $table->string('top_branch_label', 150)->nullable();
            $table->decimal('top_branch_balance', 20, 2)->default(0);
            $table->integer('source_row_count')->default(0);
                $table->timestamp('source_updated_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('dashboard_simpanan_branch_snapshots')) {
            Schema::create('dashboard_simpanan_branch_snapshots', function (Blueprint $table) {
                $table->string('uniqueid_dsbs', 255)->primary();
                $table->date('snapshot_period')->index();
                $table->string('kantor_cabang', 150)->index();
                $table->decimal('total_balance', 20, 2)->default(0);
                $table->integer('rank_order')->default(0);
                $table->timestamps();
            });
        }

        // 4. Rasio CASA Snapshots
        if (!Schema::hasTable('rasio_casa_debitur_snapshots')) {
            Schema::create('rasio_casa_debitur_snapshots', function (Blueprint $table) {
            $table->string('uniqueid_rcds', 255)->primary();
            $table->date('loan_period')->index();
            $table->date('casa_period')->nullable();
            $table->string('branch_key', 100)->nullable();
            $table->string('branch_label', 150)->nullable();
            $table->string('segment_key', 50)->nullable();
            $table->decimal('os_amount', 20, 2)->default(0);
            $table->decimal('casa_amount', 20, 2)->default(0);
            $table->integer('source_row_count')->default(0);
            $table->timestamps();
                $table->unique(['loan_period', 'branch_key', 'segment_key'], 'uk_rcds_period_branch_segment');
            });
        }

        if (!Schema::hasTable('rasio_casa_debitur_uker_snapshots')) {
            Schema::create('rasio_casa_debitur_uker_snapshots', function (Blueprint $table) {
                $table->string('uniqueid_rcdus', 255)->primary();
                $table->date('loan_period')->index();
                $table->date('casa_period')->nullable();
                $table->string('source_branch_key', 100)->nullable();
                $table->string('uker_key', 100)->nullable();
                $table->string('uker_label', 150)->nullable();
                $table->string('segment_key', 50)->nullable();
                $table->decimal('os_amount', 20, 2)->default(0);
                $table->decimal('casa_amount', 20, 2)->default(0);
                $table->integer('source_row_count')->default(0);
                $table->timestamps();

                $table->unique(['loan_period', 'source_branch_key', 'uker_key', 'segment_key'], 'uk_rcdus_period_branch_uker_segment');
            });
        }

        // 5. Rekening Dormant Snapshots
        if (!Schema::hasTable('rekening_dormant_snapshots')) {
            Schema::create('rekening_dormant_snapshots', function (Blueprint $table) {
                $table->string('uniqueid_rds', 255)->primary();
                $table->date('posisi')->index();
                $table->string('branch_label', 150)->nullable();
                $table->string('raw_branch', 150)->nullable();
                $table->string('unit_kerja', 150)->nullable();
                $table->integer('dormant_count')->default(0);
                $table->timestamps();
            });
        }

        // 6. Performance New Payroll Snapshots
        if (!Schema::hasTable('performance_new_payroll_snapshots')) {
            Schema::create('performance_new_payroll_snapshots', function (Blueprint $table) {
                $table->string('uniqueid_pnps', 255)->primary();
                $table->date('snapshot_posisi')->index();
                $table->string('branch', 150)->index();
                $table->integer('rekening_curr')->default(0);
                $table->integer('rekening_prev')->default(0);
                $table->integer('rekening_yoy_prev')->default(0);
                $table->decimal('saldo_curr', 20, 2)->default(0);
                $table->decimal('saldo_prev', 20, 2)->default(0);
                $table->decimal('saldo_yoy_prev', 20, 2)->default(0);
                $table->timestamps();
            });
        }

        // 7. TRIGGERS Logic (Using raw SQL for performance and precision)
        // Invalidates snapshots when source tables are updated via imports
        
        // Simpanan MultiPn Triggers
        DB::unprepared("DROP TRIGGER IF EXISTS trg_simpanan_after_insert");
        DB::unprepared("
            CREATE TRIGGER trg_simpanan_after_insert AFTER INSERT ON simpanan_multipn
            FOR EACH ROW BEGIN
                DELETE FROM dashboard_simpanan_snapshots WHERE snapshot_period = NEW.posisi;
                DELETE FROM dashboard_simpanan_branch_snapshots WHERE snapshot_period = NEW.posisi;
                DELETE FROM dashboard_harian_snapshots WHERE snapshot_period = NEW.posisi;
                DELETE FROM rasio_casa_debitur_snapshots WHERE casa_period = NEW.posisi;
                DELETE FROM rekening_dormant_snapshots WHERE posisi = NEW.posisi;
            END;
        ");

        // Daily Loan Dinamis Triggers
        DB::unprepared("DROP TRIGGER IF EXISTS trg_daily_loan_after_insert");
        DB::unprepared("
            CREATE TRIGGER trg_daily_loan_after_insert AFTER INSERT ON daily_loan_dinamis
            FOR EACH ROW BEGIN
                DELETE FROM dashboard_pinjaman_snapshots WHERE periode = NEW.periode;
                DELETE FROM dashboard_harian_snapshots WHERE snapshot_period = NEW.periode;
                DELETE FROM rasio_casa_debitur_snapshots WHERE loan_period = NEW.periode;
            END;
        ");

        // SSA reports - affects daily dashboard
        DB::unprepared("DROP TRIGGER IF EXISTS trg_ssa_simpanan_after_insert");
        DB::unprepared("
            CREATE TRIGGER trg_ssa_simpanan_after_insert AFTER INSERT ON ssa_simpanan
            FOR EACH ROW BEGIN
                DELETE FROM dashboard_harian_snapshots WHERE snapshot_period = NEW.Month_Day_Year_of_Posisi;
            END;
        ");

        DB::unprepared("DROP TRIGGER IF EXISTS trg_ssa_pinjaman_after_insert");
        DB::unprepared("
            CREATE TRIGGER trg_ssa_pinjaman_after_insert AFTER INSERT ON ssa_pinjaman
            FOR EACH ROW BEGIN
                DELETE FROM dashboard_harian_snapshots WHERE snapshot_period = NEW.month_day_year_of_periode;
            END;
        ");

        DB::unprepared("DROP TRIGGER IF EXISTS trg_lw325_ph_after_insert");
        DB::unprepared("
            CREATE TRIGGER trg_lw325_ph_after_insert AFTER INSERT ON lw325_ph
            FOR EACH ROW BEGIN
                DELETE FROM dashboard_harian_snapshots WHERE snapshot_period = NEW.periode;
            END;
        ");

        // Merchant/EDC Detail Trigger
        DB::unprepared("DROP TRIGGER IF EXISTS trg_merchant_detail_after_insert");
        DB::unprepared("
            CREATE TRIGGER trg_merchant_detail_after_insert AFTER INSERT ON jumlah_merchant_detail
            FOR EACH ROW BEGIN
                DELETE FROM dashboard_harian_snapshots WHERE snapshot_period = NEW.posisi;
            END;
        ");

        // PIS Performance Trigger (affects payroll snapshots)
        DB::unprepared("DROP TRIGGER IF EXISTS trg_pis_after_insert");
        DB::unprepared("
            CREATE TRIGGER trg_pis_after_insert AFTER INSERT ON performance_pis_per_produk
            FOR EACH ROW BEGIN
                DELETE FROM performance_new_payroll_snapshots WHERE snapshot_posisi = NEW.posisi;
            END;
        ");
    }

    public function down(): void
    {
        DB::unprepared("DROP TRIGGER IF EXISTS trg_pis_after_insert");
        DB::unprepared("DROP TRIGGER IF EXISTS trg_merchant_detail_after_insert");
        DB::unprepared("DROP TRIGGER IF EXISTS trg_lw325_ph_after_insert");
        DB::unprepared("DROP TRIGGER IF EXISTS trg_ssa_pinjaman_after_insert");
        DB::unprepared("DROP TRIGGER IF EXISTS trg_ssa_simpanan_after_insert");
        DB::unprepared("DROP TRIGGER IF EXISTS trg_daily_loan_after_insert");
        DB::unprepared("DROP TRIGGER IF EXISTS trg_simpanan_after_insert");

        Schema::dropIfExists('performance_new_payroll_snapshots');
        Schema::dropIfExists('rekening_dormant_snapshots');
        Schema::dropIfExists('rasio_casa_debitur_uker_snapshots');
        Schema::dropIfExists('rasio_casa_debitur_snapshots');
        Schema::dropIfExists('dashboard_simpanan_branch_snapshots');
        Schema::dropIfExists('dashboard_simpanan_snapshots');
        Schema::dropIfExists('dashboard_pinjaman_snapshots');
        Schema::dropIfExists('dashboard_harian_snapshots');
    }
};
