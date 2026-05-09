<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - Drop hourly_dpk table completely
     */
    public function up(): void
    {
        // Drop foreign key if exists
        if (Schema::hasTable('hourly_dpk')) {
            try {
                $this->dropForeignKeysIfExist('hourly_dpk');
            } catch (\Throwable $e) {
                \Log::warning("Error dropping foreign keys: " . $e->getMessage());
            }
            
            Schema::dropIfExists('hourly_dpk');
        }
    }

    /**
     * Reverse the migrations - Recreate table if rollback
     */
    public function down(): void
    {
        // Do not recreate on rollback - data cleanup is permanent
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
