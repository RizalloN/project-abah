<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('performance_rm_snapshots', function (Blueprint $table) {
            $table->integer('realisasi_deb')->default(0)->after('total_deb');
            $table->decimal('realisasi_os', 20, 2)->default(0)->after('realisasi_deb');
        });
    }

    public function down(): void
    {
        Schema::table('performance_rm_snapshots', function (Blueprint $table) {
            $table->dropColumn(['realisasi_deb', 'realisasi_os']);
        });
    }
};
