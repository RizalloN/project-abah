<?php

namespace Tests\Unit;

use App\Http\Controllers\Report\KinerjaRmMikroReportController;
use App\Support\LandingMicroPerformanceService;
use App\Support\Lw321DailyLoanSyncService;
use App\Support\MantriSourceInputStore;
use App\Support\MicroNettDisbursementCalculator;
use App\Support\ReportCacheVersion;
use App\Support\ReportDataSyncService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\TestCase;

class MantriRefinedFormulaTest extends TestCase
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
        $this->directory = storage_path('framework/testing/mantri-refined-'.bin2hex(random_bytes(8)));
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
        Schema::create('brihc_pemasar', function (Blueprint $table): void {
            foreach (['uniqueid_namareport', 'pernr', 'pn_mantri', 'nip', 'completename', 'esgdesc', 'positiondesc', 'orgdesc', 'psadesc', 'bc', 'status', 'tmt_masuk', 'tmt_jabatan'] as $column) {
                $table->string($column)->nullable();
            }
        });
        Schema::create('brihc', function (Blueprint $table): void {
            foreach (['pn', 'nama', 'jabatan'] as $column) {
                $table->string($column)->nullable();
            }
        });
        Schema::create('loan_type', function (Blueprint $table): void {
            $table->string('loan_type');
            $table->string('pola_pembayaran')->nullable();
        });
        foreach (['801' => 'PT', '802' => 'KONTRAK'] as $pn => $category) {
            DB::table('brihc_pemasar')->insert([
                'uniqueid_namareport' => 'ROSTER:'.$pn, 'pernr' => str_pad($pn, 8, '0', STR_PAD_LEFT),
                'pn_mantri' => str_pad($pn, 8, '0', STR_PAD_LEFT), 'completename' => 'Mantri '.$pn,
                'esgdesc' => $category, 'positiondesc' => 'MANTRI', 'orgdesc' => 'UNIT UJI',
                'psadesc' => 'KC MADIUN', 'bc' => '45', 'tmt_jabatan' => '2020-01-01',
            ]);
        }
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    public static function importSources(): array
    {
        return ['Native Daily Loan' => [false], 'LW321 import' => [true]];
    }

    public static function futureFloorImports(): array
    {
        return [
            'Native December' => [false, '2026-12-07', 5],
            'LW December' => [true, '2026-12-07', 5],
            'Native following year' => [false, '2027-02-04', 4],
            'LW following year' => [true, '2027-02-04', 4],
        ];
    }

    #[DataProvider('futureFloorImports')]
    public function test_floor_applies_to_each_account_before_same_initiator_totals_tiers_and_hke_in_both_consumers(bool $lw, string $period, int $hke): void
    {
        $previous = Carbon::parse($period)->startOfMonth()->subDay()->toDateString();
        $oldDate = Carbon::parse($previous)->startOfMonth()->addDays(4)->toDateString();
        DB::table('brihc_pemasar')->where('pernr', '00000801')->update(['esgdesc' => 'KONTRAK']);
        $this->importPosition($previous, [
            $this->loan($previous, 'CLOSED-BASELINE', 120_000_000, ['tgl_realisasi' => $oldDate, 'cifno' => 'CIF-REFINANCE', 'cifno_clean' => 'CIF-REFINANCE']),
        ], $lw);
        $this->importPosition($period, [
            $this->loan($period, 'REFINANCED', 100_000_000, ['tgl_realisasi' => $period, 'cifno' => 'CIF-REFINANCE', 'cifno_clean' => 'CIF-REFINANCE']),
            $this->loan($period, 'POSITIVE', 190_000_000, ['tgl_realisasi' => $period, 'cifno' => 'CIF-FRESH', 'cifno_clean' => 'CIF-FRESH']),
        ], $lw);
        // Retention must preserve the full120m baseline while flooring100m-120m at zero.
        DB::table('daily_loan_dinamis')->where('periode', $previous)->delete();
        $calculator = app(MicroNettDisbursementCalculator::class);
        $accounts = collect($calculator->mantriAccounts($period))->keyBy('account_key');
        $this->assertCount(2, $accounts);
        $this->assertSame(120_000_000.0, $accounts['REFINANCED']['previous_os']);
        $this->assertSame(0.0, $accounts['REFINANCED']['net_amount']);
        $this->assertSame(190_000_000.0, $accounts['POSITIVE']['net_amount']);
        $this->assertSame(290_000_000.0, $accounts->sum('plafon'));
        $this->assertSame(190_000_000.0, $accounts->sum('net_amount'));
        $this->assertSame(['00000801 - INITIATOR'], $accounts->pluck('owner')->unique()->values()->all());
        $personMetric = $calculator->mantri($period)[0];
        $this->assertSame(2, $personMetric['realisasi_deb']);
        $this->assertSame(190_000_000.0, $personMetric['realisasi_os']);

        $landing = app(LandingMicroPerformanceService::class)->payload($period);
        $kpi = (new ReflectionMethod(KinerjaRmMikroReportController::class, 'mantriProductivityPayload'))
            ->invoke(new KinerjaRmMikroReportController, $period);
        $this->assertTrue(data_get($landing, 'meta.available'), (string) data_get($landing, 'meta.error'));
        $this->assertSame(190_000_000.0, (float) data_get($landing, 'mantri_performance.total.net_disbursement.amount'));
        $this->assertSame(190_000_000.0, (float) $kpi['total']['realisasi_os']);
        $this->assertSame(2, data_get($landing, 'mantri_performance.total.net_disbursement.deb'));
        $this->assertSame(2, (int) $kpi['total']['realisasi_deb']);
        $this->assertSame(290_000_000.0, (float) data_get($landing, 'mantri_performance.total.realization.amount'));
        $this->assertSame($hke, data_get($landing, 'mantri_performance.working_days'));
        $this->assertSame($hke, $kpi['working_days']);
        $this->assertSame(190_000_000.0 / $hke, (float) data_get($landing, 'mantri_performance.total.net_disbursement.average_per_hke'));
        $branch = collect(data_get($landing, 'mantri_performance.rows'))->firstWhere('branch', 'KC MADIUN');
        $this->assertSame(190_000_000.0 / 2 / $hke, (float) data_get($branch, 'net_disbursement.average_per_mantri_hke'));
        $low = collect(data_get($branch, 'tiers.contract.buckets'))->firstWhere('key', 'low');
        $this->assertSame(1, $low['mantri']);
        $this->assertSame('801', $low['people'][0]['pn']);
        $this->assertSame(190_000_000.0, $low['people'][0]['net_amount']);
        $this->assertSame(2, $low['people'][0]['net_deb']);
        $person = collect($kpi['rows'])->firstWhere('pn_mantri', '00000801');
        $this->assertSame(190_000_000.0, (float) $person['realisasi_os']);
        $this->assertSame(190.0 / $hke, (float) $person['ratas_mantri_hk']);
    }

    #[DataProvider('importSources')]
    public function test_future_month_imports_keep_same_am_identity_closure_and_landing_kpi_hke_after_baseline_retention(bool $lw): void
    {
        $this->importPosition('2026-11-30', [
            $this->loan('2026-11-30', 'CLOSED', 30_000_000, ['tgl_realisasi' => '2026-10-05']),
            $this->loan('2026-11-30', 'PRESENT-ZERO', 50_000_000, ['tgl_realisasi' => '2026-10-05']),
        ], $lw);
        $this->importPosition('2026-12-07', [
            $this->loan('2026-12-07', 'BOOKING', 100_000_000),
            $this->loan('2026-12-07', 'PRESENT-ZERO', 0, ['tgl_realisasi' => '2026-10-05', 'description' => null, 'segmen_kinerja' => null, 'produk_kinerja' => null]),
        ], $lw);
        DB::table('daily_loan_dinamis')->where('periode', '2026-11-30')->delete();

        $accounts = app(MicroNettDisbursementCalculator::class)->mantriAccounts('2026-12-07');
        $this->assertCount(1, $accounts);
        $this->assertSame('BOOKING', $accounts[0]['account_key']);
        $this->assertSame('00000801 - INITIATOR', $accounts[0]['owner']);
        $this->assertSame(100_000_000.0, $accounts[0]['plafon']);
        $this->assertSame(30_000_000.0, $accounts[0]['previous_os']);
        $this->assertSame(70_000_000.0, $accounts[0]['net_amount']);

        $landing = app(LandingMicroPerformanceService::class)->payload('2026-12-07');
        $kpi = (new ReflectionMethod(KinerjaRmMikroReportController::class, 'mantriProductivityPayload'))
            ->invoke(new KinerjaRmMikroReportController, '2026-12-07');
        $this->assertTrue(data_get($landing, 'meta.available'), (string) data_get($landing, 'meta.error'));
        $this->assertSame(70_000_000.0, (float) data_get($landing, 'mantri_performance.total.net_disbursement.amount'));
        $this->assertSame(70_000_000.0, (float) $kpi['total']['realisasi_os']);
        $this->assertSame(5, data_get($landing, 'mantri_performance.working_days'));
        $this->assertSame(5, $kpi['working_days']);
        $this->assertSame(14_000_000.0, (float) data_get($landing, 'mantri_performance.total.net_disbursement.average_per_hke'));
        $branch = collect(data_get($landing, 'mantri_performance.rows'))->firstWhere('branch', 'KC MADIUN');
        $this->assertSame(7_000_000.0, (float) data_get($branch, 'net_disbursement.average_per_mantri_hke'));
        $person = collect($kpi['rows'])->firstWhere('pn_mantri', '00000801');
        $other = collect($kpi['rows'])->firstWhere('pn_mantri', '00000802');
        $this->assertSame(70_000_000.0, (float) $person['realisasi_os']);
        $this->assertSame(14.0, (float) $person['ratas_mantri_hk']);
        $this->assertNull($other, 'The current manager must not receive the initiator booking credit.');
    }

    #[DataProvider('importSources')]
    public function test_archived_baseline_keeps_micro_gbt_and_unknown_unit_but_not_unknown_kc_or_other_products(bool $lw): void
    {
        $previous = '2026-11-30';
        $oldDate = ['tgl_realisasi' => '2026-10-05'];
        $this->importPosition($previous, [
            $this->loan($previous, 'CLOSED-MICRO', 50_000_000, $oldDate),
            $this->loan($previous, 'CLOSED-GBT', 20_000_000, $oldDate + ['produk_kinerja' => 'BRIGUNAMIKRO', 'description' => 'KREDIT MIKRO - GBT', 'unit1' => 'KC MADIUN']),
            $this->loan($previous, 'CLOSED-UNKNOWN-UNIT', 30_000_000, $oldDate + ['segmen_kinerja' => null, 'produk_kinerja' => null, 'description' => null]),
            $this->loan($previous, 'CLOSED-UNKNOWN-KC', 37_000_000, $oldDate + ['segmen_kinerja' => null, 'produk_kinerja' => null, 'description' => null, 'unit1' => 'KC MADIUN']),
            $this->loan($previous, 'CLOSED-RITEL', 90_000_000, $oldDate + ['produk_kinerja' => 'KURKECIL', 'description' => 'Kredit Mikro - KUR Ritel 2015']),
            $this->loan($previous, 'CLOSED-OUTSIDE', 80_000_000, $oldDate + ['cabang1' => 'KC Jember', 'cabang_normalized' => 'KC JEMBER']),
        ], $lw);
        $this->importPosition('2026-12-07', [$this->loan('2026-12-07', 'NEW', 200_000_000)], $lw);
        DB::table('daily_loan_dinamis')->where('periode', $previous)->delete();
        $rows = app(MicroNettDisbursementCalculator::class)->mantriAccounts('2026-12-07');
        $this->assertCount(1, $rows);
        $this->assertSame(100_000_000.0, $rows[0]['previous_os']);
        $this->assertSame(100_000_000.0, $rows[0]['net_amount']);
    }

    public static function ownerEvidenceSources(): array
    {
        return [
            'approved AM evidence' => [['owner_basis' => 'pn_pemrakarsa1'], '00000801 - APPROVED INITIATOR'],
            'legacy AK evidence' => [['owner_basis' => 'pn_pengelola1', 'source_owner_column' => 'AK'], '00000803 - RAW INITIATOR'],
            'unmarked legacy evidence' => [[], '00000803 - RAW INITIATOR'],
            'future AM evidence' => [['owner_basis' => 'pn_pemrakarsa1', 'as_of' => '2026-12-08'], '00000803 - RAW INITIATOR'],
        ];
    }

    #[DataProvider('ownerEvidenceSources')]
    public function test_capture_preserves_evidence_but_only_dated_am_approval_can_override_raw_initiator(array $basis, string $owner): void
    {
        $period = '2026-12-07';
        $this->importPosition($period, [$this->loan($period, '1001', 100_000_000, ['pn_pemrakarsa1' => '00000803 - RAW INITIATOR'])], false);
        $store = app(MantriSourceInputStore::class);
        $data = $store->read($period);
        $data['owner_evidence'] = [array_replace([
            'account' => '1001', 'cif' => 'CIF-UJI', 'realization_date' => '2026-12-03',
            'pn' => '801', 'name' => 'Approved Initiator', 'as_of' => $period,
        ], $basis)];
        $store->write($period, $data);
        $store->captureDailyLoanPeriod($period);
        $this->assertSame($data['owner_evidence'], $store->read($period)['owner_evidence']);
        $row = app(MicroNettDisbursementCalculator::class)->mantriAccounts($period)[0];
        $this->assertSame($owner, $row['owner']);
        $this->assertSame(100_000_000.0, $row['net_amount']);
    }

    public function test_existing_managed_cleanup_refreshes_only_surviving_native_inputs_and_does_not_restore_deleted_position(): void
    {
        $period = '2026-12-07';
        $this->importPosition($period, [
            $this->loan($period, 'MADIUN', 100_000_000),
            $this->loan($period, 'NGAWI', 80_000_000, ['cabang1' => 'KC NGAWI', 'cabang_normalized' => 'KC NGAWI']),
        ], false);
        $store = app(MantriSourceInputStore::class);
        $calculator = app(MicroNettDisbursementCalculator::class);
        $sync = app(ReportDataSyncService::class);
        $this->assertCount(2, $calculator->mantriAccounts($period));

        DB::table('daily_loan_dinamis')->where('periode', $period)->where('cabang1', 'KC MADIUN')->delete();
        $sync->cleanupDerivedArtifactsAfterDelete('daily_loan_dinamis', $period, 'managed-delete-test');
        $rows = $calculator->mantriAccounts($period);
        $this->assertCount(1, $rows);
        $this->assertSame('NGAWI', $rows[0]['account_key']);
        $this->assertSame(80_000_000.0, $rows[0]['net_amount']);
        $this->assertSame(1, DB::table('daily_loan_dinamis')->count());

        DB::table('daily_loan_dinamis')->where('periode', $period)->delete();
        $sync->cleanupDerivedArtifactsAfterDelete('daily_loan_dinamis', $period, 'managed-delete-test');
        $this->assertSame([], $calculator->mantriAccounts($period));
        $this->assertFalse($store->read($period)['complete_presence']);
        $this->assertSame([], $store->periods());
        $this->assertSame(0, DB::table('daily_loan_dinamis')->count());
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

    private function loan(string $period, string $account, float $amount, array $overrides = []): array
    {
        return array_replace([
            'uniqueid_namareport' => 'NATIVE:'.$period.':'.$account, 'periode' => $period,
            'tgl_realisasi' => '2026-12-03', 'nomor_rekening1' => $account, 'cifno' => 'CIF-UJI', 'cifno_clean' => 'CIF-UJI',
            'cabang1' => 'KC MADIUN', 'cabang_normalized' => 'KC MADIUN', 'kode_cabang1' => '45',
            'unit1' => 'UNIT UJI', 'unit_normalized' => 'UNIT UJI', 'branch1' => '45', 'branch_normalized' => '45',
            'segmen_kinerja' => 'MICRO', 'segmen_dashboard' => 'MICRO', 'produk_kinerja' => 'KUPEDES', 'produk_dashboard' => 'Kupedes',
            'description' => 'KREDIT MIKRO - KUPEDES', 'plafon' => $amount, 'baki_debet1' => $amount,
            'pn_pemrakarsa1' => '00000801 - INITIATOR', 'pn_pengelola1' => '00000802 - CURRENT MANAGER',
            'rm_normalized' => '00000802', 'kolek' => 1, 'ln_type' => 'BULANAN', 'freq_payment' => 1,
        ], $overrides);
    }
}
