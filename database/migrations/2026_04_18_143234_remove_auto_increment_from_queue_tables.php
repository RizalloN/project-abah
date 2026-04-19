<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. jobs table: Remove AUTO_INCREMENT
        DB::statement('ALTER TABLE jobs MODIFY id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE jobs AUTO_INCREMENT = 1');

        // 2. failed_jobs table: Remove AUTO_INCREMENT
        DB::statement('ALTER TABLE failed_jobs MODIFY id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE failed_jobs AUTO_INCREMENT = 1');

        // 3. job_batches table: Convert from string to BIGINT and remove any default
        // Standard Laravel uses string(255) IDs for batches.
        DB::statement('ALTER TABLE job_batches MODIFY id BIGINT UNSIGNED NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. restore jobs
        DB::statement('ALTER TABLE jobs MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');

        // 2. restore failed_jobs
        DB::statement('ALTER TABLE failed_jobs MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');

        // 3. restore job_batches (back to string)
        DB::statement('ALTER TABLE job_batches MODIFY id VARCHAR(255) NOT NULL');
    }
};
