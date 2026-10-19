<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Gi405SingleRowRecoverySource
{
    public function query(?string $period = null): Builder
    {
        // Same signed YTD income accounts used by the GI405 Data PH report.
        // The legacy claim GL was reclassified from 2026 onwards.
        return DB::table('gi405_singlerow as gr')
            ->when($period !== null, fn (Builder $query) => $query->where('gr.periode', $period))
            ->whereIn(DB::raw('TRIM(gr.account_number)'), ['418020000010', '418020000013'])
            ->where(fn (Builder $query) => $query->where('gr.periode', '<', '2026-01-01')
                ->orWhereRaw("TRIM(gr.account_number) = '418020000010'"))
            ->whereRaw("UPPER(TRIM(gr.currency)) = 'IDR'")
            ->whereRaw("UPPER(TRIM(gr.posting_control)) = '*POST'")
            ->whereNotNull('gr.ending_balance');
    }

    public function availableForPeriod(string $period): bool
    {
        return Schema::hasTable('gi405_singlerow') && $this->query($period)->exists();
    }

    public function state(string $period): array
    {
        $query = $this->query($period)
            ->selectRaw('COUNT(*) as row_count, COALESCE(SUM(gr.ending_balance), 0) as sum_ending_balance');
        foreach (['updated_at', 'created_at', 'uniqueid_namareport'] as $column) {
            if (Schema::hasColumn('gi405_singlerow', $column)) {
                $query->selectRaw("MAX(gr.{$column}) as max_{$column}");
            }
        }
        $state = (array) $query->first();
        ksort($state);

        return $state;
    }
}
