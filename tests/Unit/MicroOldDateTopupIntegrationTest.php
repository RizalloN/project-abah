<?php

namespace Tests\Unit;

use App\Support\Lw321DailyLoanSyncService;
use App\Support\MantriSourceInputStore;
use App\Support\MicroNettDisbursementCalculator;
use App\Support\ReportCacheVersion;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MicroOldDateTopupIntegrationTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        Config::set('cache.default', 'array');
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Cache::flush();
        $this->directory = storage_path('framework/testing/micro-old-date-integration-'.bin2hex(random_bytes(8)));
        app()->instance(MantriSourceInputStore::class, new MantriSourceInputStore($this->directory));
        Schema::create('daily_loan_dinamis', function (Blueprint $table): void {
            $table->id();
            $table->string('uniqueid_namareport')->unique();
            foreach ([
                'periode', 'tgl_realisasi', 'cifno', 'cifno_clean', 'nomor_rekening1',
                'cabang1', 'kode_cabang1', 'cabang_normalized', 'unit1', 'unit_normalized',
                'branch1', 'branch_normalized', 'segmen_kinerja', 'produk_dashboard',
                'produk_kinerja', 'description', 'ln_type', 'nama_debitur1', 'jangka_waktu1',
                'pn_pemutus1', 'pn_pemutus_normalized', 'pn_pengelola1', 'pn_pemrakarsa1',
                'pn_name1', 'rm_normalized', 'kolek_detail', 'flag_restruk',
                'next_pmt_date', 'next_pmt_int_date', 'kol_adk1', 'segmen_dashboard',
                'pn_referral1', 'status_rekening1', 'npl_method',
            ] as $column) {
                $table->string($column)->nullable();
            }
            foreach (['freq_payment', 'umur_tunggakan', 'kolek'] as $column) {
                $table->integer($column)->nullable();
            }
            $table->decimal('plafon', 20, 2)->nullable();
            $table->decimal('baki_debet1', 20, 2)->nullable();
        });
        Schema::create('lw321pn', function (Blueprint $table): void {
            foreach ([
                'uniqueid_namareport', 'periode', 'no_rekening', 'description', 'cifno',
                'next_pmt_date', 'next_int_pmt_date', 'kol_adk', 'pn_referral', 'flag_restruk',
                'tgl_realisasi', 'kanca', 'uker', 'kode_uker', 'pn_pengelola_singlepn', 'pn_pemrakarsa',
            ] as $column) {
                $table->string($column)->nullable();
            }
            $table->decimal('balance_dalam_idr', 20, 2)->nullable();
            $table->decimal('plafon', 20, 2)->nullable();
        });
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    public static function sources(): array
    {
        return ['Native Daily Loan' => [false], 'LW321PN' => [true]];
    }

    #[DataProvider('sources')]
    public function test_only_proven_raises_create_cif_nett_for_mantri_and_rm_with_canonical_account_identity(bool $lw): void
    {
        $this->importPosition('2026-11-30', [
            $this->loan('2026-11-30', '000101', false, 40_000_000, ['baki_debet1' => 30_000_000]),
            $this->loan('2026-11-30', '000201', true, 40_000_000, ['baki_debet1' => 30_000_000]),
            $this->loan('2026-11-30', '102', false, 50_000_000),
            $this->loan('2026-11-30', '103', false, 40_000_000),
        ], $lw);
        $this->importPosition('2026-12-07', [
            $this->loan('2026-12-07', '101', false, 50_000_000),
            $this->loan('2026-12-07', '201', true, 50_000_000),
            $this->loan('2026-12-07', '000102', false, 50_000_000, ['baki_debet1' => 20_000_000]),
            $this->loan('2026-12-07', '103', false, 50_000_000, ['cifno' => 'OTHER-CIF', 'cifno_clean' => 'OTHER-CIF']),
        ], $lw);
        $calculator = app(MicroNettDisbursementCalculator::class);
        $mantri = $calculator->mantriAccounts('2026-12-07');
        $this->assertCount(1, $mantri);
        $this->assertSame('101', $mantri[0]['account_key']);
        $this->assertSame(30_000_000.0, $mantri[0]['previous_os']);
        $this->assertSame(50_000_000.0, $mantri[0]['plafon']);
        $this->assertSame(20_000_000.0, $mantri[0]['net_amount']);
        $this->assertSame(20_000_000.0, (float) collect($calculator->kurRmNett('2026-12-07'))->sum('realisasi_os'));
        $this->assertSame([], $calculator->kurRm('2026-12-07'));
        $input = app(MantriSourceInputStore::class)->read('2026-12-07');
        $this->assertSame([], $input['bookings']);
        $this->assertSame(['101', '201'], array_column($input['same_account_bookings'], 'nomor_rekening1'));
    }

    #[DataProvider('sources')]
    public function test_proven_suplesi_survives_retention_of_both_baseline_and_observed_position(bool $lw): void
    {
        $baseline = [];
        $current = [];
        foreach (['101' => false, '201' => true] as $account => $rm) {
            $baseline[] = $this->loan('2026-11-30', (string) $account, $rm, 40_000_000, ['baki_debet1' => 30_000_000]);
            $current[] = $this->loan('2026-12-07', (string) $account, $rm, 50_000_000);
        }
        $this->importPosition('2026-11-30', $baseline, $lw);
        foreach (['daily_loan_dinamis', 'lw321pn'] as $table) {
            DB::table($table)->where('periode', '2026-11-30')->delete();
        }
        ReportCacheVersion::bump('pinjaman');
        $this->importPosition('2026-12-07', $current, $lw);
        foreach (['daily_loan_dinamis', 'lw321pn'] as $table) {
            DB::table($table)->where('periode', '2026-12-07')->delete();
        }
        ReportCacheVersion::bump('pinjaman');
        $calculator = app(MicroNettDisbursementCalculator::class);
        $mantri = $calculator->mantriAccounts('2026-12-07');
        $this->assertCount(1, $mantri);
        $this->assertSame(30_000_000.0, $mantri[0]['previous_os']);
        $this->assertSame(20_000_000.0, $mantri[0]['net_amount']);
        $this->assertSame(20_000_000.0, (float) collect($calculator->kurRmNett('2026-12-07'))->sum('realisasi_os'));
        $this->assertSame('2026-11-05', $mantri[0]['source_realization_date']);
    }

    #[DataProvider('sources')]
    public function test_ordinary_booking_supersedes_older_detected_raise_for_the_same_account(bool $lw): void
    {
        $baseline = [];
        $first = [];
        $regular = [];
        foreach (['101' => false, '201' => true] as $account => $rm) {
            $baseline[] = $this->loan('2026-11-30', (string) $account, $rm, 40_000_000, ['baki_debet1' => 30_000_000]);
            $first[] = $this->loan('2026-12-07', (string) $account, $rm, 50_000_000);
            $regular[] = $this->loan('2026-12-08', '000'.$account, $rm, 60_000_000, ['tgl_realisasi' => '2026-12-08']);
        }
        $this->importPosition('2026-11-30', $baseline, $lw);
        $this->importPosition('2026-12-07', $first, $lw);
        $this->importPosition('2026-12-08', $regular, $lw);
        $calculator = app(MicroNettDisbursementCalculator::class);
        $rows = $calculator->mantriAccounts('2026-12-08');
        $this->assertCount(1, $rows);
        $this->assertSame(60_000_000.0, $rows[0]['plafon']);
        $this->assertSame(30_000_000.0, $rows[0]['net_amount']);
        $this->assertSame('2026-12-08', $rows[0]['realization_date']);
        $this->assertArrayNotHasKey('booking_kind', $rows[0]);
        $this->assertSame(30_000_000.0, (float) collect($calculator->kurRmNett('2026-12-08'))->sum('realisasi_os'));
        $this->assertSame(1, (int) collect($calculator->kurRmNett('2026-12-08'))->sum('realisasi_deb'));
    }

    #[DataProvider('sources')]
    public function test_equal_limit_observations_preserve_first_booking_date_and_latest_owner_placement(bool $lw): void
    {
        $baseline = [];
        $first = [];
        $corrected = [];
        foreach (['101' => false, '201' => true] as $account => $rm) {
            $baseline[] = $this->loan('2026-11-30', (string) $account, $rm, 40_000_000, ['baki_debet1' => 30_000_000]);
            $first[] = $this->loan('2026-12-07', (string) $account, $rm, 50_000_000);
            $corrected[] = $this->loan('2026-12-08', (string) $account, $rm, 50_000_000, [
                'pn_pemrakarsa1' => '00000803 - CORRECTED INITIATOR',
                'pn_pengelola1' => '00000804 - CORRECTED MANAGER',
                'unit1' => 'UNIT CORRECTED', 'unit_normalized' => 'UNIT CORRECTED',
            ]);
        }
        $this->importPosition('2026-11-30', $baseline, $lw);
        $this->importPosition('2026-12-07', $first, $lw);
        $this->importPosition('2026-12-08', $corrected, $lw);
        $calculator = app(MicroNettDisbursementCalculator::class);
        $rows = $calculator->mantriAccounts('2026-12-08');
        $this->assertCount(1, $rows);
        $this->assertSame('2026-12-07', $rows[0]['realization_date']);
        $this->assertSame('00000803 - CORRECTED INITIATOR', $rows[0]['owner']);
        $this->assertSame('UNIT CORRECTED', $rows[0]['unit']);
        $this->assertSame(20_000_000.0, $rows[0]['net_amount']);
        $rm = $calculator->kurRmNett('2026-12-08');
        $this->assertCount(1, $rm);
        $this->assertSame('00000804 - CORRECTED MANAGER', $rm[0]['rm']);
        $this->assertSame('UNIT CORRECTED', $rm[0]['unit']);
        $this->assertSame(20_000_000.0, (float) $rm[0]['realisasi_os']);
    }

    private function importPosition(string $period, array $rows, bool $lw): void
    {
        if ($lw) {
            foreach ($rows as $row) {
                DB::table('lw321pn')->insert([
                    'uniqueid_namareport' => 'RAW:'.$period.':'.$row['nomor_rekening1'], 'periode' => $period,
                    'no_rekening' => $row['nomor_rekening1'], 'cifno' => $row['cifno'],
                    'description' => $row['description'], 'kanca' => $row['cabang1'], 'uker' => $row['unit1'], 'kode_uker' => $row['branch1'],
                    'tgl_realisasi' => $row['tgl_realisasi'], 'balance_dalam_idr' => $row['baki_debet1'], 'plafon' => $row['plafon'],
                    'pn_pengelola_singlepn' => $row['pn_pengelola1'], 'pn_pemrakarsa' => $row['pn_pemrakarsa1'],
                    'next_pmt_date' => '2027-01-01',
                ]);
            }
            app(Lw321DailyLoanSyncService::class)->synchronize($period);
        } else {
            DB::table('daily_loan_dinamis')->insert($rows);
            app(MantriSourceInputStore::class)->captureDailyLoanPeriod($period);
        }
        ReportCacheVersion::bump('pinjaman');
    }

    private function loan(string $period, string $account, bool $rm, float $limit, array $overrides = []): array
    {
        $canonical = ltrim($account, '0') ?: '0';

        return array_replace([
            'uniqueid_namareport' => 'NATIVE:'.$period.':'.$account, 'periode' => $period,
            'tgl_realisasi' => '2026-11-05', 'nomor_rekening1' => $account, 'cifno' => 'CIF-'.$canonical, 'cifno_clean' => 'CIF-'.$canonical,
            'cabang1' => 'KC MADIUN', 'cabang_normalized' => 'KC MADIUN', 'kode_cabang1' => '45',
            'unit1' => 'UNIT UJI', 'unit_normalized' => 'UNIT UJI', 'branch1' => '45', 'branch_normalized' => '45',
            'segmen_kinerja' => 'MICRO', 'segmen_dashboard' => 'MICRO', 'produk_kinerja' => $rm ? 'KURMIKRO' : 'KUPEDES',
            'produk_dashboard' => $rm ? 'KUR Mikro' : 'Kupedes',
            'description' => $rm ? 'Kredit Mikro - KUR Ritel 2015' : 'KREDIT MIKRO - KUPEDES',
            'plafon' => $limit, 'baki_debet1' => $limit,
            'pn_pemrakarsa1' => '00000801 - INITIATOR', 'pn_pengelola1' => '00000802 - CURRENT MANAGER',
            'rm_normalized' => '00000802', 'kolek' => 1, 'ln_type' => 'BULANAN', 'freq_payment' => 1,
        ], $overrides);
    }
}
