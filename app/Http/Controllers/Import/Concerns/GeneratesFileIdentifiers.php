<?php

namespace App\Http\Controllers\Import\Concerns;

use Illuminate\Http\Request;

/**
 * CRITICAL: Consistent file identifier generation across all import phases
 * Ensures cache keys match between preview and import phases
 * Without this: preview state cache key ≠ import validation cache key = desync!
 */
trait GeneratesFileIdentifiers
{
    /**
     * Generate consistent file identifier for caching preview state
     *
     * This MUST be identical between:
     * - preparePreviewStream() phase
     * - preview() display phase
     * - initImport() validation phase
     *
     * Using just filename is NOT enough (multiple uploads of same file)
     * Using full path is NOT safe (changes between environments)
     * Solution: MD5(session_id + filename + user_id + timestamp)
     */
    protected function generateFileIdentifier(
        ?string $filePath = null,
        ?Request $request = null,
        ?int $userId = null
    ): string {
        $filePath = $filePath ?? session('performance_mantri_file')
                            ?? session('casa_brilink_file')
                            ?? session('import_file')
                            ?? '';

        $userId = $userId ?? auth()->id() ?? 0;
        $sessionId = session()->getId() ?? '';

        // Extract filename only (remove path prefixes)
        $filename = basename($filePath);

        // Create unique identifier that's consistent across phases
        $identifier = implode('|', [
            'file',
            $sessionId,
            $filename,
            $userId,
        ]);

        return md5($identifier);
    }

    /**
     * Generate file identifier from request parameters
     * Used when file_path is passed as request parameter
     */
    protected function generateFileIdentifierFromRequest(Request $request): string
    {
        return $this->generateFileIdentifier(
            filePath: $request->input('file_path')
                   ?? $request->input('relative_path')
                   ?? session('performance_mantri_file'),
            request: $request,
            userId: auth()->id()
        );
    }

    /**
     * Get file identifier key prefix for session storage
     * Used for storing preview context in session
     */
    protected function getSessionKeyForFile(string $fileIdentifier): string
    {
        return "import_preview_context_{$fileIdentifier}";
    }

    /**
     * Get file identifier key prefix for cache storage
     * Used for storing validation state in cache
     */
    protected function getCacheKeyForFile(string $fileIdentifier): string
    {
        return "import_preview_state:{$fileIdentifier}";
    }
}
