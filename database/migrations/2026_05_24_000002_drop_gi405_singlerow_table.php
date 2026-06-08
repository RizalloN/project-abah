<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('gi405_singlerow');
    }

    public function down(): void
    {
        if (!Schema::hasTable('gi405_singlerow')) {
            Schema::create('gi405_singlerow', function (Blueprint $table): void {
                $table->string('uniqueid_namareport')->primary();
                $table->date('periode')->nullable()->index();
                $table->string('branch', 20)->nullable()->index();
                $table->string('account_number', 64)->nullable()->index();
                $table->string('kode_uker', 20)->nullable()->index();
                $table->decimal('recovery_non_klaim', 24, 2)->nullable();
                $table->decimal('total_recovery', 24, 2)->nullable();
                $table->timestamps();
            });
        }
    }
};
