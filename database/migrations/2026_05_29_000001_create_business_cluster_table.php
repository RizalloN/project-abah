<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'business_cluster';
    private const REPORT_NAME = 'Business Cluster';

    public function up(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            Schema::create(self::TABLE, function (Blueprint $table) {
                $table->string('uniqueid_namareport', 120)->primary();
                $table->string('nama_kanca', 100)->unique();
                $table->text('link_url');
            });
        }

        $this->upsertReportMetadata();
    }

    public function down(): void
    {
        if (Schema::hasTable('nama_report')) {
            DB::table('nama_report')
                ->where('table_name', self::TABLE)
                ->delete();
        }

        Schema::dropIfExists(self::TABLE);
    }

    private function upsertReportMetadata(): void
    {
        if (!Schema::hasTable('nama_report')) {
            return;
        }

        $now = now();
        $payload = [
            'nama_report' => self::REPORT_NAME,
            'table_name' => self::TABLE,
            'active' => 1,
            'import_controller' => 'BusinessClusterController',
            'requires_manual_periode' => 0,
            'manual_periode_type' => null,
            'manual_periode_label' => null,
            'manual_periode_help' => null,
            'updated_at' => $now,
        ];

        $existingId = DB::table('nama_report')
            ->where('table_name', self::TABLE)
            ->orWhere('nama_report', self::REPORT_NAME)
            ->value('id_report');

        if ($existingId) {
            DB::table('nama_report')
                ->where('id_report', $existingId)
                ->update($payload);

            return;
        }

        DB::table('nama_report')->insert(array_merge($payload, [
            'id_report' => ((int) DB::table('nama_report')->max('id_report')) + 1,
            'created_at' => $now,
        ]));
    }
};
