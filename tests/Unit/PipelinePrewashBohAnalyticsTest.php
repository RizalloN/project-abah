<?php

namespace Tests\Unit;

use App\Support\LandingMicroPrewashBohService;
use App\Support\UserBranchScope;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PipelinePrewashBohAnalyticsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('pipeline_prewash_boh', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('tahun');
            foreach (['cif', 'nama_debt', 'alamat', 'nomorhandphone', 'norek_pinjaman', 'branch', 'nama_unit', 'nama_kanca', 'kanwil', 'sumberpipeline', 'pn_mantri', 'nama_mantri', 'product_rekomendasi', 'score', 'idpipeline_lama', 'sumberpipeline_lama', 'uniqueid_namareport'] as $column) {
                $table->string($column)->nullable();
            }
            $table->decimal('plafond', 30, 2)->nullable();
            $table->decimal('os', 30, 2)->nullable();
        });
        Schema::create('evaluasi_aktivitas_kunjungan_pemasar', function (Blueprint $table): void {
            $table->id();
            $table->date('periode');
            $table->dateTime('date_printed');
            $table->date('tgl_kunjungan');
            foreach (['status_kunjungan', 'cifno', 'kode_main_branch', 'id_aktivitas'] as $column) {
                $table->string($column)->nullable();
            }
        });
        foreach (['daily_loan_dinamis', 'lw321pn'] as $source) {
            Schema::create($source, function (Blueprint $table) use ($source): void {
                $table->id();
                $table->date('periode');
                $table->string('cifno');
                $table->date('tgl_realisasi')->nullable();
                $table->string($source === 'lw321pn' ? 'no_rekening' : 'nomor_rekening1')->nullable();
                $table->string($source === 'lw321pn' ? 'kode_kanca' : 'kode_cabang1');
                $table->decimal($source === 'lw321pn' ? 'plafon_dalam_idr' : 'plafon', 30, 2)->nullable();
            });
        }
    }

    public function test_months_use_done_priority_then_latest_status_and_count_done_months_only(): void
    {
        $this->pipeline('CIF1');
        $this->visit('CIF1', '2026-01-04', 'DONE');
        $this->visit('CIF1', '2026-01-25', 'CANCELLED');
        $this->visit('CIF1', '2026-02-03', 'PLAN');
        $this->visit('CIF1', '2026-02-24', 'CANCELLED');
        $this->visit('CIF1', '2026-03-03', 'done');
        $this->visit('CIF1', '2026-03-13', 'DONE');
        $this->visit('CIF1', '2026-04-03', '0');
        $this->visit('CIF1', '2026-05-03', '');
        $this->visit('CIF1', '2026-06-03', 'PLAN');
        $this->loan('daily_loan_dinamis', 'CIF1', 'ACC1', '123.45');

        $payload = $this->payload();
        $row = $this->record($payload, 'CIF1');

        $this->assertTrue($payload['available']);
        $this->assertSame(2026, $payload['year']);
        $this->assertSame('DONE', $row['jan']);
        $this->assertSame('CANCELLED', $row['feb']);
        $this->assertSame('DONE', $row['mar']);
        $this->assertSame('0', (string) $row['apr']);
        $this->assertSame('', $row['mei']);
        $this->assertSame('PLAN', $row['jun']);
        $this->assertNull($row['jul']);
        $this->assertNull($row['des']);
        $this->assertSame(2, $row['jumlah_kunjungan']);
        $this->assertSame('DONE', $row['kunjungan']);
        $this->assertSame(1, $payload['summary']['visited_count']);
    }

    public function test_latest_visit_snapshot_and_its_year_control_lookup(): void
    {
        $this->pipeline('CIF1');
        $this->pipeline('OLD', ['tahun' => 2025]);
        $this->visit('CIF1', '2026-01-01', 'DONE', ['periode' => '2026-11-01', 'date_printed' => '2026-11-01 09:00:00']);
        $this->visit('CIF1', '2025-02-01', 'DONE');
        $this->visit('CIF1', '2026-03-01', 'PLAN');
        $this->loan('daily_loan_dinamis', 'CIF1', 'OLD_ACCOUNT', '900.00', ['tgl_realisasi' => '2025-11-01']);
        $this->loan('daily_loan_dinamis', 'CIF1', 'CURRENT_ACCOUNT', '25.00');

        $payload = $this->payload();
        $row = $this->record($payload, 'CIF1');

        $this->assertSame('2026-11-12', $payload['visit_period']);
        $this->assertSame(1, $payload['summary']['total_count']);
        $this->assertSame(0, $row['jumlah_kunjungan']);
        $this->assertSame('NOT', $row['kunjungan']);
        $this->assertSame('PLAN', $row['mar']);
        $this->assertSame('25.00', $row['plafond_real']);
    }

    public function test_equal_date_non_done_visits_use_latest_activity_identifier(): void
    {
        $this->pipeline('CIF1');
        $this->visit('CIF1', '2026-02-01', 'CANCELLED', ['id_aktivitas' => '9']);
        $this->visit('CIF1', '2026-02-01', 'PLAN', ['id_aktivitas' => '10']);
        $this->loan('daily_loan_dinamis', 'CIF1', 'ACC1', '0.00');

        $row = $this->record($this->payload(), 'CIF1');

        $this->assertSame('PLAN', $row['feb']);
        $this->assertSame(0, $row['jumlah_kunjungan']);
    }

    public function test_daily_wins_equal_periods_and_realization_sums_distinct_accounts(): void
    {
        $this->pipeline('CIF1');
        $this->visit('CIF1', '2026-01-01', 'DONE');
        $this->loan('daily_loan_dinamis', 'CIF1', 'ACC1', '100.25');
        $this->loan('daily_loan_dinamis', 'CIF1', 'ACC1', '100.25');
        $this->loan('daily_loan_dinamis', 'CIF1', 'ACC2', '200.50');
        $this->loan('daily_loan_dinamis', 'CIF1', 'HISTORICAL', '500.00', ['periode' => '2026-10-31']);
        $this->loan('lw321pn', 'CIF1', 'LWACC', '999.00');

        $payload = $this->payload();

        $this->assertSame('daily_loan_dinamis', $payload['loan_source']);
        $this->assertSame('2026-11-10', $payload['loan_period']);
        $this->assertSame('300.75', $this->record($payload, 'CIF1')['plafond_real']);
        $this->assertSame(1, $payload['summary']['realized_count']);
        $this->assertSame('300.75', $payload['summary']['realized_amount']);
        $this->assertEquals(100, $payload['summary']['realized_share']);
    }

    public function test_newer_lw321pn_wins_without_fallback_for_unmatched_cifs(): void
    {
        $this->pipeline('CIF1');
        $this->pipeline('CIF2');
        $this->visit('CIF1', '2026-01-01', 'DONE');
        $this->loan('daily_loan_dinamis', 'CIF1', 'ACC1', '100.00');
        $this->loan('daily_loan_dinamis', 'CIF2', 'ACC2', '200.00');
        $this->loan('lw321pn', 'CIF1', 'LW1', '51.25', ['periode' => '2026-11-11']);
        $this->loan('lw321pn', 'CIF1', 'LW2', '2.25', ['periode' => '2026-11-11']);

        $payload = $this->payload();

        $this->assertSame('lw321pn', $payload['loan_source']);
        $this->assertSame('53.50', $this->record($payload, 'CIF1')['plafond_real']);
        $this->assertSame('0.00', $this->record($payload, 'CIF2')['plafond_real']);
    }

    public function test_conflicting_duplicate_or_missing_loan_amount_keeps_realization_unknown(): void
    {
        $this->pipeline('CONFLICT');
        $this->pipeline('MISSING');
        $this->visit('CONFLICT', '2026-01-01', 'DONE');
        $this->visit('MISSING', '2026-01-01', 'DONE');
        $this->loan('daily_loan_dinamis', 'CONFLICT', 'ACC1', '100.00');
        $this->loan('daily_loan_dinamis', 'CONFLICT', 'ACC1', '200.00');
        $this->loan('daily_loan_dinamis', 'CONFLICT', 'ACC2', '25.00');
        $this->loan('daily_loan_dinamis', 'MISSING', 'ACC3', '10.00', ['plafon' => null]);

        $payload = $this->payload();

        $this->assertNull($this->record($payload, 'CONFLICT')['plafond_real']);
        $this->assertNull($this->record($payload, 'MISSING')['plafond_real']);
        $this->assertNull($this->record($payload, 'CONFLICT')['status']);
        $this->assertNull($payload['summary']['realized_count']);
        $this->assertNull($payload['summary']['realized_amount']);
        $this->assertNull($payload['summary']['realized_share']);
    }

    public function test_branch_scope_restricts_pipeline_and_both_lookup_sources(): void
    {
        $this->pipeline('CIF1');
        $this->pipeline('OTHER', ['nama_kanca' => 'KC MAGETAN', 'nama_unit' => 'UNIT MAGETAN']);
        $this->visit('CIF1', '2026-01-01', 'PLAN');
        $this->visit('CIF1', '2026-02-01', 'DONE', ['kode_main_branch' => '49']);
        $this->visit('OTHER', '2026-01-01', 'DONE', ['kode_main_branch' => '49']);
        $this->loan('daily_loan_dinamis', 'CIF1', 'ACC1', '10.00');
        $this->loan('daily_loan_dinamis', 'CIF1', 'OTHER_BRANCH', '900.00', ['kode_cabang1' => '49']);

        $payload = $this->payload(UserBranchScope::forKey('madiun'));

        $this->assertSame(1, $payload['summary']['total_count']);
        $this->assertSame(0, $this->record($payload, 'CIF1')['jumlah_kunjungan']);
        $this->assertSame('10.00', $this->record($payload, 'CIF1')['plafond_real']);
        $this->assertCount(1, $payload['groups']['bo']);
        $this->assertCount(1, $payload['groups']['unit']);
    }

    public function test_realization_share_uses_visited_pipeline_as_denominator(): void
    {
        $this->pipeline('CIF1', ['plafond' => '100.25']);
        $this->pipeline('CIF2', ['plafond' => '200.50']);
        $this->pipeline('CIF3', ['plafond' => '300.75']);
        $this->visit('CIF1', '2026-01-01', 'DONE');
        $this->visit('CIF2', '2026-01-01', 'DONE');
        $this->visit('CIF3', '2026-01-01', 'PLAN');
        $this->loan('daily_loan_dinamis', 'CIF1', 'ACC1', '10.00');

        $summary = $this->payload()['summary'];

        $this->assertSame(3, $summary['total_count']);
        $this->assertSame('601.50', $summary['total_amount']);
        $this->assertSame(2, $summary['visited_count']);
        $this->assertSame(1, $summary['unvisited_count']);
        $this->assertSame(1, $summary['realized_count']);
        $this->assertEquals(50, $summary['realized_share']);
    }

    public function test_repeated_pipeline_cif_does_not_duplicate_realized_accounts_in_summary(): void
    {
        $this->pipeline('CIF1');
        $this->pipeline('CIF1', ['uniqueid_namareport' => 'boh_CIF1_second', 'plafond' => '200.00']);
        $this->visit('CIF1', '2026-01-01', 'DONE');
        $this->loan('daily_loan_dinamis', 'CIF1', 'ACC1', '100.25');
        $this->loan('daily_loan_dinamis', 'CIF1', 'ACC2', '200.50');

        $payload = $this->payload();

        $this->assertCount(2, $payload['records']);
        foreach ([$payload['summary'], $payload['groups']['bo'][0], $payload['groups']['unit'][0]] as $metrics) {
            $this->assertSame(2, $metrics['total_count']);
            $this->assertSame('300.00', $metrics['total_amount']);
            $this->assertSame(1, $metrics['realized_count']);
            $this->assertSame('300.75', $metrics['realized_amount']);
        }
    }

    public function test_missing_lookup_sources_remain_unavailable_instead_of_valid_zero(): void
    {
        $this->pipeline('CIF1');
        Schema::drop('evaluasi_aktivitas_kunjungan_pemasar');
        Schema::drop('daily_loan_dinamis');
        Schema::drop('lw321pn');

        $payload = $this->payload();

        $this->assertTrue($payload['available']);
        $this->assertNotEmpty($payload['message']);
        $this->assertNull($this->record($payload, 'CIF1')['jumlah_kunjungan']);
        $this->assertNull($this->record($payload, 'CIF1')['plafond_real']);
        $this->assertNull($payload['summary']['visited_count']);
        $this->assertNull($payload['summary']['realized_amount']);
    }

    public function test_missing_pipeline_table_produces_actionable_unavailable_payload(): void
    {
        Schema::drop('pipeline_prewash_boh');

        $payload = $this->payload();

        $this->assertFalse($payload['available']);
        $this->assertNotEmpty($payload['message']);
    }

    private function payload(?array $scope = null): array
    {
        return app(LandingMicroPrewashBohService::class)->payload($scope);
    }

    private function record(array $payload, string $cif): array
    {
        $row = collect($payload['records'])->firstWhere('cif', $cif);
        $this->assertNotNull($row, 'Expected pipeline CIF '.$cif);

        return (array) $row;
    }

    private function pipeline(string $cif, array $overrides = []): void
    {
        DB::table('pipeline_prewash_boh')->insert(array_replace([
            'tahun' => 2026, 'cif' => $cif, 'nama_debt' => 'Debitur '.$cif,
            'branch' => '6493', 'nama_unit' => 'UNIT MLARAK PONOROGO', 'nama_kanca' => 'KC MADIUN',
            'kanwil' => 'KANWIL MALANG', 'plafond' => '100.00', 'os' => '0.00',
            'uniqueid_namareport' => 'boh_'.$cif,
        ], $overrides));
    }

    private function visit(string $cif, string $date, string $status, array $overrides = []): void
    {
        DB::table('evaluasi_aktivitas_kunjungan_pemasar')->insert(array_replace([
            'periode' => '2026-11-12', 'date_printed' => '2026-11-12 09:00:00',
            'tgl_kunjungan' => $date, 'status_kunjungan' => $status, 'cifno' => $cif,
            'kode_main_branch' => '45', 'id_aktivitas' => (string) (DB::table('evaluasi_aktivitas_kunjungan_pemasar')->count() + 1),
        ], $overrides));
    }

    private function loan(string $source, string $cif, string $account, string $amount, array $overrides = []): void
    {
        $lw = $source === 'lw321pn';
        DB::table($source)->insert(array_replace([
            'periode' => '2026-11-10', 'cifno' => $cif, 'tgl_realisasi' => '2026-01-15',
            $lw ? 'no_rekening' : 'nomor_rekening1' => $account,
            $lw ? 'plafon_dalam_idr' : 'plafon' => $amount,
            $lw ? 'kode_kanca' : 'kode_cabang1' => '45',
        ], $overrides));
    }
}
