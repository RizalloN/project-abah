<?php

namespace Tests\Unit;

use App\Support\MantriSourceInputStore;
use App\Support\MicroSameAccountTopupDetector;
use App\Support\ReportCacheVersion;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MicroSameAccountTopupDetectorTest extends TestCase
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
        $this->directory = storage_path('framework/testing/micro-same-account-topup-'.bin2hex(random_bytes(8)));
        app()->instance(MantriSourceInputStore::class, new MantriSourceInputStore($this->directory));

        Schema::create('daily_loan_dinamis', function (Blueprint $table): void {
            $table->id();
            $table->string('uniqueid_namareport')->unique();
            foreach ([
                'periode', 'tgl_realisasi', 'cifno', 'cifno_clean', 'nomor_rekening1',
                'cabang1', 'cabang_normalized', 'unit1', 'unit_normalized', 'branch1',
                'branch_normalized', 'segmen_kinerja', 'produk_kinerja', 'description',
                'pn_pemrakarsa1', 'pn_pengelola1',
            ] as $column) {
                $table->string($column)->nullable();
            }
            $table->decimal('plafon', 20, 2)->nullable();
            $table->decimal('baki_debet1', 20, 2)->nullable();
        });
        Schema::create('lw321pn', function (Blueprint $table): void {
            $table->id();
            foreach ([
                'uniqueid_namareport', 'periode', 'no_rekening', 'cifno', 'tgl_realisasi',
                'description', 'kanca', 'uker', 'kode_uker', 'pn_pengelola_singlepn',
                'pn_pengelola_1', 'pn_pemrakarsa',
            ] as $column) {
                $table->string($column)->nullable();
            }
            foreach (['plafon_dalam_idr', 'plafon', 'balance_dalam_idr'] as $column) {
                $table->decimal($column, 20, 2)->nullable();
            }
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
    public function test_raised_same_account_limit_is_a_full_booking_with_observed_date_and_original_source_date(bool $lw): void
    {
        $this->position('2026-11-30', [$this->loan('2026-11-30', '10001', 40_000_000, ['baki_debet1' => 30_000_000])], $lw);
        $this->position('2026-12-07', [$this->loan('2026-12-07', '10001', 50_000_000)], $lw);
        $before = DB::table('daily_loan_dinamis')->orderBy('id')->get()->toJson();

        $rows = $this->detector()->bookings('2026-12-07');

        $this->assertCount(1, $rows);
        $row = (array) $rows[0];
        $this->assertSame('10001', $row['nomor_rekening1']);
        $this->assertSame('CIF-A', $row['cifno']);
        $this->assertSame(50_000_000.0, (float) $row['plafon']);
        $this->assertSame(40_000_000.0, (float) $row['reference_plafon']);
        $this->assertSame(50_000_000.0, (float) $row['observed_plafon']);
        $this->assertSame('2026-12-07', $row['tgl_realisasi']);
        $this->assertSame('2026-11-05', $row['source_realization_date']);
        $this->assertSame('same_account_topup', $row['booking_kind']);
        $this->assertSame('00000801 - PEMRAKARSA', $row['pn_pemrakarsa1']);
        $this->assertSame('00000802 - PENGELOLA', $row['pn_pengelola1']);
        $this->assertSame($before, DB::table('daily_loan_dinamis')->orderBy('id')->get()->toJson());
    }

    public static function nonTopups(): array
    {
        return [
            'amortization with unchanged limit' => [50_000_000, 50_000_000, [], ['baki_debet1' => 20_000_000], '2026-11-30'],
            'lower limit' => [50_000_000, 40_000_000, [], [], '2026-11-30'],
            'unknown prior limit' => [null, 50_000_000, [], [], '2026-11-30'],
            'different CIF' => [40_000_000, 50_000_000, [], ['cifno' => 'CIF-B', 'cifno_clean' => 'CIF-B'], '2026-11-30'],
            'different account' => [40_000_000, 50_000_000, [], ['nomor_rekening1' => 'OTHER'], '2026-11-30'],
            'ordinary dated new booking' => [40_000_000, 50_000_000, [], ['tgl_realisasi' => '2026-12-07'], '2026-11-30'],
            'future booking date' => [40_000_000, 50_000_000, [], ['tgl_realisasi' => '2026-12-08'], '2026-11-30'],
            'missing booking date' => [40_000_000, 50_000_000, [], ['tgl_realisasi' => null], '2026-11-30'],
            'wrong comparison date' => [40_000_000, 50_000_000, [], [], '2026-11-29'],
            'outside Area 6' => [40_000_000, 50_000_000, ['cabang1' => 'KC SURABAYA', 'cabang_normalized' => 'KC SURABAYA'], ['cabang1' => 'KC SURABAYA', 'cabang_normalized' => 'KC SURABAYA'], '2026-11-30'],
            'Briguna' => [40_000_000, 50_000_000, ['produk_kinerja' => 'BRIGUNA', 'description' => 'Kredit Briguna'], ['produk_kinerja' => 'BRIGUNA', 'description' => 'Kredit Briguna'], '2026-11-30'],
            'non Micro segment' => [40_000_000, 50_000_000, ['segmen_kinerja' => 'SMALL', 'produk_kinerja' => 'COMMERCIAL'], ['segmen_kinerja' => 'SMALL', 'produk_kinerja' => 'COMMERCIAL'], '2026-11-30'],
        ];
    }

    #[DataProvider('nonTopups')]
    public function test_detector_requires_known_comparable_old_account_limit_in_scope(?float $prior, float $current, array $priorOverrides, array $currentOverrides, string $priorPeriod): void
    {
        $this->position($priorPeriod, [$this->loan($priorPeriod, '10001', $prior, $priorOverrides)], false);
        $this->position('2026-12-07', [$this->loan('2026-12-07', '10001', $current, $currentOverrides)], false);

        $this->assertSame([], $this->detector()->bookings('2026-12-07'));
    }

    #[DataProvider('sources')]
    public function test_known_zero_prior_limit_is_distinct_from_missing_limit(bool $lw): void
    {
        $this->position('2026-11-30', [$this->loan('2026-11-30', '10001', 0)], $lw);
        $this->position('2026-12-07', [$this->loan('2026-12-07', '10001', 10_000_000)], $lw);

        $rows = $this->detector()->bookings('2026-12-07');

        $this->assertCount(1, $rows);
        $this->assertSame(0.0, (float) ((array) $rows[0])['reference_plafon']);
        $this->assertSame(10_000_000.0, (float) ((array) $rows[0])['plafon']);
    }

    #[DataProvider('sources')]
    public function test_repeated_snapshots_and_multiple_rises_use_one_booking_at_first_observation_of_highest_limit(bool $lw): void
    {
        $this->position('2026-11-30', [$this->loan('2026-11-30', '10001', 40_000_000)], $lw);
        foreach (['2026-12-07' => 50_000_000, '2026-12-08' => 50_000_000, '2026-12-09' => 70_000_000, '2026-12-10' => 70_000_000, '2026-12-11' => 60_000_000] as $date => $limit) {
            $this->position($date, [$this->loan($date, '10001', $limit)], $lw);
        }

        $first = $this->detector()->bookings('2026-12-08');
        $this->assertCount(1, $first);
        $this->assertSame('2026-12-07', ((array) $first[0])['tgl_realisasi']);
        $this->assertSame(50_000_000.0, (float) ((array) $first[0])['plafon']);
        $highest = $this->detector()->bookings('2026-12-11');
        $this->assertCount(1, $highest);
        $this->assertSame('2026-12-09', ((array) $highest[0])['tgl_realisasi']);
        $this->assertSame(70_000_000.0, (float) ((array) $highest[0])['plafon']);
        $this->assertSame($first, $this->detector()->bookings('2026-12-08'));
    }

    #[DataProvider('sources')]
    public function test_registered_raise_survives_account_disappearing_from_following_position(bool $lw): void
    {
        $this->position('2026-11-30', [$this->loan('2026-11-30', '10001', 40_000_000)], $lw);
        $this->position('2026-12-07', [$this->loan('2026-12-07', '10001', 50_000_000)], $lw);
        $this->position('2026-12-08', [$this->loan('2026-12-08', 'UNCHANGED-ANCHOR', 5_000_000)], $lw);

        $rows = $this->detector()->bookings('2026-12-08');

        $this->assertCount(1, $rows);
        $this->assertSame('10001', ((array) $rows[0])['nomor_rekening1']);
        $this->assertSame('2026-12-07', ((array) $rows[0])['tgl_realisasi']);
        $this->assertSame(50_000_000.0, (float) ((array) $rows[0])['plafon']);
    }

    public function test_native_source_replaces_conflicting_lw_at_the_same_position(): void
    {
        $this->position('2026-11-30', [$this->loan('2026-11-30', '10001', 40_000_000)], false);
        $this->position('2026-12-07', [$this->loan('2026-12-07', '10001', 90_000_000)], true);
        $this->position('2026-12-07', [$this->loan('2026-12-07', '10001', 50_000_000)], false);

        $rows = $this->detector()->bookings('2026-12-07');

        $this->assertCount(1, $rows);
        $this->assertSame(50_000_000.0, (float) ((array) $rows[0])['plafon']);
        $this->assertSame('00000801 - PEMRAKARSA', ((array) $rows[0])['pn_pemrakarsa1']);
    }

    #[DataProvider('sources')]
    public function test_one_cent_raise_is_detected_without_losing_precision(bool $lw): void
    {
        $this->position('2026-11-30', [$this->loan('2026-11-30', '10001', 40_000_000.01)], $lw);
        $this->position('2026-12-07', [$this->loan('2026-12-07', '10001', 40_000_000.02)], $lw);

        $rows = $this->detector()->bookings('2026-12-07');

        $this->assertCount(1, $rows);
        $this->assertSame(40_000_000.01, (float) ((array) $rows[0])['reference_plafon']);
        $this->assertSame(40_000_000.02, (float) ((array) $rows[0])['plafon']);
    }

    #[DataProvider('sources')]
    public function test_corrected_position_supersedes_its_previously_archived_raise(bool $lw): void
    {
        $this->position('2026-11-30', [$this->loan('2026-11-30', '10001', 40_000_000)], $lw);
        $this->position('2026-12-07', [$this->loan('2026-12-07', '10001', 50_000_000)], $lw);
        $this->assertCount(1, $this->detector()->bookings('2026-12-07'));
        DB::table('daily_loan_dinamis')->where('periode', '2026-12-07')->update(['plafon' => 40_000_000]);
        if ($lw) {
            DB::table('lw321pn')->where('periode', '2026-12-07')->update(['plafon' => 40_000_000, 'plafon_dalam_idr' => 40_000_000]);
        }
        ReportCacheVersion::bump('pinjaman');

        $this->assertSame([], $this->detector()->bookings('2026-12-07'));
    }

    #[DataProvider('sources')]
    public function test_legacy_balance_only_archive_cannot_prove_a_limit_increase(bool $lw): void
    {
        $this->position('2026-11-30', [$this->loan('2026-11-30', '10001', 40_000_000, ['baki_debet1' => 30_000_000])], $lw);
        $store = app(MantriSourceInputStore::class);
        $input = $store->read('2026-11-30');
        foreach ($input['baseline_accounts'] as &$row) {
            array_pop($row);
        }
        unset($row);
        $store->write('2026-11-30', $input);
        DB::table('daily_loan_dinamis')->where('periode', '2026-11-30')->delete();
        DB::table('lw321pn')->where('periode', '2026-11-30')->delete();
        ReportCacheVersion::bump('pinjaman');
        $this->position('2026-12-07', [$this->loan('2026-12-07', '10001', 50_000_000)], $lw);

        $this->assertSame([], $this->detector()->bookings('2026-12-07'));
    }

    private function detector(): MicroSameAccountTopupDetector
    {
        return app(MicroSameAccountTopupDetector::class);
    }

    private function position(string $period, array $rows, bool $lw): void
    {
        if ($lw) {
            foreach ($rows as $row) {
                DB::table('lw321pn')->insert([
                    'uniqueid_namareport' => 'RAW:'.$period.':'.$row['nomor_rekening1'],
                    'periode' => $period, 'no_rekening' => $row['nomor_rekening1'], 'cifno' => $row['cifno'],
                    'tgl_realisasi' => $row['tgl_realisasi'], 'description' => $row['description'],
                    'kanca' => $row['cabang1'], 'uker' => $row['unit1'], 'kode_uker' => $row['branch1'],
                    'pn_pemrakarsa' => $row['pn_pemrakarsa1'], 'pn_pengelola_singlepn' => $row['pn_pengelola1'],
                    'plafon_dalam_idr' => $row['plafon'], 'plafon' => $row['plafon'], 'balance_dalam_idr' => $row['baki_debet1'],
                ]);
                $row['uniqueid_namareport'] = 'LW321PN:'.$period.':'.$row['nomor_rekening1'];
                DB::table('daily_loan_dinamis')->insert($row);
            }
            app(MantriSourceInputStore::class)->captureLw321Period($period);
        } else {
            DB::table('daily_loan_dinamis')->insert($rows);
            app(MantriSourceInputStore::class)->captureDailyLoanPeriod($period);
        }
        ReportCacheVersion::bump('pinjaman');
    }

    private function loan(string $period, string $account, ?float $plafon, array $overrides = []): array
    {
        return array_replace([
            'uniqueid_namareport' => 'NATIVE:'.$period.':'.$account,
            'periode' => $period, 'tgl_realisasi' => '2026-11-05',
            'nomor_rekening1' => $account, 'cifno' => 'CIF-A', 'cifno_clean' => 'CIF-A',
            'cabang1' => 'KC MADIUN', 'cabang_normalized' => 'KC MADIUN',
            'unit1' => 'UNIT UJI', 'unit_normalized' => 'UNIT UJI', 'branch1' => '45', 'branch_normalized' => '45',
            'segmen_kinerja' => 'MICRO', 'produk_kinerja' => 'KUPEDES', 'description' => 'Kredit Mikro - Kupedes',
            'plafon' => $plafon, 'baki_debet1' => max(0.0, (float) ($plafon ?? 0) - 10_000_000),
            'pn_pemrakarsa1' => '00000801 - PEMRAKARSA', 'pn_pengelola1' => '00000802 - PENGELOLA',
        ], $overrides);
    }
}
