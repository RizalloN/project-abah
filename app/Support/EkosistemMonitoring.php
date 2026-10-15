<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/** Read-only equivalents of Index.html health(), filtered(), and allStats(). */
class EkosistemMonitoring
{
    public const CONDITIONS = [
        'late' => 'Lewat target', 'stale' => 'Mandek > 14 hari', 'due' => 'Jatuh tempo ≤ 7 hari',
        'hold' => 'Tertunda', 'nopic' => 'Prioritas tanpa PIC', 'blocked' => 'Menunggu prasyarat',
        'ready' => 'Siap dimulai', 'ok' => 'Sesuai jadwal', 'unit' => 'Cek satuan nominal',
        'unmapped' => 'Belum terhubung ke ekosistem',
    ];

    public function decorate(array $rows, string $today): array
    {
        $index = array_column($rows, null, 'ID_Prospek');

        return array_map(function (array $row) use ($index, $today): array {
            $row['Kondisi'] = $this->health($row, $index, $today);
            $row['Terlambat'] = $row['Kondisi']['key'] === 'late';

            return $row;
        }, $rows);
    }

    public function health(array $row, array $index, string $today): array
    {
        $status = ($row['Status'] ?? '') ?: 'Belum mulai';
        $target = (string) ($row['Target_Selesai'] ?? '');
        $make = static fn (string $key, string $label, string $tone, int $rank): array => compact('key', 'label', 'tone', 'rank');
        if ($status === 'Selesai') {
            return $make('done', 'Selesai', 'success', 9);
        }
        if ($status === 'Batal') {
            return $make('cancel', 'Batal', 'muted', 10);
        }
        if ($target !== '' && $target < $today) {
            return $make('late', 'Lewat target '.$this->days($target, $today).' hari', 'danger', 1);
        }
        if ($status === 'Tertunda') {
            return $make('hold', 'Tertunda', 'warning', 3);
        }
        if ($status === 'Berjalan') {
            $last = ($row['Diubah_Pada'] ?? '') ?: ($row['Tgl_Mulai'] ?? '');
            $days = $last !== '' ? $this->days($last, $today) : 0;
            if ($days > 14) {
                return $make('stale', 'Mandek '.$days.' hari', 'danger', 2);
            }
            if ($target !== '' && $this->days($today, $target) <= 7) {
                return $make('due', 'Jatuh tempo ≤ 7 hari', 'warning', 4);
            }

            return $make('ok', 'Sesuai jadwal', 'success', 8);
        }
        $dependency = $index[$row['Prasyarat_ID'] ?? ''] ?? null;
        if ($dependency && ($dependency['Status'] ?? '') !== 'Selesai') {
            return $make('blocked', 'Menunggu '.$row['Prasyarat_ID'], 'info', 6);
        }
        if (trim((string) ($row['Nama_PIC'] ?? '')) === '') {
            return $make('nopic', 'Belum ada PIC', 'warning', 5);
        }

        return $make('ready', 'Siap dimulai', 'info', 7);
    }

    private function days(string $from, string $to): int
    {
        try {
            return (int) CarbonImmutable::parse(substr($from, 0, 10))->startOfDay()
                ->diffInDays(CarbonImmutable::parse($to)->startOfDay(), false);
        } catch (\Throwable) {
            return 0;
        }
    }

    public function filter(array $rows, array $filters, bool $allBranches = false): array
    {
        $fields = ['jalur' => 'Jalur_RM', 'status' => 'Status', 'jenis' => 'Jenis_Potensi', 'dasar' => 'Dasar_Angka',
            'ring' => 'Ring', 'pic' => 'Nama_PIC', 'arah' => 'Arah', 'eco' => 'ID_Ekosistem', 'peran' => 'Peran_PIC'];

        return array_values(array_filter($rows, static function (array $row) use ($filters, $fields, $allBranches): bool {
            if (! $allBranches && ($filters['kc'] ?? 'all') !== 'all' && mb_strtolower($row['KC'] ?? '') !== $filters['kc']) {
                return false;
            }
            foreach ($fields as $key => $field) {
                if ($key === 'pic' && ($filters[$key] ?? '') === '__assigned') {
                    if (trim((string) ($row[$field] ?? '')) === '') {
                        return false;
                    }

                    continue;
                }
                if (($key === 'pic' && ($filters[$key] ?? '') === '__unassigned') || (in_array($key, ['peran', 'ring', 'jenis'], true) && ($filters[$key] ?? '') === '__empty')) {
                    if (trim((string) ($row[$field] ?? '')) !== '') {
                        return false;
                    }

                    continue;
                }
                if (($filters[$key] ?? '') !== '' && trim((string) ($row[$field] ?? '')) !== $filters[$key]) {
                    return false;
                }
            }
            $condition = $filters['condition'] ?? '';
            if ($condition === 'unit' && empty($row['Cek_Satuan'])) {
                return false;
            }
            if ($condition === 'unmapped' && ($row['Ekosistem_Terdaftar'] ?? ! empty($row['ID_Ekosistem']))) {
                return false;
            }
            if ($condition !== '' && ! in_array($condition, ['unit', 'unmapped'], true) && ($row['Kondisi']['key'] ?? '') !== $condition) {
                return false;
            }
            $term = mb_strtolower(trim($filters['q'] ?? ''));
            $search = implode(' ', array_map(static fn (string $field): string => (string) ($row[$field] ?? ''), [
                'ID_Prospek', 'Prospek', 'Produk', 'Ekosistem', 'Nama_PIC', 'Jalur_RM', 'KC', 'Sasaran', 'Ring', 'Simpul', 'Bank_Lain',
            ]));

            return $term === '' || str_contains(mb_strtolower($search), $term);
        }));
    }

    public function stats(array $rows): array
    {
        $registered = array_filter($rows, static fn (array $row): bool => $row['Ekosistem_Terdaftar'] ?? ! empty($row['ID_Ekosistem']));
        $result = ['total' => count($rows), 'ecosystems' => count(array_unique(array_column($registered, 'ID_Ekosistem'))),
            'ecosystem_groups' => count(array_unique(array_map(static fn (array $row): string => ($row['Ekosistem'] ?? '') ?: 'Lainnya', $rows))),
            'unmapped_ecosystem' => count($rows) - count($registered),
            'categories' => array_fill_keys(['Simpanan', 'Kredit', 'Arus dana'], ['min' => 0.0, 'max' => 0.0]),
            'payroll' => 0, 'realization' => 0.0, 'customers' => 0, 'assigned' => 0,
            'statuses' => array_fill_keys(['Belum mulai', 'Berjalan', 'Tertunda', 'Selesai', 'Batal'], 0),
            'conditions' => array_fill_keys(array_keys(self::CONDITIONS), 0), 'types' => [], 'unit_issues' => 0];
        foreach ($rows as $row) {
            $type = (string) (($row['Jenis_Potensi'] ?? '') ?: 'Belum diklasifikasi');
            $result['types'][$type] = ($result['types'][$type] ?? 0) + 1;
            if (isset($result['categories'][$type])) {
                $min = (float) ($row['Est_Min_Juta'] ?? 0);
                $result['categories'][$type]['min'] += $min;
                $result['categories'][$type]['max'] += (float) ($row['Est_Max_Juta'] ?? $min);
            }
            if ($type === 'Payroll') {
                $result['payroll'] += (int) ($row['Est_Nasabah'] ?? 0);
            }
            $result['realization'] += (float) ($row['Realisasi_Nominal_Juta'] ?? 0);
            $result['customers'] += (int) ($row['Realisasi_Nasabah'] ?? 0);
            $result['assigned'] += trim((string) ($row['Nama_PIC'] ?? '')) !== '' ? 1 : 0;
            $status = ($row['Status'] ?? '') ?: 'Belum mulai';
            $result['statuses'][$status] = ($result['statuses'][$status] ?? 0) + 1;
            $key = $row['Kondisi']['key'] ?? '';
            if (isset($result['conditions'][$key])) {
                $result['conditions'][$key]++;
            }
            $result['unit_issues'] += ! empty($row['Cek_Satuan']) ? 1 : 0;
        }
        $result['coverage'] = $result['total'] > 0 ? (int) round($result['assigned'] / $result['total'] * 100) : 0;
        $result['unassigned'] = $result['total'] - $result['assigned'];
        $result['conditions']['unit'] = $result['unit_issues'];
        $result['conditions']['unmapped'] = $result['unmapped_ecosystem'];

        return $result;
    }

    public function priority(array $rows): array
    {
        $rows = array_filter($rows, static fn (array $row): bool => in_array($row['Kondisi']['key'], ['late', 'stale', 'due', 'hold', 'nopic'], true));
        usort($rows, static fn (array $a, array $b): int => $a['Kondisi']['rank'] <=> $b['Kondisi']['rank']
            ?: ((float) (($b['Est_Max_Juta'] ?? 0) ?: ($b['Est_Min_Juta'] ?? 0)) <=> (float) (($a['Est_Max_Juta'] ?? 0) ?: ($a['Est_Min_Juta'] ?? 0))));

        return array_slice($rows, 0, 8);
    }

    public function picGroups(array $rows): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $name = trim((string) ($row['Nama_PIC'] ?? ''));
            $label = $name !== '' ? $name : 'Belum ada PIC · '.(($row['Peran_PIC'] ?? '') ?: $row['Jalur_RM']);
            $key = $label.'|'.$row['Jalur_RM'].'|'.$row['KC'];
            $groups[$key]['label'] = $label;
            $groups[$key]['pic'] = $name;
            $groups[$key]['jalur'] = $row['Jalur_RM'];
            $groups[$key]['kc'] = $row['KC'];
            $groups[$key]['peran'] = $row['Peran_PIC'] ?? '';
            $groups[$key]['rows'][] = $row;
        }
        foreach ($groups as &$group) {
            $group['stats'] = $this->stats($group['rows']);
            unset($group['rows']);
        }
        unset($group);
        usort($groups, static fn (array $a, array $b): int => $b['stats']['total'] <=> $a['stats']['total']);

        return array_values($groups);
    }
}
