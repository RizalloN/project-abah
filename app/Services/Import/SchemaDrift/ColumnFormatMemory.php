<?php

namespace App\Services\Import\SchemaDrift;

use App\Support\Import\ColumnValueProfiler;
use App\Support\StrictDateParser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Remembers the last observed source format of every imported column. It is the
 * baseline for "the source changed" notifications and the source of date-order
 * hints used while parsing (for example PERIODE = month/day with a clock).
 */
class ColumnFormatMemory
{
    public const TABLE = 'import_column_format_profiles';
    private const CACHE_TTL_SECONDS = 300;

    /** @var array<string, array<string, object>> */
    private array $runtime = [];

    /**
     * @return array<string, object> keyed by lower-case column name
     */
    public function forTable(string $table): array
    {
        $table = strtolower(trim($table));
        if ($table === '') {
            return [];
        }

        if (array_key_exists($table, $this->runtime)) {
            return $this->runtime[$table];
        }

        try {
            if (!Schema::hasTable(self::TABLE)) {
                return $this->runtime[$table] = [];
            }

            $rows = Cache::remember($this->cacheKey($table), self::CACHE_TTL_SECONDS, function () use ($table): array {
                return DB::table(self::TABLE)
                    ->where('table_name', $table)
                    ->get()
                    ->map(fn ($row) => (array) $row)
                    ->all();
            });
        } catch (Throwable) {
            return $this->runtime[$table] = [];
        }

        $byColumn = [];
        foreach ((array) $rows as $row) {
            $row = (object) $row;
            $byColumn[strtolower((string) $row->column_name)] = $row;
        }

        return $this->runtime[$table] = $byColumn;
    }

    public function previous(string $table, string $column): ?object
    {
        return $this->forTable($table)[strtolower($column)] ?? null;
    }

    /**
     * Column-level date order for parsing. A conflicting column (both orders seen)
     * returns null so every value falls back to its own evidence.
     */
    public function dateOrderFor(string $table, string $column): ?string
    {
        $row = $this->previous($table, $column);
        if ($row === null) {
            return null;
        }

        $order = (string) ($row->date_order ?? '');
        $basis = (string) ($row->date_order_basis ?? '');

        return in_array($order, [StrictDateParser::ORDER_DAY_FIRST, StrictDateParser::ORDER_MONTH_FIRST], true)
            && $basis !== ColumnValueProfiler::ORDER_CONFLICT
            ? $order
            : null;
    }

    /**
     * @return array<string, string> lower-case column => dmy|mdy
     */
    public function dateOrders(string $table): array
    {
        $orders = [];
        foreach (array_keys($this->forTable($table)) as $column) {
            $order = $this->dateOrderFor($table, $column);
            if ($order !== null) {
                $orders[$column] = $order;
            }
        }

        return $orders;
    }

    /**
     * Stores the observed profile. Columns without data never overwrite a known
     * format, and a convention-only date order never overwrites proven evidence.
     */
    public function remember(string $table, string $column, ?string $sourceHeader, array $profile): void
    {
        $table = strtolower(trim($table));
        $column = strtolower(trim($column));
        $kind = (string) ($profile['kind'] ?? ColumnValueProfiler::KIND_EMPTY);
        if ($table === '' || $column === '' || $kind === ColumnValueProfiler::KIND_EMPTY) {
            return;
        }

        try {
            if (!Schema::hasTable(self::TABLE)) {
                return;
            }

            $previous = $this->previous($table, $column);
            $dateOrder = $profile['date']['order'] ?? null;
            $dateBasis = $profile['date']['order_basis'] ?? null;
            if ($previous !== null
                && $dateBasis === ColumnValueProfiler::ORDER_CONVENTION
                && (string) ($previous->date_order_basis ?? '') === ColumnValueProfiler::ORDER_EVIDENCE
                && (string) ($previous->signature ?? '') === (string) ($profile['signature'] ?? '')) {
                $dateOrder = $previous->date_order;
                $dateBasis = $previous->date_order_basis;
            }

            $now = now();
            $values = [
                'source_header' => $sourceHeader !== null ? mb_substr($sourceHeader, 0, 255) : null,
                'kind' => $kind,
                'signature' => mb_substr((string) ($profile['signature'] ?? $kind), 0, 120),
                'label' => mb_substr((string) ($profile['label'] ?? ''), 0, 120),
                'date_order' => $dateOrder,
                'date_order_basis' => $dateBasis,
                'has_time' => (bool) ($profile['date']['has_time'] ?? false),
                'example' => ($profile['example'] ?? $profile['samples'][0] ?? null) !== null ? mb_substr((string) ($profile['example'] ?? $profile['samples'][0]), 0, 255) : null,
                'updated_at' => $now,
            ];

            if ($previous === null) {
                DB::table(self::TABLE)->insert($values + [
                    'table_name' => $table,
                    'column_name' => $column,
                    'observations' => 1,
                    'created_at' => $now,
                ]);
            } else {
                DB::table(self::TABLE)
                    ->where('table_name', $table)
                    ->where('column_name', $column)
                    ->update($values + ['observations' => DB::raw('observations + 1')]);
            }
        } catch (Throwable $e) {
            Log::warning('Import column format memory could not be stored.', [
                'table' => $table,
                'column' => $column,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function flush(string $table): void
    {
        $table = strtolower(trim($table));
        unset($this->runtime[$table]);

        try {
            Cache::forget($this->cacheKey($table));
        } catch (Throwable) {
            // Best effort; the runtime cache was already cleared.
        }
    }

    private function cacheKey(string $table): string
    {
        return 'import_column_format_memory:v1:' . $table;
    }
}
