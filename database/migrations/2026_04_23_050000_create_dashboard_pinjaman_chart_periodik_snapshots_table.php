<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'dashboard_pinjaman_chart_periodik_snapshots';

    public function up(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            Schema::create(self::TABLE, function (Blueprint $table): void {
                $table->string('uniqueid_dpcs', 255)->primary();
                $table->date('periode')->index();
                $table->string('source_uniqueid_namareport', 255)->nullable();
                $table->string('account_number', 50)->nullable();
                $table->decimal('baki_debet1', 20, 2)->default(0);
                $table->string('ln_type', 100)->nullable();
                $table->string('loan_type', 100)->nullable();
                $table->string('pola_pembayaran', 150)->nullable();
                $table->string('segmen_dashboard', 100)->nullable();
                $table->string('produk_dashboard', 150)->nullable();
                $table->string('cabang1', 150)->nullable();
                $table->string('unit1', 150)->nullable();
                $table->string('branch1', 180)->nullable();
                $table->timestamps();

                $table->index(['periode', 'cabang1', 'branch1', 'unit1'], 'idx_dpcp_period_cabang_branch_unit');
            });
        }

        $this->replaceDailyLoanTrigger();
    }

    public function down(): void
    {
        $this->replaceDailyLoanTrigger(false);

        Schema::dropIfExists(self::TABLE);
    }

    private function replaceDailyLoanTrigger(bool $includeChartSnapshotCleanup = true): void
    {
        $this->dropTriggerIfExists('trg_daily_loan_after_insert');

        $chartCleanup = $includeChartSnapshotCleanup
            ? "DELETE FROM dashboard_pinjaman_chart_periodik_snapshots WHERE periode = NEW.periode;\n"
            : '';

        DB::unprepared("
            CREATE TRIGGER trg_daily_loan_after_insert AFTER INSERT ON daily_loan_dinamis
            FOR EACH ROW BEGIN
                DELETE FROM dashboard_pinjaman_snapshots WHERE periode = NEW.periode;
                {$chartCleanup}DELETE FROM dashboard_harian_snapshots WHERE snapshot_period = NEW.periode;
                DELETE FROM rasio_casa_debitur_snapshots WHERE loan_period = NEW.periode;
            END;
        ");
    }

    private function dropTriggerIfExists(string $triggerName): void
    {
        $result = DB::selectOne(
            'SELECT COUNT(*) AS aggregate FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME = ?',
            [$triggerName]
        );

        if ((int) ($result->aggregate ?? 0) > 0) {
            DB::unprepared("DROP TRIGGER IF EXISTS `{$triggerName}`");
        }
    }
};
