<?php

namespace Tests\Unit;

use App\Http\Controllers\Report\KinerjaRmMikroReportController;
use App\Support\MicroNettDisbursementCalculator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class RmKurKanwilFormulaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        Config::set('cache.default', 'array');
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        DB::connection()->getPdo()->sqliteCreateFunction('CONCAT', fn (...$values) => implode('', $values), -1);

        Schema::create('daily_loan_dinamis', function (Blueprint $table): void {
            $table->id();
            foreach ([
                'periode', 'tgl_realisasi', 'cabang_normalized', 'cabang1', 'unit_normalized', 'unit1',
                'branch_normalized', 'branch1', 'pn_pemrakarsa1', 'pn_pengelola1', 'nomor_rekening1',
                'cifno', 'segmen_kinerja', 'produk_kinerja', 'description', 'rm_normalized',
            ] as $column) {
                $table->string($column)->nullable();
            }
            $table->decimal('plafon', 20, 2)->nullable();
            $table->decimal('baki_debet1', 20, 2)->nullable();
            $table->unsignedTinyInteger('kolek')->nullable();
            $table->unsignedTinyInteger('kol_adk1')->nullable();
        });
        Cache::flush();
    }

    public function test_realization_uses_singlepn_manager_and_gross_plafond_for_every_period(): void
    {
        foreach (['2026-09-16', '2026-10-16'] as $period) {
            $previous = substr($period, 0, 7) === '2026-09' ? '2026-08-31' : '2026-09-30';
            DB::table('daily_loan_dinamis')->insert([
                $this->row($previous, 'OLD-'.$period, 400_000_000, ['tgl_realisasi' => '2025-01-01']),
                $this->row($period, 'NEW-'.$period, 250_000_000),
            ]);

            $rows = app(MicroNettDisbursementCalculator::class)->kurRm($period);

            $this->assertCount(1, $rows);
            $this->assertSame('00011111 - MANAGER', $rows[0]['rm']);
            $this->assertSame(1, $rows[0]['realisasi_deb']);
            $this->assertSame(250_000_000.0, $rows[0]['realisasi_os']);
        }
    }

    public function test_snapshot_cutoff_excludes_closed_earlier_rows_and_future_positions_or_bookings(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->row('2026-10-15', 'DISAPPEARED', 900_000_000),
            $this->row('2026-10-16', 'CURRENT', 125_000_000),
            $this->row('2026-10-16', 'FUTURE-BOOKING', 800_000_000, ['tgl_realisasi' => '2026-10-17']),
            $this->row('2026-10-18', 'FUTURE-POSITION', 700_000_000),
        ]);

        $calculator = app(MicroNettDisbursementCalculator::class);
        $rows = $calculator->kurRm('2026-10-16');

        $this->assertCount(1, $rows);
        $this->assertSame(1, $rows[0]['realisasi_deb']);
        $this->assertSame(125_000_000.0, $rows[0]['realisasi_os']);
        $this->assertSame([], $calculator->kurRm('2026-10-17'));
    }

    public function test_realization_deduplicates_accounts_and_keeps_only_the_four_area_6_branches(): void
    {
        foreach (['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO', 'KC BANYUWANGI'] as $i => $branch) {
            $row = $this->row('2026-10-16', (string) (100 + $i), 100_000_000, [
                'cabang1' => $branch, 'cabang_normalized' => $branch,
                'unit1' => $branch, 'unit_normalized' => $branch,
            ]);
            DB::table('daily_loan_dinamis')->insert($row);
            DB::table('daily_loan_dinamis')->insert(array_merge($row, [
                'nomor_rekening1' => '000'.(100 + $i), 'plafon' => 90_000_000,
            ]));
        }

        $rows = collect(app(MicroNettDisbursementCalculator::class)->kurRm('2026-10-16'));

        $this->assertCount(4, $rows);
        $this->assertSame(4, $rows->sum('realisasi_deb'));
        $this->assertSame(400_000_000.0, $rows->sum('realisasi_os'));
        $this->assertFalse($rows->contains('cabang', 'KC BANYUWANGI'));
    }

    public function test_position_uses_positive_outstanding_balance_and_realization_keeps_gross_plafond(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->row('2026-10-16', 'LANCAR', 500_000_000, ['baki_debet1' => 120_000_000]),
            $this->row('2026-10-16', 'SML', 400_000_000, ['baki_debet1' => 60_000_000, 'kolek' => 2]),
            $this->row('2026-10-16', 'NPL', 300_000_000, ['baki_debet1' => 30_000_000, 'kolek' => 5]),
            $this->row('2026-10-16', 'ZERO', 250_000_000, ['baki_debet1' => 0, 'tgl_realisasi' => '2025-01-01']),
        ]);
        $controller = new KinerjaRmMikroReportController;
        $rows = (new ReflectionMethod($controller, 'sourceKurMikroAggregates'))
            ->invoke($controller, '2026-10-16', ['rm']);

        $this->assertCount(1, $rows);
        $row = $rows->first();
        $this->assertSame(3, (int) $row['total_deb']);
        $this->assertSame(210_000_000.0, (float) $row['total_os']);
        foreach (['lancar' => 120_000_000.0, 'sml' => 60_000_000.0, 'npl' => 30_000_000.0] as $tier => $balance) {
            $this->assertSame(1, (int) $row[$tier.'_deb']);
            $this->assertSame($balance, (float) $row[$tier.'_os']);
        }
        $this->assertSame(3, (int) $row['realisasi_deb']);
        $this->assertSame(1_200_000_000.0, (float) $row['realisasi_os']);
    }

    public function test_only_positive_plafond_bookings_count_while_gross_sums_keep_source_negative_values(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->row('2026-10-16', 'POSITIVE-W1', 300_000_000),
            $this->row('2026-10-16', 'ZERO-W1', 0),
            $this->row('2026-10-16', 'NEGATIVE-W1', -20_000_000),
            $this->row('2026-10-16', 'POSITIVE-W2', 100_000_000, ['tgl_realisasi' => '2026-10-10']),
        ]);

        $row = app(MicroNettDisbursementCalculator::class)->kurRm('2026-10-16')[0];

        $this->assertSame(2, $row['realisasi_deb']);
        $this->assertSame(380_000_000.0, $row['realisasi_os']);
        $this->assertSame(1, $row['w1_realisasi_deb']);
        $this->assertSame(280_000_000.0, $row['w1_realisasi_os']);
        $this->assertSame(1, $row['w2_realisasi_deb']);
        $this->assertSame(100_000_000.0, $row['w2_realisasi_os']);
        $this->assertSame(1, $row['lt_250_realisasi_deb']);
        $this->assertSame(80_000_000.0, $row['lt_250_realisasi_os']);
        $this->assertSame(1, $row['gt_250_realisasi_deb']);
        $this->assertSame(300_000_000.0, $row['gt_250_realisasi_os']);
        $source = (new ReflectionMethod(KinerjaRmMikroReportController::class, 'sourceKurMikroAggregates'))
            ->invoke(new KinerjaRmMikroReportController, '2026-10-16', ['rm'])->first();
        $this->assertSame(2, (int) $source['realisasi_deb']);
        $this->assertSame(380_000_000.0, (float) $source['realisasi_os']);
    }

    public function test_working_days_excludes_national_holidays_in_historical_periods(): void
    {
        $controller = new KinerjaRmMikroReportController;
        $method = new ReflectionMethod($controller, 'networkDays');

        $this->assertSame(12, $method->invoke($controller, '2026-10-01', '2026-10-16'));
        $this->assertSame(3, $method->invoke($controller, '2026-05-01', '2026-05-06'));
    }

    private function row(string $period, string $account, float $plafon, array $overrides = []): array
    {
        return array_merge([
            'periode' => $period,
            'tgl_realisasi' => substr($period, 0, 7).'-05',
            'cabang1' => 'KC MADIUN', 'cabang_normalized' => 'KC MADIUN',
            'unit1' => 'KC MADIUN', 'unit_normalized' => 'KC MADIUN',
            'branch1' => '45', 'branch_normalized' => '45',
            'pn_pengelola1' => '00011111 - MANAGER',
            'pn_pemrakarsa1' => '00022222 - INITIATOR',
            'rm_normalized' => '00011111 - MANAGER',
            'nomor_rekening1' => $account, 'cifno' => 'SAME-CIF',
            'segmen_kinerja' => 'MICRO', 'produk_kinerja' => 'KURKECIL',
            'description' => 'Kredit Mikro - KUR Ritel 2015',
            'plafon' => $plafon, 'baki_debet1' => $plafon,
            'kolek' => 1, 'kol_adk1' => 1,
        ], $overrides);
    }
}
