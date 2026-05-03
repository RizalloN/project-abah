<?php

namespace App\Services\Import;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ReferensiUkerImporter
{
    private const TABLE = 'referensi_uker';

    /**
     * @return array{rows: array<int, array<string, mixed>>, metadata: array<string, mixed>, warnings: array<int, string>}
     */
    public function parse(string $path): array
    {
        if (! is_file($path)) {
            throw new InvalidArgumentException("File referensi tidak ditemukan: {$path}");
        }

        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getSheetByName('REFF') ?? $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestDataRow();
        $highestColumn = $sheet->getHighestDataColumn();

        if ($highestRow < 2) {
            throw new InvalidArgumentException('File referensi tidak memiliki baris data.');
        }

        $rawHeaders = $sheet->rangeToArray("A1:{$highestColumn}1", null, true, false)[0] ?? [];
        $headers = array_map(fn ($header) => $this->normalizeHeader((string) $header), $rawHeaders);
        $columnMap = $this->resolveColumnMap($headers);
        $rows = [];
        $warnings = [];
        $seenCodes = [];

        for ($rowNumber = 2; $rowNumber <= $highestRow; $rowNumber++) {
            $values = $sheet->rangeToArray("A{$rowNumber}:{$highestColumn}{$rowNumber}", null, true, false)[0] ?? [];

            if ($this->isBlankRow($values)) {
                continue;
            }

            $namaUker = $this->cleanText($values[$columnMap['nama_uker']] ?? null);
            $namaUkerFinal = $this->cleanText($values[$columnMap['nama_uker_final']] ?? null) ?: $namaUker;
            $kodeUkerSource = $this->cleanCode($values[$columnMap['kode_uker']] ?? null);
            $kodeUker = $this->extractFiveDigitCode($namaUkerFinal) ?: $this->padCode($kodeUkerSource);
            $namaCabang = $this->cleanText($values[$columnMap['nama_cabang']] ?? null);
            $kodeCabang = $this->extractFiveDigitCode($namaCabang);

            if ($kodeUker === '' || $namaUkerFinal === '') {
                $warnings[] = "Baris {$rowNumber} dilewati karena kode/nama uker kosong.";
                continue;
            }

            if (isset($seenCodes[$kodeUker])) {
                throw new InvalidArgumentException("Duplikasi kode uker {$kodeUker} pada baris {$seenCodes[$kodeUker]} dan {$rowNumber}.");
            }

            $seenCodes[$kodeUker] = $rowNumber;
            $rows[] = [
                'kode_uker' => $kodeUker,
                'nama_uker' => $namaUkerFinal,
                'keterangan' => $this->cleanText($values[$columnMap['keterangan']] ?? null),
                'kode_cabang' => $kodeCabang ?: null,
                'nama_cabang' => $namaCabang ?: null,
                'nama_uker_sumber' => $namaUker ?: null,
                'kode_uker_sumber' => $kodeUkerSource ?: null,
                'sheet_name' => $sheet->getTitle(),
                'source_row' => $rowNumber,
            ];
        }

        return [
            'rows' => $rows,
            'metadata' => [
                'sheet' => $sheet->getTitle(),
                'highest_row' => $highestRow,
                'data_rows' => count($rows),
                'headers' => $rawHeaders,
            ],
            'warnings' => $warnings,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array{inserted: int, replaced: bool}
     */
    public function importRows(array $rows, bool $replace = true): array
    {
        return DB::transaction(function () use ($rows, $replace): array {
            $now = now();
            $payload = array_map(static fn (array $row): array => array_merge($row, [
                'created_at' => $now,
                'updated_at' => $now,
            ]), $rows);

            if ($replace) {
                DB::table(self::TABLE)->delete();
            }

            foreach (array_chunk($payload, 500) as $chunk) {
                DB::table(self::TABLE)->upsert(
                    $chunk,
                    ['kode_uker'],
                    [
                        'nama_uker',
                        'keterangan',
                        'kode_cabang',
                        'nama_cabang',
                        'nama_uker_sumber',
                        'kode_uker_sumber',
                        'sheet_name',
                        'source_row',
                        'updated_at',
                    ]
                );
            }

            return [
                'inserted' => count($payload),
                'replaced' => $replace,
            ];
        });
    }

    /**
     * @param array<int, string> $headers
     * @return array{nama_uker: int, keterangan: int, kode_uker: int, nama_cabang: int, nama_uker_final: int}
     */
    private function resolveColumnMap(array $headers): array
    {
        $expected = ['nama_uker', 'keterangan', 'kode_uker', 'nama_cabang', 'nama_uker'];

        foreach ($expected as $index => $header) {
            if (($headers[$index] ?? null) !== $header) {
                throw new InvalidArgumentException('Header file referensi tidak sesuai. Format wajib: Nama Uker, Keterangan, KODE UKER, Nama Cabang, Nama Uker.');
            }
        }

        return [
            'nama_uker' => 0,
            'keterangan' => 1,
            'kode_uker' => 2,
            'nama_cabang' => 3,
            'nama_uker_final' => 4,
        ];
    }

    private function normalizeHeader(string $value): string
    {
        return Str::of($value)->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString();
    }

    /**
     * @param array<int, mixed> $values
     */
    private function isBlankRow(array $values): bool
    {
        foreach ($values as $value) {
            if ($this->cleanText($value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function cleanText(mixed $value): string
    {
        return trim(preg_replace('/\s+/', ' ', (string) ($value ?? '')) ?: '');
    }

    private function cleanCode(mixed $value): string
    {
        $text = $this->cleanText($value);

        if ($text === '') {
            return '';
        }

        if (is_numeric($text)) {
            return (string) (int) $text;
        }

        return preg_replace('/\D+/', '', $text) ?: '';
    }

    private function extractFiveDigitCode(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (preg_match('/\b(\d{5})\b/', $value, $matches) === 1) {
            return $matches[1];
        }

        return '';
    }

    private function padCode(string $value): string
    {
        if ($value === '') {
            return '';
        }

        return str_pad(substr($value, -5), 5, '0', STR_PAD_LEFT);
    }
}
