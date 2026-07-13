<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shadow_backfill_checkpoints', function (Blueprint $table) {
            $table->id();
            $table->string('period', 20)->unique();
            $table->string('last_processed_id')->nullable();
            $table->bigInteger('rows_processed')->default(0);
            $table->integer('chunks_completed')->default(0);
            $table->float('completion_percentage')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('last_updated_at')->nullable();
            $table->timestamps();

            $table->index('updated_at');
        });

        Schema::create('shadow_backfill_failures', function (Blueprint $table) {
            $table->id();
            $table->string('periods');
            $table->text('error_message');
            $table->integer('attempts')->default(1);
            $table->string('status')->default('pending');
            $table->timestamp('failed_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('failed_at');
        });

        Schema::create('shadow_backfill_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('period', 20);
            $table->integer('chunk_number');
            $table->integer('chunk_size');
            $table->float('duration_seconds', 8, 3);
            $table->integer('rows_per_second');
            $table->boolean('success')->default(true);
            $table->timestamp('executed_at');

            $table->index(['period', 'executed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shadow_backfill_metrics');
        Schema::dropIfExists('shadow_backfill_failures');
        Schema::dropIfExists('shadow_backfill_checkpoints');
    }
};
