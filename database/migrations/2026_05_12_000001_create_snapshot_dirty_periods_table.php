<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('snapshot_dirty_periods')) {
            Schema::create('snapshot_dirty_periods', function (Blueprint $table): void {
                $table->string('source_table', 64);
                $table->string('period_key', 40);
                $table->string('shard_type', 32)->default('period');
                $table->string('shard_key', 100)->default('*');
                $table->dateTime('dirty_since', 6);
                $table->unsignedBigInteger('dirty_row_count')->default(0);
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->dateTime('last_attempted_at', 6)->nullable();
                $table->dateTime('claimed_at', 6)->nullable();
                $table->dateTime('dirty_since_at_claim', 6)->nullable();
                $table->string('claim_token', 64)->nullable();
                $table->text('last_error')->nullable();
                $table->timestamps(6);

                $table->primary(['source_table', 'period_key', 'shard_type', 'shard_key'], 'pk_snapshot_dirty_periods');
                $table->index(['claimed_at', 'dirty_since'], 'idx_snapshot_dirty_periods_drain');
                $table->index(['attempts', 'last_attempted_at'], 'idx_snapshot_dirty_periods_retry');
            });
        }

        if (!Schema::hasTable('failed_snapshot_dirty_periods')) {
            Schema::create('failed_snapshot_dirty_periods', function (Blueprint $table): void {
                $table->string('source_table', 64);
                $table->string('period_key', 40);
                $table->string('shard_type', 32)->default('period');
                $table->string('shard_key', 100)->default('*');
                $table->dateTime('dirty_since', 6)->nullable();
                $table->unsignedBigInteger('dirty_row_count')->default(0);
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->text('last_error')->nullable();
                $table->dateTime('failed_at', 6);
                $table->timestamps(6);

                $table->primary(['source_table', 'period_key', 'shard_type', 'shard_key'], 'pk_failed_snapshot_dirty_periods');
                $table->index(['failed_at'], 'idx_failed_snapshot_dirty_periods_failed_at');
            });
        }
    }

    public function down(): void
    {
        // Intentionally keep dirty-period tables on rollback so pending recovery
        // evidence is not destroyed. Trigger rollback is handled separately.
        Log::notice('Rollback intentionally preserved snapshot dirty-period tables; purge them explicitly if this is a dev reset.');
    }
};
