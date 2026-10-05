<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KeragaanPdfGeography
{
    /** Build printable district polygons with labels anchored to their service areas. */
    public function build(array $branches, ?array $activeOffices = null): array
    {
        $selected = [];
        foreach ((array) config('marketshare-geography.branches', []) as $key => $definition) {
            $scope = UserBranchScope::forKey($key);
            foreach ($branches as $branch) {
                if (in_array(strtolower(trim((string) $branch)), [$key, strtolower('KC '.$definition['label']), $scope['code5'] ?? '', $scope['code4'] ?? ''], true)) {
                    $selected[$key] = $definition + ['code' => $scope['code5']];
                }
            }
        }
        $result = [
            'ready' => false, 'svg' => '', 'maps' => [], 'branches' => [], 'districts' => [],
            'unit_count' => 0, 'mapped_unit_count' => 0, 'unmapped_units' => [],
            'disclosure' => '',
            'source' => (string) config('marketshare-geography.source.label', 'Badan Informasi Geospasial'),
        ];
        $path = public_path((string) config('marketshare-geography.source.geojson_path', 'data/marketshare-area6-kecamatan.geojson'));
        if ($selected === [] || ! is_file($path)) {
            return $result;
        }
        $geo = json_decode((string) file_get_contents($path), true);
        $features = [];
        foreach ((array) ($geo['features'] ?? []) as $feature) {
            foreach ($selected as $key => $branch) {
                if (in_array((string) ($feature['properties']['KDPKAB'] ?? ''), (array) $branch['regency_codes'], true)) {
                    $feature['branch'] = $key;
                    $features[(string) $feature['properties']['KDCPUM']] = $feature;
                    break;
                }
            }
        }
        ksort($features);
        $offices = DB::table('referensi_uker')->whereIn('kode_cabang', array_column($selected, 'code'))
            ->orderBy('kode_cabang')->orderBy('nama_uker')->get(['kode_uker', 'nama_uker', 'kode_cabang']);
        $mapping = (array) config('marketshare-geography.unit_districts', []);
        $districtUnits = [];
        $branchCounts = [];
        $matched = [];
        foreach ($offices as $office) {
            $code = str_pad((string) $office->kode_uker, 5, '0', STR_PAD_LEFT);
            $branchCode = str_pad((string) $office->kode_cabang, 5, '0', STR_PAD_LEFT);
            $branchKey = array_search($branchCode, array_column($selected, 'code', 'label'), true);
            $displayName = (string) $office->nama_uker;
            if ($activeOffices !== null) {
                $active = false;
                foreach ($activeOffices as $index => $identity) {
                    $scopeName = $this->identity((string) ($identity['kanca_label'] ?? $identity['kanca_key'] ?? ''));
                    $sameBranch = $scopeName === $this->identity('KC '.$branchKey);
                    $keys = [$identity['unit_key'] ?? '', $identity['unit_label'] ?? ''];
                    foreach ($keys as $value) {
                        if ($sameBranch && ($this->identity((string) $value) === $this->identity($office->nama_uker)
                            || (ctype_digit((string) $value) && str_pad((string) $value, 5, '0', STR_PAD_LEFT) === $code))) {
                            $active = true;
                            $displayName = (string) ($identity['unit_label'] ?? $office->nama_uker);
                            $matched[$index] = true;
                        }
                    }
                }
                if (! $active) {
                    continue;
                }
            }
            $unit = ['code' => $code, 'name' => (string) $office->nama_uker, 'display_name' => preg_replace('/^\d+\s*[-?]+\s*/u', '', trim($displayName)) ?? $displayName];
            $districtCodes = array_values(array_filter((array) ($mapping[$code] ?? []), fn ($district) => isset($features[$district])));
            $result['unit_count']++;
            $branchCounts[$branchCode] = ($branchCounts[$branchCode] ?? 0) + 1;
            if ($districtCodes === []) {
                $result['unmapped_units'][] = $unit;

                continue;
            }
            $result['mapped_unit_count']++;
            foreach ($districtCodes as $district) {
                $districtUnits[$district][] = $unit;
            }
        }
        // Never silently lose active identities that cannot be resolved in the office registry.
        foreach ($activeOffices ?? [] as $index => $identity) {
            if (! isset($matched[$index])) {
                $scope = $this->identity((string) ($identity['kanca_label'] ?? $identity['kanca_key'] ?? ''));
                if (in_array($scope, array_map(fn ($branch) => $this->identity('KC '.$branch['label']), $selected), true)) {
                    $result['unit_count']++;
                    $result['unmapped_units'][] = ['code' => $identity['unit_key'] ?? '', 'name' => $identity['unit_label'] ?? $identity['unit_key'] ?? ''];
                }
            }
        }
        foreach ($selected as $key => $branch) {
            $branchFeatures = array_filter($features, fn ($feature) => $feature['branch'] === $key);
            if ($branchFeatures === []) {
                continue;
            }
            $map = $this->renderMap($branchFeatures, $districtUnits);
            $result['maps'][] = ['label' => 'KC '.$branch['label'], 'svg' => $map, 'unit_count' => $branchCounts[$branch['code']] ?? 0];
            $result['branches'][] = ['key' => $key, 'label' => 'KC '.$branch['label'], 'color' => '#b9cecf', 'unit_count' => $branchCounts[$branch['code']] ?? 0];
        }
        $result['svg'] = $result['maps'][0]['svg'] ?? '';
        $result['ready'] = $result['maps'] !== [];

        return $result;
    }

    private function identity(string $name): string
    {
        $name = preg_replace('/^\d+\s*[-?]+\s*/u', '', trim($name)) ?? $name;
        $name = preg_replace('/\([^)]*\)$/', '', $name) ?? $name;

        return preg_replace('/-detail$/', '', Str::slug($name)) ?? '';
    }

    private function renderMap(array $features, array $districtUnits): string
    {
        $palette = ['#bfd2d8', '#ccd8bc', '#e5d5b9', '#d6c7d8', '#bcd8ce', '#ddc4be', '#c5cde2', '#dbdcb9'];
        $pairs = [];
        foreach ($features as $feature) {
            array_push($pairs, ...$this->pairs($feature['geometry']['coordinates']));
        }
        $minX = min(array_column($pairs, 0));
        $maxX = max(array_column($pairs, 0));
        $minY = min(array_column($pairs, 1));
        $maxY = max(array_column($pairs, 1));
        $longitudeScale = cos(deg2rad(($minY + $maxY) / 2));
        $scale = min(655 / max(($maxX - $minX) * $longitudeScale, 0.000001), 920 / max($maxY - $minY, 0.000001));
        $offsetX = 20 + (655 - ($maxX - $minX) * $longitudeScale * $scale) / 2;
        $offsetY = 70 + (920 - ($maxY - $minY) * $scale) / 2;
        $project = static fn (array $pair): array => [$offsetX + ($pair[0] - $minX) * $longitudeScale * $scale, $offsetY + ($maxY - $pair[1]) * $scale];
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1050" role="img" aria-label="Peta bernomor dan daftar sebaran unit kerja"><rect width="1000" height="1050" fill="#ffffff"/><rect data-map-pane="70" x="0" y="0" width="700" height="1050" fill="#ffffff"/><rect data-list-pane="30" x="700" y="0" width="300" height="1050" fill="#f7f9fa"/><text x="715" y="32" font-family="Arial,sans-serif" font-size="17" font-weight="bold" fill="#334c54">DAFTAR UNIT KERJA</text>';
        $offices = [];
        $points = [];
        $districtLabels = '';
        $labelBoxes = [];
        $index = 0;
        foreach ($features as $code => $feature) {
            $color = $palette[$index++ % count($palette)];
            $polygons = $feature['geometry']['type'] === 'Polygon' ? [$feature['geometry']['coordinates']] : $feature['geometry']['coordinates'];
            $ring = [];
            foreach ($polygons as $polygon) {
                if (count($polygon[0] ?? []) > count($ring)) {
                    $ring = $polygon[0];
                }
                $path = '';
                foreach ($polygon as $boundary) {
                    foreach ($boundary as $i => $pair) {
                        [$x, $y] = $project($pair);
                        $path .= ($i === 0 ? 'M' : 'L').round($x, 2).','.round($y, 2).' ';
                    }
                    $path .= 'Z ';
                }
                $svg .= '<path d="'.$path.'" fill="'.$color.'" fill-rule="evenodd" stroke="#ffffff" stroke-width="1.2"/>';
            }
            [$x, $y] = $project($this->interiorPoint($ring));
            $districtName = (string) $feature['properties']['WADMKC'];
            $labelWidth = max(55, strlen($districtName) * 8.3 + 12);
            $labelX = max(8, min(692 - $labelWidth, $x - $labelWidth / 2));
            $labelY = max(60, $y - 30);
            foreach ($labelBoxes as $box) {
                if ($labelX < $box[0] + $box[2] && $labelX + $labelWidth > $box[0] && abs($labelY - $box[1]) < 25) {
                    $labelY = $box[1] + 26;
                }
            }
            $labelBoxes[] = [$labelX, $labelY, $labelWidth, 23];
            $districtLabels .= '<g class="district-label"><rect x="'.round($labelX, 2).'" y="'.round($labelY, 2).'" width="'.round($labelWidth, 2).'" height="23" rx="4" fill="#ffffff" fill-opacity="0.88"/><text x="'.round($labelX + $labelWidth / 2, 2).'" y="'.round($labelY + 16, 2).'" text-anchor="middle" font-family="Arial,sans-serif" font-size="15" font-weight="bold" fill="#263d46">'.e($districtName).'</text></g>';
            if (empty($districtUnits[$code])) {
                continue;
            }
            foreach ($districtUnits[$code] as $unit) {
                $offices[$unit['code']] = $unit;
                $points[] = ['code' => $unit['code'], 'x' => $x, 'y' => $y, 'district' => $feature['properties']['WADMKC']];
            }
        }
        uasort($offices, static fn ($a, $b) => strnatcasecmp($a['display_name'], $b['display_name']));
        $number = 0;
        foreach ($offices as &$office) {
            $office['number'] = ++$number;
            $office['lines'] = explode("\n", wordwrap($office['display_name'], 28, "\n", true));
        }
        unset($office);
        $placed = [];
        $markers = '';
        foreach ($points as $point) {
            $office = $offices[$point['code']];
            [$x, $y] = $this->markerPosition($point['x'], $point['y'], $placed, $labelBoxes);
            $placed[] = [$x, $y];
            $label = $office['number'].'. '.$office['display_name'].' - wilayah layanan '.$point['district'];
            $markers .= '<g class="office-marker" data-office-code="'.e($point['code']).'" data-office-number="'.$office['number'].'" tabindex="0" role="button" aria-label="'.e($label).'" transform="translate('.$x.' '.$y.')"><title>'.e($label).'</title><circle class="office-highlight" cx="0" cy="0" r="14" fill="#ffffff" stroke="#00549f" stroke-width="1.5"/><text x="0" y="4.5" text-anchor="middle" font-family="Arial,sans-serif" font-size="13" font-weight="bold" fill="#00549f">'.$office['number'].'</text></g>';
        }
        $lineCount = array_sum(array_map(static fn ($office) => count($office['lines']), $offices));
        $lineHeight = min(18, 940 / max(1, $lineCount + count($offices) * 0.6));
        $fontSize = min(15, $lineHeight / 1.2);
        $gap = min(12, $lineHeight * 0.6);
        $top = 64;
        $list = '';
        foreach ($offices as $office) {
            $height = count($office['lines']) * $lineHeight + $gap;
            $list .= '<g class="office-list-entry" data-office-code="'.e($office['code']).'" data-office-number="'.$office['number'].'" tabindex="0" role="button" aria-label="'.e($office['number'].'. '.$office['display_name']).'"><title>'.e($office['display_name']).'</title><rect class="office-highlight" x="709" y="'.round($top - 5, 2).'" width="284" height="'.round($height, 2).'" rx="4" fill="transparent"/><circle cx="730" cy="'.round($top + 7, 2).'" r="11" fill="#e5edf2"/><text x="730" y="'.round($top + 11.5, 2).'" text-anchor="middle" font-family="Arial,sans-serif" font-size="13" font-weight="bold" fill="#00549f">'.$office['number'].'</text>';
            foreach ($office['lines'] as $lineIndex => $line) {
                $list .= '<text x="751" y="'.round($top + 12 + $lineIndex * $lineHeight, 2).'" font-family="Arial,sans-serif" font-size="'.round($fontSize, 2).'" fill="#263d46">'.e($line).'</text>';
            }
            $list .= '</g>';
            $top += $height;
        }

        return $svg.$districtLabels.$markers.$list.'<path d="M665 53 L665 25 M659 34 L665 25 L671 34" fill="none" stroke="#50686e" stroke-width="2"/><text x="665" y="18" text-anchor="middle" font-family="Arial,sans-serif" font-size="14" fill="#50686e">U</text></svg>';
    }

    /** Keep numbers near their service district without overlapping labels. */
    private function markerPosition(float $anchorX, float $anchorY, array $placed, array $labelBoxes = []): array
    {
        for ($radius = 0; $radius <= 960; $radius += 12) {
            $steps = max(1, (int) ceil(2 * M_PI * $radius / 12));
            for ($step = 0; $step < $steps; $step++) {
                $angle = 2 * M_PI * $step / $steps;
                $x = round($anchorX + cos($angle) * $radius, 2);
                $y = round($anchorY + sin($angle) * $radius, 2);
                if ($x < 22 || $x > 660 || $y < 78 || $y > 1000) {
                    continue;
                }
                foreach ($labelBoxes as [$left, $top, $width, $height]) {
                    if ($x + 16 > $left && $x - 16 < $left + $width && $y + 16 > $top && $y - 16 < $top + $height) {
                        continue 2;
                    }
                }
                foreach ($placed as [$otherX, $otherY]) {
                    if (abs($x - $otherX) < 32 && abs($y - $otherY) < 32) {
                        continue 2;
                    }
                }

                return [$x, $y];
            }
        }

        return [round($anchorX, 2), round($anchorY, 2)];
    }

    private function pairs(array $coordinates): array
    {
        if (isset($coordinates[0], $coordinates[1]) && is_numeric($coordinates[0]) && is_numeric($coordinates[1])) {
            return [[$coordinates[0], $coordinates[1]]];
        }
        $pairs = [];
        foreach ($coordinates as $child) {
            array_push($pairs, ...$this->pairs($child));
        }

        return $pairs;
    }

    private function interiorPoint(array $ring): array
    {
        $y = (min(array_column($ring, 1)) + max(array_column($ring, 1))) / 2;
        $crossings = [];
        for ($i = 0, $j = count($ring) - 1; $i < count($ring); $j = $i++) {
            if (($ring[$i][1] > $y) !== ($ring[$j][1] > $y)) {
                $crossings[] = $ring[$i][0] + ($y - $ring[$i][1]) * ($ring[$j][0] - $ring[$i][0]) / ($ring[$j][1] - $ring[$i][1]);
            }
        }
        sort($crossings);
        $width = -1;
        $x = $ring[0][0];
        for ($i = 0; $i + 1 < count($crossings); $i += 2) {
            if ($width < $crossings[$i + 1] - $crossings[$i]) {
                $width = $crossings[$i + 1] - $crossings[$i];
                $x = ($crossings[$i] + $crossings[$i + 1]) / 2;
            }
        }

        return [$x, $y];
    }
}
