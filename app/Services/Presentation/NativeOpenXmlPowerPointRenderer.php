<?php

namespace App\Services\Presentation;

use DOMDocument;
use RuntimeException;
use ZipArchive;

class NativeOpenXmlPowerPointRenderer
{
    private const EMU = 914400;
    private const WIDTH = 20.0;
    private const HEIGHT = 11.25;

    private int $shapeId = 1;

    /**
     * @param array<string, mixed> $deck
     * @return array{slide_count: int, renderer: string}
     */
    public function render(array $deck, string $templatePath, string $outputPath): array
    {
        if (!is_file($templatePath)) {
            throw new RuntimeException('Template PowerPoint BRI tidak ditemukan.');
        }

        if (!copy($templatePath, $outputPath)) {
            throw new RuntimeException('Template PowerPoint tidak dapat disalin ke area ekspor.');
        }

        $slides = $this->buildSlides($deck);
        $zip = new ZipArchive();
        if ($zip->open($outputPath) !== true) {
            throw new RuntimeException('Paket template PowerPoint tidak dapat dibuka.');
        }

        try {
            $this->writeBrandAssets($zip, $deck);

            foreach ($slides as $index => $slideXml) {
                $number = $index + 1;
                $this->put($zip, "ppt/slides/slide{$number}.xml", $slideXml);
                $this->put($zip, "ppt/slides/_rels/slide{$number}.xml.rels", $this->slideRelationships());
            }

            $this->removeUnusedTemplateSlides($zip, count($slides));
            $this->rewritePresentationSlideList($zip, count($slides));
            $this->rewriteDocumentMetadata($zip, $deck, count($slides));
        } finally {
            $zip->close();
        }

        return [
            'slide_count' => count($slides),
            'renderer' => 'native-openxml',
        ];
    }

    /** @param array<string, mixed> $deck */
    private function writeBrandAssets(ZipArchive $zip, array $deck): void
    {
        $assets = [
            'ppt/media/presentation-bri.png' => (string) data_get($deck, 'meta.bri_logo'),
            'ppt/media/presentation-danantara.png' => (string) data_get($deck, 'meta.danantara_logo'),
        ];

        foreach ($assets as $target => $source) {
            if ($source !== '' && is_file($source)) {
                $this->deleteIfPresent($zip, $target);
                if (!$zip->addFile($source, $target)) {
                    throw new RuntimeException("Aset branding gagal ditambahkan: {$source}");
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $deck
     * @return array<int, string>
     */
    private function buildSlides(array $deck): array
    {
        $funding = (array) data_get($deck, 'funding', []);
        $sme = (array) data_get($deck, 'sme', []);
        $consumer = (array) data_get($deck, 'consumer', []);
        $structuredFunding = (array) data_get($deck, 'structured.funding', []);
        $structuredCredit = (array) data_get($deck, 'structured.credit', []);
        $trendGroups = array_values((array) data_get($deck, 'trend_groups', []));

        return [
            $this->coverSlide($deck, 1),
            $this->agendaSlide($deck, 2),
            $structuredFunding !== []
                ? $this->structuredFundingOverviewSlide($deck, $structuredFunding, 3)
                : $this->sectionOverviewSlide($deck, $funding, 3),
            $structuredFunding !== []
                ? $this->structuredFundingBreakdownSlide($deck, $structuredFunding, 'products', 4)
                : $this->sectionProductSlide($deck, $funding, 4),
            $this->strategySlide($deck, 5),
            $this->structuredCreditOverviewSlide($deck, $structuredCredit, 6),
            $this->structuredCreditSegmentSlide($deck, $structuredCredit, 'sme', 7),
            $this->structuredCreditSegmentSlide($deck, $structuredCredit, 'consumer', 8),
            $this->structuredCreditSegmentSlide($deck, $structuredCredit, 'micro', 9),
            $this->structuredQualitySlide($deck, $structuredCredit, 'sml', 10),
            $this->structuredQualitySlide($deck, $structuredCredit, 'npl', 11),
            $this->trendLabSlide($deck, array_slice($trendGroups, 0, 2), 12, 'Timeseries Kinerja Terintegrasi'),
            $this->closingSlide($deck, 13),
        ];
    }

    /** @param array<string, mixed> $deck */
    private function coverSlide(array $deck, int $slideNumber): string
    {
        return $this->slide(function () use ($deck, $slideNumber): string {
            $title = (string) data_get($deck, 'meta.title', 'Performance Review - Area 6 Region 13');
            $scope = (string) data_get($deck, 'meta.scope_label', 'Area 6 Konsolidasi');
            $period = (string) data_get($deck, 'meta.period_label', '-');
            $funding = $this->firstOverview((array) data_get($deck, 'funding', []));
            $sme = $this->firstOverview((array) data_get($deck, 'sme', []));
            $consumer = $this->firstOverview((array) data_get($deck, 'consumer', []));

            $xml = $this->shape(0, 0, self::WIDTH, self::HEIGHT, '032A52', '032A52');
            $xml .= $this->shape(0, 0, 0.22, self::HEIGHT, '16B8F3', '16B8F3');
            $xml .= $this->picture(15.1, 0.55, 2.15, 0.62, 'rId2', 'BRI');
            $xml .= $this->picture(17.55, 0.54, 1.72, 0.64, 'rId3', 'Danantara');
            $xml .= $this->text('PERFORMANCE INTELLIGENCE DECK', 0.8, 1.35, 10.8, 0.4, 15, '69D2FF', true);
            $xml .= $this->text($title, 0.8, 2.0, 13.4, 2.1, 36, 'FFFFFF', true, 'l', 'mid');
            $xml .= $this->text("{$scope}\nPosisi {$period}", 0.85, 4.25, 9.4, 1.15, 19, 'D9E9FA', false);
            $xml .= $this->shape(0.85, 5.75, 18.35, 0.02, '3278C8', '3278C8');

            $cards = [
                ['SIMPANAN', $this->formatAmount($funding['current'] ?? null), 'Dana pihak ketiga'],
                ['OS SME', $this->formatAmount($sme['current'] ?? null), 'Portofolio SME'],
                ['OS KONSUMER', $this->formatAmount($consumer['current'] ?? null), 'Portofolio konsumer'],
                ['CAKUPAN', $scope, 'Pilihan deck aktif'],
            ];
            foreach ($cards as $index => $card) {
                $x = 0.85 + ($index * 4.58);
                $xml .= $this->shape($x, 6.25, 4.28, 2.15, 'FFFFFF', 'FFFFFF', 0.08, 1400);
                $xml .= $this->text((string) $card[0], $x + 0.28, 6.52, 3.65, 0.3, 11, '5E7188', true);
                $xml .= $this->text((string) $card[1], $x + 0.28, 6.93, 3.65, 0.58, 22, '07192E', true);
                $xml .= $this->text((string) $card[2], $x + 0.28, 7.65, 3.65, 0.32, 10.5, '607086');
            }

            $xml .= $this->text(
                'Deck ini menghubungkan pertumbuhan, kualitas, produktivitas, dan agenda eksekusi dalam satu alur keputusan.',
                0.85,
                9.05,
                15.4,
                0.62,
                15,
                'D9E9FA'
            );
            $xml .= $this->text((string) data_get($deck, 'meta.generated_at', ''), 15.8, 10.17, 3.35, 0.3, 9, '94AAC2', false, 'r');
            $xml .= $this->footer($deck, $slideNumber, true);

            return $xml;
        }, '032A52');
    }

    /** @param array<string, mixed> $deck */
    private function agendaSlide(array $deck, int $slideNumber): string
    {
        return $this->slide(function () use ($deck, $slideNumber): string {
            $agenda = array_values((array) data_get($deck, 'agenda', []));
            $xml = $this->header($deck, 'EXECUTIVE STORYLINE', 'Ikhtisar dan Alur Pembahasan', 'Dari posisi bisnis menuju prioritas tindakan.');

            foreach (array_slice($agenda, 0, 11) as $index => $item) {
                $column = $index % 3;
                $row = intdiv($index, 3);
                $x = 0.72 + ($column * 6.28);
                $y = 2.0 + ($row * 1.38);
                $xml .= $this->shape($x, $y, 5.96, 1.12, $row % 2 === 0 ? 'F6F9FD' : 'FFFFFF', 'D9E4F1', 0.04, 800);
                $xml .= $this->shape($x + 0.18, $y + 0.2, 0.7, 0.7, '0866D4', '0866D4', 0.02, 0, 'ellipse');
                $xml .= $this->text((string) ($index + 1), $x + 0.18, $y + 0.2, 0.7, 0.7, 14, 'FFFFFF', true, 'ctr', 'mid');
                $xml .= $this->text((string) $item, $x + 1.02, $y + 0.18, 4.66, 0.76, 12.5, '12233B', true, 'l', 'mid');
            }

            $xml .= $this->callout(
                'PRINSIP PEMBACAAN',
                'Posisi historis, delta, RKA, distribusi, dan timeseries dibaca berurutan. Hijau menunjukkan momentum positif; untuk SML/NPL, penurunan adalah perbaikan kualitas.',
                0.72,
                7.78,
                18.8,
                1.22,
                'EAF4FF',
                '0866D4'
            );
            $xml .= $this->footer($deck, $slideNumber);

            return $xml;
        });
    }

    /**
     * @param array<string, mixed> $deck
     * @param array<string, mixed> $section
     */
    private function sectionOverviewSlide(array $deck, array $section, int $slideNumber): string
    {
        return $this->slide(function () use ($deck, $section, $slideNumber): string {
            $title = (string) ($section['title'] ?? 'Performance');
            $scope = (string) ($section['scope_label'] ?? data_get($deck, 'meta.scope_label', 'Area 6'));
            $rows = array_values((array) ($section['overview_rows'] ?? []));
            $area = (array) ($rows[0] ?? []);
            $isFunding = ($section['key'] ?? '') === 'funding';
            $xml = $this->header($deck, strtoupper((string) ($section['key'] ?? 'PERFORMANCE')), "{$title} - Ringkasan Eksekutif", "{$scope}: posisi, kontribusi, delta, dan pencapaian RKA.");

            $cards = [
                ['POSISI TERBARU', $this->formatAmount($area['current'] ?? null), 'Total scope'],
                ['YTD', $this->formatSignedAmount(data_get($area, 'deltas.ytd')), 'vs akhir tahun'],
                ['MTD', $this->formatSignedAmount(data_get($area, 'deltas.mtd')), 'vs akhir bulan'],
                ['PENC. RKA', $this->formatPercent($area['achievement'] ?? null), $this->formatSignedAmount($area['gap'] ?? null) . ' gap'],
            ];
            foreach ($cards as $index => $card) {
                $xml .= $this->metricCard((string) $card[0], (string) $card[1], (string) $card[2], 0.7 + ($index * 4.72), 1.72, 4.42, 1.12, $index === 3 ? '0D9F77' : '0866D4');
            }

            $tableRows = [];
            foreach (array_slice($rows, 0, 6) as $row) {
                $tableRows[] = [
                    $this->cell((string) ($row['label'] ?? '-'), '12233B', true),
                    $this->cell($this->formatAmount($row['current'] ?? null), '12233B', true, null, 'r'),
                    $this->cell($this->formatPercent($row['share'] ?? null), '52647B', false, null, 'r'),
                    $this->deltaCell(data_get($row, 'deltas.ytd')),
                    $this->deltaCell(data_get($row, 'deltas.mom')),
                    $this->deltaCell(data_get($row, 'deltas.mtd')),
                    $this->cell($this->formatAmount($row['rka'] ?? null), '52647B', false, null, 'r'),
                    $this->achievementCell($row['achievement'] ?? null),
                ];
            }

            $xml .= $this->table(
                ['UNIT/CABANG', 'TERBARU', 'PORSI', 'YTD', 'MTM', 'MTD', 'RKA', 'PENC.'],
                $tableRows,
                0.7,
                3.15,
                13.25,
                6.32,
                [2.1, 1.55, 1.0, 1.35, 1.35, 1.35, 1.4, 1.1]
            );
            $xml .= $this->shape(14.2, 3.15, 5.08, 6.32, 'F4F8FD', 'D4E2F2', 0.04, 900);
            $xml .= $this->text('PEMBACAAN DATA', 14.53, 3.45, 4.4, 0.35, 12, '0866D4', true);
            $xml .= $this->text($this->sectionNarrative($section), 14.53, 3.93, 4.35, 2.25, 15, '132238', true);
            $xml .= $this->callout(
                $isFunding ? 'FOCUS' : 'QUALITY LENS',
                $isFunding
                    ? 'Pertahankan dana murah dan percepat penutupan gap RKA pada cabang dengan momentum MTD negatif.'
                    : 'Pertumbuhan OS harus dibaca bersama SML dan NPL. Prioritas ada pada pertumbuhan sehat, bukan volume saja.',
                14.48,
                6.55,
                4.52,
                2.1,
                $isFunding ? 'EAF7F3' : 'FFF7E8',
                $isFunding ? '0D9F77' : 'E59200'
            );
            $xml .= $this->footer($deck, $slideNumber);

            return $xml;
        });
    }

    /**
     * @param array<string, mixed> $deck
     * @param array<string, mixed> $section
     */
    private function sectionProductSlide(array $deck, array $section, int $slideNumber): string
    {
        return $this->slide(function () use ($deck, $section, $slideNumber): string {
            $title = (string) ($section['title'] ?? 'Performance');
            $isFunding = ($section['key'] ?? '') === 'funding';
            $rows = array_values((array) ($section['product_rows'] ?? []));
            $xml = $this->header(
                $deck,
                'PRODUCT AND QUALITY MIX',
                "{$title} - Detail Produk",
                'Komposisi nominal, momentum, dan kualitas per produk pada scope aktif.'
            );

            $tableRows = [];
            foreach (array_slice($rows, 0, 8) as $row) {
                if ($isFunding) {
                    $tableRows[] = [
                        $this->cell((string) ($row['label'] ?? '-'), '12233B', true),
                        $this->cell($this->formatAmount($row['current'] ?? null), '12233B', true, null, 'r'),
                        $this->cell($this->formatPercent($row['share'] ?? null), '52647B', false, null, 'r'),
                        $this->deltaCell(data_get($row, 'deltas.ytd')),
                        $this->deltaCell(data_get($row, 'deltas.mom')),
                        $this->deltaCell(data_get($row, 'deltas.mtd')),
                    ];
                } else {
                    $tableRows[] = [
                        $this->cell((string) ($row['label'] ?? '-'), '12233B', true),
                        $this->cell($this->formatAmount($row['current'] ?? null), '12233B', true, null, 'r'),
                        $this->cell($this->formatAmount(data_get($row, 'sml.current')), 'B36B00', true, null, 'r'),
                        $this->cell($this->formatPercent(data_get($row, 'sml.ratio')), 'B36B00', false, null, 'r'),
                        $this->cell($this->formatAmount(data_get($row, 'npl.current')), 'C62828', true, null, 'r'),
                        $this->cell($this->formatPercent(data_get($row, 'npl.ratio')), 'C62828', false, null, 'r'),
                        $this->qualityDeltaCell(data_get($row, 'npl.deltas.mtd')),
                    ];
                }
            }

            $headers = $isFunding
                ? ['PRODUK', 'POSISI', 'PORSI', 'YTD', 'MTM', 'MTD']
                : ['PRODUK', 'OS', 'SML', 'RATIO', 'NPL', 'RATIO', 'NPL MTD'];
            $weights = $isFunding ? [2.3, 1.65, 1.1, 1.45, 1.45, 1.45] : [2.05, 1.5, 1.5, 1.0, 1.5, 1.0, 1.25];
            $xml .= $this->table($headers, $tableRows, 0.7, 2.05, 12.55, 7.35, $weights);

            $xml .= $this->shape(13.5, 2.05, 5.78, 7.35, 'F7FAFE', 'D4E2F2', 0.04, 900);
            $xml .= $this->text('KOMPOSISI PORTOFOLIO', 13.82, 2.35, 5.15, 0.35, 12, '0866D4', true);
            $positiveRows = array_values(array_filter($rows, fn (array $row): bool => (float) ($row['current'] ?? 0) > 0));
            $maximum = max(array_map(fn (array $row): float => (float) ($row['current'] ?? 0), $positiveRows) ?: [1]);
            foreach (array_slice($positiveRows, 0, 6) as $index => $row) {
                $y = 2.92 + ($index * 0.86);
                $ratio = max(0.02, min(1, (float) ($row['current'] ?? 0) / $maximum));
                $xml .= $this->text((string) ($row['label'] ?? '-'), 13.82, $y, 2.65, 0.28, 10.5, '30445E', true);
                $xml .= $this->text($this->formatAmount($row['current'] ?? null), 16.7, $y, 2.2, 0.28, 10.5, '12233B', true, 'r');
                $xml .= $this->shape(13.82, $y + 0.37, 5.06, 0.12, 'DFE8F4', 'DFE8F4', 0.01, 0);
                $xml .= $this->shape(13.82, $y + 0.37, 5.06 * $ratio, 0.12, $isFunding ? '16A3C7' : '0866D4', $isFunding ? '16A3C7' : '0866D4', 0.01, 0);
            }
            $xml .= $this->callout(
                'EXECUTIVE READOUT',
                $this->productNarrative($section),
                13.78,
                8.2,
                5.15,
                0.86,
                'EAF4FF',
                '0866D4'
            );
            $xml .= $this->footer($deck, $slideNumber);

            return $xml;
        });
    }

    /**
     * @param array<string, mixed> $deck
     * @param array<string, mixed> $section
     */
    private function sectionTrendSlide(array $deck, array $section, int $slideNumber): string
    {
        return $this->slide(function () use ($deck, $section, $slideNumber): string {
            $timeseries = (array) ($section['timeseries'] ?? []);
            $labels = array_values((array) ($timeseries['labels'] ?? []));
            $series = array_values((array) ($timeseries['series'] ?? []));
            $title = (string) ($section['title'] ?? 'Performance');
            $scope = (string) ($section['scope_label'] ?? data_get($deck, 'meta.scope_label', 'Area 6'));
            $product = (string) ($section['selected_product_label'] ?? 'Total');
            $xml = $this->header($deck, 'TIMESERIES', "{$title} - Tren {$product}", "{$scope}: posisi bulanan, level nominal, dan perubahan terbaru.");
            $xml .= $this->lineChart($labels, $series, 0.72, 2.0, 13.0, 6.85);
            $xml .= $this->shape(14.02, 2.0, 5.26, 6.85, 'F7FAFE', 'D4E2F2', 0.04, 900);
            $xml .= $this->text('TREND INTERPRETATION', 14.35, 2.33, 4.55, 0.34, 12, '0866D4', true);

            foreach (array_slice($series, 0, 3) as $index => $item) {
                $values = array_values((array) ($item['values'] ?? []));
                $first = $values !== [] ? (float) $values[0] : null;
                $last = $values !== [] ? (float) end($values) : null;
                $delta = $first !== null && $first != 0.0 && $last !== null ? (($last / $first) - 1) * 100 : null;
                $displayValues = array_values((array) ($item['display_values'] ?? []));
                $display = $displayValues !== [] ? (string) end($displayValues) : $this->formatSeriesValue($item, $last);
                $color = ltrim((string) ($item['color'] ?? ['0866D4', '0D9F77', 'E59200'][$index]), '#');
                $xml .= $this->shape(14.35, 2.95 + ($index * 1.38), 4.58, 1.12, 'FFFFFF', 'D9E4F1', 0.03, 700);
                $xml .= $this->shape(14.35, 2.95 + ($index * 1.38), 0.08, 1.12, $color, $color, 0, 0);
                $xml .= $this->text((string) ($item['label'] ?? '-'), 14.63, 3.12 + ($index * 1.38), 2.6, 0.28, 10.5, '566A83', true);
                $xml .= $this->text($display, 14.63, 3.46 + ($index * 1.38), 2.6, 0.33, 16, '12233B', true);
                $xml .= $this->text($this->formatSignedPercent($delta), 17.33, 3.38 + ($index * 1.38), 1.3, 0.35, 11.5, $delta !== null && $delta < 0 ? 'C62828' : '078A68', true, 'r');
            }

            $xml .= $this->callout(
                'ACTION',
                $this->trendNarrative($labels, $series),
                14.35,
                7.25,
                4.58,
                1.15,
                'EAF4FF',
                '0866D4'
            );
            $xml .= $this->footer($deck, $slideNumber);

            return $xml;
        });
    }

    /**
     * @param array<string, mixed> $deck
     * @param array<string, mixed> $funding
     */
    private function structuredFundingOverviewSlide(array $deck, array $funding, int $slideNumber): string
    {
        return $this->slide(function () use ($deck, $funding, $slideNumber): string {
            $scope = (string) ($funding['scope_label'] ?? data_get($deck, 'meta.scope_label', 'Area 6'));
            $totalRaw = (float) ($funding['total_raw'] ?? 0);
            $segments = array_values((array) ($funding['segments'] ?? []));
            $branches = array_values((array) ($funding['branches'] ?? []));
            $ritel = $this->keyedItem($segments, 'retail');
            $wholesale = $this->keyedItem($segments, 'wholesale');
            $micro = $this->keyedItem($segments, 'micro');
            $xml = $this->header(
                $deck,
                '1. FUNDING | TOTAL',
                "Funding {$scope}",
                'Posisi total, struktur sumber dana, dan kontribusi setiap cabang.'
            );

            $cards = [
                ['TOTAL FUNDING', (string) ($funding['total'] ?? $this->formatAmount($totalRaw)), $scope, '0866D4'],
                ['RITEL', (string) ($ritel['value'] ?? $this->formatAmount($ritel['value_raw'] ?? null)), $this->formatPercent($ritel['share'] ?? null), '0866D4'],
                ['WHOLESALE', (string) ($wholesale['value'] ?? $this->formatAmount($wholesale['value_raw'] ?? null)), $this->formatPercent($wholesale['share'] ?? null), 'B4937B'],
                ['MIKRO', (string) ($micro['value'] ?? $this->formatAmount($micro['value_raw'] ?? null)), $this->formatPercent($micro['share'] ?? null), '16A3C7'],
            ];
            foreach ($cards as $index => $card) {
                $xml .= $this->metricCard((string) $card[0], (string) $card[1], (string) $card[2], 0.7 + ($index * 4.72), 1.72, 4.42, 1.08, (string) $card[3]);
            }

            $rows = [];
            if ($this->usesPrognosa($deck)) {
                $comparisonFunding = (array) data_get($deck, 'comparison.scope.funding', []);
                $comparisonRows = [[
                    'label' => $scope,
                    'metric' => (array) data_get($comparisonFunding, 'total', []),
                ]];
                $sourceRows = (string) data_get($deck, 'meta.scope', 'area6') === 'area6'
                    ? (array) data_get($comparisonFunding, 'branches', [])
                    : (array) data_get($comparisonFunding, 'segments', []);
                foreach ($sourceRows as $sourceRow) {
                    $comparisonRows[] = [
                        'label' => (string) ($sourceRow['scope_label'] ?? $sourceRow['label'] ?? '-'),
                        'metric' => (array) ($sourceRow['total'] ?? $sourceRow),
                    ];
                }
                foreach ($comparisonRows as $comparisonRow) {
                    $rows[] = $this->nativeComparisonRow(
                        (string) $comparisonRow['label'],
                        (array) $comparisonRow['metric']
                    );
                }
                $xml .= $this->nativePrognosaTable($deck, $rows, 0.7, 3.08, 18.58, 4.08);
            } else {
                foreach ($branches as $branch) {
                    $branchSegments = array_values((array) ($branch['segments'] ?? []));
                    $branchRitel = $this->keyedItem($branchSegments, 'retail');
                    $branchWholesale = $this->keyedItem($branchSegments, 'wholesale');
                    $branchMicro = $this->keyedItem($branchSegments, 'micro');
                    $branchTotal = (float) ($branch['total_raw'] ?? 0);
                    $rows[] = [
                        $this->cell((string) ($branch['scope_label'] ?? '-'), '12233B', true),
                        $this->cell((string) ($branch['total'] ?? $this->formatAmount($branchTotal)), '12233B', true, null, 'r'),
                        $this->cell($this->formatPercent($totalRaw !== 0.0 ? ($branchTotal / $totalRaw) * 100 : null), '52647B', false, null, 'r'),
                        $this->cell((string) ($branchRitel['value'] ?? $this->formatAmount($branchRitel['value_raw'] ?? null)), '52647B', false, null, 'r'),
                        $this->cell((string) ($branchWholesale['value'] ?? $this->formatAmount($branchWholesale['value_raw'] ?? null)), '52647B', false, null, 'r'),
                        $this->cell((string) ($branchMicro['value'] ?? $this->formatAmount($branchMicro['value_raw'] ?? null)), '52647B', false, null, 'r'),
                    ];
                }
                if ($rows === []) {
                    $rows[] = [
                        $this->cell($scope, '12233B', true),
                        $this->cell((string) ($funding['total'] ?? $this->formatAmount($totalRaw)), '12233B', true, null, 'r'),
                        $this->cell('100,00%', '52647B', false, null, 'r'),
                        $this->cell((string) ($ritel['value'] ?? '-'), '52647B', false, null, 'r'),
                        $this->cell((string) ($wholesale['value'] ?? '-'), '52647B', false, null, 'r'),
                        $this->cell((string) ($micro['value'] ?? '-'), '52647B', false, null, 'r'),
                    ];
                }

                $xml .= $this->table(
                    ['CABANG / SCOPE', 'FUNDING', 'PORSI', 'RITEL', 'WHOLESALE', 'MIKRO'],
                    $rows,
                    0.7,
                    3.08,
                    18.58,
                    4.08,
                    [2.0, 1.4, 0.9, 1.3, 1.3, 1.3]
                );
            }
            $leader = $this->largestRow($branches, 'total_raw');
            $xml .= $this->callout(
                'KONTRIBUTOR UTAMA',
                $leader !== []
                    ? (string) ($leader['scope_label'] ?? '-') . ' menyumbang ' . (string) ($leader['total'] ?? $this->formatAmount($leader['total_raw'] ?? null)) . '.'
                    : "{$scope} merupakan scope tunggal pada deck ini.",
                0.7,
                7.42,
                9.05,
                1.85,
                'EAF4FF',
                '0866D4'
            );
            $xml .= $this->callout(
                'ARAH PENGELOLAAN',
                'Pertahankan sumber dana dominan, pulihkan komponen yang melemah, dan jaga keseimbangan dana murah dengan dana berjangka.',
                9.98,
                7.42,
                9.3,
                1.85,
                'EAF7F3',
                '0D9F77'
            );
            $xml .= $this->footer($deck, $slideNumber);

            return $xml;
        });
    }

    /**
     * @param array<string, mixed> $deck
     * @param array<string, mixed> $funding
     */
    private function structuredFundingBreakdownSlide(array $deck, array $funding, string $groupKey, int $slideNumber): string
    {
        return $this->slide(function () use ($deck, $funding, $groupKey, $slideNumber): string {
            $isSegment = $groupKey === 'segments';
            $items = array_values((array) ($funding[$groupKey] ?? []));
            $branches = array_values((array) ($funding['branches'] ?? []));
            $scope = (string) ($funding['scope_label'] ?? data_get($deck, 'meta.scope_label', 'Area 6'));
            $title = $isSegment ? 'Funding per Segmen' : 'Funding per Produk';
            $xml = $this->header(
                $deck,
                $isSegment ? '1. FUNDING | SEGMEN' : '1. FUNDING | PRODUK',
                $title,
                $isSegment
                    ? "{$scope}: Ritel, Wholesale, dan Mikro beserta kontribusi cabang."
                    : "{$scope}: Giro, Tabungan, dan Deposito beserta kontribusi cabang."
            );

            foreach (array_slice($items, 0, 3) as $index => $item) {
                $xml .= $this->metricCard(
                    strtoupper((string) ($item['label'] ?? '-')),
                    (string) ($item['value'] ?? $this->formatAmount($item['value_raw'] ?? null)),
                    $this->formatPercent($item['share'] ?? null) . ' dari total',
                    0.7 + ($index * 6.25),
                    1.72,
                    5.95,
                    1.08,
                    ['0866D4', '16A3C7', 'B4937B'][$index] ?? '0866D4'
                );
            }

            $headers = ['CABANG / SCOPE'];
            foreach (array_slice($items, 0, 3) as $item) {
                $headers[] = strtoupper((string) ($item['label'] ?? '-'));
            }
            $headers[] = 'TOTAL';
            $rows = [];
            foreach ($branches as $branch) {
                $branchItems = array_values((array) ($branch[$groupKey] ?? []));
                $row = [$this->cell((string) ($branch['scope_label'] ?? '-'), '12233B', true)];
                foreach (array_slice($items, 0, 3) as $item) {
                    $branchItem = $this->keyedItem($branchItems, (string) ($item['key'] ?? ''));
                    $row[] = $this->cell(
                        (string) ($branchItem['value'] ?? $this->formatAmount($branchItem['value_raw'] ?? null)),
                        '344A65',
                        true,
                        null,
                        'r'
                    );
                }
                $row[] = $this->cell((string) ($branch['total'] ?? $this->formatAmount($branch['total_raw'] ?? null)), '12233B', true, null, 'r');
                $rows[] = $row;
            }
            if ($rows === []) {
                $row = [$this->cell($scope, '12233B', true)];
                foreach (array_slice($items, 0, 3) as $item) {
                    $row[] = $this->cell((string) ($item['value'] ?? '-'), '344A65', true, null, 'r');
                }
                $row[] = $this->cell((string) ($funding['total'] ?? '-'), '12233B', true, null, 'r');
                $rows[] = $row;
            }
            $xml .= $this->table($headers, $rows, 0.7, 3.08, 18.58, 4.08, [1.8, 1.35, 1.35, 1.35, 1.35]);

            $leader = $this->largestRow($items, 'value_raw');
            $xml .= $this->callout(
                'KOMPONEN DOMINAN',
                $leader !== []
                    ? (string) ($leader['label'] ?? '-') . ' berada pada ' . (string) ($leader['value'] ?? $this->formatAmount($leader['value_raw'] ?? null)) . ' atau ' . $this->formatPercent($leader['share'] ?? null) . '.'
                    : 'Komponen dominan belum dapat ditentukan.',
                0.7,
                7.42,
                9.05,
                1.85,
                'EAF4FF',
                '0866D4'
            );
            $xml .= $this->callout(
                'PEMBACAAN CABANG',
                'Bandingkan struktur setiap cabang terhadap total scope untuk menemukan konsentrasi, ruang pertumbuhan, dan risiko biaya dana.',
                9.98,
                7.42,
                9.3,
                1.85,
                'EAF7F3',
                '0D9F77'
            );
            $xml .= $this->footer($deck, $slideNumber);

            return $xml;
        });
    }

    /**
     * @param array<string, mixed> $deck
     * @param array<string, mixed> $credit
     */
    private function structuredCreditOverviewSlide(array $deck, array $credit, int $slideNumber): string
    {
        return $this->slide(function () use ($deck, $credit, $slideNumber): string {
            $scope = (string) ($credit['scope_label'] ?? data_get($deck, 'meta.scope_label', 'Area 6'));
            $total = (array) ($credit['total'] ?? []);
            $branches = array_values((array) ($credit['branches'] ?? []));
            $healthy = max(0.0, 100 - (float) ($total['sml_ratio_raw'] ?? 0) - (float) ($total['npl_ratio_raw'] ?? 0));
            $xml = $this->header(
                $deck,
                '2. PINJAMAN | TOTAL',
                "Pinjaman {$scope}",
                'Outstanding total dibaca bersama kualitas SML dan NPL pada scope aktif.'
            );
            $cards = [
                ['TOTAL OS', (string) ($total['os'] ?? $this->formatAmount($total['os_raw'] ?? null)), 'Outstanding posisi', '0866D4'],
                ['LANCAR', $this->formatPercent($healthy), 'Kol 1 terhadap OS', '0D9F77'],
                ['SML', (string) ($total['sml'] ?? $this->formatAmount($total['sml_raw'] ?? null)), (string) ($total['sml_ratio'] ?? '-'), 'E59200'],
                ['NPL', (string) ($total['npl'] ?? $this->formatAmount($total['npl_raw'] ?? null)), (string) ($total['npl_ratio'] ?? '-'), 'D73A49'],
            ];
            foreach ($cards as $index => $card) {
                $xml .= $this->metricCard((string) $card[0], (string) $card[1], (string) $card[2], 0.7 + ($index * 4.72), 1.72, 4.42, 1.08, (string) $card[3]);
            }

            $rows = [];
            if ($this->usesPrognosa($deck)) {
                $comparisonCredit = (array) data_get($deck, 'comparison.scope.credit', []);
                $comparisonRows = [[
                    'label' => $scope,
                    'metric' => (array) data_get($comparisonCredit, 'total.os', []),
                ]];
                $sourceRows = (string) data_get($deck, 'meta.scope', 'area6') === 'area6'
                    ? (array) data_get($comparisonCredit, 'branches', [])
                    : (array) data_get($comparisonCredit, 'segments', []);
                foreach ($sourceRows as $sourceRow) {
                    $comparisonRows[] = [
                        'label' => (string) ($sourceRow['scope_label'] ?? $sourceRow['label'] ?? '-'),
                        'metric' => (array) data_get($sourceRow, 'total.os', data_get($sourceRow, 'os', [])),
                    ];
                }
                foreach ($comparisonRows as $comparisonRow) {
                    $rows[] = $this->nativeComparisonRow(
                        (string) $comparisonRow['label'],
                        (array) $comparisonRow['metric']
                    );
                }
                $xml .= $this->nativePrognosaTable($deck, $rows, 0.7, 3.08, 18.58, 4.08);
            } else {
                foreach ($branches as $branch) {
                    $branchTotal = (array) ($branch['total'] ?? []);
                    $rows[] = [
                        $this->cell((string) ($branch['scope_label'] ?? '-'), '12233B', true),
                        $this->cell((string) ($branchTotal['os'] ?? $this->formatAmount($branchTotal['os_raw'] ?? null)), '12233B', true, null, 'r'),
                        $this->cell((string) ($branchTotal['sml'] ?? $this->formatAmount($branchTotal['sml_raw'] ?? null)), 'B36B00', true, null, 'r'),
                        $this->cell((string) ($branchTotal['sml_ratio'] ?? '-'), 'B36B00', false, null, 'r'),
                        $this->cell((string) ($branchTotal['npl'] ?? $this->formatAmount($branchTotal['npl_raw'] ?? null)), 'C62828', true, null, 'r'),
                        $this->cell((string) ($branchTotal['npl_ratio'] ?? '-'), 'C62828', false, null, 'r'),
                    ];
                }
                if ($rows === []) {
                    $rows[] = [
                        $this->cell($scope, '12233B', true),
                        $this->cell((string) ($total['os'] ?? '-'), '12233B', true, null, 'r'),
                        $this->cell((string) ($total['sml'] ?? '-'), 'B36B00', true, null, 'r'),
                        $this->cell((string) ($total['sml_ratio'] ?? '-'), 'B36B00', false, null, 'r'),
                        $this->cell((string) ($total['npl'] ?? '-'), 'C62828', true, null, 'r'),
                        $this->cell((string) ($total['npl_ratio'] ?? '-'), 'C62828', false, null, 'r'),
                    ];
                }
                $xml .= $this->table(
                    ['CABANG / SCOPE', 'OS', 'SML', 'RASIO', 'NPL', 'RASIO'],
                    $rows,
                    0.7,
                    3.08,
                    18.58,
                    4.08,
                    [2.0, 1.45, 1.35, 0.9, 1.35, 0.9]
                );
            }
            $risk = (float) ($total['sml_raw'] ?? 0) + (float) ($total['npl_raw'] ?? 0);
            $xml .= $this->callout(
                'PROTECT',
                'Pertahankan portofolio lancar ' . $this->formatPercent($healthy) . ' melalui monitoring disiplin bayar dan kualitas akuisisi.',
                0.7,
                7.42,
                6.0,
                1.85,
                'EAF7F3',
                '0D9F77'
            );
            $xml .= $this->callout(
                'MIGRATE',
                'SML ' . (string) ($total['sml'] ?? '-') . ' menjadi basis curing sebelum turun menjadi NPL.',
                6.92,
                7.42,
                6.0,
                1.85,
                'FFF7E8',
                'E59200'
            );
            $xml .= $this->callout(
                'RECOVER',
                'Total at risk ' . $this->formatAmount($risk) . '; recovery diprioritaskan pada nominal dan rasio tertinggi.',
                13.14,
                7.42,
                6.14,
                1.85,
                'FFF0F0',
                'D73A49'
            );
            $xml .= $this->footer($deck, $slideNumber);

            return $xml;
        });
    }

    /**
     * @param array<string, mixed> $deck
     * @param array<string, mixed> $credit
     */
    private function structuredCreditSegmentSlide(array $deck, array $credit, string $segmentKey, int $slideNumber): string
    {
        return $this->slide(function () use ($deck, $credit, $segmentKey, $slideNumber): string {
            $segment = $this->keyedItem(array_values((array) ($credit['segments'] ?? [])), $segmentKey);
            $products = array_values(array_filter(
                (array) ($segment['products'] ?? []),
                fn (array $row): bool => (float) ($row['os_raw'] ?? 0) !== 0.0
                    || (float) ($row['sml_raw'] ?? 0) !== 0.0
                    || (float) ($row['npl_raw'] ?? 0) !== 0.0
            ));
            $branches = [];
            foreach ((array) ($credit['branches'] ?? []) as $branch) {
                $branchSegment = $this->keyedItem(array_values((array) ($branch['segments'] ?? [])), $segmentKey);
                if ($branchSegment !== []) {
                    $branchSegment['scope_label'] = (string) ($branch['scope_label'] ?? '-');
                    $branches[] = $branchSegment;
                }
            }
            $label = (string) ($segment['label'] ?? match ($segmentKey) {
                'sme' => 'SME',
                'consumer' => 'Konsumer',
                default => 'Mikro',
            });
            $xml = $this->header(
                $deck,
                "2. PINJAMAN | " . strtoupper($label),
                "Pinjaman {$label}",
                $this->segmentSubtitle($segmentKey)
            );

            $cards = [
                ["TOTAL OS {$label}", (string) ($segment['os'] ?? $this->formatAmount($segment['os_raw'] ?? null)), count($products) . ' produk aktif', '0866D4'],
                ['SML', (string) ($segment['sml'] ?? $this->formatAmount($segment['sml_raw'] ?? null)), (string) ($segment['sml_ratio'] ?? '-'), 'E59200'],
                ['NPL', (string) ($segment['npl'] ?? $this->formatAmount($segment['npl_raw'] ?? null)), (string) ($segment['npl_ratio'] ?? '-'), 'D73A49'],
                ['CABANG', $this->integer(count($branches)), 'baris pembanding', '16A3C7'],
            ];
            foreach ($cards as $index => $card) {
                $xml .= $this->metricCard((string) $card[0], (string) $card[1], (string) $card[2], 0.7 + ($index * 4.72), 1.72, 4.42, 1.08, (string) $card[3]);
            }

            $productRows = [];
            $comparisonSegment = $this->keyedItem(
                array_values((array) data_get($deck, 'comparison.scope.credit.segments', [])),
                $segmentKey
            );
            if ($this->usesPrognosa($deck) && $comparisonSegment !== []) {
                $comparisonRows = [[
                    'label' => 'TOTAL ' . strtoupper($label),
                    'metric' => (array) data_get($comparisonSegment, 'os', []),
                ]];
                foreach ((array) data_get($comparisonSegment, 'products', []) as $comparisonProduct) {
                    $comparisonRows[] = [
                        'label' => (string) ($comparisonProduct['label'] ?? '-'),
                        'metric' => (array) data_get($comparisonProduct, 'os', []),
                    ];
                }
                foreach ($comparisonRows as $comparisonRow) {
                    $productRows[] = $this->nativeComparisonRow(
                        (string) $comparisonRow['label'],
                        (array) $comparisonRow['metric']
                    );
                }
            } else {
                foreach ($products as $product) {
                    $productRows[] = [
                        $this->cell((string) ($product['label'] ?? '-'), '12233B', true),
                        $this->cell((string) ($product['os'] ?? $this->formatAmount($product['os_raw'] ?? null)), '12233B', true, null, 'r'),
                        $this->cell((string) ($product['sml'] ?? $this->formatAmount($product['sml_raw'] ?? null)), 'B36B00', true, null, 'r'),
                        $this->cell((string) ($product['sml_ratio'] ?? '-'), 'B36B00', false, null, 'r'),
                        $this->cell((string) ($product['npl'] ?? $this->formatAmount($product['npl_raw'] ?? null)), 'C62828', true, null, 'r'),
                        $this->cell((string) ($product['npl_ratio'] ?? '-'), 'C62828', false, null, 'r'),
                    ];
                }
                if ($productRows === []) {
                    $productRows[] = [
                        $this->cell('Rincian produk belum tersedia', '52647B', true),
                        $this->cell('-', '52647B'),
                        $this->cell('-', '52647B'),
                        $this->cell('-', '52647B'),
                        $this->cell('-', '52647B'),
                        $this->cell('-', '52647B'),
                    ];
                }
            }

            $branchRows = [];
            foreach ($branches as $branch) {
                $branchRows[] = [
                    $this->cell((string) ($branch['scope_label'] ?? '-'), '12233B', true),
                    $this->cell((string) ($branch['os'] ?? $this->formatAmount($branch['os_raw'] ?? null)), '12233B', true, null, 'r'),
                    $this->cell((string) ($branch['sml_ratio'] ?? '-'), 'B36B00', true, null, 'r'),
                    $this->cell((string) ($branch['npl_ratio'] ?? '-'), 'C62828', true, null, 'r'),
                ];
            }
            if ($branchRows === []) {
                $branchRows[] = [
                    $this->cell((string) ($credit['scope_label'] ?? '-'), '12233B', true),
                    $this->cell((string) ($segment['os'] ?? '-'), '12233B', true, null, 'r'),
                    $this->cell((string) ($segment['sml_ratio'] ?? '-'), 'B36B00', true, null, 'r'),
                    $this->cell((string) ($segment['npl_ratio'] ?? '-'), 'C62828', true, null, 'r'),
                ];
            }

            if ($this->usesPrognosa($deck) && $comparisonSegment !== []) {
                $xml .= $this->nativePrognosaTable($deck, $productRows, 0.7, 3.08, 12.0, 4.95, true);
            } else {
                $xml .= $this->table(
                    ['PRODUK', 'OS', 'SML', 'RASIO', 'NPL', 'RASIO'],
                    $productRows,
                    0.7,
                    3.08,
                    9.05,
                    4.95,
                    [2.0, 1.4, 1.35, 0.9, 1.35, 0.9]
                );
            }
            $xml .= $this->table(
                ['CABANG / SCOPE', 'OS', 'SML %', 'NPL %'],
                $branchRows,
                $this->usesPrognosa($deck) && $comparisonSegment !== [] ? 12.92 : 9.98,
                3.08,
                $this->usesPrognosa($deck) && $comparisonSegment !== [] ? 6.36 : 9.3,
                4.95,
                [2.0, 1.35, 0.9, 0.9]
            );
            if ($products !== [] && count($products) <= 2) {
                foreach ($products as $productIndex => $product) {
                    $productBranches = [];
                    foreach ($branches as $branch) {
                        $branchProduct = $this->keyedItem(
                            array_values((array) ($branch['products'] ?? [])),
                            (string) ($product['key'] ?? '')
                        );
                        if ((float) ($branchProduct['os_raw'] ?? 0) <= 0.0) {
                            continue;
                        }
                        $branchProduct['scope_label'] = (string) ($branch['scope_label'] ?? '-');
                        $productBranches[] = $branchProduct;
                    }
                    $branchLeader = $this->largestRow($productBranches, 'os_raw');
                    $xml .= $this->callout(
                        'DISTRIBUSI ' . strtoupper((string) ($product['label'] ?? 'PRODUK')),
                        $branchLeader !== []
                            ? (string) ($branchLeader['scope_label'] ?? '-')
                                . ' memimpin ' . (string) ($branchLeader['os'] ?? $this->formatAmount($branchLeader['os_raw'] ?? null))
                                . '; SML ' . (string) ($product['sml_ratio'] ?? '-')
                                . ' dan NPL ' . (string) ($product['npl_ratio'] ?? '-') . '.'
                            : 'Distribusi cabang mengikuti snapshot scope aktif.',
                        0.92,
                        5.45 + ($productIndex * 1.12),
                        8.61,
                        0.96,
                        $productIndex === 0 ? 'EAF7F3' : 'EDF8FC',
                        $productIndex === 0 ? '0D9F77' : '16A3C7'
                    );
                }
            }
            $leader = $this->largestRow($products, 'os_raw');
            $xml .= $this->callout(
                'PEMBACAAN PRODUK',
                $leader !== []
                    ? (string) ($leader['label'] ?? '-') . ' menjadi kontributor terbesar sebesar ' . (string) ($leader['os'] ?? $this->formatAmount($leader['os_raw'] ?? null)) . '.'
                    : 'Rincian produk mengikuti struktur snapshot aktif.',
                0.7,
                8.28,
                9.05,
                1.05,
                'EAF4FF',
                '0866D4'
            );
            $xml .= $this->callout(
                'FOKUS KUALITAS',
                'SML ' . (string) ($segment['sml_ratio'] ?? '-')
                    . ' dan NPL ' . (string) ($segment['npl_ratio'] ?? '-')
                    . '; prioritaskan cabang dengan kombinasi nominal dan rasio tertinggi.',
                9.98,
                8.28,
                9.3,
                1.05,
                'FFF7E8',
                'E59200'
            );
            $xml .= $this->footer($deck, $slideNumber);

            return $xml;
        });
    }

    /**
     * @param array<string, mixed> $deck
     * @param array<string, mixed> $credit
     */
    private function structuredQualitySlide(array $deck, array $credit, string $metricKey, int $slideNumber): string
    {
        return $this->slide(function () use ($deck, $credit, $metricKey, $slideNumber): string {
            $isSml = $metricKey === 'sml';
            $title = $isSml ? 'Kualitas SML' : 'Kualitas NPL';
            $total = (array) ($credit['total'] ?? []);
            $nominalKey = "{$metricKey}_raw";
            $formattedKey = $metricKey;
            $ratioKey = "{$metricKey}_ratio";
            $branches = [];
            foreach ((array) ($credit['branches'] ?? []) as $branch) {
                $branchTotal = (array) ($branch['total'] ?? []);
                $branches[] = [
                    'label' => (string) ($branch['scope_label'] ?? '-'),
                    'raw' => (float) ($branchTotal[$nominalKey] ?? 0),
                    'value' => (string) ($branchTotal[$formattedKey] ?? $this->formatAmount($branchTotal[$nominalKey] ?? null)),
                    'ratio' => (string) ($branchTotal[$ratioKey] ?? '-'),
                ];
            }
            usort($branches, fn (array $a, array $b): int => $b['raw'] <=> $a['raw']);

            $products = [];
            foreach ((array) ($credit['segments'] ?? []) as $segment) {
                foreach ((array) ($segment['products'] ?? []) as $product) {
                    $raw = (float) ($product[$nominalKey] ?? 0);
                    if ($raw <= 0.0) {
                        continue;
                    }
                    $products[] = [
                        'label' => (string) ($product['label'] ?? '-'),
                        'segment' => (string) ($segment['label'] ?? '-'),
                        'raw' => $raw,
                        'value' => (string) ($product[$formattedKey] ?? $this->formatAmount($raw)),
                        'ratio' => (string) ($product[$ratioKey] ?? '-'),
                    ];
                }
            }
            usort($products, fn (array $a, array $b): int => $b['raw'] <=> $a['raw']);
            $topBranch = (array) ($branches[0] ?? []);
            $topProduct = (array) ($products[0] ?? []);
            $tone = $isSml ? 'E59200' : 'D73A49';
            $xml = $this->header(
                $deck,
                $isSml ? '3. KUALITAS | SML' : '3. KUALITAS | NPL',
                $title,
                $isSml
                    ? 'SML dibaca lebih dahulu sebagai early warning sebelum memburuk menjadi NPL.'
                    : 'NPL dibaca setelah SML untuk menentukan prioritas recovery.'
            );
            $cards = [
                ["TOTAL {$metricKey}", (string) ($total[$formattedKey] ?? $this->formatAmount($total[$nominalKey] ?? null)), (string) ($total[$ratioKey] ?? '-'), $tone],
                ['CABANG TERTINGGI', (string) ($topBranch['label'] ?? '-'), (string) ($topBranch['value'] ?? '-'), $tone],
                ['PRODUK TERTINGGI', (string) ($topProduct['label'] ?? '-'), (string) ($topProduct['value'] ?? '-'), $tone],
                ['TOTAL OS', (string) ($total['os'] ?? $this->formatAmount($total['os_raw'] ?? null)), 'basis rasio kualitas', '0866D4'],
            ];
            foreach ($cards as $index => $card) {
                $xml .= $this->metricCard((string) $card[0], (string) $card[1], (string) $card[2], 0.7 + ($index * 4.72), 1.72, 4.42, 1.08, (string) $card[3]);
            }

            $branchRows = [];
            if ($this->usesPrognosa($deck)) {
                $comparisonCredit = (array) data_get($deck, 'comparison.scope.credit', []);
                $comparisonRows = [[
                    'label' => (string) data_get($deck, 'meta.scope_label', 'Area 6'),
                    'metric' => (array) data_get($comparisonCredit, "total.{$metricKey}", []),
                ]];
                foreach ((array) data_get($comparisonCredit, 'branches', []) as $comparisonBranch) {
                    $comparisonRows[] = [
                        'label' => (string) ($comparisonBranch['scope_label'] ?? '-'),
                        'metric' => (array) data_get($comparisonBranch, "total.{$metricKey}", []),
                    ];
                }
                foreach ($comparisonRows as $comparisonRow) {
                    $branchRows[] = $this->nativeComparisonRow(
                        (string) $comparisonRow['label'],
                        (array) $comparisonRow['metric'],
                        true,
                        true
                    );
                }
            } else {
                foreach (array_slice($branches, 0, 6) as $index => $branch) {
                    $branchRows[] = [
                        $this->cell((string) ($index + 1), '52647B', true, null, 'ctr'),
                        $this->cell((string) $branch['label'], '12233B', true),
                        $this->cell((string) $branch['value'], 'C62828', true, null, 'r'),
                        $this->cell((string) $branch['ratio'], 'C62828', true, null, 'r'),
                    ];
                }
            }
            $productRows = [];
            foreach (array_slice($products, 0, 7) as $index => $product) {
                $productRows[] = [
                    $this->cell((string) ($index + 1), '52647B', true, null, 'ctr'),
                    $this->cell((string) $product['label'], '12233B', true),
                    $this->cell((string) $product['segment'], '52647B'),
                    $this->cell((string) $product['value'], 'C62828', true, null, 'r'),
                    $this->cell((string) $product['ratio'], 'C62828', true, null, 'r'),
                ];
            }
            if ($branchRows === []) {
                $branchRows[] = [$this->cell('-'), $this->cell('Data cabang belum tersedia', '52647B', true), $this->cell('-'), $this->cell('-')];
            }
            if ($productRows === []) {
                $productRows[] = [$this->cell('-'), $this->cell('Data produk belum tersedia', '52647B', true), $this->cell('-'), $this->cell('-'), $this->cell('-')];
            }

            if ($this->usesPrognosa($deck)) {
                $xml .= $this->nativePrognosaTable($deck, $branchRows, 0.7, 3.08, 12.0, 5.2, true);
                $xml .= $this->table(['#', 'PRODUK', 'SEGMEN', 'NOMINAL', 'RASIO'], $productRows, 12.92, 3.08, 6.36, 6.18, [0.4, 1.8, 1.1, 1.25, 0.9], 9.5, 9.5);
            } else {
                $xml .= $this->table(['#', 'CABANG', 'NOMINAL', 'RASIO'], $branchRows, 0.7, 3.08, 8.7, 5.2, [0.4, 2.0, 1.25, 0.9]);
                $xml .= $this->table(['#', 'PRODUK', 'SEGMEN', 'NOMINAL', 'RASIO'], $productRows, 9.62, 3.08, 9.66, 6.18, [0.4, 1.8, 1.1, 1.25, 0.9]);
            }
            $xml .= $this->callout(
                $isSml ? 'TINDAKAN CURING' : 'TINDAKAN RECOVERY',
                $isSml
                    ? 'Fokuskan curing pada eksposur SML terbesar sebelum jatuh tempo dan sebelum terjadi migrasi.'
                    : 'Prioritaskan recovery pada kombinasi nominal dan rasio NPL tertinggi.',
                0.7,
                8.52,
                8.7,
                0.74,
                $isSml ? 'FFF7E8' : 'FFF0F0',
                $tone
            );
            $xml .= $this->footer($deck, $slideNumber);

            return $xml;
        });
    }

    /**
     * @param array<string, mixed> $deck
     * @param array<string, mixed> $category
     */
    private function productivitySlide(array $deck, array $category, int $slideNumber): string
    {
        return $this->slide(function () use ($deck, $category, $slideNumber): string {
            $label = (string) ($category['label'] ?? 'Produktivitas RM');
            $rows = array_values((array) ($category['rows'] ?? []));
            $total = (array) ($category['total'] ?? []);
            $pdwk = (array) ($category['pdwk'] ?? []);
            $xml = $this->header(
                $deck,
                'PEOPLE PRODUCTIVITY',
                $label,
                (string) data_get($deck, 'productivity.scope_label', data_get($deck, 'meta.scope_label', 'Area 6')) . ': produktivitas, ticket size, dan kualitas portofolio.'
            );

            $cards = [
                ['JUMLAH RM', $this->integer($total['rm_count'] ?? 0), 'RM aktif'],
                ['REALISASI', (string) ($total['realisasi_os_fmt'] ?? '-'), $this->integer($total['realisasi_deb'] ?? 0) . ' debitur'],
                ['AVG / RM', (string) ($total['average_per_rm_fmt'] ?? '-'), 'Produktivitas rata-rata'],
                ['LAR', (string) ($total['lar_pct_fmt'] ?? '-'), 'Kualitas kelolaan'],
            ];
            foreach ($cards as $index => $card) {
                $xml .= $this->metricCard((string) $card[0], (string) $card[1], (string) $card[2], 0.7 + ($index * 4.72), 1.72, 4.42, 1.08, $index === 3 ? 'D97706' : '0866D4');
            }

            $tableRows = [];
            foreach (array_slice($rows, 0, 7) as $index => $row) {
                $tableRows[] = [
                    $this->cell((string) ($index + 1), '566A83', true, null, 'ctr'),
                    $this->cell((string) ($row['name'] ?? '-'), '12233B', true),
                    $this->cell(trim(implode(' - ', array_filter([(string) ($row['unit'] ?? ''), (string) ($row['branch'] ?? '')]))) ?: '-', '52647B'),
                    $this->cell($this->integer($row['realisasi_deb'] ?? 0), '12233B', false, null, 'r'),
                    $this->cell((string) ($row['realisasi_os_fmt'] ?? '-'), '12233B', true, null, 'r'),
                    $this->cell((string) ($row['average_ticket_fmt'] ?? '-'), '52647B', false, null, 'r'),
                    $this->cell((string) ($row['lar_pct_fmt'] ?? '-'), 'C56B00', true, null, 'r'),
                ];
            }
            if ($tableRows === []) {
                $tableRows[] = [
                    $this->cell('-', '52647B', false, null, 'ctr'),
                    $this->cell('Data RM belum tersedia', '52647B', true),
                    $this->cell('-', '52647B'),
                    $this->cell('-', '52647B'),
                    $this->cell('-', '52647B'),
                    $this->cell('-', '52647B'),
                    $this->cell('-', '52647B'),
                ];
            }
            $xml .= $this->table(
                ['#', 'RM', 'UNIT / CABANG', 'DEBITUR', 'REALISASI', 'AVG TICKET', 'LAR'],
                $tableRows,
                0.7,
                3.1,
                13.2,
                6.35,
                [0.45, 2.1, 2.15, 1.0, 1.45, 1.4, 0.9]
            );

            $xml .= $this->shape(14.15, 3.1, 5.13, 6.35, 'F7FAFE', 'D4E2F2', 0.04, 900);
            $leader = (array) ($rows[0] ?? []);
            $xml .= $this->text('EXECUTIVE READOUT', 14.47, 3.42, 4.5, 0.35, 12, '0866D4', true);
            $xml .= $this->text(
                $leader !== []
                    ? sprintf(
                        '%s memimpin realisasi dengan %s. Portofolio dikelola oleh %s RM dengan LAR %s.',
                        (string) ($leader['name'] ?? '-'),
                        (string) ($leader['realisasi_os_fmt'] ?? '-'),
                        $this->integer($total['rm_count'] ?? 0),
                        (string) ($total['lar_pct_fmt'] ?? '-')
                    )
                    : 'Data produktivitas pada scope dan periode ini belum tersedia.',
                14.47,
                3.95,
                4.42,
                1.5,
                14.5,
                '132238',
                true
            );

            if ((array) ($pdwk['roles'] ?? []) !== []) {
                $xml .= $this->text('REKAP PDWK PER PEMUTUS', 14.47, 5.7, 4.45, 0.34, 11.5, '0866D4', true);
                foreach (array_slice((array) $pdwk['roles'], 0, 3) as $index => $role) {
                    $y = 6.18 + ($index * 0.83);
                    $xml .= $this->shape(14.47, $y, 4.45, 0.68, 'FFFFFF', 'D9E4F1', 0.03, 650);
                    $xml .= $this->text((string) ($role['label'] ?? '-'), 14.68, $y + 0.12, 1.1, 0.24, 10.5, '52647B', true);
                    $xml .= $this->text((string) ($role['total_os_fmt'] ?? '-'), 15.78, $y + 0.1, 1.55, 0.28, 12.5, '12233B', true, 'r');
                    $xml .= $this->text($this->integer($role['total_deb'] ?? 0) . ' deb', 17.42, $y + 0.12, 1.18, 0.24, 9.5, '52647B', false, 'r');
                }
            } else {
                $xml .= $this->callout('FOCUS', 'Dorong pemerataan produktivitas dan intervensi RM dengan ticket atau kualitas di bawah benchmark.', 14.47, 6.05, 4.45, 1.45, 'EAF4FF', '0866D4');
            }
            $xml .= $this->footer($deck, $slideNumber);

            return $xml;
        });
    }

    /**
     * @param array<string, mixed> $deck
     * @param array<string, mixed> $category
     */
    private function pdwkSlide(array $deck, array $category, int $slideNumber): string
    {
        return $this->slide(function () use ($deck, $category, $slideNumber): string {
            $total = (array) ($category['total'] ?? []);
            $pdwk = (array) ($category['pdwk'] ?? []);
            $roles = array_values((array) ($pdwk['roles'] ?? []));
            $rows = array_values(array_filter(
                (array) ($category['rows'] ?? []),
                fn (array $row): bool => (float) ($row['realisasi_os'] ?? 0) !== 0.0
                    || (float) ($row['realisasi_deb'] ?? 0) !== 0.0
            ));
            $xml = $this->header(
                $deck,
                '4. PRODUKTIVITAS | MANTRI DAN PDWK',
                'Produktivitas Mantri dan Rekap PDWK',
                'Realisasi Mantri dipadukan dengan putusan K Unit, MBM, dan BOH.'
            );

            $cards = [
                ['JUMLAH MANTRI', $this->integer($total['rm_count'] ?? $total['jumlah_mantri'] ?? 0), 'Mantri aktif', '0866D4'],
                ['REALISASI OS', (string) ($total['realisasi_os_fmt'] ?? '-'), $this->integer($total['realisasi_deb'] ?? 0) . ' debitur', '0D9F77'],
                ['TOTAL PDWK', (string) data_get($pdwk, 'total.os_fmt', $total['realisasi_os_fmt'] ?? '-'), $this->integer(data_get($pdwk, 'total.deb', $total['realisasi_deb'] ?? 0)) . ' debitur', '16A3C7'],
                ['PEMUTUS AKTIF', $this->integer(count($roles)), 'K Unit, MBM, BOH', 'B4937B'],
            ];
            foreach ($cards as $index => $card) {
                $xml .= $this->metricCard((string) $card[0], (string) $card[1], (string) $card[2], 0.7 + ($index * 4.72), 1.72, 4.42, 1.08, (string) $card[3]);
            }

            $roleRows = [];
            foreach ($roles as $index => $role) {
                $roleRows[] = [
                    $this->cell((string) ($index + 1), '52647B', true, null, 'ctr'),
                    $this->cell((string) ($role['label'] ?? '-'), '12233B', true),
                    $this->cell((string) ($role['total_os_fmt'] ?? $this->formatAmount($role['total_os'] ?? null)), '12233B', true, null, 'r'),
                    $this->cell($this->integer($role['total_deb'] ?? 0), '52647B', false, null, 'r'),
                    $this->cell((string) ($role['share_pct_fmt'] ?? $this->formatPercent($role['share_pct'] ?? null)), '0866D4', true, null, 'r'),
                ];
            }
            if ($roleRows === []) {
                $roleRows[] = [
                    $this->cell('-'),
                    $this->cell('Rekap PDWK belum tersedia', '52647B', true),
                    $this->cell('-'),
                    $this->cell('-'),
                    $this->cell('-'),
                ];
            }

            $mantriRows = [];
            foreach (array_slice($rows, 0, 7) as $index => $row) {
                $mantriRows[] = [
                    $this->cell((string) ($index + 1), '52647B', true, null, 'ctr'),
                    $this->cell((string) ($row['name'] ?? '-'), '12233B', true),
                    $this->cell((string) ($row['unit'] ?? $row['branch'] ?? '-'), '52647B'),
                    $this->cell($this->integer($row['realisasi_deb'] ?? 0), '52647B', false, null, 'r'),
                    $this->cell((string) ($row['realisasi_os_fmt'] ?? '-'), '12233B', true, null, 'r'),
                ];
            }
            if ($mantriRows === []) {
                $mantriRows[] = [
                    $this->cell('-'),
                    $this->cell('Data Mantri belum tersedia', '52647B', true),
                    $this->cell('-'),
                    $this->cell('-'),
                    $this->cell('-'),
                ];
            }

            $xml .= $this->table(['#', 'PEMUTUS', 'OS', 'DEBITUR', 'PORSI'], $roleRows, 0.7, 3.08, 8.0, 4.2, [0.4, 1.8, 1.35, 0.9, 0.9]);
            $xml .= $this->table(['#', 'MANTRI', 'UNIT', 'DEBITUR', 'REALISASI'], $mantriRows, 8.92, 3.08, 10.36, 6.18, [0.4, 2.1, 1.8, 0.9, 1.35]);
            $leader = (array) ($roles[0] ?? []);
            $xml .= $this->callout(
                'KONTRIBUTOR PUTUSAN',
                $leader !== []
                    ? (string) ($leader['label'] ?? '-') . ' berkontribusi ' . (string) ($leader['total_os_fmt'] ?? '-') . ' atau ' . (string) ($leader['share_pct_fmt'] ?? '-') . '.'
                    : 'Rekap putusan akan mengikuti payload PDWK periode aktif.',
                0.7,
                7.52,
                8.0,
                1.74,
                'EAF7F3',
                '0D9F77'
            );
            $xml .= $this->footer($deck, $slideNumber);

            return $xml;
        });
    }

    /** @param array<string, mixed> $deck */
    private function ktsSlide(array $deck, int $slideNumber): string
    {
        return $this->slide(function () use ($deck, $slideNumber): string {
            $kts = (array) data_get($deck, 'kts', []);
            $ritel = (array) data_get($kts, 'categories.membaik.ritel', []);
            $micro = (array) data_get($kts, 'categories.membaik.micro', []);
            $ritelRows = array_values((array) ($ritel['rows'] ?? []));
            $microRows = array_values((array) ($micro['rows'] ?? []));
            $xml = $this->header(
                $deck,
                '4. PENDAMPING | KTS',
                'KTS Decision Support',
                'Rekening dengan kolektibilitas aktual berbeda dari kolektibilitas seharusnya.'
            );
            $cards = [
                ['KTS RITEL', $this->integer($ritel['total_count'] ?? count($ritelRows)), (string) ($ritel['total_os_fmt'] ?? $this->formatAmount($ritel['total_os'] ?? null)), '0866D4'],
                ['KTS MIKRO', $this->integer($micro['total_count'] ?? count($microRows)), (string) ($micro['total_os_fmt'] ?? $this->formatAmount($micro['total_os'] ?? null)), '16A3C7'],
                ['TOTAL REKENING', $this->integer((float) ($ritel['total_count'] ?? 0) + (float) ($micro['total_count'] ?? 0)), 'kategori membaik', '0D9F77'],
                ['PERIODE', (string) ($kts['period_label'] ?? '-'), (string) ($kts['source'] ?? 'daily loan'), 'B4937B'],
            ];
            foreach ($cards as $index => $card) {
                $xml .= $this->metricCard((string) $card[0], (string) $card[1], (string) $card[2], 0.7 + ($index * 4.72), 1.72, 4.42, 1.08, (string) $card[3]);
            }

            $rows = [];
            foreach ([['Ritel', $ritelRows], ['Mikro', $microRows]] as [$segment, $segmentRows]) {
                foreach ($segmentRows as $row) {
                    if ((float) ($row['os'] ?? $row['baki_debet'] ?? 0) === 0.0
                        && trim((string) ($row['debitur'] ?? $row['nama'] ?? '')) === '') {
                        continue;
                    }
                    $rows[] = [
                        $this->cell((string) $segment, '0866D4', true),
                        $this->cell((string) ($row['debitur'] ?? $row['nama'] ?? $row['nama_debitur'] ?? '-'), '12233B', true),
                        $this->cell((string) ($row['rekening'] ?? $row['no_rekening'] ?? $row['norek'] ?? '-'), '52647B'),
                        $this->cell((string) ($row['unit'] ?? $row['unit_kerja'] ?? $row['cabang'] ?? '-'), '52647B'),
                        $this->cell((string) ($row['kolek_label'] ?? $row['pergeseran'] ?? '-'), '0D9F77', true),
                        $this->cell((string) ($row['os_fmt'] ?? $row['baki_debet_fmt'] ?? $this->formatAmount($row['os'] ?? $row['baki_debet'] ?? null)), '12233B', true, null, 'r'),
                    ];
                }
            }
            $rows = array_slice($rows, 0, 8);
            if ($rows === []) {
                $rows[] = [
                    $this->cell('-'),
                    $this->cell('Tidak ada rekening KTS pada filter ekspor ini', '52647B', true),
                    $this->cell('-'),
                    $this->cell('-'),
                    $this->cell('-'),
                    $this->cell('-'),
                ];
            }
            $xml .= $this->table(
                ['SEGMEN', 'DEBITUR', 'REKENING', 'UNIT', 'KOLEK', 'BAKI DEBET'],
                $rows,
                0.7,
                3.08,
                18.58,
                5.75,
                [0.8, 1.8, 1.45, 1.55, 1.25, 1.25]
            );
            $xml .= $this->callout(
                'ARAH TINDAKAN',
                'Validasi rekening KTS membaik, pastikan status berkelanjutan, dan gunakan kategori memburuk untuk intervensi dini.',
                0.7,
                9.06,
                18.58,
                0.72,
                'EAF7F3',
                '0D9F77'
            );
            $xml .= $this->footer($deck, $slideNumber);

            return $xml;
        });
    }

    /**
     * @param array<string, mixed> $deck
     * @param array<int, array<string, mixed>> $groups
     */
    private function trendLabSlide(array $deck, array $groups, int $slideNumber, string $title): string
    {
        return $this->slide(function () use ($deck, $groups, $slideNumber, $title): string {
            $xml = $this->header($deck, 'MULTI-METRIC TIMESERIES', $title, 'Pergerakan indikator utama dengan angka posisi pada setiap periode.');
            if ($groups === []) {
                $xml .= $this->callout('DATA', 'Kelompok timeseries belum tersedia pada periode ini.', 0.75, 2.2, 18.5, 2.0, 'F4F8FD', '0866D4');
            }

            foreach (array_slice($groups, 0, 2) as $index => $group) {
                $x = 0.72 + ($index * 9.5);
                $xml .= $this->shape($x, 1.9, 9.06, 7.58, 'FFFFFF', 'D4E2F2', 0.04, 900);
                $xml .= $this->text((string) ($group['label'] ?? 'Timeseries'), $x + 0.28, 2.18, 8.45, 0.38, 15.5, '0866D4', true);
                $xml .= $this->text((string) ($group['description'] ?? ''), $x + 0.28, 2.58, 8.45, 0.48, 10.5, '61738A');
                $xml .= $this->lineChart(
                    array_values((array) ($group['labels'] ?? [])),
                    array_values((array) ($group['series'] ?? [])),
                    $x + 0.28,
                    3.18,
                    8.5,
                    4.65,
                    true
                );
                $xml .= $this->text(
                    $this->trendNarrative(
                        array_values((array) ($group['labels'] ?? [])),
                        array_values((array) ($group['series'] ?? []))
                    ),
                    $x + 0.32,
                    8.12,
                    8.35,
                    0.82,
                    11.5,
                    '30445E',
                    true
                );
            }
            $xml .= $this->footer($deck, $slideNumber);

            return $xml;
        });
    }

    /** @param array<string, mixed> $deck */
    private function strategySlide(array $deck, int $slideNumber): string
    {
        return $this->slide(function () use ($deck, $slideNumber): string {
            $strategy = (array) data_get($deck, 'funding_strategies', []);
            $digital = array_values((array) data_get($strategy, 'digital.rows', []));
            $casa = array_values((array) data_get($strategy, 'casa_debitur.rows', []));
            $payroll = array_values((array) data_get($strategy, 'payroll.rows', []));
            $clusters = array_values((array) data_get($strategy, 'business_cluster.rows', []));
            $supporting = array_values((array) data_get($strategy, 'supporting', []));
            $dormant = (array) data_get($strategy, 'dormant.row', []);
            $legacy = array_values((array) data_get($deck, 'strategies', []));
            $xml = $this->header(
                $deck,
                'FUNDING STRATEGY EXECUTION',
                'Rangkuman 8 Strategi Funding',
                'Delapan pengungkit funding dalam satu peta eksekusi yang ringkas, terukur, dan mudah dipresentasikan.'
            );

            if ($digital === []) {
                $digital = collect(array_slice($legacy, 0, 6))
                    ->map(fn (array $row, int $index): array => [
                        'label' => (string) ($row['title'] ?? 'Strategi ' . ($index + 1)),
                        'positions' => [
                            'ytd' => ['fmt' => '-'],
                            'mtd' => ['fmt' => '-'],
                            'current' => ['fmt' => (string) ($row['current_value'] ?? '-')],
                        ],
                        'deltas' => [
                            'ytd' => ['fmt' => '-', 'raw' => null],
                            'mtd' => ['fmt' => (string) ($row['trend'] ?? '-'), 'raw' => null],
                        ],
                        'rka' => ['fmt' => '-'],
                    ])
                    ->values()
                    ->all();
            }

            $pointCell = fn (array $point, string $color = '26354A', float $size = 8.0): array => array_merge(
                $this->cell((string) ($point['fmt'] ?? '-'), $color, true, null, 'r'),
                ['size' => $size]
            );
            $deltaPointCell = function (array $point, float $size = 8.0): array {
                $raw = $point['raw'] ?? null;
                $color = !is_numeric($raw) || (float) $raw === 0.0
                    ? '916600'
                    : ((float) $raw > 0 ? '078A68' : 'C62828');

                return array_merge(
                    $this->cell((string) ($point['fmt'] ?? '-'), $color, true, null, 'r'),
                    ['size' => $size]
                );
            };
            $compactTable = function (
                array $headers,
                array $rows,
                float $x,
                float $y,
                float $width,
                float $height,
                array $weights,
                float $headerHeight = 0.34,
                float $headerSize = 8.0
            ): string {
                $tableXml = $this->shape($x, $y, $width, $height, 'FFFFFF', 'D4E2F2', 0.025, 300);
                $weightSum = max(0.01, array_sum($weights));
                $rowHeight = max(0.16, ($height - $headerHeight) / max(1, count($rows)));
                $cursorX = $x;
                foreach ($headers as $index => $header) {
                    $cellWidth = $width * ((float) ($weights[$index] ?? 1) / $weightSum);
                    $tableXml .= $this->shape($cursorX, $y, $cellWidth, $headerHeight, 'E8F0F8', 'D4E2F2', 0, 220);
                    $tableXml .= $this->text(
                        (string) $header,
                        $cursorX + 0.04,
                        $y + 0.025,
                        max(0.08, $cellWidth - 0.08),
                        max(0.08, $headerHeight - 0.05),
                        $headerSize,
                        '36506F',
                        true,
                        $index <= 1 ? 'l' : 'ctr',
                        'mid'
                    );
                    $cursorX += $cellWidth;
                }

                foreach ($rows as $rowIndex => $row) {
                    $cursorX = $x;
                    $rowY = $y + $headerHeight + ($rowIndex * $rowHeight);
                    foreach ($headers as $cellIndex => $_header) {
                        $cellWidth = $width * ((float) ($weights[$cellIndex] ?? 1) / $weightSum);
                        $cell = (array) ($row[$cellIndex] ?? $this->cell('-'));
                        $fill = (string) ($cell['fill'] ?? ($rowIndex % 2 === 0 ? 'FFFFFF' : 'F6F9FC'));
                        $tableXml .= $this->shape($cursorX, $rowY, $cellWidth, $rowHeight, $fill, 'E0E8F2', 0, 180);
                        $tableXml .= $this->text(
                            (string) ($cell['text'] ?? '-'),
                            $cursorX + 0.04,
                            $rowY + 0.02,
                            max(0.08, $cellWidth - 0.08),
                            max(0.08, $rowHeight - 0.04),
                            (float) ($cell['size'] ?? 8.0),
                            (string) ($cell['color'] ?? '26354A'),
                            (bool) ($cell['bold'] ?? false),
                            (string) ($cell['align'] ?? ($cellIndex <= 1 ? 'l' : 'r')),
                            'mid'
                        );
                        $cursorX += $cellWidth;
                    }
                }

                return $tableXml;
            };
            $nodeFrame = function (
                string $number,
                string $title,
                string $description,
                string $iconCode,
                string $side,
                float $x,
                float $y,
                float $width,
                float $height,
                float $titleSize = 10.5
            ): array {
                $frameXml = $this->shape($x, $y, $width, $height, 'FFFFFF', 'C8D9ED', 0.05, 520);
                $isLeft = $side === 'left';
                $numberX = $isLeft ? $x + 0.08 : $x + $width - 0.46;
                $iconX = $isLeft ? $x + $width - 0.65 : $x + 0.1;
                $bodyX = $isLeft ? $x + 0.56 : $x + 0.72;
                $bodyWidth = $width - 1.28;
                $frameXml .= $this->shape($numberX, $y + ($height / 2) - 0.18, 0.36, 0.36, '075DC8', '075DC8', 0, 0, 'ellipse');
                $frameXml .= $this->text($number, $numberX, $y + ($height / 2) - 0.13, 0.36, 0.22, 10.5, 'FFFFFF', true, 'ctr', 'mid');
                $frameXml .= $this->shape($iconX, $y + ($height / 2) - 0.27, 0.54, 0.54, 'E7F6FD', '66C9EF', 0, 820, 'ellipse');
                $frameXml .= $this->text($iconCode, $iconX, $y + ($height / 2) - 0.09, 0.54, 0.18, 8.5, '053F8D', true, 'ctr', 'mid');
                $frameXml .= $this->text($title, $bodyX, $y + 0.1, $bodyWidth, 0.25, $titleSize, '102440', true);
                $frameXml .= $this->text($description, $bodyX, $y + 0.38, $bodyWidth, 0.26, 7.4, '5B6C83', false);

                return [
                    'xml' => $frameXml,
                    'body_x' => $bodyX,
                    'body_y' => $y + 0.68,
                    'body_width' => $bodyWidth,
                    'body_height' => max(0.2, $height - 0.78),
                ];
            };
            $metricStrip = function (array $frame, array $items): string {
                $stripXml = '';
                $count = max(1, count($items));
                $cellWidth = (float) $frame['body_width'] / $count;
                foreach ($items as $index => $item) {
                    $cellX = (float) $frame['body_x'] + ($cellWidth * $index);
                    $stripXml .= $this->shape(
                        $cellX,
                        (float) $frame['body_y'],
                        $cellWidth,
                        (float) $frame['body_height'],
                        $index % 2 === 0 ? 'F6F9FC' : 'FFFFFF',
                        'DCE6F1',
                        0,
                        180
                    );
                    $stripXml .= $this->text(
                        (string) ($item['label'] ?? '-'),
                        $cellX + 0.05,
                        (float) $frame['body_y'] + 0.07,
                        $cellWidth - 0.1,
                        0.16,
                        6.8,
                        '6A7A8E',
                        true,
                        'ctr'
                    );
                    $stripXml .= $this->text(
                        (string) ($item['value'] ?? '-'),
                        $cellX + 0.05,
                        (float) $frame['body_y'] + 0.26,
                        $cellWidth - 0.1,
                        max(0.16, (float) $frame['body_height'] - 0.31),
                        9.0,
                        (string) ($item['color'] ?? '14253D'),
                        true,
                        'ctr',
                        'mid'
                    );
                }

                return $stripXml;
            };

            $leftX = 0.7;
            $leftWidth = 7.3;
            $centerX = 8.15;
            $centerWidth = 3.7;
            $rightX = 12.0;
            $rightWidth = 7.3;
            $contentTop = 1.72;

            $firstDigital = (array) ($digital[0] ?? []);
            $digitalDescription = 'YTD '
                . (string) data_get($firstDigital, 'positions.ytd.label', '-')
                . ' | MtD '
                . (string) data_get($firstDigital, 'positions.mtd.label', '-')
                . ' | Terakhir '
                . (string) data_get($firstDigital, 'positions.current.label', data_get($strategy, 'period_label', '-'));
            $digitalFrame = $nodeFrame(
                '1',
                'OPTIMALISASI DIGITAL CHANNEL',
                $digitalDescription,
                'DC',
                'left',
                $leftX,
                $contentTop,
                $leftWidth,
                3.18
            );
            $xml .= (string) $digitalFrame['xml'];
            $digitalRows = [];
            foreach (array_slice($digital, 0, 6) as $index => $row) {
                $digitalRows[] = [
                    array_merge($this->cell((string) ($index + 1), '52647B', true, null, 'ctr'), ['size' => 7.6]),
                    array_merge($this->cell((string) ($row['label'] ?? '-'), '12233B', true), ['size' => 7.8]),
                    $pointCell((array) data_get($row, 'positions.ytd', []), '26354A', 7.4),
                    $pointCell((array) data_get($row, 'positions.mtd', []), '26354A', 7.4),
                    $pointCell((array) data_get($row, 'positions.current', []), '075DC8', 7.4),
                    $deltaPointCell((array) data_get($row, 'deltas.ytd', []), 7.4),
                    $deltaPointCell((array) data_get($row, 'deltas.mtd', []), 7.4),
                    array_merge($this->cell((string) data_get($row, 'rka.fmt', '-'), '26354A', true, null, 'r'), ['size' => 7.4]),
                ];
            }
            $xml .= $compactTable(
                ['#', 'KANAL', 'YTD', 'MTD', 'TERAKHIR', 'D YTD', 'D MTD', 'RKA'],
                $digitalRows,
                (float) $digitalFrame['body_x'],
                (float) $digitalFrame['body_y'],
                (float) $digitalFrame['body_width'],
                (float) $digitalFrame['body_height'],
                [0.35, 1.3, 1.0, 1.0, 1.12, 0.98, 0.98, 1.05],
                0.33,
                7.2
            );

            $supportingByNumber = collect($supporting)->keyBy(fn (array $row): string => (string) ($row['number'] ?? ''));
            $supportDefinitions = [
                8 => ['title' => 'Penguatan Produk & Fungsi RM', 'description' => 'Kapasitas, akuisisi, dan fungsi pengelolaan RM.', 'icon' => 'RM'],
                7 => ['title' => 'Optimalisasi Nasabah Prioritas BOD / BOC', 'description' => 'Peluang nasabah prioritas wholesale dan komersial.', 'icon' => 'NP'],
                6 => ['title' => 'Kolaborasi Perusahaan Anak', 'description' => 'Sinergi ekosistem untuk sumber dana dan transaksi.', 'icon' => 'PA'],
            ];
            foreach ($supportDefinitions as $number => $definition) {
                $row = (array) ($supportingByNumber->get((string) $number) ?? []);
                $rowIndex = 8 - $number;
                $frameY = 5.02 + ($rowIndex * 1.5);
                $frame = $nodeFrame(
                    (string) $number,
                    (string) ($row['title'] ?? $definition['title']),
                    (string) $definition['description'],
                    (string) $definition['icon'],
                    'left',
                    $leftX,
                    $frameY,
                    $leftWidth,
                    1.38,
                    $number === 7 ? 9.4 : 10.1
                );
                $xml .= (string) $frame['xml'];
                $xml .= $metricStrip($frame, [
                    ['label' => 'POSISI', 'value' => (string) ($row['position'] ?? '-')],
                    ['label' => 'DELTA YTD', 'value' => (string) ($row['delta_ytd'] ?? '-')],
                    ['label' => 'DELTA MTD', 'value' => (string) ($row['delta_mtd'] ?? '-')],
                    ['label' => 'RKA', 'value' => (string) ($row['rka'] ?? '-')],
                ]);
            }

            $casaPeriod = (string) data_get($strategy, 'casa_debitur.period_label', '-');
            $casaFrame = $nodeFrame(
                '2',
                'Rekening Transaksi Debitur',
                "CASA terhadap OS | {$casaPeriod}",
                'RT',
                'right',
                $rightX,
                $contentTop,
                $rightWidth,
                1.64
            );
            $xml .= (string) $casaFrame['xml'];
            $casaTableRows = collect(array_slice($casa, 0, 4))
                ->map(fn (array $row): array => [
                    array_merge($this->cell((string) ($row['label'] ?? '-'), '12233B', true), ['size' => 8.2]),
                    array_merge($this->cell((string) ($row['os_fmt'] ?? '-'), '26354A', true, null, 'r'), ['size' => 8.2]),
                    array_merge($this->cell((string) ($row['casa_fmt'] ?? '-'), '26354A', true, null, 'r'), ['size' => 8.2]),
                    array_merge($this->cell((string) ($row['ratio_fmt'] ?? '-'), '075DC8', true, null, 'r'), ['size' => 8.2]),
                ])
                ->values()
                ->all();
            $xml .= $compactTable(
                ['CABANG / SEGMEN', 'OS', 'CASA', 'RASIO'],
                $casaTableRows,
                (float) $casaFrame['body_x'],
                (float) $casaFrame['body_y'],
                (float) $casaFrame['body_width'],
                (float) $casaFrame['body_height'],
                [1.55, 1.0, 1.0, 0.8],
                0.27,
                7.5
            );

            $clusterFrame = $nodeFrame(
                '3',
                'Bisnis Cluster | Top 5',
                number_format((float) data_get($strategy, 'business_cluster.total', 0), 0, ',', '.') . ' potensi pada scope terpilih.',
                'BC',
                'right',
                $rightX,
                3.48,
                $rightWidth,
                1.82
            );
            $xml .= (string) $clusterFrame['xml'];
            $clusterRows = collect(array_slice($clusters, 0, 5))
                ->map(fn (array $row): array => [
                    array_merge($this->cell((string) ($row['category'] ?? '-'), '12233B', true), ['size' => 7.8]),
                    array_merge($this->cell((string) ($row['total_fmt'] ?? '-'), '26354A', true, null, 'r'), ['size' => 7.8]),
                    array_merge($this->cell((string) ($row['sudah_bri_fmt'] ?? '-'), '078A68', true, null, 'r'), ['size' => 7.8]),
                    array_merge($this->cell((string) ($row['belum_bri_fmt'] ?? '-'), 'C26A00', true, null, 'r'), ['size' => 7.8]),
                    array_merge($this->cell((string) ($row['penetration_fmt'] ?? '-'), '075DC8', true, null, 'r'), ['size' => 7.8]),
                ])
                ->values()
                ->all();
            $xml .= $compactTable(
                ['CLUSTER', 'TOTAL', 'BRI', 'POTENSI', '%'],
                $clusterRows,
                (float) $clusterFrame['body_x'],
                (float) $clusterFrame['body_y'],
                (float) $clusterFrame['body_width'],
                (float) $clusterFrame['body_height'],
                [1.75, 0.75, 0.75, 0.82, 0.65],
                0.27,
                7.4
            );

            $payrollPeriod = trim((string) data_get($strategy, 'payroll.period_label', '-'));
            $payrollFrame = $nodeFrame(
                '4',
                'Peningkatan Payroll Berkualitas',
                "Posisi, delta YTD/MtD, serta RKA / Penc. | {$payrollPeriod}",
                'PY',
                'right',
                $rightX,
                5.42,
                $rightWidth,
                2.34
            );
            $xml .= (string) $payrollFrame['xml'];
            $payrollMetricCell = function (array $metric, string $color = '26354A'): array {
                $current = (string) data_get($metric, 'positions.current.fmt', '-');
                $compact = fn (string $value): string => trim(str_replace('Rp', '', $value));
                $deltaYtd = $compact((string) data_get($metric, 'deltas.ytd.fmt', '-'));
                $deltaMtd = $compact((string) data_get($metric, 'deltas.mtd.fmt', '-'));

                return array_merge(
                    $this->cell(
                        "{$current}\nD Y/M: {$deltaYtd} / {$deltaMtd}",
                        $color,
                        true,
                        null,
                        'r'
                    ),
                    ['size' => 7.5]
                );
            };
            $payrollTableRows = collect(array_slice($payroll, 0, 4))
                ->map(fn (array $row): array => [
                    array_merge($this->cell((string) ($row['label'] ?? '-'), '12233B', true), ['size' => 7.8]),
                    $payrollMetricCell((array) ($row['rekening'] ?? [])),
                    $payrollMetricCell((array) ($row['saldo'] ?? [])),
                    $payrollMetricCell((array) ($row['kualitas'] ?? []), '078A68'),
                    array_merge($this->cell(
                        (string) data_get($row, 'rekening.rka.fmt', '-')
                        . "\n"
                        . (string) data_get($row, 'rekening.achievement.fmt', '-'),
                        '0857C3',
                        true,
                        null,
                        'r'
                    ), ['size' => 7.5]),
                ])
                ->values()
                ->all();
            $xml .= $compactTable(
                ['CABANG', "NEW REK\nAKTUAL | DY/DM", "SALDO NEW\nAKTUAL | DY/DM", "BERKUALITAS\nAKTUAL | DY/DM", 'RKA / PENC'],
                $payrollTableRows,
                (float) $payrollFrame['body_x'],
                (float) $payrollFrame['body_y'],
                (float) $payrollFrame['body_width'],
                (float) $payrollFrame['body_height'],
                [1.05, 1.15, 1.35, 1.25, 0.78],
                0.38,
                6.9
            );

            $dormantCurrentLabel = trim((string) data_get($dormant, 'positions.current.label', ''));
            $dormantFrame = $nodeFrame(
                '5',
                'Rekening Dormant',
                'Reaktivasi rekening tidak bertransaksi'
                    . ($dormantCurrentLabel !== '' && $dormantCurrentLabel !== '-' ? " | {$dormantCurrentLabel}" : ''),
                'RD',
                'right',
                $rightX,
                7.88,
                $rightWidth,
                1.64
            );
            $xml .= (string) $dormantFrame['xml'];
            $dormantYtd = (array) data_get($dormant, 'deltas.ytd', []);
            $dormantMtd = (array) data_get($dormant, 'deltas.mtd', []);
            $dormantColor = function (array $point): string {
                $raw = $point['raw'] ?? null;
                if (! is_numeric($raw) || (float) $raw === 0.0) {
                    return '916600';
                }

                return (float) $raw < 0 ? '078A68' : 'C62828';
            };
            $xml .= $metricStrip($dormantFrame, [
                ['label' => 'POSISI YTD', 'value' => (string) data_get($dormant, 'positions.ytd.fmt', '-')],
                ['label' => 'POSISI MTD', 'value' => (string) data_get($dormant, 'positions.mtd.fmt', '-')],
                ['label' => 'TERAKHIR', 'value' => (string) data_get($dormant, 'positions.current.fmt', '-'), 'color' => '075DC8'],
                ['label' => 'DELTA YTD', 'value' => (string) ($dormantYtd['fmt'] ?? '-'), 'color' => $dormantColor($dormantYtd)],
                ['label' => 'DELTA MTD', 'value' => (string) ($dormantMtd['fmt'] ?? '-'), 'color' => $dormantColor($dormantMtd)],
            ]);

            $scopeLabel = (string) data_get($strategy, 'scope_label', data_get($deck, 'meta.scope_label', 'Area 6'));
            $activeCount = collect($digital)
                ->filter(fn (array $row): bool => data_get($row, 'positions.current.raw') !== null)
                ->count();
            $topCasa = collect($casa)
                ->sortByDesc(fn (array $row): float => (float) ($row['ratio'] ?? 0))
                ->first();
            $topCluster = (array) ($clusters[0] ?? []);

            $xml .= $this->shape($centerX, $contentTop, $centerWidth, 7.8, '064589', '075DC8', 0.06, 700);
            $xml .= $this->shape($centerX + 0.82, 2.12, 2.06, 2.06, '0B59B4', '63CEF4', 0, 1500, 'ellipse');
            $xml .= $this->text('8', $centerX + 0.82, 2.45, 2.06, 0.55, 28, 'FFFFFF', true, 'ctr', 'mid');
            $xml .= $this->text('STRATEGI', $centerX + 0.82, 3.1, 2.06, 0.32, 13, 'FFFFFF', true, 'ctr');
            $xml .= $this->text('RETAIL FUNDING', $centerX + 0.82, 3.48, 2.06, 0.22, 8.5, 'C6EFFF', true, 'ctr');

            $barHeights = [0.76, 1.18, 1.68, 2.15, 1.44, 0.96];
            $barColors = ['13B7D6', '0BB994', 'F4A11A', 'EF6848', '0BB994', '13B7D6'];
            foreach ($barHeights as $index => $barHeight) {
                $barX = $centerX + 0.44 + ($index * 0.48);
                $xml .= $this->shape(
                    $barX,
                    6.3 - $barHeight,
                    0.34,
                    $barHeight,
                    $barColors[$index],
                    '9CE6F7',
                    0,
                    320
                );
            }
            $xml .= $this->shape($centerX + 0.34, 6.3, $centerWidth - 0.68, 0.06, '63CEF4', '63CEF4');
            $xml .= $this->text($scopeLabel, $centerX + 0.3, 6.62, $centerWidth - 0.6, 0.42, 13.5, 'FFFFFF', true, 'ctr', 'mid');
            $xml .= $this->text('FUNDING EXECUTION MAP', $centerX + 0.3, 7.06, $centerWidth - 0.6, 0.22, 8, 'BFEAFF', true, 'ctr');

            $coreMetrics = [
                ["{$activeCount}/6", 'KANAL AKTIF'],
                [(string) ($topCasa['ratio_fmt'] ?? '-'), 'CASA/OS TERTINGGI'],
                [number_format((float) data_get($strategy, 'business_cluster.total', 0), 0, ',', '.'), 'POTENSI CLUSTER'],
            ];
            foreach ($coreMetrics as $index => $metric) {
                $metricY = 7.55 + ($index * 0.55);
                $xml .= $this->shape($centerX + 0.35, $metricY, $centerWidth - 0.7, 0.45, '0A4A91', '3378BA', 0.025, 240);
                $xml .= $this->text((string) $metric[1], $centerX + 0.48, $metricY + 0.08, 1.65, 0.16, 6.8, '9EDCF7', true);
                $xml .= $this->text((string) $metric[0], $centerX + 2.05, $metricY + 0.07, 1.12, 0.2, 9.5, 'FFFFFF', true, 'r');
            }

            $actionMessage = $topCasa
                ? 'Perkuat kanal digital, pertahankan CASA/OS '
                    . (string) ($topCasa['label'] ?? 'tertinggi')
                    . ' '
                    . (string) ($topCasa['ratio_fmt'] ?? '-')
                    . ', dan tindak lanjuti potensi cluster serta rekening dormant.'
                : 'Perkuat kanal digital, payroll, bisnis cluster, dan reaktivasi rekening dormant berdasarkan posisi terakhir.';
            $xml .= $this->shape(0.7, 9.72, 18.6, 0.68, '063D7C', '0754AD', 0.05, 500);
            $xml .= $this->shape(0.88, 9.86, 0.4, 0.4, '81D7F6', '81D7F6', 0, 0, 'ellipse');
            $xml .= $this->text('!', 0.88, 9.92, 0.4, 0.2, 12, '053F8D', true, 'ctr', 'mid');
            $xml .= $this->text('FOKUS EKSEKUSI FUNDING', 1.45, 9.82, 2.55, 0.2, 8.5, 'FFFFFF', true);
            $xml .= $this->text($actionMessage, 1.45, 10.04, 10.0, 0.22, 8.3, 'D6EDFF', true);
            $footerMetrics = [
                ['CLUSTER UTAMA', (string) ($topCluster['category'] ?? '-')],
                ['CASA / OS', (string) ($topCasa['ratio_fmt'] ?? '-')],
                ['DORMANT MTD', (string) data_get($dormant, 'deltas.mtd.fmt', '-')],
            ];
            foreach ($footerMetrics as $index => $metric) {
                $metricX = 12.05 + ($index * 2.38);
                $xml .= $this->shape($metricX, 9.81, 2.2, 0.5, '0A4A91', '3378BA', 0.02, 220);
                $xml .= $this->text((string) $metric[0], $metricX + 0.08, 9.86, 2.04, 0.14, 6.2, '9EDCF7', true);
                $xml .= $this->text((string) $metric[1], $metricX + 0.08, 10.04, 2.04, 0.17, 8.3, 'FFFFFF', true);
            }
            $xml .= $this->footer($deck, $slideNumber);

            return $xml;
        });
    }

    /** @param array<string, mixed> $deck */
    private function closingSlide(array $deck, int $slideNumber): string
    {
        return $this->slide(function () use ($deck, $slideNumber): string {
            $funding = (array) data_get($deck, 'funding', []);
            $sme = (array) data_get($deck, 'sme', []);
            $consumer = (array) data_get($deck, 'consumer', []);
            $strategies = array_values((array) data_get($deck, 'strategies', []));
            $xml = $this->shape(0, 0, self::WIDTH, self::HEIGHT, '032A52', '032A52');
            $xml .= $this->shape(0, 0, 0.22, self::HEIGHT, '16B8F3', '16B8F3');
            $xml .= $this->picture(15.1, 0.55, 2.15, 0.62, 'rId2', 'BRI');
            $xml .= $this->picture(17.55, 0.54, 1.72, 0.64, 'rId3', 'Danantara');
            $xml .= $this->text('EXECUTIVE CLOSING', 0.82, 1.1, 9.0, 0.35, 14, '69D2FF', true);
            $xml .= $this->text('Prioritas 30 Hari Berikutnya', 0.82, 1.55, 12.5, 0.8, 31, 'FFFFFF', true);

            $actions = [
                ['FUNDING', $this->sectionAction($funding, true)],
                ['SME', $this->sectionAction($sme, false)],
                ['KONSUMER', $this->sectionAction($consumer, false)],
                ['DIGITAL', $this->strategyAction($strategies)],
            ];
            foreach ($actions as $index => $action) {
                $column = $index % 2;
                $row = intdiv($index, 2);
                $x = 0.82 + ($column * 9.15);
                $y = 2.85 + ($row * 2.55);
                $xml .= $this->shape($x, $y, 8.7, 2.18, 'FFFFFF', 'FFFFFF', 0.08, 1300);
                $xml .= $this->text((string) $action[0], $x + 0.32, $y + 0.3, 2.1, 0.32, 12, '0866D4', true);
                $xml .= $this->text((string) $action[1], $x + 0.32, $y + 0.78, 8.0, 1.0, 15, '12233B', true);
            }

            $xml .= $this->text(
                'Keputusan utama: jaga pertumbuhan sehat, lindungi kualitas, dan arahkan kapasitas eksekusi pada gap terbesar.',
                0.82,
                8.4,
                16.8,
                0.72,
                18,
                'D9E9FA',
                true
            );
            $xml .= $this->text((string) data_get($deck, 'meta.source_note', ''), 0.82, 9.55, 13.5, 0.4, 9.5, '94AAC2');
            $xml .= $this->footer($deck, $slideNumber, true);

            return $xml;
        }, '032A52');
    }

    /** @param array<string, mixed> $deck */
    private function header(array $deck, string $eyebrow, string $title, string $subtitle): string
    {
        $period = (string) data_get($deck, 'meta.period_label', '-');
        $scope = (string) data_get($deck, 'meta.scope_label', 'Area 6');
        $xml = $this->shape(0, 0, self::WIDTH, 0.08, '16B8F3', '16B8F3');
        $xml .= $this->text($eyebrow, 0.7, 0.34, 9.0, 0.28, 12.5, '0866D4', true);
        $xml .= $this->text($title, 0.7, 0.7, 12.0, 0.58, 28, '07192E', true);
        $xml .= $this->text($subtitle, 0.72, 1.31, 12.2, 0.32, 12, '607086');
        $xml .= $this->picture(15.38, 0.4, 1.65, 0.48, 'rId2', 'BRI');
        $xml .= $this->picture(17.3, 0.39, 1.35, 0.5, 'rId3', 'Danantara');
        $xml .= $this->text("{$scope}\n{$period}", 14.35, 0.98, 4.35, 0.58, 10.5, '52647B', true, 'r');

        return $xml;
    }

    /** @param array<string, mixed> $deck */
    private function footer(array $deck, int $slideNumber, bool $dark = false): string
    {
        $color = $dark ? '94AAC2' : '718198';
        $line = $dark ? '214C74' : 'DCE6F2';
        $xml = $this->shape(0.7, 10.55, 18.6, 0.015, $line, $line);
        $xml .= $this->text((string) data_get($deck, 'meta.source_note', ''), 0.72, 10.7, 14.7, 0.25, 9, $color);
        $xml .= $this->text(str_pad((string) $slideNumber, 2, '0', STR_PAD_LEFT), 18.3, 10.66, 0.75, 0.28, 10, $color, true, 'r');

        return $xml;
    }

    /**
     * @param array<int, string> $labels
     * @param array<int, array<string, mixed>> $series
     */
    private function lineChart(array $labels, array $series, float $x, float $y, float $width, float $height, bool $compact = false): string
    {
        $xml = $this->shape($x, $y, $width, $height, 'FFFFFF', 'D4E2F2', 0.04, 800);
        $plotX = $x + 0.55;
        $plotY = $y + 0.55;
        $plotW = $width - 0.9;
        $plotH = $height - 1.35;
        $allValues = [];
        foreach ($series as $item) {
            foreach ((array) ($item['values'] ?? []) as $value) {
                if (is_numeric($value)) {
                    $allValues[] = (float) $value;
                }
            }
        }

        if ($labels === [] || $allValues === []) {
            return $xml . $this->text('Timeseries belum tersedia untuk pilihan ini.', $x + 0.5, $y + ($height / 2) - 0.25, $width - 1, 0.5, 14, '61738A', true, 'ctr');
        }

        $minimum = min($allValues);
        $maximum = max($allValues);
        if ($maximum === $minimum) {
            $maximum += 1;
            $minimum -= 1;
        }
        $padding = ($maximum - $minimum) * 0.12;
        $minimum -= $padding;
        $maximum += $padding;

        for ($grid = 0; $grid <= 4; $grid++) {
            $gridY = $plotY + (($plotH / 4) * $grid);
            $xml .= $this->shape($plotX, $gridY, $plotW, 0.01, 'E4EBF4', 'E4EBF4');
        }

        $pointCount = count($labels);
        $xStep = $pointCount > 1 ? $plotW / ($pointCount - 1) : 0;
        $palette = ['0866D4', '0D9F77', 'E59200', 'D73A49', '6F42C1'];
        foreach (array_slice($series, 0, 5) as $seriesIndex => $item) {
            $values = array_values((array) ($item['values'] ?? []));
            $color = ltrim((string) ($item['color'] ?? $palette[$seriesIndex % count($palette)]), '#');
            $previous = null;
            foreach ($labels as $index => $label) {
                if (!isset($values[$index]) || !is_numeric($values[$index])) {
                    continue;
                }
                $value = (float) $values[$index];
                $pointX = $plotX + ($xStep * $index);
                $pointY = $plotY + $plotH - ((($value - $minimum) / ($maximum - $minimum)) * $plotH);
                if ($previous) {
                    $xml .= $this->line((float) $previous[0], (float) $previous[1], $pointX, $pointY, $color, 2.2);
                }
                $xml .= $this->shape($pointX - 0.055, $pointY - 0.055, 0.11, 0.11, $color, 'FFFFFF', 0.02, 600, 'ellipse');

                $showEveryPoint = $pointCount <= 13 && $seriesIndex < ($compact ? 2 : 3);
                $showLabel = $showEveryPoint
                    || $pointCount <= 7
                    || $index === 0
                    || $index === $pointCount - 1
                    || $index % 2 === 0;
                if ($showLabel && $seriesIndex < ($compact ? 2 : 3)) {
                    $displayValues = array_values((array) ($item['display_values'] ?? []));
                    $display = (string) ($displayValues[$index] ?? $this->formatSeriesValue($item, $value));
                    $valueWidth = $pointCount > 1
                        ? max(0.48, min(1.1, $xStep * 0.96))
                        : 1.1;
                    $valueY = (($seriesIndex + $index) % 2 === 0)
                        ? $pointY - 0.38
                        : $pointY + 0.1;
                    $valueY = max($plotY - 0.02, min($plotY + $plotH - 0.3, $valueY));
                    $xml .= $this->text(
                        $display,
                        $pointX - ($valueWidth / 2),
                        $valueY,
                        $valueWidth,
                        0.22,
                        $compact && $pointCount > 8 ? 7.2 : ($compact ? 8.5 : 9.5),
                        $color,
                        true,
                        'ctr'
                    );
                }
                $previous = [$pointX, $pointY];
            }
        }

        foreach ($labels as $index => $label) {
            if ($pointCount > 13 && $index % 2 === 1 && $index !== $pointCount - 1) {
                continue;
            }
            $pointX = $plotX + ($xStep * $index);
            $labelWidth = $pointCount > 1
                ? max(0.46, min(0.9, $xStep * 0.96))
                : 0.9;
            $xml .= $this->text(
                (string) $label,
                $pointX - ($labelWidth / 2),
                $plotY + $plotH + 0.18,
                $labelWidth,
                0.25,
                $compact && $pointCount > 8 ? 7.2 : ($compact ? 8.5 : 9.5),
                '64758C',
                false,
                'ctr'
            );
        }

        foreach (array_slice($series, 0, 5) as $index => $item) {
            $color = ltrim((string) ($item['color'] ?? $palette[$index % count($palette)]), '#');
            $legendX = $x + 0.55 + (($index % 3) * (($width - 1.1) / 3));
            $legendY = $y + 0.15 + (intdiv($index, 3) * 0.27);
            $xml .= $this->shape($legendX, $legendY + 0.05, 0.12, 0.12, $color, $color, 0.01, 0, 'ellipse');
            $xml .= $this->text((string) ($item['label'] ?? '-'), $legendX + 0.2, $legendY, (($width - 1.1) / 3) - 0.22, 0.22, $compact ? 9 : 10, '41546E', true);
        }

        return $xml;
    }

    /**
     * @param array<int, string> $headers
     * @param array<int, array<int, array<string, mixed>>> $rows
     * @param array<int, float|int> $weights
     */
    private function table(
        array $headers,
        array $rows,
        float $x,
        float $y,
        float $width,
        float $height,
        array $weights,
        float $headerFontSize = 12.5,
        float $bodyFontSize = 12.5
    ): string
    {
        $xml = $this->shape($x, $y, $width, $height, 'FFFFFF', 'D4E2F2', 0.04, 800);
        $sum = max(0.01, array_sum($weights));
        $headerHeight = 0.62;
        $rowHeight = min(0.78, max(0.54, ($height - $headerHeight - 0.16) / max(1, count($rows))));
        $cursorX = $x;
        foreach ($headers as $index => $header) {
            $cellWidth = $width * ((float) ($weights[$index] ?? 1) / $sum);
            $xml .= $this->shape($cursorX, $y, $cellWidth, $headerHeight, 'EAF0F7', 'D4E2F2', 0, 450);
            $xml .= $this->text($header, $cursorX + 0.1, $y + 0.08, $cellWidth - 0.2, $headerHeight - 0.14, $headerFontSize, '344A65', true, $index === 0 ? 'l' : 'ctr', 'mid');
            $cursorX += $cellWidth;
        }

        foreach ($rows as $rowIndex => $row) {
            $cursorX = $x;
            $rowY = $y + $headerHeight + ($rowIndex * $rowHeight);
            foreach ($headers as $cellIndex => $_header) {
                $cellWidth = $width * ((float) ($weights[$cellIndex] ?? 1) / $sum);
                $cell = (array) ($row[$cellIndex] ?? $this->cell('-'));
                $fill = (string) ($cell['fill'] ?? ($rowIndex % 2 === 0 ? 'FFFFFF' : 'F7F9FC'));
                $xml .= $this->shape($cursorX, $rowY, $cellWidth, $rowHeight, $fill, 'E2EAF3', 0, 350);
                $xml .= $this->text(
                    (string) ($cell['text'] ?? '-'),
                    $cursorX + 0.1,
                    $rowY + 0.07,
                    $cellWidth - 0.2,
                    $rowHeight - 0.12,
                    (float) ($cell['size'] ?? $bodyFontSize),
                    (string) ($cell['color'] ?? '344A65'),
                    (bool) ($cell['bold'] ?? false),
                    (string) ($cell['align'] ?? ($cellIndex === 0 ? 'l' : 'r')),
                    'mid'
                );
                $cursorX += $cellWidth;
            }
        }

        return $xml;
    }

    /** @param array<string, mixed> $deck */
    private function usesPrognosa(array $deck): bool
    {
        return (bool) data_get($deck, 'meta.use_prognosa', false)
            && (bool) data_get($deck, 'meta.prognosa.available', false);
    }

    /**
     * @param array<string, mixed> $metric
     * @return array<int, array<string, mixed>>
     */
    private function nativeComparisonRow(
        string $label,
        array $metric,
        bool $inverse = false,
        bool $showRatio = false
    ): array {
        $current = (string) ($metric['current_fmt'] ?? $this->formatAmount($metric['current'] ?? null));
        $prognosa = (string) ($metric['prognosa_fmt'] ?? $this->formatAmount($metric['prognosa'] ?? null));
        if ($showRatio) {
            $current .= "\n" . (string) data_get($metric, 'ratio_positions_fmt.current', '-');
            $prognosa .= "\n" . (string) ($metric['prognosa_ratio_fmt'] ?? '-');
        }

        $deltaCell = fn (mixed $value): array => $inverse
            ? $this->qualityDeltaCell($value)
            : $this->deltaCell($value);

        return [
            $this->cell($label, '12233B', true),
            $this->cell($current, '12233B', true, null, 'r'),
            $deltaCell(data_get($metric, 'deltas.ytd')),
            $deltaCell(data_get($metric, 'deltas.mtd')),
            $this->cell($prognosa, '087D60', true, 'EAF7F3', 'r'),
            $deltaCell($metric['prognosa_delta'] ?? null),
            $this->cell((string) ($metric['rka_fmt'] ?? $this->formatAmount($metric['rka'] ?? null)), '52647B', false, null, 'r'),
            $deltaCell($metric['gap'] ?? null),
            $this->achievementCell($metric['achievement'] ?? null),
        ];
    }

    /**
     * @param array<string, mixed> $deck
     * @param array<int, array<int, array<string, mixed>>> $rows
     */
    private function nativePrognosaTable(
        array $deck,
        array $rows,
        float $x,
        float $y,
        float $width,
        float $height,
        bool $compact = false
    ): string {
        $week = trim((string) data_get($deck, 'meta.prognosa.week_label', ''));
        $forecast = trim((string) data_get($deck, 'meta.prognosa.forecast_date_label', '-'));
        $position = trim((string) data_get(
            $deck,
            'meta.prognosa.comparison_position_label',
            data_get($deck, 'meta.prognosa.position_date_label', '-')
        ));

        return $this->table(
            [
                'SCOPE / PRODUK',
                'POSISI',
                'D YTD',
                'D MTD',
                trim("PROGNOSA {$week}\n{$forecast}"),
                "D PROG\nVS {$position}",
                'RKA',
                'GAP RKA',
                'PENC.',
            ],
            $rows,
            $x,
            $y,
            $width,
            $height,
            [1.75, 1.15, 0.95, 0.95, 1.3, 1.18, 1.08, 1.02, 0.92],
            $compact ? 8.2 : 9.4,
            $compact ? 8.7 : 10.2
        );
    }

    /** @return array<string, mixed> */
    private function cell(string $text, string $color = '344A65', bool $bold = false, ?string $fill = null, string $align = 'l'): array
    {
        return compact('text', 'color', 'bold', 'fill', 'align');
    }

    /** @return array<string, mixed> */
    private function deltaCell(mixed $value): array
    {
        $number = is_numeric($value) ? (float) $value : null;
        $color = $number === null || $number == 0.0 ? '9A6A00' : ($number > 0 ? '078A68' : 'C62828');

        return $this->cell($this->formatSignedAmount($number), $color, true, null, 'r');
    }

    /** @return array<string, mixed> */
    private function qualityDeltaCell(mixed $value): array
    {
        $number = is_numeric($value) ? (float) $value : null;
        $color = $number === null || $number == 0.0 ? '9A6A00' : ($number < 0 ? '078A68' : 'C62828');

        return $this->cell($this->formatSignedAmount($number), $color, true, null, 'r');
    }

    /** @return array<string, mixed> */
    private function achievementCell(mixed $value): array
    {
        $number = is_numeric($value) ? (float) $value : null;
        $color = $number === null ? '52647B' : ($number >= 100 ? '078A68' : ($number >= 95 ? '9A6A00' : 'C62828'));
        $fill = $number === null ? null : ($number >= 100 ? 'EAF7F3' : ($number >= 95 ? 'FFF7E8' : 'FFF0F0'));

        return $this->cell($this->formatPercent($number), $color, true, $fill, 'r');
    }

    private function metricCard(string $label, string $value, string $note, float $x, float $y, float $width, float $height, string $tone): string
    {
        $xml = $this->shape($x, $y, $width, $height, 'FFFFFF', 'D4E2F2', 0.04, 800);
        $xml .= $this->shape($x, $y, 0.07, $height, $tone, $tone);
        $xml .= $this->text($label, $x + 0.25, $y + 0.16, $width - 0.45, 0.24, 12, '63758C', true);
        $xml .= $this->text($value, $x + 0.25, $y + 0.43, $width - 0.45, 0.34, 21, '10233B', true);
        $xml .= $this->text($note, $x + 0.25, $y + 0.82, $width - 0.45, 0.18, 11, '718198');

        return $xml;
    }

    private function callout(string $label, string $text, float $x, float $y, float $width, float $height, string $fill, string $tone): string
    {
        $xml = $this->shape($x, $y, $width, $height, $fill, 'D4E2F2', 0.04, 700);
        $xml .= $this->shape($x, $y, 0.08, $height, $tone, $tone);
        $xml .= $this->text($label, $x + 0.28, $y + 0.18, $width - 0.48, 0.26, 12, $tone, true);
        $xml .= $this->text($text, $x + 0.28, $y + 0.48, $width - 0.48, $height - 0.58, 13, '30445E', true);

        return $xml;
    }

    /**
     * @param callable(): string $content
     */
    private function slide(callable $content, string $background = 'F7FAFE'): string
    {
        $this->shapeId = 1;
        $body = $content();

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<p:sld xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" '
            . 'xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">'
            . '<p:cSld><p:bg><p:bgPr><a:solidFill><a:srgbClr val="' . $background . '"/></a:solidFill><a:effectLst/></p:bgPr></p:bg>'
            . '<p:spTree><p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>'
            . '<p:grpSpPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/><a:chOff x="0" y="0"/><a:chExt cx="0" cy="0"/></a:xfrm></p:grpSpPr>'
            . $body
            . '</p:spTree></p:cSld><p:clrMapOvr><a:masterClrMapping/></p:clrMapOvr></p:sld>';
    }

    private function shape(
        float $x,
        float $y,
        float $width,
        float $height,
        string $fill,
        string $line,
        float $radius = 0,
        int $lineWidth = 0,
        string $geometry = 'rect'
    ): string {
        $id = ++$this->shapeId;
        $preset = $geometry === 'ellipse' ? 'ellipse' : ($radius > 0 ? 'roundRect' : $geometry);
        $lineXml = $lineWidth <= 0
            ? '<a:ln><a:noFill/></a:ln>'
            : '<a:ln w="' . ($lineWidth * 127) . '"><a:solidFill><a:srgbClr val="' . $line . '"/></a:solidFill></a:ln>';

        return '<p:sp><p:nvSpPr><p:cNvPr id="' . $id . '" name="Shape ' . $id . '"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>'
            . '<p:spPr><a:xfrm><a:off x="' . $this->emu($x) . '" y="' . $this->emu($y) . '"/>'
            . '<a:ext cx="' . $this->emu($width) . '" cy="' . $this->emu($height) . '"/></a:xfrm>'
            . '<a:prstGeom prst="' . $preset . '"><a:avLst/></a:prstGeom>'
            . '<a:solidFill><a:srgbClr val="' . $fill . '"/></a:solidFill>' . $lineXml
            . '</p:spPr></p:sp>';
    }

    private function text(
        string $text,
        float $x,
        float $y,
        float $width,
        float $height,
        float $fontSize,
        string $color,
        bool $bold = false,
        string $align = 'l',
        string $anchor = 't'
    ): string {
        $id = ++$this->shapeId;
        $anchor = match (strtolower($anchor)) {
            'mid', 'middle', 'center', 'centre' => 'ctr',
            'bottom' => 'b',
            default => in_array($anchor, ['t', 'ctr', 'b', 'just', 'dist'], true) ? $anchor : 't',
        };
        $paragraphs = '';
        foreach (preg_split('/\R/u', $text) ?: [''] as $line) {
            $paragraphs .= '<a:p><a:pPr algn="' . $align . '"/><a:r><a:rPr lang="id-ID" sz="' . (int) round($fontSize * 100) . '" b="' . ($bold ? '1' : '0') . '">'
                . '<a:solidFill><a:srgbClr val="' . $color . '"/></a:solidFill><a:latin typeface="Inter"/></a:rPr>'
                . '<a:t>' . $this->xml($line) . '</a:t></a:r><a:endParaRPr lang="id-ID" sz="' . (int) round($fontSize * 100) . '"/></a:p>';
        }

        return '<p:sp><p:nvSpPr><p:cNvPr id="' . $id . '" name="Text ' . $id . '"/><p:cNvSpPr txBox="1"/><p:nvPr/></p:nvSpPr>'
            . '<p:spPr><a:xfrm><a:off x="' . $this->emu($x) . '" y="' . $this->emu($y) . '"/>'
            . '<a:ext cx="' . $this->emu($width) . '" cy="' . $this->emu($height) . '"/></a:xfrm>'
            . '<a:prstGeom prst="rect"><a:avLst/></a:prstGeom><a:noFill/><a:ln><a:noFill/></a:ln></p:spPr>'
            . '<p:txBody><a:bodyPr wrap="square" anchor="' . $anchor . '" lIns="0" tIns="0" rIns="0" bIns="0">'
            . '<a:normAutofit fontScale="90000" lnSpcReduction="15000"/></a:bodyPr><a:lstStyle/>'
            . $paragraphs . '</p:txBody></p:sp>';
    }

    private function picture(float $x, float $y, float $width, float $height, string $relationId, string $name): string
    {
        $id = ++$this->shapeId;

        return '<p:pic><p:nvPicPr><p:cNvPr id="' . $id . '" name="' . $this->xml($name) . '"/><p:cNvPicPr><a:picLocks noChangeAspect="1"/></p:cNvPicPr><p:nvPr/></p:nvPicPr>'
            . '<p:blipFill><a:blip r:embed="' . $relationId . '"/><a:stretch><a:fillRect/></a:stretch></p:blipFill>'
            . '<p:spPr><a:xfrm><a:off x="' . $this->emu($x) . '" y="' . $this->emu($y) . '"/>'
            . '<a:ext cx="' . $this->emu($width) . '" cy="' . $this->emu($height) . '"/></a:xfrm>'
            . '<a:prstGeom prst="rect"><a:avLst/></a:prstGeom><a:ln><a:noFill/></a:ln></p:spPr></p:pic>';
    }

    private function line(float $x1, float $y1, float $x2, float $y2, string $color, float $width): string
    {
        $id = ++$this->shapeId;
        $x = min($x1, $x2);
        $y = min($y1, $y2);
        $flipV = (($x1 <= $x2 && $y1 > $y2) || ($x1 > $x2 && $y1 <= $y2)) ? ' flipV="1"' : '';

        return '<p:cxnSp><p:nvCxnSpPr><p:cNvPr id="' . $id . '" name="Line ' . $id . '"/><p:cNvCxnSpPr/><p:nvPr/></p:nvCxnSpPr>'
            . '<p:spPr><a:xfrm' . $flipV . '><a:off x="' . $this->emu($x) . '" y="' . $this->emu($y) . '"/>'
            . '<a:ext cx="' . max(1, $this->emu(abs($x2 - $x1))) . '" cy="' . max(1, $this->emu(abs($y2 - $y1))) . '"/></a:xfrm>'
            . '<a:prstGeom prst="line"><a:avLst/></a:prstGeom><a:ln w="' . (int) round($width * 12700) . '">'
            . '<a:solidFill><a:srgbClr val="' . $color . '"/></a:solidFill><a:round/></a:ln></p:spPr></p:cxnSp>';
    }

    private function slideRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideLayout" Target="../slideLayouts/slideLayout1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/presentation-bri.png"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/presentation-danantara.png"/>'
            . '</Relationships>';
    }

    private function rewritePresentationSlideList(ZipArchive $zip, int $slideCount): void
    {
        $xml = $zip->getFromName('ppt/presentation.xml');
        if (!is_string($xml)) {
            throw new RuntimeException('Struktur presentation.xml tidak ditemukan pada template.');
        }

        $items = '';
        for ($index = 0; $index < $slideCount; $index++) {
            $items .= '<p:sldId id="' . (256 + $index) . '" r:id="rId' . (2 + $index) . '"/>';
        }
        $updated = preg_replace('#<p:sldIdLst>.*?</p:sldIdLst>#s', '<p:sldIdLst>' . $items . '</p:sldIdLst>', $xml, 1);
        if (!is_string($updated) || $updated === $xml && !str_contains($xml, '<p:sldIdLst>')) {
            throw new RuntimeException('Daftar slide pada template tidak dapat diperbarui.');
        }

        $this->put($zip, 'ppt/presentation.xml', $updated);
    }

    private function removeUnusedTemplateSlides(ZipArchive $zip, int $slideCount): void
    {
        $namesToDelete = [];
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = (string) $zip->getNameIndex($index);
            if (
                preg_match('#^ppt/slides/(?:_rels/)?slide(\d+)\.xml(?:\.rels)?$#', $name, $matches)
                && (int) $matches[1] > $slideCount
            ) {
                $namesToDelete[] = $name;
            }
        }
        foreach ($namesToDelete as $name) {
            $this->deleteIfPresent($zip, $name);
        }

        $relationshipsXml = $zip->getFromName('ppt/_rels/presentation.xml.rels');
        if (is_string($relationshipsXml)) {
            $document = new DOMDocument();
            if (@$document->loadXML($relationshipsXml)) {
                $xpath = new \DOMXPath($document);
                $xpath->registerNamespace('r', 'http://schemas.openxmlformats.org/package/2006/relationships');
                $relationshipsToRemove = [];
                foreach ($xpath->query('//r:Relationship') ?: [] as $relationship) {
                    $target = (string) $relationship->attributes?->getNamedItem('Target')?->nodeValue;
                    $type = (string) $relationship->attributes?->getNamedItem('Type')?->nodeValue;
                    if (
                        str_ends_with($type, '/slide')
                        && preg_match('#slides/slide(\d+)\.xml$#', $target, $matches)
                        && (int) $matches[1] > $slideCount
                    ) {
                        $relationshipsToRemove[] = $relationship;
                    }
                }
                foreach ($relationshipsToRemove as $relationship) {
                    $relationship->parentNode?->removeChild($relationship);
                }
                $this->put($zip, 'ppt/_rels/presentation.xml.rels', (string) $document->saveXML());
            }
        }

        $contentTypesXml = $zip->getFromName('[Content_Types].xml');
        if (is_string($contentTypesXml)) {
            $document = new DOMDocument();
            if (@$document->loadXML($contentTypesXml)) {
                $xpath = new \DOMXPath($document);
                $xpath->registerNamespace('ct', 'http://schemas.openxmlformats.org/package/2006/content-types');
                $overridesToRemove = [];
                foreach ($xpath->query('//ct:Override') ?: [] as $override) {
                    $partName = (string) $override->attributes?->getNamedItem('PartName')?->nodeValue;
                    if (
                        preg_match('#^/ppt/slides/slide(\d+)\.xml$#', $partName, $matches)
                        && (int) $matches[1] > $slideCount
                    ) {
                        $overridesToRemove[] = $override;
                    }
                }
                foreach ($overridesToRemove as $override) {
                    $override->parentNode?->removeChild($override);
                }
                $this->put($zip, '[Content_Types].xml', (string) $document->saveXML());
            }
        }
    }

    /** @param array<string, mixed> $deck */
    private function rewriteDocumentMetadata(ZipArchive $zip, array $deck, int $slideCount): void
    {
        $title = (string) data_get($deck, 'meta.title', 'Performance Review');
        $coreXml = $zip->getFromName('docProps/core.xml');
        if (is_string($coreXml)) {
            $document = new DOMDocument();
            if (@$document->loadXML($coreXml)) {
                foreach ($document->getElementsByTagNameNS('http://purl.org/dc/elements/1.1/', 'title') as $node) {
                    $node->nodeValue = $title;
                }
                $this->put($zip, 'docProps/core.xml', (string) $document->saveXML());
            }
        }

        $appXml = $zip->getFromName('docProps/app.xml');
        if (is_string($appXml)) {
            $updated = preg_replace('#<Slides>\d+</Slides>#', '<Slides>' . $slideCount . '</Slides>', $appXml, 1);
            if (is_string($updated)) {
                $this->put($zip, 'docProps/app.xml', $updated);
            }
        }
    }

    private function put(ZipArchive $zip, string $name, string $contents): void
    {
        $this->deleteIfPresent($zip, $name);
        if (!$zip->addFromString($name, $contents)) {
            throw new RuntimeException("Komponen PPTX gagal ditulis: {$name}");
        }
    }

    private function deleteIfPresent(ZipArchive $zip, string $name): void
    {
        if ($zip->locateName($name) !== false) {
            $zip->deleteName($name);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    private function keyedItem(array $rows, string $key): array
    {
        foreach ($rows as $row) {
            if (strtolower((string) ($row['key'] ?? '')) === strtolower($key)) {
                return $row;
            }
        }

        return [];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    private function largestRow(array $rows, string $field): array
    {
        $leader = [];
        foreach ($rows as $row) {
            if ($leader === [] || (float) ($row[$field] ?? 0) > (float) ($leader[$field] ?? 0)) {
                $leader = $row;
            }
        }

        return $leader;
    }

    private function segmentSubtitle(string $segmentKey): string
    {
        return match ($segmentKey) {
            'sme' => 'Kecil Non Cashcoll dan Cashcoll dibaca bersama dengan kualitas per cabang.',
            'consumer' => 'Briguna dan KPR dibaca bersama dengan kualitas per cabang.',
            default => 'Briguna Mikro, Kupedes, KUR Mikro, KUR Kecil, dan KUR KPP tanpa baris kosong.',
        };
    }

    /** @param array<string, mixed> $section */
    private function firstOverview(array $section): array
    {
        return (array) data_get($section, 'overview_rows.0', []);
    }

    /** @param array<string, mixed> $section */
    private function sectionNarrative(array $section): string
    {
        $rows = array_values((array) ($section['overview_rows'] ?? []));
        $area = (array) ($rows[0] ?? []);
        $branches = array_slice($rows, 1);
        usort($branches, fn (array $a, array $b): int => (float) ($b['current'] ?? 0) <=> (float) ($a['current'] ?? 0));
        $leader = (array) ($branches[0] ?? []);
        $position = $this->formatAmount($area['current'] ?? null);
        $achievement = $this->formatPercent($area['achievement'] ?? null);
        $mtd = $this->formatSignedAmount(data_get($area, 'deltas.mtd'));

        return sprintf(
            '%s mencapai %s dengan MTD %s dan pencapaian RKA %s. %s menjadi kontributor nominal terbesar.',
            (string) ($section['scope_label'] ?? 'Scope aktif'),
            $position,
            $mtd,
            $achievement,
            (string) ($leader['label'] ?? 'Belum ada cabang')
        );
    }

    /** @param array<string, mixed> $section */
    private function productNarrative(array $section): string
    {
        $rows = array_values((array) ($section['product_rows'] ?? []));
        $rows = array_values(array_filter($rows, fn (array $row): bool => (float) ($row['current'] ?? 0) > 0));
        usort($rows, fn (array $a, array $b): int => (float) ($b['current'] ?? 0) <=> (float) ($a['current'] ?? 0));
        $leader = (array) ($rows[0] ?? []);

        if (($section['key'] ?? '') === 'funding') {
            return sprintf(
                '%s memberi posisi terbesar %s. Jaga porsi dana murah dan awasi produk dengan tekanan MTD.',
                (string) ($leader['label'] ?? 'Produk utama'),
                $this->formatAmount($leader['current'] ?? null)
            );
        }

        return sprintf(
            '%s memimpin OS %s; rasio SML %s dan NPL %s menjadi lensa kualitas utama.',
            (string) ($leader['label'] ?? 'Produk utama'),
            $this->formatAmount($leader['current'] ?? null),
            $this->formatPercent(data_get($leader, 'sml.ratio')),
            $this->formatPercent(data_get($leader, 'npl.ratio'))
        );
    }

    /**
     * @param array<int, string> $labels
     * @param array<int, array<string, mixed>> $series
     */
    private function trendNarrative(array $labels, array $series): string
    {
        $changes = [];
        foreach ($series as $item) {
            $values = array_values((array) ($item['values'] ?? []));
            if (count($values) < 2 || !is_numeric($values[0]) || !is_numeric(end($values)) || (float) $values[0] == 0.0) {
                continue;
            }
            $changes[] = [
                'label' => (string) ($item['label'] ?? '-'),
                'change' => (((float) end($values) / (float) $values[0]) - 1) * 100,
            ];
        }
        usort($changes, fn (array $a, array $b): int => $b['change'] <=> $a['change']);
        $best = (array) ($changes[0] ?? []);
        $watch = (array) ($changes[count($changes) - 1] ?? []);
        $range = $labels !== [] ? (string) $labels[0] . ' - ' . (string) end($labels) : 'periode tersedia';

        return sprintf(
            '%s memimpin momentum %s; %s berada pada %s dan perlu ditelaah. Basis analisis %s.',
            (string) ($best['label'] ?? 'Indikator utama'),
            $this->formatSignedPercent($best['change'] ?? null),
            (string) ($watch['label'] ?? 'indikator lain'),
            $this->formatSignedPercent($watch['change'] ?? null),
            $range
        );
    }

    /** @param array<int, array<string, mixed>> $strategies */
    private function strategyByTrend(array $strategies, bool $highest): ?array
    {
        $ranked = [];
        foreach ($strategies as $strategy) {
            $trend = str_replace(['%', '+', '.', ','], ['', '', '', '.'], (string) ($strategy['trend'] ?? ''));
            if (is_numeric($trend)) {
                $ranked[] = ['value' => (float) $trend, 'item' => $strategy];
            }
        }
        usort($ranked, fn (array $a, array $b): int => $highest ? $b['value'] <=> $a['value'] : $a['value'] <=> $b['value']);

        return isset($ranked[0]) ? (array) $ranked[0]['item'] : null;
    }

    /** @param array<string, mixed> $section */
    private function sectionAction(array $section, bool $funding): string
    {
        $rows = array_values((array) ($section['overview_rows'] ?? []));
        $branches = array_slice($rows, 1);
        usort($branches, fn (array $a, array $b): int => (float) data_get($a, 'deltas.mtd', 0) <=> (float) data_get($b, 'deltas.mtd', 0));
        $watch = (array) ($branches[0] ?? []);

        return sprintf(
            '%s: intervensi %s dengan MTD %s. %s',
            (string) ($section['scope_label'] ?? 'Scope aktif'),
            (string) ($watch['label'] ?? 'unit prioritas'),
            $this->formatSignedAmount(data_get($watch, 'deltas.mtd')),
            $funding ? 'Utamakan CASA dan penutupan gap RKA.' : 'Pastikan pertumbuhan tidak meningkatkan SML/NPL.'
        );
    }

    /** @param array<int, array<string, mixed>> $strategies */
    private function strategyAction(array $strategies): string
    {
        $watch = $this->strategyByTrend($strategies, false);

        return $watch
            ? 'Pulihkan ' . (string) ($watch['title'] ?? 'strategi terlemah') . ' yang bergerak ' . (string) ($watch['trend'] ?? '-') . ', tanpa kehilangan momentum kanal terbaik.'
            : 'Fokuskan akuisisi digital pada kanal dengan data aktif dan pertumbuhan terukur.';
    }

    private function formatSeriesValue(array $series, mixed $value): string
    {
        if (!is_numeric($value)) {
            return '-';
        }
        if (($series['format'] ?? '') === 'percent') {
            return $this->formatPercent((float) $value);
        }

        // Timeseries deck stores currency series in Rp juta.
        return $this->formatAmount((float) $value * 1000000);
    }

    private function formatAmount(mixed $value): string
    {
        if (!is_numeric($value)) {
            return '-';
        }
        $number = (float) $value;
        $absolute = abs($number);
        if ($absolute >= 1_000_000_000_000) {
            return 'Rp' . $this->number($number / 1_000_000_000_000, 2) . ' T';
        }
        if ($absolute >= 1_000_000_000) {
            return 'Rp' . $this->number($number / 1_000_000_000, 2) . ' M';
        }
        if ($absolute >= 1_000_000) {
            return 'Rp' . $this->number($number / 1_000_000, 2) . ' Jt';
        }

        return 'Rp' . $this->number($number, 0);
    }

    private function formatSignedAmount(mixed $value): string
    {
        if (!is_numeric($value)) {
            return '-';
        }
        $number = (float) $value;

        return ($number > 0 ? '+' : '') . $this->formatAmount($number);
    }

    private function formatPercent(mixed $value): string
    {
        return is_numeric($value) ? $this->number((float) $value, 2) . '%' : '-';
    }

    private function formatSignedPercent(mixed $value): string
    {
        if (!is_numeric($value)) {
            return '-';
        }
        $number = (float) $value;

        return ($number > 0 ? '+' : '') . $this->number($number, 2) . '%';
    }

    private function integer(mixed $value): string
    {
        return is_numeric($value) ? number_format((float) $value, 0, ',', '.') : '-';
    }

    private function number(float $value, int $decimals): string
    {
        return number_format($value, $decimals, ',', '.');
    }

    private function emu(float $inches): int
    {
        return max(0, (int) round($inches * self::EMU));
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
