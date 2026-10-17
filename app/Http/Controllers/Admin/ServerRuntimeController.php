<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ServerRuntimeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ServerRuntimeController extends Controller
{
    public function status(ServerRuntimeService $runtime): JsonResponse
    {
        return response()->json(['status' => 'success', 'runtime' => $runtime->snapshot()])
            ->header('Cache-Control', 'private, no-store');
    }

    public function control(Request $request, ServerRuntimeService $runtime): JsonResponse
    {
        $data = $request->validate([
            'service' => ['required', Rule::in(['all', 'scheduler', 'apache', 'mysql', 'tunnel', 'backup'])],
            'action' => ['required', Rule::in(['start', 'stop', 'restart', 'run'])],
        ]);
        abort_if($data['service'] === 'backup' && $data['action'] !== 'run', 422, 'Backup hanya dapat dijalankan dari panel.');
        abort_if(! in_array($data['service'], ['scheduler', 'backup'], true) && $data['action'] !== 'start', 422, 'Layanan ini hanya dapat diaktifkan dari panel.');
        // Each OS helper has a deadline; starting several services can exceed
        // PHP's default request limit. Persist the session before that work.
        @set_time_limit(120);
        if ($request->hasSession()) {
            $request->session()->save();
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $result = $runtime->control($data['service'], $data['action'], $request->user()?->pn);

        return response()->json($result, $result['status'] === 'error' ? 503 : 200)
            ->header('Cache-Control', 'private, no-store');
    }
}
