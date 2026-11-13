<?php

namespace Tests\Unit;

use App\Support\SectorPotentialService;
use App\Support\UserBranchScope;
use App\Support\VillageOwnershipReferenceService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class SectorPotentialServiceTest extends TestCase
{
    private const CATEGORIES = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M-N', 'O', 'P', 'Q', 'R-U'];

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        Cache::flush();
        Schema::create('cras', function (Blueprint $table): void {
            $table->date('cras_periode');
            foreach (['ket_kanca', 'br_number', 'ket_unit_kerja', 'sektor_ekonomi', 'sub_sektor_ekonomi', 'jumlah_debitur', 'status_rekening', 'segmen', 'jumlah_rekening', 'baki_debet', 'saldo_ph'] as $column) {
                $table->string($column)->nullable();
            }
        });
        $this->ownership($this->ownershipRows());
    }

    public function test_source_parser_preserves_all_categories_zeroes_and_english_grouping(): void
    {
        $payload = (new SectorPotentialService)->referenceFromCsv($this->csv());

        $this->assertCount(17, $payload['source_sectors']);
        $this->assertSame(17, $payload['source']['row_count']);
        $this->assertSame(1, $payload['source']['village_count']);
        $this->assertSame(0.0, $payload['districts'][0]['source_values']['A']);
        $this->assertSame(1234.0, $payload['districts'][0]['source_values']['G']);
        $this->assertSame(SectorPotentialService::SPREADSHEET_ID, $payload['source']['spreadsheet_id']);
    }

    public function test_duplicate_village_category_is_rejected_instead_of_double_counting(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Duplikasi');
        (new SectorPotentialService)->referenceFromCsv($this->csv()."Desa Satu,Kebonsari,Kabupaten Madiun,A,A,5\n");
    }

    public function test_incomplete_categories_are_not_published_as_zeroes(): void
    {
        $this->expectException(RuntimeException::class);
        (new SectorPotentialService)->referenceFromCsv("Desa/Kelurahan,Kecamatan,Kabupaten/Kota,Keterangan,Deskripsi,Jumlah\nDesa,Kebonsari,Kabupaten Madiun,A,Pertanian,1\n");
    }

    public function test_one_complete_village_cannot_hide_an_incomplete_neighbouring_village(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Kategori desa belum lengkap');
        (new SectorPotentialService)->referenceFromCsv($this->csv()."Desa Dua,Kebonsari,Kabupaten Madiun,A,A,5\n");
    }

    public function test_latest_period_numerators_keep_coverage(): void
    {
        $this->insert('2026-09-05', 'KC Madiun', 'PERDAGANGAN BESAR DAN ECERAN', 900);
        $this->insert('2026-09-30', 'KC Madiun', 'PERDAGANGAN BESAR DAN ECERAN', 100);
        $this->insert('2026-09-30', 'KC Madiun', '', 65);
        $this->insert('2026-09-30', 'KC Madiun', 'PENERIMA KREDIT BUKAN LAPANGAN USAHA', 2);
        $this->insert('2026-09-30', 'KC Madiun', 'LISTRIK, GAS DAN AIR', 15);
        $payload = $this->service()->payload('madiun');

        $this->assertTrue($payload['ready']);
        $this->assertSame('2026-09-30', $payload['period']);
        $this->assertSame(182.0, $payload['coverage']['ssa_debtors']);
        $this->assertSame(182.0, $payload['coverage']['eligible_debtors']);
        $this->assertSame(65.0, $payload['coverage']['unclassified_debtors']);
        $this->assertSame(2.0, $payload['coverage']['nonbusiness_debtors']);
        // Coded counts stay as the source; the 65 sector-less debtors follow the BPS mix (3,400
        // businesses, trade 200 of them: 65 x 200/3,400 = 3.8 -> 4). Non-business credit stays OTHER.
        $this->assertSame(100.0, $payload['totals']['trade']['existing_source']);
        $this->assertSame(104.0, $payload['totals']['trade']['existing']);
        $this->assertSame(15.0, $payload['totals']['utilities']['existing_source']);
        $this->assertSame(400.0, $payload['totals']['utilities']['potential']);
        $this->assertSame(182.0, $payload['totals']['total']['existing']);
        $this->assertSame(2.0, $payload['totals']['unclassified']['existing']);
        $this->assertSame(67.0, $payload['totals']['unclassified']['existing_source']);
        $sectors = array_diff_key($payload['totals'], array_flip(['total', 'unclassified']));
        $this->assertSame(180.0, array_sum(array_column($sectors, 'existing')));
        foreach ($sectors as $sector) {
            $this->assertLessThanOrEqual($sector['potential'], $sector['existing']);
        }
        $this->assertCount(16, $payload['comparison_groups']); // Total, 14 comparable groups and OTHER; source retains 17.
        $this->assertCount(17, $payload['source_sectors']);
    }

    public function test_unit_potential_uses_only_its_owned_villages_and_district_allocation_is_explicit(): void
    {
        $this->insert('2026-09-30', 'KC Madiun', 'PERDAGANGAN BESAR DAN ECERAN', 100);
        $payload = $this->service()->payload('madiun');

        $this->assertSame(200.0, $payload['branches'][0]['values']['trade']['potential']);
        $this->assertSame(50.0, $payload['branches'][0]['values']['trade']['penetration']);
        foreach ($payload['districts'] as $district) {
            $this->assertSame(50.0, $district['values']['trade']['existing']);
            $this->assertTrue($district['allocation_estimate']);
        }
        $this->assertSame(100.0, $payload['units'][0]['values']['trade']['existing']);
        $this->assertSame(200.0, $payload['units'][0]['values']['trade']['potential']);
        $this->assertSame(50.0, $payload['units'][0]['values']['trade']['penetration']);
        $this->assertSame(50.0, $payload['units'][0]['values']['trade']['penetration_raw']);
        $this->assertTrue($payload['units'][0]['potential_available']);
        $this->assertSame(2, $payload['units'][0]['village_count']);
        $this->assertSame('pelaku_usaha_desa_milik_uker', $payload['units'][0]['denominator_grain']);
        $this->assertSame(200.0, $payload['units'][0]['source_values']['G']);
        $this->assertFalse($payload['subsector_potential_available']);
    }

    public function test_same_owner_duplicate_is_deduplicated_and_missing_village_population_is_disclosed(): void
    {
        $rows = $this->ownershipRows();
        $rows[] = $rows[0];
        $rows[] = [4, 'Tidak Ada Di Sumber', 'Kebonsari', 'Kabupaten Madiun', 'Desa', 'Jawa Timur', 'KC Madiun', '09999'];
        $this->ownership($rows);
        $this->insert('2026-09-30', 'KC Madiun', 'PERDAGANGAN BESAR DAN ECERAN', 240);
        $payload = $this->service()->payload('madiun');
        $unit = $payload['units'][0];
        $this->assertSame(2, $unit['village_count']);
        $this->assertSame(3, $unit['owned_village_count']);
        $this->assertSame(1, $unit['missing_population_village_count']);
        $this->assertTrue($unit['potential_partial']);
        $this->assertSame(200.0, $unit['values']['trade']['potential']);
        // 240 coded to trade against 200 businesses: trade holds 200, the 40 above it move to other sectors.
        $this->assertSame(240.0, $unit['values']['trade']['existing_source']);
        $this->assertSame(200.0, $unit['values']['trade']['existing']);
        $this->assertSame(120.0, $unit['values']['trade']['penetration_raw']);
        $this->assertSame(100.0, $unit['values']['trade']['penetration']);
        $this->assertSame(20.0, $unit['values']['trade']['penetration_excess']);
        $this->assertTrue($unit['values']['trade']['normalization_applied']);
        $this->assertSame(240.0, $unit['values']['total']['existing']);
        $this->assertSame(0.8333333333333334, $unit['normalization']['keep_ratio']['trade']);

        $this->assertSame(1, $payload['coverage']['missing_population_village_count']);
        $this->assertTrue($payload['branches'][0]['potential_partial']);
    }

    public function test_partitioned_village_ownership_keeps_each_unit_and_branch_population_exact(): void
    {
        $rows = $this->ownershipRows();
        $rows[1] = [2, 'Desa Dua', 'Manguharjo', 'Kota Madiun', 'Kelurahan', 'Jawa Timur', 'KC Madiun', '07777'];
        $this->ownership($rows);
        $this->insert('2026-09-30', 'KC Madiun', 'PERDAGANGAN BESAR DAN ECERAN', 100);
        $this->insert('2026-09-30', 'KC Madiun', 'PERDAGANGAN BESAR DAN ECERAN', 50, '07777');
        $payload = $this->service()->payload('madiun');
        $this->assertSame(200.0, array_sum(array_column(array_column(array_column($payload['units'], 'values'), 'trade'), 'potential')));
        $this->assertSame([100.0, 100.0], array_column(array_column(array_column($payload['units'], 'values'), 'trade'), 'potential'));
        $this->assertSame(200.0, $payload['branches'][0]['values']['trade']['potential']);
        $this->assertSame(200.0, $payload['totals']['trade']['potential']);
        $this->assertSame(150.0, $payload['totals']['trade']['existing']);
        $this->assertSame(75.0, $payload['totals']['trade']['penetration']);
    }

    public function test_zero_source_population_retains_counts_but_has_no_ratio_or_normalization(): void
    {
        $reference = $this->reference();
        foreach ($reference['villages'] as &$village) {
            $village['source_values']['G'] = 0.0;
        }
        unset($village);
        $service = new class($reference) extends SectorPotentialService
        {
            public function __construct(private array $fixture) {}

            public function reference(): ?array
            {
                return $this->fixture;
            }
        };
        $this->insert('2026-09-30', 'KC Madiun', 'PERDAGANGAN BESAR DAN ECERAN', 12);
        $unit = $service->payload('madiun')['units'][0];
        $this->assertTrue($unit['potential_available']);
        $this->assertSame(0.0, $unit['values']['trade']['potential']);
        $this->assertSame(12.0, $unit['values']['trade']['existing']);
        $this->assertNull($unit['values']['trade']['penetration']);
        $this->assertNull($unit['values']['trade']['penetration_raw']);
        $this->assertFalse($unit['values']['trade']['normalization_applied']);
    }

    public function test_intra_and_ph_include_other_without_consumer_and_credit_uses_each_ph_rows_positive_balance(): void
    {
        $this->insert('2026-09-30', 'KC Madiun', 'PERDAGANGAN BESAR DAN ECERAN', 10, '09999', ['baki_debet' => '1,000.25']);
        $this->insert('2026-09-30', 'KC Madiun', '', 4, '09999', ['status_rekening' => 'PH', 'baki_debet' => '90', 'saldo_ph' => '60.25']);
        $this->insert('2026-09-30', 'KC Madiun', '', 3, '09999', ['status_rekening' => 'PH', 'baki_debet' => '40.50', 'saldo_ph' => '0']);
        $this->insert('2026-09-30', 'KC Madiun', '', 2, '09999', ['baki_debet' => '20']);
        $this->insert('2026-09-30', 'KC Madiun', '', 200, '09999', ['segmen' => 'Consumer']);
        $this->insert('2026-09-30', 'KC Madiun', '', 100, '09999', ['segmen' => 'Konsumer', 'status_rekening' => 'PH']);
        $this->insert('2026-09-30', 'KC Madiun', '', 50, '09999', ['status_rekening' => 'TUTUP']);
        $payload = $this->service()->payload('madiun');

        $value = $payload['totals']['total'];
        $this->assertSame(12.0, $value['intra']);
        $this->assertSame(7.0, $value['extra']);
        $this->assertSame(19.0, $value['existing']);
        $this->assertSame(1020.25, $value['credit_intra']);
        $this->assertSame(100.75, $value['credit_extra']);
        $this->assertSame(1121.0, $value['credit_total']);
        // SSA leaves PH (7) and 2 AKTIF without a sector: they follow the BPS business mix of the
        // UKER villages, in whole debtors, and the totals do not move.
        $this->assertSame(0.0, $payload['totals']['unclassified']['existing']);
        $this->assertSame(9.0, $payload['totals']['unclassified']['existing_source']);
        $sectors = array_diff_key($payload['totals'], array_flip(['total', 'unclassified']));
        $this->assertSame(7.0, array_sum(array_column($sectors, 'extra')));
        $this->assertSame(7.0, array_sum(array_column($sectors, 'extra_estimated')));
        $this->assertSame(19.0, array_sum(array_column($sectors, 'existing')));
        // Each sector's credit is rounded to the cent; 14 sectors may drift a few cents from the exact total row.
        $this->assertEqualsWithDelta(1121.0, array_sum(array_column($sectors, 'credit_total')), 0.1);
        foreach ($sectors as $sector) {
            $this->assertSame(floor($sector['existing']), $sector['existing']);
        }
        $this->assertSame(10.0, $payload['totals']['trade']['existing_source']);
        $this->assertSame(7.0, $payload['coverage']['extra_sector_estimated']);
        $this->assertNull($payload['totals']['unclassified']['penetration']);
        $this->assertSame(300.0, $payload['coverage']['excluded_consumer_debtors']);
        $this->assertSame(50.0, $payload['coverage']['excluded_status_debtors']);
    }

    public function test_unmapped_ssa_uker_remains_visible_with_null_potential_outside_the_owned_cohort(): void
    {
        $this->insert('2026-09-30', 'KC Madiun', 'PERDAGANGAN BESAR DAN ECERAN', 10);
        $this->insert('2026-09-30', 'KC Madiun', '', 7, '07777', ['status_rekening' => 'PH']);
        $payload = $this->service()->payload('madiun');

        $this->assertCount(1, $payload['units']);
        $this->assertSame(10.0, $payload['totals']['total']['existing']);
        $this->assertSame(7.0, $payload['coverage']['unmapped_extra']);
        $this->assertSame('07777', $payload['unmapped_units'][0]['code']);
        $this->assertSame(7.0, $payload['unmapped_units'][0]['values']['total']['extra']);
        $this->assertNull($payload['unmapped_units'][0]['values']['total']['potential']);
        $this->assertNull($payload['unmapped_units'][0]['values']['total']['penetration']);
    }

    public function test_conflicting_owners_make_unit_branch_area_and_derived_district_ratios_unavailable(): void
    {
        $rows = $this->ownershipRows();
        $rows[] = [4, 'Desa Satu', 'Kebonsari', 'Kabupaten Madiun', 'Desa', 'Jawa Timur', 'KC Madiun', '07777'];
        $this->ownership($rows);
        $this->insert('2026-09-30', 'KC Madiun', 'PERDAGANGAN BESAR DAN ECERAN', 20);
        $this->insert('2026-09-30', 'KC Madiun', 'PERDAGANGAN BESAR DAN ECERAN', 10, '07777');
        $payload = $this->service()->payload('madiun');

        $this->assertCount(1, $payload['coverage']['ownership_conflicts']);
        foreach ($payload['units'] as $unit) {
            $this->assertTrue($unit['potential_partial']);
            $this->assertFalse($unit['potential_available']);
            $this->assertNull($unit['values']['trade']['penetration']);
        }
        $this->assertSame(30.0, $payload['totals']['trade']['existing']);
        $this->assertSame(100.0, $payload['totals']['trade']['known_potential']);
        $this->assertNull($payload['totals']['trade']['potential']);
        $this->assertNull($payload['branches'][0]['values']['trade']['potential']);
        $this->assertSame(100.0, $payload['districts'][0]['values']['trade']['known_potential']);
        $this->assertTrue($payload['districts'][0]['ownership_conflict']);
        $this->assertNull($payload['districts'][0]['values']['trade']['potential']);
        $this->assertNull($payload['districts'][0]['values']['trade']['penetration']);
    }

    public function test_cross_branch_ownership_uses_declared_owner_and_does_not_create_false_unowned_villages(): void
    {
        $rows = $this->ownershipRows();
        $rows[0][6] = 'KC Ngawi';
        $rows[0][7] = '08888';
        $this->ownership($rows);
        $this->insert('2026-09-30', 'KC Madiun', 'PERDAGANGAN BESAR DAN ECERAN', 10);
        $this->insert('2026-09-30', 'KC Ngawi', 'PERDAGANGAN BESAR DAN ECERAN', 7, '08888');
        $service = $this->service();
        $madiun = $service->payload('madiun');
        $ngawi = $service->payload('ngawi');

        $this->assertSame(100.0, $madiun['totals']['trade']['potential']);
        $this->assertSame(0, $madiun['coverage']['unowned_source_village_count']);
        $this->assertSame(200.0, $ngawi['totals']['trade']['potential']);
        $this->assertSame(7.0, $ngawi['totals']['trade']['existing']);
        $this->assertCount(2, $ngawi['units'][0]['village_keys']);
        $this->assertSame(['ngawi'], array_values(array_unique(array_column($ngawi['villages'], 'branch'))));
    }

    public function test_a_legitimate_zero_numerator_does_not_look_like_normalized_data(): void
    {
        $this->insert('2026-09-30', 'KC Madiun', 'PERDAGANGAN BESAR DAN ECERAN', 0);
        $value = $this->service()->payload('madiun')['units'][0]['values']['trade'];
        $this->assertSame(200.0, $value['potential']);
        $this->assertEquals(0, $value['penetration_raw']);
        $this->assertEquals(0, $value['penetration']);
        $this->assertFalse($value['normalization_applied']);
        $this->assertEquals(0, $value['penetration_excess']);
    }

    public function test_user_branch_scope_overrides_requested_area_or_other_branch(): void
    {
        $this->insert('2026-09-30', 'KC Madiun', 'PERDAGANGAN BESAR DAN ECERAN', 100);
        $this->insert('2026-09-30', 'KC Ngawi', 'PERDAGANGAN BESAR DAN ECERAN', 7, '08888');
        $payload = UserBranchScope::runAs(UserBranchScope::forKey('ngawi'), fn () => $this->service()->payload('madiun'));

        $this->assertSame(['ngawi'], array_column($payload['branches'], 'key'));
        $this->assertSame(['ngawi'], array_values(array_unique(array_column($payload['units'], 'branch'))));
        $this->assertSame(['ngawi'], array_values(array_unique(array_column($payload['districts'], 'branch'))));
        $this->assertSame(7.0, $payload['totals']['trade']['existing']);
        $this->assertSame(100.0, $payload['totals']['trade']['potential']);
    }

    public function test_latest_available_period_is_used_without_september(): void
    {
        $this->insert('2026-08-31', 'KC Madiun', 'PERDAGANGAN BESAR DAN ECERAN', 900);
        $payload = $this->service()->payload();
        $this->assertTrue($payload['ready']);
        $this->assertSame('2026-08-31', $payload['period']);
        $this->assertSame(900.0, $payload['coverage']['ssa_debtors']);
    }

    public function test_new_periods_replace_warm_payloads_without_waiting_for_cache_expiry(): void
    {
        $service = $this->service();
        $this->assertNull($service->payload('madiun')['period']);
        $this->insert('2026-09-30', 'KC Madiun', 'PERDAGANGAN BESAR DAN ECERAN', 100);
        $this->assertSame('2026-09-30', $service->payload('madiun')['period']);

        $this->insert('2026-10-31', 'KC Madiun', 'PERDAGANGAN BESAR DAN ECERAN', 150);
        $october = $service->payload('madiun');
        $this->assertSame('2026-10-31', $october['period']);
        $this->assertSame('31 Oktober 2026', $october['period_label']);
        $this->assertSame(150.0, $october['coverage']['ssa_debtors']);
        $this->assertSame(150.0, $october['totals']['total']['existing']);

        $this->insert('2027-01-31', 'KC Madiun', 'PERDAGANGAN BESAR DAN ECERAN', 180);
        $latest = $service->payload('madiun');
        $this->assertSame('2027-01-31', $latest['period']);
        $this->assertSame(180.0, $latest['coverage']['ssa_debtors']);

        DB::table('cras')->where('cras_periode', '2027-01-31')->delete();
        $this->assertSame('2026-10-31', $service->payload('madiun')['period']);
    }

    public function test_newer_data_in_another_branch_does_not_override_authorized_period(): void
    {
        $this->insert('2026-09-30', 'KC Madiun', 'PERDAGANGAN BESAR DAN ECERAN', 100);
        $this->insert('2026-10-31', 'KC Ngawi', 'PERDAGANGAN BESAR DAN ECERAN', 200, '08888');
        $payload = UserBranchScope::runAs(UserBranchScope::forKey('madiun'), fn () => $this->service()->payload('ngawi'));
        $this->assertSame('2026-09-30', $payload['period']);
        $this->assertSame(100.0, $payload['coverage']['ssa_debtors']);
        $this->assertSame(['madiun'], array_column($payload['branches'], 'key'));
    }

    public function test_empty_source_does_not_invent_a_period(): void
    {
        $payload = $this->service()->payload('madiun');
        $this->assertFalse($payload['ready']);
        $this->assertNull($payload['period']);
        $this->assertSame('SSA CRAS belum tersedia.', $payload['message']);
    }

    public function test_latest_period_is_resolved_within_authorized_branch(): void
    {
        $this->insert('2026-09-29', 'KC Madiun', 'PERDAGANGAN BESAR DAN ECERAN', 10);
        $this->insert('2026-09-30', 'KC Ngawi', 'PERDAGANGAN BESAR DAN ECERAN', 20, '08888');
        $service = $this->service();
        $madiun = $service->payload('madiun');
        $this->assertSame('2026-09-29', $madiun['period']);
        $this->assertSame(10.0, $madiun['totals']['trade']['existing']);

        $area = $service->payload();
        $missing = collect($area['branches'])->firstWhere('key', 'madiun');
        $this->assertSame('2026-09-30', $area['period']);
        $this->assertFalse($missing['numerator_available']);
        $this->assertNull($missing['values']['trade']['existing']);
        $this->assertNull($missing['values']['trade']['penetration']);
        $this->assertNull($area['totals']['trade']['existing']);
        $this->assertNull($area['totals']['trade']['penetration']);
        $this->assertFalse($area['coverage']['numerator_complete']);
        $this->assertSame('madiun', $area['coverage']['unresolved_ownership_units'][0]['branch']);
    }

    public function test_snapshot_from_a_different_sheet_is_never_accepted_as_current_source(): void
    {
        $path = storage_path('framework/testing/sector-potential-wrong-source-'.bin2hex(random_bytes(4)).'.json');
        $reference = $this->reference();
        $reference['source']['spreadsheet_id'] = 'previous-source';
        file_put_contents($path, json_encode($reference));
        Http::fake(['*' => Http::response('', 503)]);
        $service = new class($path) extends SectorPotentialService
        {
            public function __construct(private string $path) {}

            public function snapshotPath(): string
            {
                return $this->path;
            }
        };
        try {
            $this->assertNull($service->reference());
            Http::assertSent(fn ($request) => str_contains($request->url(), SectorPotentialService::SPREADSHEET_ID));
        } finally {
            unlink($path);
        }
    }

    public function test_downloaded_decimal_counts_survive_disk_roundtrip_and_offline_fallback(): void
    {
        $path = storage_path('framework/testing/sector-potential-roundtrip-'.bin2hex(random_bytes(4)).'.json');
        $service = new class($path) extends SectorPotentialService
        {
            public function __construct(private string $path) {}

            public function snapshotPath(): string
            {
                return $this->path;
            }
        };
        Http::fakeSequence()->push($this->csv(), 200)->push('', 503);
        try {
            $downloaded = $service->reference();
            $this->assertSame(0.0, $downloaded['districts'][0]['source_values']['A']);
            $this->assertFileExists($path);
            Cache::flush();
            touch($path, now()->subMinutes(40)->timestamp);
            clearstatcache(true, $path);
            $fallback = $service->reference();
            $this->assertNotNull($fallback);
            $this->assertSame('last_verified_snapshot', $fallback['source']['read_state']);
            $this->assertSame($downloaded['source']['data_sha256'], $fallback['source']['data_sha256']);
            $this->assertSame(1234.0, $fallback['districts'][0]['source_values']['G']);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_sectorless_and_catch_all_debtors_follow_the_bps_business_mix_in_whole_debtors(): void
    {
        $this->insert('2026-09-30', 'KC Madiun', 'PERDAGANGAN BESAR DAN ECERAN', 3);
        $this->insert('2026-09-30', 'KC Madiun', 'INDUSTRI PENGOLAHAN', 1);
        $this->insert('2026-09-30', 'KC Madiun', '', 5, '09999', ['status_rekening' => 'PH']);
        // A catch-all purpose code: KUR micro loans, not real R-U businesses.
        $this->insert('2026-09-30', 'KC Madiun', 'JASA KEMASYARAKATAN SOSIAL BUDAYA HIBURAN DAN PERORANGAN LAINNYA', 34, '09999', ['sub_sektor_ekonomi' => 'Jasa Kegiatan Lainnya']);
        $this->insert('2026-09-30', 'KC Ngawi', '', 4, '08888', ['status_rekening' => 'PH']);
        $payload = $this->service()->payload();
        $units = collect($payload['units'])->keyBy('code');
        $unit = $units['09999']['values'];

        // 39 pooled debtors over 3,400 businesses (17 categories x 200): R-U (200) receives 39 x 200/3,400 = 2.3 -> 2.
        $this->assertSame(34.0, $unit['other_services']['existing_source']);
        $this->assertSame(2.0, $unit['other_services']['existing']);
        $this->assertSame(1.0, $unit['other_services']['penetration']);
        $this->assertSame(17.0, $unit['other_services']['penetration_raw']);
        $this->assertSame(43.0, $unit['total']['existing']);
        $sectors = array_diff_key($unit, array_flip(['total', 'unclassified']));
        $this->assertSame(43.0, array_sum(array_column($sectors, 'existing')));
        $this->assertSame(5.0, array_sum(array_column($sectors, 'extra')));
        $this->assertSame(43.0, $payload['coverage']['reallocated_debtors']); // 39 Madiun + 4 Ngawi
        $this->assertSame(34.0, $payload['coverage']['catch_all_debtors']);
        // Ngawi has no AKTIF sector at all: its PH still follows the BPS mix of its own villages.
        $this->assertSame(0.0, $units['08888']['values']['unclassified']['existing']);
        $this->assertSame(9.0, $payload['totals']['total']['extra']);
    }

    public function test_cras_rows_use_the_same_sector_normalization_as_the_penetration_chapter(): void
    {
        $service = new SectorPotentialService;
        $unit = ['normalization' => ['keep_ratio' => ['trade' => 0.8, 'agriculture' => 1.0], 'pool_share' => ['trade' => 0.0, 'agriculture' => 0.25]]];
        $row = fn (string $sector, string $sub, float $count) => ['sektor_ekonomi' => $sector, 'sub_sektor_ekonomi' => $sub, 'existing' => $count];

        // Coded to the sector: only the kept fraction counts.
        $this->assertSame(80.0, $service->normalizedRowDebtors($row('PERDAGANGAN BESAR DAN ECERAN', 'Perdagangan Eceran', 100), $unit, 'trade'));
        // A catch-all purpose code follows the redistribution share, even under its own coded sector.
        $this->assertSame(0.0, $service->normalizedRowDebtors($row('PERDAGANGAN BESAR DAN ECERAN', 'Perdagangan Ekspor Hasil Tanaman', 100), $unit, 'trade'));
        $this->assertSame(25.0, $service->normalizedRowDebtors($row('PERDAGANGAN BESAR DAN ECERAN', 'Perdagangan Ekspor Hasil Tanaman', 100), $unit, 'agriculture'));
        // Trade overflow (20%) lands in agriculture by the same share; non-business credit never does.
        $this->assertEqualsWithDelta(5.0, $service->normalizedRowDebtors($row('PERDAGANGAN BESAR DAN ECERAN', 'Perdagangan Eceran', 100), $unit, 'agriculture'), 1e-9);
        $this->assertSame(0.0, $service->normalizedRowDebtors($row('PENERIMA KREDIT BUKAN LAPANGAN USAHA', '', 100), $unit, 'agriculture'));
        // The all-sector total is never altered.
        $this->assertSame(100.0, $service->normalizedRowDebtors($row('PERDAGANGAN BESAR DAN ECERAN', 'Perdagangan Eceran', 100), $unit, 'total'));
    }

    public function test_map_districts_receive_unit_debtors_by_owned_population_of_the_same_group(): void
    {
        $reference = $this->reference();
        $reference['villages'][1]['source_values']['G'] = 300.0;
        $reference['source']['data_sha256'] = $this->hashOf($reference);
        $this->insert('2026-09-30', 'KC Madiun', 'PERDAGANGAN BESAR DAN ECERAN', 8);
        $payload = $this->serviceWith($reference)->payload('madiun');
        $districts = collect($payload['districts'])->keyBy('name');

        // Kebonsari holds 100 of the unit's trade population, Manguharjo 300: 2 and 6, not 4 and 4.
        $this->assertSame(2.0, $districts['Kebonsari']['values']['trade']['existing']);
        $this->assertSame(6.0, $districts['Manguharjo']['values']['trade']['existing']);
        $this->assertSame('ssa_uker_potential_weighted_owned_district_allocation', $districts['Kebonsari']['numerator_grain']);
    }

    public function test_processed_workbook_debtor_columns_are_parsed_per_village_and_category(): void
    {
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, ['Desa/Kelurahan', 'Kecamatan', 'Kabupaten/Kota', 'Keterangan', 'Deskripsi', 'Jumlah', 'Debitur Intra', 'Debitur Ekstra'], ',', '"', '');
        foreach (self::CATEGORIES as $key) {
            fputcsv($stream, ['Desa Satu', 'Kebonsari', 'Kabupaten Madiun', $key, $key, $key === 'G' ? '1,234' : '0', $key === 'G' ? '1,000' : '', $key === 'G' ? '20' : '0'], ',', '"', '');
        }
        rewind($stream);
        $payload = (new SectorPotentialService)->referenceFromCsv(stream_get_contents($stream));

        $this->assertTrue($payload['source']['debtor_columns']);
        $this->assertSame(1000.0, $payload['villages'][0]['source_debtors']['intra']['G']);
        $this->assertSame(20.0, $payload['villages'][0]['source_debtors']['extra']['G']);
        $this->assertSame(0.0, $payload['villages'][0]['source_debtors']['intra']['A']);
        $this->assertFalse((new SectorPotentialService)->referenceFromCsv($this->csv())['source']['debtor_columns']);
    }

    public function test_one_debtor_column_without_its_pair_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('bersamaan');
        (new SectorPotentialService)->referenceFromCsv("Desa/Kelurahan,Kecamatan,Kabupaten/Kota,Keterangan,Deskripsi,Jumlah,Debitur Intra\nDesa,Kebonsari,Kabupaten Madiun,A,A,1,1\n");
    }

    public function test_workbook_debtors_replace_ssa_counts_inside_the_ratio_and_stay_exact_on_the_map(): void
    {
        $reference = $this->reference();
        $reference['source']['debtor_columns'] = true;
        foreach ($reference['villages'] as $index => &$village) {
            $village['source_debtors'] = ['intra' => array_fill_keys(self::CATEGORIES, 0.0), 'extra' => array_fill_keys(self::CATEGORIES, 0.0)];
            $village['source_debtors']['intra']['G'] = [30.0, 10.0, 5.0][$index];
            $village['source_debtors']['extra']['G'] = [6.0, 4.0, 1.0][$index];
        }
        unset($village);
        $reference['source']['data_sha256'] = $this->hashOf($reference);
        $this->insert('2026-09-30', 'KC Madiun', 'PERDAGANGAN BESAR DAN ECERAN', 999);
        $payload = $this->serviceWith($reference)->payload('madiun');
        $unit = collect($payload['units'])->firstWhere('code', '09999');
        $districts = collect($payload['districts'])->keyBy('name');

        $this->assertSame('sheet_desa', $payload['coverage']['numerator_source']);
        $this->assertSame(40.0, $unit['values']['trade']['intra']);
        $this->assertSame(10.0, $unit['values']['trade']['extra']);
        $this->assertSame(25.0, round($unit['values']['trade']['penetration_raw'], 6));
        $this->assertSame(36.0, $districts['Kebonsari']['values']['trade']['existing']);
        $this->assertSame(14.0, $districts['Manguharjo']['values']['trade']['existing']);
        $this->assertFalse($districts['Kebonsari']['allocation_estimate']);
        $this->assertSame(0, $payload['coverage']['extra_sector_estimated']);
    }

    private function serviceWith(array $reference): SectorPotentialService
    {
        return new class($reference) extends SectorPotentialService
        {
            public function __construct(private array $fixture) {}

            public function reference(): ?array
            {
                return $this->fixture;
            }
        };
    }

    private function hashOf(array $reference): string
    {
        return hash('sha256', json_encode([$reference['source_sectors'], $reference['districts'], $reference['villages']], JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));
    }

    private function service(): SectorPotentialService
    {
        return new class($this->reference()) extends SectorPotentialService
        {
            public function __construct(private array $fixture) {}

            public function reference(): ?array
            {
                return $this->fixture;
            }
        };
    }

    private function reference(): array
    {
        $reference = ['schema_version' => 2, 'source' => ['spreadsheet_id' => SectorPotentialService::SPREADSHEET_ID, 'sheet_id' => SectorPotentialService::SHEET_ID, 'sha256' => str_repeat('a', 64)],
            'source_sectors' => array_map(fn ($key) => ['key' => $key, 'label' => $key], self::CATEGORIES),
            'districts' => [
                ['regency' => 'Kabupaten Madiun', 'name' => 'Kebonsari', 'branch' => 'madiun', 'source_values' => array_fill_keys(self::CATEGORIES, 100.0)],
                ['regency' => 'Kota Madiun', 'name' => 'Manguharjo', 'branch' => 'madiun', 'source_values' => array_fill_keys(self::CATEGORIES, 100.0)],
                ['regency' => 'Kabupaten Ngawi', 'name' => 'Sine', 'branch' => 'ngawi', 'source_values' => array_fill_keys(self::CATEGORIES, 100.0)],
            ], 'villages' => [
                ['key' => VillageOwnershipReferenceService::villageKey('Kabupaten Madiun', 'Kebonsari', 'Desa Satu'), 'regency' => 'Kabupaten Madiun', 'district' => 'Kebonsari', 'name' => 'Desa Satu', 'physical_branch' => 'madiun', 'source_values' => array_fill_keys(self::CATEGORIES, 100.0)],
                ['key' => VillageOwnershipReferenceService::villageKey('Kota Madiun', 'Manguharjo', 'Desa Dua'), 'regency' => 'Kota Madiun', 'district' => 'Manguharjo', 'name' => 'Desa Dua', 'physical_branch' => 'madiun', 'source_values' => array_fill_keys(self::CATEGORIES, 100.0)],
                ['key' => VillageOwnershipReferenceService::villageKey('Kabupaten Ngawi', 'Sine', 'Desa Tiga'), 'regency' => 'Kabupaten Ngawi', 'district' => 'Sine', 'name' => 'Desa Tiga', 'physical_branch' => 'ngawi', 'source_values' => array_fill_keys(self::CATEGORIES, 100.0)],
            ]];
        $reference['source']['data_sha256'] = hash('sha256', json_encode([$reference['source_sectors'], $reference['districts'], $reference['villages']], JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));

        return $reference;
    }

    private function csv(): string
    {
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, ['Desa/Kelurahan', 'Kecamatan', 'Kabupaten/Kota', 'Keterangan', 'Deskripsi', 'Jumlah'], ',', '"', '');
        foreach (self::CATEGORIES as $key) {
            fputcsv($stream, ['Desa Satu', 'Kebonsari', 'Kabupaten Madiun', $key, $key, $key === 'G' ? '1,234' : '0'], ',', '"', '');
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv;
    }

    private function insert(string $period, string $branch, string $sector, int $debtors, string $unit = '09999', array $extra = []): void
    {
        DB::table('cras')->insert([...['cras_periode' => $period, 'ket_kanca' => $branch, 'br_number' => $unit, 'ket_unit_kerja' => $unit, 'sektor_ekonomi' => $sector, 'jumlah_debitur' => (string) $debtors, 'status_rekening' => 'AKTIF', 'segmen' => 'MIKRO', 'jumlah_rekening' => $debtors, 'baki_debet' => '0', 'saldo_ph' => '0'], ...$extra]);
    }

    private function ownershipRows(): array
    {
        return [
            [1, 'Desa Satu', 'Kebonsari', 'Kabupaten Madiun', 'Desa', 'Jawa Timur', 'KC Madiun', '09999'],
            [2, 'Desa Dua', 'Manguharjo', 'Kota Madiun', 'Kelurahan', 'Jawa Timur', 'KC Madiun', '09999'],
            [3, 'Desa Tiga', 'Sine', 'Kabupaten Ngawi', 'Desa', 'Jawa Timur', 'KC Ngawi', '08888'],
        ];
    }

    private function ownership(array $rows): void
    {
        $reference = (new VillageOwnershipReferenceService)->referenceFromRows($rows);
        $this->app->instance(VillageOwnershipReferenceService::class, new class($reference) extends VillageOwnershipReferenceService
        {
            public function __construct(private array $fixture) {}

            public function reference(): ?array
            {
                return $this->fixture;
            }
        });
    }
}
