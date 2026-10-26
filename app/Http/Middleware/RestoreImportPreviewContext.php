<?php

namespace App\Http\Middleware;

use App\Services\Import\ExcelImportJobService;
use App\Support\Import\ImportSessionContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Membuat beberapa preview import (tab berbeda) dapat diimport berbarengan.
 *
 * Request yang membawa preview_state_key (atau ck pada halaman preview) memakai
 * konteks session milik preview tersebut, bukan milik upload terakhir.
 */
class RestoreImportPreviewContext
{
    public const ATTRIBUTE = 'import_preview_context';

    public const RESTORED = 'restored';

    public const MISSING = 'missing';

    public function __construct(private readonly ExcelImportJobService $previewStates)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (!$this->isImportRequest($request) || !$request->hasSession()) {
            return $next($request);
        }

        $session = $request->session();
        $stateKey = $this->previewStateKey($request);

        if ($stateKey !== '') {
            try {
                $context = $this->previewStates->getPreviewState($stateKey)[ExcelImportJobService::SESSION_CONTEXT_KEY] ?? null;
                if (is_array($context) && $context !== []) {
                    ImportSessionContext::restore($session, $context);
                    $request->attributes->set(self::ATTRIBUTE, self::RESTORED);
                } else {
                    $request->attributes->set(self::ATTRIBUTE, self::MISSING);
                }
            } catch (Throwable) {
                $request->attributes->set(self::ATTRIBUTE, self::MISSING);
            }
        }

        $previewKeyBefore = (string) $session->get('excel_preview_key', '');
        $response = $next($request);
        $previewKeyAfter = (string) $session->get('excel_preview_key', '');

        // Upload baru menyiapkan preview "ck": catat konteksnya sebelum tab lain menimpanya.
        if ($previewKeyAfter !== '' && $previewKeyAfter !== $previewKeyBefore) {
            try {
                $this->previewStates->rememberPreviewSessionContext(
                    $previewKeyAfter,
                    ImportSessionContext::capture($session)
                );
            } catch (Throwable) {
            }
        }

        return $response;
    }

    private function isImportRequest(Request $request): bool
    {
        return $request->is('import', 'import/*', 'import-*');
    }

    private function previewStateKey(Request $request): string
    {
        $key = trim((string) $request->input('preview_state_key', ''));
        if ($key !== '') {
            return $key;
        }

        // Halaman preview Excel/Daily Loan/Simpanan memakai ck sebagai preview state key.
        return $request->isMethod('GET') && str_contains($request->path(), 'preview')
            ? trim((string) $request->query('ck', ''))
            : '';
    }
}
