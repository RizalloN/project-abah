<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('input_rekanan') && !Schema::hasColumn('input_rekanan', 'periode')) {
            Schema::table('input_rekanan', function (Blueprint $table) {
                $table->date('periode')->nullable()->after('id');
            });
        }

        if (Schema::hasTable('bod_boc') && !Schema::hasColumn('bod_boc', 'periode')) {
            Schema::table('bod_boc', function (Blueprint $table) {
                $table->date('periode')->nullable()->after('id');
            });
        }

        $this->addIndexIfMissing('input_rekanan', 'idx_input_rekanan_periode', ['periode']);
        $this->addIndexIfMissing('bod_boc', 'idx_bod_boc_periode', ['periode']);
    }

    public function down(): void
    {
        $this->dropIndexIfExists('input_rekanan', 'idx_input_rekanan_periode');
        $this->dropIndexIfExists('bod_boc', 'idx_bod_boc_periode');

        if (Schema::hasTable('input_rekanan') && Schema::hasColumn('input_rekanan', 'periode')) {
            Schema::table('input_rekanan', function (Blueprint $table) {
                $table->dropColumn('periode');
            });
        }

        if (Schema::hasTable('bod_boc') && Schema::hasColumn('bod_boc', 'periode')) {
            Schema::table('bod_boc', function (Blueprint $table) {
                $table->dropColumn('periode');
            });
        }
    }

    private function addIndexIfMissing(string $table, string $indexName, array $columns): void
    {
        if (!Schema::hasTable($table) || $this->hasIndex($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($indexName, $columns) {
            $blueprint->index($columns, $indexName);
        });
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (!Schema::hasTable($table) || !$this->hasIndex($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($indexName) {
            $blueprint->dropIndex($indexName);
        });
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $result = DB::select('SHOW INDEX FROM `' . str_replace('`', '``', $table) . '` WHERE Key_name = ?', [$indexName]);

        return !empty($result);
    }
};

