<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('rekening_dormant_snapshots')) {
            return;
        }

        if (!Schema::hasColumn('rekening_dormant_snapshots', 'snapshot_version')) {
            Schema::table('rekening_dormant_snapshots', function (Blueprint $table) {
                $table->unsignedSmallInteger('snapshot_version')->default(1)->after('posisi');
                $table->index(['posisi', 'snapshot_version'], 'rekening_dormant_snapshots_posisi_snapshot_version_index');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('rekening_dormant_snapshots')) {
            return;
        }

        if (Schema::hasColumn('rekening_dormant_snapshots', 'snapshot_version')) {
            Schema::table('rekening_dormant_snapshots', function (Blueprint $table) {
                $table->dropIndex('rekening_dormant_snapshots_posisi_snapshot_version_index');
                $table->dropColumn('snapshot_version');
            });
        }
    }
};
