<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('micro_pipeline_records') && ! Schema::hasColumn('micro_pipeline_records', 'source_key')) {
            Schema::table('micro_pipeline_records', function (Blueprint $table): void {
                $table->string('source_key', 32)->default('prewash')->after('id');
            });

            Schema::table('micro_pipeline_records', function (Blueprint $table): void {
                $table->dropUnique('micro_pipeline_source_row_unique');
                $table->unique(['source_key', 'source_sheet', 'source_row'], 'micro_pipeline_source_row_unique');
            });
        }

        if (Schema::hasTable('micro_pipeline_syncs') && ! Schema::hasColumn('micro_pipeline_syncs', 'source_key')) {
            Schema::table('micro_pipeline_syncs', function (Blueprint $table): void {
                $table->string('source_key', 32)->nullable()->after('id');
            });
            DB::table('micro_pipeline_syncs')->whereNull('source_key')->update(['source_key' => 'prewash']);
            Schema::table('micro_pipeline_syncs', function (Blueprint $table): void {
                $table->unique('source_key', 'micro_pipeline_sync_source_key_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('micro_pipeline_syncs') && Schema::hasColumn('micro_pipeline_syncs', 'source_key')) {
            Schema::table('micro_pipeline_syncs', function (Blueprint $table): void {
                $table->dropUnique('micro_pipeline_sync_source_key_unique');
                $table->dropColumn('source_key');
            });
        }

        if (Schema::hasTable('micro_pipeline_records') && Schema::hasColumn('micro_pipeline_records', 'source_key')) {
            Schema::table('micro_pipeline_records', function (Blueprint $table): void {
                $table->dropUnique('micro_pipeline_source_row_unique');
                $table->unique(['source_sheet', 'source_row'], 'micro_pipeline_source_row_unique');
                $table->dropColumn('source_key');
            });
        }
    }
};
