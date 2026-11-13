<?php

namespace Tests\Unit;

use App\Support\LandingRmKurDistribution;
use DOMDocument;
use DOMXPath;
use Tests\TestCase;

class LandingRmKurPresentationTest extends TestCase
{
    public function test_compact_matrix_keeps_people_and_percentage_for_every_category_and_uses_roster_headcount(): void
    {
        $distribution = (new LandingRmKurDistribution)->build([
            ['pn' => '001', 'realisasi_os' => 500_000_000],
            ['pn' => '-', 'realisasi_os' => 410_000_000],
        ], [
            ['pn' => '001', 'branch' => 'KC MADIUN', 'branch_code' => '45', 'unit' => 'FUNGSI BISNIS MIKRO'],
            ['pn' => '002', 'branch' => 'KC MADIUN', 'branch_code' => '45', 'unit' => 'FUNGSI BISNIS MIKRO'],
        ]);
        $xpath = $this->render([
            'available' => true,
            'scope_label' => 'KC Madiun',
            'period_label' => '10 Nov 2026',
            'distribution' => $distribution,
            // Transaction rows can include an unassigned identity; this is not the BRIHC roster count.
            'total' => ['rm_count' => 3, 'realisasi_deb' => 27, 'realisasi_os' => 910_000_000],
        ]);

        $article = '//*[@data-micro-rm-kur-productivity]';
        $matrix = $article.'//table[contains(@class,"micro-rm-kur-matrix")]';
        $this->assertSame(8, $xpath->query($matrix.'/thead/tr/th')->length);
        $this->assertSame(5, $xpath->query($matrix.'/tbody/tr/td/span[contains(@class,"micro-rm-kur-score")]')->length);
        $this->assertSame('1 orang', trim($xpath->evaluate('string(('.$matrix.'/tbody/tr/td/span)[1]/strong)')));
        $this->assertSame('50,0%', trim($xpath->evaluate('string(('.$matrix.'/tbody/tr/td/span)[1]/small)')));
        $this->assertSame('1 orang', trim($xpath->evaluate('string(('.$matrix.'/tbody/tr/td/span)[2]/strong)')));
        $this->assertSame('50,0%', trim($xpath->evaluate('string(('.$matrix.'/tbody/tr/td/span)[2]/small)')));
        $this->assertSame('2', trim($xpath->evaluate('string('.$matrix.'/tbody/tr/td[contains(@class,"headcount")])')));
        $this->assertStringContainsString('dari 2 RM', $xpath->evaluate('string('.$article.'//dl[contains(@class,"micro-rm-kur-metrics")]/div[3]/dd)'));
        $this->assertStringContainsString('Rp 410 jt', $xpath->evaluate('string('.$article.'//p[@role="note"])'));
        $this->assertStringContainsString('belum masuk sebaran orang', $xpath->evaluate('string('.$article.'//p[@role="note"])'));
    }

    public function test_category_limits_and_source_explanation_are_keyboard_disclosures_and_nominative_values_survive(): void
    {
        $distribution = (new LandingRmKurDistribution)->build([], [
            ['pn' => '0001', 'branch' => 'KC MADIUN', 'branch_code' => '45', 'unit' => 'FUNGSI BISNIS MIKRO'],
        ]);
        $xpath = $this->render([
            'available' => true,
            'distribution' => $distribution,
            'rows' => [[
                'nama' => 'RM <script>alert(1)</script>', 'pn' => '0001', 'cabang' => 'KC MADIUN',
                'branch_code' => '45', 'unit' => 'FUNGSI BISNIS MIKRO',
                'realisasi_deb' => 2, 'realisasi_os' => -2_000_000, 'average_per_debtor' => -1_000_000,
            ]],
            'total' => ['realisasi_deb' => 2, 'realisasi_os' => -2_000_000, 'average_per_debtor' => -1_000_000],
        ]);
        $article = '//*[@data-micro-rm-kur-productivity]';

        $this->assertSame(2, $xpath->query($article.'//details/summary')->length);
        $this->assertSame(0, $xpath->query($article.'//details[@open]')->length);
        $this->assertSame(2, $xpath->query($article.'//*[@role="region"][@tabindex="0"]')->length);
        $this->assertSame(2, $xpath->query($article.'//*[@role="region"][@data-abah-no-table-guard="1"]')->length);
        foreach (LandingRmKurDistribution::TIERS as $tier) {
            $text = $xpath->evaluate('string('.$article.'//details[contains(@class,"explanation")])');
            $this->assertStringContainsString($tier['label'], $text);
            $this->assertStringContainsString($tier['range'], $text);
        }
        $nominatives = $article.'//table[contains(@class,"roster-table")]';
        $this->assertStringContainsString('PN 0001', $xpath->evaluate('string('.$nominatives.'/tbody/tr/th)'));
        $this->assertStringContainsString('RM <script>alert(1)</script>', $xpath->evaluate('string('.$nominatives.'/tbody/tr/th)'));
        $this->assertSame(0, $xpath->query($article.'//script')->length);
        $this->assertSame('-2', trim($xpath->evaluate('string('.$nominatives.'/tbody/tr/td[contains(@class,"amount")])')));
        $this->assertSame('-1', trim($xpath->evaluate('string('.$nominatives.'/tbody/tr/td[last()])')));
    }

    public function test_unavailable_distribution_is_not_rendered_as_a_zero_performance_matrix(): void
    {
        $xpath = $this->render(['available' => false, 'distribution' => (new LandingRmKurDistribution)->build([], [])]);
        $article = '//*[@data-micro-rm-kur-productivity]';

        $this->assertSame(0, $xpath->query($article.'//table')->length);
        $this->assertStringContainsString('data yang belum tersedia tidak dianggap sebagai belum realisasi', $xpath->evaluate('string('.$article.'//*[@role="status"])'));
    }

    private function render(array $payload): DOMXPath
    {
        $html = view('dashboard.partials.micro-performance', ['microPerformance' => [
            'meta' => ['available' => true, 'period' => '2026-11-10'],
            'rm_kur_productivity' => $payload,
        ]])->render();
        $document = new DOMDocument;
        $previousErrors = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);

        return new DOMXPath($document);
    }
}
