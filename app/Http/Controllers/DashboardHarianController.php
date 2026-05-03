<?php

namespace App\Http\Controllers;

use App\Support\DashboardHarianSnapshotService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DashboardHarianController extends Controller
{
    private const AREA_6_KANCA = ['KC Madiun', 'KC Magetan', 'KC Ponorogo', 'KC Ngawi'];

    public function __construct(
        private readonly DashboardHarianSnapshotService $dashboardHarianSnapshotService
    ) {
    }

    public function index(Request $request): View
    {
        $selectedKanca = $this->normalizeFilter($request->input('kanca'));
        $selectedUnit = $this->normalizeFilter($request->input('unit_kerja'));
        $selectedKanca = $this->defaultArea6KancaWhenAll($selectedKanca, $selectedUnit);
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
        $selectedKanca = $this->normalizeFilter($request->input('kanca'));
        $selectedUnit = $this->normalizeFilter($request->input('unit_kerja'));
        $selectedKanca = $this->defaultArea6KancaWhenAll($selectedKanca, $selectedUnit);
        $selectedPeriod = $this->dashboardHarianSnapshotService->resolveEffectivePeriod($request->input('posisi_terakhir'));
        $selectedRka = $this->dashboardHarianSnapshotService->resolveEffectiveRkaPeriod($request->input('posisi_rka'), $selectedPeriod);

        return response()->json(
            $this->payload($selectedPeriod, $selectedRka, $selectedKanca, $selectedUnit)
            + ['available_filters' => $this->dashboardHarianSnapshotService->fetchFilterOptions($selectedPeriod, $selectedKanca, $selectedUnit)]
        );
    }

    private function payload(?string $selectedPeriod, ?string $selectedRka, array|string|null $selectedKanca, array|string|null $selectedUnit): array
    {
        $cacheKey = 'dashboard_harian:payload:' . md5(json_encode([
            'schema' => 'penc-pct-v8-rka-micro-loan-cognos-recovery-area6-monthly-ph-normalized-acct',
            'version' => (int) Cache::get('report_cache_version:global', 1),
            'period' => $selectedPeriod,
            'rka' => $selectedRka,
            'kanca' => $selectedKanca,
            'unit' => $selectedUnit,
        ]));

        return Cache::remember($cacheKey, now()->addMinutes(3), function () use ($selectedPeriod, $selectedRka, $selectedKanca, $selectedUnit) {
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

        return Cache::remember($cacheKey, now()->addMinutes(3), function () use ($category, $selectedKanca, $selectedUnit, $monthOptions, $resolvedMonth) {
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
}
