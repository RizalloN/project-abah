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

        DB::unprepared('DROP TRIGGER IF EXISTS trg_dld_after_delete_invalidate_snapshots');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_smp_after_delete_invalidate_snapshots');

        DB::unprepared('
            CREATE TRIGGER trg_dld_after_delete_invalidate_snapshots
            AFTER DELETE ON daily_loan_dinamis
            FOR EACH ROW
            BEGIN
                IF OLD.periode IS NOT NULL THEN
                    DELETE FROM dashboard_pinjaman_snapshots
                    WHERE periode = OLD.periode;

                    DELETE FROM rasio_casa_debitur_snapshots
                    WHERE loan_period = OLD.periode;
                END IF;
            END
        ');

        DB::unprepared('
            CREATE TRIGGER trg_smp_after_delete_invalidate_snapshots
            AFTER DELETE ON simpanan_multipn
            FOR EACH ROW
            BEGIN
                IF OLD.posisi IS NOT NULL THEN
                    DELETE FROM rekening_dormant_snapshots
                    WHERE posisi = OLD.posisi;

                    DELETE FROM rasio_casa_debitur_snapshots
                    WHERE casa_period = OLD.posisi;
                END IF;
            END
        ');
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS trg_dld_after_delete_invalidate_snapshots');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_smp_after_delete_invalidate_snapshots');
    }
};

