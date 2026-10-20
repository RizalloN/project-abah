<?php

namespace Tests\Unit;

use App\Support\MantriProductivityReference;
use App\Support\MantriSourceInputStore;
use App\Support\MicroNettDisbursementCalculator;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MantriKanwilFormulaTest extends TestCase
{
    private ?string $cohortDirectory = null;

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

    protected function tearDown(): void
    {
        if ($this->cohortDirectory !== null) {
            File::deleteDirectory($this->cohortDirectory);
        }
        parent::tearDown();
    }

    public static function reportPositions(): array
    {
        return [['2026-09-19'], ['2026-10-16'], ['2026-12-11']];
    }

    #[DataProvider('reportPositions')]
    public function test_general_formula_is_identical_before_during_and_after_source_month_without_reference(string $period): void
    {
        $date = Carbon::parse($period);
        $previous = $date->copy()->startOfMonth()->subDay()->toDateString();
        $bookingDate = $date->copy()->startOfMonth()->addDays(4)->toDateString();
        $priorDate = Carbon::parse($previous)->startOfMonth()->toDateString();
        $row = fn (string $account, float $amount, array $overrides = []): array => $this->row($period, $bookingDate, $account, $amount, $overrides);

        DB::table('daily_loan_dinamis')->insert([
            $row('000001', 100_000_000, ['periode' => $previous, 'tgl_realisasi' => $priorDate]),
            $row('1', 80_000_000, ['periode' => $previous, 'tgl_realisasi' => $priorDate]),
            $row('BRIGUNA-OLD', 25_000_000, ['periode' => $previous, 'produk_kinerja' => 'BRIGUNAMIKRO']),
            $row('SMALL-OLD', 80_000_000, ['periode' => $previous, 'segmen_kinerja' => 'SMALL', 'produk_kinerja' => 'COMMERCIAL']),
            $row('CONSUMER-OLD', 30_000_000, ['periode' => $previous, 'segmen_kinerja' => 'CONSUMER', 'produk_kinerja' => 'KPR']),
            $row('OUTSIDE-OLD', 90_000_000, ['periode' => $previous, 'cabang1' => 'KC Kediri', 'cabang_normalized' => 'KC KEDIRI']),
            $row('1', 90_000_000, ['tgl_realisasi' => $priorDate]),
            $row('000011', 300_000_000.25),
            $row('11', 280_000_000),
            $row('12', 100_000_000),
            $row('13', 200_000_000),
            $row('FALLBACK', 250_000_000, ['cifno' => 'CIF-OTHER', 'pn_pemrakarsa1' => '', 'pn_pengelola1' => '00000456 - Pengelola']),
            $row('DISAPPEARED', 999_000_000, ['periode' => $date->copy()->subDay()->toDateString()]),
            $row('KUR-KECIL', 900_000_000, ['produk_kinerja' => 'KURKECIL']),
            $row('KUR-ALIAS', 900_000_000, ['produk_kinerja' => 'KURMIKRO', 'description' => 'Kredit Mikro - KUR Ritel 2015']),
            $row('BRIGUNA-NEW', 900_000_000, ['produk_kinerja' => 'BRIGUNAMIKRO']),
            $row('BRIGUNA-CONSUMER', 900_000_000, ['segmen_kinerja' => 'CONSUMER', 'produk_kinerja' => 'BRIGUNAKONSUMER']),
            $row('OUTSIDE', 900_000_000, ['cabang1' => 'KC Kediri', 'cabang_normalized' => 'KC KEDIRI']),
            $row('FUTURE', 900_000_000, ['tgl_realisasi' => $date->copy()->addDay()->toDateString()]),
        ]);

        $cohortPeriod = $date->copy()->subDay()->toDateString();
        $this->cohortDirectory = storage_path('framework/testing/mantri-kanwil-cohort-'.bin2hex(random_bytes(8)));
        $store = new MantriSourceInputStore($this->cohortDirectory);
        $this->app->instance(MantriSourceInputStore::class, $store);
        $store->write($cohortPeriod, [
            'source' => ['source_kind' => 'audited_complete_workbook_position'],
            'complete_presence' => true, 'presence' => ['DISAPPEARED'],
            'bookings' => [$row('DISAPPEARED', 999_000_000, ['periode' => $cohortPeriod])],
        ]);
        $this->assertNull(app(MantriProductivityReference::class)->dataForPeriod($period));
        $calculator = app(MicroNettDisbursementCalculator::class);
        $accounts = collect($calculator->mantriAccounts($period))->keyBy('account_key');
        $this->assertEqualsCanonicalizing(['11', '12', '13', 'DISAPPEARED', 'FALLBACK'], $accounts->keys()->map('strval')->all());
        $this->assertSame(300_000_000.25, $accounts['11']['plafon']);
        $this->assertSame(25_000_000.0, $accounts['11']['previous_os']);
        $this->assertSame(275_000_000.25, $accounts['11']['net_amount']);
        $this->assertSame(75_000_000.0, $accounts['12']['net_amount']);
        $this->assertSame(175_000_000.0, $accounts['13']['net_amount']);
        $this->assertSame(974_000_000.0, $accounts['DISAPPEARED']['net_amount']);
        $this->assertSame('00000123 - PEMRAKARSA', $accounts['11']['owner']);
        $this->assertSame('RM LAIN', $accounts['FALLBACK']['owner']);
        $this->assertSame(250_000_000.0, $accounts['FALLBACK']['net_amount']);
        $this->assertSame(['daily_loan_dinamis'], $accounts->pluck('baseline_source')->unique()->values()->all());
        $metrics = collect($calculator->mantri($period));
        $this->assertSame(5, $metrics->sum('realisasi_deb'));
        $this->assertSame(1_749_000_000.25, $metrics->sum('realisasi_os'));
    }

    #[DataProvider('reportPositions')]
    public function test_each_booking_is_floored_before_owner_totals_and_preserves_actual_baseline_in_every_period(string $period): void
    {
        $date = Carbon::parse($period);
        $previous = $date->copy()->startOfMonth()->subDay()->toDateString();
        $booking = $date->copy()->startOfMonth()->addDays(4)->toDateString();
        $oldBooking = Carbon::parse($previous)->startOfMonth()->toDateString();
        $row = fn (string $account, float $amount, array $overrides = []): array => $this->row($period, $booking, $account, $amount, $overrides);
        DB::table('daily_loan_dinamis')->insert([
            $row('OLD-NEGATIVE', 300_000_000.75, ['periode' => $previous, 'tgl_realisasi' => $oldBooking, 'cifno' => 'CIF-NEGATIVE']),
            $row('OLD-POSITIVE', 100_000_000.10, ['periode' => $previous, 'tgl_realisasi' => $oldBooking, 'cifno' => 'CIF-POSITIVE']),
            $row('OLD-EQUAL', 50_000_000.55, ['periode' => $previous, 'tgl_realisasi' => $oldBooking, 'cifno' => 'CIF-EQUAL']),
            $row('OLD-OTHER', 400_000_000.20, ['periode' => $previous, 'tgl_realisasi' => $oldBooking, 'cifno' => 'CIF-OTHER']),
            $row('NEW-NEGATIVE', 250_000_000.25, ['cifno' => 'CIF-NEGATIVE']),
            $row('NEW-POSITIVE', 200_000_000.40, ['cifno' => 'CIF-POSITIVE']),
            $row('NEW-EQUAL', 50_000_000.55, ['cifno' => 'CIF-EQUAL']),
            $row('NEW-OTHER', 100_000_000.10, ['cifno' => 'CIF-OTHER', 'pn_pemrakarsa1' => '00000456 - Pemrakarsa Lain']),
            $row('KUR-GROSS', 250_000_000, ['cifno' => 'CIF-NEGATIVE', 'produk_kinerja' => 'KURKECIL', 'description' => 'Kredit Mikro - KUR Ritel 2015']),
        ]);

        $calculator = app(MicroNettDisbursementCalculator::class);
        $accounts = collect($calculator->mantriAccounts($period))->keyBy('account_key');
        $this->assertCount(4, $accounts);
        $this->assertSame(300_000_000.75, $accounts['NEW-NEGATIVE']['previous_os']);
        $this->assertSame(250_000_000.25, $accounts['NEW-NEGATIVE']['plafon']);
        $this->assertSame(0.0, $accounts['NEW-NEGATIVE']['net_amount']);
        $this->assertSame(100_000_000.10, $accounts['NEW-POSITIVE']['previous_os']);
        $this->assertEqualsWithDelta(100_000_000.30, $accounts['NEW-POSITIVE']['net_amount'], 0.000001);
        $this->assertSame(50_000_000.55, $accounts['NEW-EQUAL']['previous_os']);
        $this->assertSame(0.0, $accounts['NEW-EQUAL']['net_amount']);
        $this->assertSame(400_000_000.20, $accounts['NEW-OTHER']['previous_os']);
        $this->assertSame(0.0, $accounts['NEW-OTHER']['net_amount']);

        $metrics = collect($calculator->mantri($period))->keyBy('owner');
        $this->assertCount(2, $metrics);
        $this->assertSame(3, $metrics['00000123 - PEMRAKARSA']['realisasi_deb']);
        $this->assertEqualsWithDelta(100_000_000.30, $metrics['00000123 - PEMRAKARSA']['realisasi_os'], 0.000001);
        $this->assertSame(1, $metrics['00000456 - PEMRAKARSA LAIN']['realisasi_deb']);
        $this->assertSame(0.0, $metrics['00000456 - PEMRAKARSA LAIN']['realisasi_os']);
        $kur = $calculator->kurRm($period);
        $this->assertCount(1, $kur);
        $this->assertSame(1, $kur[0]['realisasi_deb']);
        $this->assertSame(250_000_000.0, $kur[0]['realisasi_os']);
    }

    public function test_non_briguna_bookings_keep_all_current_segments_but_previous_exposure_is_micro_only(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->row('2026-12-11', '2026-12-05', 'MICRO-OLD', 40_000_000, ['periode' => '2026-11-30']),
            $this->row('2026-12-11', '2026-12-05', 'SMALL-OLD', 80_000_000, ['periode' => '2026-11-30', 'segmen_kinerja' => 'SMALL']),
            $this->row('2026-12-11', '2026-12-05', 'MICRO-NEW', 100_000_000),
            $this->row('2026-12-11', '2026-12-05', 'CONSUMER-NEW', 100_000_000, ['segmen_kinerja' => 'CONSUMER', 'produk_kinerja' => 'KPR']),
            $this->row('2026-12-11', '2026-12-05', 'SMALL-NEW', 100_000_000, ['segmen_kinerja' => 'SMALL', 'produk_kinerja' => 'COMMERCIAL']),
        ]);

        $accounts = app(MicroNettDisbursementCalculator::class)->mantriAccounts('2026-12-11');
        $this->assertCount(3, $accounts);
        $this->assertSame([40_000_000.0], collect($accounts)->pluck('previous_os')->unique()->values()->all());
        $this->assertSame(180_000_000.0, collect($accounts)->sum('net_amount'));
    }

    public function test_raw_source_branch_scope_excludes_outside_branch_despite_inside_normalized_label(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->row('2026-12-11', '2026-12-05', 'INSIDE', 100_000_000),
            $this->row('2026-12-11', '2026-12-05', 'OUTSIDE', 900_000_000, ['cabang1' => 'KC Kediri']),
            $this->row('2026-12-11', '2026-12-05', 'KUR-INSIDE', 100_000_000, ['produk_kinerja' => 'KURKECIL', 'description' => 'Kredit Mikro - KUR Ritel 2015']),
            $this->row('2026-12-11', '2026-12-05', 'KUR-OUTSIDE', 900_000_000, ['cabang1' => 'KC Kediri', 'produk_kinerja' => 'KURKECIL', 'description' => 'Kredit Mikro - KUR Ritel 2015']),
        ]);

        $calculator = app(MicroNettDisbursementCalculator::class);
        $this->assertSame(100_000_000.0, $calculator->mantri('2026-12-11')[0]['realisasi_os']);
        $this->assertSame(1, $calculator->mantri('2026-12-11')[0]['realisasi_deb']);
        $this->assertSame(100_000_000.0, $calculator->kurRm('2026-12-11')[0]['realisasi_os']);
        $this->assertSame(1, $calculator->kurRm('2026-12-11')[0]['realisasi_deb']);
    }

    public static function baselinePositions(): array
    {
        return [['2026-09-30', true], ['2026-09-29', false]];
    }

    #[DataProvider('baselinePositions')]
    public function test_account_level_baseline_fills_unknown_source_accounts_and_yields_to_exact_month_end_reimports(string $baselinePeriod, bool $exact): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->row($baselinePeriod, '2026-08-01', 'PRIOR-A', 5_000_000),
            $this->row($baselinePeriod, '2026-08-01', 'PRIOR-ZERO', 0, ['cifno' => 'CIF-ZERO']),
            $this->row('2026-10-17', '2026-10-05', 'NEW-A', 100_000_000),
            $this->row('2026-10-17', '2026-10-05', 'NEW-ZERO', 100_000_000, ['cifno' => 'CIF-ZERO']),
            $this->row('2026-10-17', '2026-10-05', 'NEW-MISSING', 100_000_000, ['cifno' => 'CIF-MISSING']),
        ]);
        $directory = sys_get_temp_dir().'/mantri-baseline-account-'.bin2hex(random_bytes(8));
        $store = new MantriSourceInputStore($directory);
        $this->app->instance(MantriSourceInputStore::class, $store);
        $unclassified = fn (string $account, string $cif): array => $this->row('2026-09-30', '2026-08-01', $account, 40_000_000, [
            'cifno' => $cif, 'segmen_kinerja' => null, 'produk_kinerja' => null, 'description' => null,
        ]);
        $store->write('2026-09-30', [
            'source' => ['source_kind' => 'manual_workbook_baseline'], 'complete_presence' => false,
            'presence' => [], 'bookings' => [], 'unclassified_accounts' => [
                $unclassified('PRIOR-A', 'CIF-A'), $unclassified('PRIOR-ZERO', 'CIF-ZERO'),
                $unclassified('PRIOR-MISSING', 'CIF-MISSING'),
            ],
        ]);
        $store->write('2026-10-17', [
            'source' => ['source_kind' => 'manual_workbook_position'], 'complete_presence' => true,
            'presence' => ['NEW-A', 'NEW-ZERO', 'NEW-MISSING'], 'bookings' => [], 'unclassified_accounts' => [],
        ]);
        try {
            $calculator = app(MicroNettDisbursementCalculator::class);
            $rows = collect($calculator->mantriAccounts('2026-10-17'))->keyBy('account_key');
            $this->assertSame($exact ? 5_000_000.0 : 40_000_000.0, $rows['NEW-A']['previous_os']);
            $this->assertSame($exact ? 0.0 : 40_000_000.0, $rows['NEW-ZERO']['previous_os']);
            $this->assertSame(40_000_000.0, $rows['NEW-MISSING']['previous_os']);
            $this->assertSame($exact ? 'daily_loan_dinamis' : 'mantri_source_input', $rows['NEW-A']['baseline_source']);
            $this->assertSame('mantri_source_input', $rows['NEW-MISSING']['baseline_source']);

            DB::table('daily_loan_dinamis')->where('periode', '2026-09-30')->delete();
            DB::table('daily_loan_dinamis')->insert([
                $this->row('2026-09-30', '2026-08-01', 'PRIOR-A', 25_000_000),
                $this->row('2026-09-30', '2026-08-01', 'PRIOR-ZERO', 0, ['cifno' => 'CIF-ZERO']),
            ]);
            $updated = collect($calculator->mantriAccounts('2026-10-17'))->keyBy('account_key');
            $this->assertSame(25_000_000.0, $updated['NEW-A']['previous_os']);
            $this->assertSame(75_000_000.0, $updated['NEW-A']['net_amount']);
            $this->assertSame(0.0, $updated['NEW-ZERO']['previous_os']);
            $this->assertSame(100_000_000.0, $updated['NEW-ZERO']['net_amount']);
            $this->assertSame('daily_loan_dinamis', $updated['NEW-ZERO']['baseline_source']);
            $this->assertSame(40_000_000.0, $updated['NEW-MISSING']['previous_os']);
        } finally {
            foreach (glob($directory.'/*') as $file) {
                unlink($file);
            }
            unlink($directory.'/.write.lock');
            rmdir($directory);
        }
    }

    public function test_archived_classified_month_end_balance_survives_history_retention_and_yields_to_live_corrections(): void
    {
        $directory = sys_get_temp_dir().'/mantri-classified-baseline-'.bin2hex(random_bytes(8));
        $store = new MantriSourceInputStore($directory);
        $this->app->instance(MantriSourceInputStore::class, $store);
        $store->write('2026-11-30', [
            'source' => ['source_kind' => 'daily_loan_import'], 'complete_presence' => false,
            'presence' => [], 'bookings' => [], 'unclassified_accounts' => [],
            'baseline_accounts' => [$this->row('2026-11-30', '2026-09-01', 'PRIOR-A', 60_000_000)],
        ]);
        DB::table('daily_loan_dinamis')->insert($this->row('2026-12-11', '2026-12-05', 'NEW-A', 100_000_000));
        try {
            $calculator = app(MicroNettDisbursementCalculator::class);
            $archived = $calculator->mantriAccounts('2026-12-11')[0];
            $this->assertSame(60_000_000.0, $archived['previous_os']);
            $this->assertSame(40_000_000.0, $archived['net_amount']);
            $this->assertSame('mantri_source_input', $archived['baseline_source']);

            foreach ([
                ['segmen_kinerja' => 'SMALL', 'produk_kinerja' => 'COMMERCIAL'],
                ['segmen_kinerja' => null, 'produk_kinerja' => 'COMMERCIAL'],
                ['cabang1' => 'KC Kediri'],
                ['produk_kinerja' => 'KURKECIL', 'description' => 'Kredit Mikro - KUR Ritel 2015'],
            ] as $correction) {
                DB::table('daily_loan_dinamis')->where('periode', '2026-11-30')->delete();
                DB::table('daily_loan_dinamis')->insert($this->row('2026-11-30', '2026-09-01', 'PRIOR-A', 60_000_000, $correction));
                $corrected = $calculator->mantriAccounts('2026-12-11')[0];
                $this->assertSame(0.0, $corrected['previous_os']);
                $this->assertSame(100_000_000.0, $corrected['net_amount']);
            }
        } finally {
            foreach (glob($directory.'/*') as $file) {
                unlink($file);
            }
            unlink($directory.'/.write.lock');
            rmdir($directory);
        }
    }

    private function row(string $period, string $bookingDate, string $account, float $amount, array $overrides = []): array
    {
        return array_merge([
            'periode' => $period, 'tgl_realisasi' => $bookingDate,
            'cabang_normalized' => 'KC MADIUN', 'cabang1' => 'KC Madiun',
            'unit_normalized' => 'UNIT A', 'unit1' => 'Unit A',
            'branch_normalized' => '45', 'branch1' => '45',
            'pn_pemrakarsa1' => '00000123 - Pemrakarsa', 'pn_pengelola1' => '00000999 - Pengelola Lain',
            'nomor_rekening1' => $account, 'cifno' => 'CIF-A',
            'segmen_kinerja' => 'MICRO', 'produk_kinerja' => 'KUPEDES',
            'description' => 'KREDIT MIKRO - KUPEDES', 'plafon' => $amount, 'baki_debet1' => $amount,
        ], $overrides);
    }
}
