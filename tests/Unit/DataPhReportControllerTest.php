<?php

namespace Tests\Unit;

use App\Http\Controllers\Report\DataPhReportController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class DataPhReportControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::dropAllTables();

        Schema::create('lw325_ph', function (Blueprint $table): void {
            $table->string('uniqueid_namareport')->nullable();
            $table->date('periode')->index();
            $table->string('acctno')->nullable();
            $table->string('kanwil')->nullable();
            $table->string('kanca')->nullable();
            $table->string('unit')->nullable();
            $table->string('nama_debitur')->nullable();
            $table->string('segmen_dashboard')->nullable();
            $table->string('produk_dashboard')->nullable();
            $table->decimal('pokok', 20, 2)->nullable();
        });

        Schema::create('cognos_recovery', function (Blueprint $table): void {
            $table->date('periode')->index();
            $table->string('cabang')->nullable();
            $table->string('unit_kerja')->nullable();
            $table->string('segmen_2')->nullable();
            $table->string('produk')->nullable();
            $table->decimal('total_recovery', 20, 2)->nullable();
        });
    }

    public function test_branch_data_ph_recovery_falls_back_to_cognos_for_old_period_without_ph_pair(): void
    {
        DB::table('lw325_ph')->insert([
            [
                'periode' => '2025-04-30',
                'acctno' => 'A1',
                'kanwil' => 'KANWIL MALANG',
                'kanca' => 'KC Madiun',
                'unit' => 'UNIT A',
                'segmen_dashboard' => 'Micro',
                'produk_dashboard' => 'Kupedes',
                'pokok' => 1000000,
            ],
        ]);

        DB::table('cognos_recovery')->insert([
            [
                'periode' => '2025-04-30',
                'cabang' => 'KC Madiun',
                'unit_kerja' => 'UNIT A',
                'segmen_2' => 'Micro',
                'produk' => 'Kupedes',
                'total_recovery' => 125000,
            ],
            [
                'periode' => '2025-04-30',
                'cabang' => 'KC Madiun',
                'unit_kerja' => 'UNIT A',
                'segmen_2' => 'Consumer',
                'produk' => 'BRIGUNA KONSUMER',
                'total_recovery' => 50000,
            ],
            [
                'periode' => '2025-04-30',
                'cabang' => 'KC Madiun',
                'unit_kerja' => 'UNIT A',
                'segmen_2' => 'Consumer',
                'produk' => 'KPR',
                'total_recovery' => 75000,
            ],
        ]);

        $metrics = $this->invokePrivate('getBranchOfficeDataPhRecoveryMetrics', ['2025-04-30', ['KC Madiun']]);

        $this->assertSame(125000.0, $metrics['KC Madiun']['micro']);
        $this->assertSame(50000.0, $metrics['KC Madiun']['consumer_briguna']);
        $this->assertSame(75000.0, $metrics['KC Madiun']['consumer_kpr']);
        $this->assertSame(250000.0, $metrics['KC Madiun']['total']);
    }

    public function test_branch_data_ph_recovery_does_not_count_missing_current_ph_period_as_all_lunas(): void
    {
        DB::table('lw325_ph')->insert([
            [
                'periode' => '2025-04-30',
                'acctno' => 'A1',
                'kanwil' => 'KANWIL MALANG',
                'kanca' => 'KC Madiun',
                'unit' => 'UNIT A',
                'segmen_dashboard' => 'Micro',
                'produk_dashboard' => 'Kupedes',
                'pokok' => 1000000,
            ],
        ]);

        $metrics = $this->invokePrivate('getBranchOfficeDataPhRecoveryMetrics', ['2025-05-31', ['KC Madiun']]);

        $this->assertSame(0.0, $metrics['KC Madiun']['total']);
    }

    public function test_branch_data_ph_recovery_matches_lw325_by_normalized_account_and_previous_branch(): void
    {
        DB::table('lw325_ph')->insert([
            [
                'periode' => '2026-05-31',
                'acctno' => '000649201027193100',
                'kanwil' => 'KANWIL MALANG',
                'kanca' => 'KC Madiun',
                'unit' => 'UNIT A',
                'segmen_dashboard' => 'Micro',
                'produk_dashboard' => 'Kupedes',
                'pokok' => 1000000,
            ],
            [
                'periode' => '2026-06-30',
                'acctno' => '649201027193100',
                'kanwil' => 'KANWIL MALANG',
                'kanca' => 'KC Magetan',
                'unit' => 'UNIT B',
                'segmen_dashboard' => 'Micro',
                'produk_dashboard' => 'Kupedes',
                'pokok' => 700000,
            ],
        ]);

        $metrics = $this->invokePrivate('getBranchOfficeDataPhRecoveryMetrics', ['2026-06-30', ['KC Madiun']]);

        $this->assertSame(300000.0, $metrics['KC Madiun']['micro']);
        $this->assertSame(300000.0, $metrics['KC Madiun']['total']);
    }

    public function test_branch_data_ph_recovery_uses_latest_available_previous_month_ph_period(): void
    {
        DB::table('lw325_ph')->insert([
            [
                'periode' => '2026-06-29',
                'acctno' => '649201027193100',
                'kanwil' => 'KANWIL MALANG',
                'kanca' => 'KC Madiun',
                'unit' => 'UNIT A',
                'segmen_dashboard' => 'Micro',
                'produk_dashboard' => 'Kupedes',
                'pokok' => 1000000,
            ],
            [
                'periode' => '2026-07-10',
                'acctno' => '649201027193100',
                'kanwil' => 'KANWIL MALANG',
                'kanca' => 'KC Madiun',
                'unit' => 'UNIT A',
                'segmen_dashboard' => 'Micro',
                'produk_dashboard' => 'Kupedes',
                'pokok' => 750000,
            ],
        ]);

        $metrics = $this->invokePrivate('getBranchOfficeDataPhRecoveryMetrics', ['2026-07-10', ['KC Madiun']]);

        $this->assertSame(250000.0, $metrics['KC Madiun']['micro']);
        $this->assertSame(250000.0, $metrics['KC Madiun']['total']);
    }

    public function test_data_ph_period_label_uses_day_short_month_two_digit_year(): void
    {
        $this->assertSame('31 Dec 25', $this->invokePrivate('formatDataPhPeriodLabel', ['2025-12-31']));
    }

    public function test_nominatif_endpoint_filters_lw325_rows_and_hides_unique_id(): void
    {
        DB::table('lw325_ph')->insert([
            [
                'uniqueid_namareport' => 'PH-1',
                'periode' => '2026-06-27',
                'acctno' => '111',
                'kanwil' => 'KANWIL MALANG',
                'kanca' => 'KC Madiun',
                'unit' => 'UNIT A',
                'nama_debitur' => 'Debitur Small',
                'segmen_dashboard' => 'Small',
                'produk_dashboard' => 'Kecil',
                'pokok' => 1250000,
            ],
            [
                'uniqueid_namareport' => 'PH-2',
                'periode' => '2026-06-27',
                'acctno' => '222',
                'kanwil' => 'KANWIL MALANG',
                'kanca' => 'KC Madiun',
                'unit' => 'UNIT A',
                'nama_debitur' => 'Debitur Mikro',
                'segmen_dashboard' => 'Micro',
                'produk_dashboard' => 'Kupedes',
                'pokok' => 2500000,
            ],
        ]);

        $controller = new DataPhReportController();
        $request = Request::create('/report/dashboard-pinjaman/data-ph/nominatif', 'GET', [
            'periode' => '2026-06-27',
            'segment' => 'small',
            'kanca' => 'KC Madiun',
            'unit_kerja' => 'UNIT A',
        ]);
        $payload = $controller->nominatif($request)->getData(true);

        $this->assertSame(1, $payload['total_count']);
        $this->assertSame(1, $payload['display_count']);
        $this->assertEqualsWithDelta(1250000.0, $payload['total_pokok'], 0.01);
        $this->assertSame('111', $payload['rows'][0]['acctno']);
        $this->assertSame('Debitur Small', $payload['rows'][0]['nama_debitur']);
        $this->assertArrayNotHasKey('uniqueid_namareport', $payload['rows'][0]);
        $this->assertNotContains('uniqueid_namareport', array_column($payload['columns'], 'key'));
    }

    public function test_data_ph_view_registers_double_click_nominatif_modal(): void
    {
        $view = file_get_contents(resource_path('views/report/data-ph.blade.php'));

        $this->assertStringContainsString('data-ph-nominatif-row', $view);
        $this->assertStringContainsString('data-abah-no-table-guard="1"', $view);
        $this->assertStringContainsString('max-height: min(72dvh, 760px) !important;', $view);
        $this->assertStringNotContainsString('overflow-y: visible !important;', $view);
        $this->assertStringContainsString('sticky-table-viewport-script', $view);
        $this->assertStringContainsString('--ph-index-column-width: 64px;', $view);
        $this->assertStringContainsString('left: var(--ph-index-column-width) !important;', $view);
        $this->assertStringContainsString('ph-sticky-index', $view);
        $this->assertStringContainsString('ph-sticky-scope', $view);
        $this->assertStringContainsString('grid-template-columns: repeat(2, minmax(0, 1fr));', $view);
        $this->assertStringContainsString('grid-template-columns: minmax(0, 1fr);', $view);
        $this->assertStringContainsString('.filter-section.p-4 {', $view);
        $this->assertMatchesRegularExpression('/\\.loan-dropdown-menu\\s*\\{[^}]*min-width:\\s*0;[^}]*max-width:\\s*100%;/s', $view);
        $this->assertStringContainsString('data-segment="small"', $view);
        $this->assertStringContainsString('data-period="{{ $selectedPeriod }}"', $view);
        $this->assertStringContainsString('phNominatifModal', $view);
        $this->assertStringContainsString("route('report.dashboard-pinjaman.data-ph.nominatif')", $view);
        $this->assertStringContainsString("row.addEventListener('dblclick'", $view);
        $this->assertStringContainsString('periode: row.dataset.period', $view);
        $this->assertStringContainsString('segment: row.dataset.segment', $view);
    }

    public function test_data_ph_uses_direct_period_predicates_so_the_ph_period_index_can_be_used(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Report/DataPhReportController.php'));

        $this->assertIsString($controller);
        $this->assertStringNotContainsString('whereDate(', $controller);
        $this->assertStringContainsString("->where('periode', \$period)", $controller);
    }

    private function invokePrivate(string $methodName, array $arguments = [])
    {
        $controller = new DataPhReportController();
        $method = new ReflectionMethod(DataPhReportController::class, $methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($controller, $arguments);
    }
}
