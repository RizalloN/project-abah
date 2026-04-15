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

        $resolvedPath = $controller->resolveDownloadablePath($data['path']);
        abort_unless($resolvedPath !== null, 404);

        return response()->download($resolvedPath, basename($resolvedPath), [
            'Content-Type' => 'application/sql; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
