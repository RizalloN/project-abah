<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = ['lw321_npd', 'lw321_npdd'];

    public function up(): void
    {
        if (Schema::hasTable('nama_report')) {
            DB::table('nama_report')
                ->whereIn('table_name', self::TABLES)
                ->delete();
        }

        foreach (self::TABLES as $table) {
            Schema::dropIfExists($table);
        }
    }

    public function down(): void
    {
        // Data PTP is intentionally retired. Restore only from the SQL backup when required.
    }
};
