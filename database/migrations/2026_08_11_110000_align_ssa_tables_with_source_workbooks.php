<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ssa_simpanan')) {
            if (!Schema::hasColumn('ssa_simpanan', 'segmen_kategorisasi_bisnis')) {
                Schema::table('ssa_simpanan', function (Blueprint $table) {
                    $table->string('segmen_kategorisasi_bisnis', 100)
                        ->nullable()
                        ->after('segmentasi');
                });
            }

            $this->dropColumnsIfPresent('ssa_simpanan', [
                'regional_office',
                'id_cabang',
                'id_uker',
                'cif',
                'no_rekening',
            ]);
        }

        if (Schema::hasTable('ssa_pinjaman')) {
            $this->dropColumnsIfPresent('ssa_pinjaman', [
                'regional_office',
                'id_cabang',
                'id_uker',
                'cif',
                'nominatif',
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ssa_simpanan')) {
            $this->addColumnsIfMissing('ssa_simpanan', [
                'regional_office' => 100,
                'id_cabang' => 20,
                'id_uker' => 20,
                'cif' => 50,
                'no_rekening' => 50,
            ]);

            if (Schema::hasColumn('ssa_simpanan', 'segmen_kategorisasi_bisnis')) {
                Schema::table('ssa_simpanan', function (Blueprint $table) {
                    $table->dropColumn('segmen_kategorisasi_bisnis');
                });
            }
        }

        if (Schema::hasTable('ssa_pinjaman')) {
            $this->addColumnsIfMissing('ssa_pinjaman', [
                'regional_office' => 100,
                'id_cabang' => 20,
                'id_uker' => 20,
                'cif' => 50,
                'nominatif' => 50,
            ]);
        }
    }

    /**
     * @param array<int, string> $columns
     */
    private function dropColumnsIfPresent(string $tableName, array $columns): void
    {
        $columns = array_values(array_filter(
            $columns,
            static fn (string $column): bool => Schema::hasColumn($tableName, $column)
        ));

        if ($columns === []) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });
    }

    /**
     * @param array<string, int> $columns
     */
    private function addColumnsIfMissing(string $tableName, array $columns): void
    {
        $missing = array_filter(
            $columns,
            static fn (int $length, string $column): bool => !Schema::hasColumn($tableName, $column),
            ARRAY_FILTER_USE_BOTH
        );

        if ($missing === []) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($missing) {
            foreach ($missing as $column => $length) {
                $table->string($column, $length)->nullable();
            }
        });
    }
};
