<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'external_report_links';

    public function up(): void
    {
        if (Schema::hasTable(self::TABLE)) {
            return;
        }

        Schema::create(self::TABLE, function (Blueprint $table) {
            $table->string('uniqueid_link', 120)->primary();
            $table->string('group_key', 80);
            $table->string('link_key', 100);
            $table->string('label', 160);
            $table->string('sheet_name', 160)->nullable();
            $table->string('spreadsheet_id', 160)->nullable();
            $table->text('link_url');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['group_key', 'link_key'], 'uq_external_report_links_scope');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(self::TABLE);
    }
};
