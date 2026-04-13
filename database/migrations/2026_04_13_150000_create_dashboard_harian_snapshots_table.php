<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('dashboard_harian_snapshots')) {
            Schema::create('dashboard_harian_snapshots', function (Blueprint $table) {
                $table->string('uniqueid_dhs', 191)->primary();
                $table->date('snapshot_period');
                $table->string('kanca_key', 100);
                $table->string('kanca_label', 150);
                $table->string('unit_key', 150);
                $table->string('unit_label', 180);
                $table->decimal('total_simpanan', 20, 2)->default(0);
                $table->decimal('simpanan_ritel', 20, 2)->default(0);
                $table->decimal('giro_ritel', 20, 2)->default(0);
                $table->decimal('deposito_ritel', 20, 2)->default(0);
                $table->decimal('tabungan_ritel', 20, 2)->default(0);
                $table->decimal('simpanan_mikro', 20, 2)->default(0);
                $table->decimal('giro_mikro', 20, 2)->default(0);
                $table->decimal('deposito_mikro', 20, 2)->default(0);
                $table->decimal('tabungan_mikro', 20, 2)->default(0);
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
                $table->decimal('micro_cashcoll_os', 20, 2)->default(0);
                $table->decimal('total_sml_abs_non_commercial', 20, 2)->default(0);
                $table->decimal('total_npl_abs_non_commercial', 20, 2)->default(0);
                $table->unsignedInteger('source_row_count')->default(0);
                $table->timestamps();

                $table->unique(['snapshot_period', 'kanca_key', 'unit_key'], 'uq_dhs_period_kanca_unit');
                $table->index(['snapshot_period', 'kanca_key'], 'idx_dhs_period_kanca');
                $table->index(['snapshot_period', 'unit_key'], 'idx_dhs_period_unit');
            });
        }

        $this->recreateDailyLoanTriggers(withDashboardHarian: true);
        $this->recreateSimpananTriggers(withDashboardHarian: true);
    }

    public function down(): void
    {
        $this->recreateDailyLoanTriggers(withDashboardHarian: false);
        $this->recreateSimpananTriggers(withDashboardHarian: false);

        Schema::dropIfExists('dashboard_harian_snapshots');
    }

    private function recreateDailyLoanTriggers(bool $withDashboardHarian): void
    {
        foreach ([
            'trg_dld_after_ins_invalidate_snapshots',
            'trg_dld_after_upd_invalidate_snapshots',
            'trg_dld_after_del_invalidate_snapshots',
        ] as $trigger) {
            DB::unprepared('DROP TRIGGER IF EXISTS ' . $trigger);
        }

        $dashboardHarianDelete = $withDashboardHarian
            ? "        DELETE FROM dashboard_harian_snapshots WHERE snapshot_period = NEW.periode;\n"
            : '';
        $dashboardHarianDeleteOld = $withDashboardHarian
            ? "        DELETE FROM dashboard_harian_snapshots WHERE snapshot_period = OLD.periode;\n"
            : '';

        DB::unprepared(<<<SQL
CREATE TRIGGER trg_dld_after_ins_invalidate_snapshots
AFTER INSERT ON daily_loan_dinamis
FOR EACH ROW
BEGIN
    IF COALESCE(@skip_snapshot_invalidation, 0) = 0 AND NEW.periode IS NOT NULL THEN
        DELETE FROM dashboard_pinjaman_snapshots WHERE periode = NEW.periode;
{$dashboardHarianDelete}        DELETE FROM rasio_casa_debitur_snapshots WHERE loan_period = NEW.periode;
    END IF;
END
SQL);

        DB::unprepared(<<<SQL
CREATE TRIGGER trg_dld_after_upd_invalidate_snapshots
AFTER UPDATE ON daily_loan_dinamis
FOR EACH ROW
BEGIN
    IF COALESCE(@skip_snapshot_invalidation, 0) = 0 AND NEW.periode IS NOT NULL THEN
        DELETE FROM dashboard_pinjaman_snapshots WHERE periode = NEW.periode;
{$dashboardHarianDelete}        DELETE FROM rasio_casa_debitur_snapshots WHERE loan_period = NEW.periode;
    END IF;

    IF COALESCE(@skip_snapshot_invalidation, 0) = 0
       AND OLD.periode IS NOT NULL
       AND (NEW.periode IS NULL OR OLD.periode <> NEW.periode) THEN
        DELETE FROM dashboard_pinjaman_snapshots WHERE periode = OLD.periode;
{$dashboardHarianDeleteOld}        DELETE FROM rasio_casa_debitur_snapshots WHERE loan_period = OLD.periode;
    END IF;
END
SQL);

        DB::unprepared(<<<SQL
CREATE TRIGGER trg_dld_after_del_invalidate_snapshots
AFTER DELETE ON daily_loan_dinamis
FOR EACH ROW
BEGIN
    IF COALESCE(@skip_snapshot_invalidation, 0) = 0 AND OLD.periode IS NOT NULL THEN
        DELETE FROM dashboard_pinjaman_snapshots WHERE periode = OLD.periode;
{$dashboardHarianDeleteOld}        DELETE FROM rasio_casa_debitur_snapshots WHERE loan_period = OLD.periode;
    END IF;
END
SQL);
    }

    private function recreateSimpananTriggers(bool $withDashboardHarian): void
    {
        foreach ([
            'trg_smp_after_ins_invalidate_snapshots',
            'trg_smp_after_upd_invalidate_snapshots',
            'trg_smp_after_del_invalidate_snapshots',
        ] as $trigger) {
            DB::unprepared('DROP TRIGGER IF EXISTS ' . $trigger);
        }

        $dashboardHarianDelete = $withDashboardHarian
            ? "        DELETE FROM dashboard_harian_snapshots WHERE snapshot_period = NEW.posisi;\n"
            : '';
        $dashboardHarianDeleteOld = $withDashboardHarian
            ? "        DELETE FROM dashboard_harian_snapshots WHERE snapshot_period = OLD.posisi;\n"
            : '';

        DB::unprepared(<<<SQL
CREATE TRIGGER trg_smp_after_ins_invalidate_snapshots
AFTER INSERT ON simpanan_multipn
FOR EACH ROW
BEGIN
    IF COALESCE(@skip_snapshot_invalidation, 0) = 0 AND NEW.posisi IS NOT NULL THEN
        DELETE FROM dashboard_simpanan_snapshots WHERE snapshot_period = NEW.posisi;
        DELETE FROM dashboard_simpanan_branch_snapshots WHERE snapshot_period = NEW.posisi;
{$dashboardHarianDelete}        DELETE FROM rekening_dormant_snapshots WHERE posisi = NEW.posisi;
        DELETE FROM rasio_casa_debitur_snapshots WHERE casa_period = NEW.posisi;
    END IF;
END
SQL);

        DB::unprepared(<<<SQL
CREATE TRIGGER trg_smp_after_upd_invalidate_snapshots
AFTER UPDATE ON simpanan_multipn
FOR EACH ROW
BEGIN
    IF COALESCE(@skip_snapshot_invalidation, 0) = 0 AND NEW.posisi IS NOT NULL THEN
        DELETE FROM dashboard_simpanan_snapshots WHERE snapshot_period = NEW.posisi;
        DELETE FROM dashboard_simpanan_branch_snapshots WHERE snapshot_period = NEW.posisi;
{$dashboardHarianDelete}        DELETE FROM rekening_dormant_snapshots WHERE posisi = NEW.posisi;
        DELETE FROM rasio_casa_debitur_snapshots WHERE casa_period = NEW.posisi;
    END IF;

    IF COALESCE(@skip_snapshot_invalidation, 0) = 0
       AND OLD.posisi IS NOT NULL
       AND (NEW.posisi IS NULL OR OLD.posisi <> NEW.posisi) THEN
        DELETE FROM dashboard_simpanan_snapshots WHERE snapshot_period = OLD.posisi;
        DELETE FROM dashboard_simpanan_branch_snapshots WHERE snapshot_period = OLD.posisi;
{$dashboardHarianDeleteOld}        DELETE FROM rekening_dormant_snapshots WHERE posisi = OLD.posisi;
        DELETE FROM rasio_casa_debitur_snapshots WHERE casa_period = OLD.posisi;
    END IF;
END
SQL);

        DB::unprepared(<<<SQL
CREATE TRIGGER trg_smp_after_del_invalidate_snapshots
AFTER DELETE ON simpanan_multipn
FOR EACH ROW
BEGIN
    IF COALESCE(@skip_snapshot_invalidation, 0) = 0 AND OLD.posisi IS NOT NULL THEN
        DELETE FROM dashboard_simpanan_snapshots WHERE snapshot_period = OLD.posisi;
        DELETE FROM dashboard_simpanan_branch_snapshots WHERE snapshot_period = OLD.posisi;
{$dashboardHarianDeleteOld}        DELETE FROM rekening_dormant_snapshots WHERE posisi = OLD.posisi;
        DELETE FROM rasio_casa_debitur_snapshots WHERE casa_period = OLD.posisi;
    END IF;
END
SQL);
    }
};
