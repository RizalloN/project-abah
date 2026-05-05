<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'dashboard_harian_snapshots';

    private const COLUMNS = [
        'rec_dh_total',
        'rec_dh_small',
        'rec_dh_consumer',
        'rec_dh_micro',
    ];

    public function up(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            foreach (self::COLUMNS as $column) {
                if (!Schema::hasColumn(self::TABLE, $column)) {
                    $table->decimal($column, 20, 2)->default(0)->after('ph_lunas');
                }
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            foreach (array_reverse(self::COLUMNS) as $column) {
                if (Schema::hasColumn(self::TABLE, $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
