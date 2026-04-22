<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class ReportIndexHintResolver
{
    /** @var array<string, bool> */
    private array $indexExistsCache = [];

    public function qualify(string $table, ?string $alias = null, array $preferredIndexes = []): string
    {
        $qualifiedTable = '`' . str_replace('`', '``', $table) . '`';
        $sql = $qualifiedTable;

        $indexName = $this->firstExistingIndex($table, $preferredIndexes);
        if ($indexName !== null) {
            $sql .= ' FORCE INDEX (`' . str_replace('`', '``', $indexName) . '`)';
        }

        if ($alias !== null && trim($alias) !== '') {
            $sql .= ' as ' . $alias;
        }

        return $sql;
    }

    public function firstExistingIndex(string $table, array $preferredIndexes): ?string
    {
        foreach ($preferredIndexes as $indexName) {
            $normalized = trim((string) $indexName);
            if ($normalized !== '' && $this->indexExists($table, $normalized)) {
                return $normalized;
            }
        }

        return null;
    }

    public function indexExists(string $table, string $indexName): bool
    {
        $cacheKey = strtolower(trim($table)) . '|' . strtolower(trim($indexName));

        if (array_key_exists($cacheKey, $this->indexExistsCache)) {
            return $this->indexExistsCache[$cacheKey];
        }

        $driver = strtolower((string) DB::connection()->getDriverName());
        if (!in_array($driver, ['mysql', 'mariadb'], true)) {
            return $this->indexExistsCache[$cacheKey] = false;
        }

        $rows = DB::select(
            'SHOW INDEX FROM `' . str_replace('`', '``', $table) . '` WHERE Key_name = ?',
            [$indexName]
        );

        return $this->indexExistsCache[$cacheKey] = !empty($rows);
    }
}
