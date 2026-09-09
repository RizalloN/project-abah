<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('micro_pipeline_records', function (Blueprint $table): void {
            $table->id();
            $table->string('source_key', 32)->default('prewash');
            $table->unsignedInteger('source_row');
            $table->string('debtor_name')->nullable();
            $table->text('address')->nullable();
            $table->string('phone', 80)->nullable();
            $table->string('cif', 80)->nullable();
            $table->string('loan_account', 100)->nullable();
            $table->string('branch_key', 24);
            $table->string('branch_name', 80);
            $table->string('unit_code', 32)->nullable();
            $table->string('unit_name', 160)->nullable();
            $table->decimal('plafond', 22, 2)->default(0);
            $table->decimal('outstanding', 22, 2)->default(0);
            $table->string('source_pipeline', 180)->nullable();
            $table->string('legacy_source_pipeline', 180)->nullable();
            $table->string('mantri_pn', 40)->nullable();
            $table->string('mantri_name', 160)->nullable();
            $table->string('recommended_product', 100)->nullable();
            $table->decimal('score', 14, 4)->nullable();
            $table->string('legacy_pipeline_id', 100)->nullable();
            $table->text('description')->nullable();
            $table->decimal('real_plafond', 22, 2)->default(0);
            $table->string('visit_status', 24)->default('pending');
            $table->unsignedTinyInteger('reported_visit_count')->default(0);
            $table->unsignedTinyInteger('visit_count')->default(0);
            $table->unsignedTinyInteger('planned_count')->default(0);
            $table->string('last_visit_month', 8)->nullable();
            $table->json('visit_months')->nullable();
            $table->json('planned_months')->nullable();
            $table->string('source_sheet', 120);
            $table->timestamps();

            $table->unique(['source_key', 'source_sheet', 'source_row'], 'micro_pipeline_source_row_unique');
            $table->index(['branch_key', 'visit_status'], 'micro_pipeline_branch_status_idx');
            $table->index(['branch_key', 'source_pipeline'], 'micro_pipeline_branch_source_idx');
            $table->index(['branch_key', 'recommended_product'], 'micro_pipeline_branch_product_idx');
            $table->index(['branch_key', 'unit_name'], 'micro_pipeline_branch_unit_idx');
            $table->index('mantri_pn', 'micro_pipeline_mantri_idx');
        });

        Schema::create('micro_pipeline_syncs', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('source_key', 32)->unique();
            $table->text('source_url')->nullable();
            $table->string('source_file', 255)->nullable();
            $table->string('source_sheet', 120)->nullable();
            $table->char('source_hash', 64)->nullable();
            $table->unsignedInteger('source_rows')->default(0);
            $table->unsignedInteger('imported_rows')->default(0);
            $table->unsignedInteger('outside_scope_rows')->default(0);
            $table->timestamp('synced_at')->nullable();
            $table->json('stats')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('micro_pipeline_syncs');
        Schema::dropIfExists('micro_pipeline_records');
    }
};
