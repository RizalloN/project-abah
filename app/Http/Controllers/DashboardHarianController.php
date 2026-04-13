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
        $selectedPeriod = $this->dashboardHarianSnapshotService->resolveEffectivePeriod($request->input('posisi_terakhir'));
        $selectedRka = $this->dashboardHarianSnapshotService->resolveEffectivePeriod($request->input('posisi_rka') ?: $selectedPeriod);
        $selectedKanca = $this->normalizeFilter($request->input('kanca'));
        $selectedUnit = $this->normalizeFilter($request->input('unit_kerja'));

        $dashboardPage = [
            'routes' => [
                'data' => route('dashboard.harian.data'),
            ],
            'filters' => $this->dashboardHarianSnapshotService->fetchFilterOptions($selectedPeriod),
            'selected' => [
                'kanca' => $selectedKanca ?? 'all',
                'unit_kerja' => $selectedUnit ?? 'all',
                'posisi_terakhir' => $selectedPeriod,
                'posisi_rka' => $selectedRka,
            ],
            'initialData' => null,
        ];

        return view('report.dashboard-harian', compact('dashboardPage'));
    }

    public function data(Request $request): JsonResponse
    {
        $selectedPeriod = $this->dashboardHarianSnapshotService->resolveEffectivePeriod($request->input('posisi_terakhir'));
        $selectedRka = $this->dashboardHarianSnapshotService->resolveEffectivePeriod($request->input('posisi_rka') ?: $selectedPeriod);
        $selectedKanca = $this->normalizeFilter($request->input('kanca'));
        $selectedUnit = $this->normalizeFilter($request->input('unit_kerja'));

        return response()->json(
            $this->payload($selectedPeriod, $selectedRka, $selectedKanca, $selectedUnit)
            + ['available_filters' => $this->dashboardHarianSnapshotService->fetchFilterOptions($selectedPeriod)]
        );
    }

    private function payload(?string $selectedPeriod, ?string $selectedRka, ?string $selectedKanca, ?string $selectedUnit): array
    {
        $cacheKey = 'dashboard_harian:payload:' . md5(json_encode([
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

    private function normalizeFilter($value): ?string
    {
        $normalized = trim((string) $value);

        if ($normalized === '' || $normalized === 'all') {
            return null;
        }

        return $normalized;
    }
}
