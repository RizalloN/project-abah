<?php

namespace App\Services\ControlCenter;

use App\Jobs\ProcessShadowBackfillJob;
use App\Services\Import\SchemaIntrospectionService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * Health of Daily Loan shadow columns. Two generations exist side by side:
 * legacy columns filled by the backfill job (read by most dashboards) and
 * STORED GENERATED *_gc siblings the database always keeps exact. Because the
 * generated sibling is deterministic, comparing both per period proves whether
 * the legacy values dashboards read are complete and correct.
 */
class ShadowColumnHealthService
{
    public const TABLE = 'daily_loan_dinamis';
    public const PERIOD_COLUMN = 'periode';
    private const CACHE_KEY = 'control-center:shadow-health:v1';
    private const CACHE_SECONDS = 600;
    public const MAX_VERIFY_PERIODS = 7;

    /** legacy column => generated sibling */
    public const PAIRS = [
        'cabang_normalized' => 'cabang_normalized_gc',
        'unit_normalized' => 'unit_normalized_gc',
        'branch_normalized' => 'branch_normalized_gc',
        'rm_normalized' => 'rm_normalized_gc',
        'pn_pemutus_normalized' => 'pn_pemutus_normalized_gc',
        'cifno_clean' => 'cifno_clean_gc',
    ];

    /** Derived classification columns that must never stay empty. */
    public const DERIVED = ['segmen_kinerja', 'produk_kinerja'];

    public function __construct(private readonly SchemaIntrospectionService $schema)
    {
    }

    public function snapshot(bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget(self::CACHE_KEY);
        }

        return Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, function (): array {
            $layout = $this->layout();
            if (!$layout['available']) {
                return $layout + ['generated_at' => now()->toIso8601String(), 'periods' => [], 'backfill' => $this->backfillTelemetry()];
            }

            $latest = DB::table(self::TABLE)->max(self::PERIOD_COLUMN);

            return $layout + [
                'generated_at' => now()->toIso8601String(),
                'periods' => $latest !== null ? [$this->inspectPeriod((string) $latest, $layout)] : [],
                'backfill' => $this->backfillTelemetry(),
            ];
        });
    }

    /**
     * Parity for the newest periods (on demand; each period costs ~1s).
     *
     * @return array<int, array<string, mixed>>
     */
    public function verifyRecent(int $count = 3): array
    {
        $layout = $this->layout();
        if (!$layout['available']) {
            return [];
        }

        $count = max(1, min(self::MAX_VERIFY_PERIODS, $count));
        $periods = DB::table(self::TABLE)
            ->select(self::PERIOD_COLUMN)
            ->distinct()
            ->orderByDesc(self::PERIOD_COLUMN)
            ->limit($count)
            ->pluck(self::PERIOD_COLUMN)
            ->all();

        return array_map(fn ($period): array => $this->inspectPeriod((string) $period, $layout), $periods);
    }

    /**
     * Queue the official backfill for periods with unbuilt or drifted rows.
     *
     * @param array<int, string> $periods
     */
    public function queueRepair(array $periods): array
    {
        $periods = array_values(array_unique(array_filter(array_map(static function ($period): ?string {
            $period = trim((string) $period);

            return preg_match('/^\d{4}-\d{2}-\d{2}$/', $period) === 1 ? $period : null;
        }, $periods))));

        if ($periods === []) {
            throw new RuntimeException('Pilih minimal satu periode valid (YYYY-MM-DD).');
        }
        if (count($periods) > self::MAX_VERIFY_PERIODS) {
            throw new RuntimeException('Maksimal ' . self::MAX_VERIFY_PERIODS . ' periode per perbaikan.');
        }

        ProcessShadowBackfillJob::dispatch($periods);
        Cache::forget(self::CACHE_KEY);
        Log::notice('Shadow column repair queued from Job Management.', ['periods' => $periods]);

        return ['queued_periods' => $periods, 'queue' => (string) config('queue.shadow_backfill_queue', 'shadow-backfill')];
    }

    private function layout(): array
    {
        if (!$this->schema->hasTable(self::TABLE)) {
            return ['available' => false, 'pairs' => [], 'derived' => [], 'has_built_marker' => false];
        }

        $meta = $this->schema->getColumnMetadata(self::TABLE);
        $pairs = [];
        foreach (self::PAIRS as $legacy => $generated) {
            if (isset($meta[$legacy], $meta[$generated])) {
                $pairs[$legacy] = $generated;
            }
        }

        return [
            'available' => $pairs !== [],
            'table' => self::TABLE,
            'pairs' => $pairs,
            'derived' => array_values(array_filter(self::DERIVED, static fn (string $column): bool => isset($meta[$column]))),
            'has_built_marker' => isset($meta['shadow_built_at']),
        ];
    }

    private function inspectPeriod(string $period, array $layout): array
    {
        $startedAt = microtime(true);
        $selects = ['COUNT(*) AS total_rows'];
        if ($layout['has_built_marker']) {
            $selects[] = 'SUM(shadow_built_at IS NULL) AS unbuilt_rows';
        }
        foreach ($layout['pairs'] as $legacy => $generated) {
            $selects[] = "SUM(NOT (`{$legacy}` <=> `{$generated}`)) AS `mm_{$legacy}`";
        }
        foreach ($layout['derived'] as $column) {
            $selects[] = "SUM(`{$column}` IS NULL OR `{$column}` = '') AS `null_{$column}`";
        }

        $row = (array) DB::table(self::TABLE)
            ->selectRaw(implode(', ', $selects))
            ->where(self::PERIOD_COLUMN, $period)
            ->first();

        $total = (int) ($row['total_rows'] ?? 0);
        $mismatches = [];
        foreach (array_keys($layout['pairs']) as $legacy) {
            $mismatches[$legacy] = (int) ($row["mm_{$legacy}"] ?? 0);
        }
        $empties = [];
        foreach ($layout['derived'] as $column) {
            $empties[$column] = (int) ($row["null_{$column}"] ?? 0);
        }
        $unbuilt = (int) ($row['unbuilt_rows'] ?? 0);
        $worst = max([0, $unbuilt, ...array_values($mismatches), ...array_values($empties)]);
        $parity = $total > 0 ? round(100 * (1 - ($worst / $total)), 3) : 100.0;

        return [
            'period' => substr($period, 0, 10),
            'total_rows' => $total,
            'unbuilt_rows' => $unbuilt,
            'mismatches' => $mismatches,
            'empty_derived' => $empties,
            'parity_percent' => $parity,
            'status' => $total === 0 ? 'info' : ($worst === 0 ? 'ok' : ($parity >= 99.5 ? 'warn' : 'fail')),
            'checked_in_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ];
    }

    private function backfillTelemetry(): array
    {
        $telemetry = ['recent_failures' => 0, 'last_failure' => null, 'pending_jobs' => 0];

        try {
            if (Schema::hasTable('shadow_backfill_failures')) {
                $failureColumns = $this->schema->getColumnListing('shadow_backfill_failures');
                $timeColumn = in_array('created_at', $failureColumns, true) ? 'created_at' : null;
                if ($timeColumn !== null) {
                    $telemetry['recent_failures'] = (int) DB::table('shadow_backfill_failures')->where($timeColumn, '>=', now()->subDays(7))->count();
                    $telemetry['last_failure'] = DB::table('shadow_backfill_failures')->max($timeColumn);
                }
            }
            if (Schema::hasTable('jobs')) {
                $telemetry['pending_jobs'] = (int) DB::table('jobs')->where('queue', (string) config('queue.shadow_backfill_queue', 'shadow-backfill'))->count();
            }
        } catch (Throwable) {
            // Telemetry is informative only.
        }

        return $telemetry;
    }
}
