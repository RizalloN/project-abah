<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('daily_loan_dinamis')) {
            return;
        }

        Schema::table('daily_loan_dinamis', function (Blueprint $table) {
            if (!Schema::hasColumn('daily_loan_dinamis', 'jangka_waktu1')) {
                $table->string('jangka_waktu1', 50)->nullable();
            }

            if (!Schema::hasColumn('daily_loan_dinamis', 'total_kewajiban')) {
                $table->decimal('total_kewajiban', 20, 2)->nullable();
            }

            if (!Schema::hasColumn('daily_loan_dinamis', 'os_idr')) {
                $table->decimal('os_idr', 20, 2)->nullable();
            }
        });

        $hasTextbox20 = Schema::hasColumn('daily_loan_dinamis', 'textbox20');
        $hasTextbox21 = Schema::hasColumn('daily_loan_dinamis', 'textbox21');

        DB::statement("
            UPDATE daily_loan_dinamis
            SET
                kode_kanwil = COALESCE(NULLIF(kode_kanwil, ''), kode_kanwil1),
                kanwil = COALESCE(NULLIF(kanwil, ''), kanwil1),
                kode_cabang = COALESCE(NULLIF(kode_cabang, ''), kode_cabang1),
                cabang = COALESCE(NULLIF(cabang, ''), cabang1),
                branch = COALESCE(NULLIF(branch, ''), branch1),
                unit = COALESCE(NULLIF(unit, ''), unit1),
                nomor_rekening = COALESCE(NULLIF(nomor_rekening, ''), nomor_rekening1),
                baki_debet = COALESCE(baki_debet, baki_debet1),
                total_kewajiban = COALESCE(total_kewajiban, " . ($hasTextbox20 ? "textbox20" : "NULL") . "),
                os_idr = COALESCE(os_idr, " . ($hasTextbox21 ? "textbox21" : "NULL") . ")
        ");

        if ($hasTextbox20) {
            DB::statement("
                UPDATE daily_loan_dinamis
                SET textbox20 = COALESCE(textbox20, total_kewajiban)
            ");
        }

        if ($hasTextbox21) {
            DB::statement("
                UPDATE daily_loan_dinamis
                SET textbox21 = COALESCE(textbox21, os_idr)
            ");
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('daily_loan_dinamis')) {
            return;
        }

        Schema::table('daily_loan_dinamis', function (Blueprint $table) {
            if (Schema::hasColumn('daily_loan_dinamis', 'jangka_waktu1')) {
                $table->dropColumn('jangka_waktu1');
            }

            if (Schema::hasColumn('daily_loan_dinamis', 'total_kewajiban')) {
                $table->dropColumn('total_kewajiban');
            }

            if (Schema::hasColumn('daily_loan_dinamis', 'os_idr')) {
                $table->dropColumn('os_idr');
            }
        });
    }
};
