<?php

namespace Tests\Unit;

use App\Support\DashboardHarianSnapshotService;
use App\Support\RkaLookupService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DashboardHarianKeragaanPdfPayloadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        $columns = (new \ReflectionClass(DashboardHarianSnapshotService::class))->getConstant('METRIC_COLUMNS');
        Schema::create(DashboardHarianSnapshotService::SNAPSHOT_TABLE, function (Blueprint $table) use ($columns): void {
            foreach (['snapshot_period', 'kanca_key', 'kanca_label', 'unit_key', 'unit_label'] as $column) {
                $table->string($column);
            }
            foreach ($columns as $column) {
                $table->double($column)->default(0);
            }
        });
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        DB::purge('sqlite');
        parent::tearDown();
    }

    public function test_area_exports_only_branch_summaries_with_null_missing_comparisons_and_summed_targets(): void
    {
        foreach (['2026-08-30', '2026-08-31', '2026-09-29', '2026-09-30'] as $period) {
            $this->snapshot($period, 'KC Madiun', 'kc-madiun', 'KC Madiun', 100);
            $this->snapshot($period, 'KC Ngawi', 'kc-ngawi', 'KC Ngawi', 200);
            $this->snapshot($period, 'KC Ponorogo', 'kc-ponorogo', 'KC Ponorogo', 999);
            $this->snapshot($period, 'KC Madiun', 'unit-dagangan', 'UNIT Dagangan', 700);
        }
        $this->mockRka('kanca', [
            'giro_ritel' => ['KC MADIUN' => 200, 'KC NGAWI' => 300],
            'kecil_non_cashcoll_sml' => ['KC MADIUN' => 5, 'KC NGAWI' => 10],
        ]);
        DB::enableQueryLog();
        $payload = (new DashboardHarianSnapshotService())->buildKeragaanPdfPayload('2026-09-30', '2026-09-01', ['KC Madiun', 'KC Ngawi'], true);
        $queries = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'from "dashboard_harian_snapshots"'));
        DB::disableQueryLog();
        $this->assertCount(3, $queries);
        $this->assertCount(17, $payload['sections']);
        $this->assertCount(2, $payload['offices']);
        $this->assertSame('2026-08-30', $payload['comparison_periods']['mtm']);
        $this->assertSame('2026-09-29', $payload['comparison_periods']['h1']);
        $savings = $payload['sections'][0];
        $this->assertSame(['KC Madiun', 'KC Ngawi'], array_column($savings['rows'], 'label'));
        $this->assertSame(300.0, $savings['total']['values']['current']);
        $this->assertSame(500.0, $savings['total']['values']['rka']);
        $this->assertNull($savings['total']['values']['ytd']);
        $this->assertSame(-200.0, $savings['total']['deltas']['rka']);
        $sml = $payload['sections'][8];
        $this->assertTrue($sml['lower_better']);
        $this->assertSame(50.0, $sml['total']['achievement']);
        $this->assertSame(15.0, $sml['total']['deltas']['rka']);
    }

    public function test_branch_exports_actual_units_including_historical_data_without_inventing_missing_balances(): void
    {
        $this->snapshot('2026-09-30', 'KC Madiun', 'kc-madiun', 'KC Madiun', 999);
        $this->snapshot('2026-09-30', 'KC Madiun', 'kc-madiun-detail', 'KC Madiun', 100);
        $this->snapshot('2026-09-30', 'KC Madiun', 'unit-dagangan', 'UNIT Dagangan', 200);
        $this->snapshot('2026-09-30', 'KC Madiun', 'unit-empty', 'UNIT Empty', 0);
        $this->snapshot('2026-08-31', 'KC Madiun', 'unit-closed', 'UNIT Closed', 70);
        $this->snapshot('2026-09-30', 'KC Ngawi', 'unit-outsider', 'UNIT Outsider', 999);
        $this->mockRka('uker', ['giro_ritel' => ['KC MADIUN' => 150], 'giro_mikro' => ['0042 - UNIT DAGANGAN (Madiun)' => 250]]);
        $payload = (new DashboardHarianSnapshotService())->buildKeragaanPdfPayload('2026-09-30', '2026-09-01', ['KC Madiun'], false);
        $rows = collect($payload['sections'][0]['rows'])->keyBy('label');
        $this->assertSame(['KC Madiun', 'Mikro'], $rows->keys()->all());
        $this->assertSame(150.0, $rows['KC Madiun']['values']['rka']);
        $this->assertNull($rows['Mikro']['values']['rka']);
        $this->assertNull($rows['Mikro']['values']['current']);
        $this->assertNull($rows['Mikro']['deltas']['mtd']);
        $this->assertNull($rows['Mikro']['values']['mtd']);
        $this->assertNull($payload['sections'][0]['total']['values']['current']);
    }

    public function test_branch_savings_total_groups_units_as_micro_without_changing_other_sections_or_totals(): void
    {
        foreach (['2025-12-31', '2026-08-30', '2026-08-31', '2026-09-29', '2026-09-30'] as $period) {
            foreach ([
                'kc-madiun-detail' => ['KC Madiun', 100],
                'kcp-sudirman' => ['KCP Sudirman', 50],
                'unit-one' => ['UNIT One', 30],
                'unit-two' => ['UNIT Two', 20],
            ] as $unitKey => [$label, $value]) {
                $this->snapshot($period, 'KC Madiun', $unitKey, $label, $value);
            }
        }
        $this->mockRka('uker', [
            'giro_ritel' => ['KC MADIUN' => 110, 'KCP SUDIRMAN' => 55],
            'giro_mikro' => ['UNIT ONE' => 35, 'UNIT TWO' => 25],
        ]);
        $sections = collect((new DashboardHarianSnapshotService())->buildKeragaanPdfPayload('2026-09-30', '2026-09-01', ['KC Madiun'], false)['sections'])->keyBy('key');
        $savings = $sections['total_simpanan'];
        $this->assertSame(['KC Madiun', 'KCP Sudirman', 'Mikro'], array_column($savings['rows'], 'label'));
        $this->assertSame(50.0, $savings['rows'][2]['values']['current']);
        $this->assertSame(50.0, $savings['rows'][2]['values']['mtd']);
        $this->assertSame(60.0, $savings['rows'][2]['values']['rka']);
        $this->assertSame(-10.0, $savings['rows'][2]['deltas']['rka']);
        $this->assertEqualsWithDelta(83.33, $savings['rows'][2]['achievement'], 0.01);
        $this->assertSame(200.0, $savings['total']['values']['current']);
        $this->assertSame(225.0, $savings['total']['values']['rka']);
        $this->assertFalse($savings['totals_include_hidden_rows']);
        $this->assertSame(['KC Madiun', 'KCP Sudirman', 'UNIT One', 'UNIT Two'], array_column($sections['total_os']['rows'], 'label'));
    }

    public function test_branch_filters_inactive_and_segment_offices_without_losing_comparisons_or_targets(): void
    {
        $offices = [
            'kc-madiun-detail' => ['KC Madiun', ['giro_ritel' => 100, 'kecil_non_cashcoll_os' => 50]],
            'kcp-active' => ['KCP Active', ['giro_wholesale' => 40, 'kpr_os' => 30]],
            'kcp-loan' => ['KCP Loan', ['kupedes_os' => 20]],
            'unit-active' => ['UNIT Active', ['giro_mikro' => 80, 'giro_wholesale' => 7, 'kupedes_os' => 60]],
            'unit-closed' => ['UNIT Closed', []],
        ];
        foreach ($offices as $unitKey => [$label, $metrics]) {
            $this->snapshot('2026-09-30', 'KC Madiun', $unitKey, $label, 0);
            DB::table(DashboardHarianSnapshotService::SNAPSHOT_TABLE)->where('unit_key', $unitKey)->update($metrics ?: ['giro_ritel' => 0]);
            $this->snapshot('2026-08-31', 'KC Madiun', $unitKey, $label, 10);
        }
        $this->mockRka('uker', ['giro_ritel' => array_fill_keys(array_column($offices, 0), 5)]);
        $payload = (new DashboardHarianSnapshotService())->buildKeragaanPdfPayload('2026-09-30', '2026-09-01', ['KC Madiun'], false);
        $sections = collect($payload['sections'])->keyBy('key');
        $labels = fn (string $key): array => array_column($sections[$key]['rows'], 'label');
        $this->assertSame(['KC Madiun', 'KCP Active', 'Mikro'], $labels('total_simpanan'));
        $this->assertSame(['KC Madiun'], $labels('simpanan_ritel'));
        $this->assertSame(['UNIT Active'], $labels('simpanan_mikro'));
        $this->assertSame(['KCP Active'], $labels('simpanan_wholesale'));
        $this->assertSame(['KC Madiun'], $labels('sme_os'));
        $this->assertSame(['KC Madiun'], $labels('sme_sml'));
        $this->assertSame(['KC Madiun'], $labels('sme_npl'));
        $this->assertSame(['KC Madiun', 'KCP Active'], $labels('consumer_os'));
        $this->assertSame(['KC Madiun'], $labels('consumer_sml'));
        $this->assertSame(['KC Madiun'], $labels('consumer_npl'));
        $this->assertSame(['KC Madiun', 'KCP Active', 'KCP Loan', 'UNIT Active'], $labels('micro_os'));
        $this->assertSame(0.0, $sections['micro_os']['rows'][0]['values']['current']);
        $this->assertSame(0.0, $sections['micro_os']['rows'][1]['values']['current']);
        foreach ($sections as $section) {
            $this->assertNotContains('UNIT Closed', array_column($section['rows'], 'label'));
        }
        $this->assertSame(227.0, $sections['total_simpanan']['total']['values']['current']);
        $this->assertSame(7.0, $sections['simpanan_wholesale']['hidden_current_value']);
        $this->assertSame(47.0, $sections['simpanan_wholesale']['total']['values']['current']);
        $this->assertSame(50.0, $sections['total_simpanan']['total']['values']['mtd']);
        $this->assertSame(25.0, $sections['total_simpanan']['total']['values']['rka']);
        $this->assertTrue($sections['total_simpanan']['totals_include_hidden_rows']);
        $this->assertSame(2, $sections['total_simpanan']['hidden_row_count']);
        $this->assertCount(4, $payload['active_offices']);
        $this->assertNotContains('UNIT Closed', array_column($payload['active_offices'], 'unit_label'));
    }

    public function test_zero_balance_office_target_is_retained_in_total_but_not_displayed(): void
    {
        $this->snapshot('2026-09-30', 'KC Madiun', 'kc-madiun-detail', 'KC Madiun', 100);
        $this->snapshot('2026-09-30', 'KC Madiun', 'unit-zero', 'UNIT Zero', 0);
        $this->mockRka('uker', ['giro_ritel' => ['KC MADIUN' => 110, 'UNIT ZERO' => 15]]);
        $payload = (new DashboardHarianSnapshotService())->buildKeragaanPdfPayload('2026-09-30', '2026-09-01', ['KC Madiun'], false);
        $savings = $payload['sections'][0];
        $this->assertSame(['KC Madiun'], array_column($savings['rows'], 'label'));
        $this->assertSame(100.0, $savings['total']['values']['current']);
        $this->assertSame(125.0, $savings['total']['values']['rka']);
        $this->assertSame(1, $savings['hidden_row_count']);
        $this->assertSame(['KC Madiun'], array_column($payload['active_offices'], 'unit_label'));
    }

    public function test_area_map_uses_only_current_active_detail_offices_without_changing_summary_rows(): void
    {
        $this->snapshot('2026-09-30', 'KC Madiun', 'kc-madiun', 'KC Madiun', 100);
        $this->snapshot('2026-09-30', 'KC Madiun', 'kc-madiun-detail', 'KC Madiun', 30);
        $this->snapshot('2026-09-30', 'KC Madiun', 'unit-active', 'UNIT Active', 70);
        $this->snapshot('2026-09-30', 'KC Madiun', 'unit-closed', 'UNIT Closed', 0);
        $this->snapshot('2026-08-31', 'KC Madiun', 'unit-history', 'UNIT History', 99);
        $this->snapshot('2026-09-30', 'KC Ngawi', 'unit-outsider', 'UNIT Outsider', 999);
        $this->mockRka('kanca', []);
        $payload = (new DashboardHarianSnapshotService())->buildKeragaanPdfPayload('2026-09-30', '2026-09-01', ['KC Madiun'], true);
        $this->assertSame(['KC Madiun', 'UNIT Active'], array_column($payload['active_offices'], 'unit_label'));
        $this->assertSame([30.0, 70.0], array_column($payload['active_offices'], 'total_os'));
        $this->assertSame([30.0, 70.0], array_column($payload['active_offices'], 'total_simpanan'));
        foreach ($payload['sections'] as $section) {
            $this->assertSame(['KC Madiun'], array_column($section['rows'], 'label'));
            $this->assertFalse($section['totals_include_hidden_rows']);
        }
    }

    public function test_missing_selected_current_branch_fails_instead_of_exporting_a_partial_area(): void
    {
        $this->snapshot('2026-09-30', 'KC Madiun', 'kc-madiun', 'KC Madiun', 100);
        $this->expectException(ValidationException::class);
        (new DashboardHarianSnapshotService())->buildKeragaanPdfPayload('2026-09-30', null, ['KC Madiun', 'KC Ngawi'], true);
    }

    public function test_achievement_preserves_dashboard_zero_balance_semantics_and_missing_targets(): void
    {
        $this->snapshot('2026-09-30', 'KC Madiun', 'kc-madiun', 'KC Madiun', 0);
        $this->snapshot('2026-09-30', 'KC Ngawi', 'kc-ngawi', 'KC Ngawi', 100);
        $this->snapshot('2026-09-30', 'KC Magetan', 'kc-magetan', 'KC Magetan', 100);
        $this->mockRka('kanca', [
            'kecil_non_cashcoll_sml' => ['KC MADIUN' => 10, 'KC NGAWI' => 0],
            'total_simpanan' => ['KC MADIUN' => 0, 'KC NGAWI' => 0],
        ]);
        $payload = (new DashboardHarianSnapshotService())->buildKeragaanPdfPayload('2026-09-30', '2026-09-01', ['KC Madiun', 'KC Ngawi', 'KC Magetan'], true);
        $sml = collect($payload['sections'][8]['rows'])->keyBy('label');
        $this->assertSame(999.99, $sml['KC Madiun']['achievement']);
        $this->assertSame(0.0, $sml['KC Ngawi']['values']['rka']);
        $this->assertSame(0.0, $sml['KC Ngawi']['achievement']);
        $this->assertNull($sml['KC Magetan']['values']['rka']);
        $this->assertNull($sml['KC Magetan']['achievement']);
        $this->assertNull($payload['sections'][8]['total']['values']['rka']);
        $this->assertNull($payload['sections'][8]['total']['achievement']);
        $savings = collect($payload['sections'][0]['rows'])->keyBy('label');
        $this->assertSame(0.0, $savings['KC Madiun']['achievement']);
    }

    public function test_empty_scope_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        (new DashboardHarianSnapshotService())->buildKeragaanPdfPayload('2026-09-30', null, [], true);
    }

    private function mockRka(string $group, array $metrics): void
    {
        $mock = \Mockery::mock(RkaLookupService::class);
        $mock->shouldReceive('resolveMonthColumn')->once()->with('2026-09-01')->andReturn('sep');
        $mock->shouldReceive('aggregateByGroup')->once()->withArgs(fn ($definitions, $month, $branches, $units, $groupBy, $year): bool => $groupBy === $group && $month === 'sep' && $year === 2026 && $units === [])->andReturn($metrics);
        $this->app->instance(RkaLookupService::class, $mock);
    }

    private function snapshot(string $period, string $branch, string $unitKey, string $label, float $value): void
    {
        DB::table(DashboardHarianSnapshotService::SNAPSHOT_TABLE)->insert([
            'snapshot_period' => $period, 'kanca_key' => \Illuminate\Support\Str::slug($branch),
            'kanca_label' => $branch, 'unit_key' => $unitKey, 'unit_label' => $label,
            'giro_ritel' => $value, 'kecil_non_cashcoll_os' => $value,
            'kecil_non_cashcoll_sml' => $value / 10,
        ]);
    }
}
