<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('import_jobs')) {
            return;
        }

        Schema::table('import_jobs', function (Blueprint $table) {
            if (!Schema::hasColumn('import_jobs', 'job_fingerprint')) {
                $table->string('job_fingerprint', 64)->nullable()->after('job_context');
            }
        });

        $this->addIndexIfMissing('import_jobs', 'idx_import_jobs_status_updated_at', ['status', 'updated_at']);
        $this->addIndexIfMissing('import_jobs', 'idx_import_jobs_created_by_status_created_at', ['created_by', 'status', 'created_at']);
        $this->addIndexIfMissing('import_jobs', 'idx_import_jobs_report_created_at', ['id_report', 'created_at']);
        $this->addIndexIfMissing('import_jobs', 'idx_import_jobs_job_fingerprint', ['job_fingerprint'], true);
    }

    public function down(): void
    {
        if (!Schema::hasTable('import_jobs')) {
            return;
        }

        $this->dropIndexIfExists('import_jobs', 'idx_import_jobs_job_fingerprint');
        $this->dropIndexIfExists('import_jobs', 'idx_import_jobs_report_created_at');
        $this->dropIndexIfExists('import_jobs', 'idx_import_jobs_created_by_status_created_at');
        $this->dropIndexIfExists('import_jobs', 'idx_import_jobs_status_updated_at');

        if (Schema::hasColumn('import_jobs', 'job_fingerprint')) {
            Schema::table('import_jobs', function (Blueprint $table) {
                $table->dropColumn('job_fingerprint');
            });
        }
    }

    private function addIndexIfMissing(string $table, string $indexName, array $columns, bool $unique = false): void
    {
        if ($this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $indexName, $unique) {
            if ($unique) {
                $blueprint->unique($columns, $indexName);
                return;
            }

            $blueprint->index($columns, $indexName);
        });
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (!$this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($indexName) {
            $blueprint->dropIndex($indexName);
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $database = Schema::getConnection()->getDatabaseName();

        return DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('index_name', $indexName)
            ->exists();
    }
};
