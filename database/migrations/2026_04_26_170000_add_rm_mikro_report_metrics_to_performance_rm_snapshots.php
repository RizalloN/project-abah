<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'performance_rm_snapshots';

    public function up(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            if (!Schema::hasColumn(self::TABLE, 'lancar_deb')) {
                $table->integer('lancar_deb')->default(0)->after('lancar_os');
            }

            if (!Schema::hasColumn(self::TABLE, 'sml_deb')) {
                $table->integer('sml_deb')->default(0)->after('sml_os');
            }

            if (!Schema::hasColumn(self::TABLE, 'npl_deb')) {
                $table->integer('npl_deb')->default(0)->after('npl_os');
            }

            foreach (['w1', 'w2', 'w3', 'w4'] as $week) {
                $debColumn = "{$week}_realisasi_deb";
                $osColumn = "{$week}_realisasi_os";

                if (!Schema::hasColumn(self::TABLE, $debColumn)) {
                    $table->integer($debColumn)->default(0)->after('realisasi_os');
                }

                if (!Schema::hasColumn(self::TABLE, $osColumn)) {
                    $table->decimal($osColumn, 20, 2)->default(0)->after($debColumn);
                }
            }

            if (!Schema::hasColumn(self::TABLE, 'lt_250_realisasi_deb')) {
                $table->integer('lt_250_realisasi_deb')->default(0)->after('w4_realisasi_os');
            }

            if (!Schema::hasColumn(self::TABLE, 'lt_250_realisasi_os')) {
                $table->decimal('lt_250_realisasi_os', 20, 2)->default(0)->after('lt_250_realisasi_deb');
            }

            if (!Schema::hasColumn(self::TABLE, 'gt_250_realisasi_deb')) {
                $table->integer('gt_250_realisasi_deb')->default(0)->after('lt_250_realisasi_os');
            }

            if (!Schema::hasColumn(self::TABLE, 'gt_250_realisasi_os')) {
                $table->decimal('gt_250_realisasi_os', 20, 2)->default(0)->after('gt_250_realisasi_deb');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        $columns = [
            'lancar_deb',
            'sml_deb',
            'npl_deb',
            'w1_realisasi_deb',
            'w1_realisasi_os',
            'w2_realisasi_deb',
            'w2_realisasi_os',
            'w3_realisasi_deb',
            'w3_realisasi_os',
            'w4_realisasi_deb',
            'w4_realisasi_os',
            'lt_250_realisasi_deb',
            'lt_250_realisasi_os',
            'gt_250_realisasi_deb',
            'gt_250_realisasi_os',
        ];

        $existing = array_values(array_filter($columns, fn (string $column): bool => Schema::hasColumn(self::TABLE, $column)));

        if ($existing !== []) {
            Schema::table(self::TABLE, function (Blueprint $table) use ($existing): void {
                $table->dropColumn($existing);
            });
        }
    }
};
