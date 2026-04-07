<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'daily_loan_dinamis';

    public function up(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        DB::statement('
            UPDATE `daily_loan_dinamis`
            SET
                `created_at` = COALESCE(`created_at`, `updated_at`, NOW()),
                `updated_at` = COALESCE(`updated_at`, `created_at`, NOW())
            WHERE `created_at` IS NULL OR `updated_at` IS NULL
        ');

        $columns = collect(DB::select('SHOW COLUMNS FROM `' . self::TABLE . '`'))
            ->keyBy(fn ($column) => $column->Field);

        if ($columns->isEmpty()) {
            return;
        }

        $coreOrder = [
            'id',
            'uniqueid_namareport',
            'periode',
            'kode_kanwil1',
            'kanwil1',
            'kode_cabang1',
            'cabang1',
            'branch1',
            'unit1',
            'curtyp',
            'ao_name',
            'cifno',
            'nomor_rekening1',
            'status_rekening1',
            'ln_type',
            'nama_debitur1',
            'rate',
            'jangka_waktu1',
            'plafon',
            'baki_debet1',
            'ckpn',
            'nilai_tercatat1',
            'kol_adk1',
            'kolek_detail',
            'kolek',
            'kolektabilitas_lancar',
            'kolektabilitas_dpk',
            'kolektabilitas_kuranglancar',
            'kolektabilitas_diragukan',
            'kolektabilitas_macet',
            'total_kewajiban',
            'tunggakan_pokok',
            'tunggakan_bunga',
            'tunggakan_penalti',
            'umur_tunggakan',
            'tgl_realisasi',
            'tgl_jatuh_tempo',
            'tanggal_menunggak',
            'tgl_bayar_terakhir',
            'tgl_terminate',
            'last_date_maintenance_billing',
            'next_pmt_date',
            'next_pmt_int_date',
            'advance_payment',
            'bap',
            'payment_amount',
            'final_payment_amount',
            'npb_pokok_la',
            'npb_pokok_lf',
            'npb_bunga_la',
            'npb_bunga_lf',
            'jml_angsuran1',
            'jumlah_bayar',
            'deffered_bunga',
            'sai_tunggakan',
            'sai_deffered',
            'sai1',
            'freq_payment',
            'freq_int_payment',
            'jadwal_gp_pokok',
            'pn_pengelola1',
            'pn_name1',
            'pn_pemrakarsa1',
            'pn_referral1',
            'pn_restruk1',
            'pn_pengelola2',
            'pn_pemutus1',
            'pn_crm1',
            'pn_crr',
            'pn_referral_naik_kelas1',
            'jumlah_pn1',
            'jumlah_pn_all1',
            'code',
            'description',
            'kecamatan_t_tinggal',
            'kelurahan_t_tinggal',
            'kodepos_t_tinggal',
            'kecamatan_t_usaha',
            'kelurahan_t_usaha',
            'kodepos_t_usaha',
            'segmen_dashboard',
            'produk_dashboard',
            'divisi_segmen_dashboard',
            'npl_method',
            'restruk_ke1',
            'jenis_restruk1',
            'tgl_akad_restruk',
            'flag_restruk',
            'flag_restruk_covid1',
            'flag_commodity_chain1',
            'flag_briguna_digital1',
            'flag_agf',
            'flag_aft',
            'pmtamt',
            'pmtamt_base',
            'offcr',
            'lbdotu',
            'keterangan_pn_pengelola',
            'os_idr',
            'flag_klaim',
            'os_sebelum_klaim',
            'os_penuh_berjalan',
            'bilprn',
            'bilint',
            'billc',
        ];

        $legacyOrder = [
            'kode_kanwil',
            'kanwil',
            'kode_cabang',
            'cabang',
            'branch',
            'unit',
            'nomor_rekening',
            'baki_debet',
            'textbox20',
            'textbox21',
        ];

        $timestampOrder = ['created_at', 'updated_at'];

        $desiredOrder = array_values(array_filter(array_merge($coreOrder, $legacyOrder), fn ($name) => $columns->has($name)));

        $known = array_fill_keys(array_merge($desiredOrder, $timestampOrder), true);
        $unexpectedColumns = [];
        foreach ($columns->keys()->all() as $columnName) {
            if (!isset($known[$columnName])) {
                $unexpectedColumns[] = $columnName;
            }
        }

        $desiredOrder = array_merge(
            $desiredOrder,
            $unexpectedColumns,
            array_values(array_filter($timestampOrder, fn ($name) => $columns->has($name)))
        );

        $clauses = [];
        $previousColumn = null;

        foreach ($desiredOrder as $columnName) {
            $column = $columns->get($columnName);
            $definition = $this->buildColumnDefinition($column);
            $position = $previousColumn === null
                ? ' FIRST'
                : ' AFTER `' . $previousColumn . '`';

            $clauses[] = 'MODIFY COLUMN ' . $definition . $position;
            $previousColumn = $columnName;
        }

        if (!empty($clauses)) {
            DB::statement("ALTER TABLE `" . self::TABLE . "`\n" . implode(",\n", $clauses));
        }
    }

    public function down(): void
    {
        // Column reordering is intentionally not reversed.
    }

    private function buildColumnDefinition(object $column): string
    {
        $definition = '`' . $column->Field . '` ' . $column->Type;
        $definition .= $column->Null === 'YES' ? ' NULL' : ' NOT NULL';

        if ($column->Default !== null) {
            $default = (string) $column->Default;
            $upperDefault = strtoupper($default);

            if (in_array($upperDefault, ['CURRENT_TIMESTAMP', 'CURRENT_TIMESTAMP()'], true)) {
                $definition .= ' DEFAULT ' . $default;
            } else {
                $definition .= ' DEFAULT ' . DB::getPdo()->quote($default);
            }
        } elseif ($column->Null === 'YES') {
            $definition .= ' DEFAULT NULL';
        }

        if (!empty($column->Extra)) {
            $definition .= ' ' . strtoupper((string) $column->Extra);
        }

        return $definition;
    }
};
