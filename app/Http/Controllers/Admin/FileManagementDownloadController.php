<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FileManagementDownloadController extends Controller
{
    public function __invoke(Request $request, FileManagementController $controller): BinaryFileResponse
    {
        $data = $request->validate([
            'path' => ['required', 'string', 'max:2048'],
        ]);

        $managedFile = $controller->resolveManagedFile($data['path']);
        abort_if(($managedFile['group'] ?? null) === 'database_backups' && !($request->user()?->isSuperAdmin() ?? false), 403, 'Akses backup database hanya untuk superadmin.');
        $resolvedPath = $managedFile['path'] ?? null;
        abort_unless($resolvedPath !== null && is_file($resolvedPath), 404);

        return response()->download($resolvedPath, basename($resolvedPath), [
            'Content-Type' => 'application/sql; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
