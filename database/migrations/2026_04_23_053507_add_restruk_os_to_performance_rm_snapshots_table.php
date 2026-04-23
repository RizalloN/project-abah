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
        Schema::table('performance_rm_snapshots', function (Blueprint $table) {
            $table->decimal('restruk_os', 20, 2)->default(0)->after('npl_os');
        });
    }

    public function down(): void
    {
        Schema::table('performance_rm_snapshots', function (Blueprint $table) {
            $table->dropColumn('restruk_os');
        });
    }
};
