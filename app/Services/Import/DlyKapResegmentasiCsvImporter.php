<?php

namespace App\Services\Import;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DlyKapResegmentasiCsvImporter
{
    public const TABLE = 'dly_kap_resegmentasi';

    private const METRIC_COLUMNS = [
        'l_rp' => 2,
        'l_deb' => 3,
        'dpk_rp' => 4,
        'dpk_deb' => 5,
        'kl_rp' => 6,
        'kl_deb' => 7,
        'd_rp' => 8,
        'd_deb' => 9,
        'm_rp' => 10,
        'm_deb' => 11,
        'npl_rp' => 12,
        'npl_deb' => 13,
        'tl_rp' => 14,
        'tl_deb' => 15,
    ];

    /**
     * @return array{metadata: array<string, mixed>, rows: array<int, array<string, mixed>>, warnings: array<int, string>}
     */
    public function parse(string $path): array
    {
        if (!is_file($path)) {
            throw new \InvalidArgumentException("File CSV tidak ditemukan: {$path}");
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException("File CSV tidak bisa dibuka: {$path}");
        }

        $warnings = [];
        $metadata = [
            'periode' => null,
            'kanwil' => null,
            'kode_cabang' => null,
            'kode_unit' => null,
        ];
        $rows = [];
        $lineNumber = 0;
        $sectionHeader = null;
        $skipNextFooterTotalRow = false;
        $delimiter = ',';

        try {
            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                $lineNumber++;
                $row = $this->normalizeRow($row);

                if ($lineNumber === 1) {
                    continue;
                }

                if ($lineNumber === 2) {
                    $metadata = [
                        'periode' => $this->normalizeDate($row[0] ?? null),
                        'kanwil' => $this->blankToNull($row[1] ?? null),
                        'kode_cabang' => $this->blankToNull($row[2] ?? null),
                        'kode_unit' => $this->blankToNull($row[3] ?? null),
                    ];

                    continue;
                }

                if ($this->isBlankRow($row)) {
                    continue;
                }

                $firstCell = strtoupper(trim((string) ($row[0] ?? '')));
                if ($skipNextFooterTotalRow) {
                    $skipNextFooterTotalRow = false;
                    continue;
                }

                if (str_starts_with($firstCell, 'TEXTBOX')) {
                    $skipNextFooterTotalRow = true;
                    continue;
                }

                if (str_starts_with($firstCell, 'SEGMEN')) {
                    $sectionHeader = $firstCell;
                    continue;
                }

                if ($sectionHeader === null) {
                    $warnings[] = "Baris {$lineNumber} dilewati karena muncul sebelum header SEGMEN.";
                    continue;
                }

                if (count($row) < 16) {
                    $warnings[] = "Baris {$lineNumber} dilewati karena kolom kurang dari format minimum 16 kolom.";
                    continue;
                }

                $rows[] = $this->makeMetricRecord(
                    $metadata,
                    $sectionHeader,
                    $lineNumber,
                    $this->blankToNull($row[0] ?? null),
                    $this->blankToNull($row[1] ?? null),
                    $row,
                    2
                );
            }
        } finally {
            fclose($handle);
        }

        foreach (['periode', 'kanwil', 'kode_cabang', 'kode_unit'] as $key) {
            if ($metadata[$key] === null) {
                $warnings[] = "Metadata {$key} kosong atau tidak terbaca dari baris 2.";
            }
        }

        return [
            'metadata' => $metadata,
            'rows' => $rows,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array{inserted: int, deleted: int}
     */
    public function import(array $rows, bool $replaceScope = true): array
    {
        if ($rows === []) {
            return ['inserted' => 0, 'deleted' => 0];
        }

        return DB::transaction(function () use ($rows, $replaceScope): array {
            $deleted = 0;

            if ($replaceScope) {
                $first = $rows[0];
                $deleted = DB::table(self::TABLE)
                    ->where('periode', $first['periode'])
                    ->where('kanwil', $first['kanwil'])
                    ->where('kode_cabang', $first['kode_cabang'])
                    ->where('kode_unit', $first['kode_unit'])
                    ->delete();
            }

            $now = now();
            $payload = array_map(static function (array $row) use ($now): array {
                $row['created_at'] = $now;
                $row['updated_at'] = $now;

                return $row;
            }, $rows);

            foreach (array_chunk($payload, 1000) as $chunk) {
                DB::table(self::TABLE)->upsert(
                    $chunk,
                    ['uniqueid_dly_kap'],
                    array_values(array_diff(array_keys($chunk[0]), ['uniqueid_dly_kap', 'created_at']))
                );
            }

            return ['inserted' => count($rows), 'deleted' => $deleted];
        });
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
     * @param array<string, mixed> $metadata
     * @param array<int, string|null> $row
     * @return array<string, mixed>
     */
    private function makeMetricRecord(
        array $metadata,
        string $sectionHeader,
        int $lineNumber,
        ?string $segmen,
        ?string $keterangan,
        array $row,
        int $metricOffset
    ): array {
        $record = [
            'uniqueid_dly_kap' => $this->makeUniqueId($metadata, $sectionHeader, $lineNumber, [$segmen, $keterangan]),
            'periode' => $metadata['periode'],
            'kanwil' => $metadata['kanwil'],
            'kode_cabang' => $metadata['kode_cabang'],
            'kode_unit' => $metadata['kode_unit'],
            'source_section' => $sectionHeader,
            'source_row_number' => $lineNumber,
            'segmen' => $segmen,
            'keterangan' => $keterangan,
        ];

        foreach (array_keys(self::METRIC_COLUMNS) as $position => $column) {
            $index = $metricOffset + $position;
            $record[$column] = str_ends_with($column, '_deb')
                ? $this->normalizeInteger($row[$index] ?? null)
                : $this->normalizeDecimal($row[$index] ?? null);
        }

        return $record;
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
     * @param array<string, mixed> $metadata
     * @param array<int, string|null> $row
     */
    private function makeUniqueId(array $metadata, string $sectionHeader, int $lineNumber, array $row): string
    {
        $fingerprint = implode('|', [
            $metadata['periode'] ?? '',
            $metadata['kanwil'] ?? '',
            $metadata['kode_cabang'] ?? '',
            $metadata['kode_unit'] ?? '',
            $sectionHeader,
            $lineNumber,
            $row[0] ?? '',
            $row[1] ?? '',
        ]);

        return 'DLYKAP_' . sha1($fingerprint);
    }
}
