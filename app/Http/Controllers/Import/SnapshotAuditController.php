<?php

namespace App\Http\Controllers\Import;

use App\Http\Controllers\Controller;
use App\Support\SnapshotAuditCoordinator;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SnapshotAuditController extends Controller
{
    public function __construct(
        private readonly SnapshotAuditCoordinator $auditCoordinator,
    ) {
    }

    public function runAudit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'table_name' => 'required|string|max:100',
            'period_hint' => 'nullable|string|max:50',
        ]);

        $result = $this->auditCoordinator->runAudit(
            tableName: $validated['table_name'],
            periodHint: $validated['period_hint'] ?? null
        );

        return response()->json([
            'status' => 'success',
            'data' => $result,
        ]);
    }

    public function getAuditResult(Request $request, string $auditId): JsonResponse
    {
        $result = $this->auditCoordinator->getAuditResult($auditId);

        if ($result === null) {
            return response()->json([
                'status' => 'error',
                'message' => 'Audit result not found or expired',
                'audit_id' => $auditId,
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $result,
        ]);
    }

    public function triggerSmartRebuild(Request $request, ?string $auditId = null): JsonResponse
    {
        $validated = $auditId !== null
            ? ['audit_id' => $auditId]
            : $request->validate([
                'audit_id' => 'required|string|uuid',
            ]);

        $result = $this->auditCoordinator->triggerSmartRebuild($validated['audit_id']);

        $statusCode = match ($result['status']) {
            'error' => 400,
            'info' => 200,
            'queued' => 200,
            default => 200,
        };

        return response()->json([
            'status' => $result['status'],
            'data' => $result,
        ], $statusCode);
    }

    public function compareAudits(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'audit_id_1' => 'required|string|uuid',
            'audit_id_2' => 'required|string|uuid',
        ]);

        $result = $this->auditCoordinator->compareAudits(
            $validated['audit_id_1'],
            $validated['audit_id_2']
        );

        $statusCode = $result['status'] === 'error' ? 400 : 200;

        return response()->json([
            'status' => $result['status'],
            'data' => $result,
        ], $statusCode);
    }

    public function getRecommendedAction(Request $request, string $tableName, ?string $periodHint = null): JsonResponse
    {
        $result = $this->auditCoordinator->runAudit($tableName, $periodHint);

        $summary = $result['summary'] ?? [];
        $action = $summary['recommended_action'] ?? 'No action needed';
        $isCritical = $summary['action_required'] ?? false;

        return response()->json([
            'status' => 'success',
            'data' => [
                'table_name' => $tableName,
                'period_hint' => $periodHint,
                'audit_id' => $result['audit_id'] ?? null,
                'recommended_action' => $action,
                'action_required' => $isCritical,
                'summary' => $summary,
                'affected_periods' => $summary['affected_periods'] ?? [],
                'critical_issues' => $summary['critical_issues'] ?? 0,
                'warnings' => $summary['warnings'] ?? 0,
            ],
        ]);
    }
}
