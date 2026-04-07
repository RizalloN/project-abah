<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        $this->dropAllSnapshotInvalidationTriggers();

        DB::unprepared('
            CREATE TRIGGER trg_dld_after_ins_invalidate_snapshots
            AFTER INSERT ON daily_loan_dinamis
            FOR EACH ROW
            BEGIN
                IF NEW.periode IS NOT NULL THEN
                    DELETE FROM dashboard_pinjaman_snapshots WHERE periode = NEW.periode;
                    DELETE FROM rasio_casa_debitur_snapshots WHERE loan_period = NEW.periode;
                END IF;
            END
        ');

        DB::unprepared('
            CREATE TRIGGER trg_dld_after_upd_invalidate_snapshots
            AFTER UPDATE ON daily_loan_dinamis
            FOR EACH ROW
            BEGIN
                IF NEW.periode IS NOT NULL THEN
                    DELETE FROM dashboard_pinjaman_snapshots WHERE periode = NEW.periode;
                    DELETE FROM rasio_casa_debitur_snapshots WHERE loan_period = NEW.periode;
                END IF;

                IF OLD.periode IS NOT NULL AND (NEW.periode IS NULL OR OLD.periode <> NEW.periode) THEN
                    DELETE FROM dashboard_pinjaman_snapshots WHERE periode = OLD.periode;
                    DELETE FROM rasio_casa_debitur_snapshots WHERE loan_period = OLD.periode;
                END IF;
            END
        ');

        DB::unprepared('
            CREATE TRIGGER trg_dld_after_del_invalidate_snapshots
            AFTER DELETE ON daily_loan_dinamis
            FOR EACH ROW
            BEGIN
                IF OLD.periode IS NOT NULL THEN
                    DELETE FROM dashboard_pinjaman_snapshots WHERE periode = OLD.periode;
                    DELETE FROM rasio_casa_debitur_snapshots WHERE loan_period = OLD.periode;
                END IF;
            END
        ');

        DB::unprepared('
            CREATE TRIGGER trg_smp_after_ins_invalidate_snapshots
            AFTER INSERT ON simpanan_multipn
            FOR EACH ROW
            BEGIN
                IF NEW.posisi IS NOT NULL THEN
                    DELETE FROM rekening_dormant_snapshots WHERE posisi = NEW.posisi;
                    DELETE FROM rasio_casa_debitur_snapshots WHERE casa_period = NEW.posisi;
                END IF;
            END
        ');

        DB::unprepared('
            CREATE TRIGGER trg_smp_after_upd_invalidate_snapshots
            AFTER UPDATE ON simpanan_multipn
            FOR EACH ROW
            BEGIN
                IF NEW.posisi IS NOT NULL THEN
                    DELETE FROM rekening_dormant_snapshots WHERE posisi = NEW.posisi;
                    DELETE FROM rasio_casa_debitur_snapshots WHERE casa_period = NEW.posisi;
                END IF;

                IF OLD.posisi IS NOT NULL AND (NEW.posisi IS NULL OR OLD.posisi <> NEW.posisi) THEN
                    DELETE FROM rekening_dormant_snapshots WHERE posisi = OLD.posisi;
                    DELETE FROM rasio_casa_debitur_snapshots WHERE casa_period = OLD.posisi;
                END IF;
            END
        ');

        DB::unprepared('
            CREATE TRIGGER trg_smp_after_del_invalidate_snapshots
            AFTER DELETE ON simpanan_multipn
            FOR EACH ROW
            BEGIN
                IF OLD.posisi IS NOT NULL THEN
                    DELETE FROM rekening_dormant_snapshots WHERE posisi = OLD.posisi;
                    DELETE FROM rasio_casa_debitur_snapshots WHERE casa_period = OLD.posisi;
                END IF;
            END
        ');
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        $this->dropAllSnapshotInvalidationTriggers();

        DB::unprepared('
            CREATE TRIGGER trg_dld_after_delete_invalidate_snapshots
            AFTER DELETE ON daily_loan_dinamis
            FOR EACH ROW
            BEGIN
                IF OLD.periode IS NOT NULL THEN
                    DELETE FROM dashboard_pinjaman_snapshots WHERE periode = OLD.periode;
                    DELETE FROM rasio_casa_debitur_snapshots WHERE loan_period = OLD.periode;
                END IF;
            END
        ');

        DB::unprepared('
            CREATE TRIGGER trg_smp_after_delete_invalidate_snapshots
            AFTER DELETE ON simpanan_multipn
            FOR EACH ROW
            BEGIN
                IF OLD.posisi IS NOT NULL THEN
                    DELETE FROM rekening_dormant_snapshots WHERE posisi = OLD.posisi;
                    DELETE FROM rasio_casa_debitur_snapshots WHERE casa_period = OLD.posisi;
                END IF;
            END
        ');
    }

    private function dropAllSnapshotInvalidationTriggers(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_dld_after_delete_invalidate_snapshots');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_smp_after_delete_invalidate_snapshots');

        DB::unprepared('DROP TRIGGER IF EXISTS trg_dld_after_ins_invalidate_snapshots');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_dld_after_upd_invalidate_snapshots');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_dld_after_del_invalidate_snapshots');

        DB::unprepared('DROP TRIGGER IF EXISTS trg_smp_after_ins_invalidate_snapshots');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_smp_after_upd_invalidate_snapshots');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_smp_after_del_invalidate_snapshots');
    }
};
