<?php

namespace Tests\Unit;

use App\Services\Presentation\NativeOpenXmlPowerPointRenderer;
use DOMDocument;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
use ZipArchive;

class NativeOpenXmlPowerPointRendererTest extends TestCase
{
    public function test_native_renderer_creates_a_thirteen_slide_editable_pptx(): void
    {
        $output = storage_path('framework/testing/native-presentation-test.pptx');
        File::ensureDirectoryExists(dirname($output));
        File::delete($output);

        try {
            $result = app(NativeOpenXmlPowerPointRenderer::class)->render(
                $this->deckFixture(),
                public_path('BRI_Presentation Template.pptx'),
                $output
            );

            $this->assertSame(13, $result['slide_count']);
            $this->assertSame('native-openxml', $result['renderer']);
            $this->assertFileExists($output);
            $this->assertGreaterThan(10000, filesize($output));

            $zip = new ZipArchive();
            $this->assertTrue($zip->open($output) === true);
            try {
                $presentation = (string) $zip->getFromName('ppt/presentation.xml');
                $this->assertSame(13, preg_match_all('#<p:sldId\b#', $presentation));
                $this->assertNotFalse($zip->locateName('ppt/media/presentation-bri.png'));
                $this->assertNotFalse($zip->locateName('ppt/media/presentation-danantara.png'));

                $physicalSlides = [];
                for ($index = 0; $index < $zip->numFiles; $index++) {
                    $entry = (string) $zip->getNameIndex($index);
                    if (preg_match('#^ppt/slides/slide\d+\.xml$#', $entry)) {
                        $physicalSlides[] = $entry;
                    }
                }
                $this->assertCount(13, $physicalSlides);
                $this->assertFalse($zip->locateName('ppt/slides/slide14.xml'));
                $this->assertFalse($zip->locateName('ppt/slides/slide15.xml'));
                $this->assertFalse($zip->locateName('ppt/slides/_rels/slide14.xml.rels'));
                $this->assertFalse($zip->locateName('ppt/slides/_rels/slide15.xml.rels'));

                $presentationRelationships = (string) $zip->getFromName('ppt/_rels/presentation.xml.rels');
                $contentTypes = (string) $zip->getFromName('[Content_Types].xml');
                $this->assertStringNotContainsString('slides/slide14.xml', $presentationRelationships);
                $this->assertStringNotContainsString('slides/slide15.xml', $presentationRelationships);
                $this->assertStringNotContainsString('/ppt/slides/slide14.xml', $contentTypes);
                $this->assertStringNotContainsString('/ppt/slides/slide15.xml', $contentTypes);

                foreach (range(1, 13) as $slideNumber) {
                    $slide = (string) $zip->getFromName("ppt/slides/slide{$slideNumber}.xml");
                    $this->assertNotSame('', $slide);
                    $document = new DOMDocument();
                    $this->assertTrue($document->loadXML($slide), "Slide {$slideNumber} harus berupa XML valid.");
                    $this->assertStringNotContainsString('anchor="mid"', $slide);
                    preg_match_all('/<a:bodyPr\b[^>]*\banchor="([^"]+)"/', $slide, $anchorMatches);
                    foreach ($anchorMatches[1] ?? [] as $anchor) {
                        $this->assertContains(
                            $anchor,
                            ['t', 'ctr', 'b', 'just', 'dist'],
                            "Vertical text anchor slide {$slideNumber} harus sesuai enum DrawingML."
                        );
                    }
                }

                $this->assertStringContainsString('Performance Review - Area 6 Region 13', (string) $zip->getFromName('ppt/slides/slide1.xml'));
                $this->assertStringContainsString('Ikhtisar dan Alur Pembahasan', (string) $zip->getFromName('ppt/slides/slide2.xml'));
                $this->assertStringContainsString('Performance Funding / Dana - Ringkasan Eksekutif', (string) $zip->getFromName('ppt/slides/slide3.xml'));
                $this->assertStringContainsString('Rangkuman 8 Strategi Funding', (string) $zip->getFromName('ppt/slides/slide5.xml'));
                $this->assertStringContainsString('Pinjaman Mikro', (string) $zip->getFromName('ppt/slides/slide9.xml'));
                $this->assertStringContainsString('Prioritas 30 Hari Berikutnya', (string) $zip->getFromName('ppt/slides/slide13.xml'));
            } finally {
                $zip->close();
            }
        } finally {
            File::delete($output);
        }
    }

    public function test_native_renderer_adds_latest_written_prognosa_columns_when_enabled(): void
    {
        $output = storage_path('framework/testing/native-presentation-prognosa-test.pptx');
        File::ensureDirectoryExists(dirname($output));
        File::delete($output);

        $metric = [
            'current' => 14_120_000_000_000.0,
            'current_fmt' => 'Rp14,12 T',
            'deltas' => ['ytd' => 480_000_000_000.0, 'mtd' => -156_000_000_000.0],
            'prognosa_available' => true,
            'prognosa' => 14_144_099_000_000.0,
            'prognosa_fmt' => 'Rp14,14 T',
            'prognosa_delta' => 24_099_000_000.0,
            'prognosa_delta_fmt' => '+Rp24,10 M',
            'rka' => 13_950_000_000_000.0,
            'rka_fmt' => 'Rp13,95 T',
            'gap' => 170_000_000_000.0,
            'gap_fmt' => '+Rp170,00 M',
            'achievement' => 101.22,
            'achievement_fmt' => '101,22%',
        ];
        $deck = $this->deckFixture();
        data_set($deck, 'meta.scope', 'area6');
        data_set($deck, 'meta.use_prognosa', true);
        data_set($deck, 'meta.prognosa', [
            'available' => true,
            'week_label' => 'W4',
            'forecast_date_label' => '25 Jul 26',
            'position_date_label' => '24 Jul 26',
            'comparison_position_label' => '24 Jul 26',
        ]);
        data_set($deck, 'structured.funding', [
            'scope_label' => 'Area 6 Konsolidasi',
            'total' => 'Rp14,12 T',
            'total_raw' => 14_120_000_000_000.0,
            'segments' => [],
            'products' => [],
            'branches' => [],
        ]);
        data_set($deck, 'comparison.scope.funding', [
            'total' => $metric,
            'branches' => [
                ['scope_label' => 'KC Madiun', 'total' => $metric],
            ],
        ]);

        try {
            app(NativeOpenXmlPowerPointRenderer::class)->render(
                $deck,
                public_path('BRI_Presentation Template.pptx'),
                $output
            );

            $zip = new ZipArchive();
            $this->assertTrue($zip->open($output) === true);
            try {
                $slide = (string) $zip->getFromName('ppt/slides/slide3.xml');
                $this->assertStringContainsString('PROGNOSA W4', $slide);
                $this->assertStringContainsString('25 Jul 26', $slide);
                $this->assertStringContainsString('D PROG', $slide);
                $this->assertStringContainsString('24 Jul 26', $slide);
                $this->assertStringContainsString('Rp14,14 T', $slide);
                $this->assertStringContainsString('+Rp24,10 M', $slide);
            } finally {
                $zip->close();
            }
        } finally {
            File::delete($output);
        }
    }

    /** @return array<string, mixed> */
    private function deckFixture(): array
    {
        $section = fn (string $key, string $title): array => [
            'key' => $key,
            'title' => $title,
            'scope_label' => 'Area 6 Konsolidasi',
            'selected_product_label' => 'Total',
            'overview_rows' => [
                $this->performanceRow('AREA 6', 15_000_000_000_000, 100),
                $this->performanceRow('KC Madiun', 4_500_000_000_000, 30),
                $this->performanceRow('KC Magetan', 3_200_000_000_000, 21.33),
                $this->performanceRow('KC Ngawi', 3_300_000_000_000, 22),
                $this->performanceRow('KC Ponorogo', 4_000_000_000_000, 26.67),
            ],
            'product_rows' => [
                array_merge($this->performanceRow('Total', 15_000_000_000_000, 100), [
                    'sml' => ['current' => 900_000_000_000, 'ratio' => 6, 'deltas' => ['mtd' => -10_000_000_000]],
                    'npl' => ['current' => 300_000_000_000, 'ratio' => 2, 'deltas' => ['mtd' => -5_000_000_000]],
                ]),
                array_merge($this->performanceRow('Produk A', 9_000_000_000_000, 60), [
                    'sml' => ['current' => 450_000_000_000, 'ratio' => 5, 'deltas' => ['mtd' => -4_000_000_000]],
                    'npl' => ['current' => 180_000_000_000, 'ratio' => 2, 'deltas' => ['mtd' => 2_000_000_000]],
                ]),
                array_merge($this->performanceRow('Produk B', 6_000_000_000_000, 40), [
                    'sml' => ['current' => 450_000_000_000, 'ratio' => 7.5, 'deltas' => ['mtd' => -6_000_000_000]],
                    'npl' => ['current' => 120_000_000_000, 'ratio' => 2, 'deltas' => ['mtd' => -7_000_000_000]],
                ]),
            ],
            'timeseries' => [
                'labels' => ['Jan 26', 'Feb 26', 'Mar 26', 'Apr 26', 'Mei 26', 'Jun 26'],
                'series' => [[
                    'key' => $key === 'funding' ? 'value' : 'os',
                    'label' => $key === 'funding' ? 'Simpanan' : 'OS',
                    'format' => 'currency',
                    'color' => '#0866D4',
                    'values' => [13_500_000, 13_800_000, 14_100_000, 14_400_000, 14_700_000, 15_000_000],
                    'display_values' => ['Rp13,50 T', 'Rp13,80 T', 'Rp14,10 T', 'Rp14,40 T', 'Rp14,70 T', 'Rp15,00 T'],
                ]],
            ],
        ];

        $productivityCategory = fn (string $key, string $label): array => [
            'key' => $key,
            'label' => $label,
            'available' => true,
            'total' => [
                'rm_count' => 12,
                'realisasi_os_fmt' => 'Rp1,25 T',
                'realisasi_deb' => 350,
                'average_per_rm_fmt' => 'Rp104,17 M',
                'lar_pct_fmt' => '8,20%',
            ],
            'rows' => [[
                'name' => 'RM Utama',
                'unit' => 'Unit A',
                'branch' => 'KC Madiun',
                'realisasi_deb' => 42,
                'realisasi_os_fmt' => 'Rp250,00 M',
                'average_ticket_fmt' => 'Rp5,95 M',
                'lar_pct_fmt' => '4,10%',
            ]],
            'pdwk' => $key === 'micro' ? [
                'roles' => [
                    ['label' => 'K Unit', 'total_os_fmt' => 'Rp120,00 M', 'total_deb' => 24],
                    ['label' => 'MBM', 'total_os_fmt' => 'Rp80,00 M', 'total_deb' => 16],
                    ['label' => 'BOH', 'total_os_fmt' => 'Rp50,00 M', 'total_deb' => 10],
                ],
            ] : [],
        ];

        $trendGroup = fn (string $key, string $label, string $color): array => [
            'key' => $key,
            'label' => $label,
            'description' => 'Pergerakan indikator utama.',
            'scope_label' => 'Area 6 Konsolidasi',
            'labels' => ['Jan 26', 'Feb 26', 'Mar 26', 'Apr 26', 'Mei 26', 'Jun 26'],
            'series' => [[
                'label' => $label,
                'format' => 'percent',
                'color' => $color,
                'values' => [90, 92, 94, 93, 96, 98],
                'display_values' => ['90,00%', '92,00%', '94,00%', '93,00%', '96,00%', '98,00%'],
            ]],
        ];

        return [
            'meta' => [
                'title' => 'Performance Review - Area 6 Region 13',
                'subtitle' => 'Performance Review Area 6',
                'scope_label' => 'Area 6 Konsolidasi',
                'period' => '2026-07-22',
                'period_label' => '22 Juli 2026',
                'generated_at' => '23 Jul 2026 10:00',
                'source_note' => 'Sumber: dashboard dan snapshot terpilih.',
                'bri_logo' => public_path('images/bri-logo-template.png'),
                'danantara_logo' => public_path('images/danantara-logo-template.png'),
            ],
            'agenda' => [
                'Performance Funding / Dana',
                'Performance SME',
                'Performance Konsumer',
                'Timeseries Cabang dan Produk',
                'Produktivitas RM Ritel dan RM Mikro',
                'Integrated Trend Lab',
                '8 Strategi Dana dan Digital',
                'Prioritas Eksekusi',
            ],
            'funding' => $section('funding', 'Performance Funding / Dana'),
            'sme' => $section('sme', 'Performance SME'),
            'consumer' => $section('consumer', 'Performance Konsumer'),
            'productivity' => [
                'scope_label' => 'Area 6 Konsolidasi',
                'categories' => [
                    $productivityCategory('retail_sme', 'Produktivitas RM Ritel - SME'),
                    $productivityCategory('retail_consumer', 'Produktivitas RM Ritel - Konsumer'),
                    $productivityCategory('micro', 'Produktivitas RM Mikro'),
                ],
            ],
            'trend_groups' => [
                $trendGroup('business', 'Business Scale', '#0866D4'),
                $trendGroup('quality', 'Portfolio Quality', '#D73A49'),
                $trendGroup('liquidity', 'Liquidity', '#0D9F77'),
                $trendGroup('profit', 'Profitability', '#6F42C1'),
            ],
            'strategies' => collect(range(1, 8))->map(fn (int $index): array => [
                'key' => "strategy-{$index}",
                'title' => "Strategi {$index}",
                'current_value' => 'Rp100,00 M',
                'secondary_value' => '1.000',
                'trend' => ($index % 3 === 0 ? '-' : '+') . $index . ',00%',
                'source' => 'report_source',
            ])->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function performanceRow(string $label, float $current, float $share): array
    {
        return [
            'label' => $label,
            'current' => $current,
            'share' => $share,
            'deltas' => [
                'ytd' => 200_000_000_000,
                'mom' => 80_000_000_000,
                'mtd' => 40_000_000_000,
            ],
            'rka' => $current * 0.98,
            'gap' => $current * 0.02,
            'achievement' => 102.04,
        ];
    }
}
