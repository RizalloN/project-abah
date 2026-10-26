<?php

namespace App\Services\Import\Crosstab;

/**
 * Describes one report's flat layout so a crosstab export of it can be flattened.
 *
 * The first $dimensionCount columns are grouping labels (repeated by merges or by
 * blank cells in an Excel crosstab); the rest are measures and are never copied
 * from another row.
 */
final class CrosstabProfile
{
    /** Validate dates and numbers and rewrite them to the canonical staging format. */
    public const VALUES_VALIDATED = 'validated';

    /** Keep every cell exactly as the source wrote it; only the period date is canonicalized. */
    public const VALUES_SOURCE = 'source';

    /**
     * @param  list<string>  $columns  canonical column keys in output order
     * @param  array<string,string>  $aliases  normalized header label => canonical key
     * @param  list<string>|null  $outputHeaders  header labels written to the staged file (defaults to $columns)
     * @param  list<string>  $integerColumns  validated mode: columns that must be non-negative integers
     * @param  list<string>  $numericDimensions  validated mode: dimensions that are numeric codes
     * @param  \Closure(string):string|null  $headerResolver  maps a raw header label to a canonical key
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly array $columns,
        public readonly int $dimensionCount,
        public readonly array $aliases = [],
        public readonly ?array $outputHeaders = null,
        public readonly string $delimiter = ',',
        public readonly string $extension = 'csv',
        public readonly string $valueMode = self::VALUES_VALIDATED,
        public readonly string $dateFormat = 'Y-m-d',
        public readonly bool $rejectBlankDimensions = true,
        public readonly bool $rejectBlankMeasures = true,
        public readonly array $integerColumns = [],
        public readonly array $numericDimensions = [],
        public readonly bool $passthroughNeedsExactHeaders = false,
        public readonly ?\Closure $headerResolver = null,
    ) {
    }

    public function dimensions(): array
    {
        return array_slice($this->columns, 0, $this->dimensionCount);
    }

    public function isMeasure(string $column): bool
    {
        $index = array_search($column, $this->columns, true);

        return $index !== false && $index >= $this->dimensionCount;
    }

    public function headers(): array
    {
        return $this->outputHeaders ?? $this->columns;
    }

    public function dateColumn(): string
    {
        return $this->columns[0];
    }
}
