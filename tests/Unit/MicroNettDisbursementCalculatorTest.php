<?php

namespace Tests\Unit;

use App\Support\MicroNettDisbursementCalculator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MicroNettDisbursementCalculatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('daily_loan_dinamis', function (Blueprint $table): void {
            $table->id();
            foreach ([
                'periode', 'tgl_realisasi', 'cabang_normalized', 'cabang1', 'unit_normalized', 'unit1',
                'branch_normalized', 'branch1', 'pn_pemrakarsa1', 'pn_pengelola1', 'nomor_rekening1', 'cifno',
                'segmen_kinerja', 'produk_kinerja', 'description',
            ] as $column) {
                $table->string($column)->nullable();
            }
            $table->decimal('plafon', 20, 2)->nullable();
            $table->decimal('baki_debet1', 20, 2)->nullable();
        });
    }

    public function test_duplicate_current_accounts_keep_maximum_plafond_once(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->row('OLD', 100_000_000, ['periode' => '2026-08-31', 'tgl_realisasi' => '2026-08-01']),
            $this->row('NEW', 300_000_000),
            $this->row(' new ', 250_000_000),
        ]);

        $calculator = app(MicroNettDisbursementCalculator::class);
        foreach (['kurRm', 'mantri'] as $method) {
            $result = $calculator->{$method}('2026-09-19');
            $this->assertCount(1, $result);
            $this->assertSame(1, $result[0]['realisasi_deb']);
            $this->assertSame(200_000_000.0, $result[0]['realisasi_os']);
        }
        $kur = $calculator->kurRm('2026-09-19')[0];
        $this->assertSame(200_000_000.0, $kur['w1_realisasi_os']);
        $this->assertSame(1, $kur['gt_250_realisasi_deb']);
        $this->assertSame(200_000_000.0, $kur['gt_250_realisasi_os']);
    }

    public function test_cached_reads_match_fresh_reads_and_refresh_on_new_data_version(): void
    {
        \Illuminate\Support\Facades\Cache::flush();
        $calculator = app(MicroNettDisbursementCalculator::class);

        // An empty month is not pinned: data imported afterwards must appear.
        $this->assertSame([], $calculator->cachedMantri('2026-09-19'));
        DB::table('daily_loan_dinamis')->insert([$this->row('NEW', 300_000_000)]);
        $this->assertSame($calculator->mantri('2026-09-19'), $calculator->cachedMantri('2026-09-19'));
        $this->assertSame($calculator->kurRm('2026-09-19'), $calculator->cachedKurRm('2026-09-19'));

        // Within one data version the month scan is shared.
        DB::table('daily_loan_dinamis')->insert([$this->row('NEW-2', 100_000_000)]);
        $this->assertSame(1, $calculator->cachedMantri('2026-09-19')[0]['realisasi_deb']);

        // An import bumps the version and the cached read sees the new rows.
        \App\Support\ReportCacheVersion::bump('pinjaman');
        $this->assertSame(2, $calculator->cachedMantri('2026-09-19')[0]['realisasi_deb']);
        $this->assertSame(2, $calculator->cachedKurRm('2026-09-19')[0]['realisasi_deb']);
    }

    public function test_duplicate_previous_accounts_keep_maximum_os_once_and_sum_distinct_accounts(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->row('OLD-A', 100_000_000, ['periode' => '2026-08-31']),
            $this->row(' old-a ', 80_000_000, ['periode' => '2026-08-31']),
            $this->row('OLD-B', 50_000_000, ['periode' => '2026-08-31']),
            $this->row('NEW', 300_000_000),
        ]);

        foreach (['kurRm', 'mantri'] as $method) {
            $result = app(MicroNettDisbursementCalculator::class)->{$method}('2026-09-19');
            $this->assertSame(1, $result[0]['realisasi_deb']);
            $this->assertSame(150_000_000.0, $result[0]['realisasi_os']);
        }
    }

    public function test_previous_rows_without_account_identity_keep_complete_cif_exposure(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->row('', 40_000_000, ['periode' => '2026-08-31']),
            $this->row('   ', 30_000_000, ['periode' => '2026-08-31']),
            $this->row('OLD', 100_000_000, ['periode' => '2026-08-31']),
            $this->row(' old ', 80_000_000, ['periode' => '2026-08-31']),
            $this->row('NEW', 300_000_000),
        ]);

        foreach (['kurRm', 'mantri'] as $method) {
            $result = app(MicroNettDisbursementCalculator::class)->{$method}('2026-09-19');
            $this->assertSame(1, $result[0]['realisasi_deb']);
            $this->assertSame(130_000_000.0, $result[0]['realisasi_os']);
        }
    }

    public function test_signed_mantri_and_kur_values_keep_negative_nett_and_existing_kur_250_million_tier_rules(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->row('OLD', 300_000_000, ['periode' => '2026-08-31']),
            $this->row('NEW', 250_000_000, ['pn_pemrakarsa1' => '']),
            $this->row('FUTURE', 900_000_000, ['tgl_realisasi' => '2026-09-20']),
        ]);

        $calculator = app(MicroNettDisbursementCalculator::class);
        $kur = $calculator->kurRm('2026-09-19')[0];
        $mantri = $calculator->mantri('2026-09-19')[0];
        $this->assertSame('RM LAIN', $kur['rm']);
        $this->assertSame('RM LAIN', $mantri['owner']);
        $this->assertSame(-50_000_000.0, $kur['realisasi_os']);
        $this->assertSame(-50_000_000.0, $mantri['realisasi_os']);
        $this->assertSame(1, $kur['realisasi_deb']);
        $this->assertSame(1, $mantri['realisasi_deb']);
        $this->assertSame(0, $kur['lt_250_realisasi_deb']);
        $this->assertSame(0, $kur['gt_250_realisasi_deb']);
    }

    public function test_each_mantri_booking_subtracts_complete_closed_cif_exposure_while_kur_keeps_its_segment_basis(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->row('OLD-KUR', 100_000_000, ['periode' => '2026-08-31']),
            $this->row('OLD-OTHER', 50_000_000, [
                'periode' => '2026-08-31', 'segmen_kinerja' => 'SMALL', 'produk_kinerja' => 'KOMERSIAL',
            ]),
            $this->row('NEW-A', 300_000_000),
            $this->row('NEW-B', 200_000_000),
        ]);

        $calculator = app(MicroNettDisbursementCalculator::class);
        $kur = $calculator->kurRm('2026-09-19')[0];
        $mantri = $calculator->mantri('2026-09-19')[0];
        $this->assertSame(2, $kur['realisasi_deb']);
        $this->assertSame(300_000_000.0, $kur['realisasi_os']);
        $this->assertSame(2, $mantri['realisasi_deb']);
        $this->assertSame(200_000_000.0, $mantri['realisasi_os']);
        $accounts = collect($calculator->mantriAccounts('2026-09-19'))->keyBy('account_key');
        $this->assertSame(150_000_000.0, $accounts['NEW-A']['previous_os']);
        $this->assertSame(150_000_000.0, $accounts['NEW-A']['net_amount']);
        $this->assertSame(150_000_000.0, $accounts['NEW-B']['previous_os']);
        $this->assertSame(50_000_000.0, $accounts['NEW-B']['net_amount']);
    }

    public function test_mtd_keeps_disappeared_booking_and_deduplicates_daily_positions(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->row('00012345', 200_000_000, ['periode' => '2026-09-05']),
            $this->row('12345', 250_000_000, ['periode' => '2026-09-06']),
            $this->row('00012345', 225_000_000, ['periode' => '2026-09-07']),
            $this->row('OTHER', 100_000_000, [
                'periode' => '2026-09-20',
                'tgl_realisasi' => '2026-09-20',
            ]),
        ]);

        foreach (['kurRm', 'mantri'] as $method) {
            $result = app(MicroNettDisbursementCalculator::class)->{$method}('2026-09-19');
            $this->assertCount(1, $result);
            $this->assertSame(1, $result[0]['realisasi_deb']);
            $this->assertSame(250_000_000.0, $result[0]['realisasi_os']);
        }
    }

    public function test_mtd_keeps_booking_initiator_but_updates_current_mantri_manager(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->row('00012345', 200_000_000, [
                'periode' => '2026-09-05',
                'pn_pemrakarsa1' => '',
            ]),
            $this->row('12345', 250_000_000, [
                'periode' => '2026-09-06',
                'pn_pemrakarsa1' => '000777',
            ]),
            $this->row('00012345', 300_000_000, [
                'periode' => '2026-09-07',
                'pn_pemrakarsa1' => '000888',
            ]),
        ]);

        $calculator = app(MicroNettDisbursementCalculator::class);
        $this->assertSame('000777', $calculator->kurRm('2026-09-19')[0]['rm']);
        $this->assertSame('000888', $calculator->mantri('2026-09-19')[0]['owner']);
    }

    public function test_zero_padded_previous_account_is_not_double_counted(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->row('000999', 100_000_000, ['periode' => '2026-08-31']),
            $this->row('999', 80_000_000, ['periode' => '2026-08-31']),
            $this->row('NEW', 300_000_000),
        ]);

        foreach (['kurRm', 'mantri'] as $method) {
            $result = app(MicroNettDisbursementCalculator::class)->{$method}('2026-09-19');
            $this->assertSame(200_000_000.0, $result[0]['realisasi_os']);
        }
    }

    public function test_mantri_subtracts_full_closed_cif_baseline_from_each_account_without_capping_negative_results(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->row('OLD', 300_000_000, ['periode' => '2026-08-31']),
            $this->row('NEW-A', 250_000_000),
            $this->row('NEW-B', 100_000_000, ['tgl_realisasi' => '2026-09-06']),
        ]);

        $calculator = app(MicroNettDisbursementCalculator::class);
        $this->assertSame(-250_000_000.0, $calculator->kurRm('2026-09-19')[0]['realisasi_os']);
        $this->assertSame(-250_000_000.0, $calculator->mantri('2026-09-19')[0]['realisasi_os']);
        $accounts = collect($calculator->mantriAccounts('2026-09-19'))->keyBy('account_key');
        $this->assertSame(300_000_000.0, $accounts['NEW-A']['previous_os']);
        $this->assertSame(-50_000_000.0, $accounts['NEW-A']['net_amount']);
        $this->assertSame(300_000_000.0, $accounts['NEW-B']['previous_os']);
        $this->assertSame(-200_000_000.0, $accounts['NEW-B']['net_amount']);
    }

    public function test_kur_uses_only_official_kur_ritel_description(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->row('OFFICIAL', 200_000_000),
            $this->row('SHADOW', 900_000_000, ['description' => 'KUR Ritel Shadow Classification']),
        ]);

        $result = app(MicroNettDisbursementCalculator::class)->kurRm('2026-09-19');

        $this->assertCount(1, $result);
        $this->assertSame(1, $result[0]['realisasi_deb']);
        $this->assertSame(200_000_000.0, $result[0]['realisasi_os']);
    }

    public function test_mantri_uses_valid_manager_when_initiator_is_blank(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->row('NEW', 300_000_000, [
                'pn_pemrakarsa1' => '',
                'pn_pengelola1' => '000777',
            ]),
        ]);

        $calculator = app(MicroNettDisbursementCalculator::class);
        $mantri = $calculator->mantri('2026-09-19');
        $this->assertCount(1, $mantri);
        $this->assertSame('000777', $mantri[0]['owner']);
        $this->assertSame(1, $mantri[0]['realisasi_deb']);
        $this->assertSame(300_000_000.0, $mantri[0]['realisasi_os']);
        $this->assertSame('RM LAIN', $calculator->kurRm('2026-09-19')[0]['rm']);
    }

    public function test_mantri_uses_manager_while_kur_preserves_distinct_initiator(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->row('NEW', 300_000_000, [
                'pn_pemrakarsa1' => '000111',
                'pn_pengelola1' => '000222',
            ]),
        ]);

        $calculator = app(MicroNettDisbursementCalculator::class);
        $this->assertSame('000222', $calculator->mantri('2026-09-19')[0]['owner']);
        $this->assertSame('000111', $calculator->kurRm('2026-09-19')[0]['rm']);
    }

    public function test_duplicate_positions_use_latest_observed_manager_independently_of_booking_initiator(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->row('00012345', 200_000_000, [
                'periode' => '2026-09-05',
                'pn_pemrakarsa1' => '000111',
                'pn_pengelola1' => '',
            ]),
            $this->row('12345', 250_000_000, [
                'periode' => '2026-09-06',
                'pn_pemrakarsa1' => '000222',
                'pn_pengelola1' => '000777',
            ]),
            $this->row('00012345', 300_000_000, [
                'periode' => '2026-09-07',
                'pn_pemrakarsa1' => '000333',
                'pn_pengelola1' => '000888',
                'cabang_normalized' => 'KC NGAWI', 'cabang1' => 'KC Ngawi',
                'unit_normalized' => 'UNIT B', 'unit1' => 'Unit B',
            ]),
        ]);

        $calculator = app(MicroNettDisbursementCalculator::class);
        $mantri = $calculator->mantri('2026-09-19');
        $this->assertCount(1, $mantri);
        $this->assertSame('000888', $mantri[0]['owner']);
        $this->assertSame('KC NGAWI', $mantri[0]['cabang']);
        $this->assertSame('UNIT B', $mantri[0]['unit']);
        $this->assertSame(1, $mantri[0]['realisasi_deb']);
        $this->assertSame(300_000_000.0, $mantri[0]['realisasi_os']);
        $this->assertSame('000111', $calculator->kurRm('2026-09-19')[0]['rm']);
    }

    public function test_mantri_does_not_deduct_an_open_baseline_account_after_cif_or_branch_transfer(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->row('000OLD', 60_000_000, ['periode' => '2026-08-31']),
            $this->row('OLD', 55_000_000, [
                'tgl_realisasi' => '2026-08-01', 'cifno' => 'CIF-TRANSFERRED',
                'cabang_normalized' => 'KC PONOROGO', 'segmen_kinerja' => 'CONSUMER',
            ]),
            $this->row('NEW', 100_000_000),
        ]);

        $mantri = app(MicroNettDisbursementCalculator::class)->mantri('2026-09-19');

        $this->assertCount(1, $mantri);
        $this->assertSame(100_000_000.0, $mantri[0]['realisasi_os']);
        $this->assertSame(1, $mantri[0]['realisasi_deb']);
    }

    public function test_blank_mantri_manager_does_not_inherit_a_valid_kur_initiator(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->row('NEW', 100_000_000, ['pn_pengelola1' => '', 'pn_pemrakarsa1' => '000123']),
        ]);

        $calculator = app(MicroNettDisbursementCalculator::class);

        $this->assertSame('RM LAIN', $calculator->mantri('2026-09-19')[0]['owner']);
        $this->assertSame('000123', $calculator->kurRm('2026-09-19')[0]['rm']);
    }

    public function test_mantri_deducts_closed_cif_exposure_from_other_segments(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->row('OLD', 60_000_000, [
                'periode' => '2026-08-31', 'segmen_kinerja' => 'SMALL', 'produk_kinerja' => 'KOMERSIAL',
            ]),
            $this->row('NEW', 100_000_000),
        ]);

        $mantri = app(MicroNettDisbursementCalculator::class)->mantri('2026-09-19');

        $this->assertSame(40_000_000.0, $mantri[0]['realisasi_os']);
        $this->assertSame(1, $mantri[0]['realisasi_deb']);
    }

    public function test_mantri_deducts_closed_briguna_baseline_without_counting_new_briguna_bookings(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->row('OLD', 25_000_000, ['periode' => '2026-08-31', 'produk_kinerja' => 'BRIGUNAMIKRO']),
            $this->row('BRIGUNA-NEW', 900_000_000, ['produk_kinerja' => 'BRIGUNAMIKRO', 'cifno' => 'CIF-B']),
            $this->row('NEW', 100_000_000),
        ]);

        $mantri = app(MicroNettDisbursementCalculator::class)->mantri('2026-09-19');

        $this->assertSame(75_000_000.0, $mantri[0]['realisasi_os']);
        $this->assertSame(1, $mantri[0]['realisasi_deb']);
    }

    private function row(string $account, float $amount, array $overrides = []): array
    {
        return array_merge([
            'periode' => '2026-09-19',
            'tgl_realisasi' => '2026-09-05',
            'cabang_normalized' => 'KC MADIUN',
            'cabang1' => 'KC Madiun',
            'unit_normalized' => 'UNIT A',
            'unit1' => 'Unit A',
            'branch_normalized' => '45',
            'branch1' => '45',
            'pn_pemrakarsa1' => '000123',
            'pn_pengelola1' => $overrides['pn_pemrakarsa1'] ?? '000123',
            'nomor_rekening1' => $account,
            'cifno' => 'CIF-A',
            'segmen_kinerja' => 'MICRO',
            'produk_kinerja' => 'KURKECIL',
            'description' => 'Kredit Mikro - KUR Ritel 2015',
            'plafon' => $amount,
            'baki_debet1' => $amount,
        ], $overrides);
    }
}
