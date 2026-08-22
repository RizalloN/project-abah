<?php

namespace App\Http\Controllers\Import\Concerns;

trait BuildsArea6PreviewFilters
{
    private const AREA6_PREVIEW_BRANCHES = [
        'MADIUN' => ['label' => 'KC MADIUN', 'codes' => ['45', '0045']],
        'MAGETAN' => ['label' => 'KC MAGETAN', 'codes' => ['49', '0049']],
        'NGAWI' => ['label' => 'KC NGAWI', 'codes' => ['57', '0057']],
        'PONOROGO' => ['label' => 'KC PONOROGO', 'codes' => ['70', '0070']],
    ];

    protected function defaultArea6PreviewColumnHints(): array
    {
        return [
            'NAMA_KCI',
            'NAMA KCI',
            'NAMA_CABANG_INDUK',
            'NAMA CABANG INDUK',
            'KANTOR_CABANG_INDUK',
            'KANTOR CABANG INDUK',
            'KC_INDUK',
            'KC INDUK',
            'NAMA_KANCA',
            'NAMA KANCA',
            'KANCA',
            'KCI',
            'MBDESC',
            'MAINBR',
            'MAIN_BRANCH',
            'CABANG',
            'BRANCH',
            'BRDESC',
        ];
    }

    protected function buildInitialArea6Selections(
        array $headers,
        array $formattedUniqueValues,
        array $columnHints = []
    ): array {
        $candidateIndices = $this->findArea6PreviewColumnIndices($headers, $columnHints);
        $bestIndex = null;
        $bestSelected = [];
        $bestBranchCount = 0;

        foreach ($candidateIndices as $index) {
            $selected = [];
            $selectedBranches = [];
            foreach ((array) ($formattedUniqueValues[$index] ?? []) as $value) {
                $value = trim((string) $value);
                if ($value === '') {
                    continue;
                }

                $branch = $this->resolveArea6PreviewBranch($value);
                if ($branch !== null) {
                    $selected[$value] = true;
                    $selectedBranches[$branch] = true;
                }
            }

            $branchCount = count($selectedBranches);
            if ($branchCount > $bestBranchCount) {
                $bestIndex = $index;
                $bestSelected = array_keys($selected);
                $bestBranchCount = $branchCount;
            }

            if ($branchCount === count(self::AREA6_PREVIEW_BRANCHES)) {
                break;
            }
        }

        // One best branch column avoids combining KCI and unit-level filters.
        return $bestIndex === null ? [] : [(string) $bestIndex => $bestSelected];
    }

    protected function findArea6PreviewColumnIndices(array $headers, array $columnHints = []): array
    {
        $hints = $columnHints !== [] ? $columnHints : $this->defaultArea6PreviewColumnHints();
        $normalizedHints = array_values(array_unique(array_filter(array_map(
            fn ($hint): string => $this->normalizeArea6PreviewToken((string) $hint),
            $hints
        ))));

        $candidates = [];
        foreach ($headers as $index => $header) {
            $headerToken = $this->normalizeArea6PreviewToken((string) $header);
            if ($headerToken === '' || str_contains($headerToken, 'KODE')) {
                continue;
            }

            $hintPosition = null;
            foreach ($normalizedHints as $position => $hint) {
                if ($headerToken === $hint || str_contains($headerToken, $hint)) {
                    $hintPosition = $position;
                    break;
                }
            }

            if ($hintPosition === null) {
                continue;
            }

            $candidates[] = [
                'index' => (int) $index,
                'score' => $this->area6PreviewHeaderPriority($headerToken) + $hintPosition,
            ];
        }

        usort($candidates, static function (array $left, array $right): int {
            return [$left['score'], $left['index']] <=> [$right['score'], $right['index']];
        });

        return array_column($candidates, 'index');
    }

    protected function collectArea6PreviewValues(array $row, array $candidateIndices, array &$uniqueValues): void
    {
        foreach ($candidateIndices as $index) {
            $value = trim((string) ($row[$index] ?? ''));
            if ($value === '' || $this->resolveArea6PreviewBranch($value) === null) {
                continue;
            }

            if (!isset($uniqueValues[$index]) || !is_array($uniqueValues[$index])) {
                $uniqueValues[$index] = [];
            }
            $uniqueValues[$index][$value] = true;
        }
    }

    protected function hasAllArea6PreviewBranches(array $uniqueValues, array $candidateIndices): bool
    {
        foreach ($candidateIndices as $index) {
            $found = [];
            foreach (array_keys((array) ($uniqueValues[$index] ?? [])) as $value) {
                $branch = $this->resolveArea6PreviewBranch((string) $value);
                if ($branch !== null) {
                    $found[$branch] = true;
                }
            }

            if (count($found) === count(self::AREA6_PREVIEW_BRANCHES)) {
                return true;
            }
        }

        return false;
    }

    private function area6PreviewHeaderPriority(string $headerToken): int
    {
        if (
            str_contains($headerToken, 'NAMA_KCI')
            || str_contains($headerToken, 'NAMA_CABANG_INDUK')
            || str_contains($headerToken, 'NAMA_KANCA')
            || str_contains($headerToken, 'KANTOR_CABANG_INDUK')
            || str_contains($headerToken, 'KC_INDUK')
        ) {
            return 0;
        }

        return match ($headerToken) {
            'KCI', 'KANCA' => 20,
            'MBDESC', 'MAINBR', 'MAIN_BRANCH' => 30,
            'CABANG', 'NAMA_CABANG' => 40,
            'BRANCH', 'NAMA_BRANCH' => 50,
            'BRDESC', 'NAMA_BRDESC' => 60,
            default => 100,
        };
    }

    private function resolveArea6PreviewBranch(string $value): ?string
    {
        $normalized = $this->normalizeArea6PreviewToken($value);
        if ($normalized === '') {
            return null;
        }

        $tokens = array_values(array_filter(explode('_', $normalized)));
        foreach (self::AREA6_PREVIEW_BRANCHES as $branch => $config) {
            $hasBranchName = in_array($branch, $tokens, true);
            $hasBranchMarker = preg_match('/(^|_)(KC|KCI|KC_INDUK|KANCA|CABANG|MAINBR|MAIN_BRANCH)($|_)/', $normalized) === 1
                || str_contains($normalized, 'KANTOR_CABANG_INDUK');

            foreach ($config['codes'] as $code) {
                if (
                    in_array($code, $tokens, true)
                    && ($normalized === $code || $hasBranchMarker || $hasBranchName)
                ) {
                    return $branch;
                }
            }

            if ($normalized === $branch) {
                return $branch;
            }

            if ($hasBranchMarker && $hasBranchName) {
                return $branch;
            }
        }

        return null;
    }

    private function normalizeArea6PreviewToken(string $value): string
    {
        $normalized = strtoupper(trim($value));
        $normalized = preg_replace('/[^A-Z0-9]+/', '_', $normalized);

        return trim((string) $normalized, '_');
    }
}
