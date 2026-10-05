<?php

namespace App\Http\Controllers;

use App\Support\DashboardHarianSnapshotService;
use App\Support\KeragaanPdfGeography;
use App\Support\UserBranchScope;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KeragaanPdfController extends Controller
{
    public const BRANCHES = ['KC Madiun', 'KC Magetan', 'KC Ngawi', 'KC Ponorogo'];

    public function __invoke(Request $request, DashboardHarianSnapshotService $snapshots, KeragaanPdfGeography $geography): View
    {
        $this->releaseSessionLockIfNeeded();
        $scope = UserBranchScope::forUser($request->user());
        if ($scope !== null) {
            $request->merge(['kanca' => $scope['label']]);
        }
        $input = $request->validate([
            'kanca' => ['required', 'string', Rule::in([...self::BRANCHES, 'area6', 'all'])],
            'posisi_terakhir' => ['nullable', 'date_format:Y-m-d'],
            'posisi_rka' => ['nullable', 'date_format:Y-m'],
        ]);
        $period = $input['posisi_terakhir'] ?? $snapshots->resolveEffectivePeriod(null);
        abort_unless($period, 422, 'Periode Dashboard Harian belum tersedia.');
        $areaScope = in_array($input['kanca'], ['area6', 'all'], true);
        $branches = $areaScope ? self::BRANCHES : [$input['kanca']];
        $report = $snapshots->buildKeragaanPdfPayload($period, $input['posisi_rka'] ?? substr($period, 0, 7), $branches, $areaScope);
        $report['scope_label'] = $areaScope ? 'Area 6 Madiun' : $branches[0];
        $report['area_scope'] = $areaScope;
        $report['geography'] = $geography->build($branches, $report['active_offices']);
        $report['logos'] = [];
        foreach (['danantara', 'bri'] as $brand) {
            $report['logos'][$brand] = 'data:image/png;base64,'.base64_encode(file_get_contents(public_path('images/'.$brand.'-logo-template.png')));
        }

        return view('report.dashboard-harian-keragaan-pdf', compact('report'));
    }
}
