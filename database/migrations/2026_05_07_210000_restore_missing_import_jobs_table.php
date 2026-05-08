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
            Schema::create('import_jobs', function (Blueprint $table) {
                $table->unsignedBigInteger('id')->primary();
                $table->unsignedBigInteger('id_report');
                $table->string('file_name');
                $table->text('folder_path');
                $table->string('status')->default('uploaded');
                $table->integer('total_files')->nullable();
                $table->unsignedInteger('total_success')->default(0);
                $table->unsignedInteger('total_failed')->default(0);
                $table->unsignedBigInteger('created_by');
                $table->text('message')->nullable();
                $table->longText('job_context')->nullable();
                $table->string('job_fingerprint', 64)->nullable()->unique('idx_import_jobs_job_fingerprint');
                $table->timestamps();

                $table->index(['status', 'updated_at'], 'idx_import_jobs_status_updated_at');
                $table->index(['created_by', 'status', 'created_at'], 'idx_import_jobs_created_by_status_created_at');
                $table->index(['id_report', 'created_at'], 'idx_import_jobs_report_created_at');
            });
        }

        $this->ensureJobContentHashColumn();
        $this->ensureIndex('idx_import_jobs_content_hash', ['job_content_hash']);
    }

    public function down(): void
    {
        // Do not drop import_jobs on rollback; this migration restores a missing
        // core job-management table and existing job history must be preserved.
    }

    private function ensureJobContentHashColumn(): void
    {
        if (!Schema::hasTable('import_jobs') || Schema::hasColumn('import_jobs', 'job_content_hash')) {
            return;
        }

        DB::statement(
            "ALTER TABLE `import_jobs`
             ADD COLUMN `job_content_hash` VARCHAR(64)
             GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(job_context, '$.content_hash')))
             VIRTUAL
             AFTER `job_context`"
        );
    }

    private function ensureIndex(string $indexName, array $columns): void
    {
        if (!Schema::hasTable('import_jobs')) {
            return;
        }

        $exists = DB::select(
            "SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'import_jobs'
               AND INDEX_NAME = ?",
            [$indexName]
        );

        if (!empty($exists)) {
            return;
        }

        $quotedColumns = implode(',', array_map(static fn (string $column): string => "`{$column}`", $columns));
        DB::statement("ALTER TABLE `import_jobs` ADD INDEX `{$indexName}` ({$quotedColumns})");
    }
};
