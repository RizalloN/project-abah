<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('tmp_bulk_csv_stage_1_qjjjl0uc');
    }

    public function down(): void
    {
        // Tabel staging sementara, tidak perlu direstorasi
    }
};
