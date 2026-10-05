<?php

namespace App\Support;

class LandingRmKurDistribution
{
    public const TIERS = [
        'zero' => ['label' => 'Belum Realisasi', 'range' => 'Nett ≤ Rp0'],
        'extreme_low' => ['label' => 'Extreme Low', 'range' => '> Rp0–Rp900 juta'],
        'low' => ['label' => 'Low', 'range' => '> Rp900 juta–Rp1,8 miliar'],
        'mid' => ['label' => 'Mid', 'range' => '> Rp1,8–Rp2,5 miliar'],
        'high' => ['label' => 'High', 'range' => '> Rp2,5 miliar'],
    ];

    public function build(array $rows, array $roster): array
    {
        $pnKey = static fn ($pn): string => ltrim(preg_replace('/\D/', '', (string) $pn), '0');
        $people = collect($roster)->filter(fn ($person) => $pnKey($person['pn'] ?? '') !== '')
            ->unique(fn ($person) => $pnKey($person['pn']))->keyBy(fn ($person) => $pnKey($person['pn']));
        $amounts = [];
        $unassigned = 0.0;
        foreach ($rows as $row) {
            $pn = $pnKey($row['pn'] ?? '');
            if (! $people->has($pn)) {
                $unassigned += (float) ($row['realisasi_os'] ?? 0);
                continue;
            }
            $amounts[$pn] = ($amounts[$pn] ?? 0) + (float) ($row['realisasi_os'] ?? 0);
        }
        $blank = static fn (): array => ['rm_count' => 0, 'counts' => array_fill_keys(array_keys(self::TIERS), 0)];
        $groups = [];
        $total = $blank();
        foreach ($people as $pn => $person) {
            $key = strtoupper(trim($person['branch'])).'|'.ltrim((string) $person['branch_code'], '0');
            $groups[$key] ??= array_merge($blank(), [
                'cabang' => $person['branch'], 'branch_code' => $person['branch_code'], 'unit' => $person['unit'],
            ]);
            $amount = $amounts[$pn] ?? 0;
            $tier = match (true) {
                $amount <= 0 => 'zero',
                $amount <= 900_000_000 => 'extreme_low',
                $amount <= 1_800_000_000 => 'low',
                $amount <= 2_500_000_000 => 'mid',
                default => 'high',
            };
            $groups[$key]['rm_count']++;
            $groups[$key]['counts'][$tier]++;
            $total['rm_count']++;
            $total['counts'][$tier]++;
        }
        $percentages = static function (array $group): array {
            $group['percentages'] = array_map(
                static fn (int $count): float => $group['rm_count'] > 0 ? 100 * $count / $group['rm_count'] : 0.0,
                $group['counts']
            );

            return $group;
        };

        return [
            'tiers' => self::TIERS,
            'rows' => collect($groups)->sortKeys()->map($percentages)->values()->all(),
            'total' => $percentages($total),
            'unassigned_amount' => $unassigned,
        ];
    }
}
