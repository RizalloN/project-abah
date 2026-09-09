<?php

namespace App\Services\Reports;

use Carbon\Carbon;

final class KpiRmSmeDashboardService
{
    private const AREA_BRANCH_CODES = ['00045', '00049', '00057', '00070'];

    private const MONTHS = [
        'jan' => 1,
        'january' => 1,
        'januari' => 1,
        'feb' => 2,
        'february' => 2,
        'februari' => 2,
        'mar' => 3,
        'march' => 3,
        'maret' => 3,
        'apr' => 4,
        'april' => 4,
        'may' => 5,
        'mei' => 5,
        'jun' => 6,
        'june' => 6,
        'juni' => 6,
        'jul' => 7,
        'july' => 7,
        'juli' => 7,
        'aug' => 8,
        'august' => 8,
        'agt' => 8,
        'agustus' => 8,
        'sep' => 9,
        'september' => 9,
        'oct' => 10,
        'october' => 10,
        'okt' => 10,
        'oktober' => 10,
        'nov' => 11,
        'november' => 11,
        'dec' => 12,
        'december' => 12,
        'des' => 12,
        'desember' => 12,
    ];

    /** @var array<string, array<string, mixed>> */
    private const METRICS = [
        'avg_balance' => [
            'label' => 'Avg Balance Small',
            'group' => 'Pertumbuhan Kredit',
            'value_header' => 'AVG BALANCE SMALL',
            'target_header' => 'RKA AVG BALANCE SMALL',
            'weight' => 0.10,
            'percent' => false,
            'reverse' => false,
        ],
        'os_small' => [
            'label' => 'Posisi OS Small',
            'group' => 'Pertumbuhan Kredit',
            'value_header' => 'OS SMALL',
            'target_header' => 'RKA OS SMALL',
            'weight' => 0.15,
            'percent' => false,
            'reverse' => false,
        ],
        'new_debtors' => [
            'label' => 'Jumlah Debitur Small Baru',
            'group' => 'Pertumbuhan Kredit',
            'value_header' => 'JUMLAH DEBITUR SMALL',
            'target_header' => 'RKA JUMLAH DEBITUR SMALL',
            'weight' => 0.10,
            'percent' => false,
            'reverse' => false,
        ],
        'downgrade_kol2' => [
            'label' => 'Downgrade ke Kolektibilitas 2',
            'group' => 'Risiko Kredit',
            'value_header' => 'DOWNGRADE TO KOL 2 %',
            'target_header' => 'RKA DOWNGRADE TO KOL 2 %',
            'weight' => 0.20,
            'percent' => true,
            'reverse' => true,
        ],
        'dpk_ratio' => [
            'label' => 'Rasio DPK Debitur Kelolaan',
            'group' => 'Dana Murah',
            'value_header' => 'RASIO DPK DEBITUR KELOLAAN TO LOAN SME',
            'target_header' => 'RKA RASIO DPK DEBITUR KELOLAAN TO LOAN SME',
            'weight' => 0.20,
            'percent' => true,
            'reverse' => false,
        ],
        'value_chain' => [
            'label' => 'Booking Value Chain Cash Loan',
            'group' => 'Retail Transaction Banking',
            'value_header' => '% BOOKING VALUE CHAIN CASH LOAN',
            'target_header' => 'RKA % BOOKING VALUE CHAIN CASH LOAN',
            'weight' => 0.20,
            'percent' => false,
            'reverse' => false,
            'decimals' => 2,
        ],
        'product_holding' => [
            'label' => 'Product Holding Nasabah Kelolaan',
            'group' => 'Retail Transaction Banking',
            'value_header' => 'PRODUCT HOLDING NASABAH KELOLAAN',
            'target_header' => 'RKA PRODUCT HOLDING NASABAH KELOLAAN',
            'weight' => 0.05,
            'percent' => true,
            'reverse' => false,
        ],
    ];

    /**
     * @param  array<string, array{header?: array<int, string>, rows?: array<int, array<int, string>>}>  $sources
     * @param  array{selected?: string, locked?: bool, options?: array<int, array{value: string, label: string}>}  $branchFilter
     * @return array<string, mixed>
     */
    public function build(array $sources, array $branchFilter): array
    {
        $mainRows = $this->associateRows($sources['main'] ?? []);
        $targetRows = $this->associateRows($sources['targets'] ?? []);
        $rosterRows = $this->associateRows($sources['roster'] ?? []);
        $selectedBranch = trim((string) ($branchFilter['selected'] ?? 'all'));

        $roster = [];
        foreach ($rosterRows as $row) {
            if (! $this->rowIsInScope($row, $selectedBranch)) {
                continue;
            }

            $identity = $this->identity($row);
            if ($identity === null) {
                continue;
            }

            $roster[$identity] = $this->personMeta($row, $identity);
        }

        $targetByIdentity = [];
        foreach ($targetRows as $row) {
            if (! $this->rowIsInScope($row, $selectedBranch)) {
                continue;
            }

            $identity = $this->identity($row);
            if ($identity === null || ! isset($roster[$identity])) {
                continue;
            }

            $targetByIdentity[$identity] = $this->metricValues($row);
        }

        $profiles = [];
        $periodCatalog = [];
        foreach ($mainRows as $row) {
            if (! $this->rowIsInScope($row, $selectedBranch)) {
                continue;
            }

            $identity = $this->identity($row);
            if ($identity === null || ! isset($roster[$identity])) {
                continue;
            }

            $period = $this->period((string) ($row['PERIODE'] ?? ''));
            if ($period === null) {
                continue;
            }

            $profile = $profiles[$identity] ?? [
                ...$roster[$identity],
                'history' => [],
                'year_end_targets' => $targetByIdentity[$identity] ?? [],
            ];
            $profile['history'][$period['key']] = [
                'metrics' => $this->metricValues($row),
                'quality' => [
                    'lancar' => $this->number($row['POSISI LANCAR'] ?? null),
                    'sml' => $this->number($row['POSISI SML'] ?? null),
                    'npl' => $this->number($row['POSISI NPL'] ?? null),
                ],
            ];
            $profiles[$identity] = $profile;
            $periodCatalog[$period['key']] = $period;
        }

        ksort($periodCatalog);
        foreach ($profiles as &$profile) {
            ksort($profile['history']);
        }
        unset($profile);

        uasort($profiles, static fn (array $left, array $right): int => [
            $left['branch'], $left['unit'], $left['name'],
        ] <=> [
            $right['branch'], $right['unit'], $right['name'],
        ]);

        $latestPeriod = array_key_last($periodCatalog);
        [$summaryRows, $branchAnalysis] = $this->summary($profiles, $latestPeriod);
        $filters = $this->filters($profiles);

        return [
            'metrics' => collect(self::METRICS)->map(function (array $metric, string $key): array {
                return [
                    'key' => $key,
                    'label' => $metric['label'],
                    'group' => $metric['group'],
                    'weight' => $metric['weight'],
                    'percent' => $metric['percent'],
                    'reverse' => $metric['reverse'],
                    'decimals' => $metric['decimals'] ?? 0,
                ];
            })->values()->all(),
            'periods' => array_values($periodCatalog),
            'latest_period' => $latestPeriod,
            'profiles' => $profiles,
            'filters' => $filters,
            'initial_profile' => array_key_first($profiles),
            'summary_rows' => $summaryRows,
            'branch_analysis' => $branchAnalysis,
            'stats' => $this->stats($summaryRows, $periodCatalog, $branchFilter),
            'branch_filter' => $branchFilter,
        ];
    }

    /** @return array<string, array<int, string>> */
    private function associateRows(array $source): array
    {
        $headers = array_map(fn ($value): string => $this->header($value), array_values($source['header'] ?? []));
        if ($headers === []) {
            return [];
        }

        $rows = [];
        foreach (array_values($source['rows'] ?? []) as $row) {
            $row = array_pad(array_values($row), count($headers), '');
            $rows[] = array_combine($headers, array_slice($row, 0, count($headers))) ?: [];
        }

        return $rows;
    }

    private function header(mixed $value): string
    {
        return strtoupper(preg_replace('/\s+/', ' ', trim((string) $value)) ?? '');
    }

    /** @param array<string, string> $row */
    private function identity(array $row): ?string
    {
        $branchCode = $this->branchCode($row['NAMA KANCA KONSOL'] ?? '');
        $person = trim((string) ($row['NAMA MANTRI'] ?? ''));
        if ($branchCode === '' || $person === '') {
            return null;
        }

        preg_match('/^\s*(\d{5,})\s*[-]/', $person, $matches);
        $personKey = $matches[1] ?? strtoupper(preg_replace('/\s+/', ' ', $person) ?? $person);

        return $branchCode . ':' . $personKey;
    }

    /** @param array<string, string> $row */
    private function rowIsInScope(array $row, string $selectedBranch): bool
    {
        $branch = trim((string) ($row['NAMA KANCA KONSOL'] ?? ''));
        if (! in_array($this->branchCode($branch), self::AREA_BRANCH_CODES, true)) {
            return false;
        }

        return $selectedBranch === '' || $selectedBranch === 'all' || $branch === $selectedBranch;
    }

    private function branchCode(string $value): string
    {
        return preg_match('/\b(\d{5})\b/', $value, $matches) === 1 ? $matches[1] : '';
    }

    /** @param array<string, string> $row */
    private function personMeta(array $row, string $identity): array
    {
        return [
            'id' => $identity,
            'branch_raw' => trim((string) ($row['NAMA KANCA KONSOL'] ?? '')),
            'branch' => $this->cleanOrganization((string) ($row['NAMA KANCA KONSOL'] ?? '')),
            'unit_raw' => trim((string) ($row['NAMA UKO'] ?? '')),
            'unit' => $this->cleanOrganization((string) ($row['NAMA UKO'] ?? '')),
            'person_raw' => trim((string) ($row['NAMA MANTRI'] ?? '')),
            'name' => $this->cleanPerson((string) ($row['NAMA MANTRI'] ?? '')),
            'jg' => trim((string) ($row['JG'] ?? '')) ?: '-',
        ];
    }

    private function cleanOrganization(string $value): string
    {
        $clean = preg_replace('/^\s*\d+\s*--\s*/', '', trim($value)) ?? trim($value);

        return trim(str_ireplace('(Konsolidasi-MB)', '', $clean));
    }

    private function cleanPerson(string $value): string
    {
        return trim(preg_replace('/^\s*\d{5,}\s*-\s*/', '', trim($value)) ?? trim($value));
    }

    /** @param array<string, string> $row */
    private function metricValues(array $row): array
    {
        $values = [];
        foreach (self::METRICS as $key => $metric) {
            $values[$key] = [
                'value' => $this->number($row[$metric['value_header']] ?? null, (bool) $metric['percent']),
                'target' => $this->number($row[$metric['target_header']] ?? null, (bool) $metric['percent']),
            ];
        }

        return $values;
    }

    private function number(mixed $value, bool $percent = false): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $text = trim((string) $value);
        if ($text === '' || in_array(strtoupper($text), ['-', '--', '#N/A', '#ERROR!'], true)) {
            return null;
        }

        $negative = str_starts_with($text, '(') && str_ends_with($text, ')');
        $text = trim($text, "() \t\n\r\0\x0B");
        $hasPercent = str_contains($text, '%');
        $text = str_replace(['%', 'Rp', 'rp', ' '], '', $text);

        if (str_contains($text, ',') && str_contains($text, '.')) {
            $text = str_replace('.', '', $text);
            $text = str_replace(',', '.', $text);
        } elseif (str_contains($text, ',')) {
            $text = str_replace(',', '.', $text);
        } elseif (preg_match('/^-?\d{1,3}(?:\.\d{3})+$/', $text) === 1) {
            $text = str_replace('.', '', $text);
        }

        if (! is_numeric($text)) {
            return null;
        }

        $number = (float) $text * ($negative ? -1 : 1);

        return $percent || $hasPercent ? $number / 100 : $number;
    }

    /** @return array{key: string, label: string, short_label: string}|null */
    private function period(string $value): ?array
    {
        $parts = preg_split('/\s+/', trim($value)) ?: [];
        if (count($parts) !== 3) {
            return null;
        }

        $month = self::MONTHS[strtolower($parts[1])] ?? null;
        if ($month === null || ! is_numeric($parts[0]) || ! is_numeric($parts[2])) {
            return null;
        }

        try {
            $date = Carbon::create((int) $parts[2], $month, (int) $parts[0])->startOfDay();
        } catch (\Throwable) {
            return null;
        }

        return [
            'key' => $date->format('Y-m-d'),
            'label' => trim($value),
            'short_label' => $date->translatedFormat('d M y'),
        ];
    }

    /** @param array<string, array<string, mixed>> $profiles */
    private function filters(array $profiles): array
    {
        $branches = [];
        foreach ($profiles as $id => $profile) {
            $branches[$profile['branch_raw']]['label'] = $profile['branch'];
            $branches[$profile['branch_raw']]['units'][$profile['unit_raw']]['label'] = $profile['unit'];
            $branches[$profile['branch_raw']]['units'][$profile['unit_raw']]['profiles'][] = $id;
        }

        return ['branches' => $branches];
    }

    /**
     * @param array<string, array<string, mixed>> $profiles
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>}
     */
    private function summary(array $profiles, ?string $latestPeriod): array
    {
        if ($latestPeriod === null) {
            return [[], []];
        }

        $rows = [];
        foreach ($profiles as $profile) {
            $position = $profile['history'][$latestPeriod] ?? null;
            if (! is_array($position)) {
                continue;
            }

            $scores = [];
            $totalScore = 0.0;
            foreach (self::METRICS as $key => $metric) {
                $entry = $position['metrics'][$key] ?? ['value' => null, 'target' => null];
                $score = $this->score(
                    $entry['value'] ?? null,
                    $entry['target'] ?? null,
                    (float) $metric['weight'],
                    (bool) $metric['reverse']
                );
                $scores[$key] = $score;
                $totalScore += (float) ($score['score'] ?? 0);
            }

            $rows[] = [
                'id' => $profile['id'],
                'branch' => $profile['branch'],
                'unit' => $profile['unit'],
                'name' => $profile['name'],
                'jg' => $profile['jg'],
                'scores' => $scores,
                'total_score' => round($totalScore, 2),
            ];
        }

        usort($rows, static fn (array $left, array $right): int => $right['total_score'] <=> $left['total_score']);

        $branches = [];
        foreach ($rows as $row) {
            $branch = $row['branch'];
            $branches[$branch] ??= [
                'branch' => $branch,
                'score_total' => 0.0,
                'rm_count' => 0,
                'top_rm' => '-',
                'top_score' => 0.0,
                'excellent' => 0,
                'good' => 0,
                'fair' => 0,
                'attention' => 0,
            ];
            $branches[$branch]['score_total'] += $row['total_score'];
            $branches[$branch]['rm_count']++;
            if ($row['total_score'] >= $branches[$branch]['top_score']) {
                $branches[$branch]['top_score'] = $row['total_score'];
                $branches[$branch]['top_rm'] = $row['name'];
            }

            match (true) {
                $row['total_score'] >= 100 => $branches[$branch]['excellent']++,
                $row['total_score'] >= 85 => $branches[$branch]['good']++,
                $row['total_score'] >= 70 => $branches[$branch]['fair']++,
                default => $branches[$branch]['attention']++,
            };
        }

        $analysis = array_values(array_map(static function (array $branch): array {
            $branch['average_score'] = $branch['rm_count'] > 0
                ? round($branch['score_total'] / $branch['rm_count'], 2)
                : 0.0;
            unset($branch['score_total']);

            return $branch;
        }, $branches));
        usort($analysis, static fn (array $left, array $right): int => $right['average_score'] <=> $left['average_score']);

        return [$rows, $analysis];
    }

    /** @return array{achievement: ?float, score: float, achieved: ?bool, tone: string} */
    private function score(?float $value, ?float $target, float $weight, bool $reverse): array
    {
        if ($value === null || $target === null) {
            return ['achievement' => null, 'score' => 0.0, 'achieved' => null, 'tone' => 'neutral'];
        }

        if ($reverse) {
            $achievement = $value !== 0.0 ? $target / $value : ($target !== 0.0 ? 1.10 : null);
            if ($achievement !== null) {
                $achievement = min(abs($achievement), 1.10);
            }
            $achieved = $value <= $target;
        } else {
            $achievement = $target !== 0.0 ? min($value / $target, 1.10) : null;
            $achieved = $value >= $target;
        }

        return [
            'achievement' => $achievement,
            'score' => round($achievement === null ? 0.0 : $weight * $achievement * 100, 2),
            'achieved' => $achieved,
            'tone' => $achieved ? 'positive' : 'negative',
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $summaryRows
     * @param array<string, array<string, string>> $periodCatalog
     */
    private function stats(array $summaryRows, array $periodCatalog, array $branchFilter): array
    {
        $average = $summaryRows === []
            ? 0.0
            : round(array_sum(array_column($summaryRows, 'total_score')) / count($summaryRows), 2);
        $top = $summaryRows[0] ?? null;

        return [
            'rm_count' => count($summaryRows),
            'average_score' => $average,
            'top_rm' => $top['name'] ?? '-',
            'top_score' => $top['total_score'] ?? 0.0,
            'latest_period_label' => data_get($periodCatalog, (string) array_key_last($periodCatalog) . '.label', '-'),
            'scope_label' => ($branchFilter['selected'] ?? 'all') === 'all'
                ? 'Area 6'
                : $this->cleanOrganization((string) ($branchFilter['selected'] ?? '')),
        ];
    }
}
