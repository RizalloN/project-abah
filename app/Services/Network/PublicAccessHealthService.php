<?php

namespace App\Services\Network;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class PublicAccessHealthService
{
    private const DOMAIN = 'asixdashboard.duckdns.org';
    private const STATUS_PATH = 'framework/network-health/public-access.json';

    /**
     * @return array<string, mixed>
     */
    public function check(bool $fix = false, bool $force = false): array
    {
        $startedAt = microtime(true);
        $publicIp = $this->detectPublicIp();
        $dnsIp = $this->resolveDnsIp(self::DOMAIN);
        $ipMatches = $publicIp !== null && $dnsIp !== null && $publicIp === $dnsIp;

        $fixResult = null;
        if ($fix && $publicIp !== null && (!$ipMatches || $force)) {
            $fixResult = $this->updateDuckDns();
            $dnsIp = $this->resolveDnsIp(self::DOMAIN);
            $ipMatches = $dnsIp !== null && $publicIp === $dnsIp;
        }

        $ports = [
            80 => $this->canOpenTcp(self::DOMAIN, 80),
            443 => $this->canOpenTcp(self::DOMAIN, 443),
        ];
        $httpStatus = $this->fetchHttpStatus((string) config('app.url', 'https://' . self::DOMAIN));

        $healthy = $ipMatches
            && in_array(true, $ports, true)
            && $httpStatus !== null
            && $httpStatus >= 200
            && $httpStatus < 500;

        $status = [
            'healthy' => $healthy,
            'domain' => self::DOMAIN,
            'public_ip' => $publicIp,
            'dns_ip' => $dnsIp,
            'ip_matches' => $ipMatches,
            'ports' => $ports,
            'http_status' => $httpStatus,
            'fix_attempted' => $fixResult !== null,
            'fix_exit_code' => $fixResult['exit_code'] ?? null,
            'checked_at' => now()->toDateTimeString(),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ];

        $this->writeStatus($status);

        return $status;
    }

    private function detectPublicIp(): ?string
    {
        $services = [
            'https://api.ipify.org',
            'https://checkip.amazonaws.com',
            'https://ifconfig.me/ip',
        ];

        foreach ($services as $url) {
            try {
                $response = Http::timeout(5)->get($url);
                if (!$response->successful()) {
                    continue;
                }

                $ip = trim($response->body());
                if ($this->isPublicIpv4($ip)) {
                    return $ip;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    private function resolveDnsIp(string $domain): ?string
    {
        $ips = @gethostbynamel($domain);
        if (!is_array($ips)) {
            return null;
        }

        foreach ($ips as $ip) {
            if ($this->isPublicIpv4((string) $ip)) {
                return (string) $ip;
            }
        }

        return null;
    }

    /**
     * @return array{exit_code: int, output: string}
     */
    private function updateDuckDns(): array
    {
        $exitCode = Artisan::call('network:update-duckdns');
        $output = Artisan::output();
        $output = preg_replace('/token=[^&"\s]+/i', 'token=***', $output) ?? $output;
        $output = preg_replace('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', '***', $output) ?? $output;

        return [
            'exit_code' => (int) $exitCode,
            'output' => trim($output),
        ];
    }

    private function canOpenTcp(string $host, int $port): bool
    {
        $socket = @fsockopen($host, $port, $errno, $errstr, 3.0);
        if (!is_resource($socket)) {
            return false;
        }

        fclose($socket);

        return true;
    }

    private function fetchHttpStatus(string $url): ?int
    {
        try {
            $response = Http::timeout(8)
                ->withOptions(['allow_redirects' => false])
                ->get($url);

            return $response->status();
        } catch (\Throwable) {
            return null;
        }
    }

    private function isPublicIpv4(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }

    /**
     * @param array<string, mixed> $status
     */
    private function writeStatus(array $status): void
    {
        $path = storage_path(self::STATUS_PATH);
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
