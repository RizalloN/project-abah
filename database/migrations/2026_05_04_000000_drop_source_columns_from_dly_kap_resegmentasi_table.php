<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('dly_kap_resegmentasi')) {
            return;
        }

        $dropColumns = array_values(array_filter([
            Schema::hasColumn('dly_kap_resegmentasi', 'source_section') ? 'source_section' : null,
            Schema::hasColumn('dly_kap_resegmentasi', 'source_row_number') ? 'source_row_number' : null,
        ]));

        if ($dropColumns === []) {
            return;
        }

        Schema::table('dly_kap_resegmentasi', function (Blueprint $table) use ($dropColumns) {
            $table->dropColumn($dropColumns);
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('dly_kap_resegmentasi')) {
            return;
        }

        $hasSourceSection = Schema::hasColumn('dly_kap_resegmentasi', 'source_section');
        $hasSourceRowNumber = Schema::hasColumn('dly_kap_resegmentasi', 'source_row_number');

        Schema::table('dly_kap_resegmentasi', function (Blueprint $table) use ($hasSourceSection, $hasSourceRowNumber) {
            if (!$hasSourceSection) {
                $table->string('source_section', 30)->nullable()->after('kode_unit');
            }

            if (!$hasSourceRowNumber) {
                $table->unsignedInteger('source_row_number')->nullable()->after('source_section');
            }
        });
    }
};
