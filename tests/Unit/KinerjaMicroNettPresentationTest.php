<?php

namespace Tests\Unit;

use Tests\TestCase;

class KinerjaMicroNettPresentationTest extends TestCase
{
    public function test_rm_bo_sums_people_and_weights_percentages_without_counting_unassigned_amount(): void
    {
        $roster = [
            ['pn' => '1', 'name' => '<script>RM</script>', 'branch' => 'KC MADIUN', 'unit' => 'KC MADIUN'],
            ['pn' => '2', 'name' => 'RM KCP', 'branch' => 'KC MADIUN', 'unit' => 'KCP DOLOPO', 'office_type' => 'kcp'],
        ];
        $payload = (new \App\Support\MicroRmNettWorkbook())->build([
            ['pn' => '2', 'realisasi_os' => 3000000000, 'realisasi_deb' => 3],
            ['pn' => '999', 'realisasi_os' => 50000000, 'realisasi_deb' => 1],
        ], $roster, 'Area 6', '2026-10-07');
        $html = view('report.dashboard-pinjaman._kinerjarmmikro_partials.micro-rm-nett-sheets', ['rmKurProductivity' => $payload] + $this->formats())->render();
        $xpath = $this->xpath($html);
        $this->assertSame(2, $xpath->query('//input[@name="micro-rm-sheet"]')->length);
        $this->assertSame(1, $xpath->query('//input[@value="bo" and @checked]')->length);
        $this->assertSame(0, $xpath->query('//section[@data-sheet="kcp"]')->length);
        $order = $xpath->query('//section[@data-sheet="bo"]//tbody/tr');
        $this->assertSame(['kc', 'kc', 'kcp', 'kcp'], array_map(fn ($r) => $r->getAttribute('data-office-type') ?: $r->getAttribute('data-subtotal'), iterator_to_array($order)));
        foreach ($xpath->query('//section[@data-sheet="bo"]//tbody/tr | //section[@data-sheet="bo"]//tfoot/tr') as $row) {
            $width = 0;
            foreach ($row->childNodes as $cell) {
                if ($cell instanceof \DOMElement) { $width += max(1, (int) $cell->getAttribute('colspan')); }
            }
            $this->assertSame(17, $width);
        }
        $footer = $xpath->query('//section[@data-sheet="bo"]//tfoot/tr/*');
        $this->assertSame('50,0%', trim($footer->item(5)->textContent));
        $this->assertSame(2, $xpath->query('//section[@data-sheet="detail"]//tbody/tr')->length);
        $this->assertSame(8, $xpath->query('//section[@data-sheet="detail"]//tbody/tr[1]/*')->length);
        $this->assertStringContainsString('Per RM', $html);
        $this->assertStringContainsString('Rp 50 jt', $html);
        $this->assertStringContainsString('&lt;script&gt;RM&lt;/script&gt;', $html);
        $this->assertStringContainsString('tidak dihitung sebagai orang', $html);
    }

    public function test_missing_rm_distribution_is_explicitly_unavailable(): void
    {
        $html = view('report.dashboard-pinjaman._kinerjarmmikro_partials.micro-rm-nett-sheets', ['rmKurProductivity' => []] + $this->formats())->render();
        $this->assertStringContainsString('Data yang belum tersedia tidak dianggap sebagai belum realisasi.', $html);
        $this->assertStringNotContainsString('0 Personel', $html);
    }

    public function test_mantri_sheets_have_aligned_columns_and_escaped_names(): void
    {
        $row = $this->mantriRow();
        $html = view('report.dashboard-pinjaman._kinerjarmmikro_partials.micro-mantri-nett-sheets', ['mantriPerformance' => ['sheet_rows' => ['bo' => [$row], 'mbm' => [$row], 'unit' => [$row]], 'total' => $row]] + $this->formats())->render();
        $xpath = $this->xpath($html);
        $this->assertSame(3, $xpath->query('//input[@name="micro-mantri-sheet"]')->length);
        $this->assertSame(1, $xpath->query('//input[@value="bo" and @checked]')->length);
        foreach (['bo' => 28, 'mbm' => 28, 'unit' => 30] as $sheet => $columns) {
            $this->assertSame($columns, $xpath->query('//section[@data-sheet="'.$sheet.'"]//tbody/tr/*')->length);
            $footer = $xpath->query('//section[@data-sheet="'.$sheet.'"]//tfoot/tr/*');
            $width = 0;
            foreach ($footer as $cell) {
                $width += max(1, (int) $cell->getAttribute('colspan'));
            }
            $this->assertSame($columns, $width);
        }
        $this->assertStringContainsString('&lt;MBM&gt;', $html);
        $this->assertStringContainsString('Total &lt;BO&gt;', $html);
        $this->assertStringNotContainsString('Realisasi bulan berjalan</th>', $html);
    }

    public function test_legacy_mantri_rows_keep_bo_visible_and_other_sheets_unavailable(): void
    {
        $row = $this->mantriRow();
        $html = view('report.dashboard-pinjaman._kinerjarmmikro_partials.micro-mantri-nett-sheets', ['mantriPerformance' => ['rows' => [$row], 'total' => $row]] + $this->formats())->render();
        $this->assertSame(1, $this->xpath($html)->query('//section[@data-sheet="bo"]//tbody/tr')->length);
        $this->assertStringContainsString('Rincian MBM belum tersedia', $html);
        $this->assertStringContainsString('Rincian UNIT belum tersedia', $html);
    }

    private function mantriRow(): array
    {
        $buckets = array_map(fn ($key) => ['key' => $key, 'label' => $key, 'range' => '', 'mantri' => 1, 'share' => 20], ['zero', 'extreme_low', 'low', 'mid', 'high']);
        return ['branch' => '<BO>', 'branch_code' => '45', 'unit' => '<Unit>', 'mbm' => '<MBM>', 'tiers' => ['pt' => ['buckets' => $buckets], 'contract' => ['buckets' => $buckets]]];
    }

    private function formats(): array
    {
        return ['formatInteger' => fn ($v) => number_format($v), 'formatJuta' => fn ($v) => number_format($v / 1000000), 'formatAmount' => fn ($v) => 'Rp '.number_format($v / 1000000).' jt', 'formatPercent' => fn ($v) => $v.'%'];
    }

    private function xpath(string $html): \DOMXPath
    {
        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        return new \DOMXPath($dom);
    }
}
