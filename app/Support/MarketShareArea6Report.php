<?php

namespace App\Support;

final class MarketShareArea6Report
{
    private const DEFAULT_SEGMENT = 'dpk';

    private const SEGMENTS = [
        'dpk' => ['label' => 'Total DPK', 'kind' => 'deposit', 'group' => 'Simpanan'],
        'tabungan' => ['label' => 'Tabungan', 'kind' => 'deposit', 'group' => 'Simpanan'],
        'giro' => ['label' => 'Giro', 'kind' => 'deposit', 'group' => 'Simpanan'],
        'deposito' => ['label' => 'Deposito', 'kind' => 'deposit', 'group' => 'Simpanan'],
        'casa' => ['label' => 'CASA', 'kind' => 'deposit', 'group' => 'Simpanan'],
        'pinjaman_total' => ['label' => 'Total Pinjaman', 'kind' => 'loan', 'group' => 'Pinjaman'],
        'umkm' => ['label' => 'Pinjaman UMKM', 'kind' => 'loan', 'group' => 'Pinjaman'],
        'konsumer' => ['label' => 'Pinjaman Konsumer', 'kind' => 'loan', 'group' => 'Pinjaman'],
        'briguna' => ['label' => 'Pinjaman BRIGUNA', 'kind' => 'loan', 'group' => 'Pinjaman'],
        'kpr' => ['label' => 'Pinjaman KPR', 'kind' => 'loan', 'group' => 'Pinjaman'],
        'quality_umkm' => ['label' => 'Kualitas UMKM', 'kind' => 'quality', 'group' => 'Kualitas Kredit'],
        'quality_konsumer' => ['label' => 'Kualitas Konsumer', 'kind' => 'quality', 'group' => 'Kualitas Kredit'],
        'quality_total' => ['label' => 'Kualitas Total Pinjaman', 'kind' => 'quality', 'group' => 'Kualitas Kredit'],
    ];

    private const ROW_BLOCKS = [
        'dpk' => <<<'ROWS'
KC Madiun|4,039|3,975|4,205|5.8%|13,586|14,098|14,362|1.9%|9,547|10,123|10,156|0.3%|29.7%|28.2%|29.3%|-0.44%|1.09%
KC Magetan|2,608|2,686|2,729|1.6%|5,519|5,712|5,848|2.4%|2,912|3,025|3,119|3.1%|47.2%|47.0%|46.7%|-0.58%|-0.37%
KC Ngawi|2,551|2,670|2,668|-0.1%|5,612|5,868|5,991|2.1%|3,061|3,198|3,323|3.9%|45.4%|45.5%|44.5%|-0.92%|-0.97%
KC Ponorogo|4,305|4,314|4,538|5.2%|9,706|9,749|10,260|5.2%|5,400|5,435|5,722|5.3%|44.4%|44.3%|44.2%|-0.13%|-0.02%
Area 6|13,502|13,646|14,140|3.6%|34,423|35,427|36,461|2.9%|20,921|21,781|22,321|2.5%|39.2%|38.5%|38.8%|-0.44%|0.26%
ROWS,
        'tabungan' => <<<'ROWS'
KC Madiun|2,875|2,936|3,053|4.0%|8,324|8,780|8,883|1.2%|5,449|5,844|5,830|-0.2%|34.54%|33.44%|34.37%|-0.17%|0.93%
KC Magetan|2,084|2,177|2,224|2.1%|3,920|4,224|4,216|-0.2%|1,837|2,048|1,992|-2.7%|53.15%|51.53%|52.74%|-0.41%|1.21%
KC Ngawi|2,055|2,172|2,189|0.8%|3,973|4,344|4,317|-0.6%|1,918|2,172|2,128|-2.0%|51.72%|50.01%|50.71%|-1.01%|0.71%
KC Ponorogo|3,682|3,701|3,914|5.7%|7,460|7,627|7,928|3.9%|3,778|3,925|4,014|2.3%|49.36%|48.53%|49.37%|0.00%|0.83%
Area 6|10,697|10,987|11,380|3.6%|23,677|24,976|25,344|1.5%|12,981|13,989|13,965|-0.2%|45.2%|44.0%|44.9%|-0.28%|0.91%
ROWS,
        'giro' => <<<'ROWS'
KC Madiun|271|202|306|51.9%|2,172|2,390|2,458|2.8%|1,901|2,188|2,152|-1.7%|12.5%|8.4%|12.5%|0.00%|4.02%
KC Magetan|41|29|30|3.3%|365|289|337|16.4%|324|260|307|17.8%|11.3%|10.1%|9.0%|-2.30%|-1.14%
KC Ngawi|29|36|30|-17.1%|475|304|467|53.8%|446|268|437|63.3%|6.0%|11.8%|6.4%|0.32%|-5.44%
KC Ponorogo|77|62|81|31.5%|515|490|589|20.1%|438|429|508|18.5%|15.0%|12.6%|13.8%|-1.19%|1.19%
Area 6|417|328|447|36.2%|3,527|3,473|3,850|10.9%|3,110|3,145|3,403|8.2%|11.8%|9.5%|11.6%|-0.22%|2.16%
ROWS,
        'deposito' => <<<'ROWS'
KC Madiun|893|838|846|1.0%|3,090|2,928|3,021|3.2%|2,198|2,090|2,175|4.1%|28.88%|28.62%|28.01%|-0.87%|-0.61%
KC Magetan|483|480|475|-1.1%|1,234|1,198|1,295|8.1%|751|718|820|14.3%|39.13%|40.10%|36.68%|-2.45%|-3.42%
KC Ngawi|467|462|449|-2.8%|1,164|1,220|1,207|-1.0%|697|758|758|0.0%|40.12%|37.85%|37.18%|-2.94%|-0.68%
KC Ponorogo|546|551|543|-1.5%|1,730|1,632|1,743|6.8%|1,184|1,081|1,200|11.0%|31.55%|33.76%|31.14%|-0.41%|-2.62%
Area 6|2,388|2,331|2,313|-0.8%|7,218|6,978|7,266|4.1%|4,830|4,647|4,953|6.6%|33.1%|33.4%|31.8%|-1.26%|-1.58%
ROWS,
        'casa' => <<<'ROWS'
KC Madiun|3,146|3,137|3,359|7.08%|10,496|11,170|11,341|1.53%|7,350|8,033|7,981|-0.6%|30.0%|28.1%|29.6%|-0.35%|1.54%
KC Magetan|2,125|2,206|2,254|2.16%|4,286|4,514|4,553|0.86%|2,161|2,308|2,299|-0.4%|49.6%|48.9%|49.5%|-0.08%|0.63%
KC Ngawi|2,084|2,208|2,219|0.49%|4,448|4,648|4,784|2.93%|2,364|2,440|2,565|5.1%|46.8%|47.5%|46.4%|-0.46%|-1.13%
KC Ponorogo|3,760|3,763|3,995|6.16%|7,976|8,117|8,517|4.93%|4,216|4,354|4,522|3.9%|47.1%|46.4%|46.9%|-0.24%|0.54%
Area 6|11,114|11,315|11,827|4.5%|27,205|28,449|29,195|2.6%|16,091|17,135|17,368|1.4%|40.9%|39.8%|40.5%|-0.34%|0.74%
ROWS,
        'pinjaman_total' => <<<'ROWS'
KC Madiun|4,563|4,482|4,511|(52)|-1.14%|29|0.64%|10,593|10,915|10,975|382|3.61%|60|0.55%|43.08%|41.06%|41.10%|-1.98%|0.04%
KC Magetan|3,278|3,215|3,231|(47)|-1.44%|16|0.50%|6,486|6,491|6,566|80|1.24%|75|1.16%|50.54%|49.53%|49.20%|-1.34%|-0.32%
KC Ngawi|3,187|3,181|3,208|21|0.67%|27|0.86%|6,886|6,876|6,979|93|1.35%|103|1.50%|46.29%|46.26%|45.97%|-0.31%|-0.29%
KC Ponorogo|4,534|4,449|4,431|(103)|-2.27%|(18)|-0.41%|7,915|7,902|7,871|(44)|-0.55%|(31)|-0.39%|57.28%|56.31%|56.29%|-0.99%|-0.01%
Area 6|15,562|15,327|15,381|(181)|-1.16%|54|0.35%|31,879|32,183|32,391|512|1.61%|208|0.65%|48.82%|47.62%|47.48%|-1.33%|-0.14%
ROWS,
        'umkm' => <<<'ROWS'
KC Madiun|3,495|3,357|3,369|(126)|-3.60%|12|0.34%|9,197|5,690|5,666|(3,531)|-38.39%|(24)|-0.43%|62.80%|59.00%|59.46%|-3.34%|0.46%
KC Magetan|2,801|2,713|2,719|(82)|-2.93%|6|0.22%|9,197|4,288|4,321|(4,876)|-53.02%|32|0.76%|63.84%|63.26%|62.92%|-0.92%|-0.34%
KC Ngawi|2,787|2,774|2,795|8|0.29%|22|0.78%|9,197|4,527|4,593|(4,604)|-50.06%|66|1.46%|60.79%|61.27%|60.86%|0.07%|-0.41%
KC Ponorogo|4,201|4,110|4,091|(110)|-2.63%|(19)|-0.46%|9,197|5,596|5,545|(3,651)|-39.70%|(51)|-0.91%|73.89%|73.44%|73.77%|-0.12%|0.33%
Area 6|13,283|12,953|12,973|(310)|-2.33%|20|0.16%|20,222|20,102|20,125|(97)|-0.48%|23|0.12%|65.69%|64.44%|64.46%|-1.22%|0.03%
ROWS,
        'konsumer' => <<<'ROWS'
KC Madiun|1,069|1,125|1,142|73|6.88%|17|1.53%|5,028|5,224|5,309|281|5.59%|85|1.62%|21.25%|21.53%|21.51%|0.26%|-0.02%
KC Magetan|478|502|512|35|7.27%|10|1.99%|2,099|2,203|2,245|147|6.98%|43|1.95%|22.75%|22.80%|22.81%|0.06%|0.01%
KC Ngawi|400|408|413|13|3.26%|6|1.42%|2,301|2,349|2,386|85|3.70%|37|1.58%|17.40%|17.35%|17.32%|-0.07%|-0.03%
KC Ponorogo|332|339|340|8|2.29%|1|0.19%|2,229|2,306|2,326|97|4.35%|20|0.87%|14.91%|14.72%|14.62%|-0.29%|-0.10%
Area 6|2,279|2,374|2,408|129|5.65%|34|1.42%|11,657|12,082|12,266|610|5.23%|185|1.53%|19.55%|19.65%|19.63%|0.08%|-0.02%
ROWS,
        'briguna' => <<<'ROWS'
KC Madiun|826|861|866|40|4.89%|5|0.64%|4,278|4,117|4,190|(88)|-2.05%|74|1.79%|20.85%|20.91%|20.68%|-0.18%|-0.24%
KC Magetan|475|500|510|35|7.38%|10|2.02%|4,278|2,009|2,054|(2,224)|-52.00%|44|2.20%|24.87%|24.88%|24.84%|-0.03%|-0.05%
KC Ngawi|397|405|411|13|3.35%|6|1.47%|4,278|2,134|2,171|(2,107)|-49.26%|36|1.71%|19.02%|18.96%|18.91%|-0.11%|-0.05%
KC Ponorogo|310|319|322|12|4.03%|3|1.08%|4,278|2,057|2,084|(2,194)|-51.28%|27|1.32%|15.71%|15.50%|15.46%|-0.25%|-0.04%
Area 6|2,008|2,084|2,109|101|5.04%|25|1.20%|9,931|10,317|10,499|568|5.71%|182|1.76%|20.22%|20.20%|20.09%|-0.13%|-0.11%
ROWS,
        'kpr' => <<<'ROWS'
KC Madiun|243|264|276|33|13.65%|12|4.46%|1,641|1,108|1,119|(522)|-31.82%|11|0.98%|22.73%|23.82%|24.64%|1.91%|0.82%
KC Magetan|3|2|2|(0)|-13.67%|(0)|-3.49%|1,641|193|192|(1,449)|-88.30%|(1)|-0.73%|1.35%|1.18%|1.15%|-0.20%|-0.03%
KC Ngawi|3|3|3|(0)|-9.00%|(0)|-4.88%|1,641|215|216|(1,425)|-86.86%|1|0.26%|1.41%|1.33%|1.27%|-0.14%|-0.07%
KC Ponorogo|23|21|18|(5)|-21.60%|(3)|-13.58%|1,641|248|241|(1,399)|-85.29%|(7)|-2.88%|8.82%|8.27%|7.36%|-1.46%|-0.91%
Area 6|271|290|298|28|10.19%|9|3.03%|1,725|1,765|1,767|42|2.44%|3|0.16%|15.69%|16.41%|16.88%|1.19%|0.47%
ROWS,
        'quality_umkm' => <<<'ROWS'
KC Madiun|223|6.61%|193|5.72%|-0.92%|2.13%|0.18%|1.31%|304|5.36%|287|5.07%|-0.52%|1.66%|0.28%|1.14%|73.28%|67.02%|-7.07%|0.98%|-1.39%|0.94%
KC Magetan|168|6.18%|101|3.71%|-0.91%|0.24%|-0.24%|0.09%|270|6.25%|164|3.79%|-0.24%|0.42%|0.14%|0.51%|62.27%|61.47%|-7.59%|-4.09%|-4.34%|-8.14%
KC Ngawi|127|4.54%|89|3.18%|-0.80%|1.40%|0.23%|0.93%|236|5.13%|162|3.52%|-0.34%|1.20%|-0.03%|1.20%|53.86%|55.00%|-5.52%|8.21%|2.64%|-4.28%
KC Ponorogo|299|7.31%|235|5.74%|0.09%|2.42%|0.00%|1.29%|398|7.18%|304|5.49%|0.37%|1.34%|0.16%|0.52%|75.17%|77.17%|-3.19%|17.90%|-1.38%|11.40%
Area 6|817|6.30%|617|4.76%|-0.59%|1.66%|0.04%|0.96%|1,207|6.00%|917|4.56%|-0.18%|1.20%|0.14%|0.84%|67.65%|67.28%|-5.49%|6.70%|-1.20%|1.60%
ROWS,
        'quality_konsumer' => <<<'ROWS'
KC Madiun|17|1.45%|19|1.70%|0.21%|0.48%|-0.17%|0.24%|411|3.27%|121|0.96%|2.23%|0.20%|0.31%|0.16%|4.04%|16.06%|-5.34%|3.40%|-0.68%|0.33%
KC Magetan|1|0.25%|2|0.42%|-0.17%|0.30%|-0.76%|0.16%|45|2.11%|35|1.66%|0.05%|0.48%|0.18%|0.40%|2.79%|6.07%|-1.93%|3.59%|-9.60%|1.15%
KC Ngawi|3|0.67%|2|0.40%|0.37%|0.17%|0.24%|0.12%|44|0.83%|51|0.96%|0.10%|0.50%|0.18%|0.06%|6.27%|3.22%|3.29%|-0.48%|1.39%|0.92%
KC Ponorogo|3|0.92%|3|0.81%|0.11%|-0.22%|-0.63%|-0.22%|59|1.18%|72|1.42%|-0.29%|-0.09%|0.03%|0.19%|5.28%|3.83%|1.77%|-0.48%|-3.66%|-1.70%
Area 6|24|0.99%|26|1.08%|0.15%|0.29%|-0.29%|0.13%|560|2.23%|279|1.11%|1.09%|0.24%|0.21%|0.17%|4.25%|9.30%|-2.13%|1.53%|-1.55%|0.10%
ROWS,
        'quality_total' => <<<'ROWS'
KC Madiun|239|5.30%|212|4.70%|-0.76%|1.67%|0.08%|1.03%|715|3.92%|408|2.24%|1.47%|0.71%|0.32%|0.49%|33.46%|51.94%|-25.47%|4.66%|-1.13%|1.88%
KC Magetan|169|5.24%|103|3.18%|-0.88%|0.21%|-0.34%|0.09%|315|4.88%|199|3.08%|-0.21%|0.40%|0.15%|0.47%|53.75%|51.63%|-7.78%|-5.20%|-5.56%|-7.77%
KC Ngawi|130|4.04%|91|2.82%|-0.67%|1.23%|0.23%|0.83%|280|2.83%|213|2.15%|-0.07%|0.84%|0.13%|0.60%|46.35%|42.59%|-5.29%|3.98%|1.26%|1.44%
KC Ponorogo|302|6.82%|238|5.36%|0.07%|2.21%|-0.05%|1.17%|457|4.32%|376|3.55%|0.08%|0.67%|0.11%|0.37%|66.09%|63.17%|0.21%|17.75%|-1.65%|8.57%
Area 6|841|5.46%|643|4.18%|-0.53%|1.42%|-0.02%|0.83%|1,767|3.91%|1,196|2.65%|0.58%|0.69%|0.21%|0.49%|47.56%|53.76%|-12.63%|6.57%|-1.89%|1.95%
ROWS,
    ];

    public static function payload(?string $selectedSegment = null): array
    {
        $segmentKey = array_key_exists((string) $selectedSegment, self::SEGMENTS)
            ? (string) $selectedSegment
            : self::DEFAULT_SEGMENT;
        $segments = [];

        foreach (self::SEGMENTS as $key => $segment) {
            $segments[$key] = [
                'key' => $key,
                'label' => $segment['label'],
                'kind' => $segment['kind'],
                'group' => $segment['group'],
            ];
        }

        $selected = $segments[$segmentKey];

        $rows = self::parseRows(self::ROW_BLOCKS[$segmentKey] ?? '');

        return [
            'title' => 'Marketshare - Area 6',
            'subtitle' => 'Cuplikan Report Market Share Umum RO Malang periode Mei 2026, difilter untuk KC Madiun, KC Magetan, KC Ngawi, dan KC Ponorogo.',
            'period' => 'Mei 2026',
            'unit' => 'Rp dalam Miliar',
            'source' => 'Report Market Share Umum RO Malang Mei 2026.pdf',
            'segments' => $segments,
            'selected' => $selected,
            'headers' => self::headers($selected['kind']),
            'rows' => $rows,
            'insights' => self::insights($selected['kind'], $rows),
        ];
    }

    /**
     * @param  array<int, array{branch: string, values: array<int, string>, total: bool}>  $rows
     */
    public static function insights(string $kind, array $rows): array
    {
        $totalRow = collect($rows)->firstWhere('total', true) ?? ($rows[0] ?? ['values' => []]);
        $totalValues = $totalRow['values'] ?? [];

        if ($kind === 'quality') {
            $cards = [
                ['label' => 'SML BRI', 'value' => $totalValues[1] ?? '-', 'icon' => 'fas fa-exclamation-circle'],
                ['label' => 'NPL BRI', 'value' => $totalValues[3] ?? '-', 'icon' => 'fas fa-shield-alt'],
                ['label' => 'Market Share SML', 'value' => $totalValues[16] ?? '-', 'icon' => 'fas fa-chart-area'],
                ['label' => 'Market Share NPL', 'value' => $totalValues[17] ?? '-', 'icon' => 'fas fa-chart-line'],
            ];
            $series = array_map(static fn (array $row): array => [
                'branch' => $row['branch'],
                'primary' => self::percentageValue($row['values'][16] ?? null),
                'secondary' => self::percentageValue($row['values'][17] ?? null),
            ], array_values(array_filter($rows, static fn (array $row): bool => ! $row['total'])));

            return [
                'cards' => $cards,
                'chart' => [
                    'title' => 'Perbandingan market share kualitas',
                    'primary_label' => 'SML',
                    'secondary_label' => 'NPL',
                    'rows' => $series,
                ],
            ];
        }

        $marketOffset = $kind === 'loan' ? 14 : 12;
        $industryCurrent = $kind === 'loan' ? 9 : 6;
        $briYtd = $kind === 'loan' ? 6 : 3;
        $cards = [
            ['label' => 'OS BRI', 'value' => $totalValues[2] ?? '-', 'icon' => 'fas fa-university'],
            ['label' => 'Total Industri', 'value' => $totalValues[$industryCurrent] ?? '-', 'icon' => 'fas fa-city'],
            ['label' => 'Market Share', 'value' => $totalValues[$marketOffset + 2] ?? '-', 'icon' => 'fas fa-bullseye'],
            ['label' => 'Pertumbuhan YTD BRI', 'value' => $totalValues[$briYtd] ?? '-', 'icon' => 'fas fa-chart-line'],
        ];
        $series = array_map(static fn (array $row): array => [
            'branch' => $row['branch'],
            'primary' => self::percentageValue($row['values'][$marketOffset + 2] ?? null),
            'secondary' => self::percentageValue($row['values'][$marketOffset + 1] ?? null),
        ], array_values(array_filter($rows, static fn (array $row): bool => ! $row['total'])));

        return [
            'cards' => $cards,
            'chart' => [
                'title' => 'Market share per cabang',
                'primary_label' => 'Mei 2026',
                'secondary_label' => 'Desember 2025',
                'rows' => $series,
            ],
        ];
    }

    private static function percentageValue(?string $value): ?float
    {
        if ($value === null || trim($value) === '-' || trim($value) === '') {
            return null;
        }

        return (float) str_replace(['%', ','], ['', ''], $value);
    }

    private static function headers(string $kind): array
    {
        if ($kind === 'quality') {
            return [
                'groups' => [
                    ['label' => 'BRI', 'span' => 8],
                    ['label' => 'Industri', 'span' => 8],
                    ['label' => 'Market Share', 'span' => 6],
                ],
                'columns' => [
                    'SML Rp', 'SML %', 'NPL Rp', 'NPL %', 'YoY SML', 'YoY NPL', 'YTD SML', 'YTD NPL',
                    'SML Rp', 'SML %', 'NPL Rp', 'NPL %', 'YoY SML', 'YoY NPL', 'YTD SML', 'YTD NPL',
                    'SML', 'NPL', 'YoY SML', 'YoY NPL', 'YTD SML', 'YTD NPL',
                ],
            ];
        }

        if ($kind === 'loan') {
            return [
                'groups' => [
                    ['label' => 'BRI', 'span' => 7],
                    ['label' => 'Industri', 'span' => 7],
                    ['label' => 'Market Share', 'span' => 5],
                ],
                'columns' => [
                    'Mei-25', 'Dec-25', 'Mei-26', 'YoY Rp', 'YoY %', 'YTD Rp', 'YTD %',
                    'Mei-25', 'Dec-25', 'Mei-26', 'YoY Rp', 'YoY %', 'YTD Rp', 'YTD %',
                    'Mei-25', 'Dec-25', 'Mei-26', 'YoY', 'YTD',
                ],
            ];
        }

        return [
            'groups' => [
                ['label' => 'BRI', 'span' => 4],
                ['label' => 'Total Industri', 'span' => 4],
                ['label' => 'Industri di Luar BRI', 'span' => 4],
                ['label' => 'Market Share', 'span' => 5],
            ],
            'columns' => [
                'Mei-25', 'Dec-25', 'Mei-26', 'YTD %',
                'Mei-25', 'Dec-25', 'Mei-26', 'YTD %',
                'Mei-25', 'Dec-25', 'Mei-26', 'YTD %',
                'Mei-25', 'Dec-25', 'Mei-26', 'YoY', 'YTD',
            ],
        ];
    }

    private static function parseRows(string $block): array
    {
        return collect(preg_split('/\R/', trim($block)))
            ->filter()
            ->map(function (string $line): array {
                $parts = explode('|', $line);

                return [
                    'branch' => array_shift($parts),
                    'values' => $parts,
                    'total' => str_starts_with($line, 'Area 6|'),
                ];
            })
            ->values()
            ->all();
    }
}
