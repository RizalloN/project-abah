<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('import_jobs') || Schema::hasColumn('import_jobs', 'job_context')) {
            return;
        }

        Schema::table('import_jobs', function (Blueprint $table) {
            $table->longText('job_context')->nullable()->after('created_by');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('import_jobs') || !Schema::hasColumn('import_jobs', 'job_context')) {
            return;
        }

        Schema::table('import_jobs', function (Blueprint $table) {
            $table->dropColumn('job_context');
        });
    }
};
