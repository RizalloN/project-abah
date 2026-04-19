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
        // Remove AUTO_INCREMENT from id column
        DB::statement('ALTER TABLE import_jobs MODIFY id BIGINT UNSIGNED NOT NULL');
        
        // Reset AUTO_INCREMENT value to 1 just in case
        DB::statement('ALTER TABLE import_jobs AUTO_INCREMENT = 1');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore AUTO_INCREMENT to id column
        DB::statement('ALTER TABLE import_jobs MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
    }
};
