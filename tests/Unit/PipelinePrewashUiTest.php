<?php

namespace Tests\Unit;

use DOMDocument;
use DOMXPath;
use Tests\TestCase;

class PipelinePrewashUiTest extends TestCase
{
    public function test_empty_payload_has_an_actionable_unavailable_state_without_zero_totals(): void
    {
        $xpath = $this->render([]);

        $this->assertSame('Pipeline Prewash', $xpath->evaluate('string(//h3)'));
        $this->assertStringContainsString('Impor laporan Pipeline Prewash', $xpath->evaluate('string(//*[@role="status"])'));
        $this->assertSame(0, $xpath->query('//table')->length);
    }

    public function test_views_render_supplied_metrics_and_keep_missing_values_distinct_from_zero(): void
    {
        $row = [
            'branch_code' => '045',
            'branch_name' => 'KC Madiun <script>alert(1)</script>',
            'unit_name' => 'Unit Gemarang',
            'mbm_pn' => '020458',
            'mbm_name' => 'Nur Efriyana',
            'total_count' => 3705,
            'total_amount' => '228743000000.50',
            'visited_count' => 1466,
            'visited_share' => 39.57,
            'unvisited_count' => 2239,
            'unvisited_share' => 60.43,
            'realized_count' => 79,
            'realized_share' => 5.39,
            'realized_amount' => null,
        ];
        $xpath = $this->render([
            'available' => true,
            'year' => 2026,
            'loan_period' => '2026-11-10',
            'loan_source' => 'daily_loan_dinamis',
            'visit_period' => '2026-11-12',
            'summary' => $row,
            'groups' => ['bo' => [$row], 'mbm' => [$row], 'unit' => [$row]],
        ]);

        $this->assertSame(3, $xpath->query('//table')->length);
        $this->assertSame(3, $xpath->query('//details')->length);
        $this->assertSame(1, $xpath->query('//details[@open]')->length);
        $this->assertSame('pipeline-prewash-bo', $xpath->evaluate('string(//details[@open]/@id)'));
        $this->assertSame(3, $xpath->query('//*[@role="region"][@tabindex="0"]')->length);
        $this->assertSame(0, $xpath->query('//script')->length);
        $this->assertSame($row['branch_name'], $xpath->evaluate('string(//details[@id="pipeline-prewash-bo"]//tbody/tr/th)'));
        $this->assertSame('020458', $xpath->evaluate('string(//details[@id="pipeline-prewash-mbm"]//tbody/tr/td[4])'));
        $this->assertSame('228.743,00', $xpath->evaluate('string(//details[@id="pipeline-prewash-bo"]//tbody/tr/td[4])'));
        $this->assertSame('5,39%', $xpath->evaluate('string(//details[@id="pipeline-prewash-bo"]//tbody/tr/td[10])'));
        $this->assertSame('—', $xpath->evaluate('string(//details[@id="pipeline-prewash-bo"]//tbody/tr/td[last()])'));
        $this->assertSame('—', $xpath->evaluate('string(//details[@id="pipeline-prewash-unit"]//tfoot/tr/td[last()])'));
        $this->assertStringContainsString('12 November 2026', $xpath->evaluate('string(//*[@aria-label="Periode laporan Pipeline Prewash"])'));
        $this->assertStringContainsString('Daily Loan Dinamis', $xpath->evaluate('string(//*[@aria-label="Periode laporan Pipeline Prewash"])'));
    }

    public function test_zero_amounts_and_shares_remain_zero_and_empty_groups_remain_explicit(): void
    {
        $xpath = $this->render([
            'available' => true,
            'groups' => [
                'bo' => [['total_amount' => '0.00', 'realized_amount' => '0.00', 'realized_share' => 0]],
            ],
        ]);

        $this->assertSame(1, $xpath->query('//table')->length);
        $this->assertSame('0,00', $xpath->evaluate('string(//tbody/tr/td[4])'));
        $this->assertSame('0,00%', $xpath->evaluate('string(//tbody/tr/td[10])'));
        $this->assertSame('0,00', $xpath->evaluate('string(//tbody/tr/td[last()])'));
        $this->assertStringContainsString('Belum ada pipeline MBM', $xpath->evaluate('string(//details[@id="pipeline-prewash-mbm"])'));
        $this->assertStringContainsString('Belum ada pipeline Unit', $xpath->evaluate('string(//details[@id="pipeline-prewash-unit"])'));
        $this->assertSame(0, $xpath->query('//*[@id="pipeline-prewash-quality-title"]')->length);
    }

    public function test_quality_notice_explains_exclusions_and_preserves_confirmed_subtotals_and_custom_view_options(): void
    {
        $row = ['branch_name' => 'KC Madiun', 'realized_count' => 2, 'realized_amount' => '3000000.00'];
        $xpath = $this->render([
            'available' => true,
            'quality' => ['missing_cif' => 3, 'unmapped_mbm' => 5, 'unverified_realization' => 2],
            'summary' => $row,
            'groups' => ['bo' => [$row]],
        ], [
            'prewashTitle' => 'Pipeline Prewash BOH',
            'prewashPrefix' => 'pipeline-prewash-boh',
            'prewashViews' => ['bo' => ['label' => 'BO', 'description' => 'Branch Office', 'identity_columns' => 3]],
        ]);

        $notice = $xpath->evaluate('string(//*[@aria-labelledby="pipeline-prewash-boh-quality-title"])');
        $this->assertStringContainsString('3 pipeline tanpa CIF tetap masuk total pipeline', $notice);
        $this->assertStringContainsString('2 pipeline memiliki data realisasi yang perlu diverifikasi', $notice);
        $this->assertStringContainsString('5 pipeline belum terhubung ke MBM', $notice);
        $this->assertStringContainsString('Belum dipetakan', $notice);
        $this->assertStringContainsString('tidak dianggap bernilai nol', $notice);
        $this->assertSame('2', $xpath->evaluate('string(//tbody/tr/td[9])'));
        $this->assertSame('3,00', $xpath->evaluate('string(//tbody/tr/td[last()])'));
        $this->assertSame('3,00', $xpath->evaluate('string(//tfoot/tr/td[last()])'));
        $this->assertSame('Pipeline Prewash BOH', $xpath->evaluate('string(//h3[@id="pipeline-prewash-boh-title"])'));
        $this->assertSame(1, $xpath->query('//details')->length);
    }

    private function render(array $payload, array $viewOptions = []): DOMXPath
    {
        $html = view('dashboard.partials.pipeline-prewash', ['pipelinePrewash' => $payload, ...$viewOptions])->render();
        $document = new DOMDocument;
        $previousErrors = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);

        return new DOMXPath($document);
    }
}
