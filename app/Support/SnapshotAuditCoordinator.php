<?php

namespace App\Support;

use App\Jobs\SmartPartialSnapshotRebuildJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class SnapshotAuditCoordinator
{
    private const AUDIT_CACHE_PREFIX = 'snapshot:audit:';
    private const AUDIT_CACHE_TTL = 3600;
    private const AUDIT_LOCK_PREFIX = 'snapshot:audit:lock:';
    private const AUDIT_LOCK_SECONDS = 10;

    public function __construct(
        private readonly SnapshotAuditService $auditService
    ) {
    }

    public function runAudit(string $tableName, ?string $periodHint = null): array
    {
        $normalizedTable = strtolower(trim($tableName));
        $auditId = (string) Str::uuid();

        Log::info('Starting snapshot audit.', [
            'audit_id' => $auditId,
            'table_name' => $normalizedTable,
            'period_hint' => $periodHint,
        ]);

        $result = $this->auditService->auditSnapshot($normalizedTable, $periodHint);
        $result['audit_id'] = $auditId;
        $result['audit_timestamp'] = now()->toIso8601String();

        $this->cacheAuditResult($auditId, $result);

        Log::info('Completed snapshot audit.', [
            'audit_id' => $auditId,
            'status' => $result['status'],
            'periods_checked' => $result['total_periods_checked'] ?? 0,
            'periods_with_issues' => $result['periods_with_issues'] ?? 0,
            'action_required' => $result['summary']['action_required'] ?? false,
        ]);

        return $result;
    }

    public function getAuditResult(string $auditId): ?array
    {
        $cached = Cache::get(self::AUDIT_CACHE_PREFIX . $auditId);

        return is_array($cached) ? $cached : null;
    }

    public function triggerSmartRebuild(string $auditId): array
    {
        $auditResult = $this->getAuditResult($auditId);

        if ($auditResult === null) {
            return [
                'status' => 'error',
                'message' => 'Audit result not found or expired',
                'audit_id' => $auditId,
            ];
        }

        $tableName = trim((string) ($auditResult['table_name'] ?? ''));
        $discrepancies = (array) ($auditResult['discrepancies'] ?? []);

        if (empty($discrepancies)) {
            return [
                'status' => 'info',
                'message' => 'No discrepancies found, no rebuild needed',
                'audit_id' => $auditId,
                'table_name' => $tableName,
            ];
        }

        $affectedPeriods = array_unique(
            array_map(fn($d) => $d['period'] ?? null, $discrepancies)
        );
        $affectedPeriods = array_filter($affectedPeriods);

        if (empty($affectedPeriods)) {
            return [
                'status' => 'error',
                'message' => 'Could not determine affected periods from audit',
                'audit_id' => $auditId,
            ];
            }

        try {
            SmartPartialSnapshotRebuildJob::dispatch(
                $tableName,
                array_values($affectedPeriods),
                $auditId
            )->onQueue((string) config('queue.report_queue', 'default'));

            Log::info('Dispatched smart partial snapshot rebuild.', [
                'audit_id' => $auditId,
                'table_name' => $tableName,
                'affected_periods' => $affectedPeriods,
                'period_count' => count($affectedPeriods),
            ]);

            return [
                'status' => 'queued',
                'message' => 'Smart partial snapshot rebuild queued',
                'audit_id' => $auditId,
                'table_name' => $tableName,
                'affected_periods' => $affectedPeriods,
                'period_count' => count($affectedPeriods),
                'timestamp' => now()->toIso8601String(),
            ];
        } catch (Throwable $e) {
            Log::error('Failed to dispatch smart partial rebuild: ' . $e->getMessage(), [
                'audit_id' => $auditId,
                'table_name' => $tableName,
                'exception' => $e::class,
            ]);

            return [
                'status' => 'error',
                'message' => 'Failed to queue rebuild: ' . $e->getMessage(),
                'audit_id' => $auditId,
                'table_name' => $tableName,
            ];
        }
    }

    public function compareAudits(string $auditId1, string $auditId2): array
    {
        $audit1 = $this->getAuditResult($auditId1);
        $audit2 = $this->getAuditResult($auditId2);

        if ($audit1 === null || $audit2 === null) {
            return [
                'status' => 'error',
                'message' => 'One or both audit results not found',
            ];
        }

        if (($audit1['table_name'] ?? null) !== ($audit2['table_name'] ?? null)) {
            return [
                'status' => 'error',
                'message' => 'Cannot compare audits for different tables',
            ];
        }

        return [
            'status' => 'success',
            'table_name' => $audit1['table_name'],
            'audit_1' => [
                'id' => $auditId1,
                'timestamp' => $audit1['audit_timestamp'],
                'status' => $audit1['status'],
                'periods_with_issues' => $audit1['periods_with_issues'] ?? 0,
            ],
            'audit_2' => [
                'id' => $auditId2,
                'timestamp' => $audit2['audit_timestamp'],
                'status' => $audit2['status'],
                'periods_with_issues' => $audit2['periods_with_issues'] ?? 0,
            ],
            'improvement' => [
                'issues_fixed' => ($audit1['summary']['total_issues'] ?? 0) - ($audit2['summary']['total_issues'] ?? 0),
                'before' => $audit1['summary'] ?? [],
                'after' => $audit2['summary'] ?? [],
            ],
        ];
    }

    private function cacheAuditResult(string $auditId, array $result): void
    {
        Cache::put(
            self::AUDIT_CACHE_PREFIX . $auditId,
            $result,
            now()->addSeconds(self::AUDIT_CACHE_TTL)
        );
    }
}
