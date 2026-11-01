<?php

namespace Tests\Unit;

use App\Http\Controllers\Report\AlmafactsDashboardController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class AlmafactsTimeseriesRatioTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('ssa_almafacts', function (Blueprint $table): void {
            $table->id();
            $table->date('month_day_year_of_posisi');
            $table->string('kanca_konsolidasi');
            $table->string('kode_unit_kerja')->nullable();
            $table->string('unit_kerja')->nullable();
            $table->string('keterangan');
            $table->decimal('saldo', 30, 12)->nullable();
        });

        // Two units with very different LDRs: 200% and 66.67%.
        $this->row('6001', 'UNIT A', '33. Pinjaman', 100_000_000);
        $this->row('6001', 'UNIT A', '34. Simpanan', 50_000_000);
        $this->row('6001', 'UNIT A', '30. LDR (%)', 2.0);
        $this->row('6001', 'UNIT A', '42. Lancar Restru (%)', 0.1);
        $this->row('6001', 'UNIT A', '15. Laba Setelah Pajak', 4_000_000);
        $this->row('6002', 'UNIT B', '33. Pinjaman', 300_000_000);
        $this->row('6002', 'UNIT B', '34. Simpanan', 150_000_000);
        $this->row('6002', 'UNIT B', '30. LDR (%)', 2.0);
        $this->row('6002', 'UNIT B', '42. Lancar Restru (%)', 0.3);
        $this->row('6002', 'UNIT B', '15. Laba Setelah Pajak', 6_000_000);
    }

    public function test_branch_ratio_is_computed_from_components_not_summed(): void
    {
        $payload = $this->payload('30. LDR (%)', 'KC Madiun', 'all');

        $this->assertSame('percent', $payload['value_type']);
        // (100 + 300) / (50 + 150) = 200%, never 2.0 + 2.0 scaled to Rp juta (which rounded to 0).
        $this->assertSame(200.0, $payload['summary']['datasets'][0]['data'][8]);
        $this->assertSame(['UNIT A', 'UNIT B'], array_keys($payload['series']));
        $this->assertNull($payload['summary']['datasets'][0]['data'][7], 'A month without source rows stays empty.');
    }

    public function test_single_unit_ratio_matches_its_own_components(): void
    {
        $payload = $this->payload('30. LDR (%)', 'KC Madiun', '6001');

        $this->assertSame(200.0, $payload['summary']['datasets'][0]['data'][8]);
        $this->assertSame(['UNIT A'], array_keys($payload['series']));
    }

    public function test_ratio_without_component_formula_uses_loan_weighted_average(): void
    {
        $payload = $this->payload('42. Lancar Restru (%)', 'area6', 'all');

        // (0.1 x 100 + 0.3 x 300) / 400 = 25%.
        $this->assertSame(25.0, $payload['summary']['datasets'][0]['data'][8]);
        $this->assertSame(25.0, $payload['series']['KC Madiun']['datasets'][0]['data'][8]);
        $this->assertSame([null, null, null, null, null, null, null, null, null, null, null, null], $payload['series']['KC Ngawi']['datasets'][0]['data']);
    }

    public function test_amount_metrics_keep_the_summed_rp_juta_payload(): void
    {
        $payload = $this->payload('15. Laba Setelah Pajak', 'KC Madiun', 'all');

        $this->assertSame('currency', $payload['value_type']);
        $this->assertSame(10.0, $payload['summary']['datasets'][0]['data'][8]);
    }

    private function payload(string $metric, string $branch, string $unit): array
    {
        $method = new ReflectionMethod(AlmafactsDashboardController::class, 'getTimeseriesPayload');

        return $method->invoke(new AlmafactsDashboardController(), $metric, $branch, $unit, 2026);
    }

    private function row(string $code, string $unit, string $metric, float $saldo): void
    {
        DB::table('ssa_almafacts')->insert([
            'month_day_year_of_posisi' => '2026-09-30',
            'kanca_konsolidasi' => 'KC Madiun',
            'kode_unit_kerja' => $code,
            'unit_kerja' => $unit,
            'keterangan' => $metric,
            'saldo' => $saldo,
        ]);
    }
}
