<?php

namespace Tests\Unit;

use App\Support\KeragaanPdfGeography;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class KeragaanPdfGeographyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('referensi_uker', function (Blueprint $table): void {
            $table->string('kode_uker');
            $table->string('nama_uker');
            $table->string('kode_cabang');
        });
        DB::table('referensi_uker')->insert([
            ['kode_uker' => '00045', 'nama_uker' => 'KC Madiun', 'kode_cabang' => '00045'],
            ['kode_uker' => '00552', 'nama_uker' => 'KCP Caruban', 'kode_cabang' => '00045'],
            ['kode_uker' => '02109', 'nama_uker' => 'UNIT Dagangan Madiun', 'kode_cabang' => '00045'],
            ['kode_uker' => '09999', 'nama_uker' => 'UNIT Tidak Terpetakan', 'kode_cabang' => '00045'],
        ]);
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        DB::purge('sqlite');
        parent::tearDown();
    }

    public function test_single_branch_has_muted_varied_polygons_and_direct_active_office_labels(): void
    {
        $map = (new KeragaanPdfGeography)->build(['KC Madiun'], [
            $this->office('kc-madiun-detail', 'KC Madiun'),
            $this->office('unit-dagangan-madiun', 'UNIT Dagangan Madiun'),
        ]);
        $this->assertTrue($map['ready']);
        $this->assertCount(1, $map['maps']);
        $this->assertSame(2, $map['unit_count']);
        $this->assertSame(2, $map['mapped_unit_count']);
        $this->assertSame([], $map['unmapped_units']);
        $this->assertStringContainsString('KC Madiun</text>', $map['svg']);
        $this->assertStringContainsString('UNIT Dagangan Madiun</text>', $map['svg']);
        $this->assertStringNotContainsString('KCP Caruban', $map['svg']);
        $this->assertStringContainsString('viewBox="0 0 1000 1050"', $map['svg']);
        preg_match_all('/<path[^>]+fill="(#[a-f0-9]+)"/', $map['svg'], $fills);
        $this->assertGreaterThanOrEqual(6, count(array_unique($fills[1])));
        $this->assertSame('', $map['disclosure']);
        $this->assertStringNotContainsString('>BRI</text>', $map['svg']);
        $this->assertStringNotContainsString('stroke="#688087"', $map['svg']);
        $this->assertStringContainsString('class="district-label"', $map['svg']);
        $this->assertStringContainsString('Kartoharjo</text>', $map['svg']);
        $xml = simplexml_load_string($map['svg']);
        $xml->registerXPathNamespace('s', 'http://www.w3.org/2000/svg');
        $this->assertSame('700', (string) $xml->xpath('//s:rect[@data-map-pane]')[0]['width']);
        $this->assertSame('300', (string) $xml->xpath('//s:rect[@data-list-pane]')[0]['width']);
        $this->assertCount(2, $xml->xpath('//s:g[@class="office-list-entry"]'));
    }

    public function test_unknown_active_offices_remain_explicitly_unmapped_and_other_branch_is_excluded(): void
    {
        $map = (new KeragaanPdfGeography)->build(['KC Madiun'], [
            $this->office('unit-tidak-terpetakan', 'UNIT Tidak Terpetakan'),
            $this->office('unit-baru', 'UNIT Baru'),
            ['unit_key' => 'unit-ngawi', 'unit_label' => 'UNIT Ngawi', 'kanca_key' => 'kc-ngawi', 'kanca_label' => 'KC Ngawi'],
        ]);
        $this->assertSame(2, $map['unit_count']);
        $this->assertSame(0, $map['mapped_unit_count']);
        $this->assertSame(['UNIT Tidak Terpetakan', 'UNIT Baru'], array_column($map['unmapped_units'], 'name'));
        $this->assertStringNotContainsString('UNIT Ngawi', $map['svg']);
    }

    public function test_numeric_office_identity_and_empty_active_selection_are_supported(): void
    {
        $map = (new KeragaanPdfGeography)->build(['KC Madiun'], [$this->office('552', '552 - KCP Caruban')]);
        $this->assertSame(1, $map['mapped_unit_count']);
        $this->assertStringContainsString('KCP Caruban</text>', $map['svg']);
        $empty = (new KeragaanPdfGeography)->build(['KC Madiun'], []);
        $this->assertSame(0, $empty['unit_count']);
        $this->assertStringNotContainsString('KC Madiun</text>', $empty['svg']);
    }

    public function test_shared_districts_reuse_office_numbers_without_duplicate_list_entries_or_overlapping_markers(): void
    {
        config(['marketshare-geography.unit_districts.00045' => ['35.77.01', '35.77.02'],
            'marketshare-geography.unit_districts.00552' => ['35.77.01']]);
        $map = (new KeragaanPdfGeography)->build(['KC Madiun'], [
            $this->office('00045', 'KC Madiun'), $this->office('00552', 'KCP Caruban'),
        ]);
        $xml = simplexml_load_string($map['svg']);
        $xml->registerXPathNamespace('s', 'http://www.w3.org/2000/svg');
        $entries = $xml->xpath('//s:g[@class="office-list-entry"]');
        $markers = $xml->xpath('//s:g[@class="office-marker"]');
        $this->assertCount(2, $entries);
        $this->assertCount(3, $markers);
        $numbers = [];
        foreach ($entries as $entry) {
            $numbers[(string) $entry['data-office-code']] = (string) $entry['data-office-number'];
        }
        $placed = [];
        foreach ($markers as $marker) {
            $this->assertSame($numbers[(string) $marker['data-office-code']], (string) $marker['data-office-number']);
            preg_match('/translate\(([-\d.]+) ([-\d.]+)\)/', (string) $marker['transform'], $position);
            [$x, $y] = [(float) $position[1], (float) $position[2]];
            $this->assertLessThanOrEqual(700, $x + 36);
            foreach ($placed as [$otherX, $otherY]) {
                $this->assertTrue(abs($x - $otherX) >= 32 || abs($y - $otherY) >= 32);
            }
            $placed[] = [$x, $y];
        }
    }

    private function office(string $key, string $label): array
    {
        return ['unit_key' => $key, 'unit_label' => $label, 'kanca_key' => 'kc-madiun', 'kanca_label' => 'KC Madiun'];
    }
}
