<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('import_jobs')) {
            return;
        }

        Schema::table('import_jobs', function (Blueprint $table) {
            $table->index(['status', 'updated_at'], 'idx_import_jobs_status_updated_at');
            $table->index(['created_by', 'status', 'created_at'], 'idx_import_jobs_created_by_status_created_at');
            $table->index(['id_report', 'created_at'], 'idx_import_jobs_report_created_at');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('import_jobs')) {
            return;
        }

        Schema::table('import_jobs', function (Blueprint $table) {
            $table->dropIndex('idx_import_jobs_status_updated_at');
            $table->dropIndex('idx_import_jobs_created_by_status_created_at');
            $table->dropIndex('idx_import_jobs_report_created_at');
        });
    }
};
