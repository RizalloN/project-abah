<?php

namespace Tests\Unit;

use App\Support\LandingMicroPerformanceService;
use App\Support\MantriSourceInputStore;
use App\Support\ReportCacheVersion;
use App\Support\UserBranchScope;
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

class LandingMicroOldDateTopupTest extends TestCase
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
        $this->directory = storage_path('framework/testing/landing-old-date-topup-'.bin2hex(random_bytes(8)));
        app()->instance(MantriSourceInputStore::class, new MantriSourceInputStore($this->directory));
        Schema::create('daily_loan_dinamis', function (Blueprint $table): void {
            $table->id();
            $table->string('uniqueid_namareport')->unique();
            foreach ([
                'periode', 'tgl_realisasi', 'cifno', 'cifno_clean', 'nomor_rekening1',
                'cabang1', 'kode_cabang1', 'cabang_normalized', 'unit1', 'unit_normalized',
                'branch1', 'branch_normalized', 'segmen_kinerja', 'segmen_dashboard',
                'produk_dashboard', 'produk_kinerja', 'description', 'ln_type', 'nama_debitur1',
                'jangka_waktu1', 'pn_pemutus1', 'pn_pemutus_normalized', 'pn_pengelola1', 'pn_pemrakarsa1',
            ] as $column) {
                $table->string($column)->nullable();
            }
            $table->integer('freq_payment')->nullable();
            $table->decimal('plafon', 20, 2)->nullable();
            $table->decimal('baki_debet1', 20, 2)->nullable();
        });
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    public static function periods(): array
    {
        return ['December' => ['2026-12-07'], 'Following year' => ['2027-02-04']];
    }

    #[DataProvider('periods')]
    public function test_old_date_limit_increase_enters_only_nett_with_source_provenance_and_branch_scope(string $period): void
    {
        $previous = Carbon::parse($period)->startOfMonth()->subDay()->toDateString();
        $firstIncrease = Carbon::parse($period)->subDay()->toDateString();
        $oldDate = Carbon::parse($previous)->subMonthsNoOverflow(2)->toDateString();
        DB::table('daily_loan_dinamis')->insert([
            $this->loan($previous, '0001001', 'CIF-A', 30_000_000, 30_000_000, $oldDate),
            $this->loan($previous, '2001', 'CIF-B', 25_000_000, 25_000_000, $oldDate, 'KC NGAWI'),
            $this->loan($previous, '3001', 'CIF-STABLE', 40_000_000, 25_000_000, $oldDate),
            $this->loan($firstIncrease, '1001', 'CIF-A', 50_000_000, 45_000_000, $oldDate),
            $this->loan($period, '1001', 'CIF-A', 50_000_000, 44_000_000, $oldDate),
            $this->loan($period, '2001', 'CIF-B', 50_000_000, 49_000_000, $oldDate, 'KC NGAWI'),
            // An OS increase alone is not evidence of another contractual booking.
            $this->loan($period, '3001', 'CIF-STABLE', 40_000_000, 38_000_000, $oldDate),
        ]);
        $rawBefore = DB::table('daily_loan_dinamis')->orderBy('id')->get()->all();
        $service = app(LandingMicroPerformanceService::class);
        $this->assertSame([], $this->invoke($service, 'fetchPlafondRealizationRows', $period, null));
        $rows = $this->invoke($service, 'fetchNetRealizationRows', $period, $previous, null, []);
        $this->assertCount(2, $rows);
        $this->assertSame(45_000_000.0, (float) collect($rows)->sum('net_amount'));
        $madiun = collect($rows)->firstWhere('account_key', '1001');
        $this->assertSame(20_000_000.0, $madiun->net_amount);
        $this->assertSame(30_000_000.0, $madiun->cif_previous_os);
        $this->assertSame($firstIncrease, $madiun->realization_date);
        $this->assertSame($oldDate, $madiun->source_realization_date);
        $this->assertSame('same_account_topup', $madiun->booking_kind);
        $this->assertSame('daily_loan_dinamis', $madiun->topup_source);
        $this->assertSame(30_000_000.0, $madiun->reference_plafon);
        $this->assertSame(50_000_000.0, (float) $madiun->observed_plafon);
        $scoped = $this->invoke($service, 'fetchNetRealizationRows', $period, $previous, UserBranchScope::forKey('madiun'), []);
        $this->assertCount(1, $scoped);
        $this->assertSame(20_000_000.0, $scoped[0]->net_amount);
        $this->assertSame([], $this->invoke($service, 'fetchPlafondRealizationRows', $period, null));
        $this->assertEquals($rawBefore, DB::table('daily_loan_dinamis')->orderBy('id')->get()->all());
    }

    #[DataProvider('periods')]
    public function test_disappeared_topup_uses_its_closed_same_account_baseline_once(string $period): void
    {
        $previous = Carbon::parse($period)->startOfMonth()->subDay()->toDateString();
        $firstIncrease = Carbon::parse($period)->subDay()->toDateString();
        $oldDate = Carbon::parse($previous)->subMonthsNoOverflow(2)->toDateString();
        DB::table('daily_loan_dinamis')->insert([
            $this->loan($previous, '0001001', 'CIF-A', 30_000_000, 30_000_000, $oldDate),
            $this->loan($previous, '3001', 'CIF-STABLE', 40_000_000, 25_000_000, $oldDate),
            $this->loan($firstIncrease, '1001', 'CIF-A', 50_000_000, 45_000_000, $oldDate),
            $this->loan($period, '3001', 'CIF-STABLE', 40_000_000, 24_000_000, $oldDate),
        ]);
        $service = app(LandingMicroPerformanceService::class);
        $rows = $this->invoke($service, 'fetchNetRealizationRows', $period, $previous, null, []);
        $this->assertCount(1, $rows);
        $this->assertSame(30_000_000.0, $rows[0]->cif_previous_os);
        $this->assertSame(20_000_000.0, $rows[0]->net_amount);
        $this->assertSame($firstIncrease, $rows[0]->realization_date);
        $this->assertSame([], $this->invoke($service, 'fetchPlafondRealizationRows', $period, null));
    }

    #[DataProvider('periods')]
    public function test_current_dated_booking_overrides_an_earlier_inferred_topup_without_duplicate_cif_deduction(string $period): void
    {
        $previous = Carbon::parse($period)->startOfMonth()->subDay()->toDateString();
        $firstIncrease = Carbon::parse($period)->subDay()->toDateString();
        $oldDate = Carbon::parse($previous)->subMonthsNoOverflow(2)->toDateString();
        DB::table('daily_loan_dinamis')->insert([
            $this->loan($previous, '0001001', 'CIF-A', 30_000_000, 30_000_000, $oldDate),
            $this->loan($firstIncrease, '1001', 'CIF-A', 50_000_000, 45_000_000, $oldDate),
            $this->loan($period, '0001001', 'CIF-A', 60_000_000, 59_000_000, $period),
        ]);
        ReportCacheVersion::bump('pinjaman');
        $service = app(LandingMicroPerformanceService::class);
        $gross = $this->invoke($service, 'fetchPlafondRealizationRows', $period, null);
        $this->assertCount(1, $gross);
        $this->assertSame('0001001', $gross[0]->account_key, 'Gross preserves its existing source identity.');
        $this->assertSame(60_000_000.0, $gross[0]->plafon);
        $rows = $this->invoke($service, 'fetchNetRealizationRows', $period, $previous, null, $gross);
        $this->assertCount(1, $rows);
        $this->assertSame('1001', $rows[0]->account_key);
        $this->assertSame(60_000_000.0, $rows[0]->plafon);
        $this->assertSame(30_000_000.0, $rows[0]->net_amount);
        $this->assertSame(30_000_000.0, $rows[0]->cif_previous_os);
        $this->assertSame($period, $rows[0]->realization_date);
        $this->assertObjectNotHasProperty('booking_kind', $rows[0]);
        $this->assertEquals($gross, $this->invoke($service, 'fetchPlafondRealizationRows', $period, null));
    }

    private function invoke(object $service, string $method, mixed ...$arguments): mixed
    {
        return (new ReflectionMethod($service, $method))->invoke($service, ...$arguments);
    }

    private function loan(string $period, string $account, string $cif, float $limit, float $balance, string $bookingDate, string $branch = 'KC MADIUN'): array
    {
        return [
            'uniqueid_namareport' => 'NATIVE:'.$period.':'.$account, 'periode' => $period,
            'tgl_realisasi' => $bookingDate, 'nomor_rekening1' => $account, 'cifno' => $cif, 'cifno_clean' => $cif,
            'cabang1' => $branch, 'cabang_normalized' => $branch, 'kode_cabang1' => '45',
            'unit1' => 'UNIT UJI', 'unit_normalized' => 'UNIT UJI', 'branch1' => '45', 'branch_normalized' => '45',
            'segmen_kinerja' => 'MICRO', 'segmen_dashboard' => 'MICRO', 'produk_kinerja' => 'KUPEDES', 'produk_dashboard' => 'Kupedes',
            'description' => 'KREDIT MIKRO - KUPEDES', 'plafon' => $limit, 'baki_debet1' => $balance,
            'pn_pemrakarsa1' => '00000801 - INITIATOR', 'pn_pengelola1' => '00000802 - MANAGER',
            'pn_pemutus1' => '00000805 - PEMUTUS', 'ln_type' => 'BULANAN', 'freq_payment' => 1,
        ];
    }
}
