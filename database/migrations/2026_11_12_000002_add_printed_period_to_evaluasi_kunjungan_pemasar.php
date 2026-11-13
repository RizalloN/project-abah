<?php

use App\Services\Import\EvaluasiKunjunganSourceService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $name = EvaluasiKunjunganSourceService::TABLE;
        if (! Schema::hasColumn($name, 'periode')) {
            Schema::table($name, function (Blueprint $table): void {
                // Existing uploads are filled only after reconciliation with their source.
                $table->date('periode')->nullable();
            });
        }
        if (! Schema::hasColumn($name, 'date_printed')) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dateTime('date_printed')->nullable();
            });
        }
        $covered = false;
        foreach (Schema::getIndexes($name) as $index) {
            if (array_slice($index['columns'], 0, 2) === ['periode', 'kode_main_branch']) {
                $covered = true;
                break;
            }
        }
        if (! $covered) {
            Schema::table($name, function (Blueprint $table): void {
                $table->index(['periode', 'kode_main_branch'], 'idx_kunjungan_period_branch');
            });
        }
    }

    public function down(): void
    {
        $name = EvaluasiKunjunganSourceService::TABLE;
        if (Schema::hasIndex($name, 'idx_kunjungan_period_branch')) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropIndex('idx_kunjungan_period_branch');
            });
        }
        Schema::table($name, function (Blueprint $table): void {
            $table->dropColumn(['periode', 'date_printed']);
        });
    }
};
