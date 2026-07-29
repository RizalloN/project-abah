<?php

namespace App\Http\Controllers;

use App\Support\UserBranchScope;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PrognosaWeeklyController extends Controller
{
    private const SPREADSHEET_ID = '1HpvFkAVzSYhAdIeDr1uZ9XIhBhuCMbpUv0Lm8qrq0oY';
    private const SPREADSHEET_URL = 'https://docs.google.com/spreadsheets/d/1HpvFkAVzSYhAdIeDr1uZ9XIhBhuCMbpUv0Lm8qrq0oY/edit?usp=sharing';

    /** @var array<string, array{label: string, sheet: string}> */
    private const SHEETS = [
        'area' => ['label' => 'Area 6', 'sheet' => 'Area'],
        'madiun' => ['label' => 'KC Madiun', 'sheet' => 'Madiun'],
        'magetan' => ['label' => 'KC Magetan', 'sheet' => 'Magetan'],
        'ponorogo' => ['label' => 'KC Ponorogo', 'sheet' => 'Ponorogo'],
        'ngawi' => ['label' => 'KC Ngawi', 'sheet' => 'Ngawi'],
    ];

    public function index(Request $request, ?string $sheet = null): View
    {
        [$sheetOptions, $selectedSheetKey, $isLocked] = $this->resolveSheetSelection(
            $sheet ?: $request->input('sheet')
        );

        if ($request->boolean('refresh')) {
            Cache::forget($this->cacheKey($selectedSheetKey));
        }

        $payload = Cache::get($this->cacheKey($selectedSheetKey));
        if (!is_array($payload)) {
            $payload = $this->fetchSheet($selectedSheetKey);
            if (empty($payload['error'])) {
                Cache::put($this->cacheKey($selectedSheetKey), $payload, now()->addMinutes(10));
            }
        }

        return view('report.prognosa-weekly', [
            'sheetOptions' => $sheetOptions,
            'selectedSheetKey' => $selectedSheetKey,
            'selectedSheet' => self::SHEETS[$selectedSheetKey],
            'isLocked' => $isLocked,
            'spreadsheetUrl' => self::SPREADSHEET_URL,
            'headerGroups' => $payload['header_groups'] ?? [],
            'headerColumns' => $payload['header_columns'] ?? [],
            'rows' => $payload['rows'] ?? [],
            'title' => $payload['title'] ?? 'Weekly Prognosa',
            'latestDate' => $payload['latest_date'] ?? null,
            'fetchedAt' => $payload['fetched_at'] ?? null,
            'error' => $payload['error'] ?? null,
        ]);
    }

    /**
     * @return array{0: array<string, array{label: string, sheet: string}>, 1: string, 2: bool}
     */
    private function resolveSheetSelection(mixed $requestedSheet): array
    {
        $scope = UserBranchScope::current();
        if ($scope !== null && isset(self::SHEETS[$scope['key']])) {
            return [
                [$scope['key'] => self::SHEETS[$scope['key']]],
                $scope['key'],
                true,
            ];
        }

        $selectedSheetKey = strtolower(trim((string) $requestedSheet));
        if (!isset(self::SHEETS[$selectedSheetKey])) {
            $selectedSheetKey = 'area';
        }

        return [self::SHEETS, $selectedSheetKey, false];
    }

    private function fetchSheet(string $sheetKey): array
    {
        $sheet = self::SHEETS[$sheetKey];
        $workbookError = null;

        try {
            $response = Http::timeout(20)
                ->retry(2, 300)
                ->get($this->workbookUrl());

            if ($response->successful() && $response->body() !== '') {
                return $this->parseWorkbook(
                    $response->body(),
                    $sheet['label'],
                    $sheet['sheet']
                );
            }

            $workbookError = 'Google Sheet mengembalikan status ' . $response->status() . '.';
        } catch (\Throwable $exception) {
            $workbookError = $exception->getMessage();
        }

        try {
            $response = Http::timeout(20)
                ->retry(2, 300)
                ->get($this->csvUrl($sheet['sheet']));

            if (!$response->successful()) {
                return $this->emptyPayload('Google Sheet mengembalikan status ' . $response->status() . '.');
            }

            $csv = trim($response->body());
            if ($csv === '' || str_contains(strtolower(substr($csv, 0, 300)), '<html')) {
                return $this->emptyPayload('Sheet tidak dapat dibaca. Pastikan akses spreadsheet terbuka untuk viewer.');
            }

            return $this->parseCsv($csv, $sheet['label']);
        } catch (\Throwable $exception) {
            return $this->emptyPayload(
                'Sheet tidak dapat dimuat: ' . ($workbookError ?: $exception->getMessage())
            );
        }
    }

    private function workbookUrl(): string
    {
        return sprintf(
            'https://docs.google.com/spreadsheets/d/%s/export?format=xlsx',
            self::SPREADSHEET_ID
        );
    }

    private function csvUrl(string $sheetName): string
    {
        return sprintf(
            'https://docs.google.com/spreadsheets/d/%s/gviz/tq?%s',
            self::SPREADSHEET_ID,
            http_build_query(['tqx' => 'out:csv', 'sheet' => $sheetName])
        );
    }

    private function cacheKey(string $sheetKey): string
    {
        return 'prognosa_weekly:spreadsheet:v2:xlsx_headers:' . $sheetKey;
    }

    private function parseWorkbook(string $contents, string $fallbackTitle, string $sheetName): array
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'prognosa_weekly_');
        if ($temporaryPath === false) {
            throw new \RuntimeException('Workbook sementara tidak dapat dibuat.');
        }

        $workbookPath = $temporaryPath . '.xlsx';
        @unlink($temporaryPath);

        try {
            if (file_put_contents($workbookPath, $contents) === false) {
                throw new \RuntimeException('Workbook sementara tidak dapat ditulis.');
            }

            $workbook = IOFactory::load($workbookPath);
            try {
                $sheet = $this->worksheetByName($workbook, $sheetName);
                if ($sheet === null) {
                    throw new \RuntimeException("Sheet '{$sheetName}' tidak ditemukan di workbook.");
                }

                return $this->parseWorksheet($sheet, $fallbackTitle);
            } finally {
                $workbook->disconnectWorksheets();
            }
        } finally {
            @unlink($workbookPath);
        }
    }

    private function worksheetByName(Spreadsheet $workbook, string $name): ?Worksheet
    {
        foreach ($workbook->getWorksheetIterator() as $sheet) {
            if (strtolower(trim($sheet->getTitle())) === strtolower(trim($name))) {
                return $sheet;
            }
        }

        return null;
    }

    private function parseWorksheet(Worksheet $sheet, string $fallbackTitle): array
    {
        $headerGroupRow = $this->findWorksheetHeaderRow($sheet);
        if ($headerGroupRow === null) {
            throw new \RuntimeException('Baris KETERANGAN tidak ditemukan pada workbook.');
        }

        $headerColumnRow = $headerGroupRow + 1;
        $columnCount = $this->worksheetColumnCount($sheet, $headerGroupRow);
        if ($columnCount < 2) {
            throw new \RuntimeException('Workbook tidak memiliki kolom laporan.');
        }

        $sourceHeader = [];
        for ($column = 1; $column <= $columnCount; $column++) {
            $sourceHeader[] = $this->worksheetCell($sheet, $column, $headerColumnRow);
        }

        $rows = [];
        for ($row = $headerColumnRow + 1; $row <= $sheet->getHighestRow(); $row++) {
            $values = [];
            for ($column = 1; $column <= $columnCount; $column++) {
                $values[] = $this->worksheetCell($sheet, $column, $row);
            }

            if (collect($values)->contains(static fn (string $value): bool => $value !== '')) {
                $rows[] = $values;
            }
        }

        $headerColumns = $this->headerColumns($sourceHeader, $columnCount);
        $latestDate = $this->worksheetCell($sheet, 2, 3);
        $latestPositionDate = $headerColumns[8]['label'] ?? '';
        if (preg_match('/^\d{2}\s+[[:alpha:]]{3}\s+\d{2}$/u', $latestPositionDate) === 1) {
            $latestDate = $latestPositionDate;
        }

        return [
            'title' => trim(implode(' - ', array_filter([
                $this->worksheetCell($sheet, 1, 1),
                $this->worksheetCell($sheet, 2, 2),
            ]))) ?: $fallbackTitle,
            'latest_date' => $latestDate !== '' ? $this->formatDateLabel($latestDate) : null,
            'header_groups' => $this->headerGroups($columnCount),
            'header_columns' => $headerColumns,
            'rows' => $rows,
            'fetched_at' => now()->toDateTimeString(),
            'error' => null,
        ];
    }

    private function findWorksheetHeaderRow(Worksheet $sheet): ?int
    {
        for ($row = 1; $row <= min(20, $sheet->getHighestRow()); $row++) {
            if (strtoupper($this->worksheetCell($sheet, 1, $row)) === 'KETERANGAN') {
                return $row;
            }
        }

        return null;
    }

    private function worksheetColumnCount(Worksheet $sheet, int $headerGroupRow): int
    {
        $highestColumn = Coordinate::columnIndexFromString($sheet->getHighestColumn());
        $highestRow = $sheet->getHighestRow();

        for ($column = $highestColumn; $column >= 1; $column--) {
            for ($row = $headerGroupRow; $row <= $highestRow; $row++) {
                if ($this->worksheetCell($sheet, $column, $row) !== '') {
                    return $column;
                }
            }
        }

        return 0;
    }

    private function worksheetCell(Worksheet $sheet, int $column, int $row): string
    {
        return trim((string) $sheet->getCell(
            Coordinate::stringFromColumnIndex($column) . $row
        )->getFormattedValue());
    }

    private function parseCsv(string $csv, string $fallbackTitle): array
    {
        $rows = [];
        foreach (preg_split('/\r\n|\n|\r/', $csv) ?: [] as $line) {
            $row = array_map(static fn ($value): string => trim((string) $value), str_getcsv($line));
            if (collect($row)->contains(static fn (string $value): bool => $value !== '')) {
                $rows[] = $row;
            }
        }

        if (count($rows) < 6) {
            return $this->emptyPayload('Struktur sheet belum memiliki tabel data.');
        }

        $columnCount = 0;
        foreach ($rows as $row) {
            foreach ($row as $index => $value) {
                if ($value !== '') {
                    $columnCount = max($columnCount, $index + 1);
                }
            }
        }

        if ($columnCount === 0) {
            return $this->emptyPayload('Sheet tidak memiliki kolom data.');
        }

        $rows = array_map(
            static fn (array $row): array => array_slice(array_pad($row, $columnCount, ''), 0, $columnCount),
            $rows
        );
        $headerColumns = $this->headerColumns($rows[4], $columnCount);

        return [
            'title' => trim(implode(' - ', array_filter([$rows[0][0] ?? '', $rows[0][1] ?? '']))) ?: $fallbackTitle,
            'latest_date' => $this->formatDateLabel(trim((string) ($rows[1][1] ?? ''))) ?: null,
            'header_groups' => $this->headerGroups($columnCount),
            'header_columns' => $headerColumns,
            'rows' => array_values(array_filter(
                array_slice($rows, 5),
                static fn (array $row): bool => collect($row)->contains(static fn (string $value): bool => $value !== '')
            )),
            'fetched_at' => now()->toDateTimeString(),
            'error' => null,
        ];
    }

    /** @return array<int, array{label: string, key: string, colspan: int, rowspan: int, start: int}> */
    private function headerGroups(int $columnCount): array
    {
        $groups = [[
            'label' => 'Keterangan',
            'key' => 'indicator',
            'colspan' => 1,
            'rowspan' => 2,
            'start' => 0,
        ]];
        $ranges = [
            ['label' => 'Posisi', 'key' => 'position', 'start' => 1, 'end' => 8],
            ['label' => 'Prognosa', 'key' => 'prognosa', 'start' => 9, 'end' => 9],
            ['label' => 'Delta', 'key' => 'delta', 'start' => 10, 'end' => 14],
            ['label' => 'RKA', 'key' => 'rka', 'start' => 15, 'end' => 17],
            ['label' => 'RKA', 'key' => 'rka', 'start' => 18, 'end' => $columnCount - 1],
        ];

        foreach ($ranges as $range) {
            $start = (int) $range['start'];
            $end = min((int) $range['end'], $columnCount - 1);
            if ($start > $end) {
                continue;
            }

            $groups[] = [
                'label' => $range['label'],
                'key' => $range['key'],
                'colspan' => $end - $start + 1,
                'rowspan' => 1,
                'start' => $start,
            ];
        }

        return $groups;
    }

    /** @return array<int, array{index: int, label: string}> */
    private function headerColumns(array $sourceHeader, int $columnCount): array
    {
        $columns = [];
        $positionDates = $this->positionDateLabels($sourceHeader);
        for ($index = 0; $index < $columnCount; $index++) {
            $sourceLabel = trim((string) ($sourceHeader[$index] ?? ''));
            $columns[] = [
                'index' => $index,
                'label' => $sourceLabel !== ''
                    ? $this->formatHeaderLabel($sourceLabel, $index, $positionDates, $sourceHeader)
                    : $this->fallbackColumnLabel($index),
            ];
        }

        return $columns;
    }

    /** @param array<int, string> $sourceHeader
     * @return array<int, string>
     */
    private function positionDateLabels(array $sourceHeader): array
    {
        $labels = [];
        for ($index = 1; $index <= 8; $index++) {
            $labels[$index] = $this->formatDateLabel((string) ($sourceHeader[$index] ?? ''));
        }

        return $labels;
    }

    /**
     * @param array<int, string> $positionDates
     * @param array<int, string> $sourceHeader
     */
    private function formatHeaderLabel(
        string $sourceLabel,
        int $index,
        array $positionDates,
        array $sourceHeader
    ): string
    {
        if ($index >= 1 && $index <= 8) {
            return $this->formatDateLabel($sourceLabel);
        }

        if ($index === 9) {
            return $this->formatMonthEndLabel($sourceLabel) ?: $sourceLabel;
        }

        $deltaReferences = [10 => 1, 11 => 2, 12 => 4, 13 => 5, 14 => 7];
        if (isset($deltaReferences[$index])) {
            $reference = $positionDates[$deltaReferences[$index]] ?? '';

            return $reference !== '' ? 'Δ ' . $reference : $sourceLabel;
        }

        if ($index >= 15 && $index <= 20) {
            $rkaReferenceIndex = $index <= 17 ? 15 : 18;
            $rkaDate = $this->formatMonthEndLabel(
                (string) ($sourceHeader[$rkaReferenceIndex] ?? '')
            );

            if ($rkaDate === '') {
                return $sourceLabel;
            }

            return match ($index % 3) {
                0 => $rkaDate,
                1 => 'Δ ' . $rkaDate,
                default => '% ' . $rkaDate,
            };
        }

        return $sourceLabel;
    }

    private function formatDateLabel(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (preg_match('/(\d{1,2})\s+([[:alpha:]]+)\s+(\d{2,4})/iu', $value, $matches) !== 1) {
            return $value;
        }

        try {
            return Carbon::parse($this->normaliseMonthName(
                $matches[1] . ' ' . $matches[2] . ' ' . $matches[3]
            ))
                ->translatedFormat('d M y');
        } catch (\Throwable) {
            return $value;
        }
    }

    private function formatMonthEndLabel(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (preg_match('/([[:alpha:]]+)\s+(\d{2,4})/iu', $value, $matches) !== 1) {
            return $this->formatDateLabel($value);
        }

        try {
            return Carbon::parse($this->normaliseMonthName('1 ' . $matches[1] . ' ' . $matches[2]))
                ->endOfMonth()
                ->translatedFormat('d M y');
        } catch (\Throwable) {
            return '';
        }
    }

    private function normaliseMonthName(string $value): string
    {
        return str_ireplace(
            [
                'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember', 'Des',
            ],
            [
                'January', 'February', 'March', 'April', 'May', 'June',
                'July', 'August', 'September', 'October', 'November', 'December', 'Dec',
            ],
            $value
        );
    }

    private function fallbackColumnLabel(int $index): string
    {
        if ($index === 0) {
            return 'Keterangan';
        }
        if ($index <= 8) {
            return 'Posisi';
        }
        if ($index === 9) {
            return 'Prognosa';
        }
        if ($index <= 14) {
            return 'Delta';
        }

        return match (($index - 15) % 3) {
            0 => 'RKA',
            1 => 'Delta RKA',
            default => 'Pencapaian',
        };
    }

    private function emptyPayload(string $message): array
    {
        return [
            'title' => 'Weekly Prognosa',
            'latest_date' => null,
            'header_groups' => [],
            'header_columns' => [],
            'rows' => [],
            'fetched_at' => now()->toDateTimeString(),
            'error' => $message,
        ];
    }
}
