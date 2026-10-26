<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Services\Reports\MantriIncentiveService;
use App\Support\ProductivitySourceQuality;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InsentifMantriReportController extends Controller
{
    private const ALL = 'ALL';

    private const STATUS_OPTIONS = [
        'tetap' => 'Pekerja Tetap',
        'semua' => 'Semua Mantri',
    ];

    public function __construct(private readonly MantriIncentiveService $service)
    {
    }

    public function index(Request $request): View
    {
        @set_time_limit(300);
        $this->releaseSessionLockIfNeeded();

        $periods = $this->service->availablePeriods();
        $selection = $this->resolveSelection($periods, $request);
        $selectedPeriod = $selection['selectedPeriod'];
        $filters = $this->resolveFilters($request);

        $payload = $selectedPeriod !== null ? $this->service->build($selectedPeriod) : null;
        $allRows = collect($payload['rows'] ?? []);
        $rows = $this->filterRows($allRows, $filters);

        $branchOptions = $this->service->scopedBranches();
        $unitsByBranch = $allRows
            ->groupBy('cabang')
            ->map(fn (Collection $items): array => $items->pluck('unit')->filter()->unique()->sort()->values()->all())
            ->all();

        return view('report.kpi.insentif-mantri', [
            'periods' => $periods,
            'selectedPeriod' => $selectedPeriod,
            'quarterOptions' => $selection['quarterOptions'],
            'selectedQuarter' => $selection['selectedQuarter'],
            'quarterPeriods' => $selection['quarterPeriods'],
            'selectedPeriodLabel' => $selectedPeriod ? Carbon::parse($selectedPeriod)->locale('id')->translatedFormat('d F Y') : '-',
            'positionSource' => $selectedPeriod ? $this->service->positionSource($selectedPeriod) : null,
            'freshness' => $this->service->sourceFreshness(),
            'sourceQuality' => app(ProductivitySourceQuality::class)->summarize(
                $selectedPeriod,
                'MICRO',
                MantriIncentiveService::PRODUCTS,
                $branchOptions
            ),
            'filters' => $filters,
            'branchOptions' => $branchOptions,
            'unitsByBranch' => $unitsByBranch,
            'statusOptions' => self::STATUS_OPTIONS,
            'window' => $payload['window'] ?? null,
            'rows' => $rows,
            'summary' => $this->summarize($rows),
            'outsideRoster' => $payload['outside_roster'] ?? null,
            'tiers' => MantriIncentiveService::TIERS,
            'minAverage' => MantriIncentiveService::MIN_AVG_NET_DISBURSEMENT,
            'maxMob' => MantriIncentiveService::MAX_MOB6_RATIO,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        @set_time_limit(300);
        $this->releaseSessionLockIfNeeded();

        $periods = $this->service->availablePeriods();
        $selectedPeriod = $this->resolveSelection($periods, $request)['selectedPeriod'];
        abort_if($selectedPeriod === null, 404);

        $type = $request->input('jenis') === 'mob6' ? 'mob6' : 'nd';
        $filters = $this->resolveFilters($request);
        $roster = $this->service->roster();
        $records = collect($this->service->nominatif($selectedPeriod, $type))
            ->map(function (array $record) use ($roster): array {
                $person = $roster[$record['pn']] ?? null;
                $record['nama_mantri'] = $person['name'] ?? '';
                $record['cabang_mantri'] = $person['branch'] ?? '';
                $record['unit_mantri'] = $person['unit'] ?? '';
                $record['status_pekerja'] = $person['status'] ?? 'Di luar roster Mantri';
                $record['status_key'] = $person['status_key'] ?? 'luar';

                return $record;
            })
            ->filter(function (array $record) use ($filters): bool {
                $branch = $record['cabang_mantri'] !== '' ? $record['cabang_mantri'] : ($record['cabang'] ?? '');
                if ($filters['cabang'] !== self::ALL && $branch !== $filters['cabang']) {
                    return false;
                }
                if ($filters['unit'] !== self::ALL && $record['unit_mantri'] !== $filters['unit']) {
                    return false;
                }
                if ($filters['status'] === 'tetap' && $record['status_key'] !== 'tetap') {
                    return false;
                }

                return in_array($branch, $this->service->scopedBranches(), true);
            })
            ->values();

        $filename = sprintf('nominatif_%s_insentif_mantri_%s.csv', $type === 'mob6' ? 'mob6' : 'net_disbursement', str_replace('-', '', $selectedPeriod));

        return response()->streamDownload(function () use ($records, $type): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            if ($type === 'mob6') {
                fputcsv($out, ['no', 'pn_prakarsa', 'nama_mantri', 'cabang', 'unit', 'status_pekerja', 'bulan_booking', 'posisi_mob', 'mob', 'cif', 'no_rekening', 'nama_debitur', 'produk', 'tgl_realisasi', 'plafond', 'kolek_mob', 'os_mob', 'plafond_pemburukan'], ';');
                foreach ($records as $index => $record) {
                    fputcsv($out, [
                        $index + 1, $record['pn'], $record['nama_mantri'], $record['cabang_mantri'], $record['unit_mantri'], $record['status_pekerja'],
                        $record['bulan_booking'], $record['posisi_mob'], $record['mob'], $record['cif'], "'".$record['account'], $record['nama_debitur'],
                        $record['produk'], $record['tgl_realisasi'], $record['plafon'], $record['kolek_mob'] ?? 'Lunas/tidak ada', $record['os_mob'] ?? 0, $record['pemburukan'],
                    ], ';');
                }
            } else {
                fputcsv($out, ['no', 'pn_prakarsa', 'sumber_pn', 'nama_mantri', 'cabang', 'unit', 'status_pekerja', 'thn_bln_real', 'posisi', 'cif', 'no_rekening', 'nama_debitur', 'produk', 'tgl_realisasi', 'plafond', 'sisa_os_pelunasan', 'kategori', 'nett_disb'], ';');
                foreach ($records as $index => $record) {
                    fputcsv($out, [
                        $index + 1, $record['pn'], $record['pn_source'], $record['nama_mantri'], $record['cabang_mantri'] ?: $record['cabang'], $record['unit_mantri'] ?: $record['unit'], $record['status_pekerja'],
                        str_replace('-', '', $record['bulan']), $record['posisi'], $record['cif'], "'".$record['account'], $record['nama_debitur'],
                        $record['produk'], $record['tgl_realisasi'], $record['plafon'], $record['os_pelunasan'], $record['kategori'], $record['net'],
                    ], ';');
                }
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @param array<int, string> $periods */
    private function resolvePeriod(array $periods, mixed $requested): ?string
    {
        $requested = trim((string) $requested);

        return in_array($requested, $periods, true) ? $requested : ($periods[0] ?? null);
    }

    /** @param array<int, string> $periods */
    private function resolveSelection(array $periods, Request $request): array
    {
        $quarterPeriods = [];
        foreach ($periods as $period) {
            $quarterPeriods[$this->quarterKey($period)][] = $period;
        }
        foreach ($quarterPeriods as &$items) {
            rsort($items, SORT_STRING);
        }
        unset($items);

        $years = array_values(array_unique(array_map(fn (string $period): string => substr($period, 0, 4), $periods)));
        rsort($years, SORT_STRING);
        $quarterOptions = [];
        foreach ($years as $year) {
            foreach ([1 => 'Jan - Mar', 2 => 'Apr - Jun', 3 => 'Jul - Sep', 4 => 'Okt - Des'] as $quarter => $label) {
                $key = $year.'-Q'.$quarter;
                $quarterOptions[$key] = [
                    'label' => 'Q'.$quarter.' '.$year.' ('.$label.')',
                    'periods' => $quarterPeriods[$key] ?? [],
                ];
            }
        }

        $requestedQuarter = trim((string) $request->input('kuartal'));
        $selectedPeriod = $this->resolvePeriod($periods, $request->input('periode'));
        if (! empty($quarterPeriods[$requestedQuarter])) {
            $selectedPeriod = $this->resolvePeriod($quarterPeriods[$requestedQuarter], $request->input('periode'));
        }
        $selectedQuarter = $selectedPeriod !== null ? $this->quarterKey($selectedPeriod) : null;

        return [
            'selectedPeriod' => $selectedPeriod,
            'selectedQuarter' => $selectedQuarter,
            'quarterOptions' => $quarterOptions,
            'quarterPeriods' => $selectedQuarter !== null ? $quarterPeriods[$selectedQuarter] : [],
        ];
    }

    private function quarterKey(string $period): string
    {
        return substr($period, 0, 4).'-Q'.(int) ceil(((int) substr($period, 5, 2)) / 3);
    }

    /** @return array{cabang: string, unit: string, status: string} */
    private function resolveFilters(Request $request): array
    {
        $branch = strtoupper(trim((string) $request->input('cabang', self::ALL)));
        if (! in_array($branch, $this->service->scopedBranches(), true)) {
            $branch = self::ALL;
        }
        $unit = strtoupper(trim((string) $request->input('unit', self::ALL)));
        $status = (string) $request->input('status', 'tetap');

        return [
            'cabang' => $branch,
            'unit' => $unit !== '' ? $unit : self::ALL,
            'status' => array_key_exists($status, self::STATUS_OPTIONS) ? $status : 'tetap',
        ];
    }

    private function filterRows(Collection $rows, array $filters): Collection
    {
        return $rows
            ->when($filters['cabang'] !== self::ALL, fn (Collection $items) => $items->where('cabang', $filters['cabang']))
            ->when($filters['unit'] !== self::ALL, fn (Collection $items) => $items->where('unit', $filters['unit']))
            ->when($filters['status'] === 'tetap', fn (Collection $items) => $items->where('status_key', 'tetap'))
            ->values();
    }

    /** @return array<string, mixed> */
    private function summarize(Collection $rows): array
    {
        $count = $rows->count();
        $quadrants = [
            'I' => $rows->filter(fn (array $row): bool => $row['nd_ok'] && $row['mob_ok'])->count(),
            'II' => $rows->filter(fn (array $row): bool => $row['nd_ok'] && ! $row['mob_ok'])->count(),
            'III' => $rows->filter(fn (array $row): bool => ! $row['nd_ok'] && $row['mob_ok'])->count(),
            'IV' => $rows->filter(fn (array $row): bool => ! $row['nd_ok'] && ! $row['mob_ok'])->count(),
        ];
        $tiers = [];
        foreach (MantriIncentiveService::TIERS as $tier) {
            $members = $rows->where('tier_key', $tier['key']);
            $tiers[$tier['key']] = ['count' => $members->count(), 'nominal' => (float) $members->sum('nominal')];
        }

        return [
            'count' => $count,
            'eligible' => $rows->where('eligible', true)->count(),
            'nd_ok' => $rows->where('nd_ok', true)->count(),
            'mob_ok' => $rows->where('mob_ok', true)->count(),
            'nominal' => (float) $rows->sum('nominal'),
            'run_rate_nominal' => (float) $rows->sum('run_rate_nominal'),
            'nd_total' => (float) $rows->sum('nd_total'),
            'mob_plafon' => (float) $rows->sum('mob_plafon'),
            'mob_pemburukan' => (float) $rows->sum('mob_pemburukan'),
            'quadrants' => $quadrants,
            'tiers' => $tiers,
        ];
    }
}
