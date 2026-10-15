<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Apache mod_php has no fastcgi_finish_request(): a chunked (and mod_deflate
 * compressed) response is only terminated after the defer() callbacks finish,
 * so the browser waits for the landing cache refreshes those callbacks run.
 *
 * When deferred work is pending, send an explicit Content-Length (gzip-encoded
 * here when the client accepts it, with mod_deflate disabled for this response)
 * so the client completes on the last byte while the callbacks keep running.
 */
class FinishResponseBeforeDeferredCallbacks
{
    private const MIN_GZIP_BYTES = 1024;

    private const GZIP_LEVEL = 6;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldFinishEarly($request, $response)) {
            $this->sendWithExplicitLength($request, $response);
        }

        return $response;
    }

    protected function runningUnderApacheModule(): bool
    {
        return PHP_SAPI === 'apache2handler' && ! function_exists('fastcgi_finish_request');
    }

    protected function hasPendingDeferredCallbacks(): bool
    {
        return count(app(DeferredCallbackCollection::class)) > 0;
    }

    protected function disableServerCompression(): void
    {
        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', '1');
        }
    }

    private function shouldFinishEarly(Request $request, Response $response): bool
    {
        if (! $this->runningUnderApacheModule() || ! $this->hasPendingDeferredCallbacks()) {
            return false;
        }

        if ($response instanceof StreamedResponse || $response instanceof BinaryFileResponse) {
            return false;
        }

        if ($request->isMethod('HEAD') || $response->isInformational() || $response->isEmpty()) {
            return false;
        }

        return ! $response->headers->has('Transfer-Encoding')
            && is_string($response->getContent());
    }

    private function sendWithExplicitLength(Request $request, Response $response): void
    {
        $content = (string) $response->getContent();
        $encoding = strtolower(trim((string) $response->headers->get('Content-Encoding', '')));

        if (($encoding === '' || $encoding === 'identity')
            && strlen($content) >= self::MIN_GZIP_BYTES
            && $this->acceptsGzip($request)
            && function_exists('gzencode')) {
            $compressed = gzencode($content, self::GZIP_LEVEL);
            if (is_string($compressed)) {
                $content = $compressed;
                $response->setContent($content);
                $response->headers->set('Content-Encoding', 'gzip');
                $response->setVary('Accept-Encoding', false);
            }
        }

        $response->headers->set('Content-Length', (string) strlen($content));
        $this->disableServerCompression();
    }

    private function acceptsGzip(Request $request): bool
    {
        foreach (explode(',', (string) $request->headers->get('Accept-Encoding', '')) as $part) {
            $segments = array_map('trim', explode(';', $part));
            $coding = strtolower(array_shift($segments) ?? '');
            if ($coding !== 'gzip' && $coding !== 'x-gzip') {
                continue;
            }

            foreach ($segments as $parameter) {
                if (preg_match('/^q\s*=\s*([0-9.]+)$/i', $parameter, $match) === 1) {
                    return (float) $match[1] > 0.0;
                }
            }

            return true;
        }

        return false;
    }
}
