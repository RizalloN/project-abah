<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'brihc_pemasar';

    public function up(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            Schema::create(self::TABLE, function (Blueprint $table): void {
                $table->string('uniqueid_namareport', 255)->primary();
                $table->string('completename')->nullable();
                $table->string('nip')->nullable();
                $table->string('pernr')->nullable();
                $table->string('sex')->nullable();
                $table->string('dateofbirth')->nullable();
                $table->string('age')->nullable();
                $table->string('esgdesc')->nullable();
                $table->string('padesc')->nullable();
                $table->string('psadesc')->nullable();
                $table->string('orgdesc')->nullable();
                $table->string('tmt_uker')->nullable();
                $table->string('mku')->nullable();
                $table->string('positiondesc')->nullable();
                $table->string('tmt_jabatan')->nullable();
                $table->string('mkj')->nullable();
                $table->string('descprogrammasuk')->nullable();
                $table->string('tmt_masuk')->nullable();
                $table->string('mke')->nullable();
                $table->string('jobgrade')->nullable();
                $table->string('tmtjg')->nullable();
                $table->string('mkjg')->nullable();
                $table->string('pg')->nullable();
                $table->string('tmtpg')->nullable();
                $table->string('mkpg')->nullable();
                $table->string('bc')->nullable();
                $table->string('pn_mantri')->nullable();
                $table->string('status')->nullable();
                $table->string('jg')->nullable();
                $table->string('bln_2026')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('nama_report')) {
            $now = now();
            $existingId = DB::table('nama_report')->where('table_name', self::TABLE)->value('id_report');
            $reportId = $existingId ?: ((int) DB::table('nama_report')->max('id_report')) + 1;

            DB::table('nama_report')->updateOrInsert(
                ['table_name' => self::TABLE],
                [
                    'id_report' => $reportId,
                    'nama_report' => 'BRIHC Pemasar',
                    'active' => 1,
                    'import_controller' => 'ImportExcelController',
                    'requires_manual_periode' => 0,
                    'manual_periode_type' => null,
                    'manual_periode_label' => null,
                    'manual_periode_help' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('nama_report')) {
            DB::table('nama_report')->where('table_name', self::TABLE)->delete();
        }

        Schema::dropIfExists(self::TABLE);
    }
};
