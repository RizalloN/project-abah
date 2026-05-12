<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_audit_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('actor_id')->comment('ID admin yang melakukan aksi');
            $table->string('action', 20)->comment('create | update | delete');
            $table->unsignedBigInteger('target_id')->nullable()->comment('ID user yang dikenai aksi');
            $table->string('target_name', 100)->nullable();
            $table->string('target_pn', 20)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->json('extra')->nullable()->comment('Data tambahan, misal role lama/baru');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['actor_id', 'action', 'created_at'], 'idx_audit_actor_action_ts');
            $table->index('created_at', 'idx_audit_created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_audit_log');
    }
};
