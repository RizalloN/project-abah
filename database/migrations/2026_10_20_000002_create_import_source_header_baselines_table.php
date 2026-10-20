<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Source header baseline per import table: the columns a report's source
     * document normally carries, including the ones the table never stores.
     * Only a header that is absent from this baseline is a "new column".
     */
    public function up(): void
    {
        if (Schema::hasTable('import_source_header_baselines')) {
            return;
        }

        Schema::create('import_source_header_baselines', function (Blueprint $table) {
            $table->id();
            $table->string('table_name', 128);
            $table->string('header_key', 128);
            $table->string('header_label', 255)->nullable();
            $table->string('db_column', 128)->nullable();
            $table->string('signature', 120)->nullable();
            $table->string('label', 120)->nullable();
            $table->string('example', 255)->nullable();
            $table->unsignedInteger('occurrences')->default(1);
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['table_name', 'header_key'], 'uq_import_source_header');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_source_header_baselines');
    }
};
