<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class SyncCrasLpgReferenceCommand extends Command
{
    protected $signature = 'cras-lpg:sync-reference
        {source : Path workbook SSA CRAS OLAH LPG}
        {--output=resources/data/cras-lpg-reference.json : Output JSON path, relative to project root or absolute}';

    protected $description = 'Ekstrak mapping sektor industri dan warna SAC LPG dari workbook referensi CRAS';

    public function handle(): int
    {
        $source = $this->absolutePath((string) $this->argument('source'));
        if (! is_file($source)) {
            $this->error("Workbook tidak ditemukan: {$source}");

            return self::FAILURE;
        }

        $reader = IOFactory::createReaderForFile($source);
        $reader->setReadDataOnly(false);
        $reader->setLoadSheetsOnly(['Generate Industrik Vs Sekom BRI', 'Sheet4']);
        $spreadsheet = $reader->load($source);

        try {
            $economicSheet = $spreadsheet->getSheetByName('Generate Industrik Vs Sekom BRI');
            $sacSheet = $spreadsheet->getSheetByName('Sheet4');
            if ($economicSheet === null || $sacSheet === null) {
                $this->error('Sheet Generate Industrik Vs Sekom BRI atau Sheet4 tidak ditemukan.');

                return self::FAILURE;
            }

            $economic = [];
            $economicConflicts = [];
            for ($row = 2; $row <= $economicSheet->getHighestDataRow(); $row++) {
                $sourceSubSector = $this->cell($economicSheet, 'F', $row);
                $industrySector = $this->cell($economicSheet, 'B', $row);
                $industrySubSector = $this->cell($economicSheet, 'D', $row);
                if ($sourceSubSector === '' || $industrySector === '' || $industrySubSector === '') {
                    continue;
                }

                $key = self::normalize($sourceSubSector);
                $candidate = [
                    'source_sub_sector' => $sourceSubSector,
                    'industry_sector' => $industrySector,
                    'industry_sub_sector' => $industrySubSector,
                    'lbu_codes' => array_values(array_filter([$this->cell($economicSheet, 'E', $row)])),
                ];
                if (isset($economic[$key])) {
                    $sameTarget = self::normalize($economic[$key]['industry_sector']) === self::normalize($industrySector)
                        && self::normalize($economic[$key]['industry_sub_sector']) === self::normalize($industrySubSector);
                    if (! $sameTarget) {
                        $economicConflicts[$key][] = $candidate;
                        continue;
                    }
                    $economic[$key]['lbu_codes'] = array_values(array_unique(array_merge(
                        $economic[$key]['lbu_codes'],
                        $candidate['lbu_codes']
                    )));
                    continue;
                }
                $economic[$key] = $candidate;
            }

            if ($economicConflicts !== []) {
                $samples = array_slice(array_keys($economicConflicts), 0, 5);
                $this->error('Ditemukan mapping subsektor ekonomi yang ambigu: '.implode(', ', $samples));

                return self::FAILURE;
            }

            $sac = [];
            $sacConflicts = [];
            for ($row = 3; $row <= $sacSheet->getHighestDataRow(); $row++) {
                $industrySubSector = $this->cell($sacSheet, 'A', $row);
                $color = $this->canonicalColor($this->cell($sacSheet, 'B', $row));
                if ($industrySubSector === '' || $color === null) {
                    continue;
                }

                $key = self::normalize($industrySubSector);
                $sac[$key] ??= [
                    'industry_sub_sector' => $industrySubSector,
                    'micro' => null,
                    'small' => null,
                ];
                foreach (['micro' => 'C', 'small' => 'D'] as $segment => $column) {
                    if (! $this->isSacEnabled($sacSheet->getCell("{$column}{$row}")->getCalculatedValue())) {
                        continue;
                    }
                    if ($sac[$key][$segment] !== null && $sac[$key][$segment] !== $color) {
                        $sacConflicts[] = "{$industrySubSector} ({$segment})";
                        continue;
                    }
                    $sac[$key][$segment] = $color;
                }
            }

            if ($sacConflicts !== []) {
                $this->error('Ditemukan warna SAC yang ambigu: '.implode(', ', array_slice(array_unique($sacConflicts), 0, 5)));

                return self::FAILURE;
            }

            $sacMatchCounts = ['exact' => 0, 'fuzzy' => 0, 'unmatched' => 0];
            foreach ($economic as &$mapping) {
                $targetKey = self::normalize((string) $mapping['industry_sub_sector']);
                $sacKey = isset($sac[$targetKey]) ? $targetKey : $this->closestSacKey($targetKey, array_keys($sac));
                $matchType = $sacKey === null ? 'unmatched' : ($sacKey === $targetKey ? 'exact' : 'fuzzy');
                $mapping['sac_key'] = $sacKey;
                $mapping['sac_sub_sector'] = $sacKey !== null ? $sac[$sacKey]['industry_sub_sector'] : null;
                $mapping['sac_match'] = $matchType;
                $sacMatchCounts[$matchType]++;
            }
            unset($mapping);

            ksort($economic, SORT_NATURAL | SORT_FLAG_CASE);
            ksort($sac, SORT_NATURAL | SORT_FLAG_CASE);
            $mappingHash = hash('sha256', json_encode([$economic, $sac], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $payload = [
                'schema_version' => 1,
                'generated_at' => now()->toIso8601String(),
                'source' => [
                    'file' => basename($source),
                    'sha256' => hash_file('sha256', $source),
                    'mapping_sha256' => $mappingHash,
                    'economic_sheet' => 'Generate Industrik Vs Sekom BRI',
                    'sac_sheet' => 'Sheet4',
                ],
                'economic' => $economic,
                'sac' => $sac,
            ];

            $output = $this->absolutePath((string) $this->option('output'));
            File::ensureDirectoryExists(dirname($output));
            File::put($output, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);

            $colorCounts = ['hijau' => 0, 'hijau_muda' => 0, 'kuning' => 0, 'merah' => 0];
            foreach ($sac as $definition) {
                foreach (['micro', 'small'] as $segment) {
                    $color = $definition[$segment];
                    if (is_string($color)) {
                        $colorCounts[$color]++;
                    }
                }
            }

            $this->info("Referensi CRAS LPG tersimpan: {$output}");
            $this->table(
                ['Mapping ekonomi', 'SAC exact', 'SAC koreksi typo', 'Belum cocok', 'Hijau', 'Hijau Muda', 'Kuning', 'Merah'],
                [[
                    count($economic),
                    $sacMatchCounts['exact'],
                    $sacMatchCounts['fuzzy'],
                    $sacMatchCounts['unmatched'],
                    $colorCounts['hijau'],
                    $colorCounts['hijau_muda'],
                    $colorCounts['kuning'],
                    $colorCounts['merah'],
                ]]
            );

            return self::SUCCESS;
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    public static function normalize(string $value): string
    {
        $value = strtoupper(trim(Str::ascii($value)));

        return trim((string) preg_replace('/[^A-Z0-9]+/', ' ', $value));
    }

    private function cell($sheet, string $column, int $row): string
    {
        return trim((string) $sheet->getCell("{$column}{$row}")->getFormattedValue());
    }

    private function canonicalColor(string $value): ?string
    {
        return match (self::normalize($value)) {
            'HIJAU' => 'hijau',
            'HIJAU MUDA' => 'hijau_muda',
            'KUNING' => 'kuning',
            'MERAH' => 'merah',
            default => null,
        };
    }

    private function isSacEnabled(mixed $value): bool
    {
        return in_array(strtoupper(trim((string) $value)), ['1', 'TRUE', 'YA', 'Y'], true);
    }

    private function closestSacKey(string $target, array $sacKeys): ?string
    {
        if ($target === '') {
            return null;
        }

        $bestKey = null;
        $bestDistance = PHP_INT_MAX;
        $ambiguous = false;
        $bestTokenKey = null;
        $bestTokenScore = 0.0;
        $tokenAmbiguous = false;
        foreach ($sacKeys as $candidate) {
            $distance = levenshtein($target, $candidate);
            if ($distance < $bestDistance) {
                $bestKey = $candidate;
                $bestDistance = $distance;
                $ambiguous = false;
            } elseif ($distance === $bestDistance) {
                $ambiguous = true;
            }

            $tokenScore = $this->tokenSimilarity($target, $candidate);
            if ($tokenScore > $bestTokenScore) {
                $bestTokenKey = $candidate;
                $bestTokenScore = $tokenScore;
                $tokenAmbiguous = false;
            } elseif ($tokenScore === $bestTokenScore) {
                $tokenAmbiguous = true;
            }
        }

        $maximumDistance = max(2, (int) floor(strlen($target) * 0.06));

        if (! $ambiguous && $bestDistance <= $maximumDistance) {
            return $bestKey;
        }

        return ! $tokenAmbiguous && $bestTokenScore >= 0.70 ? $bestTokenKey : null;
    }

    private function tokenSimilarity(string $left, string $right): float
    {
        $leftTokens = array_values(array_unique(array_filter(explode(' ', $left))));
        $rightTokens = array_values(array_unique(array_filter(explode(' ', $right))));
        $union = array_unique(array_merge($leftTokens, $rightTokens));
        if ($union === []) {
            return 0.0;
        }

        return count(array_intersect($leftTokens, $rightTokens)) / count($union);
    }

    private function absolutePath(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path));
        if (preg_match('/^[A-Z]:\//i', $path) === 1 || str_starts_with($path, '/')) {
            return $path;
        }

        return base_path($path);
    }
}
