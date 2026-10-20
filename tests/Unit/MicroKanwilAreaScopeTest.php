<?php

namespace Tests\Unit;

use App\Support\MantriPerformanceBasis;
use App\Support\MicroNettDisbursementCalculator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MicroKanwilAreaScopeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('daily_loan_dinamis', function (Blueprint $table): void {
            $table->id();
            foreach (['periode', 'tgl_realisasi', 'cabang_normalized', 'cabang1', 'unit_normalized', 'unit1', 'branch_normalized', 'branch1', 'pn_pemrakarsa1', 'pn_pengelola1', 'nomor_rekening1', 'cifno', 'segmen_kinerja', 'produk_kinerja', 'description'] as $column) {
                $table->string($column)->nullable();
            }
            $table->decimal('plafon', 20, 2)->nullable();
            $table->decimal('baki_debet1', 20, 2)->nullable();
        });
        Schema::create('brihc_pemasar', function (Blueprint $table): void {
            $table->string('uniqueid_namareport')->primary();
            foreach (['pernr', 'pn_mantri', 'completename', 'positiondesc', 'psadesc', 'orgdesc', 'esgdesc', 'bc'] as $column) {
                $table->string($column)->nullable();
            }
        });
        DB::table('brihc_pemasar')->insert([
            'uniqueid_namareport' => 'reference_brihc_hc_active', 'pernr' => '123',
            'completename' => 'Mantri Area 6', 'positiondesc' => 'MANTRI',
            'psadesc' => 'KC Madiun', 'orgdesc' => 'UNIT A', 'esgdesc' => 'PT', 'bc' => '3200',
        ]);
    }

    public function test_current_bookings_outside_area_six_are_excluded_even_with_an_active_area_six_owner_and_the_same_cif(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->row('AREA-BOOKING', 100_000_000),
            $this->row('OUTSIDE-BOOKING', 900_000_000, ['cabang_normalized' => 'KC JEMBER', 'cabang1' => 'KC Jember']),
        ]);
        $roster = app(MantriPerformanceBasis::class)->rosterState('2026-11-16');
        $this->assertSame(['123'], $roster['keys']);

        $accounts = app(MicroNettDisbursementCalculator::class)->mantriAccounts('2026-11-16');

        $this->assertCount(1, $accounts);
        $this->assertSame('AREA-BOOKING', $accounts[0]['account_key']);
        $this->assertSame('KC MADIUN', $accounts[0]['cabang']);
        $this->assertSame(100_000_000.0, $accounts[0]['net_amount']);
    }

    public function test_prior_cif_exposure_outside_area_six_is_not_subtracted_from_an_area_six_booking(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->row('AREA-OLD', 20_000_000, ['periode' => '2026-10-31', 'tgl_realisasi' => '2026-10-10']),
            $this->row('OUTSIDE-OLD', 80_000_000, ['periode' => '2026-10-31', 'tgl_realisasi' => '2026-10-10', 'cabang_normalized' => 'KC JEMBER', 'cabang1' => 'KC Jember']),
            $this->row('AREA-BOOKING', 100_000_000),
        ]);

        $accounts = app(MicroNettDisbursementCalculator::class)->mantriAccounts('2026-11-16');

        $this->assertCount(1, $accounts);
        $this->assertSame(20_000_000.0, $accounts[0]['previous_os']);
        $this->assertSame(80_000_000.0, $accounts[0]['net_amount']);
    }

    private function row(string $account, float $amount, array $overrides = []): array
    {
        return array_merge([
            'periode' => '2026-11-16', 'tgl_realisasi' => '2026-11-10',
            'cabang_normalized' => 'KC MADIUN', 'cabang1' => 'KC Madiun',
            'unit_normalized' => 'UNIT A', 'unit1' => 'Unit A', 'branch_normalized' => '3200', 'branch1' => '3200',
            'pn_pemrakarsa1' => '000123 - Mantri Area 6', 'pn_pengelola1' => '000123 - Mantri Area 6',
            'nomor_rekening1' => $account, 'cifno' => 'CIF-SAME', 'segmen_kinerja' => 'MICRO',
            'produk_kinerja' => 'KUPEDES', 'description' => 'Kupedes',
            'plafon' => $amount, 'baki_debet1' => $amount,
        ], $overrides);
    }
}
