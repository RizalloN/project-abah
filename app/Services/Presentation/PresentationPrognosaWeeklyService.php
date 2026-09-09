<?php

namespace App\Services\Presentation;

use Carbon\Carbon;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
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
    private const SPREADSHEET_ID = '1qta-IbVG5edMAy36Ku-GCvs_sesfmmXt';

    private const CACHE_KEY = 'presentation:prognosa_weekly:v5';

    private const STABLE_CACHE_KEY = 'presentation:prognosa_weekly:stable:v5';

    /** @var array<string, array<int, string>> */
    private const SHEETS = [
        'area6' => ['Area 6', 'Report AREA', 'area'],
        'KC MADIUN' => ['KC Madiun', 'MADIUN', 'madiun'],
        'KC MAGETAN' => ['KC Magetan', 'MAGETAN', 'magetan'],
        'KC NGAWI' => ['KC Ngawi', 'NGAWI', 'ngawi'],
        'KC PONOROGO' => ['KC Ponorogo', 'PONOROGO', 'ponorogo'],
    ];

    /** @return array<string, mixed> */
    public function payload(bool $forceFresh = false): array
    {
        if (! $forceFresh) {
            $cached = Cache::get(self::CACHE_KEY);
            if (is_array($cached)) {
                return $cached;
            }
        }

        try {
            $payload = $this->downloadAndParse();
            Cache::put(self::CACHE_KEY, $payload, now()->addMinutes(10));
            Cache::put(self::STABLE_CACHE_KEY, $payload, now()->addDays(3));
            $this->persistLocalPayload($payload);

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
        if (! is_file($path)) {
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
        try {
            return $this->downloadCsvAndParse();
        } catch (Throwable $csvException) {
            try {
                return $this->downloadWorkbookAndParse();
            } catch (Throwable $workbookException) {
                throw new RuntimeException(
                    'Prognosa Weekly gagal dimuat dari CSV dan workbook. CSV: '
                    .$csvException->getMessage()
                    .' Workbook: '
                    .$workbookException->getMessage(),
                    0,
                    $workbookException
                );
            }
        }
    }

    /** @return array<string, mixed> */
    private function downloadCsvAndParse(): array
    {
        $responses = Http::pool(function (Pool $pool): array {
            $requests = [];

            foreach (self::SHEETS as $scopeKey => $sheetNames) {
                $requests[] = $pool->as($scopeKey)
                    ->connectTimeout(5)
                    ->timeout(15)
                    ->get($this->csvUrl($sheetNames[0]));
            }

            return $requests;
        });

        $csvByScope = [];
        foreach (self::SHEETS as $scopeKey => $sheetNames) {
            $response = $responses[$scopeKey] ?? null;
            if (! $response instanceof Response || ! $response->successful()) {
                $response = Http::connectTimeout(5)
                    ->timeout(15)
                    ->retry(1, 250)
                    ->get($this->csvUrl($sheetNames[0]));
            }

            if (! $response->successful() || trim($response->body()) === '') {
                throw new RuntimeException(
                    "Sheet Prognosa Weekly {$sheetNames[0]} gagal dimuat (status {$response->status()})."
                );
            }

            $csvByScope[$scopeKey] = $response->body();
        }

        return $this->parseCsvSources($csvByScope);
    }

    /** @return array<string, mixed> */
    private function downloadWorkbookAndParse(): array
    {
        $response = Http::connectTimeout(5)
            ->timeout(20)
            ->retry(2, 300)
            ->get(sprintf(
                'https://docs.google.com/spreadsheets/d/%s/export?format=xlsx',
                self::SPREADSHEET_ID
            ));

        if (! $response->successful() || $response->body() === '') {
            throw new RuntimeException(
                'Workbook Prognosa Weekly gagal dimuat (status '.$response->status().').'
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

    private function localPayloadPath(): string
    {
        $workbookPath = $this->localFallbackPath();
        $extensionPosition = strrpos($workbookPath, '.');

        return $extensionPosition === false
            ? $workbookPath.'.json'
            : substr($workbookPath, 0, $extensionPosition).'.json';
    }

    /** @param array<string, mixed> $payload */
    private function persistLocalPayload(array $payload): void
    {
        $targetPath = $this->localPayloadPath();

        try {
            File::ensureDirectoryExists(dirname($targetPath));
            $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if ($json === false || file_put_contents($targetPath, $json, LOCK_EX) === false) {
                report(new RuntimeException('Fallback payload Prognosa Weekly tidak dapat diperbarui.'));
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function persistLocalFallback(string $sourcePath): void
    {
        $targetPath = $this->localFallbackPath();

        try {
            File::ensureDirectoryExists(dirname($targetPath));
            if (! @copy($sourcePath, $targetPath)) {
                report(new RuntimeException('Fallback lokal Prognosa Weekly tidak dapat diperbarui.'));
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /** @return array<string, mixed>|null */
    private function localFallbackPayload(Throwable $refreshException): ?array
    {
        $payloadPath = $this->localPayloadPath();
        if (is_file($payloadPath)) {
            try {
                $payload = json_decode((string) file_get_contents($payloadPath), true, 512, JSON_THROW_ON_ERROR);
                if (
                    is_array($payload)
                    && (bool) data_get($payload, 'meta.available', false)
                    && data_get($payload, 'meta.spreadsheet_id') === self::SPREADSHEET_ID
                ) {
                    data_set($payload, 'meta.stale', true);
                    data_set($payload, 'meta.fallback', 'local-payload');
                    data_set(
                        $payload,
                        'meta.local_updated_at',
                        Carbon::createFromTimestamp(filemtime($payloadPath))->toDateTimeString()
                    );
                    data_set($payload, 'meta.refresh_error', $refreshException->getMessage());

                    return $payload;
                }
            } catch (Throwable $payloadException) {
                report($payloadException);
            }
        }

        $path = $this->localFallbackPath();
        if (! is_file($path) || ! $this->localWorkbookMatchesCurrentSource($path)) {
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

    private function localWorkbookMatchesCurrentSource(string $path): bool
    {
        try {
            $reader = IOFactory::createReaderForFile($path);
            $sheetNames = $reader->listWorksheetNames($path);

            return collect($sheetNames)->contains(
                static fn (string $sheetName): bool => strcasecmp(trim($sheetName), self::SHEETS['area6'][0]) === 0
            );
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    private function csvUrl(string $sheetName): string
    {
        return sprintf(
            'https://docs.google.com/spreadsheets/d/%s/gviz/tq?%s',
            self::SPREADSHEET_ID,
            http_build_query(['tqx' => 'out:csv', 'sheet' => $sheetName])
        );
    }

    private function sourceUrl(): string
    {
        return sprintf(
            'https://docs.google.com/spreadsheets/d/%s/edit?usp=sharing',
            self::SPREADSHEET_ID
        );
    }

    /**
     * @param  array<string, string>  $csvByScope
     * @return array<string, mixed>
     */
    private function parseCsvSources(array $csvByScope): array
    {
        $matrices = [];
        foreach ($csvByScope as $scopeKey => $csv) {
            $matrices[$scopeKey] = $this->parseCsvMatrix($csv);
        }

        return $this->parseMatrices($matrices);
    }

    /**
     * @param  array<string, array<int, array<int, string>>>  $matrices
     * @return array<string, mixed>
     */
    private function parseMatrices(array $matrices): array
    {
        if (! isset($matrices['area6'])) {
            throw new RuntimeException('Sheet Area 6 untuk acuan Prognosa tidak tersedia.');
        }

        $areaLayout = $this->resolveCsvLayout($matrices['area6']);
        $positionDate = $areaLayout['latest_date'] ?? null;
        if (! $positionDate instanceof Carbon) {
            throw new RuntimeException('Periode posisi terbaru pada sheet Area 6 tidak ditemukan.');
        }

        $forecastDate = now()->startOfDay();
        $preferredWeek = $this->preferredWeekForDate($forecastDate, $areaLayout);
        $availableWeeks = $this->availableCsvWeeks($matrices['area6'], $areaLayout);
        $week = $this->resolveAvailableCsvWeek($matrices['area6'], $areaLayout, $preferredWeek);
        $scopes = [];
        foreach ($matrices as $scopeKey => $matrix) {
            $layout = $this->resolveCsvLayout($matrix);
            $weeklyScopes = [];
            foreach ($availableWeeks as $availableWeek) {
                $weeklyScopes['W'.$availableWeek] = $this->parseCsvScope(
                    $matrix,
                    $layout,
                    $availableWeek,
                    (int) $layout['actual_column']
                );
            }

            $selectedScope = $weeklyScopes['W'.$week] ?? $this->parseCsvScope(
                $matrix,
                $layout,
                $week,
                (int) $layout['actual_column']
            );
            $selectedScope['weeks'] = $weeklyScopes;
            $scopes[$scopeKey] = $selectedScope;
        }

        return [
            'meta' => array_merge($this->csvMetadata($forecastDate, $positionDate, $week, $availableWeeks), [
                'available' => true,
                'stale' => false,
                'source' => 'Prognosa > Prognosa Weekly',
                'source_url' => $this->sourceUrl(),
                'spreadsheet_id' => self::SPREADSHEET_ID,
                'source_sheet' => self::SHEETS['area6'][0],
                'fetched_at' => now()->toDateTimeString(),
            ]),
            'scopes' => $scopes,
        ];
    }

    /** @return array<int, array<int, string>> */
    private function parseCsvMatrix(string $csv): array
    {
        $csv = trim($csv);
        if ($csv === '' || str_contains(strtolower(substr($csv, 0, 300)), '<html')) {
            throw new RuntimeException('Sumber Prognosa Weekly tidak mengembalikan CSV yang valid.');
        }

        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new RuntimeException('CSV sementara Prognosa Weekly tidak dapat dibaca.');
        }

        try {
            fwrite($stream, $csv);
            rewind($stream);
            $rows = [];

            while (($row = fgetcsv($stream, null, ',', '"', '')) !== false) {
                $cleaned = array_map(
                    fn (mixed $value): string => $this->cleanCsvCell((string) $value),
                    $row
                );
                if (collect($cleaned)->contains(static fn (string $value): bool => $value !== '')) {
                    $rows[] = $cleaned;
                }
            }

            if (count($rows) < 2) {
                throw new RuntimeException('Sheet Prognosa Weekly belum memiliki tabel yang dapat dibaca.');
            }

            return $rows;
        } finally {
            fclose($stream);
        }
    }

    /**
     * @param  array<int, array<int, string>>  $matrix
     * @return array{header_row: int, label_column: int, forecast_base_column: int, forecast_columns: array<int, int>, forecast_dates: array<int, Carbon>, actual_column: int, latest_date: ?Carbon}
     */
    private function resolveCsvLayout(array $matrix): array
    {
        $headerRow = null;
        $labelColumn = null;

        foreach (array_slice($matrix, 0, 15, true) as $rowIndex => $row) {
            foreach ($row as $columnIndex => $value) {
                if ($this->normaliseLabel($value) === 'KETERANGAN') {
                    $headerRow = (int) $rowIndex;
                    $labelColumn = (int) $columnIndex;
                    break 2;
                }
            }
        }

        if ($headerRow === null || $labelColumn === null) {
            throw new RuntimeException('Header KETERANGAN pada sheet Prognosa Weekly tidak ditemukan.');
        }

        $combinedHeaders = $this->combinedHeaders($matrix, $headerRow);
        $forecastColumns = $this->forecastColumns($combinedHeaders);
        $forecastBase = $forecastColumns[1] ?? null;
        if ($forecastBase === null) {
            foreach (array_slice($matrix, $headerRow, 3, true) as $row) {
                foreach ($row as $columnIndex => $value) {
                    if ($this->normaliseLabel($value) === 'PROGNOSA') {
                        $forecastBase = (int) $columnIndex;
                        break 2;
                    }
                }
            }
        }

        if ($forecastBase === null) {
            throw new RuntimeException('Kolom Prognosa Week 1 pada sheet Prognosa Weekly tidak ditemukan.');
        }

        $dateCandidates = [];
        foreach (array_slice($matrix, $headerRow, 4, true) as $row) {
            foreach ($row as $columnIndex => $value) {
                if ((int) $columnIndex >= $forecastBase) {
                    continue;
                }

                $date = $this->dateFromText($value);
                if ($date) {
                    $dateCandidates[] = [
                        'column' => (int) $columnIndex,
                        'date' => $date,
                    ];
                }
            }
        }

        usort(
            $dateCandidates,
            static fn (array $left, array $right): int => $left['date']->timestamp <=> $right['date']->timestamp
        );
        $latest = $dateCandidates === [] ? null : end($dateCandidates);

        return [
            'header_row' => $headerRow,
            'label_column' => $labelColumn,
            'forecast_base_column' => $forecastBase,
            'forecast_columns' => $forecastColumns,
            'forecast_dates' => $this->forecastDates($combinedHeaders, $forecastColumns),
            'actual_column' => (int) ($latest['column'] ?? max($labelColumn + 1, $forecastBase - 2)),
            'latest_date' => $latest['date'] ?? null,
        ];
    }

    /** @return array<int, string> */
    private function combinedHeaders(array $matrix, int $headerRow): array
    {
        $headers = [];
        foreach (array_slice($matrix, $headerRow, 3, true) as $row) {
            foreach ($row as $columnIndex => $value) {
                $value = $this->cleanCsvCell((string) $value);
                if ($value === '') {
                    continue;
                }

                $current = $headers[(int) $columnIndex] ?? '';
                if ($current === '' || ! str_contains($this->normaliseLabel($current), $this->normaliseLabel($value))) {
                    $headers[(int) $columnIndex] = trim($current.' '.$value);
                }
            }
        }

        ksort($headers);

        return $headers;
    }

    /** @return array<int, int> */
    private function forecastColumns(array $headers): array
    {
        $columns = [];
        $started = false;

        foreach ($headers as $column => $header) {
            if (preg_match('/\bWEEK\s*(\d{1,2})\b/i', $this->normaliseLabel((string) $header), $matches) !== 1) {
                if ($started) {
                    break;
                }

                continue;
            }

            $week = (int) $matches[1];
            if (! $started && $week !== 1) {
                continue;
            }
            if ($started && $week !== count($columns) + 1) {
                break;
            }

            $started = true;
            $columns[$week] = (int) $column;
        }

        return $columns;
    }

    /**
     * @param  array<int, int>  $forecastColumns
     * @return array<int, Carbon>
     */
    private function forecastDates(array $headers, array $forecastColumns): array
    {
        $dates = [];
        foreach ($forecastColumns as $week => $column) {
            $date = $this->dateFromText((string) ($headers[$column] ?? ''));
            if ($date instanceof Carbon) {
                $dates[(int) $week] = $date;
            }
        }

        return $dates;
    }

    /**
     * @param  array<int, array<int, string>>  $matrix
     * @param  array{header_row: int, label_column: int, forecast_base_column: int, forecast_columns: array<int, int>, forecast_dates: array<int, Carbon>, actual_column: int, latest_date: ?Carbon}  $layout
     * @return array<string, mixed>
     */
    private function parseCsvScope(array $matrix, array $layout, int $week, int $actualColumn): array
    {
        $rows = $this->csvMetricRows($matrix, (int) $layout['label_column']);
        $forecastColumn = (int) ($layout['forecast_columns'][$week]
            ?? ((int) $layout['forecast_base_column'] + $week - 1));
        $metrics = [];

        foreach ($rows as $metric => $metricRows) {
            $forecast = $this->sumCsvRows($matrix, $metricRows, $forecastColumn);
            $sourceActual = $this->sumCsvRows($matrix, $metricRows, $actualColumn);
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

    /**
     * @param  array<int, array<int, string>>  $matrix
     * @return array<string, array<int, int>>
     */
    private function csvMetricRows(array $matrix, int $labelColumn): array
    {
        if ($this->firstCsvRowByLabels(
            $matrix,
            ['2 OS TOTAL'],
            0,
            count($matrix) - 1,
            $labelColumn
        ) !== null) {
            return $this->dashboardCsvMetricRows($matrix, $labelColumn);
        }

        $lastRow = count($matrix) - 1;
        $osStart = $this->requiredCsvRow($matrix, ['PINJAMAN'], 0, $lastRow, $labelColumn);
        $smlStart = $this->requiredCsvRow($matrix, ['SML'], $osStart + 1, $lastRow, $labelColumn);
        $nplStart = $this->requiredCsvRow($matrix, ['NPL'], $smlStart + 1, $lastRow, $labelColumn);
        $fundingStart = $this->requiredCsvRow(
            $matrix,
            ['DANA PIHAK KE TIGA'],
            $nplStart + 1,
            $lastRow,
            $labelColumn
        );
        $recoveryStart = $this->firstCsvRowByLabels(
            $matrix,
            ['RECOVERY DH'],
            $fundingStart + 1,
            $lastRow,
            $labelColumn
        );
        $fundingEnd = $recoveryStart === null ? $lastRow : $recoveryStart - 1;
        $fundingTotal = $this->requiredCsvRow(
            $matrix,
            ['TOTAL RITEL MICRO WHOLESALE', '1 SIMPANAN'],
            $fundingStart,
            $fundingEnd,
            $labelColumn
        );

        $lastFundingRow = function (array $labels) use ($matrix, $fundingStart, $fundingEnd, $labelColumn): array {
            $matches = $this->csvRowsByLabels($matrix, $labels, $fundingStart, $fundingEnd, $labelColumn);

            return $matches === [] ? [] : [(int) end($matches)];
        };

        $rows = [
            'simpanan' => [$fundingTotal],
            'funding_retail' => [$this->requiredCsvRow($matrix, ['RITEL'], $fundingStart, $fundingTotal - 1, $labelColumn)],
            'funding_micro' => [$this->requiredCsvRow($matrix, ['MICRO'], $fundingStart, $fundingTotal - 1, $labelColumn)],
            'funding_wholesale' => [$this->requiredCsvRow($matrix, ['WHOLESALE'], $fundingStart, $fundingEnd, $labelColumn)],
            'giro' => $lastFundingRow(['GIRO']),
            'tabungan' => $lastFundingRow(['TABUNGAN']),
            'deposito' => $lastFundingRow(['DEPOSITO']),
            'os' => [$osStart],
            'sml' => [$smlStart],
            'npl' => [$nplStart],
            'recovery' => $recoveryStart === null ? [] : [$recoveryStart],
        ];

        $sections = [
            'os' => [$osStart, $smlStart - 1],
            'sml' => [$smlStart, $nplStart - 1],
            'npl' => [$nplStart, $fundingStart - 1],
        ];
        $creditRows = [
            'sme' => ['KECIL KOMERSIAL', 'B SME'],
            'consumer' => ['CONSUMER', 'KONSUMER', 'C KONSUMER'],
            'micro' => ['MICRO', 'D MIKRO'],
            'sme_non_cashcoll' => ['KECIL KOMERSIAL', 'KECIL NON CASHCOLL'],
            'sme_cashcoll' => ['CASHCOLL'],
            'consumer_briguna' => ['BRIGUNA RITEL', 'BRIGUNA'],
            'consumer_kpr' => ['KPR'],
            'micro_briguna' => ['BRIGUNA MIKRO'],
            'micro_kupedes' => ['KUPEDES'],
            'micro_kur_mikro' => ['KUR MIKRO'],
            'micro_kur_kecil' => ['KUR KECIL'],
            'micro_kpp' => ['KREDIT MIKRO KPP', 'KUR KPP'],
        ];

        foreach ($sections as $suffix => [$start, $end]) {
            foreach ($creditRows as $metric => $labels) {
                $row = $this->firstCsvRowByLabels($matrix, $labels, $start, $end, $labelColumn);
                $rows["{$metric}_{$suffix}"] = $row === null ? [] : [$row];
            }
        }

        return $rows;
    }

    /**
     * Resolve metrics from the current Dashboard Harian-style Prognosa source.
     * The labels, rather than fixed row numbers, remain the contract so an
     * inserted display row does not shift every landing-page metric.
     *
     * @param  array<int, array<int, string>>  $matrix
     * @return array<string, array<int, int>>
     */
    private function dashboardCsvMetricRows(array $matrix, int $labelColumn): array
    {
        $lastRow = count($matrix) - 1;
        $fundingStart = $this->requiredCsvRow($matrix, ['1 SIMPANAN'], 0, $lastRow, $labelColumn);
        $osStart = $this->requiredCsvRow($matrix, ['2 OS TOTAL'], $fundingStart + 1, $lastRow, $labelColumn);
        $smlSection = $this->requiredCsvRow(
            $matrix,
            ['3 TOTAL SML % NON COMMERCIAL'],
            $osStart + 1,
            $lastRow,
            $labelColumn
        );
        $smlTotal = $this->requiredCsvRow(
            $matrix,
            ['TOTAL SML ABS NON COMMERCIAL'],
            $smlSection,
            $lastRow,
            $labelColumn
        );
        $nplSection = $this->requiredCsvRow(
            $matrix,
            ['4 TOTAL NPL % NON COMMERCIAL'],
            $smlTotal + 1,
            $lastRow,
            $labelColumn
        );
        $nplTotal = $this->requiredCsvRow(
            $matrix,
            ['TOTAL NPL ABS NON COMMERCIAL'],
            $nplSection,
            $lastRow,
            $labelColumn
        );
        $casaStart = $this->firstCsvRowByLabels(
            $matrix,
            ['5 %CASA'],
            $nplTotal + 1,
            $lastRow,
            $labelColumn
        );
        $nplEnd = ($casaStart ?? ($lastRow + 1)) - 1;
        $fundingEnd = $osStart - 1;
        $osEnd = $smlSection - 1;
        $smlEnd = $nplSection - 1;

        $rows = [
            'simpanan' => [$fundingStart],
            'funding_retail' => [$this->requiredCsvRow($matrix, ['A RITEL'], $fundingStart, $fundingEnd, $labelColumn)],
            'funding_micro' => [$this->requiredCsvRow($matrix, ['B MIKRO'], $fundingStart, $fundingEnd, $labelColumn)],
            'funding_wholesale' => [$this->requiredCsvRow($matrix, ['C WHOLESALE'], $fundingStart, $fundingEnd, $labelColumn)],
            'giro' => $this->csvRowsByLabels($matrix, ['GIRO'], $fundingStart, $fundingEnd, $labelColumn),
            'tabungan' => $this->csvRowsByLabels($matrix, ['TABUNGAN'], $fundingStart, $fundingEnd, $labelColumn),
            'deposito' => $this->csvRowsByLabels($matrix, ['DEPOSITO'], $fundingStart, $fundingEnd, $labelColumn),
            'os' => [$osStart],
            'sml' => [$smlTotal],
            'npl' => [$nplTotal],
        ];

        $recoveryRow = $this->firstCsvRowByLabels(
            $matrix,
            ['7 REC DH PER SEGMEN', 'RECOVERY DH'],
            $nplTotal + 1,
            $lastRow,
            $labelColumn
        );
        $rows['recovery'] = $recoveryRow === null ? [] : [$recoveryRow];

        $sections = [
            'os' => [$osStart, $osEnd],
            'sml' => [$smlTotal, $smlEnd],
            'npl' => [$nplTotal, $nplEnd],
        ];
        $creditRows = [
            'sme' => ['A SME', 'B SME'],
            'consumer' => ['B KONSUMER', 'C KONSUMER'],
            'micro' => ['C MIKRO', 'D MIKRO'],
            'sme_non_cashcoll' => ['KECIL', 'KECIL NON CASHCOLL'],
            'sme_cashcoll' => ['CASHCOLL'],
            'consumer_briguna' => ['BRIGUNA'],
            'consumer_kpr' => ['KPR'],
            'micro_briguna' => ['BRIGUNA MIKRO'],
            'micro_kupedes' => ['KUPEDES'],
            'micro_kur_mikro' => ['KUR MIKRO'],
            'micro_kur_kecil' => ['KUR KECIL'],
            'micro_kpp' => ['KUR KPP', 'KREDIT MIKRO KPP'],
        ];

        foreach ($sections as $suffix => [$start, $end]) {
            foreach ($creditRows as $metric => $labels) {
                $row = $this->firstCsvRowByLabels($matrix, $labels, $start, $end, $labelColumn);
                $rows["{$metric}_{$suffix}"] = $row === null ? [] : [$row];
            }
        }

        return $rows;
    }

    /**
     * Resolve the latest populated week up to the target week declared by the source.
     *
     * @param  array<int, array<int, string>>  $matrix
     * @param  array{header_row: int, label_column: int, forecast_base_column: int, forecast_columns: array<int, int>, forecast_dates: array<int, Carbon>, actual_column: int, latest_date: ?Carbon}  $layout
     */
    private function resolveAvailableCsvWeek(array $matrix, array $layout, int $preferredWeek): int
    {
        $availableWeeks = $this->availableCsvWeeks($matrix, $layout);
        $preferredWeek = max(1, $preferredWeek);
        $eligibleWeeks = array_values(array_filter(
            $availableWeeks,
            static fn (int $week): bool => $week <= $preferredWeek
        ));

        if ($eligibleWeeks !== []) {
            return (int) end($eligibleWeeks);
        }

        return (int) ($availableWeeks[0] ?? 1);
    }

    /**
     * @param  array<int, array<int, string>>  $matrix
     * @param  array{header_row: int, label_column: int, forecast_base_column: int, forecast_columns: array<int, int>, forecast_dates: array<int, Carbon>, actual_column: int, latest_date: ?Carbon}  $layout
     * @return array<int, int>
     */
    private function availableCsvWeeks(array $matrix, array $layout): array
    {
        $metricRows = $this->csvMetricRows($matrix, (int) $layout['label_column']);
        $rows = array_values(array_unique(array_merge(...array_values($metricRows))));
        $availableWeeks = [];

        $forecastColumns = (array) ($layout['forecast_columns'] ?? []);
        if ($forecastColumns === []) {
            $forecastBase = (int) $layout['forecast_base_column'];
            $forecastColumns = [
                1 => $forecastBase,
                2 => $forecastBase + 1,
                3 => $forecastBase + 2,
                4 => $forecastBase + 3,
            ];
        }

        foreach ($forecastColumns as $week => $column) {
            foreach ($rows as $row) {
                if ($this->parseLocaleNumber($matrix[$row][$column] ?? null) !== null) {
                    $availableWeeks[] = (int) $week;
                    break;
                }
            }
        }

        return $availableWeeks === [] ? [1] : $availableWeeks;
    }

    /**
     * @param  array{forecast_dates?: array<int, Carbon>}  $layout
     */
    private function preferredWeekForDate(Carbon $date, array $layout): int
    {
        $forecastDates = (array) ($layout['forecast_dates'] ?? []);
        foreach ($forecastDates as $week => $targetDate) {
            if ($targetDate instanceof Carbon && $date->lessThanOrEqualTo($targetDate)) {
                return (int) $week;
            }
        }

        if ($forecastDates !== []) {
            return (int) array_key_last($forecastDates);
        }

        return $this->weekOfMonth($date);
    }

    /**
     * @param  array<int, array<int, string>>  $matrix
     * @param  array<int, string>  $labels
     */
    private function requiredCsvRow(
        array $matrix,
        array $labels,
        int $start,
        int $end,
        int $labelColumn
    ): int {
        $row = $this->firstCsvRowByLabels($matrix, $labels, $start, $end, $labelColumn);
        if ($row === null) {
            throw new RuntimeException("Baris Prognosa Weekly '".implode(' / ', $labels)."' tidak ditemukan.");
        }

        return $row;
    }

    /**
     * @param  array<int, array<int, string>>  $matrix
     * @param  array<int, string>  $labels
     */
    private function firstCsvRowByLabels(
        array $matrix,
        array $labels,
        int $start,
        int $end,
        int $labelColumn
    ): ?int {
        return $this->csvRowsByLabels($matrix, $labels, $start, $end, $labelColumn)[0] ?? null;
    }

    /**
     * @param  array<int, array<int, string>>  $matrix
     * @param  array<int, string>  $labels
     * @return array<int, int>
     */
    private function csvRowsByLabels(
        array $matrix,
        array $labels,
        int $start,
        int $end,
        int $labelColumn
    ): array {
        $needles = array_map(fn (string $label): string => $this->normaliseLabel($label), $labels);
        $matches = [];

        for ($row = max(0, $start); $row <= min($end, count($matrix) - 1); $row++) {
            $label = $this->normaliseLabel((string) ($matrix[$row][$labelColumn] ?? ''));
            if (in_array($label, $needles, true)) {
                $matches[] = $row;
            }
        }

        return $matches;
    }

    /**
     * @param  array<int, array<int, string>>  $matrix
     * @param  array<int, int>  $rows
     */
    private function sumCsvRows(array $matrix, array $rows, int $column): ?float
    {
        $sum = 0.0;
        $hasValue = false;

        foreach ($rows as $row) {
            $value = $this->parseLocaleNumber($matrix[$row][$column] ?? null);
            if ($value === null) {
                continue;
            }

            $sum += $value;
            $hasValue = true;
        }

        return $hasValue ? $sum : null;
    }

    private function parseLocaleNumber(mixed $value): ?float
    {
        $value = $this->cleanCsvCell((string) $value);
        if ($value === '' || $value === '-') {
            return null;
        }

        $negativeParentheses = str_starts_with($value, '(') && str_ends_with($value, ')');
        $value = trim($value, "() \t\n\r\0\x0B");
        $value = str_replace(['%', 'Rp', ' '], '', $value);

        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } elseif (str_contains($value, ',')) {
            $value = str_replace(',', '.', $value);
        } elseif (substr_count($value, '.') > 1 || preg_match('/^-?\d{1,3}\.\d{3}$/', $value) === 1) {
            $value = str_replace('.', '', $value);
        }

        if (! is_numeric($value)) {
            return null;
        }

        $number = (float) $value;

        return $negativeParentheses ? -abs($number) : $number;
    }

    private function cleanCsvCell(string $value): string
    {
        $value = str_replace(["\u{00A0}", "\r", "\n"], [' ', ' ', ' '], $value);

        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    /** @return array<string, mixed> */
    private function csvMetadata(Carbon $forecastDate, Carbon $positionDate, int $week, array $availableWeeks): array
    {
        $localizedForecastDate = $forecastDate->copy()->locale('id');
        $localizedPositionDate = $positionDate->copy()->locale('id');

        return [
            'forecast_date' => $forecastDate->toDateString(),
            'forecast_date_label' => $localizedForecastDate->translatedFormat('d M y'),
            'position_date' => $positionDate->toDateString(),
            'position_date_label' => $localizedPositionDate->translatedFormat('d M y'),
            'week_number' => $week,
            'week_label' => 'W'.$week,
            'available_weeks' => array_values($availableWeeks),
            'label' => 'Prognosa W'.$week.' '.$localizedForecastDate->translatedFormat('F Y'),
        ];
    }

    /** @return array<string, mixed> */
    private function parseSpreadsheet(Spreadsheet $workbook): array
    {
        try {
            return $this->parseStructuredSpreadsheet($workbook);
        } catch (Throwable $structuredException) {
            try {
                $payload = $this->parseLegacySpreadsheet($workbook);
                data_set($payload, 'meta.spreadsheet_id', self::SPREADSHEET_ID);

                return $payload;
            } catch (Throwable $legacyException) {
                throw new RuntimeException(
                    'Struktur workbook Prognosa Weekly tidak dikenali. Format tabel: '
                    .$structuredException->getMessage()
                    .' Format lama: '
                    .$legacyException->getMessage(),
                    0,
                    $legacyException
                );
            }
        }
    }

    /** @return array<string, mixed> */
    private function parseStructuredSpreadsheet(Spreadsheet $workbook): array
    {
        $matrices = [];
        foreach (self::SHEETS as $scopeKey => $sheetNames) {
            $sheet = null;
            foreach ($sheetNames as $sheetName) {
                $sheet = $this->worksheet($workbook, $sheetName);
                if ($sheet instanceof Worksheet) {
                    break;
                }
            }
            if ($sheet instanceof Worksheet) {
                $matrices[$scopeKey] = $this->structuredWorksheetMatrix($sheet);
            }
        }

        return $this->parseMatrices($matrices);
    }

    /** @return array<int, array<int, string>> */
    private function structuredWorksheetMatrix(Worksheet $sheet): array
    {
        $highestColumn = min(35, Coordinate::columnIndexFromString($sheet->getHighestColumn()));
        $matrix = [];
        for ($row = 1; $row <= $sheet->getHighestRow(); $row++) {
            $values = [];
            $hasValue = false;
            for ($column = 1; $column <= $highestColumn; $column++) {
                $value = $this->spreadsheetValue($sheet, $column, $row);
                $values[] = $value;
                $hasValue = $hasValue || $value !== '';
            }

            if ($hasValue) {
                $matrix[] = $values;
            }
        }

        return $matrix;
    }

    private function spreadsheetValue(Worksheet $sheet, int $column, int $row): string
    {
        $cell = $sheet->getCell(Coordinate::stringFromColumnIndex($column).$row);
        $value = $cell->isFormula() ? $cell->getOldCalculatedValue() : $cell->getValue();
        if ($value === null || $value === '' || (is_string($value) && str_starts_with($value, '#'))) {
            return '';
        }

        return $this->cleanCsvCell((string) $value);
    }

    /** @return array<string, mixed> */
    private function parseLegacySpreadsheet(Spreadsheet $workbook): array
    {
        $scopes = [];
        $meta = null;

        foreach (self::SHEETS as $scopeKey => $sheetNames) {
            $sheet = null;
            foreach ($sheetNames as $sheetName) {
                $sheet = $this->worksheet($workbook, $sheetName);
                if ($sheet) {
                    break;
                }
            }
            if (! $sheet) {
                continue;
            }

            $columns = $this->resolvePositionColumns($sheet);
            if (! $columns) {
                continue;
            }

            $scopePayload = $this->parseScope($sheet, $columns);
            $week = $this->weekOfMonth($columns['forecast_date']);
            $scopes[$scopeKey] = array_merge($scopePayload, [
                'weeks' => ['W'.$week => $scopePayload],
            ]);
            $meta ??= $this->metadata($columns);
        }

        if ($scopes === [] || ! $meta) {
            throw new RuntimeException('Struktur workbook Prognosa Weekly belum dapat dikenali.');
        }

        return [
            'meta' => array_merge($meta, [
                'available' => true,
                'stale' => false,
                'source' => 'Prognosa > Prognosa Weekly',
                'source_url' => $this->sourceUrl(),
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
                if (! str_contains($label, 'UPDATE POSISI')) {
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

        if (! $fundingStart || ! $osStart || ! $smlStart || ! $nplStart) {
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

        $recoveryRow = $this->findRow($sheet, 'Recovery DH', $nplStart, $highestRow);
        $rows['recovery'] = $recoveryRow ? [$recoveryRow] : [];

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
        if (! $row) {
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
        $cell = $sheet->getCell(Coordinate::stringFromColumnIndex($column).$row);

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
     * @param  array{actual_date: Carbon, forecast_date: Carbon}  $columns
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
            'week_label' => 'W'.$week,
            'available_weeks' => [$week],
            'label' => 'Prognosa W'.$week,
        ];
    }

    private function weekOfMonth(Carbon $date): int
    {
        return min(5, max(1, intdiv(max(1, $date->day) - 1, 7) + 1));
    }

    private function dateFromCell(Worksheet $sheet, int $column, int $row): ?Carbon
    {
        $cell = $sheet->getCell(Coordinate::stringFromColumnIndex($column).$row);
        $raw = $cell->getValue();
        if (is_numeric($raw) && ExcelDate::isDateTime($cell)) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $raw))->startOfDay();
        }

        return $this->dateFromText($this->formattedCell($sheet, $column, $row));
    }

    private function dateFromText(string $text): ?Carbon
    {
        $value = Str::upper(Str::ascii($text));
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

        if (! preg_match('/\b(\d{1,2})\s+('.$monthPattern.')\s+(\d{2,4})\b/', $value, $match)) {
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
            Coordinate::stringFromColumnIndex($column).$row
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
                'available_weeks' => [],
                'error' => $message,
            ],
            'scopes' => [],
        ];
    }
}
