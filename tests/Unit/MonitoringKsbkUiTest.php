<?php

namespace Tests\Unit;

use DOMDocument;
use DOMXPath;
use Tests\TestCase;

class MonitoringKsbkUiTest extends TestCase
{
    public function test_grouped_headers_and_scoped_totals_render_in_the_micro_landing(): void
    {
        $payload = $this->payload();
        $payload['summary'] = array_replace($this->row(), [
            'small_count' => 16, 'large_count' => 34, 'total_count' => 50,
            'ksbk_count' => 19, 'ksbk_amount' => '893000000.00',
            'non_ksbk_count' => 6, 'non_ksbk_amount' => '310000000.00',
            'realized_count' => 25, 'realized_amount' => '1203000000.00',
            'unrealized_count' => 25, 'unrealized_share' => 50,
        ]);
        $xpath = $this->render($payload, true);

        $this->assertSame(1, $xpath->query('//*[@id="micro-ksbk-title"]')->length);
        $this->assertSame('Monitoring Realisasi KSBK', $xpath->evaluate('string(//*[@id="micro-ksbk-title"])'));
        $this->assertSame(1, $xpath->query('//section[contains(@class,"micro-ksbk")]//*[@role="region"][@tabindex="0"]')->length);
        $this->assertSame(4, $xpath->query('//table[@class="micro-ksbk__table"]/thead/tr')->length);
        $this->assertSame('3', $xpath->evaluate('string(//table[@class="micro-ksbk__table"]/thead/tr[1]/th[3]/@colspan)'));
        $this->assertSame('8', $xpath->evaluate('string(//table[@class="micro-ksbk__table"]/thead/tr[1]/th[4]/@colspan)'));
        $this->assertSame('6', $xpath->evaluate('string(//table[@class="micro-ksbk__table"]/thead/tr[2]/th[4]/@colspan)'));
        $this->assertSame('Belum Real', $xpath->evaluate('string(//table[@class="micro-ksbk__table"]/thead/tr[2]/th[5])'));
        $this->assertSame(13, $xpath->query('//table[@class="micro-ksbk__table"]/tbody/tr/*')->length);
        $this->assertSame('Region 13 / Malang', $xpath->evaluate('string(//table[@class="micro-ksbk__table"]/tbody/tr/td[1])'));
        $this->assertSame('KC Ponorogo', $xpath->evaluate('string(//table[@class="micro-ksbk__table"]/tbody/tr/th[@scope="row"])'));
        $this->assertSame('180', $this->metric($xpath, 'tbody', 'ksbk_amount'));
        $this->assertSame('16', $this->metric($xpath, 'tfoot', 'small_count'));
        $this->assertSame('50', $this->metric($xpath, 'tfoot', 'total_count'));
        $this->assertSame('893', $this->metric($xpath, 'tfoot', 'ksbk_amount'));
        $this->assertSame('1.203', $this->metric($xpath, 'tfoot', 'realized_amount'));
        $this->assertSame('50,0%', $this->metric($xpath, 'tfoot', 'unrealized_share'));
        $this->assertStringContainsString('10 November 2026', $xpath->evaluate('string(//section[contains(@class,"micro-ksbk")])'));
        $this->assertStringContainsString('Daily Loan Dinamis', $xpath->evaluate('string(//section[contains(@class,"micro-ksbk")])'));

        foreach ($xpath->query('//section[contains(@class,"micro-ksbk")]//*[@aria-labelledby or @aria-describedby]') as $element) {
            foreach (['aria-labelledby', 'aria-describedby'] as $attribute) {
                foreach (preg_split('/\s+/', trim($element->getAttribute($attribute)), -1, PREG_SPLIT_NO_EMPTY) as $id) {
                    $this->assertSame(1, $xpath->query('//*[@id="'.$id.'"]')->length);
                }
            }
        }
    }

    public function test_unverified_metrics_remain_distinct_from_zero_and_labels_are_escaped(): void
    {
        $payload = $this->payload();
        $payload['groups'][0] = array_replace($this->row(), [
            'regional_office' => '<script>alert(1)</script>',
            'branch_name' => 'KC Madiun & Sekitar',
            'ksbk_count' => null, 'ksbk_amount' => null,
            'non_ksbk_count' => 0, 'non_ksbk_amount' => '0.00',
            'unrealized_share' => null,
        ]);
        $payload['summary'] = $payload['groups'][0];
        $payload['message'] = 'Laporan pinjaman belum tersedia; realisasi belum dapat dihitung.';
        $xpath = $this->render($payload);

        $this->assertSame(0, $xpath->query('//script')->length);
        $this->assertSame('<script>alert(1)</script>', $xpath->evaluate('string(//tbody/tr/td[1])'));
        $this->assertSame('KC Madiun & Sekitar', $xpath->evaluate('string(//tbody/tr/th)'));
        $this->assertSame('—', $this->metric($xpath, 'tbody', 'ksbk_count'));
        $this->assertSame('—', $this->metric($xpath, 'tbody', 'ksbk_amount'));
        $this->assertSame('0', $this->metric($xpath, 'tbody', 'non_ksbk_amount'));
        $this->assertSame('0', $this->metric($xpath, 'tbody', 'non_ksbk_count'));
        $this->assertSame('—', $this->metric($xpath, 'tfoot', 'unrealized_share'));
        $this->assertStringContainsString('Laporan pinjaman belum tersedia', $xpath->evaluate('string(//*[@role="status"])'));
    }

    public function test_empty_report_shows_import_instruction_without_fabricated_totals(): void
    {
        $xpath = $this->render([]);

        $this->assertSame(0, $xpath->query('//table')->length);
        $this->assertStringContainsString('Impor laporan Monitoring Realisasi KSBK', $xpath->evaluate('string(//*[@role="status"])'));
    }

    public function test_fractional_millions_and_lw_source_are_displayed(): void
    {
        $payload = $this->payload();
        $payload['loan_source'] = 'lw321pn';
        $payload['groups'][0]['ksbk_amount'] = '2500000.00';
        $xpath = $this->render($payload);

        $this->assertSame('2,50', $this->metric($xpath, 'tbody', 'ksbk_amount'));
        $this->assertStringContainsString('LW321PN', $xpath->evaluate('string(//section)'));
    }

    private function metric(DOMXPath $xpath, string $section, string $metric): string
    {
        return $xpath->evaluate('string(//table[@class="micro-ksbk__table"]/'.$section.'//td[@data-ksbk-metric="'.$metric.'"])');
    }

    private function row(): array
    {
        return [
            'regional_office' => 'Region 13 / Malang', 'branch_name' => 'KC Ponorogo',
            'small_count' => 5, 'large_count' => 17, 'total_count' => 22,
            'ksbk_count' => 6, 'ksbk_amount' => '180000000.00',
            'non_ksbk_count' => 0, 'non_ksbk_amount' => '0.00',
            'realized_count' => 6, 'realized_amount' => '180000000.00',
            'unrealized_count' => 16, 'unrealized_share' => 72.7,
        ];
    }

    private function payload(): array
    {
        return [
            'available' => true, 'loan_source' => 'daily_loan_dinamis', 'loan_period' => '2026-11-10',
            'groups' => [$this->row()], 'summary' => $this->row(),
        ];
    }

    private function render(array $payload, bool $landing = false): DOMXPath
    {
        $html = $landing
            ? view('dashboard.partials.micro-performance', ['microPerformance' => [
                'meta' => ['available' => true], 'monitoring_realisasi_ksbk' => $payload,
            ]])->render()
            : view('dashboard.partials.monitoring-ksbk', ['monitoringKsbk' => $payload])->render();
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }
}
