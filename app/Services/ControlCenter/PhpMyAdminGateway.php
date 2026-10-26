<?php

namespace App\Services\ControlCenter;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Opens the local-only phpMyAdmin through the application instead of exposing
 * it on the internet. Apache keeps allowing phpMyAdmin from 127.0.0.1 only;
 * this gateway forwards requests from an authenticated superadmin who has
 * re-confirmed the account password, and phpMyAdmin still requires its own
 * database login behind it.
 */
class PhpMyAdminGateway
{
    public const SESSION_KEY = 'control_center.pma_gate';
    public const ROUTE_PREFIX = '/job-management/database/pma/';
    private const DEFAULT_INTERNAL_URL = 'http://127.0.0.1/admin-db-secret-a1b2c3/';
    private const IDLE_SECONDS = 1200;
    private const ABSOLUTE_SECONDS = 7200;
    private const TOUCH_INTERVAL_SECONDS = 60;

    private const FORWARDED_REQUEST_HEADERS = ['accept', 'accept-language', 'user-agent', 'x-requested-with', 'if-none-match', 'if-modified-since', 'content-type'];
    private const DROPPED_RESPONSE_HEADERS = ['set-cookie', 'transfer-encoding', 'content-length', 'content-encoding', 'connection', 'keep-alive', 'x-powered-by', 'server', 'date', 'x-ob_mode'];
    private const TEXT_TYPES = ['text/', 'javascript', 'json', 'xml'];

    public function internalUrl(): string
    {
        return rtrim((string) config('services.phpmyadmin.internal_url', self::DEFAULT_INTERNAL_URL), '/') . '/';
    }

    public function unlock(Request $request, string $password): bool
    {
        $user = $request->user();
        if ($user === null || !Hash::check($password, (string) $user->getAuthPassword())) {
            Log::warning('phpMyAdmin gateway unlock rejected.', ['user_id' => $user?->getAuthIdentifier(), 'ip' => $request->ip()]);

            return false;
        }

        $now = time();
        $request->session()->put(self::SESSION_KEY, [
            'user_id' => $user->getAuthIdentifier(),
            'ip' => $request->ip(),
            'unlocked_at' => $now,
            'last_seen' => $now,
            'expires_at' => $now + self::ABSOLUTE_SECONDS,
        ]);
        Log::notice('phpMyAdmin gateway unlocked.', ['user_id' => $user->getAuthIdentifier(), 'pn' => $user->pn ?? null, 'ip' => $request->ip()]);

        return true;
    }

    public function lock(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }

    /**
     * @return array{unlocked: bool, expires_in: int, idle_expires_in: int}
     */
    public function state(Request $request): array
    {
        $gate = (array) $request->session()->get(self::SESSION_KEY, []);
        $now = time();
        $valid = $this->isValid($request, $gate, $now);

        return [
            'unlocked' => $valid,
            'expires_in' => $valid ? max(0, (int) $gate['expires_at'] - $now) : 0,
            'idle_expires_in' => $valid ? max(0, (int) $gate['last_seen'] + self::IDLE_SECONDS - $now) : 0,
        ];
    }

    public function authorizeAndTouch(Request $request): bool
    {
        $gate = (array) $request->session()->get(self::SESSION_KEY, []);
        $now = time();
        if (!$this->isValid($request, $gate, $now)) {
            $this->lock($request);

            return false;
        }

        if ($now - (int) $gate['last_seen'] >= self::TOUCH_INTERVAL_SECONDS) {
            $gate['last_seen'] = $now;
            $request->session()->put(self::SESSION_KEY, $gate);
        }

        return true;
    }

    public function forward(Request $request, string $path): Response
    {
        $path = ltrim($path, '/');
        if (preg_match('#^[A-Za-z0-9_\-./]*$#', $path) !== 1 || str_contains($path, '..')) {
            return new Response('Path tidak valid.', 400);
        }

        $query = (string) $request->server('QUERY_STRING', '');
        $url = $this->internalUrl() . $path . ($query !== '' ? '?' . $query : '');
        $method = strtoupper($request->getMethod());

        $headers = [];
        foreach (self::FORWARDED_REQUEST_HEADERS as $name) {
            if ($request->headers->has($name)) {
                $headers[$name] = (string) $request->headers->get($name);
            }
        }
        $cookie = $this->forwardableCookies((string) $request->headers->get('cookie', ''));
        if ($cookie !== '') {
            $headers['cookie'] = $cookie;
        }

        $options = [];
        $isMultipart = str_starts_with(strtolower((string) $request->headers->get('content-type', '')), 'multipart/form-data');
        if (!in_array($method, ['GET', 'HEAD'], true)) {
            if ($isMultipart) {
                unset($headers['content-type']);
                $options['multipart'] = $this->multipartParts($request);
            } else {
                $options['body'] = $request->getContent();
            }
        }

        try {
            $upstream = Http::withOptions([
                'allow_redirects' => false,
                'http_errors' => false,
                'decode_content' => true,
                'connect_timeout' => 5,
                'timeout' => 900,
                'stream' => true,
            ])->withHeaders($headers)->send($method, $url, $options)->toPsrResponse();
        } catch (Throwable $e) {
            Log::warning('phpMyAdmin gateway upstream failed.', ['message' => $e->getMessage()]);

            return new Response('phpMyAdmin lokal tidak dapat dihubungi. Pastikan Apache berjalan.', 502);
        }

        return $this->buildResponse($request, $upstream);
    }

    private function buildResponse(Request $request, ResponseInterface $upstream): Response
    {
        $contentType = strtolower($upstream->getHeaderLine('Content-Type'));
        $isText = $contentType === '' || array_filter(self::TEXT_TYPES, static fn (string $type): bool => str_contains($contentType, $type)) !== [];
        $isAttachment = str_contains(strtolower($upstream->getHeaderLine('Content-Disposition')), 'attachment');

        if ($isText && !$isAttachment) {
            $response = new Response($this->rewriteText((string) $upstream->getBody()), $upstream->getStatusCode());
        } else {
            $body = $upstream->getBody();
            $response = new StreamedResponse(static function () use ($body): void {
                while (!$body->eof()) {
                    echo $body->read(65536);
                    flush();
                }
            }, $upstream->getStatusCode());
        }

        foreach ($upstream->getHeaders() as $name => $values) {
            $lower = strtolower($name);
            if (in_array($lower, self::DROPPED_RESPONSE_HEADERS, true)) {
                continue;
            }
            if ($lower === 'location') {
                $values = array_map(fn (string $value): string => $this->rewriteLocation($value), $values);
            }
            $response->headers->set($name, $values);
        }

        // phpMyAdmin cookies bypass Laravel cookie encryption on purpose: they
        // belong to phpMyAdmin and are scoped to the gateway path only.
        if (!headers_sent()) {
            foreach ($upstream->getHeader('Set-Cookie') as $cookie) {
                header('Set-Cookie: ' . $this->rewriteCookie($cookie, $request->isSecure()), false);
            }
        }

        $request->attributes->set('security_headers.proxied', true);

        return $response;
    }

    private function rewriteText(string $body): string
    {
        $internal = $this->internalUrl();
        $internalPath = (string) parse_url($internal, PHP_URL_PATH);

        return str_replace([$internal, rtrim($internal, '/'), $internalPath], [self::ROUTE_PREFIX, rtrim(self::ROUTE_PREFIX, '/'), self::ROUTE_PREFIX], $body);
    }

    public function rewriteLocation(string $location): string
    {
        $internal = $this->internalUrl();
        $internalPath = (string) parse_url($internal, PHP_URL_PATH);

        if (str_starts_with($location, $internal)) {
            return self::ROUTE_PREFIX . substr($location, strlen($internal));
        }
        // phpMyAdmin may build absolute URLs from PmaAbsoluteUri (any host);
        // the secret alias path is what identifies them.
        if (preg_match('#^https?://[^/]+' . preg_quote($internalPath, '#') . '#i', $location, $matches) === 1) {
            return self::ROUTE_PREFIX . substr($location, strlen($matches[0]));
        }
        if (str_starts_with($location, $internalPath)) {
            return self::ROUTE_PREFIX . substr($location, strlen($internalPath));
        }

        return $location;
    }

    public function rewriteCookie(string $cookie, bool $secure): string
    {
        $internalPath = (string) parse_url($this->internalUrl(), PHP_URL_PATH);
        $cookie = (string) preg_replace('#;\s*path=' . preg_quote($internalPath, '#') . '#i', '; path=' . self::ROUTE_PREFIX, $cookie);
        if (stripos($cookie, 'path=') === false) {
            $cookie .= '; path=' . self::ROUTE_PREFIX;
        }
        if ($secure && stripos($cookie, 'secure') === false) {
            $cookie .= '; Secure';
        }

        return $cookie;
    }

    /**
     * Cookies sent to the gateway path except the application's own.
     */
    public function forwardableCookies(string $header): string
    {
        $blocked = [strtolower((string) config('session.cookie')), 'xsrf-token'];
        $pairs = [];
        foreach (explode(';', $header) as $pair) {
            $pair = trim($pair);
            $name = strtolower(trim((string) strstr($pair, '=', true)));
            if ($name === '' || in_array($name, $blocked, true) || str_starts_with($name, 'remember_')) {
                continue;
            }
            $pairs[] = $pair;
        }

        return implode('; ', $pairs);
    }

    private function isValid(Request $request, array $gate, int $now): bool
    {
        return $gate !== []
            && (string) ($gate['user_id'] ?? '') === (string) $request->user()?->getAuthIdentifier()
            && (string) ($gate['ip'] ?? '') === (string) $request->ip()
            && (int) ($gate['expires_at'] ?? 0) > $now
            && (int) ($gate['last_seen'] ?? 0) + self::IDLE_SECONDS > $now;
    }

    private function multipartParts(Request $request): array
    {
        $parts = [];
        foreach ($this->flatten($request->request->all()) as $name => $value) {
            $parts[] = ['name' => $name, 'contents' => (string) $value];
        }
        foreach ($this->flatten($request->files->all()) as $name => $file) {
            if ($file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile && $file->isValid()) {
                $parts[] = ['name' => $name, 'contents' => fopen($file->getRealPath(), 'rb'), 'filename' => $file->getClientOriginalName()];
            }
        }

        return $parts;
    }

    private function flatten(array $values, string $prefix = ''): array
    {
        $flat = [];
        foreach ($values as $key => $value) {
            $name = $prefix === '' ? (string) $key : $prefix . '[' . $key . ']';
            if (is_array($value)) {
                $flat += $this->flatten($value, $name);
            } else {
                $flat[$name] = $value;
            }
        }

        return $flat;
    }
}
