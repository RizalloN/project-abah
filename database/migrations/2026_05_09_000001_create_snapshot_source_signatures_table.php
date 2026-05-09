<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('snapshot_source_signatures')) {
            return;
        }

        Schema::create('snapshot_source_signatures', function (Blueprint $table) {
            $table->id();
            $table->string('source_table', 100);
            $table->string('snapshot_table', 100);
            $table->string('period_key', 40);
            $table->string('source_signature', 64);
            $table->unsignedBigInteger('source_row_count')->default(0);
            $table->timestamp('source_max_updated_at')->nullable();
            $table->timestamp('built_at')->nullable();
            $table->text('context')->nullable();
            $table->timestamps();

            $table->unique(['source_table', 'snapshot_table', 'period_key'], 'uq_snapshot_source_signature_scope');
            $table->index(['snapshot_table', 'period_key'], 'idx_snapshot_source_signature_snapshot_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('snapshot_source_signatures');
    }
};
