<?php

namespace App\Services\Import\SchemaDrift;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * Persists schema drift events (the notification feed and audit trail) and
 * executes accept/reject decisions:
 * - add: a new nullable column filled by the import from the new header
 * - map: a renamed header routed onto an existing column (an alias, no DDL)
 * - widen: a column grown so source values stop being truncated/overflowing
 */
class SchemaDriftRegistry
{
    public const TABLE = 'import_schema_drift_events';

    public const KIND_NEW_COLUMNS = 'new_columns';
    public const KIND_FORMAT_CHANGE = 'format_change';
    public const KIND_INCOMPATIBLE = 'incompatibility';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPLIED = 'applied';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_AUTO_ADAPTED = 'auto_adapted';
    public const STATUS_ATTENTION = 'attention';
    public const STATUS_FAILED = 'failed';
    public const STATUS_RESOLVED = 'resolved';

    public const ACTION_ADD = 'add';
    public const ACTION_MAP = 'map';
    public const ACTION_WIDEN = 'widen';
    public const ACTION_SKIP = 'skip';

    /** @var array<string, array<string, string>> */
    private array $aliasCache = [];

    public function __construct(private readonly SchemaEvolutionService $evolution)
    {
    }

    public function isAvailable(): bool
    {
        try {
            return Schema::hasTable(self::TABLE);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Records the report when it contains something worth notifying. Returns the
     * event row (with id) or null when the source matched perfectly.
     */
    public function record(array $report, ?string $sourceFile = null): ?object
    {
        if (!$this->isAvailable()) {
            return null;
        }

        $kind = $this->resolveKind($report);
        if ($kind === null) {
            return null;
        }

        $table = (string) $report['table'];
        $fingerprint = (string) $report['fingerprint'];
        if ($kind === self::KIND_INCOMPATIBLE) {
            $fingerprint = hash('sha256', $fingerprint . '|' . json_encode(array_map(
                static fn (array $item): string => ($item['column'] ?? '') . ':' . ($item['code'] ?? ''),
                array_values(array_filter((array) $report['adaptations'], static fn (array $item): bool => in_array($item['severity'] ?? '', [SchemaDriftDetector::SEVERITY_DANGER, SchemaDriftDetector::SEVERITY_WARNING], true)))
            )));
        }

        $now = now();
        $payload = json_encode($this->compactPayload($report), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        $summary = mb_substr($this->summary($kind, $report), 0, 500);

        $existing = DB::table(self::TABLE)->where('table_name', $table)->where('fingerprint', $fingerprint)->first();
        if ($existing !== null) {
            $update = [
                'summary' => $summary,
                'source_file' => $sourceFile !== null ? mb_substr($sourceFile, 0, 500) : $existing->source_file,
                'occurrences' => DB::raw('occurrences + 1'),
                'last_seen_at' => $now,
                'updated_at' => $now,
            ];
            // A decided event keeps the payload it was decided on (audit trail).
            if (in_array($existing->status, [self::STATUS_PENDING, self::STATUS_AUTO_ADAPTED, self::STATUS_ATTENTION], true)) {
                $update['payload'] = $payload;
            }
            if (in_array($existing->status, [self::STATUS_AUTO_ADAPTED, self::STATUS_ATTENTION], true)) {
                $update['acknowledged_at'] = null;
            }
            DB::table(self::TABLE)->where('id', $existing->id)->update($update);

            return $this->find((int) $existing->id);
        }

        $inserted = DB::table(self::TABLE)->insertOrIgnore([
            'table_name' => $table,
            'report_label' => isset($report['report_label']) ? mb_substr((string) $report['report_label'], 0, 255) : null,
            'fingerprint' => $fingerprint,
            'kind' => $kind,
            'status' => match ($kind) {
                self::KIND_NEW_COLUMNS => self::STATUS_PENDING,
                self::KIND_FORMAT_CHANGE => self::STATUS_AUTO_ADAPTED,
                default => self::STATUS_ATTENTION,
            },
            'summary' => $summary,
            'payload' => $payload,
            'source_file' => $sourceFile !== null ? mb_substr($sourceFile, 0, 500) : null,
            'occurrences' => 1,
            'first_seen_at' => $now,
            'last_seen_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Two analyses of the same upload can race on the unique fingerprint;
        // the loser simply reads (and counts) the event the winner created.
        $row = DB::table(self::TABLE)->where('table_name', $table)->where('fingerprint', $fingerprint)->first();
        if ($row !== null && $inserted === 0) {
            DB::table(self::TABLE)->where('id', $row->id)->update([
                'occurrences' => DB::raw('occurrences + 1'),
                'last_seen_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return $row ? $this->find((int) $row->id) : null;
    }

    /**
     * Closes pending decisions that the current document proves are no longer
     * open: their new headers now land in a column (added or mapped elsewhere)
     * and their widenings are no longer needed.
     *
     * @return array<int, int> ids of the events that were resolved
     */
    public function reconcile(array $report, ?int $exceptEventId = null): array
    {
        if (!$this->isAvailable()) {
            return [];
        }

        $table = strtolower((string) ($report['table'] ?? ''));
        $matchedHeaders = [];
        $sourceHeaders = [];
        foreach ((array) ($report['columns'] ?? []) as $column) {
            $key = strtolower((string) ($column['canonical'] ?? ''));
            if ($key === '') {
                continue;
            }
            $sourceHeaders[$key] = true;
            if (($column['state'] ?? '') === 'matched') {
                $matchedHeaders[$key] = true;
            }
        }
        $stillAdjusting = [];
        foreach ((array) ($report['adjustments'] ?? []) as $adjustment) {
            if (($adjustment['decision'] ?? '') === 'pending') {
                $stillAdjusting[strtolower((string) $adjustment['column'])] = true;
            }
        }

        $resolved = [];
        $events = DB::table(self::TABLE)->where('table_name', $table)->where('status', self::STATUS_PENDING)->get();
        foreach ($events as $row) {
            if ($exceptEventId !== null && (int) $row->id === $exceptEventId) {
                continue;
            }

            $payload = (array) json_decode((string) ($row->payload ?? '{}'), true);
            $open = false;
            $evidence = false;
            foreach ((array) ($payload['new_columns'] ?? []) as $column) {
                if (($column['decision'] ?? 'pending') !== 'pending') {
                    continue;
                }
                $key = strtolower((string) ($column['canonical'] ?? $column['proposed_column'] ?? ''));
                if (!isset($sourceHeaders[$key])) {
                    // This document does not carry the header: no evidence either way.
                    $open = true;
                    continue;
                }
                $evidence = true;
                if (!isset($matchedHeaders[$key])) {
                    $open = true;
                }
            }
            foreach ((array) ($payload['adjustments'] ?? []) as $adjustment) {
                if (($adjustment['decision'] ?? 'pending') !== 'pending') {
                    continue;
                }
                $evidence = true;
                if (isset($stillAdjusting[strtolower((string) $adjustment['column'])])) {
                    $open = true;
                }
            }

            if ($open || !$evidence) {
                continue;
            }

            DB::table(self::TABLE)->where('id', $row->id)->where('status', self::STATUS_PENDING)->update([
                'status' => self::STATUS_RESOLVED,
                'decided_by' => 'Sistem (struktur sudah sesuai)',
                'decided_at' => now(),
                'acknowledged_at' => now(),
                'updated_at' => now(),
            ]);
            $resolved[] = (int) $row->id;
        }

        return $resolved;
    }

    public function find(int $id): ?object
    {
        if (!$this->isAvailable()) {
            return null;
        }

        $row = DB::table(self::TABLE)->where('id', $id)->first();

        return $row ? $this->hydrate($row) : null;
    }

    /**
     * @return array<int, string> rejected new-column names and "adjust:<column>" keys
     */
    public function rejectedColumns(string $table): array
    {
        if (!$this->isAvailable()) {
            return [];
        }

        $names = [];
        DB::table(self::TABLE)
            ->where('table_name', strtolower($table))
            ->whereNotNull('rejected_columns')
            ->pluck('rejected_columns')
            ->each(function ($json) use (&$names): void {
                foreach ((array) json_decode((string) $json, true) as $name) {
                    $names[strtolower((string) $name)] = true;
                }
            });

        return array_keys($names);
    }

    /**
     * Columns that were physically added to the table by an accepted decision.
     *
     * @return array<int, string>
     */
    public function evolvedColumns(string $table): array
    {
        $names = [];
        foreach ($this->appliedEntries($table) as $entry) {
            if (($entry['action'] ?? self::ACTION_ADD) === self::ACTION_ADD && is_string($entry['name'] ?? null) && $entry['name'] !== '') {
                $names[strtolower($entry['name'])] = true;
            }
        }

        return array_keys($names);
    }

    /**
     * Source header key => table column for every accepted decision whose
     * column name differs from the header (a mapped rename, or a new column the
     * admin renamed). Importers consult this before their name-based mapping.
     *
     * @return array<string, string>
     */
    public function columnAliases(string $table): array
    {
        $table = strtolower(trim($table));
        if (array_key_exists($table, $this->aliasCache)) {
            return $this->aliasCache[$table];
        }

        $aliases = [];
        foreach ($this->appliedEntries($table) as $entry) {
            $action = $entry['action'] ?? self::ACTION_ADD;
            $name = strtolower(trim((string) ($entry['name'] ?? '')));
            $key = strtolower(trim((string) ($entry['source_key'] ?? '')));
            if ($key === '' && !empty($entry['source_header'])) {
                $key = trim(strtolower((string) preg_replace('/[^a-z0-9]+/i', '_', (string) $entry['source_header'])), '_');
            }
            if (!in_array($action, [self::ACTION_ADD, self::ACTION_MAP], true) || $name === '' || $key === '' || $key === $name) {
                continue;
            }
            $aliases[$key] = $name;
        }

        return $this->aliasCache[$table] = $aliases;
    }

    /**
     * @param array<int, array{proposed_column: string, action?: string, name?: string, type?: string, target?: string, include?: bool}> $selections
     * @param array<int, array{column: string, to_type?: string, include?: bool}> $adjustmentSelections
     */
    public function accept(int $id, string $actor, array $selections = [], array $adjustmentSelections = []): object
    {
        $event = $this->requirePending($id);
        $table = (string) $event->table_name;
        $canEvolve = app(SchemaEvolutionPolicy::class)->canEvolve($table);

        $selectionMap = [];
        foreach ($selections as $selection) {
            $key = strtolower((string) ($selection['proposed_column'] ?? ''));
            if ($key !== '') {
                $selectionMap[$key] = $selection;
            }
        }
        $adjustmentMap = [];
        foreach ($adjustmentSelections as $selection) {
            $key = strtolower((string) ($selection['column'] ?? ''));
            if ($key !== '') {
                $adjustmentMap[$key] = $selection;
            }
        }

        $toAdd = [];
        $toMap = [];
        $excluded = [];
        foreach ($this->pendingColumns($event) as $column) {
            $key = strtolower((string) $column['proposed_column']);
            $selection = $selectionMap[$key] ?? [];
            $action = $this->resolveAction($selection, $column, $canEvolve);
            $sourceKey = strtolower((string) ($column['canonical'] ?? $key));

            if ($action === self::ACTION_SKIP) {
                $excluded[] = $key;
                continue;
            }
            if (!$canEvolve) {
                throw new RuntimeException(
                    'Report ini memakai layout import kolom tetap, jadi kolom baru tidak akan terisi walaupun ditambahkan. '
                    . 'Pilih "Lewati" untuk kolom baru — data tetap masuk tanpa kolom tersebut.'
                );
            }

            if ($action === self::ACTION_MAP) {
                $toMap[] = [
                    'name' => strtolower(trim((string) ($selection['target'] ?? $column['suggestion']['column'] ?? ''))),
                    'source_header' => $column['header'] ?? null,
                    'source_key' => $sourceKey,
                ];
                continue;
            }

            $toAdd[] = [
                'name' => strtolower(trim((string) ($selection['name'] ?? $column['proposed_column']))),
                'type' => strtoupper(trim((string) ($selection['type'] ?? $column['proposed_type']))),
                'source_header' => $column['header'] ?? null,
                'source_key' => $sourceKey,
            ];
        }

        $toWiden = [];
        foreach ($this->pendingAdjustments($event) as $adjustment) {
            $column = strtolower((string) $adjustment['column']);
            $selection = $adjustmentMap[$column] ?? [];
            if (array_key_exists('include', $selection) && !filter_var($selection['include'], FILTER_VALIDATE_BOOL)) {
                $excluded[] = 'adjust:' . $column;
                continue;
            }
            $toWiden[] = [
                'name' => $column,
                'type' => strtoupper(trim((string) ($selection['to_type'] ?? $adjustment['to_type']))),
                'from_type' => $adjustment['from_type'] ?? null,
            ];
        }

        $applied = [];
        try {
            // Mapping and widening are validated before any column is added, so a
            // bad selection never leaves the table half-evolved.
            $mapped = $this->evolution->validateMappings($table, $toMap);
            $widened = $toWiden === [] ? [] : $this->evolution->widenColumns($table, $toWiden);
            $added = $toAdd === [] ? [] : $this->evolution->addColumns($table, $toAdd);

            foreach ($added as $column) {
                $requested = collect($toAdd)->firstWhere('name', $column['name']) ?? [];
                $applied[] = $column + [
                    'action' => self::ACTION_ADD,
                    'source_header' => $requested['source_header'] ?? null,
                    'source_key' => $requested['source_key'] ?? null,
                ];
            }
            foreach ($mapped as $column) {
                $applied[] = $column + ['action' => self::ACTION_MAP, 'algorithm' => 'alias'];
            }
            foreach ($widened as $column) {
                $applied[] = $column + ['action' => self::ACTION_WIDEN];
            }
        } catch (Throwable $e) {
            // Only a still-open decision becomes "failed": a concurrent accept
            // that already applied the change must not be overwritten.
            DB::table(self::TABLE)->where('id', $id)->where('status', self::STATUS_PENDING)->update([
                'status' => self::STATUS_FAILED,
                'error' => mb_substr($e->getMessage(), 0, 2000),
                'decided_by' => mb_substr($actor, 0, 150),
                'decided_at' => now(),
                'updated_at' => now(),
            ]);

            throw $e instanceof RuntimeException ? $e : new RuntimeException($e->getMessage(), 0, $e);
        }

        DB::table(self::TABLE)->where('id', $id)->update([
            'status' => $applied === [] ? self::STATUS_REJECTED : self::STATUS_APPLIED,
            'applied_columns' => json_encode($applied, JSON_UNESCAPED_UNICODE),
            'rejected_columns' => $excluded === [] ? null : json_encode(array_values(array_unique($excluded))),
            'decided_by' => mb_substr($actor, 0, 150),
            'decided_at' => now(),
            'acknowledged_at' => now(),
            'error' => null,
            'updated_at' => now(),
        ]);

        unset($this->aliasCache[strtolower($table)]);
        app(ColumnFormatMemory::class)->flush($table);
        Log::info('Import schema drift accepted.', ['event' => $id, 'table' => $table, 'applied' => $applied, 'excluded' => $excluded, 'actor' => $actor]);

        return $this->find($id);
    }

    public function reject(int $id, string $actor): object
    {
        $event = $this->requirePending($id);
        $rejected = array_merge(
            array_map(static fn (array $column): string => strtolower((string) $column['proposed_column']), $this->pendingColumns($event)),
            array_map(static fn (array $adjustment): string => 'adjust:' . strtolower((string) $adjustment['column']), $this->pendingAdjustments($event))
        );

        DB::table(self::TABLE)->where('id', $id)->update([
            'status' => self::STATUS_REJECTED,
            'rejected_columns' => json_encode(array_values(array_unique($rejected))),
            'decided_by' => mb_substr($actor, 0, 150),
            'decided_at' => now(),
            'acknowledged_at' => now(),
            'updated_at' => now(),
        ]);

        Log::info('Import schema drift rejected.', ['event' => $id, 'table' => $event->table_name, 'columns' => $rejected, 'actor' => $actor]);

        return $this->find($id);
    }

    public function acknowledge(int $id, string $actor): ?object
    {
        if (!$this->isAvailable()) {
            return null;
        }

        $query = DB::table(self::TABLE)->where('id', $id)->where('status', '<>', self::STATUS_PENDING);
        (clone $query)->update(['acknowledged_at' => now(), 'updated_at' => now()]);
        (clone $query)->whereNull('decided_by')->update(['decided_by' => mb_substr($actor, 0, 150)]);

        return $this->find($id);
    }

    /**
     * Notification feed: pending decisions first, then unread automatic adaptations.
     */
    public function notifications(int $limit = 20): array
    {
        if (!$this->isAvailable()) {
            return ['count' => 0, 'pending' => 0, 'items' => []];
        }

        $query = DB::table(self::TABLE)->where(function ($query): void {
            $query->where('status', self::STATUS_PENDING)
                ->orWhere(function ($query): void {
                    $query->whereIn('status', [self::STATUS_AUTO_ADAPTED, self::STATUS_ATTENTION, self::STATUS_FAILED])
                        ->whereNull('acknowledged_at');
                });
        });

        $count = (clone $query)->count();
        $pending = DB::table(self::TABLE)->where('status', self::STATUS_PENDING)->count();
        $items = $query
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('last_seen_at')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => $this->presentNotification($this->hydrate($row)))
            ->all();

        return ['count' => $count, 'pending' => $pending, 'items' => $items];
    }

    public function history(string $table, int $limit = 10): array
    {
        if (!$this->isAvailable()) {
            return [];
        }

        return DB::table(self::TABLE)
            ->where('table_name', strtolower($table))
            ->orderByDesc('last_seen_at')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => $this->presentNotification($this->hydrate($row)))
            ->all();
    }

    public function presentNotification(object $event): array
    {
        return [
            'id' => (int) $event->id,
            'table' => $event->table_name,
            'report_label' => $event->report_label ?: $event->table_name,
            'kind' => $event->kind,
            'status' => $event->status,
            'evolution_mode' => app(SchemaEvolutionPolicy::class)->modeFor((string) $event->table_name),
            'summary' => $event->summary,
            'occurrences' => (int) $event->occurrences,
            'last_seen_at' => $event->last_seen_at,
            'decided_by' => $event->decided_by,
            'decided_at' => $event->decided_at,
            'error' => $event->error,
            'new_columns' => array_values((array) ($event->payload['new_columns'] ?? [])),
            'adjustments' => array_values((array) ($event->payload['adjustments'] ?? [])),
            'adaptations' => array_values((array) ($event->payload['adaptations'] ?? [])),
            'applied_columns' => array_values((array) json_decode((string) ($event->applied_columns ?? '[]'), true)),
            'rejected_columns' => array_values((array) json_decode((string) ($event->rejected_columns ?? '[]'), true)),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function appliedEntries(string $table): array
    {
        if (!$this->isAvailable()) {
            return [];
        }

        $entries = [];
        try {
            DB::table(self::TABLE)
                ->where('table_name', strtolower($table))
                ->where('status', self::STATUS_APPLIED)
                ->orderBy('decided_at')
                ->pluck('applied_columns')
                ->each(function ($json) use (&$entries): void {
                    foreach ((array) json_decode((string) $json, true) as $entry) {
                        $entries[] = is_array($entry) ? $entry : ['name' => (string) $entry, 'action' => self::ACTION_ADD];
                    }
                });
        } catch (Throwable) {
            return [];
        }

        return $entries;
    }

    private function resolveAction(array $selection, array $column, bool $canEvolve): string
    {
        if (array_key_exists('include', $selection) && !filter_var($selection['include'], FILTER_VALIDATE_BOOL)) {
            return self::ACTION_SKIP;
        }

        $requested = strtolower(trim((string) ($selection['action'] ?? '')));
        if (in_array($requested, [self::ACTION_ADD, self::ACTION_MAP, self::ACTION_SKIP], true)) {
            return $requested;
        }

        // Without an explicit choice a fixed-layout report falls through to
        // "add", which accept() refuses with an explanation instead of silently
        // skipping the column.
        return ($column['suggestion']['action'] ?? null) === self::ACTION_MAP ? self::ACTION_MAP : self::ACTION_ADD;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function pendingColumns(object $event): array
    {
        return array_values(array_filter(
            (array) ($event->payload['new_columns'] ?? []),
            static fn (array $column): bool => ($column['decision'] ?? 'pending') === 'pending'
        ));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function pendingAdjustments(object $event): array
    {
        return array_values(array_filter(
            (array) ($event->payload['adjustments'] ?? []),
            static fn (array $adjustment): bool => ($adjustment['decision'] ?? 'pending') === 'pending'
        ));
    }

    private function requirePending(int $id): object
    {
        $event = $this->find($id);
        if ($event === null) {
            throw new RuntimeException('Notifikasi perubahan struktur tidak ditemukan.');
        }
        if ($event->status !== self::STATUS_PENDING) {
            throw new RuntimeException('Perubahan struktur ini sudah diputuskan (' . $event->status . ').');
        }

        return $event;
    }

    private function hydrate(object $row): object
    {
        $row->payload = (array) json_decode((string) ($row->payload ?? '{}'), true);

        return $row;
    }

    private function resolveKind(array $report): ?string
    {
        if (!empty($report['has_pending_decision'])) {
            return self::KIND_NEW_COLUMNS;
        }

        $adaptations = (array) ($report['adaptations'] ?? []);
        foreach ($adaptations as $item) {
            if (($item['severity'] ?? '') === SchemaDriftDetector::SEVERITY_DANGER
                || ($item['code'] ?? '') === 'column_dropped') {
                return self::KIND_INCOMPATIBLE;
            }
        }
        foreach ($adaptations as $item) {
            if (($item['code'] ?? '') === 'format_changed') {
                return self::KIND_FORMAT_CHANGE;
            }
        }

        return null;
    }

    private function summary(string $kind, array $report): string
    {
        $label = (string) (($report['report_label'] ?? null) ?: $report['table']);

        if ($kind === self::KIND_NEW_COLUMNS) {
            $pending = array_filter((array) ($report['new_columns'] ?? []), static fn (array $column): bool => ($column['decision'] ?? '') === 'pending');
            $renames = array_filter($pending, static fn (array $column): bool => in_array($column['suggestion']['action'] ?? null, ['map', 'review'], true));
            $adjustments = array_filter((array) ($report['adjustments'] ?? []), static fn (array $item): bool => ($item['decision'] ?? '') === 'pending');
            $parts = array_filter([
                count($pending) - count($renames) > 0 ? sprintf('%d kolom baru (%s)', count($pending) - count($renames), implode(', ', array_slice(array_map(
                    static fn (array $column): string => (string) $column['header'],
                    array_diff_key($pending, $renames)
                ), 0, 4))) : null,
                $renames !== [] ? sprintf('%d kemungkinan ganti nama (%s)', count($renames), implode(', ', array_slice(array_map(
                    static fn (array $column): string => $column['header'] . ' → ' . ($column['suggestion']['column'] ?? '?'),
                    $renames
                ), 0, 3))) : null,
                $adjustments !== [] ? sprintf('%d kolom perlu diperlebar (%s)', count($adjustments), implode(', ', array_slice(array_map(
                    static fn (array $item): string => strtoupper((string) $item['column']),
                    $adjustments
                ), 0, 3))) : null,
            ]);

            return $label . ': ' . implode('; ', $parts) . ' — menunggu keputusan.';
        }

        if ($kind === self::KIND_FORMAT_CHANGE) {
            return sprintf(
                '%s: format %s berubah dan sudah disesuaikan otomatis.',
                $label,
                implode(', ', array_slice(array_unique(array_map(
                    static fn (array $item): string => strtoupper((string) $item['column']),
                    array_filter((array) $report['adaptations'], static fn (array $item): bool => ($item['code'] ?? '') === 'format_changed')
                )), 0, 4))
            );
        }

        $dropped = array_values(array_filter((array) ($report['adaptations'] ?? []), static fn (array $item): bool => ($item['code'] ?? '') === 'column_dropped'));
        $issues = array_values(array_filter((array) ($report['adaptations'] ?? []), static fn (array $item): bool => ($item['severity'] ?? '') === SchemaDriftDetector::SEVERITY_DANGER));

        return $label . ': ' . implode('; ', array_filter([
            $dropped !== [] ? sprintf('%d kolom hilang dari dokumen sumber (%s)', count($dropped), implode(', ', array_slice(array_map(static fn (array $item): string => strtoupper((string) $item['column']), $dropped), 0, 4))) : null,
            $issues !== [] ? sprintf('%d kolom berisi data yang tidak sesuai tipe tabel (%s)', count($issues), implode(', ', array_slice(array_unique(array_map(static fn (array $item): string => strtoupper((string) $item['column']), $issues)), 0, 4))) : null,
        ])) . '.';
    }

    private function compactPayload(array $report): array
    {
        $report['columns'] = array_map(static function (array $column): array {
            unset($column['profile_full']);

            return $column;
        }, (array) ($report['columns'] ?? []));

        return $report;
    }
}
