<?php

namespace App\Http\Controllers;

use App\Support\DashboardHarianSnapshotService;
use App\Support\ReportCacheVersion;
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
        // Custom MtM is a page-local easter egg; reloads must always return to default MtM.
        $selectedMtm = null;
        $baseUrl = rtrim($request->getSchemeAndHttpHost() . $request->getBaseUrl(), '/');
        $dataUrl = $baseUrl . '/dashboard-harian/data';
        $filters = $this->dashboardHarianSnapshotService->fetchFilterOptions($selectedPeriod, $selectedKanca, $selectedUnit);
        $filters['mtm_period'] = $filters['posisi_terakhir'] ?? [];
        $initialData = null;

        if ($selectedPeriod) {
            $initialData = $this->payload($selectedPeriod, $selectedRka, $selectedKanca, $selectedUnit, $selectedMtm)
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
                'mtm_period' => $selectedMtm,
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
        $selectedSegment = $request->input('segment', 'total'); // Default to total
        if ($selectedCategory === 'recovery' && !in_array($selectedSegment, ['ritel', 'micro'], true)) {
            $selectedSegment = 'ritel';
        }
        $selectedRecoverySegment = trim((string) $request->input('recovery_segment', ''));
        $selectedRecoveryProduct = trim((string) $request->input('recovery_product', ''));
        $monthOptions = $this->timeseriesMonthOptions();
        $selectedMonth = $this->resolveTimeseriesMonth($request->input('period_month'), $monthOptions);

        // Default to Area 6 (Madiun, Ngawi, Magetan, Ponorogo) if no kanca selected
        if (!$selectedKanca && !$selectedUnit) {
            $selectedKanca = ['KC Madiun', 'KC Ngawi', 'KC Magetan', 'KC Ponorogo'];
        }

        $filters = $this->dashboardHarianSnapshotService->fetchFilterOptions(null, $selectedKanca, $selectedUnit);
        $filters['period_month'] = $monthOptions;
        $filters['recovery_dimensions'] = $this->dashboardHarianSnapshotService->fetchRecoveryDimensionOptions();

        $dashboardPage = [
            'routes' => [
                'data' => route('dashboard.harian.timeseries.data'),
            ],
            'filters' => $filters,
            'selected' => [
                'kanca' => $selectedKanca ?? [],
                'unit_kerja' => $selectedUnit ?? 'all',
                'category' => $selectedCategory,
                'segment' => $selectedSegment,
                'recovery_segment' => $selectedRecoverySegment,
                'recovery_product' => $selectedRecoveryProduct,
                'period_month' => $selectedMonth,
            ],
            'initialData' => $this->timeseriesPayload(
                $selectedCategory,
                $selectedKanca,
                $selectedUnit,
                $selectedMonth,
                $selectedSegment,
                $selectedRecoverySegment,
                $selectedRecoveryProduct
            ),
        ];

        return view('report.dashboard-harian-timeseries', compact('dashboardPage'));
    }

    public function timeseriesData(Request $request): JsonResponse
    {
        $selectedKanca = $this->normalizeFilter($request->input('kanca'));
        $selectedUnit = $this->normalizeFilter($request->input('unit_kerja'));
        $category = $request->input('category', 'simpanan');
        $segment = $request->input('segment', 'total');
        if ($category === 'recovery' && !in_array($segment, ['ritel', 'micro'], true)) {
            $segment = 'ritel';
        }
        $recoverySegment = trim((string) $request->input('recovery_segment', ''));
        $recoveryProduct = trim((string) $request->input('recovery_product', ''));
        $monthOptions = $this->timeseriesMonthOptions();
        $selectedMonth = $this->resolveTimeseriesMonth($request->input('period_month'), $monthOptions);

        // Default to Area 6 if nothing selected
        if (!$selectedKanca && !$selectedUnit) {
            $selectedKanca = ['KC Madiun', 'KC Ngawi', 'KC Magetan', 'KC Ponorogo'];
        }

        return response()->json($this->timeseriesPayload(
            $category,
            $selectedKanca,
            $selectedUnit,
            $selectedMonth,
            $segment,
            $recoverySegment,
            $recoveryProduct
        ));
    }

    public function keragaanUker(Request $request): View
    {
        [$selectedKanca, $selectedUnit] = $this->resolveKeragaanUkerFilters($request);
        $selectedPeriod = $this->dashboardHarianSnapshotService->resolveEffectivePeriod($request->input('posisi_terakhir'));
        $selectedRka = $this->dashboardHarianSnapshotService->resolveEffectiveRkaPeriod($request->input('posisi_rka'), $selectedPeriod);
        $selectedData = $this->resolveKeragaanUkerDataType($request->input('data_type'));
        $filters = $this->keragaanUkerFilterOptions($selectedPeriod, $selectedKanca, $selectedUnit);

        $dashboardPage = [
            'routes' => [
                'data' => route('dashboard.harian.keragaan-uker.data'),
            ],
            'filters' => $filters,
            'selected' => [
                'kanca' => $selectedKanca ?? [],
                'unit_kerja' => $selectedUnit ?? 'all',
                'posisi_terakhir' => $selectedPeriod,
                'posisi_rka' => $selectedRka ? substr($selectedRka, 0, 7) : null,
                'data_type' => $selectedData,
            ],
            'dataTypes' => $this->keragaanUkerDataTypes(),
            'initialData' => ($selectedPeriod && $selectedKanca !== null)
                ? $this->keragaanUkerPayload($selectedPeriod, $selectedRka, $selectedKanca, $selectedUnit, $selectedData)
                    + ['available_filters' => $filters]
                : null,
        ];

        return view('report.dashboard-harian-keragaan-uker', compact('dashboardPage'));
    }

    public function keragaanUkerData(Request $request): JsonResponse
    {
        [$selectedKanca, $selectedUnit] = $this->resolveKeragaanUkerFilters($request);
        $selectedPeriod = $this->dashboardHarianSnapshotService->resolveEffectivePeriod($request->input('posisi_terakhir'));
        $selectedRka = $this->dashboardHarianSnapshotService->resolveEffectiveRkaPeriod($request->input('posisi_rka'), $selectedPeriod);
        $selectedData = $this->resolveKeragaanUkerDataType($request->input('data_type'));
        $filters = $this->keragaanUkerFilterOptions($selectedPeriod, $selectedKanca, $selectedUnit);

        if ($selectedKanca === null) {
            return response()->json([
                'summary' => [
                    'period' => $selectedPeriod,
                    'rka_period' => $selectedRka,
                    'data_type' => $selectedData,
                    'scope_label' => 'Pilih cabang',
                    'unit_label' => 'Semua Unit Kerja',
                    'value_scale' => 'million',
                ],
                'columns' => [],
                'metrics' => [],
                'rows' => [],
                'totals' => [],
                'available_filters' => $filters,
            ]);
        }

        return response()->json(
            $this->keragaanUkerPayload($selectedPeriod, $selectedRka, $selectedKanca, $selectedUnit, $selectedData)
            + ['available_filters' => $filters]
        );
    }

    public function data(Request $request): JsonResponse
    {
        [$selectedKanca, $selectedUnit] = $this->resolveKeragaanFilters($request);
        $selectedPeriod = $this->dashboardHarianSnapshotService->resolveEffectivePeriod($request->input('posisi_terakhir'));
        $selectedRka = $this->dashboardHarianSnapshotService->resolveEffectiveRkaPeriod($request->input('posisi_rka'), $selectedPeriod);
        $selectedMtm = $this->resolveOptionalMtmPeriod($request->input('mtm_period'), $selectedPeriod);
        $filters = $this->dashboardHarianSnapshotService->fetchFilterOptions($selectedPeriod, $selectedKanca, $selectedUnit);
        $filters['mtm_period'] = $filters['posisi_terakhir'] ?? [];

        return response()->json(
            $this->payload($selectedPeriod, $selectedRka, $selectedKanca, $selectedUnit, $selectedMtm)
            + ['available_filters' => $filters]
        );
    }

    public function exportExcel(Request $request)
    {
        @set_time_limit(0);

        [$selectedKanca, $selectedUnit] = $this->resolveKeragaanFilters($request);
        $selectedPeriod = $this->dashboardHarianSnapshotService->resolveEffectivePeriod($request->input('posisi_terakhir'));
        $selectedRka = $this->dashboardHarianSnapshotService->resolveEffectiveRkaPeriod($request->input('posisi_rka'), $selectedPeriod);
        $selectedMtm = $this->resolveOptionalMtmPeriod($request->input('mtm_period'), $selectedPeriod);

        abort_if(!$selectedPeriod, 422, 'Periode Dashboard Harian belum tersedia.');

        $payload = $this->dashboardHarianSnapshotService->buildDashboardPayload(
            $selectedPeriod,
            $selectedRka,
            $selectedKanca,
            $selectedUnit,
            $selectedMtm
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

    private function payload(?string $selectedPeriod, ?string $selectedRka, array|string|null $selectedKanca, array|string|null $selectedUnit, ?string $selectedMtm = null): array
    {
        $cacheKey = 'dashboard_harian:payload:' . md5(json_encode([
            'schema' => 'penc-pct-v21-rka-ldr-noncommercial-denominator-mtm-override',
            'version' => $this->reportCacheVersion(),
            'period' => $selectedPeriod,
            'rka' => $selectedRka,
            'mtm' => $selectedMtm,
            'kanca' => $selectedKanca,
            'unit' => $selectedUnit,
        ]));

        return $this->rememberDashboardPayload($cacheKey, function () use ($selectedPeriod, $selectedRka, $selectedKanca, $selectedUnit, $selectedMtm) {
            return $this->dashboardHarianSnapshotService->buildDashboardPayload(
                $selectedPeriod,
                $selectedRka,
                $selectedKanca,
                $selectedUnit,
                $selectedMtm
            );
        });
    }

    private function keragaanUkerPayload(?string $selectedPeriod, ?string $selectedRka, array|string|null $selectedKanca, array|string|null $selectedUnit, string $dataType): array
    {
        $cacheKey = 'dashboard_harian:keragaan_uker:' . md5(json_encode([
            'schema' => 'v3-uker-label-and-table-scroll-fix',
            'version' => $this->reportCacheVersion(),
            'period' => $selectedPeriod,
            'rka' => $selectedRka,
            'kanca' => $selectedKanca,
            'unit' => $selectedUnit,
            'data_type' => $dataType,
        ]));

        return $this->rememberDashboardPayload($cacheKey, function () use ($selectedPeriod, $selectedRka, $selectedKanca, $selectedUnit, $dataType) {
            return $this->dashboardHarianSnapshotService->buildKeragaanUkerPayload(
                $selectedPeriod,
                $selectedRka,
                $selectedKanca,
                $selectedUnit,
                $dataType
            );
        });
    }

    private function keragaanUkerDataTypes(): array
    {
        return [
            ['value' => 'pinjaman', 'label' => 'Pinjaman'],
            ['value' => 'simpanan', 'label' => 'Simpanan'],
            ['value' => 'recovery', 'label' => 'Recovery DH'],
        ];
    }

    private function resolveKeragaanUkerDataType(mixed $value): string
    {
        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['pinjaman', 'simpanan', 'recovery'], true)
            ? $normalized
            : 'pinjaman';
    }

    private function keragaanUkerFilterOptions(?string $selectedPeriod, array|string|null $selectedKanca, array|string|null $selectedUnit): array
    {
        $filters = $this->dashboardHarianSnapshotService->fetchFilterOptions($selectedPeriod, $selectedKanca, $selectedUnit);
        $filters['kanca'] = collect($filters['kanca'] ?? [])
            ->reject(fn (array $option): bool => (string) ($option['value'] ?? '') === 'all')
            ->values()
            ->all();

        return $filters;
    }

    private function timeseriesPayload(
        string $category,
        array|string|null $selectedKanca,
        array|string|null $selectedUnit,
        ?string $selectedMonth = null,
        string $segment = 'total',
        ?string $recoverySegment = null,
        ?string $recoveryProduct = null
    ): array
    {
        $monthOptions = $this->timeseriesMonthOptions();
        $resolvedMonth = $this->resolveTimeseriesMonth($selectedMonth, $monthOptions);

        $cacheKey = 'dashboard_harian:timeseries:' . md5(json_encode([
            'schema' => 'v7-gi405-daily-recovery-rp-million',
            'version' => $this->reportCacheVersion(),
            'category' => $category,
            'kanca' => $selectedKanca,
            'unit' => $selectedUnit,
            'period_month' => $resolvedMonth,
            'segment' => $segment,
            'recovery_segment' => $recoverySegment,
            'recovery_product' => $recoveryProduct,
        ]));

        return $this->rememberDashboardPayload($cacheKey, function () use ($category, $selectedKanca, $selectedUnit, $monthOptions, $resolvedMonth, $segment, $recoverySegment, $recoveryProduct) {
            $emptyPayload = [
                'months' => [],
                'series' => [],
                'labels' => range(1, 31),
                'area_total' => [],
                'value_type' => $category === 'sml'
                    ? 'percent'
                    : ($category === 'recovery' ? 'currency_million' : 'currency'),
                'source' => $category === 'recovery' ? 'gi405_recovery' : DashboardHarianSnapshotService::SNAPSHOT_TABLE,
                'selected_month' => $resolvedMonth,
                'available_months' => $monthOptions,
                'segment' => $segment,
                'recovery_segment' => $recoverySegment,
                'recovery_product' => $recoveryProduct,
            ];

            $months = $this->timeseriesWindowMonths($resolvedMonth, $monthOptions);
            if ($months === []) {
                return $emptyPayload;
            }

            $data = $this->dashboardHarianSnapshotService->fetchTimeseriesTrend(
                $months,
                $category,
                $selectedKanca,
                $selectedUnit,
                $segment,
                $recoverySegment,
                $recoveryProduct
            );

            return [
                'months' => $months,
                'series' => $data['series'],
                'labels' => range(1, 31),
                'area_total' => $data['area_total'],
                'value_type' => $data['value_type'] ?? ($category === 'sml'
                    ? 'percent'
                    : ($category === 'recovery' ? 'currency_million' : 'currency')),
                'source' => $category === 'recovery' ? 'gi405_recovery' : DashboardHarianSnapshotService::SNAPSHOT_TABLE,
                'selected_month' => $resolvedMonth,
                'available_months' => $monthOptions,
                'segment' => $segment,
                'recovery_segment' => $recoverySegment,
                'recovery_product' => $recoveryProduct,
            ];
        });
    }

    private function reportCacheVersion(): int
    {
        return ReportCacheVersion::composite(['harian', 'pinjaman', 'simpanan']);
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
        $hasH1 = !empty($periods['h1']['period']);
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

        $headerGroupRow = 6;
        $headerRow = 7;
        $dataStartRow = 8;
        $headerGroups = $this->dashboardHarianExportHeaderGroups($payload, $headers);
        foreach ($headerGroups as $group) {
            $startColumn = Coordinate::stringFromColumnIndex($group['start']);
            $endColumn = Coordinate::stringFromColumnIndex($group['end']);

            if ($group['start'] === $group['end']) {
                $sheet->mergeCells("{$startColumn}{$headerGroupRow}:{$startColumn}{$headerRow}");
            } else {
                $sheet->mergeCells("{$startColumn}{$headerGroupRow}:{$endColumn}{$headerGroupRow}");
            }

            $sheet->setCellValue("{$startColumn}{$headerGroupRow}", $group['label']);
        }

        foreach ($headers as $index => $header) {
            if ($index === 0) {
                continue;
            }

            $sheet->setCellValue(Coordinate::stringFromColumnIndex($index + 1) . $headerRow, $header);
        }

        $rowIndex = $dataStartRow;
        $qualityRows = [];
        $ldrRows = [];
        $blockRows = [];
        $segmentRows = [];
        foreach (($payload['rows'] ?? []) as $row) {
            $values = $row['values'] ?? [];
            $deltas = $row['deltas'] ?? [];
            $rkaComparison = $this->dashboardHarianRkaComparison($row);
            $type = $row['type'] ?? 'currency';
            $label = (string) ($row['label'] ?? '');

            $rowValues = [
                $label,
                $this->dashboardHarianExportValue($values['yoy'] ?? 0, $type),
                $this->dashboardHarianExportValue($values['ytd'] ?? 0, $type),
                $this->dashboardHarianExportValue($values['m2'] ?? 0, $type),
                $this->dashboardHarianExportValue($values['mtm'] ?? 0, $type),
                $this->dashboardHarianExportValue($values['mtd'] ?? 0, $type),
            ];

            if ($hasH1) {
                $rowValues[] = $this->dashboardHarianExportValue($values['h1'] ?? 0, $type);
            }

            $rowValues = array_merge($rowValues, [
                $this->dashboardHarianExportValue($values['current'] ?? 0, $type),
                $this->dashboardHarianExportValue($deltas['yoy'] ?? 0, $type),
                $this->dashboardHarianExportValue($deltas['ytd'] ?? 0, $type),
                $this->dashboardHarianExportValue($deltas['mtm'] ?? 0, $type),
                $this->dashboardHarianExportValue($deltas['mtd'] ?? 0, $type),
                $this->dashboardHarianExportValue($deltas['dtd'] ?? 0, $type),
                $this->dashboardHarianExportValue($values['rka'] ?? 0, $type),
                $this->dashboardHarianExportValue($rkaComparison['rka']['delta'], $type),
                $rkaComparison['rka']['achievement'],
                $this->dashboardHarianExportValue($values['rka_dec'] ?? 0, $type),
                $this->dashboardHarianExportValue($rkaComparison['rka_dec']['delta'], $type),
                $rkaComparison['rka_dec']['achievement'],
            ]);

            foreach ($rowValues as $columnIndex => $value) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($columnIndex + 1) . $rowIndex, $value);
            }

            $numberFormat = ($row['type'] ?? 'currency') === 'percent' ? '#,##0.00' : '#,##0';
            $sheet->getStyle("B{$rowIndex}:{$lastColumn}{$rowIndex}")->getNumberFormat()->setFormatCode($numberFormat);
            $rowKey = (string) ($row['key'] ?? '');
            $qualityRows[$rowIndex] = $this->isDashboardHarianQualityTarget($rowKey)
                || $this->isDashboardHarianLdrTarget($rowKey);
            $ldrRows[$rowIndex] = $this->isDashboardHarianLdrTarget($rowKey);
            $blockRows[$rowIndex] = $this->isDashboardHarianExportBlockLabel($label);
            $segmentRows[$rowIndex] = $this->isDashboardHarianExportSegmentLabel($label);
            $rowIndex++;
        }

        $lastRow = max($rowIndex - 1, $headerRow);
        $sheet->freezePane('B' . $dataStartRow);
        $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
            'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '003B70']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getStyle("A2:{$lastColumn}4")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8FBFF']],
            'font' => ['color' => ['rgb' => '334155']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle('A2:A4')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '00529C']],
        ]);
        $sheet->getStyle('D2:D4')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '00529C']],
        ]);
        $sheet->getStyle("A{$headerGroupRow}:{$lastColumn}{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);

        foreach ($headerGroups as $group) {
            $startColumn = Coordinate::stringFromColumnIndex($group['start']);
            $endColumn = Coordinate::stringFromColumnIndex($group['end']);
            $sheet->getStyle("{$startColumn}{$headerGroupRow}:{$endColumn}{$headerGroupRow}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $group['color']]],
            ]);
            $sheet->getStyle("{$startColumn}{$headerRow}:{$endColumn}{$headerRow}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $group['detail_color']]],
            ]);
        }

        $sheet->getStyle("A{$headerGroupRow}:{$lastColumn}{$lastRow}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D9E2EC']]],
        ]);
        $sheet->setAutoFilter("A{$headerRow}:{$lastColumn}{$lastRow}");
        $sheet->getRowDimension(1)->setRowHeight(26);
        $sheet->getRowDimension($headerGroupRow)->setRowHeight(28);
        $sheet->getRowDimension($headerRow)->setRowHeight(44);
        $sheet->getColumnDimension('A')->setWidth(34);

        if ($lastRow >= $dataStartRow) {
            $sheet->getStyle("A{$dataStartRow}:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("B{$dataStartRow}:{$lastColumn}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("A{$dataStartRow}:{$lastColumn}{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle("A{$dataStartRow}:A{$lastRow}")->getFont()->setBold(true);

            for ($rowNumber = $dataStartRow; $rowNumber <= $lastRow; $rowNumber++) {
                $fillColor = $rowNumber % 2 === 0 ? 'F8FBFF' : 'FFFFFF';
                $sheet->getStyle("A{$rowNumber}:{$lastColumn}{$rowNumber}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $fillColor]],
                ]);

                if ($blockRows[$rowNumber] ?? false) {
                    $sheet->getStyle("A{$rowNumber}:{$lastColumn}{$rowNumber}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F4C97']],
                    ]);
                } elseif ($segmentRows[$rowNumber] ?? false) {
                    $sheet->getStyle("A{$rowNumber}:{$lastColumn}{$rowNumber}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => '003B70']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DDEBFF']],
                    ]);
                }
            }
        }

        $sheet->getStyle("A{$headerGroupRow}:{$lastColumn}{$lastRow}")->getAlignment()->setWrapText(true);
        $this->applyDashboardHarianExportConditionalFormatting($sheet, $lastRow, $hasH1, $qualityRows, $ldrRows, $dataStartRow);

        foreach (range(1, count($headers)) as $columnIndex) {
            $column = Coordinate::stringFromColumnIndex($columnIndex);
            if ($column === 'A') {
                continue;
            }

            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }

    private function applyDashboardHarianExportConditionalFormatting(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $lastRow, bool $hasH1, array $qualityRows = [], array $ldrRows = [], int $dataStartRow = 7): void
    {
        if ($lastRow < $dataStartRow) {
            return;
        }

        $downStyle = $this->dashboardHarianConditionalStyle('B91C1C', 'FEE2E2');
        $upStyle = $this->dashboardHarianConditionalStyle('15803D', 'DCFCE7');
        $flatStyle = $this->dashboardHarianConditionalStyle('92400E', 'FEF3C7');
        $qualityBadStyle = $downStyle;
        $qualityGoodStyle = $upStyle;
        $deltaStart = $hasH1 ? 9 : 8;
        $deltaEnd = $deltaStart + 4;
        $rkaDelta = $deltaEnd + 2;
        $rkaAchievement = $deltaEnd + 3;
        $rkaDecDelta = $deltaEnd + 5;
        $rkaDecAchievement = $deltaEnd + 6;

        for ($rowNumber = $dataStartRow; $rowNumber <= $lastRow; $rowNumber++) {
            $isQualityRow = (bool) ($qualityRows[$rowNumber] ?? false);
            $conditionalStyles = $isQualityRow
                ? [
                    $qualityBadStyle(Conditional::OPERATOR_GREATERTHAN, '0'),
                    $flatStyle(Conditional::OPERATOR_EQUAL, '0'),
                    $qualityGoodStyle(Conditional::OPERATOR_LESSTHAN, '0'),
                ]
                : [
                    $downStyle(Conditional::OPERATOR_LESSTHAN, '0'),
                    $upStyle(Conditional::OPERATOR_GREATERTHAN, '0'),
                ];

            foreach ([
                Coordinate::stringFromColumnIndex($deltaStart) . $rowNumber . ':' . Coordinate::stringFromColumnIndex($deltaEnd) . $rowNumber,
                Coordinate::stringFromColumnIndex($rkaDelta) . $rowNumber . ':' . Coordinate::stringFromColumnIndex($rkaDelta) . $rowNumber,
                Coordinate::stringFromColumnIndex($rkaDecDelta) . $rowNumber . ':' . Coordinate::stringFromColumnIndex($rkaDecDelta) . $rowNumber,
            ] as $range) {
                $sheet->getStyle($range)->setConditionalStyles($conditionalStyles);
            }
        }

        for ($rowNumber = $dataStartRow; $rowNumber <= $lastRow; $rowNumber++) {
            $isLdrRow = (bool) ($ldrRows[$rowNumber] ?? false);
            $achievementStyles = $isLdrRow
                ? [
                    $downStyle(Conditional::OPERATOR_LESSTHAN, '100'),
                    $flatStyle(Conditional::OPERATOR_EQUAL, '100'),
                    $upStyle(Conditional::OPERATOR_GREATERTHAN, '100'),
                ]
                : [
                    $downStyle(Conditional::OPERATOR_LESSTHAN, '100'),
                    $upStyle(Conditional::OPERATOR_GREATERTHANOREQUAL, '100'),
                ];

            foreach ([$rkaAchievement, $rkaDecAchievement] as $achievementColumn) {
                $cell = Coordinate::stringFromColumnIndex($achievementColumn) . $rowNumber;
                $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('#,##0.00"%"');
                $sheet->getStyle($cell)->setConditionalStyles($achievementStyles);
            }
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
        $headers = [
            'Keterangan',
            $this->formatExportDate($periods['yoy']['period'] ?? null) . ' (YoY)',
            $this->formatExportDate($periods['ytd']['period'] ?? null) . ' (YtD)',
            $this->formatExportDate($periods['m2']['period'] ?? null) . ' (M-2)',
            $this->formatExportDate($periods['mtm']['period'] ?? null) . ' (MtM)',
            $this->formatExportDate($periods['mtd']['period'] ?? null) . ' (MtD)',
        ];

        if (!empty($periods['h1']['period'])) {
            $headers[] = $this->formatExportDate($periods['h1']['period']) . ' (DtD)';
        }

        return array_merge($headers, [
            $this->formatExportDate($payload['selected_period'] ?? null) . ' (Posisi)',
            'Delta YoY',
            'Delta YtD',
            'Delta MtM',
            'Delta MtD',
            'Delta DtD',
            'RKA ' . $this->formatExportMonth($periods['rka']['period'] ?? null),
            'Delta RKA',
            'Penc RKA (%)',
            'RKA ' . $this->formatExportMonth($periods['rka_dec']['period'] ?? null),
            'Delta RKA Des',
            'Penc RKA Des (%)',
        ]);
    }

    /**
     * @param array<int, string> $headers
     * @return array<int, array{label: string, start: int, end: int, color: string, detail_color: string}>
     */
    private function dashboardHarianExportHeaderGroups(array $payload, array $headers): array
    {
        $periods = $payload['comparison_periods'] ?? [];
        $hasH1 = !empty($periods['h1']['period']);
        $positionEnd = $hasH1 ? 8 : 7;
        $deltaStart = $positionEnd + 1;
        $deltaEnd = $deltaStart + 4;
        $rkaStart = $deltaEnd + 1;
        $rkaEnd = $rkaStart + 2;
        $rkaDecStart = $rkaEnd + 1;
        $lastColumn = count($headers);

        return [
            [
                'label' => 'KETERANGAN',
                'start' => 1,
                'end' => 1,
                'color' => '003B70',
                'detail_color' => '003B70',
            ],
            [
                'label' => 'POSISI',
                'start' => 2,
                'end' => $positionEnd,
                'color' => '0070C0',
                'detail_color' => '005B9F',
            ],
            [
                'label' => 'DELTA (SELISIH DIBANDING POSISI TERPILIH)',
                'start' => $deltaStart,
                'end' => $deltaEnd,
                'color' => '1E293B',
                'detail_color' => '334155',
            ],
            [
                'label' => 'RKA TERPILIH',
                'start' => $rkaStart,
                'end' => $rkaEnd,
                'color' => '00529C',
                'detail_color' => '0F4C97',
            ],
            [
                'label' => 'RKA DES TAHUN BERJALAN',
                'start' => $rkaDecStart,
                'end' => $lastColumn,
                'color' => '475569',
                'detail_color' => '64748B',
            ],
        ];
    }

    private function isDashboardHarianExportBlockLabel(string $label): bool
    {
        return preg_match('/^\s*\d+\.\s+/u', $label) === 1;
    }

    private function isDashboardHarianExportSegmentLabel(string $label): bool
    {
        return preg_match('/^\s*[A-Z]\.\s+/u', $label) === 1;
    }

    private function dashboardHarianExportValue(mixed $value, string $type): float
    {
        $numeric = (float) $value;

        return $type === 'percent' ? $numeric : $numeric / 1000000;
    }

    private function dashboardHarianRkaComparison(array $row): array
    {
        $currentValue = (float) ($row['values']['current'] ?? 0);
        $rowKey = (string) ($row['key'] ?? '');
        $reverse = $this->isDashboardHarianQualityTarget($rowKey) || $this->isDashboardHarianLdrTarget($rowKey);
        $useCurrentMinusTargetDelta = $this->isDashboardHarianLdrTarget($rowKey);
        $compare = function (float $targetValue) use ($currentValue, $reverse, $useCurrentMinusTargetDelta): array {
            $delta = $useCurrentMinusTargetDelta
                ? ($currentValue - $targetValue)
                : ($reverse ? ($targetValue - $currentValue) : ($currentValue - $targetValue));
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

    private function isDashboardHarianLdrTarget(string $rowKey): bool
    {
        return str_starts_with($rowKey, 'ldr_');
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

    private function resolveOptionalMtmPeriod(mixed $value, ?string $selectedPeriod): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '' || !$selectedPeriod) {
            return null;
        }

        try {
            $candidate = \Carbon\Carbon::parse(substr($raw, 0, 10))->toDateString();
        } catch (\Throwable) {
            return null;
        }

        $resolved = $this->dashboardHarianSnapshotService->resolveEffectivePeriod($candidate);

        return $resolved && $resolved !== $selectedPeriod ? $resolved : null;
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

    private function resolveKeragaanUkerFilters(Request $request): array
    {
        $selectedKanca = $this->normalizeFilter($request->input('kanca'));
        $selectedUnit = $this->normalizeFilter($request->input('unit_kerja'));

        if ($selectedKanca === null) {
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
