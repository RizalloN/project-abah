<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Keep the widths from the full export schema and store codes as text.
        $sourceColumns = [
            'TAHUN' => 10,
            'KODE_KANWIL' => 50,
            'NAMA_KANWIL' => 100,
            'KODE_KCI' => 50,
            'KODE_BRANCH' => 50,
            'JENIS' => 50,
            'SEGMENTASI_JENIS' => 100,
        ];
        $existing = array_map('strtoupper', Schema::getColumnListing('sv_merchant'));
        $missing = array_diff_key($sourceColumns, array_fill_keys($existing, true));

        if ($missing !== []) {
            Schema::table('sv_merchant', function (Blueprint $table) use ($missing): void {
                foreach ($missing as $column => $length) {
                    $table->string($column, $length)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Kolom sumber SV Merchant dapat memuat data atau sudah ada sebelum migrasi. Menghapusnya memerlukan migrasi terpisah.');
    }
};
