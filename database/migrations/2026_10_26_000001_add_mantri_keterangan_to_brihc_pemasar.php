<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('brihc_pemasar', 'keterangan')) {
            Schema::table('brihc_pemasar', function (Blueprint $table): void {
                $table->text('keterangan')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('brihc_pemasar', 'keterangan')) {
            Schema::table('brihc_pemasar', function (Blueprint $table): void {
                $table->dropColumn('keterangan');
            });
        }
    }
};
