<?php

namespace Tests\Unit;

use App\Support\PipelinePrewashService;
use App\Support\ReportCacheVersion;
use App\Support\UserBranchScope;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PipelinePrewashAnalyticsTest extends TestCase
{
    private const MONTHS = ['jan', 'feb', 'mar', 'apr', 'mei', 'jun', 'jul', 'agt', 'sep', 'okt', 'nov', 'des'];

    private const BASE_COLUMNS = [
        'nama_debt', 'alamat', 'nomorhandphone', 'cif', 'norek_pinjaman', 'branch',
        'nama_unit', 'nama_kanca', 'plafond', 'os', 'sumberpipeline', 'pn_mantri',
        'nama_mantri', 'product_rekomendasi', 'score', 'idpipeline_lama', 'sumberpipeline_lama',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Cache::flush();

        Schema::create('pipeline_prewash', function (Blueprint $table): void {
            $table->string('uniqueid_namareport')->primary();
            foreach (self::BASE_COLUMNS as $column) {
                $table->text($column)->nullable();
            }
            $table->text('kanwil')->nullable();
            $table->integer('tahun');
            $table->integer('jumlah_kunjungan')->nullable();
            $table->string('kunjungan')->nullable();
            $table->text('plafond_real')->nullable();
            $table->string('status')->nullable();
            foreach (self::MONTHS as $month) {
                $table->string($month)->nullable();
            }
            $table->timestamps();
        });
        Schema::create('evaluasi_aktivitas_kunjungan_pemasar', function (Blueprint $table): void {
            $table->string('uniqueid_namareport')->primary();
            $table->string('cifno')->nullable();
            $table->date('tgl_kunjungan');
            $table->string('status_kunjungan');
            $table->date('periode');
            $table->timestamps();
        });
        Schema::create('daily_loan_dinamis', function (Blueprint $table): void {
            $table->string('uniqueid_namareport')->nullable();
            $table->string('cifno')->nullable();
            $table->string('nomor_rekening1')->nullable();
            $table->date('tgl_realisasi')->nullable();
            $table->text('plafon')->nullable();
            $table->date('periode');
            $table->timestamps();
        });
        Schema::create('lw321pn', function (Blueprint $table): void {
            $table->string('uniqueid_namareport')->nullable();
            $table->string('cifno')->nullable();
            $table->string('no_rekening')->nullable();
            $table->date('tgl_realisasi')->nullable();
            $table->text('plafon_dalam_idr')->nullable();
            $table->date('periode');
            $table->timestamps();
        });
        Schema::create('wilayah_mbm', function (Blueprint $table): void {
            $table->string('bc');
            $table->string('nama_uker');
            $table->string('cabang');
            $table->string('nama_mbm');
        });
    }

    public function test_latest_native_loan_source_wins_and_daily_has_priority_on_an_equal_period(): void
    {
        $this->daily('native-older', 'C001', 'A1', '10.00', '2026-11-09');
        $this->daily('LW321PN:clone-newer', 'C001', 'A1', '999.00', '2026-11-12');
        $this->lw('raw-newer', 'C001', 'A1', '20.00', '2026-11-10');
        $this->daily('other-year', 'C001', 'A1', '888.00', '2027-01-01');

        $source = $this->service()->resolveLoanSource(2026);
        $this->assertSame('lw321pn', $source['table']);
        $this->assertSame('2026-11-10', $source['period']);
        $this->assertSame('lw', $source['kind']);

        $this->daily('native-tie', 'C001', 'A1', '30.00', '2026-11-10');
        $source = $this->service()->resolveLoanSource(2026);
        $this->assertSame('daily_loan_dinamis', $source['table']);
        $this->assertSame('2026-11-10', $source['period']);
        $this->assertSame('daily', $source['kind']);

        $this->daily('native-newest', 'C001', 'A1', '40.00', '2026-11-11');
        $this->assertSame('2026-11-11', $this->service()->resolveLoanSource(2026)['period']);
    }

    public function test_months_use_visit_dates_and_done_precedence_while_realization_sums_distinct_accounts_in_the_same_year(): void
    {
        $this->pipeline('p1', '000001', [
            'nama_debt' => 'Debitur asli', 'nomorhandphone' => '081234567890',
            'norek_pinjaman' => '000123456789012', 'plafond' => '123456789012345678901.25',
            'os' => '100000000000000000001.01', 'score' => '0009.50', 'pn_mantri' => '00024600',
        ]);
        $this->pipeline('p2', '000002');
        $this->pipeline('p3', '000003');
        $this->pipeline('prior-year', '000001', ['tahun' => 2025, 'jan' => 'PLAN', 'jumlah_kunjungan' => 9]);
        $this->visit('v1', '000001', '2026-01-03', 'PLAN');
        $this->visit('v2', '000001', '2026-01-04', 'DONE');
        $this->visit('v3', '000001', '2026-01-05', 'DONE');
        $this->visit('v4', '000001', '2026-02-07', 'DONE');
        $this->visit('v5', '000001', '2026-03-01', 'PLAN');
        $this->visit('v6', '000001', '2025-04-01', 'DONE');
        $this->visit('v7', '000002', '2026-05-01', 'PLAN');
        $this->visit('v8', '000003', '2026-06-01', 'CANCELLED');
        $this->daily('a1', '000001', '001234567890123', '100.05');
        $this->daily('a1-duplicate', '000001', '001234567890123', '100.05');
        $this->daily('a2', '000001', '001234567890124', '250.05');
        $this->daily('wrong-realization-year', '000001', '001234567890125', '1000.00', '2026-11-10', '2025-12-31');
        $this->daily('old-snapshot', '000001', '001234567890126', '2000.00', '2026-11-09');
        $this->lw('unused-source', '000001', '001234567890127', '4000.00', '2026-11-09');
        $baseBefore = $this->baseRows();
        $sourcesBefore = $this->sourceRows();

        $payload = $this->service()->payload('2026-11-12', null, true);

        $this->assertTrue($payload['available']);
        $row = $this->row('p1');
        $this->assertSame('DONE', $row->jan);
        $this->assertSame('DONE', $row->feb);
        $this->assertSame('PLAN', $row->mar);
        foreach (['apr', 'mei', 'jun', 'jul', 'agt', 'sep', 'okt', 'nov', 'des'] as $month) {
            $this->assertSame('0', $row->{$month}, $month);
        }
        $this->assertSame(2, (int) $row->jumlah_kunjungan);
        $this->assertSame('DONE', $row->kunjungan);
        $this->assertSame('350.10', $row->plafond_real);
        $this->assertSame('Sudah', $row->status);
        $this->assertSame('PLAN', $this->row('p2')->mei);
        $this->assertSame(0, (int) $this->row('p2')->jumlah_kunjungan);
        $this->assertSame('NOT', $this->row('p2')->kunjungan);
        $this->assertSame('0', $this->row('p3')->jun);
        $this->assertSame('PLAN', $this->row('prior-year')->jan);
        $this->assertSame(9, (int) $this->row('prior-year')->jumlah_kunjungan);
        $this->assertSame(4, DB::table('pipeline_prewash')->count());
        $this->assertSame($baseBefore, $this->baseRows());
        $this->assertSame($sourcesBefore, $this->sourceRows());
    }

    public function test_realization_uses_the_selected_source_exclusively_and_preserves_decimal_cents(): void
    {
        $this->pipeline('p1', 'C001');
        $this->visit('v1', 'C001', '2026-01-01', 'DONE');
        $this->daily('daily-old', 'C001', 'A1', '900.00', '2026-11-09');
        $this->lw('lw-a1', 'C001', 'A1', '123456789012345678901.25', '2026-11-10');
        $this->lw('lw-a2', 'C001', 'A2', '0.76', '2026-11-10');

        $payload = $this->service()->payload('2026-11-12', null, true);
        $this->assertSame('lw321pn', $payload['loan_source']);
        $this->assertSame('123456789012345678902.01', $this->row('p1')->plafond_real);
        $this->assertSame('123456789012345678902.01', $payload['summary']['realized_amount']);

        $this->daily('daily-tie', 'C001', 'A3', '25.01', '2026-11-10');
        $payload = $this->service()->payload('2026-11-12', null, true);
        $this->assertSame('daily_loan_dinamis', $payload['loan_source']);
        $this->assertSame('25.01', $this->row('p1')->plafond_real);
    }

    public function test_zero_plafond_is_a_valid_match_and_missing_cif_is_kept_separate_from_unmatched_cif(): void
    {
        $this->pipeline('zero', 'CZERO');
        $this->pipeline('unmatched', 'CNONE');
        $this->pipeline('missing', null);
        $this->visit('visit-zero', 'CZERO', '2026-01-01', 'DONE');
        $this->visit('visit-blank', null, '2026-01-01', 'DONE');
        $this->daily('zero-loan', 'CZERO', 'A1', '0.00');
        $this->daily('blank-loan', null, 'A2', '999.00');

        $payload = $this->service()->payload('2026-11-12', null, true);

        $this->assertTrue($payload['available']);
        $this->assertSame('Sudah', $this->row('zero')->status);
        $this->assertSame('0.00', $this->row('zero')->plafond_real);
        $this->assertSame('Belum', $this->row('unmatched')->status);
        $this->assertSame('CIF kosong', $this->row('missing')->status);
        $this->assertNull($this->row('missing')->plafond_real);
        $this->assertSame(0, (int) $this->row('missing')->jumlah_kunjungan);
        $this->assertSame(3, $payload['summary']['total_count']);
        $this->assertSame(1, $payload['summary']['realized_count']);
        $this->assertNotEmpty($payload['quality']);
    }

    public function test_branch_scope_applies_to_bo_mbm_unit_and_summary_and_realization_share_uses_visited_count(): void
    {
        $this->pipeline('madiun-gemarang', 'C001', ['branch' => '00123', 'nama_unit' => 'Unit Gemarang', 'plafond' => '1000000.25']);
        $this->pipeline('madiun-kare', 'C002', ['branch' => '00124', 'nama_unit' => 'Unit Kare', 'plafond' => '2000000.75']);
        $this->pipeline('magetan', 'C003', ['branch' => '00125', 'nama_kanca' => 'KC Magetan', 'nama_unit' => 'Unit Takeran', 'plafond' => '9000000.00']);
        DB::table('wilayah_mbm')->insert([
            ['bc' => '00123', 'nama_uker' => 'Unit Gemarang', 'cabang' => 'KC Madiun', 'nama_mbm' => 'Nur Elfiana'],
            ['bc' => '00124', 'nama_uker' => 'Unit Kare', 'cabang' => 'KC Madiun', 'nama_mbm' => 'Hendri Nur Wahyudi'],
            ['bc' => '00125', 'nama_uker' => 'Unit Takeran', 'cabang' => 'KC Magetan', 'nama_mbm' => 'Muko Hendrasworo'],
        ]);
        $this->visit('v1', 'C001', '2026-10-01', 'DONE');
        $this->visit('v2', 'C002', '2026-10-01', 'PLAN');
        $this->visit('v3', 'C003', '2026-10-01', 'DONE');
        $this->daily('a1', 'C001', 'A1', '500000.50');
        $this->daily('a3', 'C003', 'A3', '700000.00');

        $payload = $this->service()->payload('2026-11-12', UserBranchScope::forKey('madiun'), true);

        $this->assertTrue($payload['available']);
        $this->assertSame(2, $payload['summary']['total_count']);
        $this->assertSame('3000001.00', $payload['summary']['total_amount']);
        $this->assertSame(1, $payload['summary']['visited_count']);
        $this->assertSame(1, $payload['summary']['unvisited_count']);
        $this->assertEquals(50, $payload['summary']['visited_share']);
        $this->assertSame(1, $payload['summary']['realized_count']);
        $this->assertEquals(100, $payload['summary']['realized_share']);
        $this->assertSame('500000.50', $payload['summary']['realized_amount']);
        $this->assertCount(1, $payload['groups']['bo']);
        $this->assertCount(2, $payload['groups']['mbm']);
        $this->assertCount(2, $payload['groups']['unit']);
        foreach ($payload['groups'] as $groups) {
            foreach ($groups as $group) {
                $this->assertStringContainsString('MADIUN', strtoupper($group['branch_name']));
            }
        }
        $mbmByPn = collect($payload['groups']['mbm'])->keyBy('mbm_pn');
        $this->assertTrue($mbmByPn->has('20458'));
        $this->assertTrue($mbmByPn->has('64850'));
        $this->assertSame(1, $mbmByPn->get('20458')['visited_count']);
        $this->assertSame(0, $mbmByPn->get('64850')['visited_count']);
        $this->assertNotContains('99999', $mbmByPn->keys()->all());
        $this->assertSame(3, DB::table('pipeline_prewash')->count());
    }

    public function test_forced_refresh_recalculates_same_period_visit_and_loan_edits_without_changing_source_fields(): void
    {
        $this->pipeline('p1', 'C001');
        $this->visit('v1', 'C001', '2026-10-01', 'PLAN');
        $this->daily('a1', 'C001', 'A1', '100.00');
        $service = $this->service();
        $service->payload('2026-11-12', null, true);
        $this->assertSame('PLAN', $this->row('p1')->okt);
        $this->assertSame('100.00', $this->row('p1')->plafond_real);
        $baseBefore = $this->baseRows();

        DB::table('evaluasi_aktivitas_kunjungan_pemasar')->where('uniqueid_namareport', 'v1')->update(['status_kunjungan' => 'DONE']);
        DB::table('daily_loan_dinamis')->where('uniqueid_namareport', 'a1')->update(['plafon' => '250.75']);
        ReportCacheVersion::bump('pinjaman');
        $payload = $service->payload('2026-11-12', null, true);

        $this->assertSame('DONE', $this->row('p1')->okt);
        $this->assertSame(1, (int) $this->row('p1')->jumlah_kunjungan);
        $this->assertSame('250.75', $this->row('p1')->plafond_real);
        $this->assertSame(1, $payload['summary']['visited_count']);
        $this->assertSame('250.75', $payload['summary']['realized_amount']);
        $this->assertSame($baseBefore, $this->baseRows());
    }

    public function test_missing_loan_source_is_reported_as_unavailable(): void
    {
        $this->pipeline('p1', 'C001');
        $this->visit('v1', 'C001', '2026-01-01', 'DONE');

        $payload = $this->service()->payload('2026-11-12', null, true);

        $this->assertFalse($payload['available']);
        $this->assertNotEmpty($payload['message']);
        $this->assertNull($this->row('p1')->plafond_real);
    }

    public function test_missing_visit_source_is_reported_as_unavailable_without_fabricating_monthly_zeroes(): void
    {
        $this->pipeline('p1', 'C001');
        $this->daily('a1', 'C001', 'A1', '100.00');
        Schema::drop('evaluasi_aktivitas_kunjungan_pemasar');

        $payload = $this->service()->payload('2026-11-12', null, true);

        $this->assertFalse($payload['available']);
        $this->assertNotEmpty($payload['message']);
        $this->assertNull($this->row('p1')->jan);
        $this->assertNull($this->row('p1')->jumlah_kunjungan);
    }

    public function test_old_year_visits_and_old_printed_period_leave_current_year_derived_fields_unavailable(): void
    {
        $this->pipeline('p1', 'C001');
        $this->daily('a1', 'C001', 'A1', '100.00');
        $this->visit('old-visit', 'C001', '2025-12-31', 'DONE');
        DB::table('evaluasi_aktivitas_kunjungan_pemasar')->where('uniqueid_namareport', 'old-visit')
            ->update(['periode' => '2025-12-31']);
        $sourcesBefore = $this->sourceRows();

        $payload = $this->service()->payload('2026', null, true);

        $this->assertFalse($payload['available']);
        $this->assertStringContainsString('2026', $payload['message']);
        foreach ([...self::MONTHS, 'jumlah_kunjungan', 'kunjungan', 'plafond_real', 'status'] as $column) {
            $this->assertNull($this->row('p1')->{$column}, $column);
        }
        $this->assertSame($sourcesBefore, $this->sourceRows());
    }

    public function test_whitespace_only_cif_never_matches_whitespace_identifiers_in_visit_or_loan_sources(): void
    {
        $this->pipeline('blank-cif', '   ');
        $this->pipeline('valid-cif', 'C001');
        $this->visit('blank-visit', '   ', '2026-01-01', 'DONE');
        $this->visit('valid-visit', 'C001', '2026-02-01', 'DONE');
        $this->daily('blank-loan', '   ', 'A0', '999999.99');
        $this->daily('valid-loan', 'C001', 'A1', '100.01');
        $sourcesBefore = $this->sourceRows();

        $payload = $this->service()->payload('2026', null, true);

        $this->assertTrue($payload['available']);
        $blank = $this->row('blank-cif');
        $this->assertSame('   ', $blank->cif);
        $this->assertSame('CIF kosong', $blank->status);
        $this->assertNull($blank->plafond_real);
        $this->assertSame(0, (int) $blank->jumlah_kunjungan);
        $this->assertSame('NOT', $blank->kunjungan);
        foreach (self::MONTHS as $month) {
            $this->assertSame('0', $blank->{$month}, $month);
        }
        $this->assertSame(1, $payload['quality']['missing_cif']);
        $this->assertSame(1, $payload['summary']['visited_count']);
        $this->assertSame(1, $payload['summary']['realized_count']);
        $this->assertSame('100.01', $payload['summary']['realized_amount']);
        $this->assertSame($sourcesBefore, $this->sourceRows());
    }

    public function test_year_selection_accepts_a_four_digit_year_with_surrounding_whitespace(): void
    {
        $this->pipeline('p1', 'C001');
        $this->visit('v1', 'C001', '2026-01-01', 'DONE');
        $this->daily('a1', 'C001', 'A1', '100.00');

        $payload = $this->service()->payload('2026 ', null, true);

        $this->assertTrue($payload['available']);
        $this->assertSame(2026, $payload['year']);
        $this->assertSame('DONE', $this->row('p1')->jan);
    }

    public function test_numeric_excel_serial_cannot_be_interpreted_as_a_report_year(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('YYYY');

        $this->service()->payload('45200', null, true);
    }

    public function test_conflicting_plafonds_for_one_distinct_account_require_verification_and_are_not_summed(): void
    {
        $this->pipeline('conflict', 'C001');
        $this->pipeline('verified', 'C002');
        $this->visit('v1', 'C001', '2026-01-01', 'DONE');
        $this->visit('v2', 'C002', '2026-01-01', 'DONE');
        $this->daily('conflict-a', 'C001', '00012345', '100.00');
        $this->daily('conflict-b', 'C001', '12345', '100.01');
        $this->daily('conflict-other-account', 'C001', '12346', '500.00');
        $this->daily('verified-account', 'C002', '23456', '200.20');
        $sourcesBefore = $this->sourceRows();

        $payload = $this->service()->payload('2026', null, true);

        $this->assertTrue($payload['available']);
        $this->assertSame('Perlu verifikasi', $this->row('conflict')->status);
        $this->assertNull($this->row('conflict')->plafond_real);
        $this->assertSame('DONE', $this->row('conflict')->kunjungan);
        $this->assertSame('Sudah', $this->row('verified')->status);
        $this->assertSame('200.20', $this->row('verified')->plafond_real);
        $this->assertSame(1, $payload['quality']['unverified_realization']);
        $this->assertSame(2, $payload['summary']['total_count']);
        $this->assertSame(2, $payload['summary']['visited_count']);
        $this->assertSame(1, $payload['summary']['realized_count']);
        $this->assertSame('200.20', $payload['summary']['realized_amount']);
        $this->assertSame($sourcesBefore, $this->sourceRows());
    }

    public function test_unassigned_bo_records_are_identified_without_guessing_an_mbm_or_merging_an_unmapped_unit(): void
    {
        $this->pipeline('magetan-bo', null, ['branch' => '00049', 'nama_kanca' => 'KC Magetan', 'nama_unit' => 'KC MAGETAN', 'pn_mantri' => null, 'nama_mantri' => null, 'plafond' => '250000000.00']);
        $this->pipeline('ponorogo-bo', null, ['branch' => '70', 'nama_kanca' => 'KC PONOROGO', 'nama_unit' => 'KC Ponorogo', 'pn_mantri' => null, 'nama_mantri' => null, 'plafond' => '400000000.00']);
        $this->pipeline('mapped-unit', 'C001', ['branch' => '00125', 'nama_kanca' => 'KC Magetan', 'nama_unit' => 'Unit Takeran', 'plafond' => '1000000.00']);
        $this->pipeline('unknown-unit', 'C002', ['branch' => '00126', 'nama_kanca' => 'KC Magetan', 'nama_unit' => 'Unit Belum Ada Referensi', 'plafond' => '2000000.00']);
        DB::table('wilayah_mbm')->insert(['bc' => '00125', 'nama_uker' => 'Unit Takeran', 'cabang' => 'KC Magetan', 'nama_mbm' => 'Muko Hendrasworo']);
        $this->visit('v1', 'C001', '2026-01-01', 'DONE');
        $this->daily('loan', 'C001', 'A1', '500000.00');
        $before = $this->baseRows();

        $payload = $this->service()->payload('2026', null, true);

        $this->assertSame(4, $payload['summary']['total_count']);
        $this->assertSame('653000000.00', $payload['summary']['total_amount']);
        $this->assertSame('500000.00', $payload['summary']['realized_amount']);
        $this->assertSame(2, $payload['quality']['missing_cif']);
        $this->assertSame(3, $payload['quality']['unmapped_mbm']);
        $this->assertSame(2, $payload['quality']['branch_pipeline']);
        $this->assertCount(4, $payload['groups']['mbm']);
        $branchPipelines = collect($payload['groups']['mbm'])->where('mbm_name', 'Pipeline BO — PIC MBM belum tersedia')->values();
        $this->assertCount(2, $branchPipelines);
        foreach ($branchPipelines as $group) {
            $this->assertNull($group['mbm_pn']);
            $this->assertSame(1, $group['total_count']);
        }
        $this->assertSame(1, collect($payload['groups']['mbm'])->where('mbm_name', 'Belum dipetakan')->count());
        $this->assertSame('22263', collect($payload['groups']['mbm'])->firstWhere('mbm_name', 'Muko Hendrasworo')['mbm_pn']);
        $this->assertSame($before, $this->baseRows());
    }

    public function test_an_explicit_bo_territory_assignment_takes_priority_over_the_unassigned_bo_label(): void
    {
        $this->pipeline('bo', null, ['branch' => '49', 'nama_kanca' => 'KC Magetan', 'nama_unit' => 'KC MAGETAN', 'pn_mantri' => null, 'nama_mantri' => null]);
        DB::table('wilayah_mbm')->insert(['bc' => '00049', 'nama_uker' => 'KC MAGETAN', 'cabang' => 'KC Magetan', 'nama_mbm' => 'Muko Hendrasworo']);
        $this->visit('source-present', 'C001', '2026-01-01', 'DONE');
        $this->daily('loan', 'C001', 'A1', '1.00');

        $payload = $this->service()->payload('2026', null, true);

        $this->assertSame(0, $payload['quality']['unmapped_mbm']);
        $this->assertSame(0, $payload['quality']['branch_pipeline']);
        $this->assertSame('22263', $payload['groups']['mbm'][0]['mbm_pn']);
        $this->assertSame('Muko Hendrasworo', $payload['groups']['mbm'][0]['mbm_name']);
        $this->assertNull($this->row('bo')->cif);
    }

    public function test_a_cached_legacy_group_label_is_not_reused_after_bo_classification_changes(): void
    {
        $this->pipeline('bo', null, ['branch' => '49', 'nama_kanca' => 'KC Magetan', 'nama_unit' => 'KC MAGETAN']);
        $this->visit('source-present', 'C001', '2026-01-01', 'DONE');
        $this->daily('loan', 'C001', 'A1', '1.00');
        $service = $this->service();
        $meta = $service->refresh(2026, true);
        $signature = Cache::get('pipeline-prewash:derived:v1:2026')['signature'];
        $legacyKey = 'pipeline-prewash:groups:v1:'.hash('sha256', json_encode([2026, $meta, $signature, null, ReportCacheVersion::get()]));
        Cache::put($legacyKey, ['available' => true, 'groups' => ['mbm' => [['mbm_name' => 'Belum dipetakan']]]], 120);

        $payload = $service->payload('2026');

        $this->assertSame('Pipeline BO — PIC MBM belum tersedia', $payload['groups']['mbm'][0]['mbm_name']);
        $this->assertSame(1, $payload['quality']['branch_pipeline']);
        $this->assertSame(1, $payload['quality']['unmapped_mbm']);
        $this->assertSame(1, $payload['summary']['total_count']);
    }

    private function service(): PipelinePrewashService
    {
        return app(PipelinePrewashService::class);
    }

    private function pipeline(string $id, ?string $cif, array $values = []): void
    {
        DB::table('pipeline_prewash')->insert(array_replace([
            'uniqueid_namareport' => $id, 'tahun' => 2026, 'nama_debt' => 'Debitur '.$id,
            'alamat' => 'Alamat asli', 'nomorhandphone' => '08123456789', 'cif' => $cif,
            'norek_pinjaman' => '000012345678901', 'branch' => '00123', 'nama_unit' => 'Unit Gemarang',
            'nama_kanca' => 'KC Madiun', 'plafond' => '1000000.00', 'os' => '500000.00',
            'sumberpipeline' => 'SLIK HIJAU', 'pn_mantri' => '99999', 'nama_mantri' => 'Mantri sumber',
            'product_rekomendasi' => 'KUPEDES', 'score' => '01.25', 'idpipeline_lama' => '000001',
            'sumberpipeline_lama' => 'Sumber lama', 'created_at' => '2026-11-12 09:00:00',
            'updated_at' => '2026-11-12 09:00:00',
        ], $values));
    }

    private function visit(string $id, ?string $cif, string $date, string $status): void
    {
        DB::table('evaluasi_aktivitas_kunjungan_pemasar')->insert([
            'uniqueid_namareport' => $id, 'cifno' => $cif, 'tgl_kunjungan' => $date,
            'status_kunjungan' => $status, 'periode' => '2026-11-12',
            'created_at' => '2026-11-12 09:16:45', 'updated_at' => '2026-11-12 09:16:45',
        ]);
    }

    private function daily(string $id, ?string $cif, string $account, string $amount, string $period = '2026-11-10', string $realized = '2026-01-01'): void
    {
        DB::table('daily_loan_dinamis')->insert([
            'uniqueid_namareport' => $id, 'cifno' => $cif, 'nomor_rekening1' => $account,
            'tgl_realisasi' => $realized, 'plafon' => $amount, 'periode' => $period,
            'created_at' => '2026-11-12 09:00:00', 'updated_at' => '2026-11-12 09:00:00',
        ]);
    }

    private function lw(string $id, ?string $cif, string $account, string $amount, string $period): void
    {
        DB::table('lw321pn')->insert([
            'uniqueid_namareport' => $id, 'cifno' => $cif, 'no_rekening' => $account,
            'tgl_realisasi' => '2026-01-01', 'plafon_dalam_idr' => $amount, 'periode' => $period,
            'created_at' => '2026-11-12 09:00:00', 'updated_at' => '2026-11-12 09:00:00',
        ]);
    }

    private function row(string $id): object
    {
        return DB::table('pipeline_prewash')->where('uniqueid_namareport', $id)->first();
    }

    private function baseRows(): array
    {
        return DB::table('pipeline_prewash')->orderBy('uniqueid_namareport')
            ->get(['uniqueid_namareport', 'tahun', 'kanwil', ...self::BASE_COLUMNS])
            ->map(static fn (object $row): array => (array) $row)->all();
    }

    private function sourceRows(): array
    {
        $rows = [];
        foreach (['evaluasi_aktivitas_kunjungan_pemasar', 'daily_loan_dinamis', 'lw321pn'] as $table) {
            $rows[$table] = DB::table($table)->orderBy('uniqueid_namareport')->get()
                ->map(static fn (object $row): array => (array) $row)->all();
        }

        return $rows;
    }
}
