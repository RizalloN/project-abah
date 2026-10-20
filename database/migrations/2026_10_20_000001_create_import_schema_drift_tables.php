<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('import_schema_drift_events')) {
            Schema::create('import_schema_drift_events', function (Blueprint $table) {
                $table->id();
                $table->string('table_name', 128);
                $table->string('report_label', 255)->nullable();
                $table->string('fingerprint', 64);
                $table->string('kind', 32);
                $table->string('status', 32)->default('pending');
                $table->string('summary', 500)->nullable();
                $table->longText('payload')->nullable();
                $table->string('source_file', 500)->nullable();
                $table->unsignedInteger('occurrences')->default(1);
                $table->timestamp('first_seen_at')->nullable();
                $table->timestamp('last_seen_at')->nullable();
                $table->string('decided_by', 150)->nullable();
                $table->timestamp('decided_at')->nullable();
                $table->text('applied_columns')->nullable();
                $table->text('rejected_columns')->nullable();
                $table->text('error')->nullable();
                $table->timestamp('acknowledged_at')->nullable();
                $table->timestamps();

                $table->unique(['table_name', 'fingerprint'], 'uq_import_drift_table_fingerprint');
                $table->index(['status', 'acknowledged_at'], 'idx_import_drift_status_ack');
            });
        }

        if (!Schema::hasTable('import_column_format_profiles')) {
            Schema::create('import_column_format_profiles', function (Blueprint $table) {
                $table->id();
                $table->string('table_name', 128);
                $table->string('column_name', 128);
                $table->string('source_header', 255)->nullable();
                $table->string('kind', 32);
                $table->string('signature', 120);
                $table->string('label', 120)->nullable();
                $table->string('date_order', 8)->nullable();
                $table->string('date_order_basis', 16)->nullable();
                $table->boolean('has_time')->default(false);
                $table->string('example', 255)->nullable();
                $table->unsignedInteger('observations')->default(1);
                $table->timestamps();

                $table->unique(['table_name', 'column_name'], 'uq_import_format_table_column');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('import_column_format_profiles');
        Schema::dropIfExists('import_schema_drift_events');
    }
};
