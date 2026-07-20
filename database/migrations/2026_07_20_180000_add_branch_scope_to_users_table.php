<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'branch_scope')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('branch_scope', 20)->default('area6')->after('role');
            });
        }

        foreach ([
            '0045' => 'madiun',
            '0049' => 'magetan',
            '0057' => 'ngawi',
            '0070' => 'ponorogo',
        ] as $pn => $branchScope) {
            DB::table('users')
                ->where('pn', $pn)
                ->update(['branch_scope' => $branchScope]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'branch_scope')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('branch_scope');
            });
        }
    }
};
