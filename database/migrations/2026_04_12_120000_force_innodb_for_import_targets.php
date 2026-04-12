<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = [
        'daily_loan_dinamis',
        'simpanan_multipn',
        'lw325_ph',
        'performance_pis_per_produk',
        'jumlah_merchant_detail',
        'merchant_qris',
        'merchant_qris_volume',
        'sv_merchant',
        'brilink_web_laporan_summary_transaksi_brilink_web',
        'user_brimo_fin',
        'brimo_fin',
        'user_brimo_rpt_v2',
        'brimo_rpt_v2',
        'input_rekanan',
        'bod_boc',
        'dashboard_pinjaman_snapshots',
        'dashboard_simpanan_snapshots',
        'dashboard_simpanan_branch_snapshots',
        'rasio_casa_debitur_snapshots',
        'rekening_dormant_snapshots',
        'performance_new_payroll_snapshots',
    ];

    public function up(): void
    {
        $driver = DB::connection()->getDriverName();
        if (!in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        foreach (self::TABLES as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            $engine = $this->getTableEngine($table);
            if (strtolower($engine) !== 'innodb') {
                DB::statement('ALTER TABLE `' . str_replace('`', '``', $table) . '` ENGINE=InnoDB');
            }
        }
    }

    public function down(): void
    {
        // Tidak melakukan downgrade engine secara otomatis.
    }

    private function getTableEngine(string $table): string
    {
        if (!in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return '';
        }

        $row = DB::selectOne(
            'SELECT ENGINE FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
            [$table]
        );

        return (string) ($row->ENGINE ?? $row->engine ?? '');
    }
};
