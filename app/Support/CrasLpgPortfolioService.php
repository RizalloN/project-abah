<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CrasLpgPortfolioService
{
    private const TABLE = 'cras';

    private const COLOR_DEFINITIONS = [
        'hijau' => [
            'label' => 'Hijau',
            'meaning' => 'Prospektif',
            'requirement' => 'Wajib memenuhi seluruh syarat SAC umum',
        ],
        'hijau_muda' => [
            'label' => 'Hijau Muda',
            'meaning' => 'Netral',
            'requirement' => 'Wajib memenuhi seluruh syarat SAC umum',
        ],
        'kuning' => [
            'label' => 'Kuning',
            'meaning' => 'Selektif',
            'requirement' => 'Wajib memenuhi seluruh syarat SAC umum dan SAC khusus',
        ],
        'merah' => [
            'label' => 'Merah',
            'meaning' => 'Sangat Selektif',
            'requirement' => 'Wajib memenuhi seluruh syarat SAC umum dan SAC khusus',
        ],
        'unmapped' => [
            'label' => 'Belum Terpetakan',
            'meaning' => 'Perlu Validasi',
            'requirement' => 'Subsektor belum memiliki pasangan lengkap pada referensi LPG',
        ],
    ];

    public function __construct(private readonly CrasLpgReference $reference) {}

    public function payload(array $input, string $period, string $region): array
    {
        if (! $this->reference->ready()) {
            return [
                'ready' => false,
                'message' => 'Referensi sektor dan warna SAC LPG belum tersedia.',
                'filters' => ['selected' => [], 'options' => []],
                'rows' => [],
                'summary' => [],
                'coverage' => [],
                'color_definitions' => self::COLOR_DEFINITIONS,
            ];
        }

        $selected = [
            'segment' => $this->validOption((string) ($input['lpg_segment'] ?? 'all'), ['all', 'micro', 'small']),
            'color' => $this->validOption((string) ($input['lpg_color'] ?? 'all'), array_merge(['all'], array_keys(self::COLOR_DEFINITIONS))),
            'industry_sector' => $this->option((string) ($input['lpg_industry_sector'] ?? 'all')),
            'industry_sub_sector' => $this->option((string) ($input['lpg_industry_sub_sector'] ?? 'all')),
            'sort' => $this->validOption((string) ($input['lpg_sort'] ?? 'os_desc'), ['os_desc', 'npl_ratio_desc']),
        ];

        $baseRows = Cache::remember(
            'cras_lpg_portfolio:v4:'.md5(implode('|', [$period, $region, $this->reference->fingerprint()])),
            now()->addMinutes(15),
            fn (): array => $this->mappedRows($period, $region)
        );

        $options = $this->filterOptions($baseRows);
        $rows = array_values(array_filter($baseRows, function (array $row) use ($selected): bool {
            return ($selected['segment'] === 'all' || $row['segment'] === $selected['segment'])
                && ($selected['color'] === 'all' || $row['color'] === $selected['color'])
                && ($selected['industry_sector'] === 'all' || $row['industry_sector'] === $selected['industry_sector'])
                && ($selected['industry_sub_sector'] === 'all' || $row['industry_sub_sector'] === $selected['industry_sub_sector']);
        }));

        usort($rows, function (array $left, array $right): int {
            $segmentOrder = ['micro' => 0, 'small' => 1];
            $colorOrder = ['hijau' => 0, 'hijau_muda' => 1, 'kuning' => 2, 'merah' => 3, 'unmapped' => 4];

            return ($segmentOrder[$left['segment']] ?? 9) <=> ($segmentOrder[$right['segment']] ?? 9)
                ?: ($colorOrder[$left['color']] ?? 9) <=> ($colorOrder[$right['color']] ?? 9)
                ?: $right['baki_debet'] <=> $left['baki_debet'];
        });

        $totals = $this->sumRows($rows);
        $mappedRows = array_values(array_filter($rows, fn (array $row): bool => $row['color'] !== 'unmapped'));
        $mappedTotals = $this->sumRows($mappedRows);
        $industryRows = $this->industryRows($rows, $selected['sort']);

        return [
            'ready' => true,
            'message' => '',
            'filters' => [
                'selected' => $selected,
                'options' => $options,
            ],
            'rows' => $rows,
            'industry_rows' => $industryRows,
            'summary' => $this->colorSummary($rows),
            'metrics' => $totals,
            'risk_threshold' => 5.0,
            'coverage' => [
                'eligible_rows' => (int) $totals['source_rows'],
                'mapped_rows' => (int) $mappedTotals['source_rows'],
                'unmapped_rows' => (int) ($totals['source_rows'] - $mappedTotals['source_rows']),
                'eligible_os' => $totals['baki_debet'],
                'mapped_os' => $mappedTotals['baki_debet'],
                'mapping_ratio' => $this->percentage($mappedTotals['baki_debet'], $totals['baki_debet']),
            ],
            'color_definitions' => self::COLOR_DEFINITIONS,
            'scope_note' => 'Rekening aktif Micro non-Briguna Mikro dan Small sesuai klasifikasi LPG; Consumer, Briguna, Medium, dan Commercial tidak dihitung.',
            'reference' => $this->reference->source(),
        ];
    }

    private function mappedRows(string $period, string $region): array
    {
        $query = DB::table(self::TABLE)->where('cras_periode', $period);
        $this->applyRegionFilter($query, $region);
        $this->applyEligibleScope($query);

        $sourceRows = DB::connection()->getDriverName() === 'mysql'
            ? $this->aggregateWithSql($query)
            : $this->aggregateInPhp($query);
        $rows = [];
        foreach ($sourceRows as $sourceRow) {
            $sourceRow = (array) $sourceRow;
            $segment = $this->reference->segmentKey((string) ($sourceRow['segmen'] ?? ''));
            if ($segment === null) {
                continue;
            }
            $mapping = $this->reference->resolve((string) ($sourceRow['sub_sektor_ekonomi'] ?? ''), $segment);
            $color = (string) ($mapping['color'] ?? '');
            if (! isset(self::COLOR_DEFINITIONS[$color])) {
                $color = 'unmapped';
            }

            $metrics = [
                'source_rows' => (int) ($sourceRow['source_rows'] ?? 0),
                'plafond' => round((float) ($sourceRow['plafond'] ?? 0), 2),
                'baki_debet' => round((float) ($sourceRow['baki_debet'] ?? 0), 2),
                'sml' => round((float) ($sourceRow['sml'] ?? 0), 2),
                'npl' => round((float) ($sourceRow['npl'] ?? 0), 2),
                'jumlah_debitur' => round((float) ($sourceRow['jumlah_debitur'] ?? 0), 2),
                'jumlah_rekening' => round((float) ($sourceRow['jumlah_rekening'] ?? 0), 2),
            ];
            $metrics['sml_ratio'] = $this->percentage($metrics['sml'], $metrics['baki_debet']);
            $metrics['npl_ratio'] = $this->percentage($metrics['npl'], $metrics['baki_debet']);

            $rows[] = [
                'segment' => $segment,
                'segment_label' => $segment === 'micro' ? 'Micro' : 'Small',
                'source_sector' => trim((string) ($sourceRow['sektor_ekonomi'] ?? '')) ?: '-',
                'source_sub_sector' => trim((string) ($sourceRow['sub_sektor_ekonomi'] ?? '')) ?: '-',
                'industry_sector' => trim((string) ($mapping['industry_sector'] ?? '')) ?: 'Belum Terpetakan',
                'industry_sub_sector' => trim((string) ($mapping['sac_sub_sector'] ?? $mapping['industry_sub_sector'] ?? '')) ?: 'Belum Terpetakan',
                'sac_reference_sub_sector' => trim((string) ($mapping['sac_sub_sector'] ?? '')) ?: null,
                'sac_match' => (string) ($mapping['sac_match'] ?? 'unmatched'),
                'lbu_codes' => array_values((array) ($mapping['lbu_codes'] ?? [])),
                'color' => $color,
                'color_label' => self::COLOR_DEFINITIONS[$color]['label'],
            ] + $metrics;
        }

        return $rows;
    }

    private function applyEligibleScope(Builder $query): void
    {
        $segment = 'UPPER(TRIM(COALESCE(`segmen`, \'\')))';

        $query
            ->whereRaw("UPPER(TRIM(COALESCE(`status_rekening`, ''))) = 'AKTIF'")
            ->whereRaw("{$segment} IN ('MICRO', 'MIKRO', 'SMALL')")
            ->whereRaw("UPPER(COALESCE(`produk`, '')) NOT LIKE '%BRIGUNA%'")
            ->whereRaw("UPPER(COALESCE(`loan_type`, '')) NOT LIKE '%BRIGUNA%'")
            ->whereRaw("UPPER(COALESCE(`ket_produk_tiering`, '')) NOT LIKE '%BRIGUNA%'");
    }

    private function aggregateWithSql(Builder $query): array
    {
        $quality = 'UPPER(TRIM(COALESCE(`kualitas`, \'\')))';
        $nplCondition = "({$quality} = 'NPL' OR {$quality} IN ('3', '4', '5', 'KL', 'D', 'M', 'KURANG LANCAR', 'DIRAGUKAN', 'MACET'))";
        $smlCondition = "({$quality} LIKE 'SML%' OR {$quality} IN ('2', 'DPK', 'DALAM PERHATIAN KHUSUS'))";

        return $query
            ->selectRaw(implode(', ', [
                'TRIM(`sektor_ekonomi`) AS sektor_ekonomi',
                'TRIM(`sub_sektor_ekonomi`) AS sub_sektor_ekonomi',
                'TRIM(`segmen`) AS segmen',
                'COUNT(*) AS source_rows',
                'SUM('.$this->mysqlNumeric('plafond').') AS plafond',
                'SUM('.$this->mysqlNumeric('baki_debet').') AS baki_debet',
                'SUM('.$this->mysqlNumeric('jumlah_debitur').') AS jumlah_debitur',
                'SUM('.$this->mysqlNumeric('jumlah_rekening').') AS jumlah_rekening',
                'SUM(CASE WHEN '.$smlCondition.' THEN '.$this->mysqlNumeric('baki_debet').' ELSE 0 END) AS sml',
                'SUM(CASE WHEN '.$nplCondition.' THEN '.$this->mysqlNumeric('baki_debet').' ELSE 0 END) AS npl',
            ]))
            ->groupByRaw('TRIM(`sektor_ekonomi`), TRIM(`sub_sektor_ekonomi`), TRIM(`segmen`)')
            ->get()
            ->all();
    }

    private function aggregateInPhp(Builder $query): array
    {
        $groups = [];
        foreach ($query->select([
            'sektor_ekonomi', 'sub_sektor_ekonomi', 'segmen', 'kualitas',
            'plafond', 'baki_debet', 'jumlah_debitur', 'jumlah_rekening',
        ])->cursor() as $row) {
            $key = implode('|', [
                CrasLpgReference::normalize((string) $row->sektor_ekonomi),
                CrasLpgReference::normalize((string) $row->sub_sektor_ekonomi),
                CrasLpgReference::normalize((string) $row->segmen),
            ]);
            $groups[$key] ??= [
                'sektor_ekonomi' => $row->sektor_ekonomi,
                'sub_sektor_ekonomi' => $row->sub_sektor_ekonomi,
                'segmen' => $row->segmen,
                'source_rows' => 0,
                'plafond' => 0.0,
                'baki_debet' => 0.0,
                'jumlah_debitur' => 0.0,
                'jumlah_rekening' => 0.0,
                'sml' => 0.0,
                'npl' => 0.0,
            ];
            $groups[$key]['source_rows']++;
            foreach (['plafond', 'baki_debet', 'jumlah_debitur', 'jumlah_rekening'] as $metric) {
                $groups[$key][$metric] += $this->parseNumber($row->{$metric});
            }
            if ($this->isNpl((string) $row->kualitas)) {
                $groups[$key]['npl'] += $this->parseNumber($row->baki_debet);
            } elseif ($this->isSml((string) $row->kualitas)) {
                $groups[$key]['sml'] += $this->parseNumber($row->baki_debet);
            }
        }

        return array_values($groups);
    }

    private function filterOptions(array $rows): array
    {
        $optionList = static function (array $values): array {
            $values = array_values(array_unique(array_filter($values, fn ($value): bool => trim((string) $value) !== '')));
            natcasesort($values);

            return array_merge(
                [['value' => 'all', 'label' => 'Semua']],
                array_map(fn ($value): array => ['value' => (string) $value, 'label' => (string) $value], array_values($values))
            );
        };

        return [
            'segment' => [
                ['value' => 'all', 'label' => 'Micro dan Small'],
                ['value' => 'micro', 'label' => 'Micro non-Briguna Mikro'],
                ['value' => 'small', 'label' => 'Small s.d. Rp5 Miliar'],
            ],
            'color' => array_merge(
                [['value' => 'all', 'label' => 'Semua Kategori']],
                array_map(
                    fn (string $key, array $definition): array => ['value' => $key, 'label' => $definition['label'].' - '.$definition['meaning']],
                    array_keys(self::COLOR_DEFINITIONS),
                    array_values(self::COLOR_DEFINITIONS)
                )
            ),
            'industry_sector' => $optionList(array_column($rows, 'industry_sector')),
            'industry_sub_sector' => $optionList(array_column($rows, 'industry_sub_sector')),
        ];
    }

    private function colorSummary(array $rows): array
    {
        $summary = [];
        foreach (self::COLOR_DEFINITIONS as $key => $definition) {
            $colorRows = array_values(array_filter($rows, fn (array $row): bool => $row['color'] === $key));
            $metrics = $this->sumRows($colorRows);
            $summary[] = [
                'key' => $key,
                'label' => $definition['label'],
                'meaning' => $definition['meaning'],
                'requirement' => $definition['requirement'],
                'sub_sector_count' => count($colorRows),
            ] + $metrics;
        }

        return $summary;
    }

    private function industryRows(array $rows, string $sort): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $key = implode('|', [
                CrasLpgReference::normalize((string) ($row['industry_sector'] ?? '')),
                CrasLpgReference::normalize((string) ($row['industry_sub_sector'] ?? '')),
            ]);
            $groups[$key] ??= [
                'industry_sector' => (string) ($row['industry_sector'] ?? 'Belum Terpetakan'),
                'industry_sub_sector' => (string) ($row['industry_sub_sector'] ?? 'Belum Terpetakan'),
                'source_rows' => 0,
                'source_sub_sectors' => [],
                'plafond' => 0.0,
                'baki_debet' => 0.0,
                'sml' => 0.0,
                'npl' => 0.0,
                'jumlah_debitur' => 0.0,
                'jumlah_rekening' => 0.0,
                'sac_categories' => [],
            ];
            foreach (['source_rows', 'plafond', 'baki_debet', 'sml', 'npl', 'jumlah_debitur', 'jumlah_rekening'] as $metric) {
                $groups[$key][$metric] += (float) ($row[$metric] ?? 0);
            }
            $sourceSubSector = trim((string) ($row['source_sub_sector'] ?? ''));
            if ($sourceSubSector !== '') {
                $groups[$key]['source_sub_sectors'][CrasLpgReference::normalize($sourceSubSector)] = true;
            }
            $segment = (string) ($row['segment'] ?? '');
            $color = (string) ($row['color'] ?? 'unmapped');
            $groups[$key]['sac_categories'][$segment] = [
                'segment' => $segment,
                'segment_label' => (string) ($row['segment_label'] ?? '-'),
                'color' => $color,
                'color_label' => (string) ($row['color_label'] ?? self::COLOR_DEFINITIONS[$color]['label'] ?? '-'),
                'meaning' => (string) (self::COLOR_DEFINITIONS[$color]['meaning'] ?? '-'),
            ];
        }

        $industryRows = [];
        foreach ($groups as $group) {
            $group['source_sub_sector_count'] = count($group['source_sub_sectors']);
            unset($group['source_sub_sectors']);
            $group['sac_categories'] = array_values($group['sac_categories']);
            usort($group['sac_categories'], fn (array $left, array $right): int => ($left['segment'] === 'micro' ? 0 : 1) <=> ($right['segment'] === 'micro' ? 0 : 1));
            $group['sml_ratio'] = $this->percentage($group['sml'], $group['baki_debet']);
            $group['npl_ratio'] = $this->percentage($group['npl'], $group['baki_debet']);
            $industryRows[] = array_map(
                fn (mixed $value): mixed => is_float($value) ? round($value, 2) : $value,
                $group
            );
        }

        if ($sort === 'npl_ratio_desc') {
            $industryRows = array_values(array_filter($industryRows, fn (array $row): bool => (float) $row['npl_ratio'] > 5.0));
            usort($industryRows, fn (array $left, array $right): int => $right['npl_ratio'] <=> $left['npl_ratio']
                ?: $right['baki_debet'] <=> $left['baki_debet']);
        } else {
            usort($industryRows, fn (array $left, array $right): int => $right['baki_debet'] <=> $left['baki_debet']
                ?: $right['npl_ratio'] <=> $left['npl_ratio']);
        }

        return $industryRows;
    }

    private function sumRows(array $rows): array
    {
        $totals = [
            'source_rows' => 0,
            'plafond' => 0.0,
            'baki_debet' => 0.0,
            'sml' => 0.0,
            'npl' => 0.0,
            'jumlah_debitur' => 0.0,
            'jumlah_rekening' => 0.0,
            'sml_ratio' => 0.0,
            'npl_ratio' => 0.0,
        ];
        foreach ($rows as $row) {
            foreach (['source_rows', 'plafond', 'baki_debet', 'sml', 'npl', 'jumlah_debitur', 'jumlah_rekening'] as $metric) {
                $totals[$metric] += (float) ($row[$metric] ?? 0);
            }
        }
        $totals['sml_ratio'] = $this->percentage($totals['sml'], $totals['baki_debet']);
        $totals['npl_ratio'] = $this->percentage($totals['npl'], $totals['baki_debet']);

        return array_map(fn (float|int $value): float|int => is_float($value) ? round($value, 2) : $value, $totals);
    }

    private function mysqlNumeric(string $column): string
    {
        $clean = "REPLACE(REPLACE(REPLACE(REPLACE(TRIM(`{$column}`), ',', ''), '\"', ''), '(', '-'), ')', '')";

        return "COALESCE(CAST(NULLIF({$clean}, '') AS DECIMAL(30, 4)), 0)";
    }

    private function parseNumber(mixed $value): float
    {
        $value = trim((string) $value);
        $negative = str_starts_with($value, '(') && str_ends_with($value, ')');
        $number = (float) (is_numeric($clean = str_replace([',', '"', '(', ')', ' '], '', $value)) ? $clean : 0);

        return $negative ? -abs($number) : $number;
    }

    private function isNpl(string $quality): bool
    {
        return in_array(CrasLpgReference::normalize($quality), [
            'NPL', '3', '4', '5', 'KL', 'D', 'M', 'KURANG LANCAR', 'DIRAGUKAN', 'MACET',
        ], true);
    }

    private function isSml(string $quality): bool
    {
        $quality = CrasLpgReference::normalize($quality);

        return str_starts_with($quality, 'SML') || in_array($quality, [
            '2', 'DPK', 'DALAM PERHATIAN KHUSUS',
        ], true);
    }

    private function percentage(float $numerator, float $denominator): float
    {
        return $denominator > 0 ? round(($numerator / $denominator) * 100, 4) : 0.0;
    }

    private function validOption(string $value, array $allowed): string
    {
        return in_array($value = trim($value), $allowed, true) ? $value : 'all';
    }

    private function option(string $value): string
    {
        $value = trim($value);

        return $value === '' || strlen($value) > 255 ? 'all' : $value;
    }

    private function applyRegionFilter(Builder $query, string $region): void
    {
        if ($region === 'all') {
            return;
        }
        $label = trim((string) config("marketshare-geography.branches.{$region}.label", ''));
        if ($label !== '') {
            $query->where('ket_kanca', 'KC '.$label);
        }
    }
}
