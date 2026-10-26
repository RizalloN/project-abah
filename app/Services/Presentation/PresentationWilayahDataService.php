<?php

namespace App\Services\Presentation;

/**
 * Wilayah segments of the segmented presentation: market share against the
 * industry per branch, and the unit service-area mapping (potential, existing
 * customers and penetration per CRAS sector) on kecamatan polygons.
 *
 * Inputs are the payloads the dashboard already builds (MarketShareArea6Report
 * segments and the REKAP geography); this class only reshapes them so the
 * browser never has to know source column positions.
 */
class PresentationWilayahDataService
{
    /** Column positions in the RO report: BRI now, industry now, share Dec, share now, YtD share change. */
    private const COLUMNS = [
        'deposit' => ['bri' => 2, 'industry' => 6, 'share_prev' => 13, 'share' => 14, 'share_ytd' => 16, 'growth_ytd' => 3],
        'loan' => ['bri' => 2, 'industry' => 9, 'share_prev' => 15, 'share' => 16, 'share_ytd' => 18, 'growth_ytd' => 6],
    ];

    private const MARKET_SEGMENTS = [
        'simpanan' => ['dpk', 'giro', 'tabungan', 'deposito', 'casa'],
        'pinjaman' => ['pinjaman_total', 'umkm', 'konsumer', 'briguna', 'kpr'],
    ];

    /**
     * @param array<string, array<string, mixed>> $segments MarketShareArea6Report segments keyed by segment key
     * @param string|null $branchKey restrict rows to one branch (madiun, magetan, ...) for branch-scoped users
     */
    public function marketShare(array $segments, array $meta, ?string $branchKey = null): array
    {
        $groups = [];
        foreach (self::MARKET_SEGMENTS as $group => $keys) {
            $groups[$group] = collect($keys)
                ->filter(fn (string $key): bool => isset($segments[$key]))
                ->map(fn (string $key): array => $this->marketSegment($segments[$key], $key, $branchKey))
                ->filter(fn (array $segment): bool => $segment['rows'] !== [])
                ->values()
                ->all();
        }

        return [
            'available' => $groups['simpanan'] !== [] || $groups['pinjaman'] !== [],
            'period' => (string) ($meta['period'] ?? ''),
            'unit' => (string) ($meta['unit'] ?? ''),
            'source' => (string) ($meta['source'] ?? ''),
            'groups' => $groups,
        ];
    }

    private function marketSegment(array $segment, string $key, ?string $branchKey): array
    {
        $kind = ($segment['kind'] ?? '') === 'loan' ? 'loan' : 'deposit';
        $columns = self::COLUMNS[$kind];
        $periods = (array) data_get($segment, 'headers.columns', []);

        $rows = collect((array) ($segment['rows'] ?? []))
            ->map(function (array $row) use ($columns): array {
                $values = (array) ($row['values'] ?? []);
                $bri = $this->number($values[$columns['bri']] ?? null);
                $industry = $this->number($values[$columns['industry']] ?? null);

                return [
                    'key' => $this->branchKey((string) ($row['branch'] ?? '')),
                    'label' => (string) ($row['branch'] ?? '-'),
                    'total' => (bool) ($row['total'] ?? false),
                    'bri' => $bri,
                    'industry' => $industry,
                    'others' => $bri !== null && $industry !== null && $industry >= $bri ? $industry - $bri : null,
                    'share' => $this->number($values[$columns['share']] ?? null),
                    'share_prev' => $this->number($values[$columns['share_prev']] ?? null),
                    'share_ytd' => $this->number($values[$columns['share_ytd']] ?? null),
                    'growth_ytd' => $this->number($values[$columns['growth_ytd']] ?? null),
                    'labels' => [
                        'bri' => (string) ($values[$columns['bri']] ?? '-'),
                        'industry' => (string) ($values[$columns['industry']] ?? '-'),
                        'share' => (string) ($values[$columns['share']] ?? '-'),
                        'share_prev' => (string) ($values[$columns['share_prev']] ?? '-'),
                        'share_ytd' => (string) ($values[$columns['share_ytd']] ?? '-'),
                    ],
                ];
            })
            ->filter(fn (array $row): bool => $row['bri'] !== null || $row['industry'] !== null || $row['share'] !== null)
            ->when($branchKey !== null, fn ($rows) => $rows->filter(fn (array $row): bool => $row['key'] === $branchKey))
            ->values()
            ->all();

        return [
            'key' => $key,
            'label' => (string) ($segment['label'] ?? $key),
            'kind' => $kind,
            'period_now' => (string) ($periods[$columns['bri']] ?? ''),
            'period_prev' => (string) ($periods[$columns['share_prev']] ?? ''),
            'rows' => $rows,
        ];
    }

    /**
     * Mapping without the GeoJSON body (the browser fetches it from geojson_url).
     * Units carry their district codes and per-sector values; branches their totals.
     */
    public function mapping(array $geography): array
    {
        if (empty($geography['ready'])) {
            return ['ready' => false];
        }

        return [
            'ready' => true,
            'updated_at' => (string) ($geography['updated_at'] ?? ''),
            'geojson_url' => (string) ($geography['geojson_url'] ?? ''),
            'source' => (array) ($geography['source'] ?? []),
            'sectors' => array_values((array) ($geography['sectors'] ?? [])),
            'branches' => array_map(fn (array $branch): array => [
                'key' => (string) ($branch['key'] ?? ''),
                'label' => 'KC ' . (string) ($branch['label'] ?? ''),
                'regency_codes' => array_values((array) ($branch['regency_codes'] ?? [])),
                'unit_count' => (int) ($branch['unit_count'] ?? 0),
                'totals' => (array) ($branch['totals'] ?? []),
            ], (array) ($geography['branches'] ?? [])),
            'units' => array_map(fn (array $unit): array => [
                'code' => (string) ($unit['code'] ?? ''),
                'name' => (string) ($unit['name'] ?? ''),
                'branch' => (string) ($unit['branch'] ?? ''),
                'district_codes' => array_values((array) ($unit['district_codes'] ?? [])),
                'values' => (array) ($unit['values'] ?? []),
            ], (array) ($geography['units'] ?? [])),
            'area_totals' => (array) ($geography['area_totals'] ?? []),
            'coverage' => (array) ($geography['coverage'] ?? []),
        ];
    }

    private function branchKey(string $label): ?string
    {
        $token = strtolower(trim(preg_replace('/^KC\s+/i', '', $label) ?? $label));
        foreach (['madiun', 'magetan', 'ngawi', 'ponorogo'] as $branch) {
            if ($token === $branch || str_contains($token, $branch)) {
                return $branch;
            }
        }

        return null;
    }

    /** "4,039" -> 4039, "29.7%" -> 29.7, "(52)" -> -52, "-" -> null. Source numbers use comma thousands. */
    private function number(mixed $raw): ?float
    {
        $text = trim((string) $raw);
        if ($text === '' || $text === '-') {
            return null;
        }
        $negative = str_starts_with($text, '(') && str_ends_with($text, ')');
        $clean = str_replace([',', '%', '(', ')', ' '], '', $text);
        if (!is_numeric($clean)) {
            return null;
        }

        return ($negative ? -1 : 1) * (float) $clean;
    }
}
