<?php

namespace Tests\Unit;

use App\Support\VillageOwnershipReferenceService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class VillageOwnershipReferenceServiceTest extends TestCase
{
    private const HEADERS = ['No.', 'Desa/Kelurahan', 'Kecamatan', 'Kabupaten/Kota', 'Jenis Wilayah', 'Provinsi', 'Nama Cabang', 'Nama Unit'];

    private array $temporaryPaths = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
        Cache::flush();
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryPaths as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    public function test_eight_geographic_columns_with_a_bom_are_read_without_staff_columns(): void
    {
        $reference = (new VillageOwnershipReferenceService)->referenceFromCsv("\xEF\xBB\xBF".$this->csv([$this->row()]));

        $this->assertSame(VillageOwnershipReferenceService::SPREADSHEET_ID, $reference['source']['spreadsheet_id']);
        $this->assertSame(VillageOwnershipReferenceService::SHEET_ID, $reference['source']['sheet_id']);
        $this->assertSame(1, $reference['source']['row_count']);
        $this->assertCount(1, $reference['villages']);
        $this->assertSame('KOTA MADIUN|TAMAN|DEMANGAN', $reference['villages'][0]['key']);
        $this->assertSame('madiun', $reference['villages'][0]['owners'][0]['branch']);
        $this->assertSame('UNIT SLEKO MADIUN', $reference['villages'][0]['owners'][0]['unit_name']);
    }

    public function test_same_owner_staff_duplicates_are_deduplicated_and_staff_columns_never_enter_the_reference_or_hashes(): void
    {
        $first = [...$this->row(), 'PRIVATE_PN_FIRST', 'PRIVATE_STAFF_FIRST'];
        $second = [...$this->row(), 'PRIVATE_PN_SECOND', 'PRIVATE_STAFF_SECOND'];
        $service = new VillageOwnershipReferenceService;
        $reference = $service->referenceFromCsv($this->csv([$first, $second], true));
        $changedStaff = $service->referenceFromCsv($this->csv([
            [...$this->row(), 'REPLACED_PN_FIRST', 'REPLACED_STAFF_FIRST'],
            [...$this->row(), 'REPLACED_PN_SECOND', 'REPLACED_STAFF_SECOND'],
        ], true));

        $this->assertSame(2, $reference['source']['row_count']);
        $this->assertSame(1, $reference['source']['unique_village_count']);
        $this->assertSame(1, $reference['source']['duplicate_row_count']);
        $this->assertSame(0, $reference['source']['conflict_count']);
        $this->assertCount(1, $reference['villages'][0]['owners']);
        $this->assertSame([2, 3], $reference['villages'][0]['owners'][0]['source_rows']);
        $this->assertFalse($reference['villages'][0]['conflicting_ownership']);
        $this->assertSame($reference['source']['sha256'], $changedStaff['source']['sha256']);
        $this->assertSame($reference['source']['data_sha256'], $changedStaff['source']['data_sha256']);
        $encoded = json_encode($reference, JSON_THROW_ON_ERROR);
        foreach (['PRIVATE_PN_FIRST', 'PRIVATE_PN_SECOND', 'PRIVATE_STAFF_FIRST', 'PRIVATE_STAFF_SECOND', 'PN Mantri', 'Nama Mantri'] as $privateValue) {
            $this->assertStringNotContainsString($privateValue, $encoded);
        }
    }

    public function test_homonymous_villages_are_qualified_by_both_district_and_regency(): void
    {
        $reference = (new VillageOwnershipReferenceService)->referenceFromRows([
            $this->row('Sumberrejo', 'Taman', 'Kota Madiun'),
            $this->row('Sumberrejo', 'Taman', 'Kabupaten Madiun'),
            $this->row('Sumberrejo', 'Wungu', 'Kabupaten Madiun'),
        ]);

        $this->assertSame(3, $reference['source']['unique_village_count']);
        $this->assertSame(0, $reference['source']['duplicate_row_count']);
        $this->assertSame(0, $reference['source']['conflict_count']);
        $this->assertEqualsCanonicalizing([
            'KOTA MADIUN|TAMAN|SUMBERREJO',
            'KABUPATEN MADIUN|TAMAN|SUMBERREJO',
            'KABUPATEN MADIUN|WUNGU|SUMBERREJO',
        ], array_column($reference['villages'], 'key'));
    }

    public function test_conflicting_units_and_branches_are_retained_and_flagged_without_choosing_an_owner(): void
    {
        $reference = (new VillageOwnershipReferenceService)->referenceFromRows([
            $this->row(),
            $this->row(unit: 'UNIT DIPONEGORO MADIUN'),
            $this->row(branch: 'KC Magetan', unit: 'UNIT MAGETAN MAGETAN'),
        ]);

        $this->assertCount(1, $reference['villages']);
        $this->assertSame(1, $reference['source']['conflict_count']);
        $this->assertSame(0, $reference['source']['duplicate_row_count']);
        $this->assertSame(['KOTA MADIUN|TAMAN|DEMANGAN'], $reference['conflicts']);
        $this->assertTrue($reference['villages'][0]['conflicting_ownership']);
        $this->assertCount(3, $reference['villages'][0]['owners']);
        $this->assertEqualsCanonicalizing(['madiun', 'madiun', 'magetan'], array_column($reference['villages'][0]['owners'], 'branch'));
        $this->assertEqualsCanonicalizing(['UNIT SLEKO MADIUN', 'UNIT DIPONEGORO MADIUN', 'UNIT MAGETAN MAGETAN'], array_column($reference['villages'][0]['owners'], 'unit_name'));
    }

    public function test_reordered_geographic_headers_are_rejected(): void
    {
        $csv = str_replace('Desa/Kelurahan,Kecamatan', 'Kecamatan,Desa/Kelurahan', $this->csv([$this->row()]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Header mapping kepemilikan desa tidak sesuai.');
        (new VillageOwnershipReferenceService)->referenceFromCsv($csv);
    }

    public function test_downloaded_projected_snapshot_survives_json_roundtrip_and_offline_fallback_without_staff_data(): void
    {
        [$service, $path] = $this->isolatedService();
        Http::fakeSequence()
            ->push($this->csv([[...$this->row(), 'PRIVATE_PN_DOWNLOAD', 'PRIVATE_STAFF_DOWNLOAD']], true), 200)
            ->push('', 503);

        $downloaded = $service->reference();

        $this->assertNotNull($downloaded);
        $this->assertFileExists($path);
        $disk = (string) file_get_contents($path);
        $this->assertStringNotContainsString('PRIVATE_PN_DOWNLOAD', $disk);
        $this->assertStringNotContainsString('PRIVATE_STAFF_DOWNLOAD', $disk);
        $this->assertStringNotContainsString('PN Mantri', $disk);
        $this->assertStringNotContainsString('Nama Mantri', $disk);
        $this->assertSame($downloaded, json_decode($disk, true, 512, JSON_THROW_ON_ERROR));
        Http::assertSent(function ($request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return str_contains($request->url(), VillageOwnershipReferenceService::SPREADSHEET_ID.'/gviz/tq')
                && ($query['gid'] ?? null) === (string) VillageOwnershipReferenceService::SHEET_ID
                && ($query['range'] ?? null) === 'A:H';
        });

        Cache::flush();
        touch($path, now()->subMinutes(40)->timestamp);
        clearstatcache(true, $path);
        $snapshotHash = hash_file('sha256', $path);
        $fallback = $service->reference();

        $this->assertNotNull($fallback);
        $this->assertSame('last_verified_snapshot', $fallback['source']['read_state']);
        $this->assertSame($downloaded['source']['data_sha256'], $fallback['source']['data_sha256']);
        $this->assertSame($downloaded['villages'], $fallback['villages']);
        $this->assertSame($snapshotHash, hash_file('sha256', $path));
        Http::assertSentCount(2);
    }

    public function test_fresh_verified_snapshot_is_loaded_without_a_network_request(): void
    {
        [$service, $path] = $this->isolatedService();
        $reference = $service->referenceFromRows([$this->row()]);
        $this->writeSnapshot($path, $reference);
        Http::fake(['*' => Http::response('', 503)]);

        $this->assertSame($reference, $service->reference());
        Http::assertNothingSent();
    }

    public function test_offline_snapshot_from_another_spreadsheet_is_rejected_even_when_its_data_hash_is_valid(): void
    {
        $this->assertUntrustedSnapshotRejected(function (array &$reference): void {
            $reference['source']['spreadsheet_id'] = 'previous-unrelated-spreadsheet';
        });
    }

    public function test_offline_snapshot_from_another_tab_is_rejected_even_when_its_data_hash_is_valid(): void
    {
        $this->assertUntrustedSnapshotRejected(function (array &$reference): void {
            $reference['source']['sheet_id'] = 123;
        });
    }

    public function test_offline_snapshot_with_modified_village_data_and_an_old_checksum_is_rejected(): void
    {
        $this->assertUntrustedSnapshotRejected(function (array &$reference): void {
            $reference['villages'][0]['owners'][0]['unit_name'] = 'UNVERIFIED UNIT';
        });
    }

    private function assertUntrustedSnapshotRejected(callable $modify): void
    {
        [$service, $path] = $this->isolatedService();
        $reference = $service->referenceFromRows([$this->row()]);
        $modify($reference);
        $this->writeSnapshot($path, $reference);
        Http::fake(['*' => Http::response('', 503)]);

        $this->assertNull($service->reference());
        Http::assertSentCount(1);
    }

    private function isolatedService(): array
    {
        $path = storage_path('framework/testing/village-ownership-reader-'.bin2hex(random_bytes(8)).'.json');
        $this->temporaryPaths[] = $path;
        $service = new class($path) extends VillageOwnershipReferenceService
        {
            public function __construct(private string $path) {}

            public function snapshotPath(): string
            {
                return $this->path;
            }
        };

        return [$service, $path];
    }

    private function writeSnapshot(string $path, array $reference): void
    {
        file_put_contents($path, json_encode($reference, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        touch($path, now()->timestamp);
        clearstatcache(true, $path);
    }

    private function row(string $village = 'Demangan', string $district = 'Taman', string $regency = 'Kota Madiun', string $branch = 'KC Madiun', string $unit = 'UNIT SLEKO MADIUN'): array
    {
        return ['1', $village, $district, $regency, 'Kelurahan', 'Jawa Timur', $branch, $unit];
    }

    private function csv(array $rows, bool $withStaff = false): string
    {
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, $withStaff ? [...self::HEADERS, 'PN Mantri', 'Nama Mantri'] : self::HEADERS, ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($stream, $row, ',', '"', '');
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv;
    }
}
