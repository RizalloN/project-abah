<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('dashboard_harian_snapshots')) {
            return;
        }

        Schema::table('dashboard_harian_snapshots', function (Blueprint $table): void {
            if (!Schema::hasColumn('dashboard_harian_snapshots', 'source_signature')) {
                $table->string('source_signature', 64)->nullable()->after('source_row_count');
            }

            if (!Schema::hasColumn('dashboard_harian_snapshots', 'source_recovery_row_count')) {
                $table->unsignedBigInteger('source_recovery_row_count')->nullable()->after('source_savings_row_count');
            }

            if (!Schema::hasColumn('dashboard_harian_snapshots', 'source_recovery_period')) {
                $table->date('source_recovery_period')->nullable()->after('source_recovery_row_count');
            }
        });
    }

    public function down(): void
    {
        // Repair-only migration: keep rollback non-destructive because these columns
        // may have been created by the original metadata migration in other databases.
    }
};
