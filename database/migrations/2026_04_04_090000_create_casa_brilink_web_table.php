<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('casa_brilink_web', function (Blueprint $table) {
            $table->id();
            $table->string('uniqueid_namareport')->unique();
            $table->date('periode')->index();
            $table->unsignedInteger('row_num')->nullable();
            $table->string('region', 20)->nullable();
            $table->string('rgdesc', 150)->nullable();
            $table->string('mainbr', 20)->nullable();
            $table->string('mbdesc', 150)->nullable();
            $table->string('branch', 20)->nullable();
            $table->string('brdesc', 150)->nullable();
            $table->string('kode_agen', 30)->nullable();
            $table->string('mid_code', 50)->nullable();
            $table->string('account', 50)->nullable()->index();
            $table->string('keterangan', 100)->nullable();
            $table->string('sumber', 50)->nullable();
            $table->decimal('jml_nominal_casa', 20, 2)->nullable();
            $table->decimal('textbox9', 20, 2)->nullable();
            $table->string('cifno', 50)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('casa_brilink_web');
    }
};
