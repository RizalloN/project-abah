<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rasio_casa_debitur_uker_snapshots')) {
            return;
        }

        Schema::create('rasio_casa_debitur_uker_snapshots', function (Blueprint $table) {
            $table->string('uniqueid_rcdus', 32)->primary();
            $table->date('loan_period')->index();
            $table->date('casa_period')->nullable()->index();
            $table->string('source_branch_key', 191)->index();
            $table->string('uker_key', 191)->index();
            $table->string('uker_label', 191)->nullable();
            $table->string('segment_key', 32)->index();
            $table->decimal('os_amount', 20, 2)->default(0);
            $table->decimal('casa_amount', 20, 2)->default(0);
            $table->unsignedInteger('source_row_count')->default(0);
            $table->timestamps();

            $table->unique(
                ['loan_period', 'source_branch_key', 'uker_key', 'segment_key'],
                'rasio_casa_debitur_uker_snapshots_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rasio_casa_debitur_uker_snapshots');
    }
};
