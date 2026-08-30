<?php

namespace App\Http\Controllers;

use App\Support\DashboardHarianSnapshotService;
use App\Support\UserBranchScope;
use Carbon\Carbon;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PrognosaWeeklyController extends Controller
{
    private const SPREADSHEET_ID = '1HpvFkAVzSYhAdIeDr1uZ9XIhBhuCMbpUv0Lm8qrq0oY';

    private const SPREADSHEET_URL = 'https://docs.google.com/spreadsheets/d/1HpvFkAVzSYhAdIeDr1uZ9XIhBhuCMbpUv0Lm8qrq0oY/edit?usp=sharing';

    private const REPORT_FIRST_COLUMN = 2; // B

    private const REPORT_LAST_COLUMN = 35; // AI

    private const REPORT_COLUMN_COUNT = 34;

    private const WORKBOOK_GROUP_HEADER_ROW = 9;

    private const WORKBOOK_COLUMN_HEADER_ROW = 10;

    private const WORKBOOK_DATA_START_ROW = 11;

    private const BRANCH_KEYS = ['madiun', 'magetan', 'ngawi', 'ponorogo'];

    private const AREA_6_KANCA = ['KC Madiun', 'KC Magetan', 'KC Ngawi', 'KC Ponorogo'];

    private const DISPLAY_POSITION_KEYS = ['yoy', 'ytd', 'm2', 'mtm', 'mtd', 'h1', 'current'];

    private const FORECAST_COLUMN_BY_WEEK = [
        1 => 8,
        2 => 9,
        3 => 10,
        4 => 11,
    ];

    private const RUN_OFF_SNAPSHOT_PERIOD = '2026-08-23';

    /**
     * Snapshot read-only dari kolom SISA RUN OFF UPDATE per 23 Agustus 2026.
     * Nominal disimpan dalam Rp juta, sesuai satuan tampilan Weekly Prognosa.
     * Total dan rincian ditempel sesuai angka sumber agar selisih pembulatan tetap terjaga.
     *
     * @var array<string, array<string, array{accounts: int, amount_millions: float}>>
     */
    private const RUN_OFF_SNAPSHOT = [
        'area' => [
            'micro' => ['accounts' => 68_244, 'amount_millions' => 145_851.0],
            'micro_kur_kecil' => ['accounts' => 1_229, 'amount_millions' => 8_801.0],
            'micro_kupedes' => ['accounts' => 20_400, 'amount_millions' => 54_686.0],
            'micro_briguna' => ['accounts' => 442, 'amount_millions' => 549.0],
            'micro_kur_mikro' => ['accounts' => 45_900, 'amount_millions' => 80_989.0],
            'micro_kpp' => ['accounts' => 273, 'amount_millions' => 826.0],
            'small' => ['accounts' => 714, 'amount_millions' => 8_617.0],
            'small_commercial' => ['accounts' => 714, 'amount_millions' => 8_617.0],
            'small_cashcall' => ['accounts' => 0, 'amount_millions' => 0.0],
            'consumer' => ['accounts' => 5_147, 'amount_millions' => 7_528.0],
            'consumer_briguna' => ['accounts' => 4_490, 'amount_millions' => 6_555.0],
            'consumer_kpr' => ['accounts' => 657, 'amount_millions' => 973.0],
        ],
        'madiun' => [
            'micro' => ['accounts' => 16_406, 'amount_millions' => 31_070.0],
            'micro_kur_kecil' => ['accounts' => 354, 'amount_millions' => 2_104.0],
            'micro_kupedes' => ['accounts' => 4_583, 'amount_millions' => 10_128.0],
            'micro_briguna' => ['accounts' => 166, 'amount_millions' => 203.0],
            'micro_kur_mikro' => ['accounts' => 11_291, 'amount_millions' => 18_605.0],
            'micro_kpp' => ['accounts' => 12, 'amount_millions' => 29.0],
            'small' => ['accounts' => 243, 'amount_millions' => 3_498.0],
            'small_commercial' => ['accounts' => 243, 'amount_millions' => 3_498.0],
            'small_cashcall' => ['accounts' => 0, 'amount_millions' => 0.0],
            'consumer' => ['accounts' => 2_469, 'amount_millions' => 3_720.0],
            'consumer_briguna' => ['accounts' => 1_894, 'amount_millions' => 2_852.0],
            'consumer_kpr' => ['accounts' => 575, 'amount_millions' => 869.0],
        ],
        'magetan' => [
            'micro' => ['accounts' => 13_332, 'amount_millions' => 31_434.0],
            'micro_kur_kecil' => ['accounts' => 230, 'amount_millions' => 1_558.0],
            'micro_kupedes' => ['accounts' => 5_092, 'amount_millions' => 12_600.0],
            'micro_briguna' => ['accounts' => 129, 'amount_millions' => 172.0],
            'micro_kur_mikro' => ['accounts' => 7_811, 'amount_millions' => 16_935.0],
            'micro_kpp' => ['accounts' => 70, 'amount_millions' => 169.0],
            'small' => ['accounts' => 104, 'amount_millions' => 768.0],
            'small_commercial' => ['accounts' => 104, 'amount_millions' => 768.0],
            'small_cashcall' => ['accounts' => 0, 'amount_millions' => 0.0],
            'consumer' => ['accounts' => 981, 'amount_millions' => 1_438.0],
            'consumer_briguna' => ['accounts' => 974, 'amount_millions' => 1_427.0],
            'consumer_kpr' => ['accounts' => 7, 'amount_millions' => 11.0],
        ],
        'ngawi' => [
            'micro' => ['accounts' => 14_643, 'amount_millions' => 44_596.0],
            'micro_kur_kecil' => ['accounts' => 239, 'amount_millions' => 2_242.0],
            'micro_kupedes' => ['accounts' => 5_704, 'amount_millions' => 22_804.0],
            'micro_briguna' => ['accounts' => 86, 'amount_millions' => 85.0],
            'micro_kur_mikro' => ['accounts' => 8_532, 'amount_millions' => 19_214.0],
            'micro_kpp' => ['accounts' => 82, 'amount_millions' => 251.0],
            'small' => ['accounts' => 114, 'amount_millions' => 2_231.0],
            'small_commercial' => ['accounts' => 114, 'amount_millions' => 2_231.0],
            'small_cashcall' => ['accounts' => 0, 'amount_millions' => 0.0],
            'consumer' => ['accounts' => 877, 'amount_millions' => 1_235.0],
            'consumer_briguna' => ['accounts' => 873, 'amount_millions' => 1_230.0],
            'consumer_kpr' => ['accounts' => 4, 'amount_millions' => 4.0],
        ],
        'ponorogo' => [
            'micro' => ['accounts' => 23_863, 'amount_millions' => 38_751.0],
            'micro_kur_kecil' => ['accounts' => 406, 'amount_millions' => 2_897.0],
            'micro_kupedes' => ['accounts' => 5_021, 'amount_millions' => 9_153.0],
            'micro_briguna' => ['accounts' => 61, 'amount_millions' => 89.0],
            'micro_kur_mikro' => ['accounts' => 18_266, 'amount_millions' => 26_235.0],
            'micro_kpp' => ['accounts' => 109, 'amount_millions' => 377.0],
            'small' => ['accounts' => 253, 'amount_millions' => 2_120.0],
            'small_commercial' => ['accounts' => 253, 'amount_millions' => 2_120.0],
            'small_cashcall' => ['accounts' => 0, 'amount_millions' => 0.0],
            'consumer' => ['accounts' => 820, 'amount_millions' => 1_134.0],
            'consumer_briguna' => ['accounts' => 749, 'amount_millions' => 1_046.0],
            'consumer_kpr' => ['accounts' => 71, 'amount_millions' => 89.0],
        ],
    ];

    /** @var array<string, array{label: string, sheet: string}> */
    private const SHEETS = [
        'area' => ['label' => 'Area 6', 'sheet' => 'Report AREA'],
        'madiun' => ['label' => 'KC Madiun', 'sheet' => 'MADIUN'],
        'magetan' => ['label' => 'KC Magetan', 'sheet' => 'MAGETAN'],
        'ngawi' => ['label' => 'KC Ngawi', 'sheet' => 'NGAWI'],
        'ponorogo' => ['label' => 'KC Ponorogo', 'sheet' => 'PONOROGO'],
    ];

    public function __construct(private ?DashboardHarianSnapshotService $dashboardHarianSnapshotService = null)
    {
    }

    public function index(Request $request, ?string $sheet = null): View
    {
        [$sheetOptions, $selectedSheetKey, $isLocked] = $this->resolveSheetSelection(
            $sheet ?: $request->input('sheet')
        );

        $refresh = $request->boolean('refresh');
        if ($refresh) {
            Cache::forget($this->cacheKey($selectedSheetKey));
        }

        $forecastPayload = Cache::get($this->cacheKey($selectedSheetKey));
        if (! is_array($forecastPayload)) {
            $forecastPayload = $this->fetchSheet($selectedSheetKey);
            if (empty($forecastPayload['error'])) {
                Cache::put($this->cacheKey($selectedSheetKey), $forecastPayload, now()->addMinutes(10));
            }
        }

        $payload = $forecastPayload;
        if (empty($forecastPayload['error'])) {
            try {
                $payload = $this->buildDashboardAlignedPayload(
                    $selectedSheetKey,
                    $forecastPayload,
                    $request->input('week')
                );
            } catch (\Throwable $exception) {
                Log::error('Weekly Prognosa could not align Dashboard Harian positions.', [
                    'sheet' => $selectedSheetKey,
                    'message' => $exception->getMessage(),
                ]);

                $payload = $this->emptyPayload(
                    'Posisi Keragaan Harian belum dapat dimuat. Silakan perbarui data beberapa saat lagi.'
                );
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
            'highlights' => $payload['highlights'] ?? [],
            'sourceSheets' => $payload['source_sheets'] ?? [],
            'latestForecastLabel' => $payload['latest_forecast_label'] ?? null,
            'activeForecastWeek' => $payload['active_forecast_week'] ?? null,
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
        if (! isset(self::SHEETS[$selectedSheetKey])) {
            $selectedSheetKey = 'area';
        }

        return [self::SHEETS, $selectedSheetKey, false];
    }

    private function fetchSheet(string $sheetKey): array
    {
        $sourceKeys = $this->sourceSheetKeys($sheetKey);

        try {
            return $this->buildPayload($sheetKey, $this->fetchCsvMatrices($sourceKeys));
        } catch (\Throwable $csvException) {
            Log::warning('Weekly Prognosa CSV source could not be parsed.', [
                'sheet' => $sheetKey,
                'message' => $csvException->getMessage(),
            ]);
        }

        try {
            $response = Http::timeout(30)
                ->retry(2, 350)
                ->get($this->workbookUrl());

            if (! $response->successful() || $response->body() === '') {
                throw new \RuntimeException('Workbook source returned an invalid response.');
            }

            return $this->buildPayload(
                $sheetKey,
                $this->parseWorkbookMatrices($response->body(), $sourceKeys)
            );
        } catch (\Throwable $workbookException) {
            Log::warning('Weekly Prognosa workbook fallback could not be parsed.', [
                'sheet' => $sheetKey,
                'message' => $workbookException->getMessage(),
            ]);

            return $this->emptyPayload(
                'Data Weekly Prognosa belum dapat disinkronkan. Silakan perbarui data beberapa saat lagi.'
            );
        }
    }

    /** @return array<int, string> */
    private function sourceSheetKeys(string $sheetKey): array
    {
        return $sheetKey === 'area' ? self::BRANCH_KEYS : [$sheetKey];
    }

    /**
     * @param  array<int, string>  $sheetKeys
     * @return array<string, array{headers: array<int, string>, rows: array<int, array<int, string>>}>
     */
    private function fetchCsvMatrices(array $sheetKeys): array
    {
        if (count($sheetKeys) === 1) {
            $key = $sheetKeys[0];
            $response = Http::timeout(20)
                ->retry(2, 300)
                ->get($this->csvUrl(self::SHEETS[$key]['sheet']));

            return [$key => $this->matrixFromCsvResponse($response, $key)];
        }

        $responses = Http::pool(function (Pool $pool) use ($sheetKeys): array {
            $requests = [];
            foreach ($sheetKeys as $key) {
                $requests[] = $pool->as($key)
                    ->timeout(20)
                    ->get($this->csvUrl(self::SHEETS[$key]['sheet']));
            }

            return $requests;
        });

        $matrices = [];
        foreach ($sheetKeys as $key) {
            $response = $responses[$key] ?? null;
            if (! $response instanceof Response || ! $response->successful()) {
                $response = Http::timeout(20)
                    ->retry(1, 300)
                    ->get($this->csvUrl(self::SHEETS[$key]['sheet']));
            }

            $matrices[$key] = $this->matrixFromCsvResponse($response, $key);
        }

        return $matrices;
    }

    /** @return array{headers: array<int, string>, rows: array<int, array<int, string>>} */
    private function matrixFromCsvResponse(Response $response, string $sheetKey): array
    {
        if (! $response->successful()) {
            throw new \RuntimeException("Sheet {$sheetKey} returned status {$response->status()}.");
        }

        $csv = trim($response->body());
        if ($csv === '' || str_contains(strtolower(substr($csv, 0, 300)), '<html')) {
            throw new \RuntimeException("Sheet {$sheetKey} did not return CSV data.");
        }

        return $this->parseCsvMatrix($csv);
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
        return 'prognosa_weekly:forecast:v5:area6_consolidated:'.$sheetKey;
    }

    /**
     * @param  array<int, string>  $sourceKeys
     * @return array<string, array{headers: array<int, string>, rows: array<int, array<int, string>>}>
     */
    private function parseWorkbookMatrices(string $contents, array $sourceKeys): array
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'prognosa_weekly_');
        if ($temporaryPath === false) {
            throw new \RuntimeException('Workbook sementara tidak dapat dibuat.');
        }

        $workbookPath = $temporaryPath.'.xlsx';
        @unlink($temporaryPath);

        try {
            if (file_put_contents($workbookPath, $contents) === false) {
                throw new \RuntimeException('Workbook sementara tidak dapat ditulis.');
            }

            $reader = new Xlsx;
            $reader->setReadDataOnly(false);
            $reader->setLoadSheetsOnly(array_map(
                static fn (string $key): string => self::SHEETS[$key]['sheet'],
                $sourceKeys
            ));
            $workbook = $reader->load($workbookPath);

            try {
                $matrices = [];
                foreach ($sourceKeys as $key) {
                    $sheet = $this->worksheetByName($workbook, self::SHEETS[$key]['sheet']);
                    if ($sheet === null) {
                        throw new \RuntimeException("Sheet {$key} tidak ditemukan di workbook.");
                    }

                    $matrices[$key] = $this->parseWorksheetMatrix($sheet);
                }

                return $matrices;
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
            if (strcasecmp(trim($sheet->getTitle()), trim($name)) === 0) {
                return $sheet;
            }
        }

        return null;
    }

    /** @return array{headers: array<int, string>, rows: array<int, array<int, string>>} */
    private function parseWorksheetMatrix(Worksheet $sheet): array
    {
        $headers = [];
        for ($column = self::REPORT_FIRST_COLUMN; $column <= self::REPORT_LAST_COLUMN; $column++) {
            $group = $this->worksheetDisplayValue($sheet, $column, self::WORKBOOK_GROUP_HEADER_ROW, false);
            $label = $this->worksheetDisplayValue($sheet, $column, self::WORKBOOK_COLUMN_HEADER_ROW, false);
            $headers[] = trim(implode(' ', array_filter([$group, $label])));
        }

        if (! str_contains(strtoupper($headers[1] ?? ''), 'KETERANGAN')) {
            throw new \RuntimeException('Header KETERANGAN tidak ditemukan pada workbook.');
        }

        $rows = [];
        for ($row = self::WORKBOOK_DATA_START_ROW; $row <= $sheet->getHighestRow(); $row++) {
            $values = [];
            for ($column = self::REPORT_FIRST_COLUMN; $column <= self::REPORT_LAST_COLUMN; $column++) {
                $reportIndex = $column - self::REPORT_FIRST_COLUMN;
                $values[] = $this->worksheetDisplayValue($sheet, $column, $row, $reportIndex >= 2);
            }

            if ($this->rowHasValue($values)) {
                $rows[] = $values;
            }
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    private function worksheetDisplayValue(
        Worksheet $sheet,
        int $column,
        int $row,
        bool $formatNumeric
    ): string {
        $cell = $sheet->getCell(Coordinate::stringFromColumnIndex($column).$row);
        $value = $cell->isFormula() ? $cell->getOldCalculatedValue() : $cell->getValue();

        if ($value === null || $value === '' || (is_string($value) && str_starts_with($value, '#'))) {
            return '';
        }

        if ($formatNumeric && is_numeric($value)) {
            $reportIndex = $column - self::REPORT_FIRST_COLUMN;

            return $reportIndex >= 32
                ? $this->formatRatio((float) $value)
                : $this->formatNumber((float) $value);
        }

        return $this->cleanCell((string) $value);
    }

    /** @return array{headers: array<int, string>, rows: array<int, array<int, string>>} */
    private function parseCsvMatrix(string $csv): array
    {
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new \RuntimeException('CSV sementara tidak dapat dibaca.');
        }

        try {
            fwrite($stream, $csv);
            rewind($stream);
            $sourceRows = [];
            while (($row = fgetcsv($stream, null, ',', '"', '')) !== false) {
                $row = array_map(fn (mixed $value): string => $this->cleanCell((string) $value), $row);
                if ($this->rowHasValue($row)) {
                    $sourceRows[] = $row;
                }
            }
        } finally {
            fclose($stream);
        }

        if (count($sourceRows) < 2) {
            throw new \RuntimeException('Sheet belum memiliki tabel Weekly Prognosa.');
        }

        $headers = $this->sliceReportColumns($sourceRows[0]);
        if (! str_contains(strtoupper($headers[1] ?? ''), 'KETERANGAN')) {
            throw new \RuntimeException('Header KETERANGAN tidak ditemukan pada CSV.');
        }

        $rows = [];
        foreach (array_slice($sourceRows, 1) as $sourceRow) {
            $row = $this->sliceReportColumns($sourceRow);
            if ($this->rowHasValue($row)) {
                $rows[] = $row;
            }
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    /** @return array<int, string> */
    private function sliceReportColumns(array $row): array
    {
        $row = array_pad($row, self::REPORT_LAST_COLUMN, '');

        return array_values(array_slice(
            $row,
            self::REPORT_FIRST_COLUMN - 1,
            self::REPORT_COLUMN_COUNT
        ));
    }

    private function rowHasValue(array $row): bool
    {
        foreach ($row as $value) {
            if ($this->cleanCell((string) $value) !== '') {
                return true;
            }
        }

        return false;
    }

    private function cleanCell(string $value): string
    {
        $value = str_replace(["\u{00A0}", "\r", "\n"], [' ', ' ', ' '], $value);

        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    /**
     * @param  array<string, array{headers: array<int, string>, rows: array<int, array<int, string>>}>  $matrices
     */
    private function buildPayload(string $sheetKey, array $matrices): array
    {
        $sourceKeys = $this->sourceSheetKeys($sheetKey);
        foreach ($sourceKeys as $sourceKey) {
            if (! isset($matrices[$sourceKey])) {
                throw new \RuntimeException("Data sheet {$sourceKey} tidak tersedia.");
            }
        }

        $baseMatrix = $matrices[$sourceKeys[0]];
        $rows = $sheetKey === 'area'
            ? $this->consolidateAreaRows($matrices)
            : $baseMatrix['rows'];

        return [
            'title' => $sheetKey === 'area'
                ? 'Monitoring PTP - Konsolidasi Area 6'
                : 'Monitoring PTP - '.self::SHEETS[$sheetKey]['label'],
            'forecast_rows' => $rows,
            'source_sheets' => array_map(
                static fn (string $key): string => self::SHEETS[$key]['label'],
                $sourceKeys
            ),
            'fetched_at' => now()->toDateTimeString(),
            'error' => null,
        ];
    }

    private function buildDashboardAlignedPayload(
        string $sheetKey,
        array $forecastPayload,
        mixed $requestedWeek = null
    ): array
    {
        $service = $this->dashboardHarianSnapshotService ??= app(DashboardHarianSnapshotService::class);
        $selectedPeriod = $service->resolveEffectivePeriod(null);
        if ($selectedPeriod === null) {
            throw new \RuntimeException('Periode Keragaan Harian belum tersedia.');
        }

        $dailyPayload = $service->buildDashboardPayload(
            $selectedPeriod,
            null,
            $this->dashboardScopeForSheet($sheetKey),
            null
        );
        if (empty($dailyPayload['rows'])) {
            throw new \RuntimeException('Baris Keragaan Harian belum tersedia.');
        }

        $runOffContext = $this->buildRunOffContext($sheetKey);
        $activeWeek = $this->resolveForecastWeek($selectedPeriod, $requestedWeek);
        $rows = $this->buildDashboardAlignedRows(
            $forecastPayload['forecast_rows'] ?? [],
            $dailyPayload['rows'],
            $activeWeek,
            $runOffContext['segments']
        );
        $latestDate = $this->formatIndonesianDate(Carbon::parse($selectedPeriod));
        $latestForecastLabel = 'Week '.$activeWeek;

        return [
            'title' => $forecastPayload['title'] ?? 'Weekly Prognosa',
            'latest_date' => $latestDate,
            'latest_forecast_label' => $latestForecastLabel,
            'active_forecast_week' => $activeWeek,
            'header_groups' => $this->headerGroups($dailyPayload),
            'header_columns' => $this->headerColumns($dailyPayload, $activeWeek, $runOffContext),
            'rows' => $rows,
            'highlights' => $this->highlights($rows, 'Posisi '.$latestDate),
            'source_sheets' => $forecastPayload['source_sheets'] ?? [],
            'fetched_at' => $forecastPayload['fetched_at'] ?? now()->toDateTimeString(),
            'error' => null,
        ];
    }

    private function dashboardScopeForSheet(string $sheetKey): array|string
    {
        if ($sheetKey === 'area') {
            return self::AREA_6_KANCA;
        }

        return self::SHEETS[$sheetKey]['label'];
    }

    /**
     * @return array{
     *     segments: array<string, array{accounts: int, amount_millions: float}|null>,
     *     latest_period: ?string,
     *     error: ?string
     * }
     */
    private function buildRunOffContext(string $sheetKey): array
    {
        $scopeKey = isset(self::RUN_OFF_SNAPSHOT[$sheetKey]) ? $sheetKey : 'area';
        $segments = self::RUN_OFF_SNAPSHOT[$scopeKey];
        $segments['retail'] = [
            'accounts' => $segments['small']['accounts'] + $segments['consumer']['accounts'],
            'amount_millions' => $segments['small']['amount_millions'] + $segments['consumer']['amount_millions'],
        ];
        $segments['total'] = [
            'accounts' => $segments['micro']['accounts'] + $segments['small']['accounts'] + $segments['consumer']['accounts'],
            'amount_millions' => $segments['micro']['amount_millions'] + $segments['small']['amount_millions'] + $segments['consumer']['amount_millions'],
        ];

        return [
            'segments' => $segments,
            'latest_period' => self::RUN_OFF_SNAPSHOT_PERIOD,
            'error' => null,
        ];
    }

    private function resolveForecastWeek(string $selectedPeriod, mixed $requestedWeek = null): int
    {
        $requestedWeek = filter_var($requestedWeek, FILTER_VALIDATE_INT);
        if (is_int($requestedWeek) && isset(self::FORECAST_COLUMN_BY_WEEK[$requestedWeek])) {
            return $requestedWeek;
        }

        $day = (int) Carbon::parse($selectedPeriod)->format('j');

        return min(4, max(1, intdiv(max(1, $day) - 1, 7) + 1));
    }

    /**
     * @param  array<int, array<int, string>>  $forecastRows
     * @param  array<int, array<string, mixed>>  $dailyRows
     * @return array<int, array<string, mixed>>
     */
    private function buildDashboardAlignedRows(
        array $forecastRows,
        array $dailyRows,
        int $activeWeek,
        array $runOffSegments
    ): array
    {
        $dailyRowsByKey = [];
        foreach ($dailyRows as $dailyRow) {
            $key = (string) ($dailyRow['key'] ?? '');
            if ($key !== '') {
                $dailyRowsByKey[$key] = $dailyRow;
            }
        }

        $forecastColumn = self::FORECAST_COLUMN_BY_WEEK[$activeWeek];
        $context = [
            'section' => 'pinjaman',
            'loan_family' => 'os',
            'loan_segment' => 'total',
            'dpk_scope' => '',
        ];
        $result = [];

        foreach ($forecastRows as $forecastRow) {
            $number = $this->cleanCell((string) ($forecastRow[0] ?? ''));
            $label = $this->cleanCell((string) ($forecastRow[1] ?? ''));
            $normalizedLabel = $this->normalizeIndicatorLabel($label);
            $context = $this->updateForecastContext($context, $normalizedLabel);
            $metricKeys = $this->forecastMetricKeys($context, $normalizedLabel);
            $isLowerBetter = $context['section'] === 'pinjaman'
                && in_array($context['loan_family'], ['sml', 'npl'], true);

            $cells = [
                $this->displayCell($number),
                $this->displayCell($label),
            ];

            $currentRaw = null;
            foreach (self::DISPLAY_POSITION_KEYS as $positionKey) {
                $rawValue = $this->aggregateDailyMetric($dailyRowsByKey, $metricKeys, $positionKey);
                if ($positionKey === 'current') {
                    $currentRaw = $rawValue;
                }
                $millions = $rawValue !== null ? $rawValue / 1_000_000 : null;
                $cells[] = $this->displayCell(
                    $this->formatDisplayNumber($millions),
                    $millions
                );
            }

            $forecastValue = $this->parseLocaleNumber($forecastRow[$forecastColumn] ?? '');
            $cells[] = $this->displayCell(
                $this->formatDisplayNumber($forecastValue),
                $forecastValue
            );

            $delta = $currentRaw !== null && $forecastValue !== null
                ? ($currentRaw / 1_000_000) - $forecastValue
                : null;
            $cells[] = $this->displayCell(
                $this->formatDisplayNumber($delta),
                $delta,
                $this->deltaTone($delta, $isLowerBetter)
            );

            $currentMillions = $currentRaw !== null ? $currentRaw / 1_000_000 : null;
            foreach (['rka', 'rka_dec'] as $rkaKey) {
                $rkaRaw = $this->aggregateDailyMetric($dailyRowsByKey, $metricKeys, $rkaKey);
                $rkaMillions = $rkaRaw !== null && abs($rkaRaw) > 0.000001
                    ? $rkaRaw / 1_000_000
                    : null;
                $rkaGap = $currentMillions !== null && $rkaMillions !== null
                    ? $currentMillions - $rkaMillions
                    : null;
                $achievement = $this->rkaAchievement(
                    $currentMillions,
                    $rkaMillions,
                    $isLowerBetter
                );

                $cells[] = $this->displayCell(
                    $this->formatDisplayNumber($rkaMillions),
                    $rkaMillions
                );
                $cells[] = $this->displayCell(
                    $this->formatDisplayNumber($rkaGap),
                    $rkaGap,
                    $this->deltaTone($rkaGap, $isLowerBetter)
                );
                $cells[] = $this->displayCell(
                    $this->formatDisplayPercent($achievement),
                    $achievement,
                    $this->achievementTone($achievement),
                    true
                );
            }

            $runOff = $context['section'] === 'pinjaman' && $context['loan_family'] === 'os'
                ? $this->runOffForIndicator(
                    $runOffSegments,
                    $normalizedLabel,
                    $context['loan_segment']
                )
                : null;
            $hasRunOff = $runOff !== null
                && ($runOff['accounts'] !== 0 || abs($runOff['amount_millions']) > 0.000001);
            $cells[] = $this->displayCell(
                $hasRunOff ? number_format($runOff['accounts'], 0, ',', '.') : '',
                $hasRunOff ? (float) $runOff['accounts'] : null
            );
            $cells[] = $this->displayCell(
                $this->formatDisplayNumber($hasRunOff ? $runOff['amount_millions'] : null),
                $hasRunOff ? $runOff['amount_millions'] : null
            );

            $result[] = [
                'cells' => $cells,
                'type' => $this->forecastRowType($number, $normalizedLabel),
                'section' => $context['section'],
                'label' => $label,
                'metric_keys' => $metricKeys,
            ];
        }

        return $result;
    }

    /**
     * @param  array<string, array{accounts: int, amount_millions: float}>  $segments
     * @return array{accounts: int, amount_millions: float}|null
     */
    private function runOffForIndicator(array $segments, string $label, string $segmentContext): ?array
    {
        $segmentKey = match ($label) {
            'PINJAMAN' => 'total',
            'MICRO', 'MICRO TOTAL', 'TOTAL KUPEDES KUR KPP BRIGUNA',
            'TOTAL KUPEDES KUR KPP DAN BRIGUNA' => 'micro',
            'KUR KECIL' => 'micro_kur_kecil',
            'KUPEDES' => 'micro_kupedes',
            'BRIGUNA MIKRO' => 'micro_briguna',
            'KUR MIKRO' => 'micro_kur_mikro',
            'KPP', 'KUR KPP', 'KREDIT MIKRO KPP' => 'micro_kpp',
            'RITEL' => 'retail',
            'KECIL KOMERSIAL', 'SMALL', 'SME' => 'small',
            'COMMERCIAL', 'KECIL NON CASHCALL', 'KECIL NON CASHCOLL' => 'small_commercial',
            'CASHCALL', 'CASHCOLL' => 'small_cashcall',
            'CONSUMER', 'KONSUMER' => 'consumer',
            'BRIGUNA RITEL' => 'consumer_briguna',
            'KPR' => 'consumer_kpr',
            'BRIGUNA' => match ($segmentContext) {
                'micro' => 'micro_briguna',
                'consumer' => 'consumer_briguna',
                default => null,
            },
            default => null,
        };

        return $segmentKey !== null ? ($segments[$segmentKey] ?? null) : null;
    }

    /** @return array{section: string, loan_family: string, loan_segment: string, dpk_scope: string} */
    private function updateForecastContext(array $context, string $label): array
    {
        if ($label === 'PINJAMAN') {
            return ['section' => 'pinjaman', 'loan_family' => 'os', 'loan_segment' => 'total', 'dpk_scope' => ''];
        }
        if ($label === 'SML') {
            return ['section' => 'pinjaman', 'loan_family' => 'sml', 'loan_segment' => '', 'dpk_scope' => ''];
        }
        if ($label === 'NPL') {
            return ['section' => 'pinjaman', 'loan_family' => 'npl', 'loan_segment' => '', 'dpk_scope' => ''];
        }
        if ($label === 'DANA PIHAK KE TIGA') {
            return ['section' => 'dpk', 'loan_family' => '', 'loan_segment' => '', 'dpk_scope' => ''];
        }
        if ($label === 'RECOVERY DH') {
            return ['section' => 'recovery', 'loan_family' => '', 'loan_segment' => '', 'dpk_scope' => ''];
        }

        if ($context['section'] === 'pinjaman' && $context['loan_family'] === 'os') {
            $context['loan_segment'] = match ($label) {
                'MICRO', 'MICRO TOTAL' => 'micro',
                'RITEL' => 'retail',
                'KECIL KOMERSIAL', 'SMALL', 'SME', 'COMMERCIAL',
                'KECIL NON CASHCALL', 'KECIL NON CASHCOLL', 'CASHCALL', 'CASHCOLL' => 'small',
                'CONSUMER', 'KONSUMER', 'CONSUMER TOTAL' => 'consumer',
                default => $context['loan_segment'],
            };

            return $context;
        }

        if ($context['section'] !== 'dpk') {
            return $context;
        }

        $context['dpk_scope'] = match ($label) {
            'RITEL' => 'ritel',
            'MICRO' => 'micro',
            'TOTAL RITEL MICRO NON WHOLESALE' => 'non_wholesale',
            'WHOLESALE' => 'wholesale',
            'TOTAL RITEL MICRO DAN WHOLESALE' => 'total',
            default => $context['dpk_scope'],
        };

        return $context;
    }

    /** @return array<int, string> */
    private function forecastMetricKeys(array $context, string $label): array
    {
        if ($context['section'] === 'dpk') {
            return $this->dpkMetricKeys($context['dpk_scope'], $label);
        }

        if ($context['section'] === 'recovery') {
            return match ($label) {
                'RECOVERY DH' => ['rec_dh_total'],
                'RITEL' => ['rec_dh_small'],
                'MICRO' => ['rec_dh_micro'],
                default => [],
            };
        }

        $family = $context['loan_family'];
        if ($label === 'PINJAMAN') {
            return ['total_os_non_commercial'];
        }
        if ($label === 'SML') {
            return ['total_sml_abs_non_commercial'];
        }
        if ($label === 'NPL') {
            return ['total_npl_abs_non_commercial'];
        }
        if (! in_array($family, ['os', 'sml', 'npl'], true)) {
            return [];
        }

        return match ($label) {
            'MICRO' => ["micro_{$family}"],
            'TOTAL KUPEDES KUR KPP BRIGUNA' => [
                "briguna_mikro_{$family}",
                "kupedes_{$family}",
                "kur_mikro_{$family}",
                "kur_kecil_{$family}",
                "kur_kpp_{$family}",
            ],
            'KUPEDES' => ["kupedes_{$family}"],
            'BRIGUNA MIKRO' => ["briguna_mikro_{$family}"],
            'KUR MIKRO' => ["kur_mikro_{$family}"],
            'KREDIT MIKRO KPP' => ["kur_kpp_{$family}"],
            'KUR KECIL' => ["kur_kecil_{$family}"],
            'RITEL' => ["sme_{$family}", "consumer_{$family}"],
            'KECIL KOMERSIAL' => ["kecil_{$family}"],
            'CONSUMER' => ["consumer_{$family}"],
            'BRIGUNA RITEL' => ["briguna_konsumer_{$family}"],
            'KPR' => ["kpr_{$family}"],
            default => [],
        };
    }

    /** @return array<int, string> */
    private function dpkMetricKeys(string $scope, string $label): array
    {
        $totals = [
            'RITEL' => ['simpanan_ritel'],
            'MICRO' => ['simpanan_mikro'],
            'TOTAL RITEL MICRO NON WHOLESALE' => ['simpanan_ritel', 'simpanan_mikro'],
            'WHOLESALE' => ['simpanan_wholesale'],
            'TOTAL RITEL MICRO DAN WHOLESALE' => ['total_simpanan'],
        ];
        if (isset($totals[$label])) {
            return $totals[$label];
        }

        $component = match ($label) {
            'GIRO' => 'giro',
            'TABUNGAN' => 'tabungan',
            'DEPOSITO' => 'deposito',
            default => null,
        };
        if ($component === null) {
            return [];
        }

        return match ($scope) {
            'ritel' => ["{$component}_ritel"],
            'micro' => ["{$component}_mikro"],
            'non_wholesale' => ["{$component}_ritel", "{$component}_mikro"],
            'wholesale' => ["{$component}_wholesale"],
            'total' => ["{$component}_ritel", "{$component}_mikro", "{$component}_wholesale"],
            default => [],
        };
    }

    private function aggregateDailyMetric(array $dailyRowsByKey, array $metricKeys, string $positionKey): ?float
    {
        $total = 0.0;
        $hasValue = false;

        foreach ($metricKeys as $metricKey) {
            $value = data_get($dailyRowsByKey, $metricKey.'.values.'.$positionKey);
            if (! is_numeric($value)) {
                continue;
            }

            $total += (float) $value;
            $hasValue = true;
        }

        return $hasValue ? $total : null;
    }

    /** @return array{value: string, negative: bool, positive: bool, percent: bool, tone: string} */
    private function displayCell(
        string $value,
        ?float $numericValue = null,
        string $tone = '',
        bool $percent = false
    ): array
    {
        return [
            'value' => $value,
            'negative' => $numericValue !== null && $numericValue < -0.000001,
            'positive' => $numericValue !== null && $numericValue > 0.000001,
            'percent' => $percent,
            'tone' => $tone,
        ];
    }

    private function deltaTone(?float $delta, bool $lowerBetter): string
    {
        if ($delta === null || abs($delta) < 0.000001) {
            return 'neutral';
        }

        $isGood = $lowerBetter ? $delta < 0 : $delta > 0;

        return $isGood ? 'good' : 'bad';
    }

    private function rkaAchievement(
        ?float $current,
        ?float $target,
        bool $lowerBetter
    ): ?float
    {
        if ($current === null || $target === null || abs($target) < 0.000001) {
            return null;
        }

        if ($lowerBetter) {
            return $current <= 0.000001 ? 100.0 : ($target / $current) * 100;
        }

        return ($current / $target) * 100;
    }

    private function achievementTone(?float $achievement): string
    {
        if ($achievement === null) {
            return 'neutral';
        }

        return $achievement >= 100 ? 'good' : 'bad';
    }

    private function formatDisplayNumber(?float $value): string
    {
        if ($value === null) {
            return '';
        }

        $formatted = $this->formatNumber(abs($value));

        return $value < -0.000001 ? '('.$formatted.')' : $formatted;
    }

    private function formatDisplayPercent(?float $value): string
    {
        if ($value === null) {
            return '';
        }

        return number_format($value, 2, ',', '.').'%';
    }

    private function normalizeIndicatorLabel(string $label): string
    {
        $label = strtoupper($this->cleanCell($label));
        $label = str_replace('&', ' DAN ', $label);
        $label = (string) preg_replace('/[^A-Z0-9]+/', ' ', $label);

        return trim((string) preg_replace('/\s+/', ' ', $label));
    }

    private function forecastRowType(string $number, string $label): string
    {
        return match (true) {
            in_array($label, ['PINJAMAN', 'DANA PIHAK KE TIGA', 'RECOVERY DH'], true) => 'section',
            $number !== '' && $number !== '-' => 'category',
            str_starts_with($label, 'TOTAL ') => 'subtotal',
            in_array($label, ['SML', 'NPL'], true) => 'metric',
            default => 'detail',
        };
    }

    /**
     * @param  array<string, array{headers: array<int, string>, rows: array<int, array<int, string>>}>  $matrices
     * @return array<int, array<int, string>>
     */
    private function consolidateAreaRows(array $matrices): array
    {
        $indexedRows = [];
        foreach (self::BRANCH_KEYS as $key) {
            $indexedRows[$key] = $this->indexRowsByIdentity($matrices[$key]['rows']);
        }

        $templateRows = $matrices[self::BRANCH_KEYS[0]]['rows'];
        $templateOccurrences = [];
        $consolidated = [];

        foreach ($templateRows as $templateRow) {
            $identity = $this->rowIdentity($templateRow);
            $occurrence = ($templateOccurrences[$identity] ?? 0) + 1;
            $templateOccurrences[$identity] = $occurrence;
            $identityKey = $identity.'#'.$occurrence;

            $branchRows = [];
            foreach (self::BRANCH_KEYS as $key) {
                if (! isset($indexedRows[$key][$identityKey])) {
                    throw new \RuntimeException("Struktur sheet {$key} tidak sejajar pada {$identityKey}.");
                }
                $branchRows[] = $indexedRows[$key][$identityKey];
            }

            $row = array_fill(0, self::REPORT_COLUMN_COUNT, '');
            $row[0] = $templateRow[0] ?? '';
            $row[1] = $templateRow[1] ?? '';

            for ($column = 2; $column <= 31; $column++) {
                $sum = 0.0;
                $hasNumber = false;
                foreach ($branchRows as $branchRow) {
                    $number = $this->parseLocaleNumber($branchRow[$column] ?? '');
                    if ($number !== null) {
                        $sum += $number;
                        $hasNumber = true;
                    }
                }

                $row[$column] = $hasNumber ? $this->formatNumber($sum) : '';
            }

            $currentPosition = $this->parseLocaleNumber($row[7] ?? '');
            foreach ([32 => 25, 33 => 29] as $achievementColumn => $rkaColumn) {
                $rka = $this->parseLocaleNumber($row[$rkaColumn] ?? '');
                $row[$achievementColumn] = $currentPosition !== null && $rka !== null && abs($rka) > 0.000001
                    ? $this->formatRatio($currentPosition / $rka)
                    : '0,00%';
            }

            $consolidated[] = $row;
        }

        return $consolidated;
    }

    /** @return array<string, array<int, string>> */
    private function indexRowsByIdentity(array $rows): array
    {
        $indexed = [];
        $occurrences = [];

        foreach ($rows as $row) {
            $identity = $this->rowIdentity($row);
            $occurrence = ($occurrences[$identity] ?? 0) + 1;
            $occurrences[$identity] = $occurrence;
            $indexed[$identity.'#'.$occurrence] = $row;
        }

        return $indexed;
    }

    private function rowIdentity(array $row): string
    {
        return strtoupper($this->cleanCell((string) ($row[0] ?? '')))
            .'|'
            .strtoupper($this->cleanCell((string) ($row[1] ?? '')));
    }

    private function parseLocaleNumber(mixed $value): ?float
    {
        $value = $this->cleanCell((string) $value);
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

    private function formatNumber(float $value): string
    {
        if (abs($value) < 0.5) {
            $value = 0.0;
        }

        return number_format((int) round($value), 0, ',', '.');
    }

    private function formatRatio(float $value): string
    {
        return number_format($value * 100, 2, ',', '.').'%';
    }

    /** @return array<int, array{label: string, key: string, colspan: int, start: int}> */
    private function headerGroups(array $dailyPayload): array
    {
        $rkaCurrentLabel = $this->rkaHeaderLabel(
            data_get($dailyPayload, 'comparison_periods.rka.period')
                ?: ($dailyPayload['selected_period'] ?? null)
        );
        $rkaDecemberLabel = $this->rkaHeaderLabel(
            data_get($dailyPayload, 'comparison_periods.rka_dec.period')
        );

        return [
            ['label' => 'Indikator', 'key' => 'identity', 'colspan' => 2, 'start' => 0],
            ['label' => 'Posisi', 'key' => 'position', 'colspan' => 7, 'start' => 2],
            ['label' => 'Prognosa', 'key' => 'forecast', 'colspan' => 1, 'start' => 9],
            ['label' => 'Delta', 'key' => 'delta', 'colspan' => 1, 'start' => 10],
            ['label' => 'RKA '.$rkaCurrentLabel, 'key' => 'rka_current', 'colspan' => 3, 'start' => 11],
            ['label' => 'RKA '.$rkaDecemberLabel, 'key' => 'rka_december', 'colspan' => 3, 'start' => 14],
            ['label' => 'Sisa Run Off Update', 'key' => 'runoff', 'colspan' => 2, 'start' => 17],
        ];
    }

    /** @return array<int, array{index: int, label: string, detail: string, group: string, summary: bool}> */
    private function headerColumns(array $dailyPayload, int $activeWeek, array $runOffContext): array
    {
        $periods = $dailyPayload['comparison_periods'] ?? [];
        $selectedPeriod = $dailyPayload['selected_period'] ?? null;

        return [
            ['index' => 0, 'label' => 'No', 'detail' => '', 'group' => 'identity', 'summary' => true],
            ['index' => 1, 'label' => 'Keterangan', 'detail' => '', 'group' => 'identity', 'summary' => true],
            ['index' => 2, 'label' => 'YOY', 'detail' => $this->periodHeaderLabel(data_get($periods, 'yoy.period')), 'group' => 'position', 'summary' => false],
            ['index' => 3, 'label' => 'YTD', 'detail' => $this->periodHeaderLabel(data_get($periods, 'ytd.period')), 'group' => 'position', 'summary' => false],
            ['index' => 4, 'label' => 'M-2', 'detail' => $this->periodHeaderLabel(data_get($periods, 'm2.period')), 'group' => 'position', 'summary' => false],
            ['index' => 5, 'label' => 'MTM', 'detail' => $this->periodHeaderLabel(data_get($periods, 'mtm.period')), 'group' => 'position', 'summary' => false],
            ['index' => 6, 'label' => 'MTD', 'detail' => $this->periodHeaderLabel(data_get($periods, 'mtd.period')), 'group' => 'position', 'summary' => false],
            ['index' => 7, 'label' => 'H-1', 'detail' => $this->periodHeaderLabel(data_get($periods, 'h1.period')), 'group' => 'position', 'summary' => false],
            ['index' => 8, 'label' => 'Posisi', 'detail' => $this->periodHeaderLabel($selectedPeriod), 'group' => 'position', 'summary' => true],
            ['index' => 9, 'label' => 'Week '.$activeWeek, 'detail' => 'Target aktif', 'group' => 'forecast', 'summary' => true],
            ['index' => 10, 'label' => 'Vs W'.$activeWeek, 'detail' => 'Posisi - target', 'group' => 'delta', 'summary' => true],
            ['index' => 11, 'label' => 'Rp', 'detail' => 'Target', 'group' => 'rka_current', 'summary' => true],
            ['index' => 12, 'label' => 'Selisih', 'detail' => 'Posisi - RKA', 'group' => 'rka_current', 'summary' => true],
            ['index' => 13, 'label' => '% Pencapaian', 'detail' => 'Terhadap target', 'group' => 'rka_current', 'summary' => true],
            ['index' => 14, 'label' => 'Rp', 'detail' => 'Target', 'group' => 'rka_december', 'summary' => true],
            ['index' => 15, 'label' => 'Selisih', 'detail' => 'Posisi - RKA', 'group' => 'rka_december', 'summary' => true],
            ['index' => 16, 'label' => '% Pencapaian', 'detail' => 'Terhadap target', 'group' => 'rka_december', 'summary' => true],
            [
                'index' => 17,
                'label' => 'Rek',
                'detail' => $this->periodHeaderLabel($runOffContext['latest_period'] ?? null),
                'group' => 'runoff',
                'summary' => true,
            ],
            [
                'index' => 18,
                'label' => 'Rp',
                'detail' => 'Rp juta',
                'group' => 'runoff',
                'summary' => true,
            ],
        ];
    }

    private function rkaHeaderLabel(mixed $period): string
    {
        $period = trim((string) $period);
        if ($period === '') {
            return '-';
        }

        try {
            return $this->formatIndonesianMonth(Carbon::parse($period));
        } catch (\Throwable) {
            return $period;
        }
    }

    private function periodHeaderLabel(mixed $period): string
    {
        $period = trim((string) $period);
        if ($period === '') {
            return '-';
        }

        try {
            return $this->formatIndonesianDate(Carbon::parse($period));
        } catch (\Throwable) {
            return $period;
        }
    }

    private function columnGroup(int $index): string
    {
        return match (true) {
            $index <= 1 => 'identity',
            $index <= 6 => 'position',
            $index === 7 => 'current',
            $index <= 11 => 'forecast',
            $index <= 17 => 'delta',
            $index <= 29 => 'rka',
            $index <= 31 => 'gap',
            default => 'achievement',
        };
    }

    private function formatHeaderLabel(string $sourceLabel, int $index): string
    {
        if ($index === 0) {
            return 'No';
        }
        if ($index === 1) {
            return 'Keterangan';
        }

        $label = $this->cleanCell($sourceLabel);
        $prefixes = [
            2 => '/^POSISI\s+/i',
            7 => '/^UPDATE\s+POSISI\s+/i',
            8 => '/^PROGNOSA\s+/i',
            12 => '/^DELTA\s+/i',
            18 => '/^RKA\s+/i',
            30 => '/^GAP\s+/i',
            32 => '/^PENCAPAIAN\s+RKA\s+/i',
        ];
        if (isset($prefixes[$index])) {
            $label = trim((string) preg_replace($prefixes[$index], '', $label));
        }
        if ($index === 7) {
            $label = trim((string) preg_replace('/^JAM\s+/i', '', $label));
        }

        if (($date = $this->tryFormatDateLabel($label)) !== null) {
            return $date;
        }

        if (preg_match('/^(?:RKA\s+)?([[:alpha:]]+)\s+(\d{4})$/iu', $label, $matches) === 1) {
            try {
                return $this->formatIndonesianMonth(Carbon::parse(
                    $this->normaliseMonthName('1 '.$matches[1].' '.$matches[2])
                ));
            } catch (\Throwable) {
            }
        }

        if (preg_match('/^WEEK\s*(\d+)$/i', $label, $matches) === 1) {
            return 'Week '.$matches[1];
        }

        if (preg_match('/^PROGNOSA\s+WEEK\s*(\d+)$/i', $label, $matches) === 1) {
            return 'Prog. W'.$matches[1];
        }

        return $label !== '' ? $label : '-';
    }

    private function latestPositionDate(array $headerColumns): ?string
    {
        for ($index = 6; $index >= 2; $index--) {
            $label = (string) ($headerColumns[$index]['label'] ?? '');
            if (preg_match('/^\d{2}\s+[^\s]+\s+\d{2}$/u', $label) === 1) {
                return $label;
            }
        }

        return null;
    }

    private function tryFormatDateLabel(string $value): ?string
    {
        $value = trim($value);
        if (preg_match('/(\d{1,2})\s+([[:alpha:]]+)\s+(\d{2,4})/iu', $value, $matches) !== 1) {
            return null;
        }

        try {
            return $this->formatIndonesianDate(Carbon::parse($this->normaliseMonthName(
                $matches[1].' '.$matches[2].' '.$matches[3]
            )));
        } catch (\Throwable) {
            return null;
        }
    }

    private function formatIndonesianDate(Carbon $date): string
    {
        return $date->format('d').' '.$this->indonesianMonthAbbreviation($date).' '.$date->format('y');
    }

    private function formatIndonesianMonth(Carbon $date): string
    {
        return $this->indonesianMonthAbbreviation($date).' '.$date->format('y');
    }

    private function indonesianMonthAbbreviation(Carbon $date): string
    {
        return [
            1 => 'Jan',
            2 => 'Feb',
            3 => 'Mar',
            4 => 'Apr',
            5 => 'Mei',
            6 => 'Jun',
            7 => 'Jul',
            8 => 'Agu',
            9 => 'Sep',
            10 => 'Okt',
            11 => 'Nov',
            12 => 'Des',
        ][(int) $date->format('n')];
    }

    private function normaliseMonthName(string $value): string
    {
        return str_ireplace(
            [
                'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                'Juli', 'Agustus', 'Agu', 'Ags', 'Agt', 'September', 'Oktober',
                'November', 'Desember', 'Des',
            ],
            [
                'January', 'February', 'March', 'April', 'May', 'June',
                'July', 'August', 'August', 'August', 'August', 'September', 'October',
                'November', 'December', 'Dec',
            ],
            $value
        );
    }

    /**
     * @return array<int, array{cells: array<int, array{value: string, negative: bool, percent: bool}>, type: string, section: string, label: string}>
     */
    private function decorateRows(array $rows): array
    {
        $decorated = [];
        $section = 'pinjaman';

        foreach ($rows as $row) {
            $number = $this->cleanCell((string) ($row[0] ?? ''));
            $label = $this->cleanCell((string) ($row[1] ?? ''));
            $normalisedLabel = strtoupper($label);

            if ($normalisedLabel === 'DANA PIHAK KE TIGA') {
                $section = 'dpk';
            } elseif ($normalisedLabel === 'RECOVERY DH') {
                $section = 'recovery';
            }

            $type = match (true) {
                in_array($normalisedLabel, ['PINJAMAN', 'DANA PIHAK KE TIGA', 'RECOVERY DH'], true) => 'section',
                $number !== '' && $number !== '-' => 'category',
                str_starts_with($normalisedLabel, 'TOTAL ') => 'subtotal',
                in_array($normalisedLabel, ['SML', 'NPL'], true) => 'metric',
                default => 'detail',
            };

            $cells = [];
            for ($index = 0; $index < self::REPORT_COLUMN_COUNT; $index++) {
                $value = $this->cleanCell((string) ($row[$index] ?? ''));
                $numericValue = $index >= 2 ? $this->parseLocaleNumber($value) : null;
                $cells[] = [
                    'value' => $value,
                    'negative' => $numericValue !== null && $numericValue < 0,
                    'percent' => $index >= 32,
                ];
            }

            $decorated[] = [
                'cells' => $cells,
                'type' => $type,
                'section' => $section,
                'label' => $label,
            ];
        }

        return $decorated;
    }

    /** @return array<int, array{label: string, value: string, context: string, icon: string, tone: string}> */
    private function highlights(array $rows, string $context): array
    {
        $definitions = [
            'PINJAMAN' => ['label' => 'Pinjaman', 'icon' => 'fa-hand-holding-usd', 'tone' => 'blue'],
            'TOTAL RITEL, MICRO & WHOLESALE' => ['label' => 'Dana Pihak Ketiga', 'icon' => 'fa-university', 'tone' => 'slate'],
            'SML' => ['label' => 'SML', 'icon' => 'fa-exclamation-circle', 'tone' => 'amber'],
            'NPL' => ['label' => 'NPL', 'icon' => 'fa-chart-line', 'tone' => 'red'],
        ];
        $highlights = [];

        foreach ($definitions as $target => $definition) {
            foreach ($rows as $row) {
                if (strtoupper((string) ($row['label'] ?? '')) !== $target) {
                    continue;
                }

                $value = (string) data_get($row, 'cells.8.value', '');
                $highlights[] = $definition + [
                    'value' => $value !== '' ? $value : '-',
                    'context' => $context,
                ];
                break;
            }
        }

        return $highlights;
    }

    private function emptyPayload(string $message): array
    {
        return [
            'title' => 'Weekly Prognosa',
            'latest_date' => null,
            'latest_forecast_label' => null,
            'active_forecast_week' => null,
            'header_groups' => [],
            'header_columns' => [],
            'rows' => [],
            'highlights' => [],
            'forecast_rows' => [],
            'source_sheets' => [],
            'fetched_at' => now()->toDateTimeString(),
            'error' => $message,
        ];
    }
}
