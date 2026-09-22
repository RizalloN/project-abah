<?php

namespace Tests\Unit;

use App\Support\MicroNettDisbursementCalculator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MicroNettDisbursementCalculatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('daily_loan_dinamis', function (Blueprint $table): void {
            $table->id();
            foreach ([
                'periode', 'tgl_realisasi', 'cabang_normalized', 'cabang1', 'unit_normalized', 'unit1',
                'branch_normalized', 'branch1', 'pn_pemrakarsa1', 'nomor_rekening1', 'cifno',
                'segmen_kinerja', 'produk_kinerja', 'description',
            ] as $column) {
                $table->string($column)->nullable();
            }
            $table->decimal('plafon', 20, 2)->nullable();
            $table->decimal('baki_debet1', 20, 2)->nullable();
        });
    }

    public function test_duplicate_current_accounts_keep_maximum_plafond_once(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->row('OLD', 100_000_000, ['periode' => '2026-08-31', 'tgl_realisasi' => '2026-08-01']),
            $this->row('NEW', 300_000_000),
            $this->row(' new ', 250_000_000),
        ]);

        $calculator = app(MicroNettDisbursementCalculator::class);
        foreach (['kurRm', 'mantri'] as $method) {
            $result = $calculator->{$method}('2026-09-19');
            $this->assertCount(1, $result);
            $this->assertSame(1, $result[0]['realisasi_deb']);
            $this->assertSame(200_000_000.0, $result[0]['realisasi_os']);
        }
        $kur = $calculator->kurRm('2026-09-19')[0];
        $this->assertSame(200_000_000.0, $kur['w1_realisasi_os']);
        $this->assertSame(1, $kur['gt_250_realisasi_deb']);
        $this->assertSame(200_000_000.0, $kur['gt_250_realisasi_os']);
    }

    public function test_duplicate_previous_accounts_keep_maximum_os_once_and_sum_distinct_accounts(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->row('OLD-A', 100_000_000, ['periode' => '2026-08-31']),
            $this->row(' old-a ', 80_000_000, ['periode' => '2026-08-31']),
            $this->row('OLD-B', 50_000_000, ['periode' => '2026-08-31']),
            $this->row('NEW', 300_000_000),
        ]);

        foreach (['kurRm', 'mantri'] as $method) {
            $result = app(MicroNettDisbursementCalculator::class)->{$method}('2026-09-19');
            $this->assertSame(1, $result[0]['realisasi_deb']);
            $this->assertSame(150_000_000.0, $result[0]['realisasi_os']);
        }
    }

    public function test_previous_rows_without_account_identity_keep_complete_cif_exposure(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->row('', 40_000_000, ['periode' => '2026-08-31']),
            $this->row('   ', 30_000_000, ['periode' => '2026-08-31']),
            $this->row('OLD', 100_000_000, ['periode' => '2026-08-31']),
            $this->row(' old ', 80_000_000, ['periode' => '2026-08-31']),
            $this->row('NEW', 300_000_000),
        ]);

        foreach (['kurRm', 'mantri'] as $method) {
            $result = app(MicroNettDisbursementCalculator::class)->{$method}('2026-09-19');
            $this->assertSame(1, $result[0]['realisasi_deb']);
            $this->assertSame(130_000_000.0, $result[0]['realisasi_os']);
        }
    }

    public function test_existing_signed_kur_clamped_mantri_and_250_million_tier_rules_are_preserved(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->row('OLD', 300_000_000, ['periode' => '2026-08-31']),
            $this->row('NEW', 250_000_000, ['pn_pemrakarsa1' => '']),
            $this->row('FUTURE', 900_000_000, ['tgl_realisasi' => '2026-09-20']),
        ]);

        $calculator = app(MicroNettDisbursementCalculator::class);
        $kur = $calculator->kurRm('2026-09-19')[0];
        $mantri = $calculator->mantri('2026-09-19')[0];
        $this->assertSame('RM LAIN', $kur['rm']);
        $this->assertSame('RM LAIN', $mantri['owner']);
        $this->assertSame(-50_000_000.0, $kur['realisasi_os']);
        $this->assertSame(0.0, $mantri['realisasi_os']);
        $this->assertSame(1, $kur['realisasi_deb']);
        $this->assertSame(1, $mantri['realisasi_deb']);
        $this->assertSame(0, $kur['lt_250_realisasi_deb']);
        $this->assertSame(0, $kur['gt_250_realisasi_deb']);
    }

    public function test_previous_cif_exposure_scope_and_per_account_subtraction_are_preserved(): void
    {
        DB::table('daily_loan_dinamis')->insert([
            $this->row('OLD-KUR', 100_000_000, ['periode' => '2026-08-31']),
            $this->row('OLD-OTHER', 50_000_000, [
                'periode' => '2026-08-31', 'segmen_kinerja' => 'SMALL', 'produk_kinerja' => 'KOMERSIAL',
            ]),
            $this->row('NEW-A', 300_000_000),
            $this->row('NEW-B', 200_000_000),
        ]);

        $calculator = app(MicroNettDisbursementCalculator::class);
        $kur = $calculator->kurRm('2026-09-19')[0];
        $mantri = $calculator->mantri('2026-09-19')[0];
        $this->assertSame(2, $kur['realisasi_deb']);
        $this->assertSame(300_000_000.0, $kur['realisasi_os']);
        $this->assertSame(2, $mantri['realisasi_deb']);
        $this->assertSame(200_000_000.0, $mantri['realisasi_os']);
    }

    private function row(string $account, float $amount, array $overrides = []): array
    {
        return array_merge([
            'periode' => '2026-09-19',
            'tgl_realisasi' => '2026-09-05',
            'cabang_normalized' => 'KC MADIUN',
            'cabang1' => 'KC Madiun',
            'unit_normalized' => 'UNIT A',
            'unit1' => 'Unit A',
            'branch_normalized' => '45',
            'branch1' => '45',
            'pn_pemrakarsa1' => '000123',
            'nomor_rekening1' => $account,
            'cifno' => 'CIF-A',
            'segmen_kinerja' => 'MICRO',
            'produk_kinerja' => 'KURKECIL',
            'description' => 'Kredit Mikro - KUR Ritel 2015',
            'plafon' => $amount,
            'baki_debet1' => $amount,
        ], $overrides);
    }
}
