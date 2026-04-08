<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SUMMARY_TABLE = 'dashboard_simpanan_snapshots';
    private const BRANCH_TABLE = 'dashboard_simpanan_branch_snapshots';
    private const SOURCE_TABLE = 'simpanan_multipn';

    public function up(): void
    {
        if (!Schema::hasTable(self::SUMMARY_TABLE)) {
            Schema::create(self::SUMMARY_TABLE, function (Blueprint $table) {
                $table->date('snapshot_period')->primary();
                $table->decimal('total_balance', 24, 2)->default(0);
                $table->unsignedBigInteger('account_count')->default(0);
                $table->unsignedBigInteger('cif_count')->default(0);
                $table->unsignedInteger('branch_count')->default(0);
                $table->unsignedInteger('unit_count')->default(0);
                $table->decimal('tabungan_balance', 24, 2)->default(0);
                $table->decimal('giro_balance', 24, 2)->default(0);
                $table->decimal('other_balance', 24, 2)->default(0);
                $table->string('top_branch_label', 150)->nullable();
                $table->decimal('top_branch_balance', 24, 2)->default(0);
                $table->unsignedBigInteger('source_row_count')->default(0);
                $table->timestamp('source_updated_at')->nullable();
                $table->timestamps();

                $table->index('source_updated_at', 'idx_dss_source_updated_at');
            });
        }

        if (!Schema::hasTable(self::BRANCH_TABLE)) {
            Schema::create(self::BRANCH_TABLE, function (Blueprint $table) {
                $table->string('uniqueid_dsbs', 191)->primary();
                $table->date('snapshot_period');
                $table->string('kantor_cabang', 150);
                $table->decimal('total_balance', 24, 2)->default(0);
                $table->unsignedTinyInteger('rank_order')->default(0);
                $table->timestamps();

                $table->unique(['snapshot_period', 'kantor_cabang'], 'uq_dsbs_period_branch');
                $table->index(['snapshot_period', 'rank_order'], 'idx_dsbs_period_rank');
                $table->index(['snapshot_period', 'total_balance'], 'idx_dsbs_period_balance');
            });
        }

        if (Schema::hasTable(self::SOURCE_TABLE)) {
            $this->addIndexIfMissing(self::SOURCE_TABLE, 'idx_smp_posisi_rekening', ['posisi', 'no_rekening']);
            $this->addIndexIfMissing(self::SOURCE_TABLE, 'idx_smp_posisi_cabang', ['posisi', 'kantor_cabang']);
            $this->addIndexIfMissing(self::SOURCE_TABLE, 'idx_smp_posisi_updated', ['posisi', 'updated_at']);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable(self::SOURCE_TABLE)) {
            $this->dropIndexIfExists(self::SOURCE_TABLE, 'idx_smp_posisi_rekening');
            $this->dropIndexIfExists(self::SOURCE_TABLE, 'idx_smp_posisi_cabang');
            $this->dropIndexIfExists(self::SOURCE_TABLE, 'idx_smp_posisi_updated');
        }

        Schema::dropIfExists(self::BRANCH_TABLE);
        Schema::dropIfExists(self::SUMMARY_TABLE);
    }

    private function addIndexIfMissing(string $table, string $indexName, array $columns): void
    {
        if ($this->hasIndex($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($indexName, $columns) {
            $blueprint->index($columns, $indexName);
        });
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (!$this->hasIndex($table, $indexName)) {
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
