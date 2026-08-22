<?php

namespace App\Http\Controllers\Import\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

trait ServesMappedCsvPreviewFilters
{
    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    abstract protected function resolveMappedPreviewSource(string $requestedPath, ?string $requestedDelimiter = null): array;

    abstract protected function iterateMappedPreviewRows(string $path, array $context, callable $callback): void;

    abstract protected function mappedPreviewCacheNamespace(): string;

    abstract protected function mappedPreviewLabel(): string;

    /**
     * @return array<int, string>|null
     */
    protected function mappedPreviewFilterableColumns(): ?array
    {
        return null;
    }

    protected function mappedPreviewFilterOptionLimit(): int
    {
        return 5000;
    }

    /**
     * Build only the first visual sample. Complete filter options remain available
     * from the on-demand endpoint and the final import still reads the whole file.
     *
     * @return array{0: array<int, array<int, mixed>>, 1: array<int, array<int, string>>}
     */
    protected function collectMappedPreviewSample(
        string $path,
        array $context,
        int $rowLimit = 100,
        int $scanLimit = 1000,
        int $uniqueLimit = 200
    ): array {
        $headers = array_values((array) ($context['headers'] ?? []));
        $filterableColumns = $this->mappedPreviewFilterableColumns();
        $filterableMap = is_array($filterableColumns) ? array_fill_keys($filterableColumns, true) : null;
        $previewRows = [];
        $uniqueValues = array_fill(0, count($headers), []);
        $rowsScanned = 0;
        $rowLimit = max(0, $rowLimit);
        $scanLimit = max($rowLimit, $scanLimit);
        $uniqueLimit = max(1, $uniqueLimit);

        $this->iterateMappedPreviewRows($path, $context, function (array $row) use (
            &$previewRows,
            &$uniqueValues,
            &$rowsScanned,
            $headers,
            $filterableMap,
            $rowLimit,
            $scanLimit,
            $uniqueLimit
        ): bool {
            $rowsScanned++;

            if (count($previewRows) < $rowLimit) {
                $previewRows[] = $row;
            }

            foreach ($headers as $columnIndex => $header) {
                if ($filterableMap !== null && ! isset($filterableMap[$header])) {
                    continue;
                }

                $value = trim((string) ($row[$columnIndex] ?? ''));
                if (isset($uniqueValues[$columnIndex][$value])) {
                    continue;
                }

                if (count($uniqueValues[$columnIndex]) < $uniqueLimit) {
                    $uniqueValues[$columnIndex][$value] = true;
                }
            }

            return $rowsScanned < $scanLimit;
        });

        return [$previewRows, $this->formatMappedPreviewUniqueValues($uniqueValues)];
    }

    public function previewFilterOptions(Request $request): JsonResponse
    {
        $request->validate([
            'file_path' => 'required|string',
            'delimiter' => 'nullable|string',
            'column_index' => 'required|integer|min:0',
            'active_filters_json' => 'nullable|string',
        ]);

        try {
            [$workingPath, $context] = $this->resolveMappedPreviewSource(
                (string) $request->input('file_path'),
                $request->filled('delimiter') ? (string) $request->input('delimiter') : null
            );

            $headers = array_values((array) ($context['headers'] ?? []));
            $columnIndex = (int) $request->input('column_index');
            if (! array_key_exists($columnIndex, $headers)) {
                throw new RuntimeException('Kolom filter tidak tersedia pada file preview.');
            }

            if (! $this->isMappedPreviewColumnFilterable((string) $headers[$columnIndex])) {
                return response()->json([
                    'status' => 'success',
                    'values' => [],
                    'cached' => false,
                ]);
            }

            $activeFilters = $this->normalizeMappedPreviewFilters(
                json_decode((string) $request->input('active_filters_json', ''), true),
                count($headers),
                $columnIndex
            );
            $cacheKey = $this->mappedPreviewFilterCacheKey($workingPath, $context, $columnIndex, $activeFilters);
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return response()->json([
                    'status' => 'success',
                    'values' => $cached,
                    'cached' => true,
                ]);
            }

            $valuesMap = [];
            $limit = max(1, $this->mappedPreviewFilterOptionLimit());
            $this->iterateMappedPreviewRows($workingPath, $context, function (array $row) use (
                &$valuesMap,
                $activeFilters,
                $columnIndex,
                $limit
            ): bool {
                if (! $this->mappedPreviewRowPassesFilters($row, $activeFilters)) {
                    return true;
                }

                $value = trim((string) ($row[$columnIndex] ?? ''));
                if (! isset($valuesMap[$value])) {
                    $valuesMap[$value] = true;
                }

                return count($valuesMap) < $limit;
            });

            $values = array_keys($valuesMap);
            usort($values, 'strnatcmp');
            Cache::put($cacheKey, $values, now()->addHours(2));

            return response()->json([
                'status' => 'success',
                'values' => $values,
                'cached' => false,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memuat opsi filter '.$this->mappedPreviewLabel().': '.$e->getMessage(),
            ], 422);
        }
    }

    public function previewFilteredRows(Request $request): JsonResponse
    {
        $request->validate([
            'file_path' => 'required|string',
            'delimiter' => 'nullable|string',
            'active_filters_json' => 'nullable|string',
            'limit' => 'nullable|integer|min:1|max:200',
        ]);

        try {
            [$workingPath, $context] = $this->resolveMappedPreviewSource(
                (string) $request->input('file_path'),
                $request->filled('delimiter') ? (string) $request->input('delimiter') : null
            );

            $headers = array_values((array) ($context['headers'] ?? []));
            $activeFilters = $this->normalizeMappedPreviewFilters(
                json_decode((string) $request->input('active_filters_json', ''), true),
                count($headers)
            );
            $limit = (int) $request->input('limit', 100);
            $rows = [];
            $matchedCount = 0;
            $truncated = false;

            $this->iterateMappedPreviewRows($workingPath, $context, function (array $row) use (
                &$rows,
                &$matchedCount,
                &$truncated,
                $activeFilters,
                $limit
            ): bool {
                if (! $this->mappedPreviewRowPassesFilters($row, $activeFilters)) {
                    return true;
                }

                $matchedCount++;
                if (count($rows) < $limit) {
                    $rows[] = $row;

                    return true;
                }

                $truncated = true;

                return false;
            });

            return response()->json([
                'status' => 'success',
                'rows' => $rows,
                'total_matched' => $truncated ? null : $matchedCount,
                'returned_rows' => count($rows),
                'truncated' => $truncated,
                'source' => $this->mappedPreviewCacheNamespace(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memuat hasil filter '.$this->mappedPreviewLabel().': '.$e->getMessage(),
            ], 422);
        }
    }

    private function isMappedPreviewColumnFilterable(string $header): bool
    {
        $filterableColumns = $this->mappedPreviewFilterableColumns();

        return ! is_array($filterableColumns) || in_array($header, $filterableColumns, true);
    }

    /**
     * @return array<int, array<string, true>>
     */
    private function normalizeMappedPreviewFilters($filters, int $columnCount, ?int $excludeColumnIndex = null): array
    {
        if (! is_array($filters)) {
            return [];
        }

        $normalized = [];
        foreach ($filters as $columnIndex => $values) {
            $columnIndex = (int) $columnIndex;
            if ($columnIndex < 0 || $columnIndex >= $columnCount || $columnIndex === $excludeColumnIndex) {
                continue;
            }

            $normalizedValues = array_values(array_unique(array_map(
                static fn ($value): string => trim((string) $value),
                is_array($values) ? $values : []
            )));
            $normalized[$columnIndex] = array_fill_keys($normalizedValues, true);
        }

        ksort($normalized);

        return $normalized;
    }

    /**
     * @param  array<int, array<string, true>>  $activeFilters
     */
    private function mappedPreviewRowPassesFilters(array $row, array $activeFilters): bool
    {
        foreach ($activeFilters as $columnIndex => $allowedValues) {
            $value = trim((string) ($row[$columnIndex] ?? ''));
            if (! isset($allowedValues[$value])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, array<string, true>>  $activeFilters
     */
    private function mappedPreviewFilterCacheKey(string $path, array $context, int $columnIndex, array $activeFilters): string
    {
        return 'mapped_preview_filter:v1:'.sha1(json_encode([
            'namespace' => $this->mappedPreviewCacheNamespace(),
            'path' => realpath($path) ?: $path,
            'size' => @filesize($path) ?: 0,
            'mtime' => @filemtime($path) ?: 0,
            'context' => [
                'headers' => array_values((array) ($context['headers'] ?? [])),
                'delimiter' => (string) ($context['delimiter'] ?? ''),
                'periode' => (string) ($context['periode'] ?? ''),
            ],
            'column' => $columnIndex,
            'filters' => $activeFilters,
        ]));
    }

    /**
     * @param  array<int, array<string, true>>  $uniqueValues
     * @return array<int, array<int, string>>
     */
    private function formatMappedPreviewUniqueValues(array $uniqueValues): array
    {
        $formatted = [];
        foreach ($uniqueValues as $columnIndex => $valuesMap) {
            $values = array_keys($valuesMap);
            usort($values, 'strnatcmp');
            $formatted[$columnIndex] = $values;
        }

        return $formatted;
    }
}
