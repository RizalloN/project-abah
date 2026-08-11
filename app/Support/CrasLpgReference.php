<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CrasLpgReference
{
    private const BUNDLED_PATH = 'resources/data/cras-lpg-reference.json';

    private ?array $data = null;

    public function ready(): bool
    {
        return $this->data() !== [];
    }

    public function source(): array
    {
        return (array) ($this->data()['source'] ?? []);
    }

    public function fingerprint(): string
    {
        return (string) ($this->source()['mapping_sha256'] ?? $this->source()['sha256'] ?? 'missing');
    }

    public function resolve(string $sourceSubSector, string $segment): ?array
    {
        $segment = $this->segmentKey($segment);
        if ($segment === null) {
            return null;
        }

        $economic = (array) data_get($this->data(), 'economic.'.self::normalize($sourceSubSector), []);
        if ($economic === []) {
            return null;
        }

        $industrySubSector = (string) ($economic['industry_sub_sector'] ?? '');
        $sacKey = (string) ($economic['sac_key'] ?? self::normalize($industrySubSector));
        $sac = (array) data_get($this->data(), 'sac.'.$sacKey, []);
        $color = $sac[$segment] ?? null;

        return [
            'industry_sector' => trim((string) ($economic['industry_sector'] ?? '')),
            'industry_sub_sector' => $industrySubSector,
            'sac_sub_sector' => trim((string) ($sac['industry_sub_sector'] ?? '')),
            'sac_match' => (string) ($economic['sac_match'] ?? 'unmatched'),
            'color' => is_string($color) && $color !== '' ? $color : null,
            'lbu_codes' => array_values((array) ($economic['lbu_codes'] ?? [])),
        ];
    }

    public static function normalize(string $value): string
    {
        $value = strtoupper(trim(Str::ascii($value)));

        return trim((string) preg_replace('/[^A-Z0-9]+/', ' ', $value));
    }

    public function segmentKey(string $value): ?string
    {
        return match (self::normalize($value)) {
            'MICRO', 'MIKRO' => 'micro',
            'SMALL' => 'small',
            default => null,
        };
    }

    private function data(): array
    {
        if ($this->data !== null) {
            return $this->data;
        }

        $paths = [
            storage_path('app/reference/cras-lpg-reference.json'),
            base_path(self::BUNDLED_PATH),
        ];
        $path = collect($paths)->first(fn (string $candidate): bool => is_file($candidate));
        if (! is_string($path)) {
            return $this->data = [];
        }

        $cacheKey = 'cras_lpg_reference:v1:'.md5($path.'|'.filemtime($path).'|'.filesize($path));

        return $this->data = Cache::rememberForever($cacheKey, static function () use ($path): array {
            $decoded = json_decode((string) file_get_contents($path), true);

            return is_array($decoded) ? $decoded : [];
        });
    }
}
