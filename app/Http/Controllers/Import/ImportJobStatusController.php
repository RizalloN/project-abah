<?php

namespace App\Http\Controllers\Import;

use App\Http\Controllers\Controller;
use App\Services\Import\ImportProgressService;

class ImportJobStatusController extends Controller
{
    public function __invoke(int $jobId, ImportProgressService $progressService)
    {
        $payload = $progressService->getStatusPayload($jobId);
        $statusCode = ($payload['status'] ?? '') === 'error' ? 404 : 200;

        return response()->json($payload, $statusCode);
    }
}
