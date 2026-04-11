<?php

namespace App\Services\Import\Strategies;

interface ImportStrategyInterface
{
    public function key(): string;

    public function supports(?object $report, ?string $tableName = null): bool;

    public function prepareContext(array $context): array;

    public function validateSchema(array $availableColumns): array;

    public function transformHeaders(array $headers): array;

    public function importMode(array $context = []): string;
}
