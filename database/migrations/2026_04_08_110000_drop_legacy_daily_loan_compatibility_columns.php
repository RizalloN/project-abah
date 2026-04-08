<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'daily_loan_dinamis';

    public function up(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        $legacyColumns = [
            'kode_kanwil',
            'kanwil',
            'kode_cabang',
            'cabang',
            'branch',
            'unit',
            'nomor_rekening',
            'baki_debet',
        ];

        $existingColumns = array_values(array_filter(
            $legacyColumns,
            static fn (string $column): bool => Schema::hasColumn(self::TABLE, $column)
        ));

        if ($existingColumns === []) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) use ($existingColumns) {
            $table->dropColumn($existingColumns);
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        $definitions = [
            'kode_kanwil' => static function (Blueprint $table): void {
                $table->string('kode_kanwil', 50)->nullable();
            },
            'kanwil' => static function (Blueprint $table): void {
                $table->string('kanwil', 100)->nullable();
            },
            'kode_cabang' => static function (Blueprint $table): void {
                $table->string('kode_cabang', 50)->nullable();
            },
            'cabang' => static function (Blueprint $table): void {
                $table->string('cabang', 100)->nullable();
            },
            'branch' => static function (Blueprint $table): void {
                $table->string('branch', 100)->nullable();
            },
            'unit' => static function (Blueprint $table): void {
                $table->string('unit', 100)->nullable();
            },
            'nomor_rekening' => static function (Blueprint $table): void {
                $table->string('nomor_rekening', 100)->nullable();
            },
            'baki_debet' => static function (Blueprint $table): void {
                $table->decimal('baki_debet', 18, 2)->nullable();
            },
        ];

        Schema::table(self::TABLE, function (Blueprint $table) use ($definitions) {
            foreach ($definitions as $column => $definition) {
                if (!Schema::hasColumn(self::TABLE, $column)) {
                    $definition($table);
                }
            }
        });
    }
};
