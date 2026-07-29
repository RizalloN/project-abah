<?php

namespace App\Services\Presentation;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;
use Throwable;

class PresentationPrognosaWeeklyService
{
    private const SPREADSHEET_ID = '1HpvFkAVzSYhAdIeDr1uZ9XIhBhuCMbpUv0Lm8qrq0oY';

    private const CACHE_KEY = 'presentation:prognosa_weekly:v1';

    private const STABLE_CACHE_KEY = 'presentation:prognosa_weekly:stable:v1';

    /** @var array<string, string> */
    private const SHEETS = [
        'area6' => 'area',
        'KC MADIUN' => 'madiun',
        'KC MAGETAN' => 'magetan',
        'KC NGAWI' => 'ngawi',
        'KC PONOROGO' => 'ponorogo',
    ];

    /** @return array<string, mixed> */
    public function payload(bool $forceFresh = false): array
    {
        if (!$forceFresh) {
            $cached = Cache::get(self::CACHE_KEY);
            if (is_array($cached)) {
                return $cached;
            }
        }

        try {
            $payload = $this->downloadAndParse();
            Cache::put(self::CACHE_KEY, $payload, now()->addMinutes(10));
            Cache::put(self::STABLE_CACHE_KEY, $payload, now()->addDays(3));

            return $payload;
        } catch (Throwable $exception) {
            $stable = Cache::get(self::STABLE_CACHE_KEY);
            if (is_array($stable)) {
                data_set($stable, 'meta.stale', true);
                data_set($stable, 'meta.refresh_error', $exception->getMessage());

                return $stable;
            }

            $local = $this->localFallbackPayload($exception);
            if ($local !== null) {
                Cache::put(self::CACHE_KEY, $local, now()->addMinutes(5));
                Cache::put(self::STABLE_CACHE_KEY, $local, now()->addDays(3));

                return $local;
            }

            return $this->emptyPayload($exception->getMessage());
        }
    }

    /** @return array<string, mixed> */
    public function parseWorkbook(string $path): array
    {
        if (!is_file($path)) {
            throw new RuntimeException('File workbook Prognosa Weekly tidak ditemukan.');
        }

        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(false);
        $workbook = $reader->load($path);

        try {
            return $this->parseSpreadsheet($workbook);
        } finally {
            $workbook->disconnectWorksheets();
            unset($workbook);
        }
    }

    /** @return array<string, mixed> */
    private function downloadAndParse(): array
    {
        $response = Http::connectTimeout(5)
            ->timeout(20)
            ->retry(2, 300)
            ->get(sprintf(
                'https://docs.google.com/spreadsheets/d/%s/export?format=xlsx',
                self::SPREADSHEET_ID
            ));

        if (!$response->successful() || $response->body() === '') {
            throw new RuntimeException(
                'Workbook Prognosa Weekly gagal dimuat (status ' . $response->status() . ').'
            );
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'prognosa_weekly_');
        if ($temporaryPath === false) {
            throw new RuntimeException('File sementara Prognosa Weekly tidak dapat dibuat.');
        }

        try {
            if (file_put_contents($temporaryPath, $response->body()) === false) {
                throw new RuntimeException('Workbook Prognosa Weekly tidak dapat disimpan sementara.');
            }

            $payload = $this->parseWorkbook($temporaryPath);
            $this->persistLocalFallback($temporaryPath);

            return $payload;
        } finally {
            @unlink($temporaryPath);
        }
    }

    private function localFallbackPath(): string
    {
        return (string) config(
            'services.presentation_prognosa.local_path',
            storage_path('app/private/presentation/prognosa-weekly.xlsx')
        );
    }

    private function persistLocalFallback(string $sourcePath): void
    {
        $targetPath = $this->localFallbackPath();

        try {
            File::ensureDirectoryExists(dirname($targetPath));
            if (!@copy($sourcePath, $targetPath)) {
                report(new RuntimeException('Fallback lokal Prognosa Weekly tidak dapat diperbarui.'));
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /** @return array<string, mixed>|null */
    private function localFallbackPayload(Throwable $refreshException): ?array
    {
        $path = $this->localFallbackPath();
        if (!is_file($path)) {
            return null;
        }

        try {
            $payload = $this->parseWorkbook($path);
            data_set($payload, 'meta.stale', true);
            data_set($payload, 'meta.fallback', 'local-workbook');
            data_set($payload, 'meta.local_updated_at', Carbon::createFromTimestamp(filemtime($path))->toDateTimeString());
            data_set($payload, 'meta.refresh_error', $refreshException->getMessage());

            return $payload;
        } catch (Throwable $fallbackException) {
            report($fallbackException);

            return null;
        }
    }

    /** @return array<string, mixed> */
    private function parseSpreadsheet(Spreadsheet $workbook): array
    {
        $scopes = [];
        $meta = null;

        foreach (self::SHEETS as $scopeKey => $sheetName) {
            $sheet = $this->worksheet($workbook, $sheetName);
            if (!$sheet) {
                continue;
            }

            $columns = $this->resolvePositionColumns($sheet);
            if (!$columns) {
                continue;
            }

            $scopePayload = $this->parseScope($sheet, $columns);
            $scopes[$scopeKey] = $scopePayload;
            $meta ??= $this->metadata($columns);
        }

        if ($scopes === [] || !$meta) {
            throw new RuntimeException('Struktur workbook Prognosa Weekly belum dapat dikenali.');
        }

        return [
            'meta' => array_merge($meta, [
                'available' => true,
                'stale' => false,
                'source' => 'Prognosa > Prognosa Weekly',
                'source_url' => sprintf(
                    'https://docs.google.com/spreadsheets/d/%s/edit',
                    self::SPREADSHEET_ID
                ),
                'fetched_at' => now()->toDateTimeString(),
            ]),
            'scopes' => $scopes,
        ];
    }

    private function worksheet(Spreadsheet $workbook, string $name): ?Worksheet
    {
        foreach ($workbook->getWorksheetIterator() as $sheet) {
            if (strtolower(trim($sheet->getTitle())) === strtolower(trim($name))) {
                return $sheet;
            }
        }

        return null;
    }

    /**
     * @return array{
     *     header_row: int,
     *     actual_column: int,
     *     actual_date: Carbon,
     *     forecast_column: int,
     *     forecast_date: Carbon
     * }|null
     */
    private function resolvePositionColumns(Worksheet $sheet): ?array
    {
        $highestColumn = Coordinate::columnIndexFromString($sheet->getHighestColumn());
        $forecastCandidates = [];

        for ($row = 1; $row <= min(15, $sheet->getHighestRow()); $row++) {
            for ($column = 1; $column <= $highestColumn; $column++) {
                $label = $this->normaliseLabel($this->formattedCell($sheet, $column, $row));
                if (!str_contains($label, 'UPDATE POSISI')) {
                    continue;
                }

                $date = $this->dateFromCell($sheet, $column, $row);
                if ($date) {
                    $forecastCandidates[] = compact('row', 'column', 'date');
                }
            }
        }

        if ($forecastCandidates === []) {
            return null;
        }

        usort(
            $forecastCandidates,
            static fn (array $left, array $right): int => $left['date']->timestamp <=> $right['date']->timestamp
        );
        $forecast = end($forecastCandidates);
        $actualCandidates = [];

        for ($column = 2; $column <= $highestColumn; $column++) {
            if ($column === (int) $forecast['column']) {
                continue;
            }

            $date = $this->dateFromCell($sheet, $column, (int) $forecast['row']);
            if ($date && $date->lessThanOrEqualTo($forecast['date'])) {
                $actualCandidates[] = compact('column', 'date');
            }
        }

        if ($actualCandidates === []) {
            return null;
        }

        usort(
            $actualCandidates,
            static fn (array $left, array $right): int => $left['date']->timestamp <=> $right['date']->timestamp
        );
        $actual = end($actualCandidates);

        return [
            'header_row' => (int) $forecast['row'],
            'actual_column' => (int) $actual['column'],
            'actual_date' => $actual['date']->copy(),
            'forecast_column' => (int) $forecast['column'],
            'forecast_date' => $forecast['date']->copy(),
        ];
    }

    /** @param array<string, mixed> $columns */
    private function parseScope(Worksheet $sheet, array $columns): array
    {
        $rows = $this->metricRows($sheet);
        $metrics = [];

        foreach ($rows as $metric => $metricRows) {
            $forecast = $this->sumRows($sheet, $metricRows, (int) $columns['forecast_column']);
            $sourceActual = $this->sumRows($sheet, $metricRows, (int) $columns['actual_column']);
            $metrics[$metric] = [
                'available' => $forecast !== null,
                'value' => $forecast === null ? null : $forecast * 1_000_000,
                'source_actual' => $sourceActual === null ? null : $sourceActual * 1_000_000,
            ];
        }

        return [
            'available' => collect($metrics)->contains(
                static fn (array $metric): bool => (bool) ($metric['available'] ?? false)
            ),
            'metrics' => $metrics,
        ];
    }

    /** @return array<string, array<int, int>> */
    private function metricRows(Worksheet $sheet): array
    {
        $highestRow = $sheet->getHighestRow();
        $fundingStart = $this->findRow($sheet, '1. Simpanan', 1, $highestRow);
        $osStart = $this->findRow($sheet, 'Total OS Non Commercial', 1, $highestRow);
        $smlStart = $this->findRow($sheet, 'Total SML (ABS) Non Commercial', 1, $highestRow);
        $nplStart = $this->findRow($sheet, 'Total NPL (ABS) Non Commercial', 1, $highestRow);

        if (!$fundingStart || !$osStart || !$smlStart || !$nplStart) {
            throw new RuntimeException('Blok Funding/OS/SML/NPL pada Prognosa Weekly tidak lengkap.');
        }

        $fundingEnd = $osStart - 1;
        $osEnd = $smlStart - 1;
        $smlEnd = $nplStart - 1;
        $nplEnd = ($this->findRow($sheet, '5. %CASA', $nplStart, $highestRow) ?: ($highestRow + 1)) - 1;

        $rows = [
            'simpanan' => [$fundingStart],
            'funding_retail' => [$this->requiredRow($sheet, 'A. Ritel', $fundingStart, $fundingEnd)],
            'funding_micro' => [$this->requiredRow($sheet, 'B. Mikro', $fundingStart, $fundingEnd)],
            'funding_wholesale' => [$this->requiredRow($sheet, 'C. Wholesale', $fundingStart, $fundingEnd)],
            'giro' => $this->findRows($sheet, 'Giro', $fundingStart, $fundingEnd),
            'tabungan' => $this->findRows($sheet, 'Tabungan', $fundingStart, $fundingEnd),
            'deposito' => $this->findRows($sheet, 'Deposito', $fundingStart, $fundingEnd),
            'os' => [$osStart],
            'sml' => [$smlStart],
            'npl' => [$nplStart],
        ];

        $sectionDefinitions = [
            'os' => [$osStart, $osEnd],
            'sml' => [$smlStart, $smlEnd],
            'npl' => [$nplStart, $nplEnd],
        ];
        $creditDefinitions = [
            'sme' => 'B. SME',
            'consumer' => 'C. Konsumer',
            'micro' => 'D. Mikro',
            'sme_non_cashcoll' => 'Kecil Non Cashcoll',
            'sme_cashcoll' => 'Cashcoll',
            'consumer_briguna' => 'Briguna',
            'consumer_kpr' => 'KPR',
            'micro_briguna' => 'Briguna Mikro',
            'micro_kupedes' => 'Kupedes',
            'micro_kur_mikro' => 'KUR Mikro',
            'micro_kur_kecil' => 'KUR Kecil',
            'micro_kpp' => 'KUR KPP',
        ];

        foreach ($sectionDefinitions as $suffix => [$start, $end]) {
            foreach ($creditDefinitions as $metric => $label) {
                $rows["{$metric}_{$suffix}"] = [
                    $this->requiredRow($sheet, $label, $start, $end),
                ];
            }
        }

        return $rows;
    }

    private function requiredRow(Worksheet $sheet, string $label, int $start, int $end): int
    {
        $row = $this->findRow($sheet, $label, $start, $end);
        if (!$row) {
            throw new RuntimeException("Baris Prognosa Weekly '{$label}' tidak ditemukan.");
        }

        return $row;
    }

    private function findRow(Worksheet $sheet, string $label, int $start, int $end): ?int
    {
        return $this->findRows($sheet, $label, $start, $end)[0] ?? null;
    }

    /** @return array<int, int> */
    private function findRows(Worksheet $sheet, string $label, int $start, int $end): array
    {
        $needle = $this->normaliseLabel($label);
        $rows = [];

        for ($row = max(1, $start); $row <= min($end, $sheet->getHighestRow()); $row++) {
            if ($this->normaliseLabel($this->formattedCell($sheet, 1, $row)) === $needle) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /** @param array<int, int> $rows */
    private function sumRows(Worksheet $sheet, array $rows, int $column): ?float
    {
        $sum = 0.0;
        $hasValue = false;

        foreach ($rows as $row) {
            if ($row <= 0) {
                continue;
            }

            $value = $this->numericCell($sheet, $column, $row);
            if ($value === null) {
                continue;
            }

            $hasValue = true;
            $sum += $value;
        }

        return $hasValue ? $sum : null;
    }

    private function numericCell(Worksheet $sheet, int $column, int $row): ?float
    {
        $cell = $sheet->getCell(Coordinate::stringFromColumnIndex($column) . $row);

        try {
            $value = $cell->getCalculatedValue();
        } catch (Throwable) {
            $value = $cell->getOldCalculatedValue();
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $formatted = trim((string) $cell->getFormattedValue());
        if ($formatted === '' || $formatted === '-') {
            return null;
        }

        $normalised = str_replace([',', ' ', 'Rp'], '', $formatted);

        return is_numeric($normalised) ? (float) $normalised : null;
    }

    /**
     * @param array{actual_date: Carbon, forecast_date: Carbon} $columns
     * @return array<string, mixed>
     */
    private function metadata(array $columns): array
    {
        /** @var Carbon $forecastDate */
        $forecastDate = $columns['forecast_date'];
        /** @var Carbon $actualDate */
        $actualDate = $columns['actual_date'];
        $week = $this->weekOfMonth($forecastDate);

        return [
            'forecast_date' => $forecastDate->toDateString(),
            'forecast_date_label' => $forecastDate->translatedFormat('d M y'),
            'position_date' => $actualDate->toDateString(),
            'position_date_label' => $actualDate->translatedFormat('d M y'),
            'week_number' => $week,
            'week_label' => 'W' . $week,
            'label' => 'Prognosa W' . $week,
        ];
    }

    private function weekOfMonth(Carbon $date): int
    {
        $firstMonday = $date->copy()->startOfMonth();
        if ($firstMonday->dayOfWeek !== Carbon::MONDAY) {
            $firstMonday->next(Carbon::MONDAY);
        }
        if ($date->lessThan($firstMonday)) {
            return 1;
        }

        return 2 + intdiv((int) $firstMonday->diffInDays($date), 7);
    }

    private function dateFromCell(Worksheet $sheet, int $column, int $row): ?Carbon
    {
        $cell = $sheet->getCell(Coordinate::stringFromColumnIndex($column) . $row);
        $raw = $cell->getValue();
        if (is_numeric($raw) && ExcelDate::isDateTime($cell)) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $raw))->startOfDay();
        }

        $value = Str::upper(Str::ascii($this->formattedCell($sheet, $column, $row)));
        $months = [
            'JANUARI' => 1,
            'JANUARY' => 1,
            'JAN' => 1,
            'FEBRUARI' => 2,
            'FEBRUARY' => 2,
            'FEB' => 2,
            'MARET' => 3,
            'MARCH' => 3,
            'MAR' => 3,
            'APRIL' => 4,
            'APR' => 4,
            'MEI' => 5,
            'MAY' => 5,
            'JUNI' => 6,
            'JUNE' => 6,
            'JUN' => 6,
            'JULI' => 7,
            'JULY' => 7,
            'JUL' => 7,
            'AGUSTUS' => 8,
            'AUGUST' => 8,
            'AUG' => 8,
            'SEPTEMBER' => 9,
            'SEP' => 9,
            'OKTOBER' => 10,
            'OCTOBER' => 10,
            'OCT' => 10,
            'NOVEMBER' => 11,
            'NOV' => 11,
            'DESEMBER' => 12,
            'DECEMBER' => 12,
            'DEC' => 12,
        ];
        $monthPattern = implode('|', array_map('preg_quote', array_keys($months)));

        if (!preg_match('/\b(\d{1,2})\s+(' . $monthPattern . ')\s+(\d{2,4})\b/', $value, $match)) {
            return null;
        }

        $year = (int) $match[3];
        if ($year < 100) {
            $year += 2000;
        }

        try {
            return Carbon::create($year, $months[$match[2]], (int) $match[1])->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }

    private function formattedCell(Worksheet $sheet, int $column, int $row): string
    {
        return trim((string) $sheet->getCell(
            Coordinate::stringFromColumnIndex($column) . $row
        )->getFormattedValue());
    }

    private function normaliseLabel(string $value): string
    {
        $value = Str::upper(Str::ascii(trim($value)));

        return trim((string) preg_replace('/[^A-Z0-9%]+/', ' ', $value));
    }

    /** @return array<string, mixed> */
    private function emptyPayload(string $message): array
    {
        return [
            'meta' => [
                'available' => false,
                'stale' => false,
                'source' => 'Prognosa > Prognosa Weekly',
                'error' => $message,
            ],
            'scopes' => [],
        ];
    }
}
