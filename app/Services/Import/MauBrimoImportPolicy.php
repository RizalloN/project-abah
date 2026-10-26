<?php

namespace App\Services\Import;

use App\Support\StrictDateParser;
use Illuminate\Validation\ValidationException;

class MauBrimoImportPolicy
{
    public const TABLE = 'mau_brimo_r_all';

    public const COLUMNS = [
        'tahun', 'periode', 'posisi', 'region', 'rgdesc', 'mainbr', 'mbdesc',
        'branch', 'brdesc', 'jenis', 'kategori', 'segmentasi', 'frequency',
        'jumlah_cifno', 'jumlah_rek_fin',
    ];

    private const BRANCH_CODES = [
        'MADIUN' => '45',
        'MAGETAN' => '49',
        'NGAWI' => '57',
        'PONOROGO' => '70',
    ];

    public function mbdescIndex(array $headers): int
    {
        $matches = [];
        foreach ($headers as $index => $header) {
            if ($this->headerToken($header) === 'mbdesc') {
                $matches[] = (int) $index;
            }
        }

        if (count($matches) !== 1) {
            throw ValidationException::withMessages([
                'file' => count($matches) === 0
                    ? 'Header MAU BRIMO wajib memiliki kolom MBDESC.'
                    : 'Header MAU BRIMO memiliki kolom MBDESC ganda.',
            ]);
        }

        return $matches[0];
    }

    public function headerMap(array $headers): array
    {
        $map = [];
        $known = array_combine(array_map(fn (string $column) => str_replace('_', '', $column), self::COLUMNS), self::COLUMNS);
        foreach ($headers as $index => $header) {
            $token = $this->headerToken($header);
            $column = $known[$token] ?? null;
            if ($column === null) {
                continue;
            }
            if (array_key_exists($column, $map)) {
                throw ValidationException::withMessages(['file' => "Header MAU BRIMO memiliki kolom {$column} ganda."]);
            }
            $map[$column] = (int) $index;
        }

        $missing = array_values(array_diff(self::COLUMNS, array_keys($map)));
        if ($missing !== []) {
            throw ValidationException::withMessages(['file' => 'Kolom wajib MAU BRIMO belum lengkap: '.implode(', ', $missing).'.']);
        }

        return $map;
    }

    public function branch(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }
        $value = strtoupper($this->collapseWhitespace((string) $value));
        $pattern = '/^(?:(0*(?:45|49|57|70))\s*(?:--?|:)\s*)?(?:KC\s+)?(MADIUN|MAGETAN|NGAWI|PONOROGO)$/D';
        if (preg_match($pattern, $value, $matches) !== 1) {
            return null;
        }
        $city = $matches[2];
        $code = ltrim($matches[1], '0');
        if ($code !== '' && $code !== self::BRANCH_CODES[$city]) {
            return null;
        }

        return 'KC '.$city;
    }

    public function belongsToArea6(mixed $value): bool
    {
        return $this->branch($value) !== null;
    }

    public function normalizeRow(array $row, array $headerMap, int $line): ?array
    {
        $branchIndex = $headerMap['mbdesc'] ?? null;
        if ($branchIndex === null) {
            $this->invalidRow($line, 'mapping MBDESC tidak tersedia');
        }
        if (! $this->belongsToArea6($row[$branchIndex] ?? null)) {
            return null;
        }
        $normalized = [];
        foreach (self::COLUMNS as $column) {
            $index = $headerMap[$column] ?? null;
            if ($index === null || ! array_key_exists($index, $row)
                || (! is_string($row[$index]) && ! is_int($row[$index]))) {
                $this->invalidRow($line, "kolom {$column} tidak valid");
            }
            $value = $this->trimText((string) $row[$index]);
            if ($value === '') {
                $this->invalidRow($line, "kolom {$column} kosong");
            }
            $normalized[$column] = $value;
        }

        $position = StrictDateParser::normalize($normalized['posisi']);
        if ($position === null || $position === StrictDateParser::ZERO_DATE) {
            $this->invalidRow($line, 'kolom posisi tidak valid');
        }
        if (preg_match('/^\d{4}-(?:0[1-9]|1[0-2])$/D', $normalized['periode']) !== 1
            || $normalized['periode'] !== substr($position, 0, 7)) {
            $this->invalidRow($line, 'kolom periode tidak valid atau tidak sesuai posisi');
        }
        if (preg_match('/^\d{4}$/D', $normalized['tahun']) !== 1
            || $normalized['tahun'] !== substr($position, 0, 4)) {
            $this->invalidRow($line, 'kolom tahun tidak valid atau tidak sesuai posisi');
        }

        $normalized['posisi'] = $position;
        foreach (['tahun', 'frequency', 'jumlah_cifno', 'jumlah_rek_fin'] as $column) {
            $normalized[$column] = $this->nonnegativeInteger($normalized[$column], $column, $line);
        }

        return $normalized;
    }

    public function identity(array $normalized): string
    {
        $dimensions = [];
        foreach (array_diff(self::COLUMNS, ['jumlah_cifno', 'jumlah_rek_fin']) as $column) {
            $dimensions[$column] = strtoupper($this->collapseWhitespace((string) ($normalized[$column] ?? '')));
        }

        return 'MAUBRIMO:'.hash('sha256', json_encode($dimensions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    public function initialSelections(array $headers, array $uniqueValues): array
    {
        $index = $this->mbdescIndex($headers);
        $values = [];
        foreach ((array) ($uniqueValues[$index] ?? []) as $value) {
            if ($this->belongsToArea6($value)) {
                $values[(string) $value] = true;
            }
        }

        return $values === [] ? [] : [(string) $index => array_keys($values)];
    }

    public function assertSelectedColumns(array $headers, array $selected): void
    {
        $map = $this->headerMap($headers);
        $lookup = [];
        foreach ($selected as $index) {
            if ((! is_int($index) && ! is_string($index)) || preg_match('/^\d+$/D', (string) $index) !== 1) {
                throw ValidationException::withMessages(['selected_columns' => 'Indeks kolom MAU BRIMO tidak valid.']);
            }
            $lookup[ltrim((string) $index, '0') ?: '0'] = true;
        }
        foreach (self::COLUMNS as $column) {
            if (! isset($lookup[(string) $map[$column]])) {
                throw ValidationException::withMessages(['selected_columns' => "Kolom {$column} wajib dipilih untuk import MAU BRIMO."]);
            }
        }
    }

    private function headerToken(mixed $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header);

        return strtolower((string) preg_replace('/[^A-Z0-9]+/', '', strtoupper($this->trimText($header))));
    }

    private function trimText(string $value): string
    {
        return trim(str_replace("\xC2\xA0", ' ', $value));
    }

    private function collapseWhitespace(string $value): string
    {
        return trim((string) preg_replace('/[\s\p{Z}]+/u', ' ', $this->trimText($value)));
    }

    private function nonnegativeInteger(string $value, string $column, int $line): int
    {
        if (preg_match('/^\d+$/D', $value) !== 1) {
            $this->invalidRow($line, "kolom {$column} wajib bilangan bulat nonnegatif");
        }
        $digits = ltrim($value, '0');
        $maximum = (string) PHP_INT_MAX;
        if (strlen($digits) > strlen($maximum)
            || (strlen($digits) === strlen($maximum) && strcmp($digits, $maximum) > 0)) {
            $this->invalidRow($line, "kolom {$column} melebihi batas bilangan bulat");
        }

        return $digits === '' ? 0 : (int) $digits;
    }

    private function invalidRow(int $line, string $message): never
    {
        throw ValidationException::withMessages(['file' => "Baris {$line}: {$message}."]);
    }
}
