<?php

namespace App\Services\Import\SchemaDrift;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * Persists schema drift events (the notification feed and audit trail) and
 * executes accept/reject decisions.
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
                array_values(array_filter((array) $report['adaptations'], static fn (array $item): bool => ($item['severity'] ?? '') === SchemaDriftDetector::SEVERITY_DANGER))
            )));
        }

        $now = now();
        $payload = json_encode($this->compactPayload($report), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        $summary = mb_substr($this->summary($kind, $report), 0, 500);

        $existing = DB::table(self::TABLE)->where('table_name', $table)->where('fingerprint', $fingerprint)->first();
        if ($existing !== null) {
            $update = [
                'payload' => $payload,
                'summary' => $summary,
                'source_file' => $sourceFile !== null ? mb_substr($sourceFile, 0, 500) : $existing->source_file,
                'occurrences' => DB::raw('occurrences + 1'),
                'last_seen_at' => $now,
                'updated_at' => $now,
            ];
            if (in_array($existing->status, [self::STATUS_AUTO_ADAPTED, self::STATUS_ATTENTION], true)) {
                $update['acknowledged_at'] = null;
            }
            DB::table(self::TABLE)->where('id', $existing->id)->update($update);

            return $this->find((int) $existing->id);
        }

        $id = DB::table(self::TABLE)->insertGetId([
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

        return $this->find((int) $id);
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
     * @return array<int, string> proposed column names that were rejected before
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
     * Columns that were added to the table by an accepted drift decision.
     *
     * @return array<int, string>
     */
    public function evolvedColumns(string $table): array
    {
        if (!$this->isAvailable()) {
            return [];
        }

        $names = [];
        DB::table(self::TABLE)
            ->where('table_name', strtolower($table))
            ->where('status', self::STATUS_APPLIED)
            ->pluck('applied_columns')
            ->each(function ($json) use (&$names): void {
                foreach ((array) json_decode((string) $json, true) as $column) {
                    $name = is_array($column) ? ($column['name'] ?? null) : $column;
                    if (is_string($name) && $name !== '') {
                        $names[strtolower($name)] = true;
                    }
                }
            });

        return array_keys($names);
    }

    /**
     * @param array<int, array{proposed_column: string, name?: string, type?: string, include?: bool}> $selections
     */
    public function accept(int $id, string $actor, array $selections = []): object
    {
        $event = $this->requirePending($id);
        if (!app(SchemaEvolutionPolicy::class)->canEvolve((string) $event->table_name)) {
            throw new RuntimeException(
                'Report ini memakai layout import kolom tetap, jadi kolom baru tidak akan terisi walaupun ditambahkan. '
                . 'Tandai sebagai ditinjau (Tolak) — data tetap masuk tanpa kolom tersebut.'
            );
        }
        $pending = array_values(array_filter(
            (array) ($event->payload['new_columns'] ?? []),
            static fn (array $column): bool => ($column['decision'] ?? 'pending') === 'pending'
        ));
        $selectionMap = [];
        foreach ($selections as $selection) {
            $key = strtolower((string) ($selection['proposed_column'] ?? ''));
            if ($key !== '') {
                $selectionMap[$key] = $selection;
            }
        }

        $toAdd = [];
        $excluded = [];
        foreach ($pending as $column) {
            $key = strtolower((string) $column['proposed_column']);
            $selection = $selectionMap[$key] ?? [];
            if (array_key_exists('include', $selection) && !filter_var($selection['include'], FILTER_VALIDATE_BOOL)) {
                $excluded[] = $key;
                continue;
            }

            $toAdd[] = [
                'name' => strtolower(trim((string) ($selection['name'] ?? $column['proposed_column']))),
                'type' => strtoupper(trim((string) ($selection['type'] ?? $column['proposed_type']))),
                'source_header' => $column['header'] ?? null,
            ];
        }

        try {
            $applied = $toAdd === [] ? [] : $this->evolution->addColumns((string) $event->table_name, $toAdd);
        } catch (Throwable $e) {
            DB::table(self::TABLE)->where('id', $id)->update([
                'status' => self::STATUS_FAILED,
                'error' => mb_substr($e->getMessage(), 0, 2000),
                'decided_by' => mb_substr($actor, 0, 150),
                'decided_at' => now(),
                'updated_at' => now(),
            ]);

            throw $e instanceof RuntimeException ? $e : new RuntimeException($e->getMessage(), 0, $e);
        }

        foreach ($applied as &$column) {
            foreach ($toAdd as $requested) {
                if ($requested['name'] === $column['name']) {
                    $column['source_header'] = $requested['source_header'];
                }
            }
        }
        unset($column);

        DB::table(self::TABLE)->where('id', $id)->update([
            'status' => $applied === [] ? self::STATUS_REJECTED : self::STATUS_APPLIED,
            'applied_columns' => json_encode($applied, JSON_UNESCAPED_UNICODE),
            'rejected_columns' => $excluded === [] ? null : json_encode($excluded),
            'decided_by' => mb_substr($actor, 0, 150),
            'decided_at' => now(),
            'acknowledged_at' => now(),
            'error' => null,
            'updated_at' => now(),
        ]);

        app(ColumnFormatMemory::class)->flush((string) $event->table_name);
        Log::info('Import schema drift accepted.', ['event' => $id, 'table' => $event->table_name, 'applied' => $applied, 'excluded' => $excluded, 'actor' => $actor]);

        return $this->find($id);
    }

    public function reject(int $id, string $actor): object
    {
        $event = $this->requirePending($id);
        $rejected = array_values(array_map(
            static fn (array $column): string => strtolower((string) $column['proposed_column']),
            array_filter((array) ($event->payload['new_columns'] ?? []), static fn (array $column): bool => ($column['decision'] ?? 'pending') === 'pending')
        ));

        DB::table(self::TABLE)->where('id', $id)->update([
            'status' => self::STATUS_REJECTED,
            'rejected_columns' => json_encode($rejected),
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
            'adaptations' => array_values((array) ($event->payload['adaptations'] ?? [])),
            'applied_columns' => array_values((array) json_decode((string) ($event->applied_columns ?? '[]'), true)),
            'rejected_columns' => array_values((array) json_decode((string) ($event->rejected_columns ?? '[]'), true)),
        ];
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
            if (($item['severity'] ?? '') === SchemaDriftDetector::SEVERITY_DANGER) {
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
        $label = (string) ($report['report_label'] ?: $report['table']);

        return match ($kind) {
            self::KIND_NEW_COLUMNS => sprintf(
                '%s: %d kolom baru (%s) menunggu keputusan.',
                $label,
                (int) $report['stats']['new'],
                implode(', ', array_slice(array_map(
                    static fn (array $column): string => (string) $column['header'],
                    array_filter((array) $report['new_columns'], static fn (array $column): bool => $column['decision'] === 'pending')
                ), 0, 4))
            ),
            self::KIND_FORMAT_CHANGE => sprintf(
                '%s: format %s berubah dan sudah disesuaikan otomatis.',
                $label,
                implode(', ', array_slice(array_unique(array_map(
                    static fn (array $item): string => strtoupper((string) $item['column']),
                    array_filter((array) $report['adaptations'], static fn (array $item): bool => ($item['code'] ?? '') === 'format_changed')
                )), 0, 4))
            ),
            default => sprintf('%s: ada isi kolom yang tidak sesuai tipe tabel tujuan.', $label),
        };
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
