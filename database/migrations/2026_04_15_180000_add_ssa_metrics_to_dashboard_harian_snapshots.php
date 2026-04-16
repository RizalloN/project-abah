<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('dashboard_harian_snapshots')) {
            return;
        }

        Schema::table('dashboard_harian_snapshots', function (Blueprint $table) {
            $decimal = function (string $column) use ($table): void {
                if (!Schema::hasColumn('dashboard_harian_snapshots', $column)) {
                    $table->decimal($column, 20, 2)->default(0);
                }
            };

            foreach ([
                'simpanan_wholesale',
                'giro_wholesale',
                'deposito_wholesale',
                'tabungan_wholesale',
                'commercial_sml',
                'sme_sml',
                'kecil_sml',
                'kecil_non_cashcoll_sml',
                'cashcoll_sml',
                'medium_sml',
                'consumer_sml',
                'briguna_konsumer_sml',
                'kpr_sml',
                'kkb_sml',
                'micro_sml',
                'briguna_mikro_sml',
                'kupedes_sml',
                'kur_mikro_sml',
                'kur_kecil_sml',
                'kur_kpp_sml',
                'commercial_npl',
                'sme_npl',
                'kecil_npl',
                'kecil_non_cashcoll_npl',
                'cashcoll_npl',
                'medium_npl',
                'consumer_npl',
                'briguna_konsumer_npl',
                'kpr_npl',
                'kkb_npl',
                'micro_npl',
                'briguna_mikro_npl',
                'kupedes_npl',
                'kur_mikro_npl',
                'kur_kecil_npl',
                'kur_kpp_npl',
            ] as $column) {
                $decimal($column);
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('dashboard_harian_snapshots')) {
            return;
        }

        Schema::table('dashboard_harian_snapshots', function (Blueprint $table) {
            $columns = array_values(array_filter([
                'simpanan_wholesale',
                'giro_wholesale',
                'deposito_wholesale',
                'tabungan_wholesale',
                'commercial_sml',
                'sme_sml',
                'kecil_sml',
                'kecil_non_cashcoll_sml',
                'cashcoll_sml',
                'medium_sml',
                'consumer_sml',
                'briguna_konsumer_sml',
                'kpr_sml',
                'kkb_sml',
                'micro_sml',
                'briguna_mikro_sml',
                'kupedes_sml',
                'kur_mikro_sml',
                'kur_kecil_sml',
                'kur_kpp_sml',
                'commercial_npl',
                'sme_npl',
                'kecil_npl',
                'kecil_non_cashcoll_npl',
                'cashcoll_npl',
                'medium_npl',
                'consumer_npl',
                'briguna_konsumer_npl',
                'kpr_npl',
                'kkb_npl',
                'micro_npl',
                'briguna_mikro_npl',
                'kupedes_npl',
                'kur_mikro_npl',
                'kur_kecil_npl',
                'kur_kpp_npl',
            ], static fn (string $column): bool => Schema::hasColumn('dashboard_harian_snapshots', $column)));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
