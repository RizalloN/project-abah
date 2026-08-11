<?php

namespace Tests\Unit;

use App\Support\HourlyDpkDashboardService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HourlyDpkDashboardServiceTest extends TestCase
{
    private string $originalDefaultConnection;
    private ?string $originalSqliteDatabase;
    private string $tempDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalDefaultConnection = (string) Config::get('database.default');
        $this->originalSqliteDatabase = Config::get('database.connections.sqlite.database');
        $this->tempDatabase = tempnam(sys_get_temp_dir(), 'abah_hourly_dpk_');

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', $this->tempDatabase);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('hourly_dpk', function (Blueprint $table): void {
            $table->string('uniqueid_namareport')->primary();
            $table->date('posisi');
            $table->dateTime('posisi_jam')->nullable();
            $table->string('mbname');
            $table->string('brname');
            $table->string('segmen')->nullable();
            $table->string('segmen2')->nullable();
            $table->string('produk');
            $table->decimal('saldo', 24, 2);
        });

        Schema::create('ssa_simpanan', function (Blueprint $table): void {
            $table->id();
            $table->date('Month_Day_Year_of_Posisi');
            $table->string('nama_cabang');
            $table->string('nama_uker')->nullable();
            $table->string('segmentasi')->nullable();
            $table->string('produk');
            $table->decimal('saldo', 24, 2);
        });
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        Config::set('database.default', $this->originalDefaultConnection);
        Config::set('database.connections.sqlite.database', $this->originalSqliteDatabase);
        DB::purge('sqlite');

        if (is_file($this->tempDatabase)) {
            unlink($this->tempDatabase);
        }

        parent::tearDown();
    }

    public function test_branch_payload_shows_uker_code_and_separate_retail_micro_subtotals(): void
    {
        $this->seedBranchRows();

        $payload = (new HourlyDpkDashboardService())->payload('KC Madiun', 'TABUNGAN');

        $this->assertSame(['dtd', 'mtd', 'ytd'], array_keys($payload['total']['delta_values']));
        $this->assertSame(4, $payload['dataRowCount']);
        $this->assertSame('00045', $payload['branchCode']);
        $this->assertSame('00045', collect($payload['rows'])->firstWhere('unit', 'KC Madiun')['unit_code']);
        $this->assertSame('08146', collect($payload['rows'])->firstWhere('unit', 'UNIT Pasar Condong')['unit_code']);
        $this->assertNotContains('', collect($payload['rows'])->where('row_type', 'detail')->pluck('unit_code')->all());

        $retailSubtotal = collect($payload['rows'])->firstWhere('row_type', 'subtotal_retail');
        $microSubtotal = collect($payload['rows'])->firstWhere('row_type', 'subtotal_micro');

        $this->assertSame('TOTAL RITEL - KC MADIUN', $retailSubtotal['unit']);
        $this->assertSame(30_000_000, (int) $retailSubtotal['hour_values'][$payload['latestHour']]);
        $this->assertSame('TOTAL MIKRO - KC MADIUN', $microSubtotal['unit']);
        $this->assertSame(70_000_000, (int) $microSubtotal['hour_values'][$payload['latestHour']]);
        $this->assertSame(100_000_000, (int) $payload['total']['hour_values'][$payload['latestHour']]);

        $detailUnitCodes = collect($payload['rows'])
            ->where('row_type', 'detail')
            ->pluck('unit_code')
            ->values()
            ->all();
        $this->assertSame(['00045', '02109', '08146', '08147'], $detailUnitCodes);
    }

    public function test_export_uses_segment_summary_for_branch_and_all_product_tables(): void
    {
        $this->seedBranchRows();

        $export = (new HourlyDpkDashboardService())->exportPayload('KC Madiun', 'RITEL');

        $this->assertSame(
            ['All Simpanan', 'Tabungan', 'Giro', 'Deposito'],
            array_column($export['tables'], 'label')
        );
        $this->assertSame('segment', $export['summaryType']);
        $this->assertSame('all', $export['selectedSegment']);
        $this->assertSame(['Ritel', 'Mikro'], array_column($export['summary'], 'segment'));
        $this->assertSame([30_000_000, 70_000_000], array_map('intval', array_column($export['summary'], 'posisi')));
        $this->assertSame(['yoy', 'ytd', 'mtm', 'mtd', 'h2', 'h1'], array_keys($export['summary'][0]['period_values']));
        $this->assertSame(['dtd', 'mtd', 'ytd'], array_keys($export['summary'][0]['delta_values']));
        $this->assertSame(22_000_000, (int) $export['summary'][0]['delta_values']['dtd']);
        $this->assertSame(62_000_000, (int) $export['summary'][1]['delta_values']['dtd']);
        $this->assertSame(100_000_000, (int) $export['summaryTotal']['hour_values'][$export['latestHour']]);
        $this->assertSame(84_000_000, (int) $export['summaryTotal']['delta_values']['dtd']);
        $allSavingsRows = collect($export['tables'][0]['payload']['rows']);
        $this->assertCount(1, $allSavingsRows->where('row_type', 'subtotal_retail'));
        $this->assertCount(1, $allSavingsRows->where('row_type', 'subtotal_micro'));
    }

    public function test_area_export_uses_branch_code_and_all_product_tables(): void
    {
        $this->seedBranchRows();

        $export = (new HourlyDpkDashboardService())->exportPayload('all');
        $allSavings = $export['tables'][0]['payload'];

        $this->assertSame(
            ['All Simpanan', 'Tabungan', 'Giro', 'Deposito'],
            array_column($export['tables'], 'label')
        );
        $this->assertSame('product', $export['summaryType']);
        $this->assertSame('00045', $allSavings['rows'][0]['branch_code']);
        $this->assertSame('KC Madiun', $allSavings['rows'][0]['branch']);
        $this->assertSame('', $allSavings['rows'][0]['unit_code']);
    }

    private function seedBranchRows(): void
    {
        $units = [
            ['code' => '08147', 'name' => 'UNIT Alon Alon', 'saldo' => 40_000_000],
            ['code' => '00045', 'name' => 'KC Madiun', 'saldo' => 10_000_000],
            ['code' => '08146', 'name' => 'UNIT Pasar Condong', 'saldo' => 30_000_000],
            ['code' => '02109', 'name' => 'KCP Dolopo', 'saldo' => 20_000_000],
        ];
        $historicalPeriods = [
            '2025-07-31' => 1_000_000,
            '2025-12-31' => 2_000_000,
            '2026-06-30' => 3_000_000,
            '2026-07-30' => 4_000_000,
        ];

        foreach ($units as $index => $unit) {
            DB::table('hourly_dpk')->insert([
                'uniqueid_namareport' => 'hourly-' . $index,
                'posisi' => '2026-07-31',
                'posisi_jam' => '2026-07-31 20:00:00',
                'mbname' => '00045 -- KC Madiun(Konsolidasi-MB)',
                'brname' => $unit['code'] . ' -- ' . $unit['name'],
                'segmen2' => str_starts_with($unit['name'], 'UNIT ') ? 'MIKRO' : 'RITEL',
                'produk' => 'TABUNGAN',
                'saldo' => $unit['saldo'],
            ]);

            foreach ($historicalPeriods as $period => $saldo) {
                DB::table('ssa_simpanan')->insert([
                    'Month_Day_Year_of_Posisi' => $period,
                    'nama_cabang' => 'KC Madiun',
                    'nama_uker' => $unit['code'] . ' -- ' . $unit['name'],
                    'segmentasi' => str_starts_with($unit['name'], 'UNIT ') ? 'MICRO' : 'RITEL',
                    'produk' => 'TABUNGAN',
                    'saldo' => $saldo,
                ]);
            }
        }
    }
}
