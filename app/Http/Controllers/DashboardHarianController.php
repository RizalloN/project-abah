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
