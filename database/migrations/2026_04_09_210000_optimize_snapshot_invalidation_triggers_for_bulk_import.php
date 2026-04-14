<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        $this->dropTriggers();

        DB::unprepared(<<<'SQL'
CREATE TRIGGER trg_dld_after_ins_invalidate_snapshots
AFTER INSERT ON daily_loan_dinamis
FOR EACH ROW
BEGIN
    IF COALESCE(@skip_snapshot_invalidation, 0) = 0 AND NEW.periode IS NOT NULL THEN
        DELETE FROM dashboard_pinjaman_snapshots WHERE periode = NEW.periode;
        DELETE FROM rasio_casa_debitur_snapshots WHERE loan_period = NEW.periode;
    END IF;
END
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER trg_dld_after_upd_invalidate_snapshots
AFTER UPDATE ON daily_loan_dinamis
FOR EACH ROW
BEGIN
    IF COALESCE(@skip_snapshot_invalidation, 0) = 0 AND NEW.periode IS NOT NULL THEN
        DELETE FROM dashboard_pinjaman_snapshots WHERE periode = NEW.periode;
        DELETE FROM rasio_casa_debitur_snapshots WHERE loan_period = NEW.periode;
    END IF;

    IF COALESCE(@skip_snapshot_invalidation, 0) = 0
       AND OLD.periode IS NOT NULL
       AND (NEW.periode IS NULL OR OLD.periode <> NEW.periode) THEN
        DELETE FROM dashboard_pinjaman_snapshots WHERE periode = OLD.periode;
        DELETE FROM rasio_casa_debitur_snapshots WHERE loan_period = OLD.periode;
    END IF;
END
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER trg_dld_after_del_invalidate_snapshots
AFTER DELETE ON daily_loan_dinamis
FOR EACH ROW
BEGIN
    IF COALESCE(@skip_snapshot_invalidation, 0) = 0 AND OLD.periode IS NOT NULL THEN
        DELETE FROM dashboard_pinjaman_snapshots WHERE periode = OLD.periode;
        DELETE FROM rasio_casa_debitur_snapshots WHERE loan_period = OLD.periode;
    END IF;
END
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER trg_smp_after_ins_invalidate_snapshots
AFTER INSERT ON simpanan_multipn
FOR EACH ROW
BEGIN
    IF COALESCE(@skip_snapshot_invalidation, 0) = 0 AND NEW.posisi IS NOT NULL THEN
        DELETE FROM rekening_dormant_snapshots WHERE posisi = NEW.posisi;
        DELETE FROM rasio_casa_debitur_snapshots WHERE casa_period = NEW.posisi;
    END IF;
END
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER trg_smp_after_upd_invalidate_snapshots
AFTER UPDATE ON simpanan_multipn
FOR EACH ROW
BEGIN
    IF COALESCE(@skip_snapshot_invalidation, 0) = 0 AND NEW.posisi IS NOT NULL THEN
        DELETE FROM rekening_dormant_snapshots WHERE posisi = NEW.posisi;
        DELETE FROM rasio_casa_debitur_snapshots WHERE casa_period = NEW.posisi;
    END IF;

    IF COALESCE(@skip_snapshot_invalidation, 0) = 0
       AND OLD.posisi IS NOT NULL
       AND (NEW.posisi IS NULL OR OLD.posisi <> NEW.posisi) THEN
        DELETE FROM rekening_dormant_snapshots WHERE posisi = OLD.posisi;
        DELETE FROM rasio_casa_debitur_snapshots WHERE casa_period = OLD.posisi;
    END IF;
END
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER trg_smp_after_del_invalidate_snapshots
AFTER DELETE ON simpanan_multipn
FOR EACH ROW
BEGIN
    IF COALESCE(@skip_snapshot_invalidation, 0) = 0 AND OLD.posisi IS NOT NULL THEN
        DELETE FROM rekening_dormant_snapshots WHERE posisi = OLD.posisi;
        DELETE FROM rasio_casa_debitur_snapshots WHERE casa_period = OLD.posisi;
    END IF;
END
SQL);
    }

    public function down(): void
    {
        if (!in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        $this->dropTriggers();
    }

    private function dropTriggers(): void
    {
        foreach ([
            'trg_dld_after_ins_invalidate_snapshots',
            'trg_dld_after_upd_invalidate_snapshots',
            'trg_dld_after_del_invalidate_snapshots',
            'trg_smp_after_ins_invalidate_snapshots',
            'trg_smp_after_upd_invalidate_snapshots',
            'trg_smp_after_del_invalidate_snapshots',
        ] as $trigger) {
            DB::unprepared('DROP TRIGGER IF EXISTS ' . $trigger);
        }
    }
};
