<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use RuntimeException;

final class InstitutionMarketShareSheet
{
    /** Read the exported cells, without Google's gviz header/type inference. */
    public static function parse(string $csv): array
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, preg_replace('/^\xEF\xBB\xBF/', '', $csv) ?? $csv);
        rewind($stream);
        $matrix = [];
        while (($row = fgetcsv($stream)) !== false) {
            $matrix[] = array_map(static fn ($cell): string => trim((string) $cell), $row);
        }
        fclose($stream);

        $headerEnd = null;
        foreach (array_slice($matrix, 0, 6) as $index => $row) {
            $tokens = array_map(self::token(...), $row);
            if (in_array('NAMA INSTANSI', $tokens, true)) {
                $headerEnd = $index;
            }
            if ($headerEnd !== null && in_array('DEB', $tokens, true) && in_array('OUTSTANDING', $tokens, true)) {
                $headerEnd = $index;
                break;
            }
        }
        if ($headerEnd === null) {
            throw new RuntimeException('Header Nama Instansi tidak ditemukan pada sumber Marketshare Instansi.');
        }

        $headers = array_slice($matrix, 0, $headerEnd + 1);
        $width = max(array_map('count', $headers));
        $columns = [];
        $keys = [];
        $periods = [];
        $group = null;
        for ($column = 0; $column < $width; $column++) {
            $parts = array_values(array_filter(array_map(static fn ($row): string => $row[$column] ?? '', $headers), static fn ($v): bool => $v !== ''));
            $label = implode(' ', array_unique($parts));
            $token = self::token($label);
            $period = self::period($token);
            $metricGroup = $period !== null || preg_match('/^(MTD|YTD|YOY)(?: DEB)?$/', $token);
            if ($metricGroup) {
                $group = $period ?? strtolower(explode(' ', $token)[0]);
                $display = $period !== null ? CarbonImmutable::parse($period)->locale('id')->translatedFormat('d F Y') : strtoupper($group);
                $columns[] = $display.' Deb';
                $keys[] = $group.':deb';
                if ($period !== null) {
                    $periods[] = $period;
                }
            } elseif ($group !== null && ($token === '' || in_array($token, ['OUTSTANDING', 'OS'], true))) {
                $columns[] = 'Outstanding';
                $keys[] = $group.':outstanding';
                $group = null;
            } else {
                $group = null;
                $columns[] = $label !== '' ? $label : 'Kolom '.($column + 1);
                $keys[] = match (true) {
                    in_array($token, ['PEGAWAI', 'TOTAL PEGAWAI'], true) => 'employees',
                    str_starts_with($token, 'POTENSI BRIG') => 'potential',
                    $token === '' => 'unlabelled:'.$column,
                    default => strtolower(str_replace(' ', '_', $token)),
                };
            }
        }
        if (! in_array('potential', $keys, true) || count(array_unique($keys)) !== count($keys)) {
            throw new RuntimeException('Kolom potensi hilang atau pemetaan kolom instansi ambigu; cache sebelumnya dipertahankan.');
        }
        foreach ($keys as $key) {
            if (str_ends_with($key, ':deb') && ! in_array(substr($key, 0, -4).':outstanding', $keys, true)) {
                throw new RuntimeException('Pasangan Deb dan Outstanding tidak lengkap pada sumber instansi.');
            }
        }
        $rows = [];
        foreach (array_slice($matrix, $headerEnd + 1) as $row) {
            if (implode('', $row) === '') {
                continue;
            }
            if (count($row) > $width && implode('', array_slice($row, $width)) !== '') {
                throw new RuntimeException('Data instansi memiliki kolom tanpa header; sinkronisasi dihentikan.');
            }
            $rows[] = array_slice(array_pad($row, $width, ''), 0, $width);
        }
        if ($rows === []) {
            throw new RuntimeException('Sumber instansi tidak memiliki data; cache sebelumnya dipertahankan.');
        }

        return ['columns' => $columns, 'column_keys' => $keys, 'rows' => $rows, 'period' => $periods === [] ? null : max($periods)];
    }

    /** Filter presentation only; retain the full source and its totals in the cache. */
    public static function forDisplay(array $sheet, string $scope): array
    {
        if (! in_array($scope, ['magetan', 'kc-magetan', 'area6'], true)) {
            return $sheet;
        }
        $keys = $sheet['column_keys'] ?? [];
        $employees = array_search('employees', $keys, true);
        $potential = array_search('potential', $keys, true);
        $branch = array_search('cabang', $keys, true);
        if ($employees === false || $potential === false || ($scope === 'area6' && $branch === false)) {
            throw new RuntimeException('Kolom pegawai/potensi atau cabang tidak lengkap untuk tampilan instansi Magetan.');
        }
        $sourceRows = $sheet['rows'];
        $sheet['rows'] = array_values(array_filter($sourceRows, static function (array $row) use ($scope, $branch, $employees, $potential): bool {
            if ($scope === 'area6' && self::token($row[$branch] ?? '') !== 'KC MAGETAN') {
                return true;
            }

            return ! self::zeroOrBlank($row[$employees] ?? '') && ! self::zeroOrBlank($row[$potential] ?? '');
        }));
        $sheet['sourceRowCount'] = count($sourceRows);
        $sheet['rowCount'] = count($sheet['rows']);
        $sheet['hiddenRowCount'] = $sheet['sourceRowCount'] - $sheet['rowCount'];

        return $sheet;
    }

    private static function zeroOrBlank(string $value): bool
    {
        $value = preg_replace('/[\s\x{00A0}()]/u', '', $value) ?? $value;

        return in_array($value, ['', '-', '–', '—'], true) || preg_match('/^[+-]?0+(?:[.,]0+)*$/D', $value) === 1;
    }

    /** Align every branch by meaning and period; never by the original column offset. */
    public static function consolidate(array $branches): array
    {
        $labels = [];
        foreach ($branches as $payload) {
            foreach ($payload['column_keys'] as $index => $key) {
                $labels[$key] ??= $payload['columns'][$index];
            }
        }
        $identity = array_filter(array_keys($labels), static fn ($key): bool => in_array($key, ['nama_instansi', 'kepala_instansi'], true));
        $periods = array_filter(array_keys($labels), static fn ($key): bool => preg_match('/^\d{4}-\d{2}-\d{2}:/', $key) === 1);
        sort($periods);
        $keys = array_merge($identity, $periods, array_values(array_diff(array_keys($labels), $identity, $periods)));
        $rows = [];
        foreach ($branches as $branch => $payload) {
            foreach ($payload['rows'] as $row) {
                $values = array_combine($payload['column_keys'], $row);
                $rows[] = array_merge([$branch], array_map(static fn ($key): string => $values[$key] ?? '', $keys));
            }
        }

        return ['columns' => array_merge(['Cabang'], array_map(static fn ($key): string => $labels[$key], $keys)), 'column_keys' => array_merge(['cabang'], $keys), 'rows' => $rows];
    }

    private static function token(string $label): string
    {
        return trim(preg_replace('/\s+/u', ' ', strtoupper($label)) ?? $label);
    }

    private static function period(string $label): ?string
    {
        $months = ['JANUARI' => 1, 'JAN' => 1, 'FEBRUARI' => 2, 'FEB' => 2, 'MARET' => 3, 'MAR' => 3, 'APRIL' => 4, 'APR' => 4, 'MEI' => 5, 'JUNI' => 6, 'JUN' => 6, 'JULI' => 7, 'JUL' => 7, 'AGUSTUS' => 8, 'AGT' => 8, 'AGS' => 8, 'AGU' => 8, 'SEPTEMBER' => 9, 'SEP' => 9, 'OKTOBER' => 10, 'OKT' => 10, 'NOVEMBER' => 11, 'NOV' => 11, 'DESEMBER' => 12, 'DES' => 12];
        if (! preg_match('/^(?:(\d{1,2})\s+)?([A-Z]+)\s+(20\d{2})(?:\s+DEB)?$/', $label, $matches) || ! isset($months[$matches[2]])) {
            return null;
        }
        $month = CarbonImmutable::create((int) $matches[3], $months[$matches[2]], 1);
        // Month-only headers denote month end; invalid 31 September must not overflow into October.
        $day = empty($matches[1]) ? $month->daysInMonth : min((int) $matches[1], $month->daysInMonth);

        return $month->day($day)->toDateString();
    }
}
