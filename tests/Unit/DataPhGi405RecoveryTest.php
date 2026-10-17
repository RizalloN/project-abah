<?php

namespace Tests\Unit;

use App\Services\Reports\DataPhGi405RecoveryService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DataPhGi405RecoveryTest extends TestCase
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
            $table->string('description')->nullable();
            $table->decimal('ending_balance', 24, 2)->nullable();
        });

        DB::table('referensi_uker')->insert([
            ['kode_uker' => '00045', 'nama_uker' => '00045 -- KC Madiun', 'nama_cabang' => '00045 -- KC Madiun (Konsolidasi-MB)'],
            ['kode_uker' => '06343', 'nama_uker' => '06343 -- UNIT A', 'nama_cabang' => '00045 -- KC Madiun (Konsolidasi-MB)'],
            ['kode_uker' => '06344', 'nama_uker' => '06344 -- UNIT B', 'nama_cabang' => '00045 -- KC Madiun (Konsolidasi-MB)'],
            ['kode_uker' => '06345', 'nama_uker' => '06345 -- UNIT C', 'nama_cabang' => '00049 -- KC Magetan (Konsolidasi-MB)'],
            ['kode_uker' => '09999', 'nama_uker' => '09999 -- UNIT LUAR', 'nama_cabang' => '00099 -- KC Surabaya (Konsolidasi-MB)'],
        ]);
    }

    public function test_official_recovery_preserves_cents_and_unknown_split_without_explicit_claim_account(): void
    {
        $this->control('06343', -1250.25);
        $this->ledger('06343', '418020000010', -1250.25);

        $rows = $this->rows();

        $this->assertCount(1, $rows);
        $row = $rows[0];
        $this->assertSame('KC Madiun', $row['branch']);
        $this->assertSame('UNIT A', $row['unit']);
        $this->assertSame('6343', $row['unit_code']);
        $this->assertEqualsWithDelta(1250.25, $row['total'], 0.00001);
        $this->assertUnknownSplit($row);
        $this->assertNotSame('', $row['source']);
    }

    public function test_official_zero_and_signed_reversal_do_not_fall_back_or_become_absolute_values(): void
    {
        $this->control('06343', 0);
        $this->control('06344', 150.12);
        $this->ledger('06343', '418020000010', -999);
        $this->ledger('06344', '418020000010', -999);

        $rows = collect($this->rows())->keyBy('unit_code');

        $this->assertEqualsWithDelta(0.0, $rows['6343']['total'], 0.00001);
        $this->assertEqualsWithDelta(-150.12, $rows['6344']['total'], 0.00001);
        $this->assertUnknownSplit($rows['6343']);
        $this->assertUnknownSplit($rows['6344']);
    }

    public function test_explicit_historical_claim_and_non_claim_accounts_reconcile_to_official_total(): void
    {
        $this->control('06343', -125.25, '2025-12-31');
        $this->ledger('06343', '418020000010', -100.12, '2025-12-31');
        $this->ledger('06343', '418020000013', -25.13, '2025-12-31');

        $row = $this->rows('2025-12-31')[0];

        $this->assertTrue($row['split_available']);
        $this->assertEqualsWithDelta(100.12, $row['non_klaim'], 0.00001);
        $this->assertEqualsWithDelta(25.13, $row['klaim'], 0.00001);
        $this->assertEqualsWithDelta(125.25, $row['total'], 0.00001);
        $this->assertEqualsWithDelta($row['total'], $row['non_klaim'] + $row['klaim'], 0.00001);
    }

    public function test_reconciled_claim_reversal_keeps_its_negative_sign(): void
    {
        $this->control('06343', -75, '2025-12-31');
        $this->ledger('06343', '418020000010', -100, '2025-12-31');
        $this->ledger('06343', '418020000013', 25, '2025-12-31');

        $row = $this->rows('2025-12-31')[0];

        $this->assertTrue($row['split_available']);
        $this->assertEqualsWithDelta(100.0, $row['non_klaim'], 0.00001);
        $this->assertEqualsWithDelta(-25.0, $row['klaim'], 0.00001);
        $this->assertEqualsWithDelta(75.0, $row['total'], 0.00001);
    }

    public function test_mismatched_ledger_split_cannot_override_official_control_or_invent_components(): void
    {
        $this->control('06343', -125, '2025-12-31');
        $this->ledger('06343', '418020000010', -100, '2025-12-31');
        $this->ledger('06343', '418020000013', -99, '2025-12-31');

        $row = $this->rows('2025-12-31')[0];

        $this->assertEqualsWithDelta(125.0, $row['total'], 0.00001);
        $this->assertUnknownSplit($row);
    }

    public function test_claim_account_from_a_different_period_does_not_prove_current_split(): void
    {
        $this->control('06343', -100);
        $this->ledger('06343', '418020000010', -100);
        $this->ledger('06343', '418020000013', 0, '2025-12-31');

        $this->assertUnknownSplit($this->rows()[0]);
    }

    public function test_explicit_claim_account_in_selected_source_allows_reconciled_zero_claim_for_another_unit(): void
    {
        $this->control('06343', -100, '2025-12-31');
        $this->ledger('06343', '418020000010', -100, '2025-12-31');
        $this->ledger('06344', '418020000013', 0, '2025-12-31');

        $row = $this->rows('2025-12-31', ['KC Madiun'], 'UNIT A')[0];

        $this->assertTrue($row['split_available']);
        $this->assertEqualsWithDelta(100.0, $row['non_klaim'], 0.00001);
        $this->assertEqualsWithDelta(0.0, $row['klaim'], 0.00001);
    }

    public function test_single_row_fallback_uses_only_posted_idr_recovery_accounts(): void
    {
        $this->ledger('06343', '418020000010', -100, '2025-12-31', currency: ' IDR ', posting: ' *POST ');
        $this->ledger('06343', '418020000013', -25, '2025-12-31');
        $this->ledger('06343', '418020000010', -1000, '2025-12-31', currency: 'USD');
        $this->ledger('06343', '418020000013', -2000, '2025-12-31', posting: '*TOTAL');
        $this->ledger('06343', '418020000099', -3000, '2025-12-31');
        $this->ledger('06343', '250010000010', -4000, '2025-12-31');

        $rows = $this->rows('2025-12-31');

        $this->assertCount(1, $rows);
        $this->assertTrue($rows[0]['split_available']);
        $this->assertEqualsWithDelta(100.0, $rows[0]['non_klaim'], 0.00001);
        $this->assertEqualsWithDelta(25.0, $rows[0]['klaim'], 0.00001);
        $this->assertEqualsWithDelta(125.0, $rows[0]['total'], 0.00001);
    }

    public function test_single_row_fallback_without_claim_account_retains_total_and_unknown_split(): void
    {
        $this->ledger('06343', '418020000010', -100.01);

        $row = $this->rows()[0];

        $this->assertEqualsWithDelta(100.01, $row['total'], 0.00001);
        $this->assertUnknownSplit($row);
    }

    public function test_2026_single_row_fallback_ignores_legacy_claim_gl_after_income_reclassification(): void
    {
        $this->ledger('06343', '418020000010', -100.12);
        $this->ledger('06343', '418020000013', -999.99);
        $this->ledger('06343', '418020000013', -1000, '2026-10-01');

        $rows = $this->rows();

        $this->assertCount(1, $rows);
        $this->assertEqualsWithDelta(100.12, $rows[0]['total'], 0.00001);
        $this->assertUnknownSplit($rows[0]);
        $this->assertSame([], $this->rows('2026-10-01'));
        $this->assertNotContains('2026-10-01', app(DataPhGi405RecoveryService::class)->availablePeriods()->all());
    }

    public function test_control_and_single_row_are_selected_per_unit_without_double_counting_sources(): void
    {
        $this->control('06343', -100);
        $this->ledger('06343', '418020000010', -100);
        $this->ledger('06344', '418020000010', -50);

        $rows = collect($this->rows())->keyBy('unit_code');

        $this->assertCount(2, $rows);
        $this->assertEqualsWithDelta(100.0, $rows['6343']['total'], 0.00001);
        $this->assertEqualsWithDelta(50.0, $rows['6344']['total'], 0.00001);
        $this->assertEqualsWithDelta(150.0, $rows->sum('total'), 0.00001);
    }

    public function test_exact_period_and_area_scope_reject_other_periods_branches_and_unknown_branch_filters(): void
    {
        $this->control('06343', -100);
        $this->control('06344', -50);
        $this->control('06345', -20);
        $this->control('09999', -1000);
        $this->control('06343', -10000, '2026-09-29');

        $all = $this->rows();
        $madiun = $this->rows('2026-09-30', ['KC Madiun']);
        $unit = $this->rows('2026-09-30', ['KC Madiun'], '06343 -- UNIT A');

        $this->assertCount(3, $all);
        $this->assertEqualsWithDelta(170.0, array_sum(array_column($all, 'total')), 0.00001);
        $this->assertCount(2, $madiun);
        $this->assertCount(1, $unit);
        $this->assertSame('6343', $unit[0]['unit_code']);
        $this->assertEqualsWithDelta(100.0, $unit[0]['total'], 0.00001);
        $this->assertSame([], $this->rows('2026-09-28'));
        $this->assertSame([], $this->rows('2026-09-30', ['KC Surabaya']));
    }

    public function test_available_periods_include_gi405_only_dates_and_remove_duplicates(): void
    {
        $this->control('06343', 0, '2026-09-30');
        $this->ledger('06343', '418020000010', -100, '2026-09-30');
        $this->ledger('06343', '418020000010', -50, '2026-02-28');

        $periods = app(DataPhGi405RecoveryService::class)->availablePeriods()->values()->all();

        $this->assertSame(['2026-09-30', '2026-02-28'], $periods);
    }

    private function rows(string $period = '2026-09-30', array $branches = [], string $unit = 'all'): array
    {
        return array_values(app(DataPhGi405RecoveryService::class)->unitRows($period, $branches, $unit));
    }

    private function control(string $unitCode, float $balance, string $period = '2026-09-30'): void
    {
        DB::table('gi405_recovery')->insert([
            'periode' => $period,
            'kode_uker' => $unitCode,
            'pendapatan_koreksi_ppap_dr_angsuran_ph' => $balance,
        ]);
    }

    private function ledger(
        string $unitCode,
        string $account,
        float $balance,
        string $period = '2026-09-30',
        string $currency = 'IDR',
        string $posting = '*POST'
    ): void {
        $unit = DB::table('referensi_uker')->where('kode_uker', $unitCode)->first();
        DB::table('gi405_singlerow')->insert([
            'periode' => $period,
            'branch' => (string) ((int) $unitCode),
            'nama_cabang' => $unit->nama_cabang,
            'nama_uker' => $unit->nama_uker,
            'currency' => $currency,
            'posting_control' => $posting,
            'account_number' => $account,
            'ending_balance' => $balance,
        ]);
    }

    private function assertUnknownSplit(array $row): void
    {
        $this->assertFalse($row['split_available']);
        $this->assertNull($row['non_klaim']);
        $this->assertNull($row['klaim']);
    }
}
