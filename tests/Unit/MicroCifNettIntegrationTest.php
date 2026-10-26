<?php

namespace Tests\Unit;

use App\Support\Lw321DailyLoanSyncService;
use App\Support\MantriSourceInputStore;
use App\Support\MicroNettDisbursementCalculator;
use App\Support\ReportCacheVersion;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MicroCifNettIntegrationTest extends TestCase
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
        $this->directory = storage_path('framework/testing/micro-cif-nett-'.bin2hex(random_bytes(8)));
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

    public static function importedPeriodsAndAccountKinds(): array
    {
        $cases = [];
        foreach (['Native Daily Loan' => false, 'LW321PN' => true] as $source => $lw) {
            foreach (['2026-12-07', '2027-02-04'] as $period) {
                foreach (['same account' => true, 'replacement account' => false] as $kind => $same) {
                    $cases[$source.' '.$period.' '.$kind] = [$lw, $period, $same];
                }
            }
        }

        return $cases;
    }

    #[DataProvider('importedPeriodsAndAccountKinds')]
    public function test_imported_cif_nett_handles_downsizing_growth_equal_and_split_bookings_without_changing_rm_gross(bool $lw, string $period, bool $same): void
    {
        $previous = Carbon::parse($period)->startOfMonth()->subDay()->toDateString();
        $bookingDate = Carbon::parse($period)->subDays(2)->toDateString();
        $oldDate = Carbon::parse($previous)->startOfMonth()->addDay()->toDateString();
        $baseline = [];
        $current = [];
        foreach (['MANTRI' => 10000, 'RM' => 20000] as $segment => $start) {
            foreach (['DOWNSIZE' => [25, 10], 'GROWTH' => [30, 50], 'EQUAL' => [30, 30]] as $index => [$prior, $gross]) {
                $sequence = array_search($index, ['DOWNSIZE', 'GROWTH', 'EQUAL'], true) + 1;
                $account = (string) ($start + $sequence);
                $cif = $segment.'-'.$index;
                $baseline[] = $this->loan($previous, '000'.$account, $prior * 1_000_000, $oldDate, strtolower($cif), $segment);
                $current[] = $this->loan($period, $same ? $account : (string) ($start + $sequence + 10), $gross * 1_000_000, $bookingDate, ' '.$cif.' ', $segment);
            }
            $baseline[] = $this->loan($previous, (string) ($start + 4), 30_000_000, $oldDate, $segment.'-SPLIT', $segment);
            $current[] = $this->loan($period, (string) ($start + 14), 20_000_000, $bookingDate, $segment.'-SPLIT', $segment);
            $current[] = $this->loan($period, (string) ($start + 15), 20_000_000, $bookingDate, $segment.'-SPLIT', $segment, [
                'pn_pemrakarsa1' => '00000803 - OTHER INITIATOR', 'pn_pengelola1' => '00000804 - OTHER MANAGER',
            ]);
            // An unrelated old facility remains open and is not refinanced by the new booking.
            $baseline[] = $this->loan($previous, (string) ($start + 5), 70_000_000, $oldDate, $segment.'-OPEN', $segment);
            $current[] = $this->loan($period, '000'.($start + 5), 60_000_000, $oldDate, $segment.'-OPEN', $segment);
            $current[] = $this->loan($period, (string) ($start + 16), 50_000_000, $bookingDate, $segment.'-OPEN', $segment);
        }
        $this->importPosition($previous, $baseline, $lw);
        $this->importPosition($period, $current, $lw);
        $calculator = app(MicroNettDisbursementCalculator::class);
        $accounts = collect($calculator->mantriAccounts($period));
        $this->assertCount(6, $accounts);
        foreach (['DOWNSIZE' => [25, 10], 'GROWTH' => [30, 20], 'EQUAL' => [30, 0], 'SPLIT' => [30, 10], 'OPEN' => [0, 50]] as $case => [$prior, $net]) {
            $rows = $accounts->where('cif_key', 'MANTRI-'.$case);
            $this->assertNotEmpty($rows->all(), $case);
            $this->assertSame($net * 1_000_000.0, (float) $rows->sum('net_amount'), $case);
            $this->assertSame([$prior * 1_000_000.0], $rows->pluck('previous_os')->unique()->values()->all(), $case);
        }
        $split = $accounts->where('cif_key', 'MANTRI-SPLIT');
        $this->assertSame([5_000_000.0, 5_000_000.0], $split->pluck('net_amount')->values()->all());
        foreach ($accounts as $account) {
            $this->assertSame($account['plafon'] - $account['net_amount'], $account['deduction_applied']);
            $this->assertGreaterThanOrEqual(0, $account['net_amount']);
        }
        $this->assertSame(90_000_000.0, (float) $accounts->sum('net_amount'));
        $this->assertSame(180_000_000.0, (float) $accounts->sum('plafon'));
        $mantri = collect($calculator->cachedMantri($period));
        $this->assertSame(90_000_000.0, (float) $mantri->sum('realisasi_os'));
        $this->assertSame(5_000_000.0, (float) $mantri->firstWhere('owner', '00000803 - OTHER INITIATOR')['realisasi_os']);

        $gross = collect($calculator->kurRm($period))->keyBy('rm');
        $nett = collect($calculator->kurRmNett($period))->keyBy('rm');
        $this->assertSame(180_000_000.0, (float) $gross->sum('realisasi_os'));
        $this->assertSame(90_000_000.0, (float) $nett->sum('realisasi_os'));
        $this->assertSame(5_000_000.0, (float) $nett->get('00000804 - OTHER MANAGER')['realisasi_os']);
        $this->assertSame($gross->keys()->all(), $nett->keys()->all());
        foreach ($gross as $owner => $metric) {
            foreach ($metric as $key => $value) {
                if (str_ends_with($key, '_deb')) {
                    $this->assertSame($value, $nett->get($owner)[$key], $owner.' '.$key);
                }
            }
        }
        $this->assertSame(6, (int) $gross->sum('realisasi_deb'));
        $this->assertSame(6, (int) $nett->sum('realisasi_deb'));
        $this->assertSame($calculator->kurRmNett($period), $calculator->cachedKurRmNett($period));
        $this->assertSame($calculator->mantriAccounts($period), $calculator->cachedMantriAccounts($period));
        $input = app(MantriSourceInputStore::class)->read($period);
        $this->assertTrue($input['complete_presence']);
        $this->assertSame($lw ? 'lw321_import' : 'daily_loan_import', $input['source']['source_kind']);
    }

    #[DataProvider('importedPeriodsAndAccountKinds')]
    public function test_closed_month_archive_preserves_same_and_replacement_cif_deduction_after_source_retention(bool $lw, string $period, bool $same): void
    {
        $previous = Carbon::parse($period)->startOfMonth()->subDay()->toDateString();
        $oldDate = Carbon::parse($previous)->startOfMonth()->toDateString();
        $bookingDate = Carbon::parse($period)->subDay()->toDateString();
        $baseline = [];
        $current = [];
        foreach (['MANTRI' => '30001', 'RM' => '40001'] as $segment => $account) {
            $baseline[] = $this->loan($previous, '000'.$account, 30_000_000, $oldDate, $segment.'-RETAINED', $segment);
            $current[] = $this->loan($period, $same ? $account : $account.'9', 50_000_000, $bookingDate, $segment.'-RETAINED', $segment);
        }
        $this->importPosition($previous, $baseline, $lw);
        $this->importPosition($period, $current, $lw);
        $calculator = app(MicroNettDisbursementCalculator::class);
        $before = $calculator->mantriAccounts($period);
        $rmBefore = $calculator->kurRmNett($period);
        $this->assertSame(20_000_000.0, (float) collect($before)->sum('net_amount'));
        $this->assertSame(20_000_000.0, (float) collect($rmBefore)->sum('realisasi_os'));

        DB::table('daily_loan_dinamis')->where('periode', $previous)->delete();
        DB::table('lw321pn')->where('periode', $previous)->delete();
        ReportCacheVersion::bump('pinjaman');

        $after = $calculator->cachedMantriAccounts($period);
        $this->assertSame(30_000_000.0, $after[0]['previous_os']);
        $this->assertSame(20_000_000.0, $after[0]['net_amount']);
        $this->assertSame($rmBefore, $calculator->cachedKurRmNett($period));
        $this->assertSame(50_000_000.0, (float) collect($calculator->kurRm($period))->sum('realisasi_os'));
    }

    public static function projectionPresenceCases(): array
    {
        return [
            'matched unknown presence' => ['matched', 50_000_000.0],
            'native count changed' => ['daily_count', 20_000_000.0],
            'native timestamp changed' => ['daily_time', 20_000_000.0],
            'raw timestamp changed' => ['raw_time', 20_000_000.0],
            'financial content changed without timestamp change' => ['financial', 20_000_000.0],
            'matching metadata without hash' => ['no_hash', 20_000_000.0],
            'partial raw presence' => ['partial', 20_000_000.0],
            'raw classified account cannot override native' => ['classified', 20_000_000.0],
            'missing timestamps' => ['null_time', 20_000_000.0],
            'raw-only account changed after capture' => ['raw_account', 20_000_000.0],
            'raw-only classification changed after capture' => ['raw_classification', 20_000_000.0],
            'unknown account moved outside area remains open' => ['outside', 50_000_000.0],
        ];
    }

    #[DataProvider('projectionPresenceCases')]
    public function test_unknown_raw_presence_requires_matching_complete_financial_projection(string $case, float $expected): void
    {
        foreach (['daily_loan_dinamis', 'lw321pn'] as $table) {
            Schema::table($table, fn (Blueprint $schema) => $schema->timestamp('updated_at')->nullable());
        }
        $period = '2027-02-04';
        $old = $this->loan('2027-01-31', 'OLD', 30_000_000, '2027-01-05', 'CIF-CHECK', 'MANTRI');
        $this->importPosition('2027-01-31', [$old], false);
        $current = $this->loan($period, 'NEW', 50_000_000, $period, 'CIF-CHECK', 'MANTRI', ['updated_at' => '2027-02-04 10:00:00']);
        $this->importPosition($period, [$current], false);
        DB::table('lw321pn')->insert([
            ['uniqueid_namareport' => 'RAW:NEW', 'periode' => $period, 'no_rekening' => 'NEW', 'cifno' => 'CIF-CHECK', 'tgl_realisasi' => $period,
                'description' => 'KREDIT MIKRO - KUPEDES', 'kanca' => 'KC MADIUN', 'uker' => 'UNIT UJI', 'kode_uker' => '45',
                'balance_dalam_idr' => 50_000_000, 'plafon' => 50_000_000, 'pn_pemrakarsa' => '00000803 - RAW OWNER', 'updated_at' => '2027-02-04 09:00:00'],
            ['uniqueid_namareport' => 'RAW:OLD', 'periode' => $period, 'no_rekening' => 'OLD', 'cifno' => 'CIF-CHECK', 'tgl_realisasi' => '2027-01-05',
                'description' => $case === 'classified' ? 'KREDIT MIKRO - KUPEDES' : null, 'kanca' => 'KC MADIUN', 'uker' => 'UNIT UJI', 'kode_uker' => '45',
                'balance_dalam_idr' => 25_000_000, 'plafon' => 30_000_000, 'pn_pemrakarsa' => null, 'updated_at' => '2027-02-04 09:00:00'],
        ]);
        if ($case === 'outside') {
            DB::table('lw321pn')->where('no_rekening', 'OLD')->update(['kanca' => 'KC KEDIRI']);
        }
        $store = app(MantriSourceInputStore::class);
        $input = $store->captureLw321Period($period);
        if ($case === 'daily_count') {
            DB::table('daily_loan_dinamis')->insert($this->loan($period, 'ANCHOR', 1, '2027-01-05', 'OTHER', 'MANTRI', ['updated_at' => '2027-02-04 10:00:00']));
        } elseif ($case === 'daily_time' || $case === 'null_time') {
            DB::table('daily_loan_dinamis')->where('periode', $period)->update(['updated_at' => $case === 'null_time' ? null : '2027-02-04 11:00:00']);
        } elseif ($case === 'raw_time') {
            DB::table('lw321pn')->where('periode', $period)->update(['updated_at' => '2027-02-04 11:00:00']);
        } elseif ($case === 'financial') {
            DB::table('lw321pn')->where('no_rekening', 'NEW')->update(['balance_dalam_idr' => 49_000_000]);
        } elseif ($case === 'raw_account') {
            DB::table('lw321pn')->where('no_rekening', 'OLD')->update(['no_rekening' => 'REPLACED-UNKNOWN']);
        } elseif ($case === 'raw_classification') {
            DB::table('lw321pn')->where('no_rekening', 'OLD')->update(['description' => 'KREDIT MIKRO - KUPEDES']);
        } elseif ($case === 'no_hash' || $case === 'partial') {
            $input['source']['raw_sha256'] = $case === 'no_hash' ? null : $input['source']['raw_sha256'];
            $input['complete_presence'] = $case !== 'partial';
            $store->write($period, $input);
        }
        ReportCacheVersion::bump('pinjaman');
        $rows = app(MicroNettDisbursementCalculator::class)->mantriAccounts($period);
        $this->assertCount(1, $rows);
        $this->assertSame('NEW', $rows[0]['account_key']);
        $this->assertSame(50_000_000.0, $rows[0]['plafon']);
        $this->assertSame('00000801 - INITIATOR', $rows[0]['owner']);
        $this->assertSame($expected, $rows[0]['net_amount']);
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
                    'next_pmt_date' => Carbon::parse($period)->addDay()->toDateString(),
                ]);
            }
            app(Lw321DailyLoanSyncService::class)->synchronize($period);
        } else {
            DB::table('daily_loan_dinamis')->insert($rows);
            app(MantriSourceInputStore::class)->captureDailyLoanPeriod($period);
        }
        ReportCacheVersion::bump('pinjaman');
    }

    private function loan(string $period, string $account, float $amount, string $bookingDate, string $cif, string $segment, array $overrides = []): array
    {
        $kur = $segment === 'RM';

        return array_replace([
            'uniqueid_namareport' => 'NATIVE:'.$period.':'.$account, 'periode' => $period,
            'tgl_realisasi' => $bookingDate, 'nomor_rekening1' => $account, 'cifno' => $cif, 'cifno_clean' => trim(strtoupper($cif)),
            'cabang1' => 'KC MADIUN', 'cabang_normalized' => 'KC MADIUN', 'kode_cabang1' => '45',
            'unit1' => 'UNIT UJI', 'unit_normalized' => 'UNIT UJI', 'branch1' => '45', 'branch_normalized' => '45',
            'segmen_kinerja' => 'MICRO', 'segmen_dashboard' => 'MICRO', 'produk_kinerja' => $kur ? 'KURMIKRO' : 'KUPEDES',
            'produk_dashboard' => $kur ? 'KUR Mikro' : 'Kupedes',
            'description' => $kur ? 'Kredit Mikro - KUR Ritel 2015' : 'KREDIT MIKRO - KUPEDES',
            'plafon' => $amount, 'baki_debet1' => $amount,
            'pn_pemrakarsa1' => '00000801 - INITIATOR', 'pn_pengelola1' => '00000802 - CURRENT MANAGER',
            'rm_normalized' => '00000802', 'kolek' => 1, 'ln_type' => 'BULANAN', 'freq_payment' => 1,
        ], $overrides);
    }
}
