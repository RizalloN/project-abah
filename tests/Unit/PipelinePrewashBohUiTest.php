<?php

namespace Tests\Unit;

use DOMDocument;
use DOMXPath;
use Tests\TestCase;

class PipelinePrewashBohUiTest extends TestCase
{
    public function test_micro_landing_exposes_labeled_native_radio_switch_with_original_pipeline_selected(): void
    {
        $xpath = $this->render([]);

        $this->assertSame(4, $xpath->query('//input[@type="radio"][@name="micro-prewash-view"]')->length);
        $this->assertSame(1, $xpath->query('//input[@id="micro-prewash-original"][@checked]')->length);
        $this->assertSame(0, $xpath->query('//input[@id="micro-prewash-boh"][@checked]')->length);
        $this->assertSame('Pipeline Prewash', $xpath->evaluate('string(//label[@for="micro-prewash-original"])'));
        $this->assertSame('TL Pipeline Pre-wash BOH', $xpath->evaluate('string(//label[@for="micro-prewash-boh"])'));
        $this->assertSame(1, $xpath->query('//fieldset[contains(@class,"micro-prewash-switch")]/legend')->length);
        $this->assertStringContainsString('Impor laporan Pipeline Prewash', $xpath->evaluate('string(//div[@class="micro-prewash-boh-panel"]//*[@role="status"])'));
    }

    public function test_boh_views_render_branch_and_unit_metrics_with_unique_accessibility_references(): void
    {
        $metrics = [
            'branch_code' => '0045', 'branch_name' => 'KC Madiun', 'unit_name' => 'Unit Gemarang',
            'total_count' => 4, 'total_amount' => '1000000.00',
            'visited_count' => 2, 'visited_share' => 50,
            'unvisited_count' => 2, 'unvisited_share' => 50,
            'realized_count' => 1, 'realized_share' => 50, 'realized_amount' => null,
        ];
        $xpath = $this->render([
            'available' => true, 'year' => 2026,
            'visit_period' => '2026-11-12', 'loan_period' => '2026-11-10', 'loan_source' => 'daily_loan_dinamis',
            'summary' => $metrics, 'groups' => ['bo' => [$metrics], 'unit' => [$metrics]],
        ]);

        $this->assertSame(2, $xpath->query('//div[@class="micro-prewash-boh-panel"]//details')->length);
        $this->assertSame(2, $xpath->query('//div[@class="micro-prewash-boh-panel"]//table')->length);
        $this->assertSame(0, $xpath->query('//*[@id="pipeline-prewash-boh-mbm"]')->length);
        $this->assertSame(1, $xpath->query('//*[@id="pipeline-prewash-boh-bo"][@open]')->length);
        $this->assertSame('Monitoring Tindak Lanjut Pipeline Prewash PIC BOH', $xpath->evaluate('string(//*[@id="pipeline-prewash-boh-title"])'));
        $this->assertSame('KC Madiun', $xpath->evaluate('string(//*[@id="pipeline-prewash-boh-bo"]//tbody/tr/th)'));
        $this->assertSame('Unit Gemarang', $xpath->evaluate('string(//*[@id="pipeline-prewash-boh-unit"]//tbody/tr/th)'));
        $this->assertSame('—', $xpath->evaluate('string(//*[@id="pipeline-prewash-boh-bo"]//tbody/tr/td[last()])'));

        foreach ($xpath->query('//div[@class="micro-prewash-boh-panel"]//*[@id]') as $element) {
            $id = $element->getAttribute('id');
            $this->assertSame(1, $xpath->query('//*[@id="'.$id.'"]')->length, 'Duplicate ID: '.$id);
        }
        foreach ($xpath->query('//div[@class="micro-prewash-boh-panel"]//*[@aria-labelledby or @aria-describedby]') as $element) {
            foreach (['aria-labelledby', 'aria-describedby'] as $attribute) {
                foreach (preg_split('/\s+/', trim($element->getAttribute($attribute)), -1, PREG_SPLIT_NO_EMPTY) as $id) {
                    $this->assertSame(1, $xpath->query('//*[@id="'.$id.'"]')->length, 'Unresolved '.$attribute.': '.$id);
                }
            }
        }
    }

    private function render(array $boh): DOMXPath
    {
        $html = view('dashboard.partials.micro-performance', [
            'microPerformance' => ['meta' => ['available' => true], 'pipeline_prewash_boh' => $boh],
        ])->render();
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }
}
