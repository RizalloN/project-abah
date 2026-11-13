<?php

namespace Tests\Unit;

use App\Support\LandingMicroPrewashMbmService;
use App\Support\UserBranchScope;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PipelinePrewashMbmAnalyticsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('monitoring_tl_pipeline_mbm', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('tahun');
            foreach (['cif', 'nama_debt', 'alamat', 'nomorhandphone', 'norek_pinjaman', 'branch', 'nama_unit', 'nama_kanca', 'kanwil', 'sumberpipeline', 'pn_mantri', 'nama_mantri', 'product_rekomendasi', 'score', 'idpipeline_lama', 'sumberpipeline_lama', 'uniqueid_namareport'] as $column) {
                $table->string($column)->nullable();
            }
            $table->decimal('plafond', 30, 2)->nullable();
            $table->decimal('os', 30, 2)->nullable();
            foreach (['jan', 'feb', 'mar', 'apr', 'mei', 'jun', 'jul', 'agt', 'sep', 'okt', 'nov', 'des', 'kunjungan', 'status'] as $column) {
                $table->string($column)->nullable();
            }
            $table->unsignedTinyInteger('jumlah_kunjungan')->nullable();
            $table->decimal('plafond_real', 30, 2)->nullable();
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
        Schema::create('wilayah_mbm', function (Blueprint $table): void {
            foreach (['bc', 'nama_uker', 'cabang', 'nama_mbm'] as $column) {
                $table->string($column);
            }
        });
        foreach (['daily_loan_dinamis', 'lw321pn'] as $source) {
            Schema::create($source, function (Blueprint $table) use ($source): void {
                $table->id();
                $table->date('periode');
                $table->string('cifno');
                $table->string('uniqueid_namareport')->nullable();
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
        $this->pipeline('CIF1', ['uniqueid_namareport' => 'mbm_CIF1_second', 'plafond' => '200.00']);
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
        Schema::drop('monitoring_tl_pipeline_mbm');

        $payload = $this->payload();

        $this->assertFalse($payload['available']);
        $this->assertNotEmpty($payload['message']);
    }

    public function test_all_twelve_done_months_are_persisted_without_changing_source_columns(): void
    {
        $this->pipeline('CIF1');
        $source = (array) DB::table('monitoring_tl_pipeline_mbm')->first();
        foreach (range(1, 12) as $month) {
            $this->visit('CIF1', sprintf('2026-%02d-01', $month), 'DONE');
            $this->visit('CIF1', sprintf('2026-%02d-02', $month), 'DONE');
        }
        $this->loan('daily_loan_dinamis', 'CIF1', 'ACC1', '100.25');

        $row = $this->record($this->payload(), 'CIF1');
        $stored = (array) DB::table('monitoring_tl_pipeline_mbm')->first();

        $this->assertSame(12, $row['jumlah_kunjungan']);
        $this->assertSame(12, (int) $stored['jumlah_kunjungan']);
        $this->assertSame('DONE', $stored['kunjungan']);
        $this->assertSame('Real', $stored['status']);
        $this->assertEquals(100.25, $stored['plafond_real']);
        foreach (['jan', 'feb', 'mar', 'apr', 'mei', 'jun', 'jul', 'agt', 'sep', 'okt', 'nov', 'des'] as $month) {
            $this->assertSame('DONE', $stored[$month]);
        }
        foreach (['tahun', 'cif', 'nama_debt', 'branch', 'nama_unit', 'nama_kanca', 'plafond', 'os', 'uniqueid_namareport'] as $column) {
            $this->assertSame($source[$column], $stored[$column]);
        }
        DB::enableQueryLog();
        $this->payload();
        $writes = array_filter(DB::getQueryLog(), fn (array $query): bool => str_starts_with(strtolower($query['query']), 'update '));
        DB::disableQueryLog();
        $this->assertCount(0, $writes, 'Unchanged computed values should not be written again.');
    }

    public function test_new_visit_snapshot_replaces_old_month_status_and_refreshes_stored_values(): void
    {
        $this->pipeline('CIF1');
        $this->visit('CIF1', '2026-01-01', 'DONE');
        $this->loan('daily_loan_dinamis', 'CIF1', 'ACC1', '100.00');
        $this->payload();
        $this->visit('CIF1', '2026-02-01', 'CANCELLED', ['periode' => '2026-11-13']);
        $this->visit('CIF1', '2026-03-01', '0', ['periode' => '2026-11-13']);
        $this->visit('CIF1', '2026-04-01', '', ['periode' => '2026-11-13']);

        $row = $this->record($this->payload(), 'CIF1');
        $stored = DB::table('monitoring_tl_pipeline_mbm')->first();

        $this->assertNull($stored->jan);
        $this->assertSame('CANCELLED', $stored->feb);
        $this->assertSame('0', $stored->mar);
        $this->assertSame('', $stored->apr);
        $this->assertSame(0, $row['jumlah_kunjungan']);
        $this->assertSame('NOT', $stored->kunjungan);
    }

    public function test_mbm_and_unit_groups_use_branch_and_unit_mapping_and_keep_unmapped_rows(): void
    {
        DB::table('wilayah_mbm')->insert([
            ['bc' => '06493', 'nama_uker' => 'UNIT MLARAK PONOROGO', 'cabang' => 'KC MADIUN', 'nama_mbm' => 'Hendri Nur Wahyudi'],
            ['bc' => '8145', 'nama_uker' => 'UNIT BALE LINTANG', 'cabang' => 'KC MADIUN', 'nama_mbm' => 'Trimo Agung Yunianto'],
            ['bc' => '6493', 'nama_uker' => 'UNIT MLARAK PONOROGO', 'cabang' => 'KC MAGETAN', 'nama_mbm' => 'Rita Auliasari'],
        ]);
        $this->pipeline('CIF1');
        $this->pipeline('CIF2', ['branch' => '8145', 'nama_unit' => 'UNIT BALE LINTANG']);
        $this->pipeline('CIF3', ['branch' => '9999', 'nama_unit' => 'UNIT BARU']);
        $this->visit('CIF1', '2026-01-01', 'DONE');
        $this->loan('daily_loan_dinamis', 'CIF1', 'ACC1', '10.00');

        $payload = $this->payload();

        $this->assertCount(1, $payload['groups']['bo']);
        $this->assertCount(3, $payload['groups']['mbm']);
        $this->assertCount(3, $payload['groups']['unit']);
        $this->assertSame('64850', $this->record($payload, 'CIF1')['mbm_pn']);
        $this->assertSame('Hendry Nurwahyudi', $this->record($payload, 'CIF1')['mbm_name']);
        $this->assertSame('24600', $this->record($payload, 'CIF2')['mbm_pn']);
        $this->assertSame('Belum dipetakan', $this->record($payload, 'CIF3')['mbm_name']);
        $this->assertSame(1, $payload['quality']['unmapped_mbm']);
        $this->assertSame(3, array_sum(array_column($payload['groups']['mbm'], 'total_count')));
        $this->assertSame('6493', $payload['groups']['unit'][0]['unit_code']);
    }

    public function test_conflicting_mbm_mapping_is_not_resolved_by_unit_name_fallback(): void
    {
        DB::table('wilayah_mbm')->insert([
            ['bc' => '6493', 'nama_uker' => 'UNIT MLARAK PONOROGO', 'cabang' => 'KC MADIUN', 'nama_mbm' => 'Nur Elfiana'],
            ['bc' => '06493', 'nama_uker' => 'UNIT LAIN', 'cabang' => 'KC MADIUN', 'nama_mbm' => 'Trimo Agung Yunianto'],
        ]);
        $this->pipeline('CIF1');

        $row = $this->record($this->payload(), 'CIF1');

        $this->assertNull($row['mbm_pn']);
        $this->assertSame('Belum dipetakan', $row['mbm_name']);
    }

    public function test_mirrored_lw_rows_do_not_make_daily_loan_the_newest_native_report(): void
    {
        $this->pipeline('CIF1');
        $this->visit('CIF1', '2026-01-01', 'DONE');
        $this->loan('daily_loan_dinamis', 'CIF1', 'NATIVE', '10.00');
        $this->loan('daily_loan_dinamis', 'CIF1', 'MIRROR', '900.00', ['periode' => '2026-11-11', 'uniqueid_namareport' => 'LW321PN:mirror']);
        $this->loan('lw321pn', 'CIF1', 'LW1', '100.00', ['periode' => '2026-11-11']);

        $payload = $this->payload();

        $this->assertSame('lw321pn', $payload['loan_source']);
        $this->assertSame('100.00', $this->record($payload, 'CIF1')['plafond_real']);
    }

    public function test_blank_cif_and_loan_account_do_not_create_valid_zero_realizations(): void
    {
        $this->pipeline('');
        $this->pipeline('CIF1');
        $this->visit('CIF1', '2026-01-01', 'DONE');
        $this->loan('daily_loan_dinamis', 'CIF1', '', '100.00');

        $payload = $this->payload();

        $this->assertNull($this->record($payload, '')['plafond_real']);
        $this->assertNull($this->record($payload, '')['jumlah_kunjungan']);
        $this->assertNull($this->record($payload, 'CIF1')['plafond_real']);
        $this->assertNull($payload['summary']['realized_amount']);
        $this->assertSame(1, $payload['quality']['missing_cif']);
        $this->assertSame(1, $payload['quality']['unverified_realization']);
    }

    public function test_same_cif_in_different_branches_has_identical_values_in_area_and_branch_views(): void
    {
        $this->pipeline('CIF1');
        $this->pipeline('CIF1', ['nama_kanca' => 'KC MAGETAN', 'uniqueid_namareport' => 'mbm_magetan_CIF1']);
        $this->visit('CIF1', '2026-01-01', 'DONE');
        $this->loan('daily_loan_dinamis', 'CIF1', 'ACC1', '100.00');

        $area = $this->payload();
        $madiun = collect($area['records'])->firstWhere('nama_kanca', 'KC MADIUN');
        $magetan = collect($area['records'])->firstWhere('nama_kanca', 'KC MAGETAN');
        $scoped = $this->record($this->payload(UserBranchScope::forKey('magetan')), 'CIF1');

        $this->assertSame(1, $madiun['jumlah_kunjungan']);
        $this->assertSame('100.00', $madiun['plafond_real']);
        $this->assertSame(0, $magetan['jumlah_kunjungan']);
        $this->assertSame('0.00', $magetan['plafond_real']);
        $this->assertSame($magetan['plafond_real'], $scoped['plafond_real']);
        $this->assertSame($magetan['jumlah_kunjungan'], $scoped['jumlah_kunjungan']);
        $this->assertSame(100.0, (float) DB::table('monitoring_tl_pipeline_mbm')->where('nama_kanca', 'KC MADIUN')->value('plafond_real'));
        $this->assertSame(0.0, (float) DB::table('monitoring_tl_pipeline_mbm')->where('nama_kanca', 'KC MAGETAN')->value('plafond_real'));
    }

    private function payload(?array $scope = null): array
    {
        return app(LandingMicroPrewashMbmService::class)->payload($scope);
    }

    private function record(array $payload, string $cif): array
    {
        $row = collect($payload['records'])->firstWhere('cif', $cif);
        $this->assertNotNull($row, 'Expected pipeline CIF '.$cif);

        return (array) $row;
    }

    private function pipeline(string $cif, array $overrides = []): void
    {
        DB::table('monitoring_tl_pipeline_mbm')->insert(array_replace([
            'tahun' => 2026, 'cif' => $cif, 'nama_debt' => 'Debitur '.$cif,
            'branch' => '6493', 'nama_unit' => 'UNIT MLARAK PONOROGO', 'nama_kanca' => 'KC MADIUN',
            'kanwil' => 'KANWIL MALANG', 'plafond' => '100.00', 'os' => '0.00',
            'uniqueid_namareport' => 'mbm_'.$cif,
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
