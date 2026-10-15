<?php

namespace App\Support;

class EkosistemGeography
{
    /** Project the existing BIG district boundaries; no inferred prospect coordinates. */
    public function build(array $allowedBranches): array
    {
        $path = public_path((string) config('marketshare-geography.source.geojson_path'));
        $result = ['ready' => false, 'regions' => [], 'source' => config('marketshare-geography.source.label')];
        if (! is_file($path)) {
            return $result;
        }
        $geo = json_decode((string) file_get_contents($path), true);
        $definitions = (array) config('marketshare-geography.branches');
        $regions = [];
        $points = [];
        foreach ($geo['features'] ?? [] as $feature) {
            foreach ($allowedBranches as $key) {
                if (! in_array($feature['properties']['KDPKAB'] ?? '', $definitions[$key]['regency_codes'] ?? [], true)) {
                    continue;
                }
                $geometry = $feature['geometry'] ?? [];
                $polygons = ($geometry['type'] ?? '') === 'Polygon' ? [$geometry['coordinates']] : (($geometry['type'] ?? '') === 'MultiPolygon' ? $geometry['coordinates'] : []);
                foreach ($polygons as $polygon) {
                    $regions[$key][] = $polygon;
                    foreach ($polygon as $ring) {
                        foreach ($ring as $point) {
                            $points[] = $point;
                        }
                    }
                }
            }
        }
        if ($points === []) {
            return $result;
        }
        $minX = min(array_column($points, 0));
        $maxX = max(array_column($points, 0));
        $minY = min(array_column($points, 1));
        $maxY = max(array_column($points, 1));
        $cos = cos(deg2rad(($minY + $maxY) / 2));
        $scale = min(610 / max(($maxX - $minX) * $cos, .000001), 425 / max($maxY - $minY, .000001));
        $offsetX = (700 - ($maxX - $minX) * $cos * $scale) / 2;
        $offsetY = (490 - ($maxY - $minY) * $scale) / 2;
        $project = static fn (array $point): array => [($point[0] - $minX) * $cos * $scale + $offsetX, ($maxY - $point[1]) * $scale + $offsetY];
        $projected = array_map($project, $points);
        $left = min(array_column($projected, 0)) - 30;
        $top = min(array_column($projected, 1)) - 25;
        $right = max(array_column($projected, 0)) + 30;
        $bottom = max(array_column($projected, 1)) + 25;
        $result['viewBox'] = implode(' ', array_map(static fn ($n) => round($n, 2), [$left, $top, $right - $left, $bottom - $top]));
        $result['north'] = ['x' => round($right - 14, 2), 'y' => round($top + 50, 2)];
        $palette = ['madiun' => '#0857c3', 'magetan' => '#11849a', 'ngawi' => '#5b64b7', 'ponorogo' => '#317b59'];
        foreach ($regions as $key => $polygons) {
            $paths = [];
            $vertices = [];
            foreach ($polygons as $polygon) {
                $d = '';
                foreach ($polygon as $ring) {
                    foreach ($ring as $i => $point) {
                        [$x, $y] = $project($point);
                        $d .= ($i === 0 ? 'M' : 'L').round($x, 2).','.round($y, 2);
                        $vertices[] = [$x, $y];
                    }
                    $d .= 'Z';
                }
                $paths[] = $d;
            }
            // This is a region label, not a prospect location pin.
            $center = [(min(array_column($vertices, 0)) + max(array_column($vertices, 0))) / 2,
                (min(array_column($vertices, 1)) + max(array_column($vertices, 1))) / 2];
            $result['regions'][$key] = ['paths' => $paths, 'label' => $definitions[$key]['label'],
                'color' => $palette[$key], 'x' => round($center[0], 2), 'y' => round($center[1], 2)];
        }
        $result['ready'] = $result['regions'] !== [];

        return $result;
    }
}
