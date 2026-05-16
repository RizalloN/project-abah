<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class SnapshotDirtyPeriodService
{
    public const TABLE = 'snapshot_dirty_periods';
    public const FAILED_TABLE = 'failed_snapshot_dirty_periods';
    private const AUDIT_TABLE = 'report_sync_audits';
    public const DEFAULT_SHARD_TYPE = 'period';
    public const DEFAULT_SHARD_KEY = '*';
    public const MAX_ATTEMPTS = 5;
    private const CLAIM_STALE_AFTER_SECONDS = 600;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function claimDue(int $limit = 50, ?string $sourceTable = null, ?string $period = null): array
    {
        if (!Schema::hasTable(self::TABLE)) {
            return [];
        }

        $limit = max(1, min(500, $limit));
        $token = (string) Str::uuid();

        return DB::transaction(function () use ($limit, $sourceTable, $period, $token): array {
            $this->releaseStaleClaims($sourceTable, $period);

            $query = DB::table(self::TABLE)
                ->whereNull('claimed_at')
                ->where('attempts', '<', self::MAX_ATTEMPTS)
                ->orderBy('dirty_since')
                ->limit($limit);

            if ($sourceTable !== null && trim($sourceTable) !== '') {
                $query->where('source_table', strtolower(trim($sourceTable)));
            }

            if ($period !== null && trim($period) !== '') {
                $query->where('period_key', trim($period));
            }

            $rows = $query->get();
            if ($rows->isEmpty()) {
                return [];
            }

            $now = now();
            $claimed = [];

            foreach ($rows as $row) {
                $dirtySince = $row->dirty_since;

                $updated = DB::table(self::TABLE)
                    ->where('source_table', $row->source_table)
                    ->where('period_key', $row->period_key)
                    ->where('shard_type', $row->shard_type)
                    ->where('shard_key', $row->shard_key)
                    ->whereNull('claimed_at')
                    ->where('dirty_since', $dirtySince)
                    ->update([
                        'claim_token' => $token,
                        'claimed_at' => $now,
                        'dirty_since_at_claim' => $dirtySince,
                        'last_attempted_at' => $now,
                        'attempts' => DB::raw('attempts + 1'),
                        'updated_at' => $now,
                    ]);

                if ($updated !== 1) {
                    continue;
                }

                $claimed[] = [
                    'source_table' => (string) $row->source_table,
                    'period_key' => (string) $row->period_key,
                    'shard_type' => (string) $row->shard_type,
                    'shard_key' => (string) $row->shard_key,
                    'dirty_since' => (string) $dirtySince,
                    'dirty_since_at_claim' => (string) $dirtySince,
                    'claim_token' => $token,
                    'attempts' => (int) $row->attempts + 1,
                ];
            }

            return $claimed;
        });
    }

    public function mark(
        string $sourceTable,
        ?string $period,
        string $shardType = self::DEFAULT_SHARD_TYPE,
        string $shardKey = self::DEFAULT_SHARD_KEY,
        int $count = 1
    ): void {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        $sourceTable = strtolower(trim($sourceTable));
        $period = trim((string) $period);
        $shardType = trim($shardType) !== '' ? strtolower(trim($shardType)) : self::DEFAULT_SHARD_TYPE;
        $shardKey = trim($shardKey) !== '' ? strtoupper(trim($shardKey)) : self::DEFAULT_SHARD_KEY;

        if ($sourceTable === '' || $period === '') {
            return;
        }

        $now = now();

        $key = [
            'source_table' => $sourceTable,
            'period_key' => $period,
            'shard_type' => $shardType,
            'shard_key' => $shardKey,
        ];

        $this->forgetFailedMarker($key);

        $dirtySinceExpression = in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)
            ? DB::raw('LEAST(dirty_since, ' . DB::getPdo()->quote($now->format('Y-m-d H:i:s.u')) . ')')
            : $now;

        $affected = DB::table(self::TABLE)
            ->where($key)
            ->update([
                'dirty_since' => $dirtySinceExpression,
                'dirty_row_count' => DB::raw('dirty_row_count + ' . max(1, $count)),
                'claimed_at' => null,
                'claim_token' => null,
                'dirty_since_at_claim' => null,
                'last_error' => null,
                'last_attempted_at' => null,
                'attempts' => 0,
                'updated_at' => $now,
            ]);

        if ($affected > 0) {
            return;
        }

        DB::table(self::TABLE)->insert($key + [
            'dirty_since' => $now,
            'dirty_row_count' => max(1, $count),
            'attempts' => 0,
            'claimed_at' => null,
            'claim_token' => null,
            'dirty_since_at_claim' => null,
            'last_attempted_at' => null,
            'last_error' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * @param array<string, mixed> $claim
     */
    public function clearClaim(array $claim): bool
    {
        if (!Schema::hasTable(self::TABLE)) {
            return false;
        }

        $deleted = DB::table(self::TABLE)
            ->where('source_table', (string) ($claim['source_table'] ?? ''))
            ->where('period_key', (string) ($claim['period_key'] ?? ''))
            ->where('shard_type', (string) ($claim['shard_type'] ?? self::DEFAULT_SHARD_TYPE))
            ->where('shard_key', (string) ($claim['shard_key'] ?? self::DEFAULT_SHARD_KEY))
            ->where('claim_token', (string) ($claim['claim_token'] ?? ''))
            ->where('dirty_since', '<=', (string) ($claim['dirty_since_at_claim'] ?? ''))
            ->delete();

        $cleared = $deleted > 0;
        if ($cleared) {
            $this->writeAudit($claim, 'snapshot_dirty_clear', 'success');
        }

        return $cleared;
    }

    /**
     * @param array<string, mixed> $claim
     */
    public function releaseClaim(array $claim, Throwable|string $error): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        $message = $error instanceof Throwable ? $error->getMessage() : $error;

        $row = DB::table(self::TABLE)
            ->where('source_table', (string) ($claim['source_table'] ?? ''))
            ->where('period_key', (string) ($claim['period_key'] ?? ''))
            ->where('shard_type', (string) ($claim['shard_type'] ?? self::DEFAULT_SHARD_TYPE))
            ->where('shard_key', (string) ($claim['shard_key'] ?? self::DEFAULT_SHARD_KEY))
            ->where('claim_token', (string) ($claim['claim_token'] ?? ''))
            ->first();

        if ($row === null) {
            return;
        }

        if ((int) ($row->attempts ?? 0) >= self::MAX_ATTEMPTS) {
            $this->moveToFailed($row, $message);
            return;
        }

        DB::table(self::TABLE)
            ->where('source_table', $row->source_table)
            ->where('period_key', $row->period_key)
            ->where('shard_type', $row->shard_type)
            ->where('shard_key', $row->shard_key)
            ->update([
                'claimed_at' => null,
                'claim_token' => null,
                'dirty_since_at_claim' => null,
                'last_error' => mb_substr($message, 0, 1000),
                'updated_at' => now(),
            ]);

        $this->writeAudit((array) $row, 'snapshot_dirty_release', 'retry', $message);
    }

    public function pendingCount(?string $sourceTable = null): int
    {
        if (!Schema::hasTable(self::TABLE)) {
            return 0;
        }

        $query = DB::table(self::TABLE)->whereNull('claimed_at');
        if ($sourceTable !== null && trim($sourceTable) !== '') {
            $query->where('source_table', strtolower(trim($sourceTable)));
        }

        return (int) $query->count();
    }

    private function releaseStaleClaims(?string $sourceTable = null, ?string $period = null): void
    {
        $cutoff = now()->subSeconds(self::CLAIM_STALE_AFTER_SECONDS);

        $query = DB::table(self::TABLE)
            ->whereNotNull('claimed_at')
            ->where('claimed_at', '<=', $cutoff)
            ->where('attempts', '<', self::MAX_ATTEMPTS);

        if ($sourceTable !== null && trim($sourceTable) !== '') {
            $query->where('source_table', strtolower(trim($sourceTable)));
        }

        if ($period !== null && trim($period) !== '') {
            $query->where('period_key', trim($period));
        }

        $query->update([
            'claimed_at' => null,
            'claim_token' => null,
            'dirty_since_at_claim' => null,
            'last_error' => 'Released stale snapshot dirty claim for retry.',
            'updated_at' => now(),
        ]);
    }

    /**
     * @param array{source_table:string, period_key:string, shard_type:string, shard_key:string} $key
     */
    private function forgetFailedMarker(array $key): void
    {
        if (!Schema::hasTable(self::FAILED_TABLE)) {
            return;
        }

        DB::table(self::FAILED_TABLE)->where($key)->delete();
    }

    private function moveToFailed(object $row, string $message): void
    {
        if (!Schema::hasTable(self::FAILED_TABLE)) {
            Log::error('Snapshot dirty period reached max attempts.', [
                'source_table' => $row->source_table ?? null,
                'period_key' => $row->period_key ?? null,
                'message' => $message,
            ]);
            return;
        }

        DB::table(self::FAILED_TABLE)->updateOrInsert(
            [
                'source_table' => $row->source_table,
                'period_key' => $row->period_key,
                'shard_type' => $row->shard_type,
                'shard_key' => $row->shard_key,
            ],
            [
                'dirty_since' => $row->dirty_since,
                'dirty_row_count' => (int) ($row->dirty_row_count ?? 0),
                'attempts' => (int) ($row->attempts ?? self::MAX_ATTEMPTS),
                'last_error' => mb_substr($message, 0, 1000),
                'failed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table(self::TABLE)
            ->where('source_table', $row->source_table)
            ->where('period_key', $row->period_key)
            ->where('shard_type', $row->shard_type)
            ->where('shard_key', $row->shard_key)
            ->delete();

        $this->writeAudit((array) $row, 'snapshot_dirty_dead_letter', 'failed', $message);
    }

    /**
     * @param array<string, mixed> $claim
     */
    private function writeAudit(array $claim, string $action, string $status, ?string $message = null): void
    {
        if (!Schema::hasTable(self::AUDIT_TABLE)) {
            return;
        }

        try {
            DB::table(self::AUDIT_TABLE)->insert([
                'import_job_id' => null,
                'source' => static::class,
                'table_name' => (string) ($claim['source_table'] ?? 'snapshot_dirty_periods'),
                'period_hint' => $this->normalizeAuditPeriod((string) ($claim['period_key'] ?? '')),
                'action' => $action,
                'status' => $status,
                'duration_ms' => null,
                'affected_rows' => null,
                'message' => $message !== null ? mb_substr($message, 0, 1000) : null,
                'context' => json_encode([
                    'shard_type' => (string) ($claim['shard_type'] ?? self::DEFAULT_SHARD_TYPE),
                    'shard_key' => (string) ($claim['shard_key'] ?? self::DEFAULT_SHARD_KEY),
                    'claim_token' => (string) ($claim['claim_token'] ?? ''),
                    'attempts' => (int) ($claim['attempts'] ?? 0),
                ], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('Gagal menulis audit dirty-period snapshot: ' . $e->getMessage(), [
                'action' => $action,
                'status' => $status,
            ]);
        }
    }

    private function normalizeAuditPeriod(string $period): ?string
    {
        $period = trim($period);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $period) === 1) {
            return $period;
        }

        return null;
    }
}
