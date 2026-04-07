<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_sync_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('import_job_id')->nullable()->index();
            $table->string('source', 150)->nullable()->index();
            $table->string('table_name', 120)->index();
            $table->date('period_hint')->nullable()->index();
            $table->string('action', 80)->index();
            $table->string('status', 30)->index();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedInteger('affected_rows')->nullable();
            $table->text('message')->nullable();
            $table->json('context')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_sync_audits');
    }
};
