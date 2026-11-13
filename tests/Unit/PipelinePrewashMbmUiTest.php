<?php

namespace Tests\Unit;

use DOMDocument;
use DOMXPath;
use Tests\TestCase;

class PipelinePrewashMbmUiTest extends TestCase
{
    public function test_micro_landing_adds_mbm_trigger_next_to_existing_reports_with_original_selected(): void
    {
        $xpath = $this->render([]);

        $this->assertSame(4, $xpath->query('//input[@type="radio"][@name="micro-prewash-view"]')->length);
        $this->assertSame(1, $xpath->query('//input[@id="micro-prewash-original"][@checked]')->length);
        $this->assertSame(0, $xpath->query('//input[@id="micro-prewash-mbm"][@checked]')->length);
        $this->assertSame('TL Pipeline Pre-wash PIC MBM', $xpath->evaluate('string(//label[@for="micro-prewash-mbm"])'));
        $this->assertSame('micro-prewash-boh', $xpath->evaluate('string(//input[@id="micro-prewash-mbm"]/preceding-sibling::input[1]/@id)'));
        $this->assertStringContainsString('Impor laporan Monitoring TL Pipeline Prewash PIC MBM', $xpath->evaluate('string(//div[@class="micro-prewash-mbm-panel"]//*[@role="status"])'));
        $this->assertSame(0, $xpath->query('//div[@class="micro-prewash-mbm-panel"]//table')->length);
    }

    public function test_bo_mbm_and_unit_tables_keep_their_distinct_identity_columns_and_correct_totals(): void
    {
        $row = $this->row();
        $xpath = $this->render($this->payload($row));

        $this->assertSame('Monitoring Tindak Lanjut Pipeline Prewash PIC MBM', $xpath->evaluate('string(//*[@id="pipeline-prewash-mbm-title"])'));
        $this->assertSame(3, $xpath->query('//div[@class="micro-prewash-mbm-panel"]//table')->length);
        $this->assertSame(3, $xpath->query('//div[@class="micro-prewash-mbm-panel"]//*[@role="region"][@tabindex="0"]')->length);
        $this->assertSame(1, $xpath->query('//*[@id="pipeline-prewash-mbm-bo"][@open]')->length);

        foreach (['bo' => 3, 'mbm' => 5, 'unit' => 6] as $view => $identities) {
            $table = '//*[@id="pipeline-prewash-mbm-'.$view.'"]//table';
            $this->assertSame($identities + 9, $xpath->query($table.'//tbody/tr/*')->length);
            $this->assertSame((string) $identities, $xpath->evaluate('string('.$table.'//tfoot/tr/th/@colspan)'));
            $this->assertSame('4', $xpath->evaluate('string('.$table.'//tbody/tr/*['.($identities + 1).'])'));
            $this->assertSame('2,50', $xpath->evaluate('string('.$table.'//tbody/tr/*['.($identities + 2).'])'));
            $this->assertSame('50,00%', $xpath->evaluate('string('.$table.'//tbody/tr/*['.($identities + 4).'])'));
            $this->assertStringContainsString('10 November 2026', $xpath->evaluate('string('.$table.'//thead/tr[1]/th[last()])'));
        }

        $this->assertSame('020458', $xpath->evaluate('string(//*[@id="pipeline-prewash-mbm-mbm"]//tbody/tr/td[4])'));
        $this->assertSame('Nur Elfiana', $xpath->evaluate('string(//*[@id="pipeline-prewash-mbm-unit"]//tbody/tr/td[4])'));
        $this->assertSame('6350', $xpath->evaluate('string(//*[@id="pipeline-prewash-mbm-unit"]//tbody/tr/td[5])'));
        $this->assertSame('Unit Gemarang', $xpath->evaluate('string(//*[@id="pipeline-prewash-mbm-unit"]//tbody/tr/th)'));
        $this->assertSame('Kode Unit', $xpath->evaluate('string(//*[@id="pipeline-prewash-mbm-unit"]//thead/tr[1]/th[5])'));

        foreach ($xpath->query('//div[@class="micro-prewash-mbm-panel"]//*[@id]') as $element) {
            $id = $element->getAttribute('id');
            $this->assertSame(1, $xpath->query('//*[@id="'.$id.'"]')->length, 'Duplicate ID: '.$id);
        }
        foreach ($xpath->query('//div[@class="micro-prewash-mbm-panel"]//*[@aria-labelledby or @aria-describedby]') as $element) {
            foreach (['aria-labelledby', 'aria-describedby'] as $attribute) {
                foreach (preg_split('/\s+/', trim($element->getAttribute($attribute)), -1, PREG_SPLIT_NO_EMPTY) as $id) {
                    $this->assertSame(1, $xpath->query('//*[@id="'.$id.'"]')->length, 'Unresolved '.$attribute.': '.$id);
                }
            }
        }
    }

    public function test_missing_realization_stays_distinct_from_zero_and_names_are_escaped(): void
    {
        $row = array_replace($this->row(), [
            'mbm_name' => '<script>alert(1)</script>',
            'unit_name' => 'Unit Gemarang & Karang',
            'realized_count' => null, 'realized_share' => null, 'realized_amount' => null,
            'total_amount' => '0.00',
        ]);
        $payload = $this->payload($row);
        $payload['message'] = 'Laporan pinjaman belum tersedia; realisasi belum dapat dihitung.';
        $xpath = $this->render($payload);

        $this->assertSame('<script>alert(1)</script>', $xpath->evaluate('string(//*[@id="pipeline-prewash-mbm-mbm"]//tbody/tr/th)'));
        $this->assertSame(0, $xpath->query('//div[@class="micro-prewash-mbm-panel"]//script')->length);
        $this->assertSame('0,00', $xpath->evaluate('string(//*[@id="pipeline-prewash-mbm-bo"]//tbody/tr/*[5])'));
        $this->assertSame('—', $xpath->evaluate('string(//*[@id="pipeline-prewash-mbm-bo"]//tbody/tr/*[last()])'));
        $this->assertSame('—', $xpath->evaluate('string(//*[@id="pipeline-prewash-mbm-unit"]//tfoot/tr/*[last()])'));
        $this->assertStringContainsString('Laporan pinjaman belum tersedia', $xpath->evaluate('string(//div[@class="micro-prewash-mbm-panel"]//*[@role="status"])'));
    }

    private function row(): array
    {
        return [
            'branch_code' => '0045', 'branch_name' => 'KC Madiun',
            'mbm_pn' => '020458', 'mbm_name' => 'Nur Elfiana',
            'unit_code' => '6350', 'unit_name' => 'Unit Gemarang',
            'total_count' => 4, 'total_amount' => '2500000.00',
            'visited_count' => 2, 'visited_share' => 50,
            'unvisited_count' => 2, 'unvisited_share' => 50,
            'realized_count' => 1, 'realized_share' => 50, 'realized_amount' => '1000000.00',
        ];
    }

    private function payload(array $row): array
    {
        return [
            'available' => true, 'year' => 2026,
            'visit_period' => '2026-11-12', 'loan_period' => '2026-11-10', 'loan_source' => 'daily_loan_dinamis',
            'summary' => $row, 'groups' => ['bo' => [$row], 'mbm' => [$row], 'unit' => [$row]],
        ];
    }

    private function render(array $mbm): DOMXPath
    {
        $html = view('dashboard.partials.micro-performance', [
            'microPerformance' => ['meta' => ['available' => true], 'monitoring_tl_pipeline_mbm' => $mbm],
        ])->render();
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }
}
