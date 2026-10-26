<?php

namespace App\Services\Import;

use App\Services\Import\Crosstab\SpreadsheetCrosstabNormalizer;
use RuntimeException;

/**
 * Turns an Excel crosstab export of an SSA report (merged or blank-repeated labels)
 * into a flat staging file. Already flat exports return null and keep their
 * existing import rules and aliases.
 */
class SsaSpreadsheetStagingService
{
    public function __construct(private readonly SpreadsheetCrosstabNormalizer $normalizer)
    {
    }

    public function supports(string $tableName): bool
    {
        return $this->normalizer->profileFor($tableName) !== null;
    }

    public function stagedExtension(string $tableName): string
    {
        return $this->normalizer->profileFor($tableName)?->extension ?? 'csv';
    }

    public function stage(string $sourcePath, string $tableName, string $outputPath, ?callable $progress = null): ?array
    {
        $profile = $this->normalizer->profileFor($tableName)
            ?? throw new RuntimeException('Report tidak memiliki profil normalisasi crosstab.');

        return $this->normalizer->normalize($sourcePath, $profile, $outputPath, $progress);
    }
}
