<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const HISTORY_TABLE = 'consumer_rm_position_history';

    private const CAPTURE_TABLE = 'consumer_rm_position_captures';

    public function up(): void
    {
        if (! Schema::hasTable(self::HISTORY_TABLE)) {
            Schema::create(self::HISTORY_TABLE, function (Blueprint $table): void {
                $table->date('periode');
                $table->string('produk', 32);
                $table->string('cifno_clean', 50);
                $table->string('account_key', 100);
                $table->date('tgl_realisasi')->nullable();
                $table->decimal('plafon', 20, 2)->default(0);
                $table->decimal('baki_debet', 20, 2)->default(0);
                $table->string('cabang', 100)->default('');
                $table->string('unit', 100)->default('');
                $table->string('branch_code', 100)->default('');
                $table->string('rm', 160)->default('');
                $table->text('pn_pengelola')->nullable();
                $table->text('pn_pemrakarsa')->nullable();
                $table->string('lookup_order', 255)->default('');
                $table->string('status_rekening1', 100)->nullable();
                $table->string('flag_restruk', 100)->nullable();
                $table->text('pn_restruk1')->nullable();
                $table->integer('restruk_ke1')->nullable();
                $table->string('jenis_restruk1', 100)->nullable();
                $table->date('tgl_akad_restruk')->nullable();

                // This single key is both the row identity and the left-prefix
                // lookup used for period/product/CIF history reads.
                $table->primary(
                    ['periode', 'produk', 'cifno_clean', 'account_key'],
                    'consumer_rm_position_history_pk'
                );
            });
        }

        if (! Schema::hasTable(self::CAPTURE_TABLE)) {
            Schema::create(self::CAPTURE_TABLE, function (Blueprint $table): void {
                $table->date('periode')->primary();
                $table->unsignedBigInteger('source_rows')->default(0);
                $table->unsignedBigInteger('archived_rows')->default(0);
                $table->char('content_hash', 64);
                $table->boolean('verified')->default(false);
                $table->timestamp('captured_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists(self::CAPTURE_TABLE);
        Schema::dropIfExists(self::HISTORY_TABLE);
    }
};
