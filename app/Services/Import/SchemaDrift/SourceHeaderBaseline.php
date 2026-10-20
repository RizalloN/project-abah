<?php

namespace App\Services\Import\SchemaDrift;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * The set of source headers a report normally carries. The first analysed
 * document of a table becomes its baseline, so columns the import has always
 * ignored are never announced as "new"; only a header that later appears on
 * top of that baseline asks for an accept/reject decision.
 */
class SourceHeaderBaseline
{
    public const TABLE = 'import_source_header_baselines';

    /** @var array<string, array<string, object>> */
    private array $runtime = [];

    public function isAvailable(): bool
    {
        try {
            return Schema::hasTable(self::TABLE);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, object> keyed by header key
     */
    public function forTable(string $table): array
    {
        $table = strtolower(trim($table));
        if (array_key_exists($table, $this->runtime)) {
            return $this->runtime[$table];
        }

        if ($table === '' || !$this->isAvailable()) {
            return $this->runtime[$table] = [];
        }

        try {
            $rows = DB::table(self::TABLE)->where('table_name', $table)->get();
        } catch (Throwable) {
            return $this->runtime[$table] = [];
        }

        $byKey = [];
        foreach ($rows as $row) {
            $byKey[strtolower((string) $row->header_key)] = $row;
        }

        return $this->runtime[$table] = $byKey;
    }

    /**
     * A table has a baseline once any document was recorded for it. Before
     * that, every unknown header is treated as an existing (ignored) column.
     */
    public function isEstablished(string $table): bool
    {
        return $this->forTable($table) !== [];
    }

    /**
     * @return array<int, string>
     */
    public function knownKeys(string $table): array
    {
        return array_keys($this->forTable($table));
    }

    /**
     * Records the source columns of an analysed document. New headers are only
     * absorbed while the baseline is being established or when the caller says
     * they were decided (accepted/rejected); pending ones stay "new" until then.
     *
     * @param array<int, array<string, mixed>> $columns detector column entries
     * @param array<int, string> $absorbKeys additional header keys to absorb
     */
    public function record(string $table, array $columns, array $absorbKeys = []): void
    {
        $table = strtolower(trim($table));
        if ($table === '' || !$this->isAvailable()) {
            return;
        }

        $known = $this->forTable($table);
        $establishing = $known === [];
        $absorb = array_fill_keys(array_map('strtolower', $absorbKeys), true);
        $now = now();

        try {
            foreach ($columns as $column) {
                $key = strtolower(trim((string) ($column['canonical'] ?? '')));
                $state = (string) ($column['state'] ?? '');
                if ($key === '' || preg_match('/^col_\d+$/', $key) === 1) {
                    continue;
                }

                $isKnown = isset($known[$key]);
                // A pending "new" column stays outside the baseline until decided.
                if (!$isKnown && !$establishing && !isset($absorb[$key]) && !in_array($state, ['matched', 'unmapped', 'rejected'], true)) {
                    continue;
                }

                $values = [
                    'header_label' => mb_substr((string) ($column['header'] ?? $key), 0, 255),
                    'db_column' => isset($column['db_column']) && $column['db_column'] !== null ? mb_substr((string) $column['db_column'], 0, 128) : null,
                    'signature' => isset($column['signature']) ? mb_substr((string) $column['signature'], 0, 120) : null,
                    'label' => isset($column['label']) ? mb_substr((string) $column['label'], 0, 120) : null,
                    'example' => ($column['example'] ?? $column['samples'][0] ?? null) !== null ? mb_substr((string) ($column['example'] ?? $column['samples'][0]), 0, 255) : null,
                    'last_seen_at' => $now,
                    'updated_at' => $now,
                ];

                if ($isKnown) {
                    // An empty sample never overwrites a known format.
                    if (($column['kind'] ?? '') === 'empty') {
                        unset($values['signature'], $values['label'], $values['example']);
                    }
                    DB::table(self::TABLE)
                        ->where('table_name', $table)
                        ->where('header_key', $key)
                        ->update($values + ['occurrences' => DB::raw('occurrences + 1')]);
                    continue;
                }

                DB::table(self::TABLE)->insertOrIgnore($values + [
                    'table_name' => $table,
                    'header_key' => mb_substr($key, 0, 128),
                    'occurrences' => 1,
                    'first_seen_at' => $now,
                    'created_at' => $now,
                ]);
            }
        } catch (Throwable $e) {
            Log::warning('Import source header baseline could not be stored.', ['table' => $table, 'error' => $e->getMessage()]);
        } finally {
            unset($this->runtime[$table]);
        }
    }

    /**
     * Marks decided headers (accepted or rejected) as part of the baseline.
     *
     * @param array<int, array{key: string, label?: string|null, db_column?: string|null}> $headers
     */
    public function absorb(string $table, array $headers): void
    {
        $table = strtolower(trim($table));
        if ($table === '' || $headers === [] || !$this->isAvailable()) {
            return;
        }

        $now = now();
        try {
            foreach ($headers as $header) {
                $key = strtolower(trim((string) ($header['key'] ?? '')));
                if ($key === '') {
                    continue;
                }

                $existing = DB::table(self::TABLE)->where('table_name', $table)->where('header_key', $key)->exists();
                $values = [
                    'header_label' => isset($header['label']) ? mb_substr((string) $header['label'], 0, 255) : $key,
                    'db_column' => $header['db_column'] ?? null,
                    'last_seen_at' => $now,
                    'updated_at' => $now,
                ];

                $existing
                    ? DB::table(self::TABLE)->where('table_name', $table)->where('header_key', $key)->update($values)
                    : DB::table(self::TABLE)->insertOrIgnore($values + [
                        'table_name' => $table,
                        'header_key' => mb_substr($key, 0, 128),
                        'occurrences' => 1,
                        'first_seen_at' => $now,
                        'created_at' => $now,
                    ]);
            }
        } catch (Throwable $e) {
            Log::warning('Import source header baseline could not absorb decided headers.', ['table' => $table, 'error' => $e->getMessage()]);
        } finally {
            unset($this->runtime[$table]);
        }
    }
}
