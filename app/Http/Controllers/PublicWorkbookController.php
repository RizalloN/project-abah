<?php

namespace App\Http\Controllers;

use App\Jobs\RefreshRemoteDashboardSourcesJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PublicWorkbookController extends Controller
{
    public function marketShare(Request $request): Response
    {
        return $this->serveWorkbook(
            $request,
            'market_share',
            'market-share.xlsx',
            'Market Share',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
    }

    public function marketShareMapping(Request $request): Response
    {
        return $this->serveWorkbook(
            $request,
            'market_share_mapping',
            'market-share-mapping.xlsx',
            'Mapping Market Share',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
    }

    private function serveWorkbook(
        Request $request,
        string $configKey,
        string $filename,
        string $label,
        string $contentType
    ): Response {
        $this->authorizeToken($request, $configKey);

        $cachePath = trim(
            (string) config("services.{$configKey}.cache_path", 'app/public_workbooks/' . $filename),
            '/\\'
        );
        $path = storage_path($cachePath);
        $cacheMinutes = (int) config("services.{$configKey}.cache_minutes", 15);

        if ($cacheMinutes <= 0 || !$this->isCacheFresh($path, $cacheMinutes)) {
            $source = $configKey === 'market_share_mapping' ? 'market-share-mapping' : 'market-share';
            $pendingKey = 'dashboard_sources:refresh:' . $source . ':pending';
            if (Cache::add($pendingKey, now()->toIso8601String(), now()->addMinutes(10))) {
                RefreshRemoteDashboardSourcesJob::dispatch([$source]);
            }

            if (!$this->isUsableWorkbook($path)) {
                $sourceUrl = (string) config("services.{$configKey}.source_url", '');
                if ($sourceUrl !== '') {
                    return redirect()->away($sourceUrl);
                }
                $fallbackUrl = (string) config("services.{$configKey}.workbook_url", '');
                if ($fallbackUrl !== '') {
                    return redirect()->away($fallbackUrl);
                }
                abort(502, 'Workbook ' . $label . ' belum tersedia pada cache lokal.');
            }
        }

        return response()->file($path, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'Cache-Control' => 'public, max-age=300',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    private function authorizeToken(Request $request, string $configKey): void
    {
        $expected = (string) config("services.{$configKey}.public_token", '');
        $provided = (string) ($request->route('token') ?? $request->query('token', ''));

        abort_unless($expected !== '' && hash_equals($expected, $provided), 404);
    }

    private function isCacheFresh(string $path, int $cacheMinutes): bool
    {
        return $this->isUsableWorkbook($path)
            && filemtime($path) >= now()->subMinutes($cacheMinutes)->getTimestamp();
    }

    private function refreshWorkbook(string $path, string $configKey, string $label): void
    {
        $sourceUrl = $this->normalizeSourceUrl((string) config("services.{$configKey}.source_url", ''));
        abort_if($sourceUrl === '', 500, 'Source workbook ' . $label . ' belum dikonfigurasi.');

        $timeoutSeconds = (int) config("services.{$configKey}.timeout_seconds", 90);

        $response = Http::timeout(max(30, $timeoutSeconds))
            ->retry(2, 800)
            ->withHeaders(['User-Agent' => 'ASIXDashboardWorkbookProxy/1.0'])
            ->get($sourceUrl);

        $body = $response->body();
        $contentType = strtolower((string) $response->header('Content-Type', ''));

        abort_unless($response->successful(), 502, 'Source workbook ' . $label . ' gagal diambil.');
        abort_unless($body !== '', 502, 'Source workbook ' . $label . ' kosong.');
        abort_unless(
            str_contains($contentType, 'spreadsheet')
                || str_contains($contentType, 'octet-stream')
                || str_contains($contentType, 'application/vnd.ms-excel'),
            502,
            'Source workbook ' . $label . ' bukan file Excel.'
        );
        abort_unless(
            $this->looksLikeOfficeWorkbook($body),
            502,
            'Source workbook ' . $label . ' bukan file Excel valid.'
        );

        File::ensureDirectoryExists(dirname($path));
        File::replace($path, $body);
    }

    public function refreshMarketShareSource(): array
    {
        $cachePath = trim((string) config('services.market_share.cache_path', 'app/public_workbooks/market-share.xlsx'), '/\\');
        $path = storage_path($cachePath);

        try {
            $this->refreshWorkbook($path, 'market_share', 'Market Share');

            return ['success' => true, 'path' => $path, 'updated_at' => now()->toDateTimeString()];
        } catch (Throwable $exception) {
            Log::warning('Market Share workbook background refresh failed.', ['message' => $exception->getMessage()]);

            return ['success' => false, 'error' => $exception->getMessage()];
        } finally {
            Cache::forget('dashboard_sources:refresh:market-share:pending');
        }
    }

    private function isUsableWorkbook(string $path): bool
    {
        if (!is_file($path) || filesize($path) < 1024) {
            return false;
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }

        $head = fread($handle, 4);
        fclose($handle);

        return $this->looksLikeOfficeWorkbook((string) $head, false);
    }

    private function looksLikeOfficeWorkbook(string $content, bool $checkSize = true): bool
    {
        if ($checkSize && strlen($content) < 1024) {
            return false;
        }

        return str_starts_with($content, "PK\x03\x04")
            || str_starts_with($content, "PK\x05\x06")
            || str_starts_with($content, "PK\x07\x08");
    }

    private function normalizeSourceUrl(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return '';
        }

        if (preg_match('/<iframe\b[^>]*\bsrc=["\']([^"\']+)["\']/i', $url, $matches) === 1) {
            $url = $matches[1];
        }

        $url = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5);

        if (!str_contains(strtolower($url), 'sharepoint.com')) {
            return str_replace(['{', '}'], ['%7B', '%7D'], $url);
        }

        return $this->normalizeSharePointDownloadUrl($url);
    }

    private function normalizeSharePointDownloadUrl(string $url): string
    {
        $parts = parse_url($url);

        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return str_replace(['{', '}'], ['%7B', '%7D'], $url);
        }

        parse_str((string) ($parts['query'] ?? ''), $query);

        $path = (string) ($parts['path'] ?? '');
        $lowerPath = strtolower($path);

        if (str_contains($lowerPath, '/_layouts/15/doc.aspx')) {
            foreach (array_keys($query) as $key) {
                if (str_starts_with(strtolower($key), 'wd')) {
                    unset($query[$key]);
                }
            }

            $query['action'] = 'default';
            $query['download'] = '1';
        } elseif (str_contains($lowerPath, '/:x:/')) {
            $query['download'] = '1';
        }

        $rebuilt = $parts['scheme'] . '://' . $parts['host'];
        $rebuilt .= isset($parts['port']) ? ':' . $parts['port'] : '';
        $rebuilt .= $path;

        if ($query !== []) {
            $rebuilt .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        if (isset($parts['fragment'])) {
            $rebuilt .= '#' . $parts['fragment'];
        }

        return $rebuilt;
    }
}
