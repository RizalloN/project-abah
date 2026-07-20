<?php

namespace App\Services\Reports;

use App\Support\UserBranchScope;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Shared service untuk membangun opsi filter branch/uker.
 * Digunakan oleh controller report (EDC, QRIS, New Payroll, Brilink, dsb.)
 * agar tidak menduplikasi query yang sama di tiap controller.
 */
class ReportFilterService
{
    /**
     * Bangun array branchOptions dan branchUkerMap dari tabel yang diberikan.
     *
     * @param  string  $table         Nama tabel database
     * @param  string  $branchColumn  Nama kolom branch/kanca
     * @param  string  $ukerColumn    Nama kolom uker/unit kerja
     */
    public function buildBranchUkerFilterOptions(string $table, string $branchColumn, string $ukerColumn): array
    {
        $cacheKey = 'report_filter:branch_uker:' . sha1(json_encode([$table, $branchColumn, $ukerColumn]));

        $options = Cache::remember($cacheKey, now()->addHours(6), function () use ($table, $branchColumn, $ukerColumn) {
            $branchUkerRows = DB::table($table)
                ->selectRaw("TRIM($branchColumn) as branch_name")
                ->selectRaw("TRIM($ukerColumn) as uker_name")
                ->whereNotNull($branchColumn)
                ->whereNotNull($ukerColumn)
                ->whereRaw("TRIM($branchColumn) <> ''")
                ->whereRaw("TRIM($ukerColumn) <> ''")
                ->distinct()
                ->orderBy('branch_name')
                ->orderBy('uker_name')
                ->get();

            return [
                'branchOptions' => $branchUkerRows
                    ->pluck('branch_name')
                    ->filter()
                    ->unique()
                    ->values(),
                'branchUkerMap' => $branchUkerRows
                    ->groupBy('branch_name')
                    ->map(function ($rows) {
                        return $rows->pluck('uker_name')
                            ->filter()
                            ->unique()
                            ->values()
                            ->all();
                    }),
            ];
        });

        return $this->scopeBranchOptions($options);
    }

    /**
     * Bangun hanya daftar branch unik, tanpa memproses peta branch -> uker.
     */
    public function buildBranchOptions(string $table, string $branchColumn): array
    {
        $cacheKey = 'report_filter:branch_options:' . sha1(json_encode([$table, $branchColumn]));

        return $this->scopeBranchOptions([
            'branchOptions' => Cache::remember($cacheKey, now()->addHours(6), function () use ($table, $branchColumn) {
                return DB::table($table)
                    ->selectRaw("TRIM($branchColumn) as branch_name")
                    ->whereNotNull($branchColumn)
                    ->whereRaw("TRIM($branchColumn) <> ''")
                    ->distinct()
                    ->orderBy('branch_name')
                    ->pluck('branch_name')
                    ->filter()
                    ->values();
            }),
        ]);
    }

    /**
     * Bangun array branchOptions dan branchUkerMap khusus untuk Brilink
     * (menggabungkan data dari 3 tabel berbeda).
     */
    public function buildBrilinkFilterOptions(): array
    {
        $options = Cache::remember('report_filter:brilink_options', now()->addHours(6), function () {
            $rows = collect([
                DB::table('brilink_web_laporan_summary_transaksi_brilink_web')
                    ->selectRaw('TRIM(cabang) as branch_name')
                    ->selectRaw('TRIM(uker) as uker_name')
                    ->whereNotNull('cabang')
                    ->whereNotNull('uker')
                    ->whereRaw("TRIM(cabang) <> ''")
                    ->whereRaw("TRIM(uker) <> ''")
                    ->get(),
                DB::table('casa_brilink_web')
                    ->selectRaw('TRIM(mbdesc) as branch_name')
                    ->selectRaw('TRIM(brdesc) as uker_name')
                    ->whereNotNull('mbdesc')
                    ->whereNotNull('brdesc')
                    ->whereRaw("TRIM(mbdesc) <> ''")
                    ->whereRaw("TRIM(brdesc) <> ''")
                    ->get(),
                DB::table('casa_brilink_edc')
                    ->selectRaw('TRIM(mbdesc) as branch_name')
                    ->selectRaw('TRIM(brdesc) as uker_name')
                    ->whereNotNull('mbdesc')
                    ->whereNotNull('brdesc')
                    ->whereRaw("TRIM(mbdesc) <> ''")
                    ->whereRaw("TRIM(brdesc) <> ''")
                    ->get(),
            ])->flatten(1)
                ->map(function ($row) {
                    $row->branch_name = strtoupper(trim((string) ($row->branch_name ?? '')));
                    $row->uker_name   = strtoupper(trim((string) ($row->uker_name ?? '')));
                    return $row;
                })
                ->filter(function ($row) {
                    return $row->branch_name !== '' && $row->uker_name !== '';
                })
                ->unique(fn ($row) => $row->branch_name . '|' . $row->uker_name)
                ->sortBy([
                    ['branch_name', 'asc'],
                    ['uker_name', 'asc'],
                ])
                ->values();

            return [
                'branchOptions' => $rows
                    ->pluck('branch_name')
                    ->filter()
                    ->unique()
                    ->values(),
                'branchUkerMap' => $rows
                    ->groupBy('branch_name')
                    ->map(function ($items) {
                        return $items->pluck('uker_name')
                            ->filter()
                            ->unique()
                            ->values()
                            ->all();
                    }),
            ];
        });

        return $this->scopeBranchOptions($options);
    }

    /**
     * Ambil daftar uker unik untuk branch tertentu dari tabel detail.
     */
    public function getUkersForBranches(string $table, string $branchColumn, string $ukerColumn, array $branches): array
    {
        $selectedBranches = collect($branches)
            ->map(fn ($branch) => strtoupper(trim((string) $branch)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($selectedBranches)) {
            return [];
        }

        $cacheKey = 'report_filter:ukers_for_branches:' . sha1(json_encode([
            $table,
            $branchColumn,
            $ukerColumn,
            $selectedBranches,
        ]));

        return Cache::remember($cacheKey, now()->addHours(6), function () use ($table, $branchColumn, $ukerColumn, $selectedBranches) {
            return DB::table($table)
                ->selectRaw("TRIM($ukerColumn) as uker_name")
                ->whereNotNull($branchColumn)
                ->whereNotNull($ukerColumn)
                ->whereRaw("TRIM($branchColumn) <> ''")
                ->whereRaw("TRIM($ukerColumn) <> ''")
                ->whereIn(DB::raw("UPPER(TRIM($branchColumn))"), $selectedBranches)
                ->distinct()
                ->orderBy('uker_name')
                ->pluck('uker_name')
                ->filter()
                ->values()
                ->all();
        });
    }

    private function scopeBranchOptions(array $options): array
    {
        $scope = UserBranchScope::current();
        if ($scope === null) {
            return $options;
        }

        $matchesScope = fn ($branch): bool => strtoupper(trim((string) $branch)) === $scope['upper_label'];
        $options['branchOptions'] = collect($options['branchOptions'] ?? [])
            ->filter($matchesScope)
            ->values();

        if (array_key_exists('branchUkerMap', $options)) {
            $options['branchUkerMap'] = collect($options['branchUkerMap'] ?? [])
                ->filter(fn ($ukers, $branch): bool => $matchesScope($branch));
        }

        return $options;
    }
}
