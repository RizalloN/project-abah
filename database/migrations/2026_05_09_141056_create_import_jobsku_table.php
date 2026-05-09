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
        Schema::create('import_jobsku', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('id_report');
            $table->string('sku')->index();
            $table->string('file_name');
            $table->text('folder_path');
            $table->string('status')->default('uploaded')->index();
            $table->integer('total_items')->nullable();
            $table->unsignedInteger('total_processed')->default(0);
            $table->unsignedInteger('total_success')->default(0);
            $table->unsignedInteger('total_failed')->default(0);
            $table->unsignedBigInteger('created_by');
            $table->text('message')->nullable();
            $table->longText('job_context')->nullable();
            $table->string('job_fingerprint', 64)->nullable()->unique('idx_import_jobsku_job_fingerprint');
            $table->timestamps();

            $table->index(['status', 'updated_at'], 'idx_import_jobsku_status_updated_at');
            $table->index(['created_by', 'status', 'created_at'], 'idx_import_jobsku_created_by_status_created_at');
            $table->index(['id_report', 'created_at'], 'idx_import_jobsku_report_created_at');
            $table->index(['sku', 'status'], 'idx_import_jobsku_sku_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_jobsku');
    }
};
