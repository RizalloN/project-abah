<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Services\Import\SuspectReaktivasiSourceService;
use App\Support\UserBranchScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SuspectReaktivasiReportController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $this->releaseSessionLockIfNeeded();
        $branches = collect(UserBranchScope::branchScopes())->pluck('label')->all();
        $scope = UserBranchScope::current();
        $request->validate([
            'periode' => ['nullable', 'date_format:Y-m-d'],
            'cabang' => ['nullable', 'string', Rule::in($branches)],
            'nama_uker' => ['nullable', 'string', 'max:180'],
            'reaktivasi' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);
        $branch = $scope['label'] ?? ($request->input('cabang') ?: null);
        $unit = trim((string) $request->input('nama_uker', ''));
        $frequency = $request->filled('reaktivasi') ? (int) $request->input('reaktivasi') : null;
        $ready = Schema::hasTable(SuspectReaktivasiSourceService::TABLE);
        $base = $ready ? DB::table(SuspectReaktivasiSourceService::TABLE)->whereIn('cabang', $branches)
            ->when($scope !== null, fn ($query) => $query->where('cabang', $scope['label'])) : null;
        $periods = $base ? (clone $base)->select('periode')->distinct()->orderByDesc('periode')->pluck('periode') : collect();
        $period = $request->input('periode') ?: $periods->first();
        $query = $base ? (clone $base)->where('periode', $period)->when($branch, fn ($q) => $q->where('cabang', $branch)) : null;
        $units = $query ? (clone $query)->select('nama_uker')->distinct()->orderBy('nama_uker')->pluck('nama_uker') : collect();
        if ($unit !== '' && ! $units->contains($unit)) {
            abort(422, 'Nama uker tidak tersedia untuk cabang dan periode yang dipilih.');
        }
        $query?->when($unit !== '', fn ($q) => $q->where('nama_uker', $unit));
        $sourceCategories = $query ? (clone $query)->selectRaw('keterangan, COUNT(*) as accounts')->groupBy('keterangan')->get() : collect();
        $frequencyLabels = [];
        $frequencyCounts = [];
        foreach ($sourceCategories as $category) {
            if (preg_match('/^\s*(\d+)\s*x\s+reaktivasi\s*$/iu', $category->keterangan, $matches)) {
                $number = (int) $matches[1];
                $frequencyLabels[$number][] = $category->keterangan;
                $frequencyCounts[$number] = ($frequencyCounts[$number] ?? 0) + (int) $category->accounts;
            }
        }
        if ($frequency !== null) {
            $frequencyCounts[$frequency] ??= 0;
            $query?->whereIn('keterangan', $frequencyLabels[$frequency] ?? []);
        }
        ksort($frequencyCounts, SORT_NUMERIC);
        $frequencies = collect($frequencyCounts)->map(fn ($count, $number) => [
            'value' => $number, 'label' => $number.' kali reaktivasi', 'count' => $count,
        ])->values();
        $summary = $query ? (clone $query)->selectRaw('COUNT(*) as accounts, COALESCE(SUM(outstanding), 0) as outstanding')
            ->selectRaw('SUM(CASE WHEN flag_restruk = ? THEN 1 ELSE 0 END) as restruk', ['Restruk'])->first()
            : (object) ['accounts' => 0, 'outstanding' => 0, 'restruk' => 0];
        $distribution = $query ? (clone $query)->selectRaw('cabang, COUNT(*) as accounts')->groupBy('cabang')->orderBy('cabang')->get() : collect();
        $categories = $query ? (clone $query)->selectRaw('keterangan, COUNT(*) as accounts')->groupBy('keterangan')->orderByDesc('accounts')->get() : collect();
        $rows = $query ? (clone $query)->orderBy('cabang')->orderBy('nama_uker')->orderByDesc('outstanding')->orderBy('norek')->paginate(25)->withQueryString() : null;

        $data = compact('branches', 'scope', 'periods', 'period', 'branch', 'unit', 'units', 'frequency', 'frequencies', 'summary', 'distribution', 'categories', 'rows', 'ready');
        if ($request->wantsJson()) {
            return response()->json([
                'html' => view('report.dashboard-pinjaman.suspect-reaktivasi-results', $data)->render(),
                'units' => $units->values(),
                'frequencies' => $frequencies,
                'filters' => ['periode' => $period, 'cabang' => $branch ?? '', 'nama_uker' => $unit, 'reaktivasi' => $frequency],
                'accounts' => (int) $summary->accounts,
            ]);
        }

        return view('report.dashboard-pinjaman.suspect-reaktivasi', $data);
    }
}
