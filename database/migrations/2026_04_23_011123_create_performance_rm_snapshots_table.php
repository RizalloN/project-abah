<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_rm_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('periode');
            $table->string('cabang', 100);
            $table->string('unit', 100);
            $table->string('rm', 255);
            $table->string('segmen', 50);
            $table->string('produk', 100);
            $table->decimal('loan_os', 20, 2)->default(0);
            $table->decimal('lancar_os', 20, 2)->default(0);
            $table->decimal('sml_os', 20, 2)->default(0);
            $table->decimal('npl_os', 20, 2)->default(0);
            $table->integer('total_deb')->default(0);
            $table->decimal('total_deposit', 20, 2)->default(0);
            $table->timestamps();

            $table->index(['periode', 'segmen', 'cabang'], 'idx_p_s_c');
            $table->index(['periode', 'segmen', 'rm'], 'idx_p_s_rm');
            $table->index('rm');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_rm_snapshots');
    }
};
