<?php

namespace App\Services\Reports;

use App\Support\UserBranchScope;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

/**
 * Service untuk laporan Kolaborasi Perusahaan Anak (Program Referral & Nasabah Prioritas).
 * Menangani build data snapshot untuk halaman kolaborasi.
 */
class KolaborasiReportService
{
    /**
     * Bangun dan kembalikan data snapshot kolaborasi.
     *
     * @param  Request  $request
     * @param  string   $sourceTable   Tabel sumber ('input_rekanan' | 'bod_boc')
     * @param  string   $viewName      Nama view Blade yang akan dirender
     * @param  string   $sourceLabel   Label sumber data untuk ditampilkan di view
     * @param  string   $pageTitle     Judul halaman
     * @return \Illuminate\View\View
     */
    public function buildReport(
        Request $request,
        string $sourceTable,
        string $viewName,
        string $sourceLabel,
        string $pageTitle
    ) {
        $statusColumn = $sourceTable === 'bod_boc' ? 'ket_nasabah' : 'status_nasabah';

        $selectedDateInput = (string) $request->query('posisi_terakhir', '');
        $today             = now()->endOfDay();
        $selectedDate      = $selectedDateInput !== ''
            ? Carbon::parse($selectedDateInput)->endOfDay()
            : $today->copy();

        if ($selectedDate->greaterThan($today)) {
            $selectedDate = $today->copy();
        }

        // Tentukan 3 titik posisi snapshot (YoY end, bulan lalu, hari ini)
        $positions = collect([
            $selectedDate->copy()->subYearNoOverflow()->endOfYear()->toDateString(),
            $selectedDate->copy()->subMonthNoOverflow()->endOfMonth()->toDateString(),
            $selectedDate->copy()->toDateString(),
        ])->unique()->values();

        while ($positions->count() < 3) {
            $seed = Carbon::parse($positions->first())->subYearNoOverflow()->endOfYear()->toDateString();
            $positions->prepend($seed);
            $positions = $positions->unique()->values();
        }

        $positionBuckets = $positions
            ->mapWithKeys(function ($position) {
                $date = Carbon::parse($position);
                return [$date->format('Y-m') => $position];
            });

        $windowStart = Carbon::parse($positions->first())->startOfMonth()->toDateString();

        // Query data sumber (input_rekanan atau bod_boc)
        $sourceRows = DB::table($sourceTable . ' as src')
            ->whereNotNull('src.periode')
            ->whereBetween('src.periode', [$windowStart, $selectedDate->toDateString()])
            ->selectRaw('DATE(src.periode) as periode')
            ->selectRaw('TRIM(src.cif) as cif')
            ->selectRaw("TRIM(COALESCE(src.{$statusColumn}, '')) as status_nasabah")
            ->orderBy('periode')
            ->get();

        $cifList = $sourceRows
            ->pluck('cif')
            ->map(fn ($cif) => trim((string) $cif))
            ->filter(fn ($cif) => $cif !== '')
            ->unique()
            ->values();

        // Query saldo simpanan terbaru per CIF
        $latestSaldoByCif = [];
        if ($cifList->isNotEmpty()) {
            $latestPosisiQuery = DB::table('simpanan_multipn')
                ->selectRaw('CIFNO, MAX(posisi) as max_posisi')
                ->whereNotNull('CIFNO')
                ->where('posisi', '<=', $selectedDate->toDateString())
                ->whereIn('CIFNO', $cifList->all())
                ->groupBy('CIFNO');

            $simpananRowsQuery = DB::table('simpanan_multipn as sm')
                ->joinSub($latestPosisiQuery, 'latest', function ($join) {
                    $join->on('sm.CIFNO', '=', 'latest.CIFNO')
                         ->on('sm.posisi', '=', 'latest.max_posisi');
                })
                ->selectRaw('TRIM(sm.CIFNO) as cif')
                ->selectRaw('DATE(sm.posisi) as posisi')
                ->selectRaw("MAX(COALESCE(NULLIF(TRIM(sm.kantor_cabang), ''), 'Branch Office Belum Terpetakan')) as kantor_cabang")
                ->selectRaw('SUM(COALESCE(sm.saldo_idr, 0)) as saldo_idr')
                ->groupBy(DB::raw('TRIM(sm.CIFNO)'), DB::raw('DATE(sm.posisi)'));
            $userBranchScope = UserBranchScope::current();
            if ($userBranchScope !== null) {
                $simpananRowsQuery->whereRaw("UPPER(TRIM(COALESCE(sm.kantor_cabang, ''))) LIKE ?", [
                    '%' . $userBranchScope['upper_label'] . '%',
                ]);
            }
            $simpananRows = $simpananRowsQuery->get();

            foreach ($simpananRows as $simpananRow) {
                $cif = trim((string) ($simpananRow->cif ?? ''));
                if ($cif === '') {
                    continue;
                }
                $latestSaldoByCif[$cif] = [
                    'posisi'        => (string) ($simpananRow->posisi ?? ''),
                    'kantor_cabang' => trim((string) ($simpananRow->kantor_cabang ?: 'Branch Office Belum Terpetakan')),
                    'saldo_idr'     => (float) ($simpananRow->saldo_idr ?? 0),
                ];
            }
        }

        // Enrich source rows dengan data simpanan dan bucket posisi
        $sourceRows = $sourceRows
            ->map(function ($row) use ($latestSaldoByCif, $positionBuckets) {
                $cif       = trim((string) ($row->cif ?? ''));
                $simpanan  = $latestSaldoByCif[$cif] ?? null;
                $periode   = trim((string) ($row->periode ?? ''));
                $bucketKey = $periode !== '' ? Carbon::parse($periode)->format('Y-m') : null;

                $row->kantor_cabang   = $simpanan['kantor_cabang'] ?? 'Branch Office Belum Terpetakan';
                $row->saldo_idr       = $simpanan['saldo_idr'] ?? 0;
                $row->is_matched      = $simpanan ? 1 : 0;
                $row->bucket_periode  = $bucketKey && $positionBuckets->has($bucketKey)
                    ? $positionBuckets->get($bucketKey)
                    : null;
                $row->status_nasabah  = trim((string) ($row->status_nasabah ?? ''));

                return $row;
            })
            ->when(UserBranchScope::current() !== null, function ($items) {
                $branch = strtoupper((string) UserBranchScope::current()['label']);

                return $items->filter(fn ($row): bool => str_contains(
                    strtoupper((string) ($row->kantor_cabang ?? '')),
                    $branch
                ));
            })
            ->filter(fn ($row) => !empty($row->bucket_periode))
            ->sortBy([['kantor_cabang', 'asc'], ['bucket_periode', 'asc']])
            ->values();

        $latestPosition   = $positions->last();
        $previousPosition = $positions->slice(-2, 1)->first();
        $pipelineByRegional = [];
        $stats              = [];
        $matchedCount       = 0;

        foreach ($sourceRows as $row) {
            $regional      = trim((string) ($row->kantor_cabang ?: 'Branch Office Belum Terpetakan'));
            $cif           = trim((string) $row->cif);
            $posisi        = (string) $row->bucket_periode;
            $isMatched     = (int) ($row->is_matched ?? 0) === 1;
            $statusNasabah = strtolower(trim((string) ($row->status_nasabah ?? '')));

            $stats[$regional] ??= [];
            $stats[$regional][$posisi] ??= ['pipeline_cifs' => [], 'sudah_cifs' => [], 'belum_cifs' => [], 'saldo_cif' => 0];
            $stats[$regional][$posisi]['pipeline_cifs'][$cif] = true;
            $pipelineByRegional[$regional][$cif] = true;

            if (str_contains($statusNasabah, 'belum')) {
                $stats[$regional][$posisi]['belum_cifs'][$cif] = true;
            } elseif (str_contains($statusNasabah, 'sudah')) {
                $stats[$regional][$posisi]['sudah_cifs'][$cif] = true;
            }

            if ($isMatched && isset($stats[$regional][$posisi]['sudah_cifs'][$cif])) {
                $stats[$regional][$posisi]['saldo_cif'] += (float) ($row->saldo_idr ?? 0);
                $matchedCount++;
            }
        }

        $regionals = collect(array_unique(array_merge(
            array_keys($pipelineByRegional),
            array_keys($stats)
        )))->sort()->values();

        $tableRows   = [];
        $grandTotals = ['total_pipeline' => 0, 'positions' => [], 'akuisisi_pct' => 0, 'growth_saldo_pct' => 0];

        foreach ($positions as $position) {
            $grandTotals['positions'][$position] = ['belum_terakuisisi' => 0, 'sudah_terakuisisi' => 0, 'saldo_cif' => 0];
        }

        foreach ($regionals as $regional) {
            $totalPipeline = isset($pipelineByRegional[$regional]) ? count($pipelineByRegional[$regional]) : 0;
            $row           = ['regional' => $regional, 'total_pipeline' => $totalPipeline, 'positions' => [], 'akuisisi_pct' => 0, 'growth_saldo_pct' => 0];
            $runningBelum  = 0;
            $runningSudah  = 0;
            $runningSaldo  = 0;
            $runningYear   = null;

            foreach ($positions as $position) {
                $positionYear = Carbon::parse($position)->year;
                if ($runningYear !== null && $runningYear !== $positionYear) {
                    $runningBelum = 0;
                    $runningSudah = 0;
                    $runningSaldo = 0;
                }
                $runningYear = $positionYear;

                $regionalStats = $stats[$regional][$position] ?? ['pipeline_cifs' => [], 'sudah_cifs' => [], 'belum_cifs' => [], 'saldo_cif' => 0];
                $sudah         = count($regionalStats['sudah_cifs']);
                $belum         = count($regionalStats['belum_cifs']);
                $runningBelum += $belum;
                $runningSudah += $sudah;
                $runningSaldo += (float) $regionalStats['saldo_cif'];

                $row['positions'][$position] = [
                    'belum_terakuisisi' => $runningBelum,
                    'sudah_terakuisisi' => $runningSudah,
                    'saldo_cif'         => $runningSaldo,
                ];

                $grandTotals['positions'][$position]['belum_terakuisisi'] += $runningBelum;
                $grandTotals['positions'][$position]['sudah_terakuisisi'] += $runningSudah;
                $grandTotals['positions'][$position]['saldo_cif']         += $runningSaldo;
            }

            if ($latestPosition && isset($row['positions'][$latestPosition])) {
                $latestSudah        = $row['positions'][$latestPosition]['sudah_terakuisisi'];
                $row['akuisisi_pct'] = $totalPipeline > 0 ? ($latestSudah / $totalPipeline) * 100 : 0;
            }

            if ($latestPosition && $previousPosition && isset($row['positions'][$previousPosition])) {
                $latestSaldo  = $row['positions'][$latestPosition]['saldo_cif'] ?? 0;
                $previousSaldo = $row['positions'][$previousPosition]['saldo_cif'] ?? 0;
                $row['growth_saldo_pct'] = $previousSaldo > 0
                    ? (($latestSaldo - $previousSaldo) / $previousSaldo) * 100
                    : 0;
            }

            $grandTotals['total_pipeline'] += $totalPipeline;
            $tableRows[]                    = $row;
        }

        if ($latestPosition && isset($grandTotals['positions'][$latestPosition])) {
            $grandLatestSudah             = $grandTotals['positions'][$latestPosition]['sudah_terakuisisi'];
            $grandTotals['akuisisi_pct']  = $grandTotals['total_pipeline'] > 0
                ? ($grandLatestSudah / $grandTotals['total_pipeline']) * 100
                : 0;
        }

        if ($latestPosition && $previousPosition && isset($grandTotals['positions'][$previousPosition])) {
            $grandLatestSaldo   = $grandTotals['positions'][$latestPosition]['saldo_cif'] ?? 0;
            $grandPreviousSaldo = $grandTotals['positions'][$previousPosition]['saldo_cif'] ?? 0;
            $grandTotals['growth_saldo_pct'] = $grandPreviousSaldo > 0
                ? (($grandLatestSaldo - $grandPreviousSaldo) / $grandPreviousSaldo) * 100
                : 0;
        }

        return view($viewName, [
            'positions'    => $positions,
            'tableRows'    => $tableRows,
            'grandTotals'  => $grandTotals,
            'matchedCount' => $matchedCount,
            'selectedDate' => $selectedDate->toDateString(),
            'sourceLabel'  => $sourceLabel,
            'pageTitle'    => $pageTitle,
        ]);
    }
}
