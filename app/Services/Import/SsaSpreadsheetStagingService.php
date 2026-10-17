<?php

namespace App\Services\Import;

use App\Services\Import\Strategies\SsaPinjamanImportStrategy;
use App\Services\Import\Strategies\SsaSimpananImportStrategy;
use App\Support\StrictDateParser;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class SsaSpreadsheetStagingService
{
    private const COLUMNS = [
        'ssa_simpanan' => [
            'month_day_year_of_posisi', 'nama_cabang', 'nama_uker', 'produk',
            'segmentasi', 'segmen_kategorisasi_bisnis', 'saldo',
        ],
        'ssa_pinjaman' => [
            'month_day_year_of_periode', 'nama_cabang', 'nama_uker', 'produk',
            'produk_dashboard', 'segmen', 'segmen_lama', 'segmen_2025',
            'segmen_dashboard', 'kolektabilitas_one_obligor', 'flag_restruk',
            'baki_debet', 'jumlah_debitur_aktif', 'jumlah_rekening_aktif',
        ],
    ];

    public function stage(string $sourcePath, string $tableName, string $outputPath): ?array
    {
        $columns = self::COLUMNS[$tableName] ?? throw new RuntimeException('Report bukan SSA Simpanan/Pinjaman.');
        $dimensionCount = $tableName === 'ssa_simpanan' ? 6 : 11;
        $strategy = $tableName === 'ssa_simpanan' ? new SsaSimpananImportStrategy : new SsaPinjamanImportStrategy;
        $reader = IOFactory::createReaderForFile($sourcePath);
        // Merge boundaries are source data: data-only mode drops this metadata.
        $reader->setReadDataOnly(false);
        $book = $reader->load($sourcePath);
        $handle = null;

        try {
            $sheet = $book->getSheet(0);
            $worksheetLastColumn = $sheet->getHighestDataColumn();
            $worksheetLastRow = $sheet->getHighestDataRow();
            // Already flat exports retain their existing import rules and aliases.
            $firstHeaders = $sheet->rangeToArray('A1:'.$worksheetLastColumn.'1', null, false, false)[0];
            if ($sheet->getMergeCells() === [] && trim((string) end($firstHeaders)) !== '') {
                return null;
            }
            $width = Coordinate::columnIndexFromString($worksheetLastColumn);
            if ($width !== count($columns)) {
                throw new RuntimeException('Jumlah kolom file SSA tidak sesuai: '.count($columns).' kolom diperlukan.');
            }
            $headers = [];
            $headerIndex = null;
            for ($row = 1; $row <= min($worksheetLastRow, 200); $row++) {
                $rawHeaders = $sheet->rangeToArray('A'.$row.':'.$worksheetLastColumn.$row, null, false, false)[0];
                $candidate = $strategy->transformHeaders($rawHeaders);
                // Only the original seven-column savings layout has an unlabeled balance.
                if ($tableName === 'ssa_simpanan'
                    && array_slice($candidate, 0, 6) === array_slice($columns, 0, 6)
                    && in_array(strtolower($candidate[6]), ['', 'col_6'], true)) {
                    $candidate[6] = 'saldo';
                }
                if (count(array_unique($candidate)) === count($columns) && array_diff($columns, $candidate) === []) {
                    $headers = $candidate;
                    $headerIndex = $row - 1;
                    break;
                }
            }
            if ($headers === []) {
                throw new RuntimeException('Header file SSA tidak lengkap atau tidak dikenali.');
            }

            $dimensionColumns = array_slice($columns, 0, $dimensionCount);
            foreach ($sheet->getMergeCells() as $range) {
                [[$firstColumn, $firstRow], [$lastColumn, $lastRow]] = Coordinate::rangeBoundaries($range);
                if ($firstRow <= $headerIndex + 1 || ! in_array($headers[$firstColumn - 1] ?? '', $dimensionColumns, true)) {
                    continue;
                }
                if ($firstColumn !== $lastColumn) {
                    throw new RuntimeException('Merge lintas kolom SSA tidak didukung: '.$range.'.');
                }
                $value = $sheet->getCell([$firstColumn, $firstRow])->getValue();
                for ($row = $firstRow + 1; $row <= $lastRow; $row++) {
                    $sheet->setCellValue([$firstColumn, $row], $value);
                }
            }

            $handle = fopen($outputPath, 'wb');
            if ($handle === false) {
                throw new RuntimeException('CSV staging SSA tidak dapat dibuat.');
            }
            fputcsv($handle, $columns, ',', '"', '');
            $writtenRows = 0;
            for ($row = $headerIndex + 2; $row <= $worksheetLastRow; $row++) {
                $values = $sheet->rangeToArray('A'.$row.':'.$worksheetLastColumn.$row, null, false, false)[0];
                if (! array_filter($values, static fn ($value): bool => $value !== null && trim((string) $value) !== '')) {
                    continue;
                }
                $mapped = array_combine($headers, $values);
                $output = [];
                foreach ($columns as $index => $column) {
                    $value = $mapped[$column];
                    if ($value === null || trim((string) $value) === '') {
                        throw new RuntimeException("Baris SSA {$row}: kolom {$column} kosong di luar merge sumber.");
                    }
                    if ($index === 0) {
                        $dateText = trim((string) $value);
                        $value = StrictDateParser::normalize($dateText);
                        $rolledOver = $value !== null
                            && preg_match('/^(\d{1,2})\s+[a-z]+\s+(\d{4})$/i', $dateText, $parts)
                            && ((int) substr($value, 8, 2) !== (int) $parts[1] || (int) substr($value, 0, 4) !== (int) $parts[2]);
                        if ($value === null || $rolledOver) {
                            throw new RuntimeException("Baris SSA {$row}: tanggal periode tidak valid.");
                        }
                    } elseif ($index >= $dimensionCount || $column === 'kolektabilitas_one_obligor') {
                        if (! is_numeric($value) || ! is_finite((float) $value)) {
                            throw new RuntimeException("Baris SSA {$row}: kolom {$column} bukan angka yang valid.");
                        }
                        $integer = ! in_array($column, ['saldo', 'baki_debet'], true);
                        if ($integer && ((float) $value < 0 || floor((float) $value) !== (float) $value)) {
                            throw new RuntimeException("Baris SSA {$row}: kolom {$column} harus bilangan bulat nonnegatif.");
                        }
                        $value = number_format((float) $value, $integer ? 0 : 2, '.', '');
                    }
                    $output[] = trim(str_replace("\u{00A0}", ' ', (string) $value));
                }
                fputcsv($handle, $output, ',', '"', '');
                $writtenRows++;
            }
            if ($writtenRows === 0) {
                throw new RuntimeException('File SSA tidak memiliki baris data.');
            }

            return ['staged_csv_path' => $outputPath, 'headers' => $columns, 'header_index' => 0, 'total_rows' => $writtenRows];
        } catch (\Throwable $e) {
            if (is_resource($handle)) {
                fclose($handle);
                $handle = null;
            }
            @unlink($outputPath);
            throw $e;
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
            $book->disconnectWorksheets();
        }
    }
}
