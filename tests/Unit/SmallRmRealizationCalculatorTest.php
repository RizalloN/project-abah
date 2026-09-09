<?php

namespace Tests\Unit;

use App\Support\SmallRmRealizationCalculator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SmallRmRealizationCalculatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::dropAllTables();
        Schema::create('daily_loan_dinamis', function (Blueprint $table): void {
            $table->id();
            $table->date('periode');
            $table->string('segmen_kinerja')->nullable();
            $table->string('produk_kinerja')->nullable();
            $table->string('cabang_normalized')->nullable();
            $table->string('unit_normalized')->nullable();
            $table->string('branch_normalized')->nullable();
            $table->string('rm_normalized')->nullable();
            $table->string('pn_pemrakarsa1')->nullable();
            $table->string('cifno_clean')->nullable();
            $table->string('nomor_rekening1')->nullable();
            $table->decimal('plafon', 20, 2)->nullable();
            $table->decimal('baki_debet1', 20, 2)->nullable();
            $table->date('tgl_realisasi')->nullable();
        });
    }

    public function test_new_and_additive_accounts_keep_their_full_plafon(): void
    {
        $this->insertRow('2026-07-09', 'CIF-ADD', 'OLD-STAYS', 300, 300, '2025-01-01');
        $this->insertRow('2026-07-31', 'CIF-ADD', 'OLD-STAYS', 300, 290, '2025-01-01');
        $this->insertRow('2026-07-31', 'CIF-ADD', 'NEW-ADD', 400, 400, '2026-07-10');
        $this->insertRow('2026-07-31', 'CIF-NEW', 'NEW-CIF', 500, 500, '2026-07-10');

        $result = (new SmallRmRealizationCalculator)->calculate(['2026-07-31']);
        $row = collect($result['rows'])->firstWhere('rm_identity', 'PN:63020');

        $this->assertTrue($result['covered_periods']['2026-07-31']);
        $this->assertNotNull($row);
        $this->assertSame(2, $row['deb']);
        $this->assertSame(900.0, $row['rp']);
    }

    public function test_prior_exposure_does_not_mutate_stored_realization_plafon(): void
    {
        $this->insertRow('2026-07-09', 'CIF-REPLACE', 'OLD-CLOSED', 600, 400, '2025-01-01');
        $this->insertRow('2026-07-31', 'CIF-REPLACE', 'NEW-REPLACEMENT', 600, 600, '2026-07-10');

        $this->insertRow('2026-07-09', 'CIF-SAME', 'SAME-ACCOUNT', 300, 200, '2025-01-01');
        $this->insertRow('2026-07-31', 'CIF-SAME', 'SAME-ACCOUNT', 350, 350, '2026-07-10');

        $result = (new SmallRmRealizationCalculator)->calculate(['2026-07-31']);
        $row = collect($result['rows'])->firstWhere('rm_identity', 'PN:63020');

        $this->assertNotNull($row);
        $this->assertSame(2, $row['deb']);
        $this->assertSame(950.0, $row['rp']);
    }

    public function test_realization_uses_initiator_deduplicates_accounts_and_ignores_blank_initiator(): void
    {
        $this->insertRow('2026-07-31', 'CIF-A', 'ACCOUNT-A', 500, 500, '2026-07-10');
        $this->insertRow('2026-07-31', 'CIF-A', 'ACCOUNT-A', 500, 500, '2026-07-10');
        $this->insertRow(
            '2026-07-31',
            'CIF-B',
            'ACCOUNT-B',
            700,
            700,
            '2026-07-10',
            '00024959 - RM PEMRAKARSA LAIN'
        );
        $this->insertRow('2026-07-31', 'CIF-C', 'ACCOUNT-C', 900, 900, '2026-07-10', '');

        $result = (new SmallRmRealizationCalculator)->calculate(['2026-07-31']);
        $rows = collect($result['rows'])->keyBy('rm_identity');

        $this->assertSame(500.0, $rows['PN:63020']['rp']);
        $this->assertSame(1, $rows['PN:63020']['deb']);
        $this->assertSame(700.0, $rows['PN:24959']['rp']);
        $this->assertSame(1, $result['diagnostics']['excluded_blank_initiator']);
        $this->assertSame(2, $result['diagnostics']['candidate_accounts']);
    }

    public function test_duplicate_candidate_rows_preserve_maximum_plafon(): void
    {
        $this->insertRow('2026-07-31', 'CIF-DUP', 'ACC-DUP', 400, 400, '2026-07-15');
        $this->insertRow('2026-07-31', 'CIF-DUP', 'ACC-DUP', 850, 850, '2026-07-15');
        $this->insertRow('2026-07-31', 'CIF-DUP', 'ACC-DUP', 600, 600, '2026-07-15');

        $result = (new SmallRmRealizationCalculator)->calculate(['2026-07-31']);
        $row = collect($result['rows'])->firstWhere('rm_identity', 'PN:63020');

        $this->assertNotNull($row);
        $this->assertSame(1, $row['deb']);
        $this->assertSame(850.0, $row['rp']);
    }

    private function insertRow(
        string $period,
        string $cif,
        string $account,
        float $plafon,
        float $os,
        string $realizationDate,
        ?string $initiator = '00063020 - ANTON PURWANTO',
        array $overrides = []
    ): void {
        DB::table('daily_loan_dinamis')->insert(array_merge([
            'periode' => $period,
            'segmen_kinerja' => 'SMALL',
            'produk_kinerja' => 'COMMERCIAL',
            'cabang_normalized' => 'KC PONOROGO',
            'unit_normalized' => 'KC PONOROGO',
            'branch_normalized' => '70',
            'rm_normalized' => '00063020 - ANTON PURWANTO',
            'pn_pemrakarsa1' => $initiator,
            'cifno_clean' => $cif,
            'nomor_rekening1' => $account,
            'plafon' => $plafon,
            'baki_debet1' => $os,
            'tgl_realisasi' => $realizationDate,
        ], $overrides));
    }
}
