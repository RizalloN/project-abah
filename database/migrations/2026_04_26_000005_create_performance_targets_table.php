<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('performance_targets', function (Blueprint $table) {
            $table->id();
            $table->string('category')->index(); // e.g. BRIGUNA-KONSUMER, KPR
            $table->string('rm_name')->index();   // e.g. BAGUS PRASETYO
            $table->integer('target_deb')->default(0);
            $table->decimal('target_os', 20, 2)->default(0);
            $table->timestamps();

            $table->unique(['category', 'rm_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('performance_targets');
    }
};
