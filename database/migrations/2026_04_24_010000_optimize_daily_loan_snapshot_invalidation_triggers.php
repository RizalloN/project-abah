<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_daily_loan_after_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_daily_loan_after_update');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_daily_loan_after_delete');

        DB::unprepared("
            CREATE TRIGGER trg_daily_loan_after_insert
            AFTER INSERT ON daily_loan_dinamis
            FOR EACH ROW
            BEGIN
                IF COALESCE(@skip_snapshot_invalidation, 0) = 0 AND NEW.periode IS NOT NULL THEN
                    SET @dld_snapshot_period_keys = COALESCE(@dld_snapshot_period_keys, '');
                    SET @dld_snapshot_period_key = DATE_FORMAT(NEW.periode, '%Y-%m-%d');

                    IF FIND_IN_SET(@dld_snapshot_period_key, @dld_snapshot_period_keys) = 0 THEN
                        DELETE FROM dashboard_pinjaman_snapshots WHERE periode = NEW.periode;
                        DELETE FROM dashboard_pinjaman_chart_periodik_snapshots WHERE periode = NEW.periode;
                        DELETE FROM dashboard_harian_snapshots WHERE snapshot_period = NEW.periode;
                        DELETE FROM rasio_casa_debitur_snapshots WHERE loan_period = NEW.periode;
                        SET @dld_snapshot_period_keys = CONCAT_WS(',', @dld_snapshot_period_keys, @dld_snapshot_period_key);
                    END IF;
                END IF;
            END
        ");

        DB::unprepared("
            CREATE TRIGGER trg_daily_loan_after_update
            AFTER UPDATE ON daily_loan_dinamis
            FOR EACH ROW
            BEGIN
                IF COALESCE(@skip_snapshot_invalidation, 0) = 0 THEN
                    IF NEW.periode IS NOT NULL THEN
                        SET @dld_snapshot_period_keys = COALESCE(@dld_snapshot_period_keys, '');
                        SET @dld_snapshot_period_key = DATE_FORMAT(NEW.periode, '%Y-%m-%d');

                        IF FIND_IN_SET(@dld_snapshot_period_key, @dld_snapshot_period_keys) = 0 THEN
                            DELETE FROM dashboard_pinjaman_snapshots WHERE periode = NEW.periode;
                            DELETE FROM dashboard_pinjaman_chart_periodik_snapshots WHERE periode = NEW.periode;
                            DELETE FROM dashboard_harian_snapshots WHERE snapshot_period = NEW.periode;
                            DELETE FROM rasio_casa_debitur_snapshots WHERE loan_period = NEW.periode;
                            SET @dld_snapshot_period_keys = CONCAT_WS(',', @dld_snapshot_period_keys, @dld_snapshot_period_key);
                        END IF;
                    END IF;

                    IF OLD.periode IS NOT NULL AND (NEW.periode IS NULL OR OLD.periode <> NEW.periode) THEN
                        SET @dld_snapshot_period_keys = COALESCE(@dld_snapshot_period_keys, '');
                        SET @dld_snapshot_old_period_key = DATE_FORMAT(OLD.periode, '%Y-%m-%d');

                        IF FIND_IN_SET(@dld_snapshot_old_period_key, @dld_snapshot_period_keys) = 0 THEN
                            DELETE FROM dashboard_pinjaman_snapshots WHERE periode = OLD.periode;
                            DELETE FROM dashboard_pinjaman_chart_periodik_snapshots WHERE periode = OLD.periode;
                            DELETE FROM dashboard_harian_snapshots WHERE snapshot_period = OLD.periode;
                            DELETE FROM rasio_casa_debitur_snapshots WHERE loan_period = OLD.periode;
                            SET @dld_snapshot_period_keys = CONCAT_WS(',', @dld_snapshot_period_keys, @dld_snapshot_old_period_key);
                        END IF;
                    END IF;
                END IF;
            END
        ");

        DB::unprepared("
            CREATE TRIGGER trg_daily_loan_after_delete
            AFTER DELETE ON daily_loan_dinamis
            FOR EACH ROW
            BEGIN
                IF COALESCE(@skip_snapshot_invalidation, 0) = 0 AND OLD.periode IS NOT NULL THEN
                    SET @dld_snapshot_period_keys = COALESCE(@dld_snapshot_period_keys, '');
                    SET @dld_snapshot_period_key = DATE_FORMAT(OLD.periode, '%Y-%m-%d');

                    IF FIND_IN_SET(@dld_snapshot_period_key, @dld_snapshot_period_keys) = 0 THEN
                        DELETE FROM dashboard_pinjaman_snapshots WHERE periode = OLD.periode;
                        DELETE FROM dashboard_pinjaman_chart_periodik_snapshots WHERE periode = OLD.periode;
                        DELETE FROM dashboard_harian_snapshots WHERE snapshot_period = OLD.periode;
                        DELETE FROM rasio_casa_debitur_snapshots WHERE loan_period = OLD.periode;
                        SET @dld_snapshot_period_keys = CONCAT_WS(',', @dld_snapshot_period_keys, @dld_snapshot_period_key);
                    END IF;
                END IF;
            END
        ");
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_daily_loan_after_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_daily_loan_after_update');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_daily_loan_after_delete');

        DB::unprepared("
            CREATE TRIGGER trg_daily_loan_after_insert
            AFTER INSERT ON daily_loan_dinamis
            FOR EACH ROW
            BEGIN
                DELETE FROM dashboard_pinjaman_snapshots WHERE periode = NEW.periode;
                DELETE FROM dashboard_pinjaman_chart_periodik_snapshots WHERE periode = NEW.periode;
                DELETE FROM dashboard_harian_snapshots WHERE snapshot_period = NEW.periode;
                DELETE FROM rasio_casa_debitur_snapshots WHERE loan_period = NEW.periode;
            END
        ");
    }
};

