<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'dashboard_harian_snapshots';

    /** Kol-1 split by SSA flag_restruk: _lr = restruk Y, _lnr = restruk N, per Kredit category. */
    private const CATEGORIES = [
        'kecil_non_cashcoll',
        'cashcoll',
        'briguna_konsumer',
        'kpr',
        'kkb',
        'briguna_mikro',
        'kupedes',
        'kur_mikro',
        'kur_kecil',
        'kur_kpp',
    ];

    public function up(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $after = 'kur_kpp_npl';
            foreach ($this->columns() as $column) {
                if (!Schema::hasColumn(self::TABLE, $column)) {
                    $table->decimal($column, 20, 2)->default(0)->after($after);
                }
                $after = $column;
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            foreach (array_reverse($this->columns()) as $column) {
                if (Schema::hasColumn(self::TABLE, $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function columns(): array
    {
        $columns = [];
        foreach (['lr', 'lnr'] as $type) {
            foreach (self::CATEGORIES as $category) {
                $columns[] = "{$category}_{$type}";
            }
        }

        return $columns;
    }
};
