<?php

namespace App\Http\Controllers;

use App\Support\DashboardHarianSnapshotService;
use Illuminate\Contracts\View\View;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class DashboardHarianController extends Controller
{
    private const AREA_6_KANCA = ['KC Madiun', 'KC Magetan', 'KC Ponorogo', 'KC Ngawi'];

    public function __construct(
        private readonly DashboardHarianSnapshotService $dashboardHarianSnapshotService
    ) {
    }

    public function index(Request $request): View
    {
        [$selectedKanca, $selectedUnit] = $this->resolveKeragaanFilters($request);
        $selectedPeriod = $this->dashboardHarianSnapshotService->resolveEffectivePeriod($request->input('posisi_terakhir'));
        $selectedRka = $this->dashboardHarianSnapshotService->resolveEffectiveRkaPeriod($request->input('posisi_rka'), $selectedPeriod);
        $baseUrl = rtrim($request->getSchemeAndHttpHost() . $request->getBaseUrl(), '/');
        $dataUrl = $baseUrl . '/dashboard-harian/data';
        $filters = $this->dashboardHarianSnapshotService->fetchFilterOptions($selectedPeriod, $selectedKanca, $selectedUnit);
        $initialData = null;

        if ($selectedPeriod) {
            $initialData = $this->payload($selectedPeriod, $selectedRka, $selectedKanca, $selectedUnit)
                + ['available_filters' => $filters];
        }

        $dashboardPage = [
            'routes' => [
                'data' => $dataUrl,
                'export' => route('dashboard.harian.export'),
            ],
            'filters' => $filters,
            'selected' => [
                'kanca' => $selectedKanca ?? [],
                'unit_kerja' => $selectedUnit ?? 'all',
                'posisi_terakhir' => $selectedPeriod,
                'posisi_rka' => $selectedRka ? substr($selectedRka, 0, 7) : null,
            ],
            'initialData' => $initialData,
        ];

        return view('report.dashboard-harian', compact('dashboardPage'));
    }

    public function timeseries(Request $request): View
    {
        $selectedKanca = $this->normalizeFilter($request->input('kanca'));
        $selectedUnit = $this->normalizeFilter($request->input('unit_kerja'));
        $selectedCategory = $request->input('category', 'simpanan'); // Default to simpanan
        $monthOptions = $this->timeseriesMonthOptions();
        $selectedMonth = $this->resolveTimeseriesMonth($request->input('period_month'), $monthOptions);

        // Default to Area 6 (Madiun, Ngawi, Magetan, Ponorogo) if no kanca selected
        if (!$selectedKanca && !$selectedUnit) {
            $selectedKanca = ['KC Madiun', 'KC Ngawi', 'KC Magetan', 'KC Ponorogo'];
        }

        $filters = $this->dashboardHarianSnapshotService->fetchFilterOptions(null, $selectedKanca, $selectedUnit);
        $filters['period_month'] = $monthOptions;

        $dashboardPage = [
            'routes' => [
                'data' => route('dashboard.harian.timeseries.data'),
            ],
            'filters' => $filters,
            'selected' => [
                'kanca' => $selectedKanca ?? [],
                'unit_kerja' => $selectedUnit ?? 'all',
                'category' => $selectedCategory,
                'period_month' => $selectedMonth,
            ],
            'initialData' => $this->timeseriesPayload($selectedCategory, $selectedKanca, $selectedUnit, $selectedMonth),
        ];

        return view('report.dashboard-harian-timeseries', compact('dashboardPage'));
    }

    public function timeseriesData(Request $request): JsonResponse
    {
        $selectedKanca = $this->normalizeFilter($request->input('kanca'));
        $selectedUnit = $this->normalizeFilter($request->input('unit_kerja'));
        $category = $request->input('category', 'simpanan');
        $monthOptions = $this->timeseriesMonthOptions();
        $selectedMonth = $this->resolveTimeseriesMonth($request->input('period_month'), $monthOptions);

        // Default to Area 6 if nothing selected
        if (!$selectedKanca && !$selectedUnit) {
            $selectedKanca = ['KC Madiun', 'KC Ngawi', 'KC Magetan', 'KC Ponorogo'];
        }

        return response()->json($this->timeseriesPayload($category, $selectedKanca, $selectedUnit, $selectedMonth));
    }

    public function data(Request $request): JsonResponse
    {
        [$selectedKanca, $selectedUnit] = $this->resolveKeragaanFilters($request);
        $selectedPeriod = $this->dashboardHarianSnapshotService->resolveEffectivePeriod($request->input('posisi_terakhir'));
        $selectedRka = $this->dashboardHarianSnapshotService->resolveEffectiveRkaPeriod($request->input('posisi_rka'), $selectedPeriod);

        return response()->json(
            $this->payload($selectedPeriod, $selectedRka, $selectedKanca, $selectedUnit)
            + ['available_filters' => $this->dashboardHarianSnapshotService->fetchFilterOptions($selectedPeriod, $selectedKanca, $selectedUnit)]
        );
    }

    public function exportExcel(Request $request)
    {
        @set_time_limit(0);

        [$selectedKanca, $selectedUnit] = $this->resolveKeragaanFilters($request);
        $selectedPeriod = $this->dashboardHarianSnapshotService->resolveEffectivePeriod($request->input('posisi_terakhir'));
        $selectedRka = $this->dashboardHarianSnapshotService->resolveEffectiveRkaPeriod($request->input('posisi_rka'), $selectedPeriod);

        abort_if(!$selectedPeriod, 422, 'Periode Dashboard Harian belum tersedia.');

        $payload = $this->dashboardHarianSnapshotService->buildDashboardPayload(
            $selectedPeriod,
            $selectedRka,
            $selectedKanca,
            $selectedUnit
        );

        $filename = sprintf(
            'dashboard-harian_%s_%s.xlsx',
            str_replace('-', '', (string) $selectedPeriod),
            $this->sanitizeExportToken($payload['summary']['scope_label'] ?? $payload['summary']['kanca_label'] ?? 'area-6')
        );

        return response()->streamDownload(function () use ($payload) {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Dashboard Harian');

            $this->fillDashboardHarianSheet($sheet, $payload);

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    private function payload(?string $selectedPeriod, ?string $selectedRka, array|string|null $selectedKanca, array|string|null $selectedUnit): array
    {
        $cacheKey = 'dashboard_harian:payload:' . md5(json_encode([
            'schema' => 'penc-pct-v9-rka-unit-scope-cache',
            'version' => (int) Cache::get('report_cache_version:global', 1),
            'period' => $selectedPeriod,
            'rka' => $selectedRka,
            'kanca' => $selectedKanca,
            'unit' => $selectedUnit,
        ]));

        return $this->rememberDashboardPayload($cacheKey, function () use ($selectedPeriod, $selectedRka, $selectedKanca, $selectedUnit) {
            return $this->dashboardHarianSnapshotService->buildDashboardPayload(
                $selectedPeriod,
                $selectedRka,
                $selectedKanca,
                $selectedUnit
            );
        });
    }

    private function timeseriesPayload(string $category, array|string|null $selectedKanca, array|string|null $selectedUnit, ?string $selectedMonth = null): array
    {
        $monthOptions = $this->timeseriesMonthOptions();
        $resolvedMonth = $this->resolveTimeseriesMonth($selectedMonth, $monthOptions);

        $cacheKey = 'dashboard_harian:timeseries:' . md5(json_encode([
            'version' => (int) Cache::get('report_cache_version:global', 1),
            'category' => $category,
            'kanca' => $selectedKanca,
            'unit' => $selectedUnit,
            'period_month' => $resolvedMonth,
        ]));

        return $this->rememberDashboardPayload($cacheKey, function () use ($category, $selectedKanca, $selectedUnit, $monthOptions, $resolvedMonth) {
            $emptyPayload = [
                'months' => [],
                'series' => [],
                'labels' => range(1, 31),
                'area_total' => [],
                'source' => DashboardHarianSnapshotService::SNAPSHOT_TABLE,
                'selected_month' => $resolvedMonth,
                'available_months' => $monthOptions,
            ];

            $months = $this->timeseriesWindowMonths($resolvedMonth, $monthOptions);
            if ($months === []) {
                return $emptyPayload;
            }

            $data = $this->dashboardHarianSnapshotService->fetchTimeseriesTrend(
                $months,
                $category,
                $selectedKanca,
                $selectedUnit
            );

            return [
                'months' => $months,
                'series' => $data['series'],
                'labels' => range(1, 31),
                'area_total' => $data['area_total'],
                'source' => DashboardHarianSnapshotService::SNAPSHOT_TABLE,
                'selected_month' => $resolvedMonth,
                'available_months' => $monthOptions,
            ];
        });
    }

    private function rememberDashboardPayload(string $cacheKey, callable $callback): array
    {
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        // Lock TTL must comfortably exceed the worst-case build time.
        // Block timeout of 10 s gives concurrent requests a real chance to be
        // served from cache once the first request finishes building the payload,
        // instead of all of them falling back to a "warming" response.
        $lock = Cache::lock($cacheKey . ':lock', 90);

        try {
            return $lock->block(10, function () use ($cacheKey, $callback): array {
                // Double-check inside lock: a parallel request may have already built it.
                $cached = Cache::get($cacheKey);
                if (is_array($cached)) {
                    return $cached;
                }

                $payload = $callback();
                Cache::put($cacheKey, $payload, now()->addMinutes(15));

                return $payload;
            });
        } catch (LockTimeoutException) {
            // Serve whatever is in cache; if still empty, return warming signal.
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }

            return [
                'status' => 'warming',
                'message' => 'Dashboard sedang menyiapkan cache terbaru. Silakan muat ulang beberapa saat lagi.',
            ];
        }
    }

    private function timeseriesMonthOptions(): array
    {
        return $this->dashboardHarianSnapshotService->fetchPeriods()
            ->map(fn ($period) => substr((string) $period, 0, 7))
            ->unique()
            ->values()
            ->map(fn (string $month) => [
                'value' => $month,
                'label' => $this->formatTimeseriesMonthLabel($month),
            ])
            ->all();
    }

    private function resolveTimeseriesMonth(mixed $requestedMonth, array $monthOptions): ?string
    {
        $availableMonths = collect($monthOptions)
            ->pluck('value')
            ->map(fn ($value) => (string) $value)
            ->all();

        if ($availableMonths === []) {
            return null;
        }

        $requested = trim((string) $requestedMonth);
        if (preg_match('/^\d{4}-\d{2}$/', $requested) === 1 && in_array($requested, $availableMonths, true)) {
            return $requested;
        }

        return $availableMonths[0];
    }

    private function timeseriesWindowMonths(?string $selectedMonth, array $monthOptions): array
    {
        if ($selectedMonth === null) {
            return [];
        }

        $availableMonths = collect($monthOptions)
            ->pluck('value')
            ->map(fn ($value) => (string) $value)
            ->filter(fn (string $month) => $month <= $selectedMonth)
            ->take(4)
            ->values()
            ->reverse()
            ->values()
            ->all();

        return $availableMonths;
    }

    private function formatTimeseriesMonthLabel(string $month): string
    {
        try {
            return \Carbon\Carbon::createFromFormat('Y-m', $month)->translatedFormat('F Y');
        } catch (\Throwable) {
            return $month;
        }
    }

    private function fillDashboardHarianSheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, array $payload): void
    {
        $summary = $payload['summary'] ?? [];
        $periods = $payload['comparison_periods'] ?? [];
        $headers = $this->dashboardHarianExportHeaders($payload);
        $lastColumn = Coordinate::stringFromColumnIndex(count($headers));

        $sheet->setCellValue('A1', 'DASHBOARD KERAGAAN HARIAN');
        $sheet->mergeCells("A1:{$lastColumn}1");
        $sheet->setCellValue('A2', 'Kanca');
        $sheet->setCellValue('B2', $summary['kanca_label'] ?? 'Area 6');
        $sheet->setCellValue('D2', 'Unit Kerja');
        $sheet->setCellValue('E2', $summary['unit_label'] ?? 'Semua Unit Kerja');
        $sheet->setCellValue('A3', 'Posisi Terakhir');
        $sheet->setCellValue('B3', $payload['selected_period_label'] ?? $this->formatExportDate($payload['selected_period'] ?? null));
        $sheet->setCellValue('D3', 'Posisi RKA');
        $sheet->setCellValue('E3', $this->formatExportMonth($periods['rka']['period'] ?? $payload['selected_rka_period'] ?? null));
        $sheet->setCellValue('A4', 'Sumber');
        $sheet->setCellValue('B4', $summary['source'] ?? $payload['source'] ?? 'source_fallback');
        $sheet->setCellValue('D4', 'Satuan');
        $sheet->setCellValue('E4', 'Nominal dalam Rp Juta, persentase dalam angka %');

        $headerRow = 6;
        foreach ($headers as $index => $header) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($index + 1) . $headerRow, $header);
        }

        $numberedKeys = [
            'total_simpanan' => '1',
            'total_os' => '2',
            'total_sml_pct_non_commercial' => '3',
            'total_npl_pct_non_commercial' => '4',
            'casa_pct' => '5',
            'ldr_non_commercial' => '6',
            'rec_dh_total' => '7',
        ];

        $rowIndex = $headerRow + 1;
        foreach (($payload['rows'] ?? []) as $row) {
            $values = $row['values'] ?? [];
            $deltas = $row['deltas'] ?? [];
            $rkaComparison = $this->dashboardHarianRkaComparison($row);
            $type = $row['type'] ?? 'currency';

            $rowValues = [
                $numberedKeys[$row['key'] ?? ''] ?? '',
                $row['label'] ?? '',
                $this->dashboardHarianExportValue($values['yoy'] ?? 0, $type),
                $this->dashboardHarianExportValue($values['ytd'] ?? 0, $type),
                $this->dashboardHarianExportValue($values['m2'] ?? 0, $type),
                $this->dashboardHarianExportValue($values['mtm'] ?? 0, $type),
                $this->dashboardHarianExportValue($values['mtd'] ?? 0, $type),
                $this->dashboardHarianExportValue($values['h1'] ?? 0, $type),
                $this->dashboardHarianExportValue($values['current'] ?? 0, $type),
                $this->dashboardHarianExportValue($deltas['yoy'] ?? 0, $type),
                $this->dashboardHarianExportValue($deltas['ytd'] ?? 0, $type),
                $this->dashboardHarianExportValue($deltas['mtd'] ?? 0, $type),
                $this->dashboardHarianExportValue($deltas['dtd'] ?? 0, $type),
                $this->dashboardHarianExportValue($values['rka'] ?? 0, $type),
                $this->dashboardHarianExportValue($rkaComparison['rka']['delta'], $type),
                $rkaComparison['rka']['achievement'],
                $this->dashboardHarianExportValue($values['rka_dec'] ?? 0, $type),
                $this->dashboardHarianExportValue($rkaComparison['rka_dec']['delta'], $type),
                $rkaComparison['rka_dec']['achievement'],
            ];

            foreach ($rowValues as $columnIndex => $value) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($columnIndex + 1) . $rowIndex, $value);
            }

            $numberFormat = ($row['type'] ?? 'currency') === 'percent' ? '#,##0.00' : '#,##0';
            $sheet->getStyle("C{$rowIndex}:S{$rowIndex}")->getNumberFormat()->setFormatCode($numberFormat);
            $rowIndex++;
        }

        $lastRow = max($rowIndex - 1, $headerRow);
        $sheet->freezePane('C7');
        $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '004685']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getStyle("A{$headerRow}:{$lastColumn}{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '00529C']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getStyle("A{$headerRow}:{$lastColumn}{$lastRow}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D9E2EC']]],
        ]);
        $sheet->getStyle("C7:{$lastColumn}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle("A7:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("P7:P{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("S7:S{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
        $this->applyDashboardHarianExportConditionalFormatting($sheet, $lastRow);

        foreach (range(1, count($headers)) as $columnIndex) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($columnIndex))->setAutoSize(true);
        }
    }

    private function applyDashboardHarianExportConditionalFormatting(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $lastRow): void
    {
        if ($lastRow < 7) {
            return;
        }

        $downStyle = $this->dashboardHarianConditionalStyle('B91C1C', 'FEE2E2');
        $upStyle = $this->dashboardHarianConditionalStyle('15803D', 'DCFCE7');

        foreach (['J7:M' . $lastRow, 'O7:O' . $lastRow, 'R7:R' . $lastRow] as $range) {
            $sheet->getStyle($range)->setConditionalStyles([
                $downStyle(Conditional::OPERATOR_LESSTHAN, '0'),
                $upStyle(Conditional::OPERATOR_GREATERTHAN, '0'),
            ]);
        }

        foreach (['P7:P' . $lastRow, 'S7:S' . $lastRow] as $range) {
            $sheet->getStyle($range)->setConditionalStyles([
                $downStyle(Conditional::OPERATOR_LESSTHAN, '100'),
                $upStyle(Conditional::OPERATOR_GREATERTHANOREQUAL, '100'),
            ]);
        }
    }

    private function dashboardHarianConditionalStyle(string $fontColor, string $fillColor): \Closure
    {
        return static function (string $operator, string $condition) use ($fontColor, $fillColor): Conditional {
            $conditional = new Conditional();
            $conditional->setConditionType(Conditional::CONDITION_CELLIS);
            $conditional->setOperatorType($operator);
            $conditional->addCondition($condition);
            $conditional->getStyle()->getFont()->setBold(true);
            $conditional->getStyle()->getFont()->getColor()->setARGB('FF' . $fontColor);
            $conditional->getStyle()->getFill()->setFillType(Fill::FILL_SOLID);
            $conditional->getStyle()->getFill()->getStartColor()->setARGB('FF' . $fillColor);

            return $conditional;
        };
    }

    private function dashboardHarianExportHeaders(array $payload): array
    {
        $periods = $payload['comparison_periods'] ?? [];

        return [
            'No',
            'Keterangan',
            $this->formatExportDate($periods['yoy']['period'] ?? null) . ' (YoY)',
            $this->formatExportDate($periods['ytd']['period'] ?? null) . ' (YtD)',
            $this->formatExportDate($periods['m2']['period'] ?? null) . ' (M-2)',
            $this->formatExportDate($periods['mtm']['period'] ?? null) . ' (MtM)',
            $this->formatExportDate($periods['mtd']['period'] ?? null) . ' (MtD)',
            $this->formatExportDate($periods['h1']['period'] ?? null) . ' (DtD)',
            $this->formatExportDate($payload['selected_period'] ?? null) . ' (Posisi)',
            'Delta YoY',
            'Delta YtD',
            'Delta MtD',
            'Delta DtD',
            'RKA ' . $this->formatExportMonth($periods['rka']['period'] ?? null),
            'Delta RKA',
            'Penc RKA (%)',
            'RKA ' . $this->formatExportMonth($periods['rka_dec']['period'] ?? null),
            'Delta RKA Des',
            'Penc RKA Des (%)',
        ];
    }

    private function dashboardHarianExportValue(mixed $value, string $type): float
    {
        $numeric = (float) $value;

        return $type === 'percent' ? $numeric : $numeric / 1000000;
    }

    private function dashboardHarianRkaComparison(array $row): array
    {
        $currentValue = (float) ($row['values']['current'] ?? 0);
        $reverse = $this->isDashboardHarianQualityTarget($row['key'] ?? '');
        $compare = function (float $targetValue) use ($currentValue, $reverse): array {
            $delta = $reverse ? ($targetValue - $currentValue) : ($currentValue - $targetValue);
            $achievement = 0.0;

            if ($reverse) {
                $achievement = $currentValue <= 0 ? 100.0 : ($targetValue / $currentValue) * 100;
            } elseif ($targetValue > 0) {
                $achievement = ($currentValue / $targetValue) * 100;
            }

            return [
                'delta' => is_finite($delta) ? $delta : 0.0,
                'achievement' => is_finite($achievement) ? $achievement : 0.0,
            ];
        };

        return [
            'rka' => $compare((float) ($row['values']['rka'] ?? 0)),
            'rka_dec' => $compare((float) ($row['values']['rka_dec'] ?? 0)),
        ];
    }

    private function isDashboardHarianQualityTarget(string $rowKey): bool
    {
        return str_contains($rowKey, '_sml')
            || str_contains($rowKey, '_npl')
            || str_starts_with($rowKey, 'total_sml_')
            || str_starts_with($rowKey, 'total_npl_');
    }

    private function formatExportDate(mixed $value): string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return '-';
        }

        try {
            return \Carbon\Carbon::parse(substr($raw, 0, 10))->translatedFormat('d M y');
        } catch (\Throwable) {
            return $raw;
        }
    }

    private function formatExportMonth(mixed $value): string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return '-';
        }

        try {
            return \Carbon\Carbon::parse(substr($raw, 0, 7) . '-01')->translatedFormat('M Y');
        } catch (\Throwable) {
            return $raw;
        }
    }

    private function sanitizeExportToken(string $value): string
    {
        $sanitized = preg_replace('/[^A-Za-z0-9]+/', '-', trim($value)) ?? 'export';
        $sanitized = trim($sanitized, '-');

        return $sanitized !== '' ? strtolower($sanitized) : 'export';
    }

    private function normalizeFilter($value): array|string|null
    {
        if (is_array($value)) {
            $normalized = collect($value)
                ->map(fn ($item) => trim((string) $item))
                ->filter(fn ($item) => $item !== '' && $item !== 'all')
                ->unique()
                ->values()
                ->all();

            return $normalized === [] ? null : $normalized;
        }

        $normalized = trim((string) $value);
        if ($normalized === '' || $normalized === 'all') {
            return null;
        }

        return $normalized;
    }

    private function defaultArea6KancaWhenAll(array|string|null $selectedKanca, array|string|null $selectedUnit): array|string|null
    {
        if ($selectedKanca !== null || $selectedUnit !== null) {
            return $selectedKanca;
        }

        return self::AREA_6_KANCA;
    }

    private function resolveKeragaanFilters(Request $request): array
    {
        $selectedKanca = $this->normalizeFilter($request->input('kanca'));
        $selectedUnit = $this->normalizeFilter($request->input('unit_kerja'));

        if ($selectedKanca === null) {
            $selectedUnit = null;
        }

        $selectedKanca = $this->defaultArea6KancaWhenAll($selectedKanca, $selectedUnit);

        if ($this->isArea6KancaScope($selectedKanca)) {
            $selectedUnit = null;
        }

        return [$selectedKanca, $selectedUnit];
    }

    private function isArea6KancaScope(array|string|null $selectedKanca): bool
    {
        if ($selectedKanca === null) {
            return true;
        }

        $values = is_array($selectedKanca) ? $selectedKanca : [$selectedKanca];
        $normalized = collect($values)
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        $area6 = collect(self::AREA_6_KANCA)
            ->sort()
            ->values()
            ->all();

        return $normalized === $area6;
    }
}
