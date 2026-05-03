<?php

namespace App\Services\Import;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class L1133CsvImporter
{
    public const TABLE = 'l1133';

    public const NORMALIZED_HEADERS = [
        'periode',
        'kode_kanwil',
        'nama_kanwil',
        'kode_kanca',
        'nama_kanca',
        'kode_uker',
        'nama_uker',
        'jenis',
        'jumlah_debitur',
        'jumlah_rekening',
        'outstanding',
        'jumlah_debitur_npl',
        'npl',
        'jumlah_debitur_dpk',
        'dpk',
    ];

    /**
     * @return array{metadata: array<string, mixed>, rows: array<int, array<string, mixed>>, warnings: array<int, string>}
     */
    public function parse(string $path, int $limit = 0): array
    {
        $warnings = [];
        $rows = [];
        $written = 0;

        $result = $this->streamNormalizedRows(
            $path,
            function (array $row) use (&$rows, &$written, $limit): bool {
                $rows[] = $row;
                $written++;

                return $limit <= 0 || $written < $limit;
            },
            $warnings
        );

        return [
            'metadata' => $result['metadata'],
            'rows' => $rows,
            'warnings' => $warnings,
        ];
    }

    /**
     * @return array{absolute_path: string, relative_path: string, total_rows: int, metadata: array<string, mixed>, warnings: array<int, string>}
     */
    public function stageNormalizedCsv(string $sourcePath, ?string $relativeDirectory = null): array
    {
        $relativeDirectory ??= 'import_staging';
        $absoluteDirectory = storage_path('app/' . trim($relativeDirectory, '/\\'));
        if (!is_dir($absoluteDirectory)) {
            mkdir($absoluteDirectory, 0777, true);
        }

        $fileName = 'l1133_normalized_' . Str::uuid()->toString() . '.csv';
        $absolutePath = $absoluteDirectory . DIRECTORY_SEPARATOR . $fileName;
        $relativePath = trim($relativeDirectory, '/\\') . '/' . $fileName;

        $output = fopen($absolutePath, 'wb');
        if ($output === false) {
            throw new \RuntimeException('Gagal membuat CSV staging L1133.');
        }

        $warnings = [];
        $totalRows = 0;

        try {
            fputcsv($output, self::NORMALIZED_HEADERS);
            $result = $this->streamNormalizedRows(
                $sourcePath,
                function (array $row) use ($output, &$totalRows): bool {
                    fputcsv($output, array_map(static fn (string $header) => $row[$header] ?? null, self::NORMALIZED_HEADERS));
                    $totalRows++;

                    return true;
                },
                $warnings
            );
        } catch (\Throwable $e) {
            fclose($output);
            @unlink($absolutePath);
            throw $e;
        }

        fclose($output);

        return [
            'absolute_path' => $absolutePath,
            'relative_path' => $relativePath,
            'total_rows' => $totalRows,
            'metadata' => $result['metadata'],
            'warnings' => $warnings,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array{inserted: int, deleted: int}
     */
    public function importRows(array $rows, bool $replacePeriod = true): array
    {
        if ($rows === []) {
            return ['inserted' => 0, 'deleted' => 0];
        }

        return DB::transaction(function () use ($rows, $replacePeriod): array {
            $deleted = 0;
            if ($replacePeriod) {
                $deleted = DB::table(self::TABLE)
                    ->where('periode', $rows[0]['periode'])
                    ->delete();
            }

            $now = now();
            $payload = [];
            foreach ($rows as $index => $row) {
                $row['uniqueid_namareport'] = $row['uniqueid_namareport']
                    ?? $this->makeUniqueId($row, $index + 1);
                $row['created_at'] = $now;
                $row['updated_at'] = $now;
                $payload[] = $row;
            }

            foreach (array_chunk($payload, 1000) as $chunk) {
                DB::table(self::TABLE)->upsert(
                    $chunk,
                    ['uniqueid_namareport'],
                    array_values(array_diff(array_keys($chunk[0]), ['uniqueid_namareport', 'created_at']))
                );
            }

            return ['inserted' => count($rows), 'deleted' => $deleted];
        });
    }

    /**
     * @param callable(array<string, mixed>): bool $onRow
     * @param array<int, string> $warnings
     * @return array{metadata: array<string, mixed>}
     */
    private function streamNormalizedRows(string $path, callable $onRow, array &$warnings): array
    {
        if (!is_file($path)) {
            throw new \InvalidArgumentException("File CSV tidak ditemukan: {$path}");
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException("File CSV tidak bisa dibuka: {$path}");
        }

        $metadata = [
            'periode' => null,
            'kode_kanwil' => null,
            'nama_kanwil' => null,
        ];
        $lineNumber = 0;
        $headerFound = false;
        $headerMap = [];
        $delimiter = ',';

        try {
            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                $lineNumber++;
                $row = $this->normalizeRow($row);

                if ($this->isBlankRow($row)) {
                    continue;
                }

                if ($lineNumber === 2) {
                    $kanwil = $this->splitCodeName($row[0] ?? null);
                    $metadata = [
                        'periode' => $this->normalizeDate($row[1] ?? null),
                        'kode_kanwil' => $kanwil['code'],
                        'nama_kanwil' => $kanwil['name'],
                    ];

                    continue;
                }

                if (!$headerFound && $this->looksLikeHeader($row)) {
                    $headerFound = true;
                    $headerMap = $this->buildHeaderMap($row);
                    continue;
                }

                if (!$headerFound) {
                    continue;
                }

                $kodeBr = $this->splitCodeName($row[$headerMap['kode_br']] ?? null);
                $mbdesc = $this->splitCodeName($row[$headerMap['mbdesc']] ?? null);
                $record = [
                    'periode' => $metadata['periode'],
                    'kode_kanwil' => $metadata['kode_kanwil'],
                    'nama_kanwil' => $metadata['nama_kanwil'],
                    'kode_kanca' => $kodeBr['code'],
                    'nama_kanca' => $kodeBr['name'],
                    'kode_uker' => $mbdesc['code'],
                    'nama_uker' => $mbdesc['name'],
                    'jenis' => $this->blankToNull($row[$headerMap['jenis']] ?? null),
                    'jumlah_debitur' => $this->normalizeInteger($row[$headerMap['jumlah_debitur']] ?? null),
                    'jumlah_rekening' => $this->normalizeInteger($row[$headerMap['jumlah_rekening']] ?? null),
                    'outstanding' => $this->normalizeDecimal($row[$headerMap['outstanding']] ?? null),
                    'jumlah_debitur_npl' => $this->normalizeInteger($row[$headerMap['jumlah_debitur_npl']] ?? null),
                    'npl' => $this->normalizeDecimal($row[$headerMap['npl']] ?? null),
                    'jumlah_debitur_dpk' => $this->normalizeInteger($row[$headerMap['jumlah_debitur_dpk']] ?? null),
                    'dpk' => $this->normalizeDecimal($row[$headerMap['dpk']] ?? null),
                ];

                if ($record['kode_kanca'] === null || $record['jenis'] === null) {
                    $warnings[] = "Baris {$lineNumber} dilewati karena kode cabang atau jenis kosong.";
                    continue;
                }

                if ($onRow($record) === false) {
                    break;
                }
            }
        } finally {
            fclose($handle);
        }

        if (!$headerFound) {
            throw new \RuntimeException('Header data L1133 tidak ditemukan.');
        }

        foreach (['periode', 'kode_kanwil', 'nama_kanwil'] as $key) {
            if ($metadata[$key] === null) {
                $warnings[] = "Metadata {$key} kosong atau tidak terbaca.";
            }
        }

        return ['metadata' => $metadata];
    }

    /**
     * @param array<int, string|null> $row
     * @return array<int, string|null>
     */
    private function normalizeRow(array $row): array
    {
        if (isset($row[0])) {
            $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $row[0]);
        }

        return array_map(function ($value): ?string {
            if ($value === null) {
                return null;
            }

            $value = str_replace("\xC2\xA0", ' ', (string) $value);
            $value = trim($value);

            return $value === '' ? null : $value;
        }, $row);
    }

    /**
     * @param array<int, string|null> $row
     */
    private function looksLikeHeader(array $row): bool
    {
        $normalized = array_map(static fn ($value): string => strtolower(trim((string) $value)), $row);

        return in_array('kode_br', $normalized, true)
            && in_array('mbdesc', $normalized, true)
            && in_array('jenis', $normalized, true)
            && in_array('deb_npl', $normalized, true)
            && in_array('deb_dpk', $normalized, true);
    }

    /**
     * @param array<int, string|null> $row
     * @return array<string, int>
     */
    private function buildHeaderMap(array $row): array
    {
        $lookup = [];
        foreach ($row as $index => $header) {
            $lookup[strtolower(trim((string) $header))] = $index;
        }

        return [
            'kode_br' => $lookup['kode_br'],
            'mbdesc' => $lookup['mbdesc'],
            'jenis' => $lookup['jenis'],
            'jumlah_debitur' => $lookup['textbox3'],
            'jumlah_rekening' => $lookup['textbox11'],
            'outstanding' => $lookup['textbox16'],
            'jumlah_debitur_npl' => $lookup['deb_npl'],
            'npl' => $lookup['textbox23'],
            'jumlah_debitur_dpk' => $lookup['deb_dpk'],
            'dpk' => $lookup['textbox25'],
        ];
    }

    /**
     * @return array{code: ?string, name: ?string}
     */
    private function splitCodeName($value): array
    {
        $value = $this->blankToNull($value);
        if ($value === null) {
            return ['code' => null, 'name' => null];
        }

        if (str_contains($value, '--')) {
            [$code, $name] = explode('--', $value, 2);

            return [
                'code' => $this->blankToNull($code),
                'name' => $this->blankToNull($name),
            ];
        }

        return ['code' => null, 'name' => $value];
    }

    /**
     * @param array<int, string|null> $row
     */
    private function isBlankRow(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function blankToNull($value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function normalizeDate($value): ?string
    {
        $value = $this->blankToNull($value);
        if ($value === null) {
            return null;
        }

        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d'] as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);
                if ($date !== false) {
                    return $date->format('Y-m-d');
                }
            } catch (\Throwable) {
            }
        }

        return null;
    }

    private function normalizeDecimal($value): ?string
    {
        $value = $this->blankToNull($value);
        if ($value === null) {
            return null;
        }

        $normalized = preg_replace('/[^0-9.\-]/', '', $value);

        return $normalized === '' || !is_numeric($normalized)
            ? null
            : number_format((float) $normalized, 2, '.', '');
    }

    private function normalizeInteger($value): ?int
    {
        $value = $this->blankToNull($value);
        if ($value === null) {
            return null;
        }

        $normalized = preg_replace('/[^0-9\-]/', '', $value);

        return $normalized === '' || !is_numeric($normalized)
            ? null
            : (int) $normalized;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function makeUniqueId(array $row, int $sequence): string
    {
        return 'L1133_' . sha1(implode('|', [
            $row['periode'] ?? '',
            $row['kode_kanwil'] ?? '',
            $row['kode_kanca'] ?? '',
            $row['kode_uker'] ?? '',
            $row['jenis'] ?? '',
            $sequence,
        ]));
    }
}
