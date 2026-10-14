<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Reports\MantriIncentiveService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InsentifMantriReportTest extends TestCase
{
    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-12-31 10:00:00');
        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        Config::set('cache.default', 'array');
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::dropAllTables();
        Schema::create('daily_loan_dinamis', function (Blueprint $table) {
            $table->string('uniqueid_namareport')->primary();
            $table->date('periode');
            $table->string('segmen_kinerja')->nullable();
            $table->string('produk_kinerja')->nullable();
            $table->string('description')->nullable();
            $table->string('cabang1')->nullable();
            $table->string('unit1')->nullable();
            $table->string('pn_pengelola1')->nullable();
            $table->string('pn_pemrakarsa1')->nullable();
            $table->string('nomor_rekening1')->nullable();
            $table->string('cifno')->nullable();
            $table->string('nama_debitur1')->nullable();
            $table->decimal('plafon', 20, 2)->nullable();
            $table->decimal('baki_debet1', 20, 2)->nullable();
            $table->string('kolek')->nullable();
            $table->date('tgl_realisasi')->nullable();
        });
        Schema::create('brihc_pemasar', function (Blueprint $table) {
            $table->string('uniqueid_namareport')->primary();
            $table->string('completename')->nullable();
            $table->string('pernr')->nullable();
            $table->string('pn_mantri')->nullable();
            $table->string('esgdesc')->nullable();
            $table->string('psadesc')->nullable();
            $table->string('orgdesc')->nullable();
            $table->string('positiondesc')->nullable();
            $table->string('bc')->nullable();
            $table->string('tmt_jabatan')->nullable();
        });
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function mantri(string $pn, string $name, string $status = 'PT (peserta pens.)', string $position = 'MANTRI', ?string $tmt = null): void
    {
        DB::table('brihc_pemasar')->insert([
            'uniqueid_namareport' => 'M'.$pn, 'completename' => $name, 'pernr' => $pn, 'pn_mantri' => $pn,
            'esgdesc' => $status, 'psadesc' => 'KC Madiun', 'orgdesc' => 'UNIT BALEREJO', 'positiondesc' => $position,
            'bc' => '6346', 'tmt_jabatan' => $tmt,
        ]);
    }

    private function loan(string $periode, string $account, string $cif, float $plafon, float $os, string $realisasi, ?string $pn = '00111111 - Mantri A', string $kolek = '1', string $product = 'KUPEDES', string $id = ''): void
    {
        DB::table('daily_loan_dinamis')->insert([
            'uniqueid_namareport' => $id !== '' ? $id : 'R'.(++$this->sequence), 'periode' => $periode,
            'segmen_kinerja' => 'MICRO', 'produk_kinerja' => $product, 'description' => 'Kredit Mikro - Kupedes',
            'cabang1' => 'KC Madiun', 'unit1' => 'Unit Balerejo', 'pn_pengelola1' => $pn, 'pn_pemrakarsa1' => $pn,
            'nomor_rekening1' => $account, 'cifno' => $cif, 'nama_debitur1' => 'Debitur '.$cif,
            'plafon' => $plafon, 'baki_debet1' => $os, 'kolek' => $kolek, 'tgl_realisasi' => $realisasi,
        ]);
    }

    private function monthNet(string $start, string $end, string $position): array
    {
        $service = app(MantriIncentiveService::class);

        return collect($service->monthNetDisbursement($start, $end, $position, $service->availablePeriods()))
            ->keyBy('account')->map(fn (array $row): float => $row['net'])->all();
    }

    public function test_net_disbursement_follows_guideline_scenarios(): void
    {
        // Baru: CIF baru, seluruh plafond.
        $this->loan('2026-11-30', 'N1', 'BARU', 100_000_000, 100_000_000, '2026-11-05');
        // Suplesi di bulan yang sama: 150 - OS 50 = 100.
        $this->loan('2026-10-31', 'S0', 'SUP', 120_000_000, 50_000_000, '2025-11-01');
        $this->loan('2026-11-15', 'S0', 'SUP', 120_000_000, 50_000_000, '2025-11-01');
        $this->loan('2026-11-30', 'S1', 'SUP', 150_000_000, 150_000_000, '2026-11-16');
        // Pelunasan bulan sama: lunas 12 Nov (OS 10), realisasi 20 Nov 100 = 90.
        $this->loan('2026-10-31', 'P0', 'SAMA', 50_000_000, 12_000_000, '2025-06-01');
        $this->loan('2026-11-11', 'P0', 'SAMA', 50_000_000, 10_000_000, '2025-06-01');
        $this->loan('2026-11-30', 'P1', 'SAMA', 100_000_000, 100_000_000, '2026-11-20');
        // OS terakhir > plafond baru: ND 0, tidak minus.
        $this->loan('2026-10-31', 'Z0', 'NOL', 30_000_000, 25_000_000, '2025-01-01');
        $this->loan('2026-11-30', 'Z1', 'NOL', 20_000_000, 20_000_000, '2026-11-25');
        // Pelunasan bulan berbeda: lunas Nov, realisasi 2 Des = seluruh plafond.
        $this->loan('2026-10-31', 'B0', 'BEDA', 50_000_000, 10_000_000, '2025-06-01');
        $this->loan('2026-11-11', 'B0', 'BEDA', 50_000_000, 10_000_000, '2025-06-01');
        $this->loan('2026-12-31', 'B1', 'BEDA', 100_000_000, 100_000_000, '2026-12-02');

        $november = $this->monthNet('2026-11-01', '2026-11-30', '2026-11-30');
        $this->assertEqualsWithDelta(100_000_000, $november['N1'], 0.01);
        $this->assertEqualsWithDelta(100_000_000, $november['S1'], 0.01);
        $this->assertEqualsWithDelta(90_000_000, $november['P1'], 0.01);
        $this->assertEqualsWithDelta(0, $november['Z1'], 0.01);

        $december = $this->monthNet('2026-12-01', '2026-12-31', '2026-12-31');
        $this->assertEqualsWithDelta(100_000_000, $december['B1'], 0.01);
    }

    public function test_missing_prakarsa_pn_is_resolved_from_earlier_position_then_pengelola(): void
    {
        $this->loan('2026-11-20', 'X1', 'C1', 80_000_000, 80_000_000, '2026-11-18', '00222222 - Mantri B');
        $this->loan('2026-11-30', 'X1', 'C1', 80_000_000, 80_000_000, '2026-11-18', ' - ');
        $service = app(MantriIncentiveService::class);
        $rows = collect($service->monthNetDisbursement('2026-11-01', '2026-11-30', '2026-11-30', $service->availablePeriods()));

        $this->assertSame('222222', $rows->firstWhere('account', 'X1')['pn']);
    }

    public function test_tiering_matches_simulation_slide(): void
    {
        $service = app(MantriIncentiveService::class);

        $this->assertSame(0.005, $service->tierFor(1_012_794_616)['rate']);
        $this->assertSame(0.004, $service->tierFor(998_029_317)['rate']);
        $this->assertSame(0.003, $service->tierFor(881_956_998)['rate']);
        $this->assertSame(0.003, $service->tierFor(800_000_000)['rate']);
        $this->assertSame(0.004, $service->tierFor(900_000_001)['rate']);
        $this->assertNull($service->tierFor(799_999_999));
        $this->assertEqualsWithDelta(5_063_973, round(1_012_794_616 * $service->tierFor(1_012_794_616)['rate']), 1);
    }

    public function test_build_combines_average_nd_mob6_and_roster_rules(): void
    {
        $this->mantri('111111', 'Mantri A');
        $this->mantri('333333', 'Mantri Kontrak', 'Pekerja Kontrak');
        $this->mantri('444444', 'Mantri Briguna', 'PT (peserta pens.)', 'MANTRI BRIGUNA');

        // Q4: ND Okt 1.000, Nov 1.100, Des 940 => avg 1.013,33 Jt (tier 0,5%).
        $this->loan('2026-10-31', 'Q10', 'Q10', 1_000_000_000, 1_000_000_000, '2026-10-10');
        $this->loan('2026-11-30', 'Q11', 'Q11', 1_100_000_000, 1_100_000_000, '2026-11-10');
        $this->loan('2026-12-31', 'Q12', 'Q12', 940_000_000, 940_000_000, '2026-12-10');
        // Booking Jan-Jun 2026 diukur MOB 6 (Jul-Des): plafond 1.000, pemburukan 5 => 0,5%.
        $this->loan('2026-01-31', 'V1', 'V1', 995_000_000, 995_000_000, '2026-01-15');
        $this->loan('2026-07-31', 'V1', 'V1', 995_000_000, 900_000_000, '2026-01-15');
        $this->loan('2026-02-28', 'V2', 'V2', 5_000_000, 5_000_000, '2026-02-15');
        $this->loan('2026-08-31', 'V2', 'V2', 5_000_000, 4_000_000, '2026-02-15', '00111111 - Mantri A', '2');
        // ND milik mantri kontrak tidak membuat dia berhak.
        $this->loan('2026-10-31', 'K1', 'K1', 3_000_000_000, 3_000_000_000, '2026-10-10', '00333333 - Kontrak');

        $payload = app(MantriIncentiveService::class)->build('2026-12-31');
        $rows = collect($payload['rows'])->keyBy('pn');

        $this->assertArrayNotHasKey('444444', $rows->all());
        $mantri = $rows['111111'];
        $this->assertEqualsWithDelta(1_013_333_333.33, $mantri['nd_avg'], 1);
        $this->assertEqualsWithDelta(0.5, $mantri['mob_ratio'], 0.0001);
        $this->assertTrue($mantri['eligible']);
        $this->assertSame(0.005, $mantri['rate']);
        $this->assertEquals(round(1_013_333_333.33 * 0.005), $mantri['nominal']);

        $contract = $rows['333333'];
        $this->assertTrue($contract['nd_ok']);
        $this->assertFalse($contract['eligible']);
        $this->assertContains('Bukan pekerja tetap', $contract['reasons']);
    }

    public function test_mob6_above_one_percent_is_not_eligible(): void
    {
        $this->mantri('111111', 'Mantri A');
        foreach (['2026-10', '2026-11', '2026-12'] as $month) {
            $end = Carbon::parse($month.'-01')->endOfMonth()->toDateString();
            $this->loan($end, 'Q'.$month, 'Q'.$month, 1_200_000_000, 1_200_000_000, $month.'-05');
        }
        $this->loan('2026-03-31', 'V1', 'V1', 100_000_000, 100_000_000, '2026-03-10');
        $this->loan('2026-09-30', 'V1', 'V1', 100_000_000, 90_000_000, '2026-03-10', '00111111 - Mantri A', '3');

        $row = collect(app(MantriIncentiveService::class)->build('2026-12-31')['rows'])->firstWhere('pn', '111111');

        $this->assertTrue($row['nd_ok']);
        $this->assertFalse($row['mob_ok']);
        $this->assertFalse($row['eligible']);
        $this->assertSame(0.0, (float) $row['nominal']);
    }

    public function test_route_renders_with_branch_and_unit_filters_and_exports_nominatif(): void
    {
        $this->mantri('111111', 'Mantri A');
        $this->loan('2026-12-31', 'Q12', 'Q12', 940_000_000, 940_000_000, '2026-12-10');

        $user = new User();
        $user->forceFill(['id' => 1, 'pn' => '0045', 'name' => 'Tester', 'role' => 'admin']);
        $this->actingAs($user);

        $response = $this->get(route('report.kpi.insentif-mantri', ['cabang' => 'KC MADIUN', 'unit' => 'UNIT BALEREJO']));
        $response->assertOk();
        $response->assertSee('Insentif Mantri Q4 2026');
        $response->assertSee('Mantri A');
        $response->assertSee('UNIT BALEREJO');

        $export = $this->get(route('report.kpi.insentif-mantri.export', ['jenis' => 'nd', 'cabang' => 'KC MADIUN']));
        $export->assertOk();
        $csv = $export->streamedContent();
        $this->assertStringContainsString('nett_disb', $csv);
        $this->assertStringContainsString('940000000', $csv);
    }
}
