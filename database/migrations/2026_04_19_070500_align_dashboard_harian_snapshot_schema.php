<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('dashboard_harian_snapshots')) {
            return;
        }

        Schema::table('dashboard_harian_snapshots', function (Blueprint $table) {
            if (!Schema::hasColumn('dashboard_harian_snapshots', 'kanca_key')) {
                $table->string('kanca_key', 150)->default('')->after('snapshot_period');
            }

            if (!Schema::hasColumn('dashboard_harian_snapshots', 'kanca_label')) {
                $table->string('kanca_label', 150)->nullable()->after('kanca_key');
            }

            if (!Schema::hasColumn('dashboard_harian_snapshots', 'unit_key')) {
                $table->string('unit_key', 180)->default('')->after('kanca_label');
            }

            if (!Schema::hasColumn('dashboard_harian_snapshots', 'unit_label')) {
                $table->string('unit_label', 180)->nullable()->after('unit_key');
            }

            if (!Schema::hasColumn('dashboard_harian_snapshots', 'source_row_count')) {
                $table->integer('source_row_count')->default(0)->after('kur_kpp_npl');
            }
        });

        $this->dropIndexIfExists('dashboard_harian_snapshots', 'idx_dhs_period_scope');

        if (!$this->indexExists('dashboard_harian_snapshots', 'uq_dhs_period_kanca_unit')) {
            Schema::table('dashboard_harian_snapshots', function (Blueprint $table) {
                $table->unique(['snapshot_period', 'kanca_key', 'unit_key'], 'uq_dhs_period_kanca_unit');
            });
        }

        $this->dropIndexIfExists('dashboard_harian_snapshots', 'idx_dhs_period_kanca_unit');

        DB::table('dashboard_harian_snapshots')->delete();
    }

    public function down(): void
    {
        if (!Schema::hasTable('dashboard_harian_snapshots')) {
            return;
        }

        $this->dropIndexIfExists('dashboard_harian_snapshots', 'idx_dhs_period_kanca_unit');
        $this->dropIndexIfExists('dashboard_harian_snapshots', 'uq_dhs_period_kanca_unit');

        if (!$this->indexExists('dashboard_harian_snapshots', 'idx_dhs_period_scope')
            && Schema::hasColumn('dashboard_harian_snapshots', 'scope')) {
            Schema::table('dashboard_harian_snapshots', function (Blueprint $table) {
                $table->index(['snapshot_period', 'scope'], 'idx_dhs_period_scope');
            });
        }

        Schema::table('dashboard_harian_snapshots', function (Blueprint $table) {
            $dropColumns = [];

            foreach (['kanca_key', 'kanca_label', 'unit_key', 'unit_label', 'source_row_count'] as $column) {
                if (Schema::hasColumn('dashboard_harian_snapshots', $column)) {
                    $dropColumns[] = $column;
                }
            }

            if ($dropColumns !== []) {
                $table->dropColumn($dropColumns);
            }
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $database = DB::getDatabaseName();

        return DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('index_name', $indexName)
            ->exists();
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (!$this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $tableBlueprint) use ($indexName) {
            $tableBlueprint->dropIndex($indexName);
        });
    }
};
