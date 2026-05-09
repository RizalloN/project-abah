<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop if exists to ensure clean state
        if (Schema::hasTable('import_jobs')) {
            // Drop foreign keys first if any exist
            $this->dropForeignKeysIfExist('import_jobs');
            Schema::dropIfExists('import_jobs');
        }

        // Create fresh import_jobs table with all required columns
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
            $table->string('job_fingerprint', 64)->nullable();
            $table->string('job_content_hash', 64)->nullable();
            $table->timestamps();

            // Indexes
            $table->unique('job_fingerprint', 'idx_import_jobs_job_fingerprint');
            $table->index(['status', 'updated_at'], 'idx_import_jobs_status_updated_at');
            $table->index(['created_by', 'status', 'created_at'], 'idx_import_jobs_created_by_status_created_at');
            $table->index(['id_report', 'created_at'], 'idx_import_jobs_report_created_at');
            $table->index('job_content_hash', 'idx_import_jobs_content_hash');
        });

        // Verify table was created
        if (!Schema::hasTable('import_jobs')) {
            throw new \RuntimeException('Failed to create import_jobs table');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('import_jobs')) {
            $this->dropForeignKeysIfExist('import_jobs');
            Schema::dropIfExists('import_jobs');
        }
    }

    private function dropForeignKeysIfExist(string $table): void
    {
        try {
            $database = config('database.connections.mysql.database');
            $foreignKeys = DB::select(
                "SELECT CONSTRAINT_NAME 
                 FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL",
                [$database, $table]
            );

            foreach ($foreignKeys as $fk) {
                try {
                    DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
                } catch (\Throwable $e) {
                    \Log::warning("Could not drop foreign key {$fk->CONSTRAINT_NAME}: " . $e->getMessage());
                }
            }
        } catch (\Throwable $e) {
            \Log::warning("Error checking foreign keys: " . $e->getMessage());
        }
    }
};
