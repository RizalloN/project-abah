<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'hourly_dpk';

    public function up(): void
    {
        if (!Schema::hasTable(self::TABLE) || !Schema::hasColumn(self::TABLE, 'segmen') || !Schema::hasColumn(self::TABLE, 'segmen2')) {
            return;
        }

        DB::table(self::TABLE)
            ->where(function ($query): void {
                $query->whereNull('segmen2')->orWhereRaw("TRIM(segmen2) = ''");
            })
            ->whereIn(DB::raw('UPPER(TRIM(segmen))'), ['RITEL', 'KORPORASI'])
            ->update(['segmen2' => DB::raw('UPPER(TRIM(segmen))')]);
    }

    public function down(): void
    {
        //
    }
};
