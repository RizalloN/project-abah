<?php

namespace Tests\Unit;

use App\Services\Import\MonitoringKsbkSourceService as Source;
use App\Support\LandingMicroKsbkService;
use App\Support\UserBranchScope;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MonitoringKsbkAnalyticsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Schema::create(Source::TABLE, function (Blueprint $table): void {
            $table->string('uniqueid_namareport')->primary();
            foreach ([...Source::IMPORT_COLUMNS, 'cek_real', 'plafond', 'ln_type', 'ksbk_non'] as $column) {
                $table->string($column)->nullable();
            }
            $table->timestamps();
        });
    }

    public function test_empty_dataset_explains_that_import_is_needed_without_claiming_a_branch_filter_problem(): void
    {
        $result = (new LandingMicroKsbkService)->payload(UserBranchScope::forKey('madiun'));

        $this->assertFalse($result['available']);
        $this->assertSame([], $result['groups']);
        $this->assertSame([], $result['summary']);
        $this->assertStringContainsString('belum diimport', $result['message']);
        $this->assertStringNotContainsString('cabang yang dipilih', $result['message']);
        $this->assertSame(0, DB::table(Source::TABLE)->count());
    }

    public function test_existing_dataset_outside_selected_branch_keeps_the_specific_branch_empty_message(): void
    {
        $this->pipeline('PONOROGO', '70');
        $before = (array) DB::table(Source::TABLE)->first();

        $result = (new LandingMicroKsbkService)->payload(UserBranchScope::forKey('madiun'));

        $this->assertFalse($result['available']);
        $this->assertStringContainsString('cabang yang dipilih', $result['message']);
        $this->assertStringNotContainsString('belum diimport', $result['message']);
        $this->assertSame($before, (array) DB::table(Source::TABLE)->first());
    }

    public function test_latest_source_and_equal_period_daily_priority_recalculate_stored_derived_values(): void
    {
        $this->pipeline('A');
        $this->loan('lw321pn', '2026-11-10', 'A', '100000000.00', '8E');
        $this->loan('daily_loan_dinamis', '2026-11-09', 'A', '200000000.00', '1H');
        $service = new LandingMicroKsbkService;
        $result = $service->payload(null);
        $this->assertSame('lw321pn', $result['loan_source']);
        $this->assertSame(1, $result['summary']['ksbk_count']);
        $this->assertSame('100000000.00', $result['summary']['ksbk_amount']);
        $this->loan('daily_loan_dinamis', '2026-11-10', 'A', '225000000.00', '1H');
        $result = $service->payload(null);
        $this->assertSame('daily_loan_dinamis', $result['loan_source']);
        $this->assertSame(1, $result['summary']['non_ksbk_count']);
        $this->assertSame('225000000.00', DB::table(Source::TABLE)->value('plafond'));
        $this->assertSame('Non KSBK', DB::table(Source::TABLE)->value('ksbk_non'));
        $this->assertSame('Sudah Real', DB::table(Source::TABLE)->value('cek_real'));
        $this->loan('lw321pn', '2026-11-11', 'A', '35000000.00', '7F');
        $this->assertSame('35000000.00', $service->payload(null)['summary']['ksbk_amount']);
    }

    public function test_mirrored_lw_rows_never_establish_a_native_daily_report(): void
    {
        $this->pipeline('A');
        $this->loan('lw321pn', '2026-11-10', 'A', '15000000.00', '8F');
        $this->loan('daily_loan_dinamis', '2026-11-11', 'A', '999000000.00', '1H', '45', 'mirror', 'LW321PN:mirror');
        $result = (new LandingMicroKsbkService)->payload(null);
        $this->assertSame('lw321pn', $result['loan_source']);
        $this->assertSame('15000000.00', $result['summary']['ksbk_amount']);
    }

    public function test_branch_scope_and_group_totals_match_reference_structure_and_weighted_share(): void
    {
        $this->pipeline('A', '45', 'Kupedes <= 150juta');
        $this->pipeline('B', '45', 'Kupedes > 150juta');
        $this->pipeline('C', '70', 'Kupedes <= 150juta');
        $this->pipeline('D', '70', 'Kupedes > 150juta');
        $this->loan('daily_loan_dinamis', '2026-11-10', 'A', '100000000.00', '8E');
        $this->loan('daily_loan_dinamis', '2026-11-10', 'C', '50000000.00', '1H', '00070');
        $result = (new LandingMicroKsbkService)->payload(null);
        $this->assertSame(2, count($result['groups']));
        $this->assertSame(2, $result['summary']['small_count']);
        $this->assertSame(2, $result['summary']['large_count']);
        $this->assertSame(4, $result['summary']['total_count']);
        $this->assertSame(2, $result['summary']['realized_count']);
        $this->assertSame('150000000.00', $result['summary']['realized_amount']);
        $this->assertSame(50, $result['summary']['unrealized_share']);
        DB::table(Source::TABLE)->where('cifno', 'C')->update(['plafond' => '777.00']);
        $result = (new LandingMicroKsbkService)->payload(UserBranchScope::forKey('madiun'));
        $this->assertSame(2, $result['summary']['total_count']);
        $this->assertSame('100000000.00', $result['summary']['ksbk_amount']);
        $this->assertSame('777.00', DB::table(Source::TABLE)->where('cifno', 'C')->value('plafond'));
    }

    public function test_missing_source_and_missing_cif_remain_unknown_but_valid_unmatched_cif_is_not_realized(): void
    {
        $this->pipeline('A');
        $result = (new LandingMicroKsbkService)->payload(null);
        $this->assertNull($result['summary']['realized_count']);
        $this->assertNull(DB::table(Source::TABLE)->value('plafond'));
        $this->loan('daily_loan_dinamis', '2026-11-10', 'OTHER', '1.00', '1H');
        $result = (new LandingMicroKsbkService)->payload(null);
        $this->assertSame(0, $result['summary']['realized_count']);
        $this->assertSame(1, $result['summary']['unrealized_count']);
        $this->assertSame('Belum Real', DB::table(Source::TABLE)->value('ksbk_non'));
        DB::table(Source::TABLE)->update(['cifno' => null]);
        $result = (new LandingMicroKsbkService)->payload(null);
        $this->assertNull($result['summary']['unrealized_count']);
        $this->assertNull(DB::table(Source::TABLE)->value('plafond'));
    }

    public function test_multiple_accounts_conflicting_amounts_and_missing_amount_are_not_arbitrary_lookup_matches(): void
    {
        $this->pipeline('A');
        $this->loan('daily_loan_dinamis', '2026-11-10', 'A', '100000000.00', '8E', '45', '1');
        $this->loan('daily_loan_dinamis', '2026-11-10', 'A', '200000000.00', '1H', '45', '2');
        $result = (new LandingMicroKsbkService)->payload(null);
        $this->assertSame(1, $result['quality']['unverified_realization']);
        $this->assertNull($result['summary']['realized_amount']);
        $this->assertNull(DB::table(Source::TABLE)->value('ln_type'));
        DB::table('daily_loan_dinamis')->where('nomor_rekening1', '2')->delete();
        $result = (new LandingMicroKsbkService)->payload(null);
        $this->assertSame('100000000.00', $result['summary']['ksbk_amount']);
        $this->loan('daily_loan_dinamis', '2026-11-10', 'A', '100000000.00', '8E', '45', '1');
        $this->assertSame(1, (new LandingMicroKsbkService)->payload(null)['summary']['ksbk_count']);
        DB::table('daily_loan_dinamis')->update(['plafon' => null]);
        $this->assertNull((new LandingMicroKsbkService)->payload(null)['summary']['realized_count']);
    }

    public function test_partial_unverified_branch_does_not_blank_out_summary_of_verified_branches(): void
    {
        $this->pipeline('A', '45', 'Kupedes <= 150juta');
        $this->pipeline('B', '45', 'Kupedes > 150juta');
        $this->pipeline('C', '70', 'Kupedes > 150juta');
        $this->loan('daily_loan_dinamis', '2026-11-10', 'A', '100000000.00', '8E', '45');
        $this->loan('daily_loan_dinamis', '2026-11-10', 'B', '50000000.00', '1H', '45');
        // CIF C has conflicting accounts, so Ponorogo is unverified
        $this->loan('daily_loan_dinamis', '2026-11-10', 'C', '100000000.00', '8E', '70', 'c1');
        $this->loan('daily_loan_dinamis', '2026-11-10', 'C', '200000000.00', '1H', '70', 'c2');

        $result = (new LandingMicroKsbkService)->payload(null);

        $this->assertSame(1, $result['quality']['unverified_realization']);
        $this->assertSame(3, $result['summary']['total_count']);
        $this->assertSame(1, $result['summary']['ksbk_count']);
        $this->assertSame('100000000.00', $result['summary']['ksbk_amount']);
        $this->assertSame(1, $result['summary']['non_ksbk_count']);
        $this->assertSame('50000000.00', $result['summary']['non_ksbk_amount']);
        $this->assertSame(2, $result['summary']['realized_count']);
        $this->assertSame('150000000.00', $result['summary']['realized_amount']);
        $this->assertSame(1, $result['summary']['unrealized_count']);
    }

    public function test_exact_ksbk_formula_for_all_four_types_and_zero_loan_type(): void
    {
        foreach (['8E', '8F', '7E', '7F', '1H', '0'] as $type) {
            $this->pipeline('C'.$type);
            $this->loan('daily_loan_dinamis', '2026-11-10', 'C'.$type, $type === '0' ? '0.00' : '1000000.00', $type);
        }
        $result = (new LandingMicroKsbkService)->payload(null);
        $this->assertSame(4, $result['summary']['ksbk_count']);
        $this->assertSame(1, $result['summary']['non_ksbk_count']);
        $this->assertSame(1, $result['summary']['unrealized_count']);
        $this->assertSame('Belum Real', DB::table(Source::TABLE)->where('cifno', 'C0')->value('ksbk_non'));
    }

    public function test_cif_lookup_may_match_another_authorized_branch_without_changing_proposal_group(): void
    {
        $this->pipeline('A', '45');
        $this->loan('daily_loan_dinamis', '2026-11-10', 'A', '1000000.00', '7F', '70');
        $result = (new LandingMicroKsbkService)->payload(null);
        $this->assertSame('KC Madiun', $result['groups'][0]['branch_name']);
        $this->assertSame(1, $result['summary']['ksbk_count']);
        $this->assertSame('1000000.00', $result['summary']['ksbk_amount']);
    }

    public function test_source_decimal_precision_and_historical_realization_dates_are_preserved(): void
    {
        $this->pipeline('A');
        $this->loan('lw321pn', '2026-11-10', 'A', '9007199254740993.01', '8E');
        $result = (new LandingMicroKsbkService)->payload(null);
        $this->assertSame('9007199254740993.01', $result['summary']['ksbk_amount']);
        $this->assertSame('9007199254740993.01', DB::table(Source::TABLE)->value('plafond'));
    }

    public function test_unit_groups_and_read_only_records_share_scoped_source_rows_and_exact_totals(): void
    {
        Schema::create('referensi_uker', function (Blueprint $table): void {
            foreach (['kode_cabang', 'kode_uker', 'nama_uker'] as $column) {
                $table->string($column);
            }
        });
        DB::table('referensi_uker')->insert([
            ['kode_cabang' => '00045', 'kode_uker' => '03508', 'nama_uker' => '03508 -- UNIT DIPONEGORO MADIUN'],
            ['kode_cabang' => '00045', 'kode_uker' => '03888', 'nama_uker' => '03888 -- UNIT SLEKO MADIUN'],
            ['kode_cabang' => '00070', 'kode_uker' => '03843', 'nama_uker' => '03843 -- UNIT JETIS PONOROGO'],
        ]);
        foreach (['A', 'B', 'C'] as $cif) {
            $this->pipeline($cif, '45', $cif === 'B' ? 'Kupedes > 150juta' : 'Kupedes <= 150juta');
            DB::table(Source::TABLE)->where('cifno', $cif)->update(['branch' => $cif === 'C' ? '3888' : '3508']);
        }
        $this->pipeline('OTHER', '70');
        DB::table(Source::TABLE)->where('cifno', 'OTHER')->update(['branch' => '3843', 'plafond' => '777.00']);
        $otherBefore = (array) DB::table(Source::TABLE)->where('cifno', 'OTHER')->first();
        $this->loan('daily_loan_dinamis', '2026-11-10', 'A', '9007199254740993.01', '8E');
        $this->loan('daily_loan_dinamis', '2026-11-10', 'B', '200.02', '1H');
        $service = new LandingMicroKsbkService;
        $scope = UserBranchScope::forKey('madiun');
        $before = DB::table(Source::TABLE)->orderBy('uniqueid_namareport')->get()->all();
        $records = $service->records($scope);
        $this->assertEquals($before, DB::table(Source::TABLE)->orderBy('uniqueid_namareport')->get()->all());
        $this->assertSame(['A', 'B', 'C'], array_column($records, 'cifno'));
        $this->assertSame(['3508', '3508', '3888'], array_column($records, 'unit_code'));
        $this->assertSame(['small_count', 'large_count', 'small_count'], array_column($records, 'product_bucket'));
        $this->assertSame('UNIT DIPONEGORO MADIUN', $records[0]['unit_name']);
        $this->assertSame('45', $records[0]['branch_code']);
        $this->assertSame('9007199254740993.01', $records[0]['plafond']);
        $result = $service->payload($scope);
        $this->assertSame('madiun', $result['scope_key']);
        $this->assertCount(1, $result['groups']);
        $this->assertCount(2, $result['unit_groups']);
        $this->assertSame(0, $result['quality']['unmapped_unit']);
        $this->assertSame(3, array_sum(array_column($result['unit_groups'], 'total_count')));
        $this->assertSame('9007199254741193.03', $result['summary']['realized_amount']);
        $this->assertSame($result['summary']['realized_amount'], $result['unit_groups'][0]['realized_amount']);
        $this->assertSame(1, $result['unit_groups'][1]['unrealized_count']);
        $this->assertSame($otherBefore, (array) DB::table(Source::TABLE)->where('cifno', 'OTHER')->first());
        $this->assertArrayNotHasKey('records', $result);
        $this->assertCount(3, $service->records($scope));
        $this->assertCount(4, $service->records(null));
    }

    public function test_unmapped_unit_stays_explicit_and_cannot_borrow_another_branch_reference(): void
    {
        Schema::create('referensi_uker', function (Blueprint $table): void {
            foreach (['kode_cabang', 'kode_uker', 'nama_uker'] as $column) {
                $table->string($column);
            }
        });
        DB::table('referensi_uker')->insert(['kode_cabang' => '00070', 'kode_uker' => '03508', 'nama_uker' => '03508 -- WRONG BRANCH UNIT']);
        $this->pipeline('A');
        $this->pipeline('B');
        DB::table(Source::TABLE)->where('cifno', 'A')->update(['branch' => '03508']);
        $result = (new LandingMicroKsbkService)->payload(UserBranchScope::forKey('madiun'));
        $units = array_column($result['unit_groups'], null, 'unit_code');
        $this->assertSame('Unit 3508', $units['3508']['unit_name']);
        $this->assertSame('Unit belum terpetakan', $units['']['unit_name']);
        $this->assertNull($units['3508']['realized_count']);
        $this->assertSame(2, $result['quality']['unmapped_unit']);
        $this->assertSame(2, array_sum(array_column($result['unit_groups'], 'total_count')));
    }

    private function pipeline(string $cif, string $bo = '45', string $product = 'Kupedes <= 150juta'): void
    {
        DB::table(Source::TABLE)->insert(['uniqueid_namareport' => 'proposal-'.$cif, 'cifno' => $cif, 'nama' => 'Fixture', 'bo' => $bo, 'ro' => 'R', 'produk_sbk' => $product, 'hasil_cek' => 'Lolos']);
    }

    private function loan(string $table, string $period, string $cif, ?string $amount, string $type, string $branch = '45', ?string $account = null, ?string $identity = null): void
    {
        $daily = $table === 'daily_loan_dinamis';
        $branchColumn = $daily ? 'kode_cabang1' : 'kode_kanca';
        $accountColumn = $daily ? 'nomor_rekening1' : 'no_rekening';
        $amountColumn = $daily ? 'plafon' : 'plafon_dalam_idr';
        if (! Schema::hasTable($table)) {
            Schema::create($table, function (Blueprint $schema) use ($branchColumn, $accountColumn, $amountColumn): void {
                foreach (['uniqueid_namareport', 'periode', 'cifno', 'ln_type', 'tgl_realisasi', $branchColumn, $accountColumn, $amountColumn] as $column) {
                    $schema->string($column)->nullable();
                }
            });
        }
        DB::table($table)->insert(['uniqueid_namareport' => $identity ?? uniqid('native'), 'periode' => $period, 'cifno' => $cif, 'ln_type' => $type, 'tgl_realisasi' => '2020-01-01', $branchColumn => $branch, $accountColumn => $account ?? 'account-'.$cif, $amountColumn => $amount]);
    }
}
