<?php

namespace App\Http\Controllers;

use App\Support\DashboardHarianSnapshotService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DashboardHarianController extends Controller
{
    public function __construct(
        private readonly DashboardHarianSnapshotService $dashboardHarianSnapshotService
    ) {
    }

    public function index(Request $request): View
    {
        $selectedKanca = $this->normalizeFilter($request->input('kanca'));
        $selectedUnit = $this->normalizeFilter($request->input('unit_kerja'));
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

        // Default to Area 6 (Madiun, Ngawi, Magetan, Ponorogo) if no kanca selected
        if (!$selectedKanca && !$selectedUnit) {
            $selectedKanca = ['KC Madiun', 'KC Ngawi', 'KC Magetan', 'KC Ponorogo'];
        }

        $filters = $this->dashboardHarianSnapshotService->fetchFilterOptions(null, $selectedKanca, $selectedUnit);

        $dashboardPage = [
            'routes' => [
                'data' => route('dashboard.harian.timeseries.data'),
            ],
            'filters' => $filters,
            'selected' => [
                'kanca' => $selectedKanca ?? [],
                'unit_kerja' => $selectedUnit ?? 'all',
                'category' => $selectedCategory,
            ],
        ];

        return view('report.dashboard-harian-timeseries', compact('dashboardPage'));
    }

    public function timeseriesData(Request $request): JsonResponse
    {
        $selectedKanca = $this->normalizeFilter($request->input('kanca'));
        $selectedUnit = $this->normalizeFilter($request->input('unit_kerja'));
        $category = $request->input('category', 'simpanan');

        // Default to Area 6 if nothing selected
        if (!$selectedKanca && !$selectedUnit) {
            $selectedKanca = ['KC Madiun', 'KC Ngawi', 'KC Magetan', 'KC Ponorogo'];
        }

        // Fetch last 4 months available in snapshots
        $periods = $this->dashboardHarianSnapshotService->fetchPeriods()->take(120);

        if ($periods->isEmpty()) {
            return response()->json([
                'months' => [],
                'series' => [],
                'labels' => range(1, 31),
                'area_total' => []
            ]);
        }

        // Group by month and sort chronologically (oldest to newest)
        $months = $periods->map(fn($p) => substr($p, 0, 7))->unique()->take(4)->values()->reverse()->values();

        if ($months->isEmpty()) {
            return response()->json([
                'months' => [],
                'series' => [],
                'labels' => range(1, 31),
                'area_total' => []
            ]);
        }

        $data = $this->dashboardHarianSnapshotService->fetchTimeseriesTrend(
            $months->toArray(),
            $category,
            $selectedKanca,
            $selectedUnit
        );

        return response()->json([
            'months' => $months->toArray(),
            'series' => $data['series'],
            'labels' => range(1, 31),
            'area_total' => $data['area_total']
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $selectedKanca = $this->normalizeFilter($request->input('kanca'));
        $selectedUnit = $this->normalizeFilter($request->input('unit_kerja'));
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
            'schema' => 'penc-pct-v2',
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
}
