<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeadersMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->getMethod(), ['TRACE', 'TRACK'], true)) {
            return $this->withSecurityHeaders(response('Method Not Allowed', 405), $request);
        }

        /** @var Response $response */
        $response = $next($request);

        return $this->withSecurityHeaders($response, $request);
    }

    private function withSecurityHeaders(Response $response, Request $request): Response
    {
        $response->headers->remove('X-Powered-By');
        if (function_exists('header_remove') && !headers_sent()) {
            header_remove('X-Powered-By');
        }

        if (!$this->isPublicWorkbookRequest($request)) {
            $response->headers->set('X-Frame-Options', 'DENY');
        }
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Download-Options', 'noopen');
        $response->headers->set('X-DNS-Prefetch-Control', 'off');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set(
            'Cross-Origin-Resource-Policy',
            $this->isPublicWorkbookRequest($request)
                ? 'cross-origin'
                : 'same-origin'
        );

        $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy($request));

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if (auth()->check() || $this->isAuthenticationRequest($request)) {
            $response->headers->set('Cache-Control', 'no-store, private, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }

        return $response;
    }

    private function isPublicWorkbookRequest(Request $request): bool
    {
        return $request->is('workbooks/market-share.xlsx')
            || $request->is('workbooks/market-share/*')
            || $request->is('workbooks/market-share-mapping.xlsx')
            || $request->is('workbooks/market-share-mapping/*');
    }

    private function isAuthenticationRequest(Request $request): bool
    {
        return $request->is('login')
            || $request->is('forgot-password')
            || $request->is('reset-password')
            || $request->is('reset-password/*')
            || $request->is('confirm-password');
    }

    private function contentSecurityPolicy(Request $request): string
    {
        if ($this->isPublicWorkbookRequest($request)) {
            return "default-src 'none'; frame-ancestors https://*.officeapps.live.com https://*.office.com https://*.microsoft.com";
        }

        $directives = [
            "default-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "object-src 'none'",
            "img-src 'self' data: blob:",
            "font-src 'self' https://fonts.gstatic.com data:",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "script-src 'self' 'unsafe-inline'",
            "connect-src 'self'",
        ];

        $workbookUrl = $this->workbookUrlForRequest($request);
        if ($workbookUrl !== null) {
            $frameSources = $this->workbookFrameSources($workbookUrl);
            $directives[] = "frame-src {$frameSources}";
            $directives[] = "child-src {$frameSources}";
        }

        if ($request->isSecure()) {
            $directives[] = 'upgrade-insecure-requests';
        }

        return implode('; ', $directives);
    }

    private function workbookUrlForRequest(Request $request): ?string
    {
        if ($request->is('report/dashboard-dana/market-share')) {
            return (string) config('services.market_share.workbook_url', '');
        }

        if ($request->is('report/dashboard-dana/market-share/mapping')) {
            return (string) config('services.market_share_mapping.workbook_url', '');
        }

        if ($request->is('report/dashboard-dana/market-share/instansi')) {
            return 'https://docs.google.com';
        }

        return null;
    }

    private function workbookFrameSources(string $workbookUrl): string
    {
        $sources = [
            "'self'",
            'https://docs.google.com',
            'https://*.sharepoint.com',
            'https://*.officeapps.live.com',
            'https://*.office.com',
            'https://*.microsoft.com',
        ];

        $origin = $this->originFromUrl($workbookUrl);
        if ($origin !== null) {
            $sources[] = $origin;
        }

        return implode(' ', array_values(array_unique($sources)));
    }

    private function originFromUrl(string $url): ?string
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);

        if (!in_array($scheme, ['http', 'https'], true) || !is_string($host) || $host === '') {
            return null;
        }

        return $scheme . '://' . $host;
    }
}
