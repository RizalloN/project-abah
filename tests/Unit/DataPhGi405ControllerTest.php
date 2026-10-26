<?php

namespace Tests\Unit;

use App\Http\Controllers\Report\DataPhReportController;
use App\Support\UserBranchScope;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class DataPhGi405ControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('referensi_uker', function (Blueprint $table): void {
            $table->string('kode_uker')->primary();
            $table->string('nama_uker');
            $table->string('nama_cabang');
        });
        Schema::create('gi405_recovery', function (Blueprint $table): void {
            $table->id();
            $table->date('periode')->index();
            $table->string('kode_uker');
            $table->string('nama_uker')->nullable();
            $table->decimal('pendapatan_koreksi_ppap_dr_angsuran_ph', 24, 2)->nullable();
        });
        Schema::create('gi405_singlerow', function (Blueprint $table): void {
            $table->id();
            $table->date('periode')->index();
            $table->string('branch');
            $table->string('nama_cabang')->nullable();
            $table->string('nama_uker')->nullable();
            $table->string('currency');
            $table->string('posting_control');
            $table->string('account_number');
            $table->decimal('ending_balance', 24, 2)->nullable();
        });
        Schema::create('lw325_ph', function (Blueprint $table): void {
            $table->string('uniqueid_namareport')->nullable();
            $table->date('periode')->index();
            $table->string('acctno')->nullable();
            $table->string('kanca')->nullable();
            $table->string('unit')->nullable();
            $table->string('segmen_dashboard')->nullable();
            $table->string('produk_dashboard')->nullable();
            $table->string('flag_klaim')->nullable();
            $table->decimal('clmamt', 20, 2)->nullable();
            $table->decimal('pokok', 20, 2)->nullable();
        });
        Schema::create('cognos_recovery', function (Blueprint $table): void {
            $table->date('periode')->index();
            $table->string('cabang')->nullable();
            $table->string('unit_kerja')->nullable();
            $table->string('sub_bc')->nullable();
            $table->string('segmen_2')->nullable();
            $table->string('produk')->nullable();
            $table->decimal('recovery_klaim', 20, 2)->nullable();
            $table->decimal('recovery_non_klaim', 20, 2)->nullable();
            $table->decimal('total_recovery', 20, 2)->nullable();
        });

        DB::table('referensi_uker')->insert([
            ['kode_uker' => '06343', 'nama_uker' => '06343 -- UNIT A', 'nama_cabang' => '00045 -- KC Madiun (Konsolidasi-MB)'],
            ['kode_uker' => '06344', 'nama_uker' => '06344 -- UNIT B', 'nama_cabang' => '00045 -- KC Madiun (Konsolidasi-MB)'],
            ['kode_uker' => '06345', 'nama_uker' => '06345 -- UNIT C', 'nama_cabang' => '00049 -- KC Magetan (Konsolidasi-MB)'],
        ]);
    }

    public function test_period_list_follows_lw325_and_a_gi405_only_date_is_not_offered(): void
    {
        $this->ph('2026-08-31', 1000);
        $this->control('06343', -100);
        $this->control('06344', -50);

        $data = $this->index();

        // Recovery and its breakdown come from LW325; a GI405-only date would
        // open on empty LW325 columns, so the default is the latest PH date.
        $this->assertSame('2026-08-31', $data['selectedPeriod']);
        $this->assertNotContains('2026-09-30', $data['availablePeriods']->all());
        $this->assertFalse($data['usesGi405']);
        $this->assertFalse($data['showUnallocated']);
        $this->assertTrue($data['hasCurrentPh']);
        $this->assertEqualsWithDelta(1000.0, $data['summary']['total_sisa_ph'], 0.00001);
    }

    public function test_lw325_is_the_recovery_and_gi405_is_only_checked_in_the_backend(): void
    {
        $this->phRow('2026-08-31', 'A1', 'UNIT A', 1000);
        $this->phRow('2026-08-31', 'A2', 'UNIT B', 500);
        $this->phRow('2026-09-30', 'A1', 'UNIT A', 800); // TUPOK 200; A2 paid off: LUNAS 500
        $this->control('06343', -740);
        $this->control('06344', -50);
        $this->control('06343', -60, '2026-08-31');
        $this->control('06344', -30, '2026-08-31');

        $area = $this->index();
        $madiun = collect($area['rows'])->firstWhere('kanca', 'KC Madiun');

        $this->assertSame('lw325_ph/cognos', $madiun['recovery_source']);
        $this->assertEqualsWithDelta(200.0, $madiun['tupok']['total'], 0.00001);
        $this->assertEqualsWithDelta(500.0, $madiun['lunas']['total'], 0.00001);
        $this->assertEqualsWithDelta(700.0, $madiun['recovery_curr']['total'], 0.00001);
        $this->assertEqualsWithDelta(700.0, $madiun['recovery_curr']['micro'], 0.00001);
        // GI405 is not part of the page.
        $this->assertArrayNotHasKey('gi405_control', $madiun);
        $this->assertArrayNotHasKey('gi405ControlTotals', $area);
        $this->assertEqualsWithDelta(0.0, $area['grandTotals']['recovery_curr']['unallocated'], 0.00001);

        // Backend: LW325 movement 700 against GI405 income since 31 Aug (790 - 90).
        $check = (new DataPhReportController)->gi405Reconciliation('2026-09-30');

        $this->assertSame('2026-08-31', $check['gi405_m1_period']);
        $this->assertEqualsWithDelta(700.0, $check['branches']['KC Madiun']['lw325'], 0.00001);
        $this->assertEqualsWithDelta(700.0, $check['branches']['KC Madiun']['gi405'], 0.00001);
        $this->assertSame('ok', $check['branches']['KC Madiun']['status']);
        $this->assertNull($check['branches']['KC Magetan']['gi405']);
        $this->assertSame('unknown', $check['branches']['KC Magetan']['status']);
        $this->assertSame('ok', $check['status']);
    }

    public function test_gi405_zero_income_flags_an_lw325_recovery_as_a_mismatch(): void
    {
        $this->control('06343', 0);
        $this->control('06343', 0, '2026-08-31');
        $this->ph('2026-08-31', 1000);
        $this->ph('2026-09-30', 500);

        $data = $this->index(['kanca' => ['KC Madiun'], 'unit_kerja' => 'UNIT A']);
        $check = (new DataPhReportController)->gi405Reconciliation('2026-09-30');

        $this->assertEqualsWithDelta(500.0, $data['summary']['total_recovery'], 0.00001);
        $this->assertEqualsWithDelta(500.0, $data['summary']['total_sisa_ph'], 0.00001);
        // A booked zero is a real GL value, not "missing".
        $this->assertSame(0.0, $check['branches']['KC Madiun']['gi405']);
        $this->assertSame('fail', $check['branches']['KC Madiun']['status']);
    }

    public function test_gi405_never_fills_the_lw325_breakdown_columns(): void
    {
        $this->ph('2025-11-30', 1000);
        $this->ph('2025-12-31', 900);
        $this->control('06343', -125, '2025-12-31');
        $this->ledger('418020000010', -100, '2025-12-31');
        $this->ledger('418020000013', -25, '2025-12-31');

        $totals = $this->index(['periode' => '2025-12-31', 'kanca' => ['KC Madiun']])['grandTotals'];

        $this->assertEqualsWithDelta(100.0, $totals['recovery_curr']['total'], 0.00001);
        $this->assertEqualsWithDelta(100.0, $totals['tupok']['total'], 0.00001);
        $this->assertEqualsWithDelta(0.0, $totals['recovery_curr']['unallocated'], 0.00001);
        $this->assertEqualsWithDelta(0.0, $totals['klaim']['unallocated'], 0.00001);
        $this->assertEqualsWithDelta(0.0, $totals['unclassified_recovery']['total'], 0.00001);
    }

    public function test_gi405_control_does_not_change_lw325_stock_or_nominatif_values(): void
    {
        $this->control('06343', -125);
        $this->ph('2026-09-30', 50000, 'Small', 'Kecil');

        $data = $this->index();
        $request = Request::create('/report/dashboard-pinjaman/data-ph/nominatif', 'GET', [
            'periode' => '2026-09-30', 'segment' => 'small', 'kanca' => 'KC Madiun', 'unit_kerja' => 'UNIT A',
        ]);
        $nominatif = (new DataPhReportController)->nominatif($request)->getData(true);

        $this->assertEqualsWithDelta(0.0, $data['summary']['total_recovery'], 0.00001, 'No previous month PH: no LW325 movement.');
        $this->assertArrayNotHasKey('gi405ControlTotals', $data);
        $this->assertEqualsWithDelta(50000.0, $data['summary']['total_sisa_ph'], 0.00001);
        $this->assertEqualsWithDelta(50000.0, $data['grandTotals']['sisa_ph']['small'], 0.00001);
        $this->assertEqualsWithDelta(0.0, $data['grandTotals']['sisa_ph']['unallocated'], 0.00001);
        $this->assertSame(1, $nominatif['total_count']);
        $this->assertEqualsWithDelta(50000.0, $nominatif['total_pokok'], 0.00001);
        $this->assertSame('A1', $nominatif['rows'][0]['acctno']);
    }

    public function test_selected_unit_and_pinned_branch_scope_do_not_show_other_gi405_units(): void
    {
        $this->phRow('2026-09-30', 'A1', 'UNIT A', 10);
        $this->phRow('2026-09-30', 'B1', 'UNIT B', 10);
        $this->control('06343', -100);
        $this->control('06344', -50);
        $this->control('06345', -20);

        $unit = $this->index(['kanca' => ['KC Madiun'], 'unit_kerja' => 'UNIT A']);
        $scoped = UserBranchScope::runAs(UserBranchScope::forKey('madiun'), fn (): array => $this->index());

        $this->assertCount(1, $unit['rows']);
        $this->assertSame('UNIT A', $unit['rows'][0]['unit']);
        $this->assertCount(1, $scoped['rows']);
        $this->assertSame('KC Madiun', $scoped['rows'][0]['kanca']);
        $this->assertNotContains('KC Magetan', array_column($scoped['filters']['kanca'], 'value'));
        $this->assertNotContains('UNIT C', array_column($scoped['filters']['unit_kerja'], 'value'));
    }

    public function test_gi405_income_delta_restarts_in_january_instead_of_subtracting_previous_year_income(): void
    {
        $current = ['source' => 'GI405', 'unallocated' => 125.25, 'total' => 125.25];
        $previousYear = ['source' => 'GI405', 'unallocated' => 2000.0, 'total' => 2000.0];

        $delta = $this->invokePrivate('incomePeriodDelta', [$current, $previousYear, '2026-01-31', '2025-12-31']);

        $this->assertEqualsWithDelta(125.25, $delta['total'], 0.00001);
        $this->assertEqualsWithDelta(125.25, $delta['unallocated'], 0.00001);
    }

    public function test_gi405_same_year_income_delta_uses_the_difference_between_comparable_ytd_positions(): void
    {
        $current = ['source' => 'GI405', 'unallocated' => 175.75, 'total' => 175.75];
        $previous = ['source' => 'GI405', 'unallocated' => 125.25, 'total' => 125.25];

        $delta = $this->invokePrivate('incomePeriodDelta', [$current, $previous, '2026-02-28', '2026-01-31']);
        $aligned = $this->invokePrivate('alignedIncomeComparison', [$current, $previous]);

        $this->assertEqualsWithDelta(50.5, $delta['total'], 0.00001);
        $this->assertEqualsWithDelta(50.5, $delta['unallocated'], 0.00001);
        $this->assertSame($previous, $aligned);
    }

    public function test_gi405_does_not_subtract_legacy_or_missing_comparison_from_current_income(): void
    {
        $current = ['source' => 'GI405', 'unallocated' => 150.0, 'total' => 150.0];
        $legacy = ['micro' => 100.0, 'total' => 100.0];

        $aligned = $this->invokePrivate('alignedIncomeComparison', [$current, $legacy]);
        $rawDelta = $this->invokePrivate('incomePeriodDelta', [$current, $legacy, '2026-02-28', '2026-01-31']);
        $alignedDelta = $this->invokePrivate('incomePeriodDelta', [$current, $aligned, '2026-02-28', '2026-01-31']);
        $missingDelta = $this->invokePrivate('incomePeriodDelta', [$current, [], '2026-02-28', null]);

        $this->assertFalse($aligned['available']);
        foreach (['micro', 'small', 'consumer_briguna', 'consumer_kpr', 'unallocated', 'total'] as $key) {
            $this->assertEqualsWithDelta(0.0, $aligned[$key], 0.00001);
            $this->assertEqualsWithDelta(0.0, $rawDelta[$key], 0.00001);
            $this->assertEqualsWithDelta(0.0, $alignedDelta[$key], 0.00001);
            $this->assertEqualsWithDelta(0.0, $missingDelta[$key], 0.00001);
        }
    }

    public function test_non_gi405_income_comparison_and_delta_keep_existing_legacy_behavior(): void
    {
        $current = ['micro' => 150.0, 'total' => 150.0];
        $legacy = ['micro' => 100.0, 'total' => 100.0];

        $aligned = $this->invokePrivate('alignedIncomeComparison', [$current, $legacy]);
        $delta = $this->invokePrivate('incomePeriodDelta', [$current, $legacy, '2026-01-31', '2025-12-31']);

        $this->assertSame($legacy, $aligned);
        $this->assertEqualsWithDelta(50.0, $delta['micro'], 0.00001);
        $this->assertEqualsWithDelta(50.0, $delta['total'], 0.00001);
    }

    public function test_january_reconciliation_restarts_instead_of_subtracting_previous_year_income(): void
    {
        $this->ph('2026-01-31', 10);
        $this->control('06343', -125.25, '2026-01-31');
        $this->control('06343', -2000, '2025-12-31');

        $check = (new DataPhReportController)->gi405Reconciliation('2026-01-31');

        $this->assertSame('2025-12-31', $check['gi405_m1_period']);
        $this->assertEqualsWithDelta(125.25, $check['branches']['KC Madiun']['gi405'], 0.00001);
    }

    public function test_month_comparison_stays_on_the_lw325_cognos_basis_when_gi405_exists(): void
    {
        $this->ph('2026-02-28', 10);
        $this->control('06343', -150, '2026-02-28');
        DB::table('cognos_recovery')->insert([
            'periode' => '2026-01-31', 'cabang' => 'KC Madiun', 'unit_kerja' => 'UNIT A',
            'segmen_2' => 'Micro', 'produk' => 'Kupedes', 'total_recovery' => 100,
        ]);

        $data = $this->index(['periode' => '2026-02-28', 'kanca' => ['KC Madiun']]);

        $this->assertNotFalse($data['rows'][0]['recovery_m1']['available'] ?? true);
        $this->assertEqualsWithDelta(100.0, $data['grandTotals']['recovery_m1']['total'], 0.00001);
        $check = (new DataPhReportController)->gi405Reconciliation('2026-02-28');
        $this->assertNull($check['gi405_m1_period'], 'No GI405 position in January: no GL month income to compare.');
        $this->assertSame('unknown', $check['status']);
    }

    private function index(array $parameters = []): array
    {
        $request = Request::create('/report/dashboard-pinjaman/data-ph', 'GET', array_merge(['periode' => '2026-09-30'], $parameters));

        return (new DataPhReportController)->index($request)->getData();
    }

    private function invokePrivate(string $methodName, array $arguments): array
    {
        $method = new ReflectionMethod(DataPhReportController::class, $methodName);

        return $method->invokeArgs(new DataPhReportController, $arguments);
    }

    private function control(string $unitCode, float $balance, string $period = '2026-09-30'): void
    {
        DB::table('gi405_recovery')->insert([
            'periode' => $period, 'kode_uker' => $unitCode, 'pendapatan_koreksi_ppap_dr_angsuran_ph' => $balance,
        ]);
    }

    private function ledger(string $account, float $balance, string $period): void
    {
        DB::table('gi405_singlerow')->insert([
            'periode' => $period, 'branch' => '6343', 'nama_cabang' => 'KC Madiun', 'nama_uker' => 'UNIT A',
            'currency' => 'IDR', 'posting_control' => '*POST', 'account_number' => $account, 'ending_balance' => $balance,
        ]);
    }

    private function phRow(string $period, string $account, string $unit, float $balance): void
    {
        DB::table('lw325_ph')->insert([
            'periode' => $period, 'acctno' => $account, 'kanca' => 'KC Madiun', 'unit' => $unit,
            'segmen_dashboard' => 'Micro', 'produk_dashboard' => 'Kupedes', 'flag_klaim' => 'N', 'pokok' => $balance,
        ]);
    }

    private function ph(string $period, float $balance, string $segment = 'Micro', string $product = 'Kupedes'): void
    {
        DB::table('lw325_ph')->insert([
            'periode' => $period, 'acctno' => 'A1', 'kanca' => 'KC Madiun', 'unit' => 'UNIT A',
            'segmen_dashboard' => $segment, 'produk_dashboard' => $product, 'flag_klaim' => 'N', 'pokok' => $balance,
        ]);
    }
}
