<?php

namespace App\Services\Import;

use App\Support\StrictDateParser;
use App\Support\UserBranchScope;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use RuntimeException;

class SuspectReaktivasiSourceService
{
    public const TABLE = 'suspect_reaktivasi_pinjaman';

    private const SOURCE = [
        1 => 'kode_kanwil', 4 => 'kanwil', 5 => 'kode_cabang', 6 => 'cabang',
        7 => 'kode_uker', 8 => 'nama_uker', 9 => 'norek', 10 => 'cifno',
        11 => 'nama_debitur', 12 => 'fksegmen', 13 => 'deskripsi', 14 => 'curtyp',
        15 => 'outstanding', 16 => 'bulan_laporan_11', 17 => 'bulan_laporan_10',
        18 => 'bulan_laporan_9', 19 => 'bulan_laporan_8', 20 => 'bulan_laporan_7',
        21 => 'bulan_laporan_6', 22 => 'bulan_laporan_5', 23 => 'bulan_laporan_4',
        25 => 'bulan_laporan_3', 26 => 'bulan_laporan_2', 27 => 'bulan_laporan_1',
        28 => 'bulan_laporan', 29 => 'keterangan', 30 => 'flag_restruk', 32 => 'ao_pengelola',
    ];

    public static function columns(): array
    {
        return ['periode', ...array_values(self::SOURCE)];
    }

    /** Normalize the original workbook, including its merged header cells. */
    public function stage(string $source, string $destination): array
    {
        $extension = strtolower(pathinfo($source, PATHINFO_EXTENSION));
        if (! in_array($extension, ['xlsx', 'xls'], true)) {
            throw new RuntimeException('Gunakan workbook XLSX/XLS asli Laporan Suspect Reaktivasi Pinjaman.');
        }
        $reader = IOFactory::createReaderForFile($source);
        $reader->setReadDataOnly(true);
        $book = $reader->load($source);
        try {
            $sheet = $book->getActiveSheet();
            $rows = $sheet->toArray(null, false, false, false);
            $headerIndex = null;
            $period = null;
            foreach (array_slice($rows, 0, 40, true) as $index => $row) {
                $labels = array_map(static fn ($value): string => strtoupper(trim((string) $value)), $row);
                if (in_array('POSISI', $labels, true)) {
                    foreach ($row as $value) {
                        if (trim((string) $value) === '' || strtoupper(trim((string) $value)) === 'POSISI') {
                            continue;
                        }
                        $candidate = $this->date($value);
                        if ($candidate !== null) {
                            $period = $candidate;
                            break;
                        }
                    }
                }
                if (($labels[9] ?? '') === 'NOREK' && ($labels[15] ?? '') === 'OUTSTANDING'
                    && ($labels[29] ?? '') === 'KETERANGAN' && ($labels[32] ?? '') === 'AO PENGELOLA') {
                    $headerIndex = $index;
                    break;
                }
            }
            if ($headerIndex === null || $period === null) {
                throw new RuntimeException('Header atau POSISI workbook Suspect Reaktivasi tidak dikenali.');
            }
            $output = fopen($destination, 'wb');
            if ($output === false) {
                throw new RuntimeException('File normalisasi Suspect Reaktivasi tidak dapat dibuat.');
            }
            $sourceCount = $imported = 0;
            $seen = [];
            $branches = [];
            try {
                fputcsv($output, self::columns(), ',', '"', '');
                foreach (array_slice($rows, $headerIndex + 1, null, true) as $index => $row) {
                    if (count(array_filter($row, static fn ($value): bool => trim((string) $value) !== '')) === 0) {
                        continue;
                    }
                    $sourceCount++;
                    $branch = $this->branch($row[5] ?? '', $row[6] ?? '');
                    if ($branch === null) {
                        continue;
                    }
                    $record = ['periode' => $period];
                    foreach (self::SOURCE as $columnIndex => $column) {
                        $value = $row[$columnIndex] ?? null;
                        if (str_starts_with($column, 'bulan_laporan')) {
                            $value = $this->date($value);
                            if ($value === null && trim((string) ($row[$columnIndex] ?? '')) !== '') {
                                throw new RuntimeException('Tanggal '.$column.' tidak valid pada baris '.($index + 1).'.');
                            }
                        } elseif ($column === 'outstanding') {
                            if (! is_numeric($value)) {
                                throw new RuntimeException('Outstanding tidak valid pada baris '.($index + 1).'.');
                            }
                            $value = number_format((float) $value, 2, '.', '');
                        } else {
                            $value = trim((string) $value);
                        }
                        $record[$column] = $value;
                    }
                    $record['cabang'] = $branch['label'];
                    $record['kode_cabang'] = $branch['code4'];
                    foreach (['norek', 'cifno', 'nama_debitur', 'kode_uker', 'nama_uker', 'keterangan'] as $required) {
                        if ($record[$required] === '') {
                            throw new RuntimeException('Kolom '.$required.' kosong pada baris '.($index + 1).'.');
                        }
                    }
                    if (isset($seen[$record['norek']])) {
                        throw new RuntimeException('Rekening ganda pada baris '.($index + 1).': '.$record['norek']);
                    }
                    $seen[$record['norek']] = true;
                    fputcsv($output, array_values($record), ',', '"', '');
                    $branches[$record['cabang']] = ($branches[$record['cabang']] ?? 0) + 1;
                    $imported++;
                }
                if ($imported === 0) {
                    throw new RuntimeException('Workbook tidak memiliki rekening Suspect Reaktivasi untuk Area 6.');
                }
            } catch (\Throwable $error) {
                fclose($output);
                @unlink($destination);
                throw $error;
            }
            fclose($output);

            return ['periode' => $period, 'source_rows' => $sourceCount, 'imported_rows' => $imported, 'excluded_rows' => $sourceCount - $imported, 'branches' => $branches];
        } finally {
            $book->disconnectWorksheets();
        }
    }

    private function date(mixed $value): ?string
    {
        if (trim((string) $value) === '') {
            return null;
        }
        if (is_numeric($value) && (float) $value > 0 && (float) $value < 100000) {
            return Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }

        return StrictDateParser::normalize((string) $value);
    }

    private function branch(mixed $code, mixed $name): ?array
    {
        $code = ltrim(trim((string) $code), '0');
        foreach (UserBranchScope::branchScopes() as $branch) {
            if ($code === ltrim($branch['code4'], '0')) {
                if (strtoupper(trim((string) $name)) !== $branch['upper_label']) {
                    throw new RuntimeException('Kode dan nama kanca pada sumber Suspect Reaktivasi tidak sesuai.');
                }

                return $branch;
            }
        }

        return null;
    }
}
