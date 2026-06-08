<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'daily_loan_dinamis';
    private const INDEX = 'idx_dld_periode_cifno_clean';

    public function up(): void
    {
        if (!Schema::hasTable(self::TABLE) || $this->indexExists()) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->index(['periode', 'cifno_clean'], self::INDEX);
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable(self::TABLE) || !$this->indexExists()) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->dropIndex(self::INDEX);
        });
    }

    private function indexExists(): bool
    {
        $database = DB::getDatabaseName();

        return DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', self::TABLE)
            ->where('index_name', self::INDEX)
            ->exists();
    }
};
