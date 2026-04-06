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
            $columns = array_map('strtolower', Schema::getColumnListing('daily_loan_dinamis'));

            $compatibilityColumns = [
                'periode' => fn () => $table->date('periode')->nullable(),
                'kode_kanwil' => fn () => $table->string('kode_kanwil', 50)->nullable(),
                'kanwil' => fn () => $table->string('kanwil', 100)->nullable(),
                'kode_cabang' => fn () => $table->string('kode_cabang', 50)->nullable(),
                'cabang' => fn () => $table->string('cabang', 100)->nullable(),
                'branch' => fn () => $table->string('branch', 100)->nullable(),
                'unit' => fn () => $table->string('unit', 100)->nullable(),
                'ao_name' => fn () => $table->string('ao_name', 150)->nullable(),
                'cifno' => fn () => $table->string('cifno', 50)->nullable(),
                'nomor_rekening' => fn () => $table->string('nomor_rekening', 100)->nullable(),
                'baki_debet' => fn () => $table->decimal('baki_debet', 18, 2)->nullable(),
                'segmen_dashboard' => fn () => $table->string('segmen_dashboard', 100)->nullable(),
                'produk_dashboard' => fn () => $table->string('produk_dashboard', 100)->nullable(),
            ];

            foreach ($compatibilityColumns as $column => $definition) {
                if (!in_array($column, $columns, true)) {
                    $definition();
                }
            }
        });

        DB::statement("
            UPDATE daily_loan_dinamis
            SET
                periode = COALESCE(periode, STR_TO_DATE(PERIODE, '%Y-%m-%d'), STR_TO_DATE(PERIODE, '%d-%m-%Y')),
                kode_kanwil = COALESCE(kode_kanwil, KODE_KANWIL1),
                kanwil = COALESCE(kanwil, KANWIL1),
                kode_cabang = COALESCE(kode_cabang, KODE_CABANG1),
                cabang = COALESCE(cabang, CABANG1),
                branch = COALESCE(branch, BRANCH1),
                unit = COALESCE(unit, UNIT1),
                ao_name = COALESCE(ao_name, AO_NAME),
                cifno = COALESCE(cifno, CIFNO),
                nomor_rekening = COALESCE(nomor_rekening, NOMOR_REKENING1),
                baki_debet = COALESCE(baki_debet, BAKI_DEBET1),
                segmen_dashboard = COALESCE(segmen_dashboard, SEGMEN_DASHBOARD),
                produk_dashboard = COALESCE(produk_dashboard, PRODUK_DASHBOARD)
        ");
    }

    public function down(): void
    {
        if (!Schema::hasTable('daily_loan_dinamis')) {
            return;
        }

        Schema::table('daily_loan_dinamis', function (Blueprint $table) {
            $columns = array_map('strtolower', Schema::getColumnListing('daily_loan_dinamis'));

            foreach ([
                'periode',
                'kode_kanwil',
                'kanwil',
                'kode_cabang',
                'cabang',
                'branch',
                'unit',
                'ao_name',
                'cifno',
                'nomor_rekening',
                'baki_debet',
                'segmen_dashboard',
                'produk_dashboard',
            ] as $column) {
                if (in_array($column, $columns, true)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
