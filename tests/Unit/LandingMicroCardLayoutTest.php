<?php

namespace Tests\Unit;

use DOMDocument;
use DOMXPath;
use Tests\TestCase;

class LandingMicroCardLayoutTest extends TestCase
{
    public function test_every_micro_card_remains_inside_the_shared_spacing_container_with_or_without_pending_mantri(): void
    {
        foreach ([[], [['branch' => 'KC MADIUN', 'name' => 'Mantri Uji', 'pn' => '00123', 'unit' => 'Unit Uji']]] as $people) {
            $xpath = $this->render(['realization' => ['kupedes_not_realized' => $people]]);
            $class = 'contains(concat(" ",normalize-space(@class)," ")," micro-ops-section ")';
            $allCards = $xpath->query('//section['.$class.']')->length;
            $rootCards = $xpath->query('//*[@data-micro-performance-ready]/section['.$class.']')->length;

            $this->assertGreaterThanOrEqual(10, $allCards);
            $this->assertSame($allCards - 1, $rootCards, 'Only the segmented KSBK report should be nested inside another card.');
            $this->assertSame(1, $xpath->query('//*[@id="micro-prewash-ksbk-panel"]/section[contains(@class,"micro-ksbk")]')->length);
            $this->assertSame(1, $xpath->query('//*[@data-micro-performance-ready]/section[@data-pn-mismatch-section]')->length);
            $this->assertSame(1, $xpath->query('//*[@data-micro-performance-ready]//fieldset[contains(@class,"micro-prewash-switch")]')->length);
        }
    }

    public function test_native_prewash_controls_reference_unique_panels_and_keep_each_reports_values_separate(): void
    {
        $xpath = $this->render([
            'pipeline_prewash' => $this->report('Original', 11),
            'pipeline_prewash_boh' => $this->report('BOH', 22),
            'monitoring_tl_pipeline_mbm' => $this->report('MBM', 33),
        ]);

        $this->assertSame(4, $xpath->query('//fieldset[contains(@class,"micro-prewash-switch")]/input[@type="radio"]')->length);
        foreach (['original' => ['pipeline-prewash', 'Original', '11'], 'boh' => ['pipeline-prewash-boh', 'BOH', '22'], 'mbm' => ['pipeline-prewash-mbm', 'MBM', '33']] as $key => [$prefix, $branch, $count]) {
            $control = $xpath->query('//*[@id="micro-prewash-'.$key.'"]')->item(0);
            $this->assertNotNull($control);
            $panelId = $control->getAttribute('aria-controls');
            $this->assertSame('micro-prewash-'.$key.'-panel', $panelId);
            $panel = $xpath->query('//*[@id="'.$panelId.'"]')->item(0);
            $this->assertNotNull($panel);
            $this->assertSame('region', $panel->getAttribute('role'));
            $label = $xpath->query('//*[@id="'.$panel->getAttribute('aria-labelledby').'"]')->item(0);
            $this->assertNotNull($label);
            $this->assertSame($control->getAttribute('id'), $label->getAttribute('for'));
            $this->assertSame($key === 'original', $control->hasAttribute('checked'));
            $this->assertSame($branch, trim($xpath->evaluate('string(//*[@id="'.$prefix.'-bo"]//tbody/tr/th)')));
            $this->assertSame($count, trim($xpath->evaluate('string(//*[@id="'.$prefix.'-bo"]//tbody/tr/td[3])')));
        }
        $this->assertSame('micro-prewash-ksbk-panel', $xpath->evaluate('string(//*[@id="micro-prewash-ksbk"]/@aria-controls)'));
        $this->assertSame('micro-prewash-ksbk-label', $xpath->evaluate('string(//*[@id="micro-prewash-ksbk-panel"]/@aria-labelledby)'));
        $this->assertSame(1, $xpath->query('//*[@id="micro-ksbk-title"]')->length);
        $this->assertSame(0, $xpath->query('//*[@data-micro-performance-ready]/section[contains(@class,"micro-ksbk")]')->length);
    }

    public function test_personnel_card_retains_scoped_nominative_actions_and_counts(): void
    {
        $xpath = $this->render(['pn_mismatch' => [
            'available' => true, 'period' => '2026-11-10', 'total' => 6391,
            'branches' => [
                ['branch' => 'KC MADIUN', 'count' => 1108],
                ['branch' => 'KC MAGETAN', 'count' => 1992],
                ['branch' => 'KC NGAWI', 'count' => 3059],
                ['branch' => 'KC PONOROGO', 'count' => 232],
            ],
        ]]);
        $card = '//*[@data-micro-performance-ready]/section[@data-pn-mismatch-section]';
        $this->assertSame(1, $xpath->query($card.'[@data-segment="micro"][@data-period="2026-11-10"]')->length);
        $this->assertSame(4, $xpath->query($card.'//button[@data-pn-mismatch-open]')->length);
        $this->assertStringContainsString('6.391', $xpath->evaluate('string('.$card.'/header/strong)'));
        $this->assertSame('KC MADIUN', $xpath->evaluate('string(('.$card.'//button)[1]/@data-branch)'));
        $this->assertStringContainsString('1.108 rekening', $xpath->evaluate('string(('.$card.'//button)[1])'));
        $this->assertSame(1, $xpath->query('//*[@data-pn-mismatch-modal][@data-pn-modal-segment="micro"][@hidden]')->length);
        $this->assertSame(1, $xpath->query('//*[@data-pn-mismatch-rows]')->length);
    }

    private function report(string $branch, int $count): array
    {
        $group = [
            'branch_name' => $branch, 'branch_code' => '45', 'unit_name' => 'Unit Uji',
            'mbm_pn' => '00123', 'mbm_name' => 'MBM Uji', 'total_count' => $count,
            'total_amount' => '1000000.00', 'visited_count' => 1, 'visited_share' => 10,
            'unvisited_count' => $count - 1, 'unvisited_share' => 90,
            'realized_count' => 1, 'realized_share' => 100, 'realized_amount' => '100000.00',
        ];

        return ['available' => true, 'year' => 2026, 'groups' => ['bo' => [$group], 'unit' => [], 'mbm' => []], 'summary' => $group];
    }

    private function render(array $sections): DOMXPath
    {
        $html = view('dashboard.partials.micro-performance', ['microPerformance' => [
            'meta' => ['available' => true, 'period' => '2026-11-10'], ...$sections,
        ]])->render();
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }
}
