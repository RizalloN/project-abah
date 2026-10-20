<?php

namespace Tests\Unit;

use App\Support\MantriSourceInputStore;
use App\Support\MicroNettDisbursementCalculator;
use App\Support\ReportCacheVersion;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MicroNettDisbursementCalculatorTest extends TestCase
{
    private ?string $cohortDirectory = null;

    protected function tearDown(): void
    {
        if ($this->cohortDirectory !== null) {
            File::deleteDirectory($this->cohortDirectory);
        }
        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        Config::set('cache.default', 'array');
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
            $this->row('OLD', 100_000_000, ['periode' => '2026-08-31']),
            $this->row('NEW', 300_000_000),
            $this->row(' new ', 250_000_000),
            $this->kurRow('KUR', 300_000_000),
            $this->kurRow(' kur ', 250_000_000),
        ]);

        $calculator = app(MicroNettDisbursementCalculator::class);
        $mantri = $calculator->mantri('2026-09-19');
        $this->assertCount(1, $mantri);
        $this->assertSame(1, $mantri[0]['realisasi_deb']);
        $this->assertSame(200_000_000.0, $mantri[0]['realisasi_os']);
        $kur = $calculator->kurRm('2026-09-19')[0];
        $this->assertSame(300_000_000.0, $kur['realisasi_os']);
        $this->assertSame(300_000_000.0, $kur['w1_realisasi_os']);
        $this->assertSame(1, $kur['gt_250_realisasi_deb']);
        $this->assertSame(300_000_000.0, $kur['gt_250_realisasi_os']);
    }

    public function test_cached_reads_match_fresh_reads_and_refresh_on_new_data_version(): void
    {
        Cache::flush();
        $calculator = app(MicroNettDisbursementCalculator::class);
        $this->assertSame([], $calculator->cachedMantri('2026-09-19'));
        $this->assertSame([], $calculator->cachedKurRm('2026-09-19'));
        DB::table('daily_loan_dinamis')->insert([
            $this->row('MANTRI-A', 300_000_000), $this->kurRow('KUR-A', 300_000_000),
        ]);
        $this->assertSame($calculator->mantri('2026-09-19'), $calculator->cachedMantri('2026-09-19'));
        $this->assertSame($calculator->kurRm('2026-09-19'), $calculator->cachedKurRm('2026-09-19'));

        DB::table('daily_loan_dinamis')->insert([
            $this->row('MANTRI-B', 100_000_000), $this->kurRow('KUR-B', 100_000_000),
        ]);
        $this->assertSame(1, $calculator->cachedMantri('2026-09-19')[0]['realisasi_deb']);
        $this->assertSame(1, $calculator->cachedKurRm('2026-09-19')[0]['realisasi_deb']);
        ReportCacheVersion::bump('pinjaman');
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

        $result = app(MicroNettDisbursementCalculator::class)->mantri('2026-09-19');
        $this->assertSame(1, $result[0]['realisasi_deb']);
        $this->assertSame(150_000_000.0, $result[0]['realisasi_os']);
    }

    public function test_previous_rows_without_account_identity_cannot_prove_closure(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->row('', 40_000_000, ['periode' => '2026-08-31']),
            $this->row('   ', 30_000_000, ['periode' => '2026-08-31']),
            $this->row('OLD', 100_000_000, ['periode' => '2026-08-31']),
            $this->row(' old ', 80_000_000, ['periode' => '2026-08-31']),
            $this->row('NEW', 300_000_000),
        ]);

        $result = app(MicroNettDisbursementCalculator::class)->mantri('2026-09-19');
        $this->assertSame(1, $result[0]['realisasi_deb']);
        $this->assertSame(200_000_000.0, $result[0]['realisasi_os']);
    }

    public function test_mantri_floors_negative_nett_while_kur_uses_gross_and_keeps_250_million_boundary(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->row('OLD', 300_000_000, ['periode' => '2026-08-31']),
            $this->row('MANTRI', 250_000_000, ['pn_pemrakarsa1' => '', 'pn_pengelola1' => '']),
            $this->kurRow('KUR', 250_000_000, ['pn_pengelola1' => '']),
            $this->row('FUTURE', 900_000_000, ['tgl_realisasi' => '2026-09-20']),
        ]);

        $calculator = app(MicroNettDisbursementCalculator::class);
        $kur = $calculator->kurRm('2026-09-19')[0];
        $mantri = $calculator->mantri('2026-09-19')[0];
        $this->assertSame('RM LAIN', $kur['rm']);
        $this->assertSame('RM LAIN', $mantri['owner']);
        $this->assertSame(250_000_000.0, $kur['realisasi_os']);
        $this->assertSame(0.0, $mantri['realisasi_os']);
        $this->assertSame(1, $kur['realisasi_deb']);
        $this->assertSame(1, $mantri['realisasi_deb']);
        $this->assertSame(0, $kur['lt_250_realisasi_deb']);
        $this->assertSame(0, $kur['gt_250_realisasi_deb']);
        $account = collect($calculator->mantriAccounts('2026-09-19'))->keyBy('account_key')['MANTRI'];
        $this->assertSame(300_000_000.0, $account['previous_os']);
        $this->assertSame(250_000_000.0, $account['plafon']);
        $this->assertSame(0.0, $account['net_amount']);
    }

    public function test_each_mantri_booking_subtracts_complete_micro_cif_exposure_and_ignores_small(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->row('OLD-MICRO', 100_000_000, ['periode' => '2026-08-31']),
            $this->row('OLD-SMALL', 50_000_000, ['periode' => '2026-08-31', 'segmen_kinerja' => 'SMALL', 'produk_kinerja' => 'COMMERCIAL']),
            $this->row('NEW-A', 300_000_000),
            $this->row('NEW-B', 200_000_000),
        ]);

        $calculator = app(MicroNettDisbursementCalculator::class);
        $mantri = $calculator->mantri('2026-09-19')[0];
        $this->assertSame(2, $mantri['realisasi_deb']);
        $this->assertSame(300_000_000.0, $mantri['realisasi_os']);
        $accounts = collect($calculator->mantriAccounts('2026-09-19'))->keyBy('account_key');
        $this->assertSame(100_000_000.0, $accounts['NEW-A']['previous_os']);
        $this->assertSame(200_000_000.0, $accounts['NEW-A']['net_amount']);
        $this->assertSame(100_000_000.0, $accounts['NEW-B']['previous_os']);
        $this->assertSame(100_000_000.0, $accounts['NEW-B']['net_amount']);
    }

    public function test_mantri_retains_disappeared_bookings_while_kur_uses_only_exact_position(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->row('DISAPPEARED', 500_000_000, ['periode' => '2026-09-05']),
            $this->row('00012345', 250_000_000, ['periode' => '2026-09-06']),
            $this->row('12345', 225_000_000),
            $this->kurRow('KUR-OLD', 900_000_000, ['periode' => '2026-09-06']),
            $this->kurRow('KUR-CURRENT', 200_000_000),
        ]);

        $this->cohortDirectory = storage_path('framework/testing/mantri-nett-cohort-'.bin2hex(random_bytes(8)));
        $store = new MantriSourceInputStore($this->cohortDirectory);
        $this->app->instance(MantriSourceInputStore::class, $store);
        $store->write('2026-09-05', [
            'source' => ['source_kind' => 'audited_complete_workbook_position'],
            'complete_presence' => true, 'presence' => ['DISAPPEARED'],
            'bookings' => [$this->row('DISAPPEARED', 500_000_000, ['periode' => '2026-09-05'])],
        ]);
        $calculator = app(MicroNettDisbursementCalculator::class);
        $this->assertSame(725_000_000.0, $calculator->mantri('2026-09-19')[0]['realisasi_os']);
        $this->assertSame(2, $calculator->mantri('2026-09-19')[0]['realisasi_deb']);
        $this->assertSame(200_000_000.0, $calculator->kurRm('2026-09-19')[0]['realisasi_os']);
        $this->assertSame(1, $calculator->kurRm('2026-09-19')[0]['realisasi_deb']);
    }

    public function test_current_position_uses_initiator_for_mantri_and_singlepn_for_kur(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->row('MANTRI', 200_000_000, ['periode' => '2026-09-05', 'pn_pemrakarsa1' => '000999']),
            $this->row('MANTRI', 300_000_000, ['pn_pemrakarsa1' => '000111', 'pn_pengelola1' => '000222']),
            $this->kurRow('KUR', 300_000_000, ['pn_pemrakarsa1' => '000333', 'pn_pengelola1' => '000444']),
        ]);

        $calculator = app(MicroNettDisbursementCalculator::class);
        $this->assertSame('00000111', $calculator->mantri('2026-09-19')[0]['owner']);
        $this->assertSame('000444', $calculator->kurRm('2026-09-19')[0]['rm']);
    }

    public function test_zero_padded_previous_account_is_not_double_counted(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->row('000999', 100_000_000, ['periode' => '2026-08-31']),
            $this->row('999', 80_000_000, ['periode' => '2026-08-31']),
            $this->row('NEW', 300_000_000),
        ]);

        $result = app(MicroNettDisbursementCalculator::class)->mantri('2026-09-19');
        $this->assertSame(200_000_000.0, $result[0]['realisasi_os']);
    }

    public function test_mantri_floors_each_booking_before_aggregating_same_cif(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->row('OLD', 300_000_000, ['periode' => '2026-08-31']),
            $this->row('NEW-A', 250_000_000),
            $this->row('NEW-B', 100_000_000),
            $this->row('NEW-C', 350_000_000),
        ]);

        $calculator = app(MicroNettDisbursementCalculator::class);
        $this->assertSame(50_000_000.0, $calculator->mantri('2026-09-19')[0]['realisasi_os']);
        $this->assertSame(3, $calculator->mantri('2026-09-19')[0]['realisasi_deb']);
        $accounts = collect($calculator->mantriAccounts('2026-09-19'))->keyBy('account_key');
        $this->assertSame(300_000_000.0, $accounts['NEW-A']['previous_os']);
        $this->assertSame(0.0, $accounts['NEW-A']['net_amount']);
        $this->assertSame(300_000_000.0, $accounts['NEW-B']['previous_os']);
        $this->assertSame(0.0, $accounts['NEW-B']['net_amount']);
        $this->assertSame(50_000_000.0, $accounts['NEW-C']['net_amount']);
    }

    public function test_kur_uses_only_official_kur_ritel_description(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->kurRow('OFFICIAL', 200_000_000),
            $this->kurRow('SHADOW', 900_000_000, ['description' => 'KUR Ritel Shadow Classification']),
        ]);

        $result = app(MicroNettDisbursementCalculator::class)->kurRm('2026-09-19');
        $this->assertCount(1, $result);
        $this->assertSame(1, $result[0]['realisasi_deb']);
        $this->assertSame(200_000_000.0, $result[0]['realisasi_os']);
    }

    public function test_mantri_keeps_blank_or_invalid_initiator_unassigned_despite_valid_manager(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->row('BLANK', 100_000_000, ['pn_pemrakarsa1' => '', 'pn_pengelola1' => '000777']),
            $this->row('INVALID', 200_000_000, ['pn_pemrakarsa1' => ' - ', 'pn_pengelola1' => '000777']),
        ]);

        $mantri = app(MicroNettDisbursementCalculator::class)->mantri('2026-09-19');
        $this->assertCount(1, $mantri);
        $this->assertSame('RM LAIN', $mantri[0]['owner']);
        $this->assertSame(2, $mantri[0]['realisasi_deb']);
        $this->assertSame(300_000_000.0, $mantri[0]['realisasi_os']);
    }

    public function test_kur_manager_and_mantri_initiator_remain_distinct(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->row('MANTRI', 300_000_000, ['pn_pemrakarsa1' => '000111', 'pn_pengelola1' => '000222']),
            $this->kurRow('KUR', 300_000_000, ['pn_pemrakarsa1' => '000111', 'pn_pengelola1' => '000222']),
        ]);

        $calculator = app(MicroNettDisbursementCalculator::class);
        $this->assertSame('00000111', $calculator->mantri('2026-09-19')[0]['owner']);
        $this->assertSame('000222', $calculator->kurRm('2026-09-19')[0]['rm']);
    }

    public function test_current_position_updates_location_without_reusing_earlier_ownership(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->row('00012345', 400_000_000, ['periode' => '2026-09-05', 'pn_pemrakarsa1' => '000111']),
            $this->row('12345', 300_000_000, [
                'pn_pemrakarsa1' => '000333', 'pn_pengelola1' => '000888',
                'cabang_normalized' => 'KC NGAWI', 'cabang1' => 'KC Ngawi',
                'unit_normalized' => 'UNIT B', 'unit1' => 'Unit B',
            ]),
        ]);

        $mantri = app(MicroNettDisbursementCalculator::class)->mantri('2026-09-19');
        $this->assertCount(1, $mantri);
        $this->assertSame('00000333', $mantri[0]['owner']);
        $this->assertSame('KC NGAWI', $mantri[0]['cabang']);
        $this->assertSame('UNIT B', $mantri[0]['unit']);
        $this->assertSame(1, $mantri[0]['realisasi_deb']);
        $this->assertSame(300_000_000.0, $mantri[0]['realisasi_os']);
    }

    public function test_mantri_does_not_deduct_prior_micro_account_still_present_after_transfer(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->row('0009876', 60_000_000, ['periode' => '2026-08-31']),
            $this->row('9876', 55_000_000, [
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

    public function test_mantri_uses_initiator_while_kur_keeps_blank_manager_unassigned(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->row('MANTRI', 100_000_000, ['pn_pengelola1' => '', 'pn_pemrakarsa1' => '000123']),
            $this->kurRow('KUR', 100_000_000, ['pn_pengelola1' => '', 'pn_pemrakarsa1' => '000123']),
        ]);

        $calculator = app(MicroNettDisbursementCalculator::class);
        $this->assertSame('00000123', $calculator->mantri('2026-09-19')[0]['owner']);
        $this->assertSame('RM LAIN', $calculator->kurRm('2026-09-19')[0]['rm']);
    }

    public function test_mantri_ignores_prior_cif_exposure_from_other_segments(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->row('OLD', 60_000_000, [
                'periode' => '2026-08-31', 'segmen_kinerja' => 'SMALL', 'produk_kinerja' => 'KOMERSIAL',
            ]),
            $this->row('NEW', 100_000_000),
        ]);

        $mantri = app(MicroNettDisbursementCalculator::class)->mantri('2026-09-19');

        $this->assertSame(100_000_000.0, $mantri[0]['realisasi_os']);
        $this->assertSame(1, $mantri[0]['realisasi_deb']);
    }

    public function test_mantri_deducts_briguna_micro_baseline_without_counting_new_briguna_bookings(): void
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

    private function kurRow(string $account, float $amount, array $overrides = []): array
    {
        return $this->row($account, $amount, array_merge([
            'produk_kinerja' => 'KURKECIL', 'description' => 'Kredit Mikro - KUR Ritel 2015',
        ], $overrides));
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
            'pn_pengelola1' => '000123',
            'nomor_rekening1' => $account,
            'cifno' => 'CIF-A',
            'segmen_kinerja' => 'MICRO',
            'produk_kinerja' => 'KUPEDES',
            'description' => 'KREDIT MIKRO - KUPEDES',
            'plafon' => $amount,
            'baki_debet1' => $amount,
        ], $overrides);
    }
}
