<?php

namespace Tests\Unit;

use DOMDocument;
use DOMXPath;
use Tests\TestCase;

class MicroReportNominativeUiTest extends TestCase
{
    public function test_selected_kc_opens_units_and_preserves_the_scope_for_each_pipeline_report(): void
    {
        foreach (['pipeline-prewash' => 'prewash', 'pipeline-prewash-boh' => 'boh', 'pipeline-prewash-mbm' => 'mbm'] as $prefix => $report) {
            $xpath = $this->render('pipeline-prewash', [
                'pipelinePrewash' => $this->pipeline(),
                'prewashPrefix' => $prefix,
                'microReportContext' => ['branch_key' => 'madiun', 'period' => '2026-11-10'],
            ]);

            $this->assertSame($prefix.'-unit', $xpath->evaluate('string(//details[@open]/@id)'));
            $this->assertSame(1, $xpath->query('//details[@open]')->length);
            $row = '//details[@id="'.$prefix.'-unit"]//tr[@data-micro-report-row]';
            foreach (['report' => $report, 'level' => 'unit', 'branch' => '0045', 'unit' => '1234', 'cabang' => 'madiun', 'periode' => '2026-11-10'] as $attribute => $value) {
                $this->assertSame($value, $xpath->evaluate('string('.$row.'/@data-'.$attribute.')'));
            }
            $this->assertSame(4, $xpath->query($row.'//button[@data-micro-nominative-open]')->length);
            $this->assertSame(1, $xpath->query($row.'//button[@data-metric="visited"]')->length);
            $this->assertSame('0', $xpath->evaluate('string('.$row.'//button[@data-metric="realized"])'));
        }
    }

    public function test_payload_context_takes_precedence_over_the_default_area_context(): void
    {
        $payload = $this->pipeline();
        $payload['view_context'] = ['branch_key' => 'magetan', 'periode' => '2026-11-11'];
        $xpath = $this->render('pipeline-prewash', ['pipelinePrewash' => $payload]);

        $this->assertSame('pipeline-prewash-unit', $xpath->evaluate('string(//details[@open]/@id)'));
        $this->assertSame('magetan', $xpath->evaluate('string(//tr[@data-micro-report-row]/@data-cabang)'));
        $this->assertSame('2026-11-11', $xpath->evaluate('string(//tr[@data-micro-report-row]/@data-periode)'));
    }

    public function test_area_view_starts_at_bo_and_unknown_counts_remain_unavailable(): void
    {
        $payload = $this->pipeline();
        $payload['groups']['bo'][0]['realized_count'] = null;
        $xpath = $this->render('pipeline-prewash', ['pipelinePrewash' => $payload]);

        $this->assertSame('pipeline-prewash-bo', $xpath->evaluate('string(//details[@open]/@id)'));
        $this->assertSame(0, $xpath->query('//details[@id="pipeline-prewash-bo"]//button[@data-metric="realized"]')->length);
        $this->assertSame('—', $xpath->evaluate('string(//details[@id="pipeline-prewash-bo"]//tbody/tr/td[9])'));
    }

    public function test_ksbk_selected_kc_displays_unit_groups_with_count_actions_and_unit_scope(): void
    {
        $row = ['regional_office' => 'Malang', 'branch_code' => '0045', 'branch_name' => 'KC Madiun', 'unit_code' => '1234', 'unit_name' => 'Unit Gemarang <test>', 'total_count' => 2, 'small_count' => 1, 'large_count' => 1, 'ksbk_count' => null];
        $xpath = $this->render('monitoring-ksbk', [
            'monitoringKsbk' => ['available' => true, 'groups' => [['branch_name' => 'BO aggregate']], 'unit_groups' => [$row], 'summary' => $row],
            'microReportContext' => ['branch_key' => 'madiun', 'period' => '2026-11-10'],
        ]);

        $this->assertSame('Unit Kerja', $xpath->evaluate('string(//thead/tr[1]/th[3])'));
        $this->assertSame('Unit Gemarang <test>', $xpath->evaluate('string(//tbody/tr/th)'));
        $this->assertSame('unit', $xpath->evaluate('string(//tbody/tr/@data-level)'));
        $this->assertSame('1234', $xpath->evaluate('string(//tbody/tr/@data-unit)'));
        $this->assertSame('ksbk', $xpath->evaluate('string(//tbody/tr/@data-report)'));
        $this->assertSame('3', $xpath->evaluate('string(//tfoot/tr/th/@colspan)'));
        $this->assertSame(0, $xpath->query('//button[@data-metric="ksbk"]')->length);
        $this->assertSame(1, $xpath->query('//button[@data-metric="all"]')->length);
        $this->assertSame(0, $xpath->query('//test')->length);
        $this->assertStringNotContainsString('BO aggregate', $xpath->evaluate('string(//tbody)'));
    }

    private function pipeline(): array
    {
        $row = ['branch_code' => '0045', 'branch_name' => 'KC Madiun', 'unit_code' => '1234', 'unit_name' => 'Unit Gemarang', 'mbm_pn' => '020458', 'mbm_name' => 'Nur Efliana', 'total_count' => 2, 'visited_count' => 1, 'unvisited_count' => 1, 'realized_count' => 0];

        return ['available' => true, 'year' => 2026, 'groups' => ['bo' => [$row], 'mbm' => [$row], 'unit' => [$row]]];
    }

    private function render(string $partial, array $data): DOMXPath
    {
        $html = view('dashboard.partials.'.$partial, $data)->render();
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);

        return new DOMXPath($document);
    }
}
