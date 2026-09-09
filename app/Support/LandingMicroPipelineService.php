<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class LandingMicroPipelineService
{
    /** @param array<string, mixed>|null $branchScope */
    public function payload(?array $branchScope): array
    {
        if (! Schema::hasTable('micro_pipeline_records')) {
            return $this->emptyPayload($branchScope, 'Tabel Pipeline Mikro belum tersedia.');
        }

        $syncs = Schema::hasTable('micro_pipeline_syncs')
            ? DB::table('micro_pipeline_syncs')->get()->keyBy('source_key')
            : collect();
        $scopeKey = $branchScope['key'] ?? UserBranchScope::AREA_SCOPE;
        $cacheVersion = hash('sha256', implode('|', [
            (string) ($syncs->get('prewash')->source_hash ?? 'empty'),
            (string) ($syncs->get('prewash')->updated_at ?? 'never'),
            (string) ($syncs->get('slik_hijau')->source_hash ?? 'empty'),
            (string) ($syncs->get('slik_hijau')->updated_at ?? 'never'),
            (string) $scopeKey,
            'v3',
        ]));

        return Cache::remember(
            'landing:micro-pipeline:'.$cacheVersion,
            now()->addHours(6),
            fn (): array => $this->buildPayload($branchScope, $syncs->all())
        );
    }

    /** @param array<string, mixed>|null $branchScope */
    private function buildPayload(?array $branchScope, array $syncs): array
    {
        $sync = $syncs['prewash'] ?? null;
        $slikSync = $syncs['slik_hijau'] ?? null;
        $query = $this->scopedQuery($branchScope, 'prewash');
        $summary = $query
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN visit_status = 'done' THEN 1 ELSE 0 END) as done")
            ->selectRaw("SUM(CASE WHEN visit_status = 'scheduled' THEN 1 ELSE 0 END) as scheduled")
            ->selectRaw("SUM(CASE WHEN visit_status = 'pending' THEN 1 ELSE 0 END) as pending")
            ->selectRaw('COALESCE(SUM(plafond), 0) as potential_plafond')
            ->selectRaw('COALESCE(SUM(real_plafond), 0) as realized_plafond')
            ->first();

        $total = (int) ($summary->total ?? 0);
        $slikHijau = $this->datasetSummary($branchScope, 'slik_hijau', $slikSync);
        if ($total === 0 && (int) data_get($slikHijau, 'summary.total', 0) === 0) {
            return $this->emptyPayload($branchScope, 'Pipeline Mikro belum disinkronkan.');
        }

        $groupColumn = $branchScope === null ? 'branch_name' : 'unit_name';
        $groupLabel = $branchScope === null ? 'Cabang' : 'Unit Kerja';
        $groups = $this->scopedQuery($branchScope)
            ->selectRaw("COALESCE(NULLIF(TRIM({$groupColumn}), ''), 'Tidak Teridentifikasi') as label")
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN visit_status = 'done' THEN 1 ELSE 0 END) as done")
            ->selectRaw("SUM(CASE WHEN visit_status = 'scheduled' THEN 1 ELSE 0 END) as scheduled")
            ->selectRaw("SUM(CASE WHEN visit_status = 'pending' THEN 1 ELSE 0 END) as pending")
            ->selectRaw('COALESCE(SUM(plafond), 0) as potential_plafond')
            ->groupBy($groupColumn)
            ->orderByDesc('total')
            ->limit($branchScope === null ? 8 : 12)
            ->get()
            ->map(fn (object $row): array => $this->aggregateRow($row))
            ->all();

        $sources = $this->aggregateSources($branchScope, 10, $groupColumn);
        $products = $this->aggregateDimension($branchScope, 'recommended_product', 8);
        return [
            'available' => true,
            'scope_label' => $branchScope['label'] ?? 'Area 6',
            'scope' => $branchScope === null ? UserBranchScope::AREA_SCOPE : 'branch',
            'query_scope' => $branchScope['key'] ?? UserBranchScope::AREA_SCOPE,
            'summary' => [
                'total' => $total,
                'done' => (int) ($summary->done ?? 0),
                'scheduled' => (int) ($summary->scheduled ?? 0),
                'pending' => (int) ($summary->pending ?? 0),
                'visit_rate' => $this->percentage((int) ($summary->done ?? 0), $total),
                'potential_plafond' => (float) ($summary->potential_plafond ?? 0),
                'realized_plafond' => (float) ($summary->realized_plafond ?? 0),
            ],
            'slik_hijau' => $slikHijau,
            'group_label' => $groupLabel,
            'groups' => $groups,
            'sources' => $sources,
            'products' => $products,
            'source_options' => $this->scopedQuery($branchScope)
                ->whereNotNull('source_pipeline')
                ->where('source_pipeline', '<>', '')
                ->distinct()
                ->orderBy('source_pipeline')
                ->limit(100)
                ->pluck('source_pipeline')
                ->values()
                ->all(),
            'product_options' => $this->scopedQuery($branchScope)
                ->whereNotNull('recommended_product')
                ->where('recommended_product', '<>', '')
                ->distinct()
                ->orderBy('recommended_product')
                ->limit(100)
                ->pluck('recommended_product')
                ->values()
                ->all(),
            'initial_records' => $this->records($branchScope, ['status' => 'open', 'per_page' => 20]),
            'sync' => [
                'source_url' => (string) ($sync->source_url ?? config('services.micro_pipeline.prewash_source_url', '')),
                'source_file' => (string) ($sync->source_file ?? '-'),
                'source_sheet' => (string) ($sync->source_sheet ?? '-'),
                'source_rows' => (int) ($sync->source_rows ?? 0),
                'imported_rows' => (int) ($sync->imported_rows ?? 0),
                'outside_scope_rows' => (int) ($sync->outside_scope_rows ?? 0),
                'synced_at' => $sync?->synced_at,
            ],
            'status_labels' => $this->statusLabels(),
            'dataset_options' => [
                ['key' => 'prewash', 'label' => 'Prewash'],
                ['key' => 'slik_hijau', 'label' => 'SLIK Hijau'],
            ],
            'error' => '',
        ];
    }

    /**
     * @param array<string, mixed>|null $branchScope
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function records(?array $branchScope, array $filters): array
    {
        if (! Schema::hasTable('micro_pipeline_records')) {
            return ['data' => [], 'meta' => ['page' => 1, 'last_page' => 1, 'total' => 0, 'per_page' => 20]];
        }

        $dataset = in_array((string) ($filters['dataset'] ?? 'prewash'), ['prewash', 'slik_hijau'], true)
            ? (string) ($filters['dataset'] ?? 'prewash')
            : 'prewash';
        $query = $this->scopedQuery($branchScope, $dataset);
        $status = strtolower(trim((string) ($filters['status'] ?? 'all')));
        if ($dataset === 'slik_hijau' && $status === 'open') {
            $status = 'all';
        }
        if ($status === 'open') {
            $query->whereIn('visit_status', ['scheduled', 'pending']);
        } elseif (in_array($status, ['done', 'scheduled', 'pending'], true)) {
            $query->where('visit_status', $status);
        }

        $source = trim((string) ($filters['source'] ?? ''));
        if ($source !== '') {
            $query->where('source_pipeline', $source);
        }
        $product = trim((string) ($filters['product'] ?? ''));
        if ($product !== '') {
            $query->where('recommended_product', $product);
        }
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $needle = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_substr($search, 0, 100)).'%';
            $query->where(function (Builder $builder) use ($needle): void {
                $builder->where('debtor_name', 'like', $needle)
                    ->orWhere('cif', 'like', $needle)
                    ->orWhere('unit_name', 'like', $needle)
                    ->orWhere('mantri_name', 'like', $needle)
                    ->orWhere('description', 'like', $needle);
            });
        }

        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(50, max(10, (int) ($filters['per_page'] ?? 20)));
        $total = (clone $query)->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);

        $rows = $query
            ->select([
                'id', 'source_key', 'debtor_name', 'cif', 'branch_name', 'unit_name', 'source_pipeline',
                'recommended_product', 'plafond', 'outstanding', 'description', 'visit_status',
                'visit_count', 'planned_count', 'last_visit_month', 'visit_months', 'planned_months',
                'mantri_name', 'mantri_pn',
            ])
            ->orderByRaw("CASE visit_status WHEN 'scheduled' THEN 0 WHEN 'pending' THEN 1 ELSE 2 END")
            ->orderByDesc('score')
            ->orderByDesc('plafond')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->map(function (object $row): array {
                $status = (string) $row->visit_status;

                return [
                    'id' => (int) $row->id,
                    'dataset' => (string) $row->source_key,
                    'debtor_name' => (string) ($row->debtor_name ?: '-'),
                    'cif' => (string) ($row->cif ?: '-'),
                    'branch_name' => (string) ($row->branch_name ?: '-'),
                    'unit_name' => (string) ($row->unit_name ?: '-'),
                    'source_pipeline' => (string) ($row->source_pipeline ?: 'Tidak Teridentifikasi'),
                    'recommended_product' => (string) ($row->recommended_product ?: '-'),
                    'plafond' => (float) $row->plafond,
                    'outstanding' => (float) $row->outstanding,
                    'description' => (string) ($row->description ?: '-'),
                    'visit_status' => $status,
                    'visit_status_label' => $this->statusLabels()[$status] ?? 'Belum Dikunjungi',
                    'visit_count' => (int) $row->visit_count,
                    'planned_count' => (int) $row->planned_count,
                    'last_visit_month' => (string) ($row->last_visit_month ?: '-'),
                    'visit_months' => $this->decodeMonths($row->visit_months),
                    'planned_months' => $this->decodeMonths($row->planned_months),
                    'mantri_name' => (string) ($row->mantri_name ?: '-'),
                    'mantri_pn' => (string) ($row->mantri_pn ?: '-'),
                ];
            })
            ->all();

        return [
            'data' => $rows,
            'meta' => [
                'page' => $page,
                'last_page' => $lastPage,
                'total' => $total,
                'per_page' => $perPage,
                'from' => $total > 0 ? (($page - 1) * $perPage) + 1 : 0,
                'to' => min($total, $page * $perPage),
            ],
        ];
    }

    /** @param array<string, mixed>|null $branchScope */
    private function scopedQuery(?array $branchScope, string $dataset = 'prewash'): Builder
    {
        $query = DB::table('micro_pipeline_records')->where('source_key', $dataset);
        if ($branchScope !== null) {
            $query->where('branch_key', (string) $branchScope['key']);
        }

        return $query;
    }

    /** @param array<string, mixed>|null $branchScope */
    private function aggregateSources(?array $branchScope, int $limit, string $groupColumn): array
    {
        $sources = $this->aggregateDimension($branchScope, 'source_pipeline', $limit);

        if (empty($sources)) {
            return [];
        }

        $branchRows = $this->scopedQuery($branchScope)
            ->whereNotNull('source_pipeline')
            ->where('source_pipeline', '<>', '')
            ->selectRaw("source_pipeline as source")
            ->selectRaw("COALESCE(NULLIF(TRIM({$groupColumn}), ''), 'Tidak Teridentifikasi') as label")
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN visit_status = 'done' THEN 1 ELSE 0 END) as done")
            ->selectRaw("SUM(CASE WHEN visit_status = 'scheduled' THEN 1 ELSE 0 END) as scheduled")
            ->selectRaw("SUM(CASE WHEN visit_status = 'pending' THEN 1 ELSE 0 END) as pending")
            ->selectRaw('COALESCE(SUM(plafond), 0) as potential_plafond')
            ->groupBy('source_pipeline', $groupColumn)
            ->orderByDesc('total')
            ->get()
            ->groupBy('source');

        $productRows = $this->scopedQuery($branchScope)
            ->whereNotNull('source_pipeline')
            ->where('source_pipeline', '<>', '')
            ->selectRaw("source_pipeline as source")
            ->selectRaw("COALESCE(NULLIF(TRIM(recommended_product), ''), 'Tidak Teridentifikasi') as label")
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN visit_status = 'done' THEN 1 ELSE 0 END) as done")
            ->selectRaw("SUM(CASE WHEN visit_status = 'scheduled' THEN 1 ELSE 0 END) as scheduled")
            ->selectRaw("SUM(CASE WHEN visit_status = 'pending' THEN 1 ELSE 0 END) as pending")
            ->selectRaw('COALESCE(SUM(plafond), 0) as potential_plafond')
            ->groupBy('source_pipeline', 'recommended_product')
            ->orderByDesc('total')
            ->get()
            ->groupBy('source');

        foreach ($sources as &$source) {
            $sourceLabel = $source['label'];
            $source['groups'] = collect($branchRows->get($sourceLabel, []))
                ->map(fn (object $r): array => $this->aggregateRow($r))
                ->all();
            $source['products'] = collect($productRows->get($sourceLabel, []))
                ->map(fn (object $r): array => $this->aggregateRow($r))
                ->all();
        }
        unset($source);

        return $sources;
    }

    private function aggregateDimension(?array $branchScope, string $column, int $limit): array
    {
        return $this->scopedQuery($branchScope)
            ->selectRaw("COALESCE(NULLIF(TRIM({$column}), ''), 'Tidak Teridentifikasi') as label")
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN visit_status = 'done' THEN 1 ELSE 0 END) as done")
            ->selectRaw("SUM(CASE WHEN visit_status = 'scheduled' THEN 1 ELSE 0 END) as scheduled")
            ->selectRaw("SUM(CASE WHEN visit_status = 'pending' THEN 1 ELSE 0 END) as pending")
            ->selectRaw('COALESCE(SUM(plafond), 0) as potential_plafond')
            ->groupBy($column)
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(fn (object $row): array => $this->aggregateRow($row))
            ->all();
    }

    private function aggregateRow(object $row): array
    {
        $total = (int) ($row->total ?? 0);
        $done = (int) ($row->done ?? 0);
        $scheduled = (int) ($row->scheduled ?? 0);
        $pending = (int) ($row->pending ?? 0);

        return [
            'label' => (string) ($row->label ?? 'Tidak Teridentifikasi'),
            'total' => $total,
            'done' => $done,
            'scheduled' => $scheduled,
            'pending' => $pending,
            'visit_rate' => $this->percentage($done, $total),
            'done_rate' => $this->percentage($done, $total),
            'scheduled_rate' => $this->percentage($scheduled, $total),
            'pending_rate' => $this->percentage($pending, $total),
            'potential_plafond' => (float) ($row->potential_plafond ?? 0),
        ];
    }

    /** @param array<string, mixed>|null $branchScope */
    private function datasetSummary(?array $branchScope, string $dataset, ?object $sync): array
    {
        $query = $this->scopedQuery($branchScope, $dataset);
        $summary = $query
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN visit_status = 'done' THEN 1 ELSE 0 END) as done")
            ->selectRaw('COALESCE(SUM(plafond), 0) as potential_plafond')
            ->selectRaw("COUNT(DISTINCT NULLIF(TRIM(mantri_pn), '')) as mantri")
            ->selectRaw("COUNT(DISTINCT NULLIF(TRIM(unit_name), '')) as units")
            ->first();

        $groupColumn = $branchScope === null ? 'branch_name' : 'unit_name';
        $groups = $this->scopedQuery($branchScope, $dataset)
            ->selectRaw("COALESCE(NULLIF(TRIM({$groupColumn}), ''), 'Tidak Teridentifikasi') as label")
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('COALESCE(SUM(plafond), 0) as potential_plafond')
            ->groupBy($groupColumn)
            ->orderByDesc('potential_plafond')
            ->limit($branchScope === null ? 8 : 12)
            ->get()
            ->map(function (object $row): array {
                return [
                    'label' => (string) ($row->label ?? 'Tidak Teridentifikasi'),
                    'total' => (int) ($row->total ?? 0),
                    'potential_plafond' => (float) ($row->potential_plafond ?? 0),
                ];
            })
            ->all();

        return [
            'available' => (int) ($summary->total ?? 0) > 0,
            'summary' => [
                'total' => (int) ($summary->total ?? 0),
                'done' => (int) ($summary->done ?? 0),
                'potential_plafond' => (float) ($summary->potential_plafond ?? 0),
                'mantri' => (int) ($summary->mantri ?? 0),
                'units' => (int) ($summary->units ?? 0),
            ],
            'groups' => $groups,
            'sync' => [
                'source_url' => (string) ($sync->source_url ?? config('services.micro_pipeline.slik_hijau_source_url', '')),
                'source_sheet' => (string) ($sync->source_sheet ?? 'Berminat 1'),
                'imported_rows' => (int) ($sync->imported_rows ?? 0),
                'synced_at' => $sync?->synced_at,
            ],
        ];
    }

    private function percentage(int $part, int $total): float
    {
        return $total > 0 ? round(($part / $total) * 100, 1) : 0.0;
    }

    /** @return array<int, string> */
    private function decodeMonths(mixed $value): array
    {
        if (is_array($value)) {
            return array_values($value);
        }
        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? array_values(array_map('strval', $decoded)) : [];
    }

    /** @return array<string, string> */
    private function statusLabels(): array
    {
        return [
            'done' => 'Sudah Dikunjungi',
            'scheduled' => 'Kunjungan Terjadwal',
            'pending' => 'Belum Dikunjungi',
        ];
    }

    /** @param array<string, mixed>|null $branchScope */
    private function emptyPayload(?array $branchScope, string $error): array
    {
        return [
            'available' => false,
            'scope_label' => $branchScope['label'] ?? 'Area 6',
            'scope' => $branchScope === null ? UserBranchScope::AREA_SCOPE : 'branch',
            'query_scope' => $branchScope['key'] ?? UserBranchScope::AREA_SCOPE,
            'summary' => [
                'total' => 0, 'done' => 0, 'scheduled' => 0, 'pending' => 0,
                'visit_rate' => 0.0, 'potential_plafond' => 0.0, 'realized_plafond' => 0.0,
            ],
            'slik_hijau' => [
                'available' => false,
                'summary' => ['total' => 0, 'done' => 0, 'potential_plafond' => 0.0, 'mantri' => 0, 'units' => 0],
                'groups' => [],
                'sync' => [],
            ],
            'group_label' => $branchScope === null ? 'Cabang' : 'Unit Kerja',
            'groups' => [],
            'sources' => [],
            'products' => [],
            'source_options' => [],
            'product_options' => [],
            'initial_records' => ['data' => [], 'meta' => ['page' => 1, 'last_page' => 1, 'total' => 0, 'per_page' => 20]],
            'sync' => [],
            'status_labels' => $this->statusLabels(),
            'dataset_options' => [
                ['key' => 'prewash', 'label' => 'Prewash'],
                ['key' => 'slik_hijau', 'label' => 'SLIK Hijau'],
            ],
            'error' => $error,
        ];
    }
}
