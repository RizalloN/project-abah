<?php

namespace App\Support;

class MicroRmNettWorkbook
{
    public function build(array $rawRows, array $roster, string $scopeLabel, string $period): array
    {
        $pnKey = static function (mixed $value): string {
            return preg_match('/^\s*(\d+)(?:\s*-.*)?\s*$/u', (string) $value, $match)
                ? ltrim($match[1], '0') : '';
        };
        $nameKey = static fn (mixed $value): string => preg_replace('/[^A-Z0-9]/', '', strtoupper(trim((string) $value)));
        $people = [];
        $names = [];
        foreach ($roster as $person) {
            $pn = $pnKey($person['pn'] ?? '');
            if ($pn === '' || isset($people[$pn])) {
                continue;
            }
            $people[$pn] = [
                'pn' => (string) $person['pn'], 'nama' => (string) $person['name'],
                'cabang' => (string) $person['branch'], 'unit' => (string) $person['unit'],
                'branch_code' => (string) ($person['branch_code'] ?? '-'),
                'office_type' => ($person['office_type'] ?? 'kc') === 'kcp' ? 'kcp' : 'kc',
                'realisasi_deb' => 0, 'realisasi_os' => 0.0,
            ];
            $key = $nameKey($person['name']);
            if ($key !== '') {
                $names[$key][] = $pn;
            }
        }
        $unassigned = [];
        $rawAmount = 0.0;
        $rawDeb = 0;
        foreach ($rawRows as $row) {
            $amount = (float) ($row['realisasi_os'] ?? 0);
            $deb = (int) ($row['realisasi_deb'] ?? 0);
            $rawAmount += $amount;
            $rawDeb += $deb;
            $identity = (string) ($row['pn'] ?? $row['rm'] ?? $row['owner'] ?? '');
            $name = (string) ($row['nama'] ?? $row['name'] ?? '');
            if ($name === '' && str_contains($identity, '-')) {
                $name = trim(explode('-', $identity, 2)[1]);
            }
            $pn = $pnKey($identity);
            $matched = $pn !== '' && isset($people[$pn]) ? $pn : null;
            $numericIdentity = preg_match('/^\s*\d/', $identity) === 1;
            $nameIndex = $nameKey($name);
            if ($matched === null && ! $numericIdentity && $pn === '' && $nameIndex !== '' && $nameIndex !== 'RMLAIN'
                && $nameKey($identity) !== 'RMLAIN' && count($names[$nameIndex] ?? []) === 1) {
                $matched = $names[$nameIndex][0];
            }
            if ($matched !== null) {
                $people[$matched]['realisasi_os'] += $amount;
                $people[$matched]['realisasi_deb'] += $deb;
            } else {
                $unassigned[] = array_merge($row, ['source_pn' => $identity, 'source_name' => $name, 'realisasi_os' => $amount, 'realisasi_deb' => $deb]);
            }
        }
        $blank = static fn (): array => ['rm_count' => 0, 'realisasi_os' => 0.0, 'realisasi_deb' => 0, 'counts' => array_fill_keys(array_keys(LandingRmKurDistribution::TIERS), 0)];
        $sections = ['kc' => ['rows' => [], 'total' => $blank()], 'kcp' => ['rows' => [], 'total' => $blank()]];
        foreach (array_merge($roster, $rawRows) as $source) {
            $branch = trim((string) ($source['branch'] ?? $source['cabang'] ?? ''));
            if ($branch === '') {
                continue;
            }
            $key = strtoupper($branch).'|';
            $sections['kc']['rows'][$key] ??= array_merge($blank(), ['cabang' => $branch, 'unit' => $branch, 'branch_code' => '-', 'office_type' => 'kc']);
        }
        $all = $blank();
        $rows = [];
        foreach ($people as $person) {
            $amount = $person['realisasi_os'];
            $tier = match (true) {
                $amount <= 0 => 'zero', $amount <= 900000000 => 'extreme_low',
                $amount <= 1800000000 => 'low', $amount <= 2500000000 => 'mid', default => 'high',
            };
            $person['average_per_debtor'] = $person['realisasi_deb'] > 0 ? $amount / $person['realisasi_deb'] : 0.0;
            $rows[] = $person;
            $type = $person['office_type'];
            $key = strtoupper(trim($person['cabang'])).'|'.($type === 'kcp' ? strtoupper(trim($person['unit'])) : '');
            $sections[$type]['rows'][$key] ??= array_merge($blank(), [
                'cabang' => $person['cabang'], 'unit' => $type === 'kc' ? $person['cabang'] : $person['unit'],
                'branch_code' => $person['branch_code'], 'office_type' => $type,
            ]);
            foreach (['rm_count' => 1, 'realisasi_os' => $amount, 'realisasi_deb' => $person['realisasi_deb']] as $field => $value) {
                $sections[$type]['rows'][$key][$field] += $value;
                $sections[$type]['total'][$field] += $value;
                $all[$field] += $value;
            }
            $sections[$type]['rows'][$key]['counts'][$tier]++;
            $sections[$type]['total']['counts'][$tier]++;
            $all['counts'][$tier]++;
        }
        $percentages = static function (array $row): array {
            $row['percentages'] = array_map(static fn (int $count): float => $row['rm_count'] > 0 ? 100 * $count / $row['rm_count'] : 0.0, $row['counts']);
            $row['need_acceleration_count'] = $row['counts']['zero'] + $row['counts']['extreme_low'] + $row['counts']['low'];
            $row['need_acceleration_percentage'] = $row['rm_count'] > 0 ? 100.0 * $row['need_acceleration_count'] / $row['rm_count'] : 0.0;
            return $row;
        };
        $order = array_flip(['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO']);
        usort($rows, static fn ($a, $b): int => [($a['office_type'] === 'kc' ? 0 : 1), ($order[strtoupper($a['cabang'])] ?? 99), $a['unit'], $a['nama']] <=> [($b['office_type'] === 'kc' ? 0 : 1), ($order[strtoupper($b['cabang'])] ?? 99), $b['unit'], $b['nama']]);
        foreach ($sections as &$section) {
            $section['rows'] = array_values($section['rows']);
            usort($section['rows'], static fn ($a, $b): int => [($order[strtoupper($a['cabang'])] ?? 99), $a['unit']] <=> [($order[strtoupper($b['cabang'])] ?? 99), $b['unit']]);
            $section['rows'] = array_map($percentages, $section['rows']);
            $section['total'] = $percentages($section['total']);
        }
        unset($section);
        $unassignedAmount = array_sum(array_column($unassigned, 'realisasi_os'));
        $unassignedDeb = array_sum(array_column($unassigned, 'realisasi_deb'));
        return [
            'available' => $rows !== [] || $rawRows !== [], 'scope_label' => $scopeLabel, 'period' => $period,
            'period_label' => $period, 'rows' => $rows, 'sections' => $sections,
            'total' => ['rm_count' => count($rows), 'realisasi_os' => $rawAmount, 'realisasi_deb' => $rawDeb, 'average_per_debtor' => $rawDeb > 0 ? $rawAmount / $rawDeb : 0.0],
            'assigned_total' => $percentages($all), 'unassigned_amount' => $unassignedAmount,
            'unassigned_deb' => $unassignedDeb, 'unassigned_rows' => $unassigned,
            'distribution' => ['tiers' => LandingRmKurDistribution::TIERS, 'rows' => array_merge($sections['kc']['rows'], $sections['kcp']['rows']),
                'total' => $percentages($all), 'unassigned_amount' => $unassignedAmount],
        ];
    }
}
