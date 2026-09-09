<?php

namespace App\Support;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Cache;
use Throwable;

class SchemaMetadataCache
{
    private const CACHE_VERSION = 'v1';

    /** @var array<string, array<string, array{name: string, columns: array<int, string>, is_table: bool}>> */
    private array $inventories = [];

    /** @var array<string, string> */
    private array $persistentKeys = [];

    public function hasTable(Connection $connection, string $table): bool
    {
        [$database, $tableName] = $this->resolveTableReference($connection, $table);
        $metadata = $this->inventory($connection, $database)[$this->normalize($tableName)] ?? null;

        return is_array($metadata) && (bool) ($metadata['is_table'] ?? false);
    }

    public function hasColumn(Connection $connection, string $table, string $column): bool
    {
        return in_array(
            $this->normalize($column),
            array_map($this->normalize(...), $this->getColumnListing($connection, $table)),
            true
        );
    }

    /** @param array<int, string> $columns */
    public function hasColumns(Connection $connection, string $table, array $columns): bool
    {
        $available = array_fill_keys(
            array_map($this->normalize(...), $this->getColumnListing($connection, $table)),
            true
        );

        foreach ($columns as $column) {
            if (! isset($available[$this->normalize($column)])) {
                return false;
            }
        }

        return true;
    }

    /** @return array<int, string> */
    public function getColumnListing(Connection $connection, string $table): array
    {
        [$database, $tableName] = $this->resolveTableReference($connection, $table);
        $metadata = $this->inventory($connection, $database)[$this->normalize($tableName)] ?? null;

        return is_array($metadata) ? array_values($metadata['columns'] ?? []) : [];
    }

    public function invalidate(Connection $connection): void
    {
        $connectionPrefix = $this->connectionPrefix($connection);

        foreach (array_keys($this->inventories) as $key) {
            if (str_starts_with($key, $connectionPrefix)) {
                unset($this->inventories[$key]);
            }
        }

        foreach ($this->persistentKeys as $inventoryKey => $cacheKey) {
            if (! str_starts_with($inventoryKey, $connectionPrefix)) {
                continue;
            }

            try {
                Cache::forget($cacheKey);
            } catch (Throwable) {
                // Schema mutations must not fail only because the cache store is unavailable.
            }

            unset($this->persistentKeys[$inventoryKey]);
        }

        $defaultInventoryKey = $this->inventoryKey($connection, $connection->getDatabaseName());
        try {
            Cache::forget($this->persistentCacheKey($defaultInventoryKey));
        } catch (Throwable) {
            // See note above: the database schema remains the source of truth.
        }
    }

    /**
     * @return array<string, array{name: string, columns: array<int, string>, is_table: bool}>
     */
    private function inventory(Connection $connection, string $database): array
    {
        if ($database === '') {
            return [];
        }

        $inventoryKey = $this->inventoryKey($connection, $database);
        if (array_key_exists($inventoryKey, $this->inventories)) {
            return $this->inventories[$inventoryKey];
        }

        $cacheKey = $this->persistentCacheKey($inventoryKey);
        $this->persistentKeys[$inventoryKey] = $cacheKey;

        try {
            $inventory = Cache::remember(
                $cacheKey,
                now()->addHours(24),
                fn (): array => $this->loadInventory($connection, $database)
            );
        } catch (Throwable) {
            $inventory = $this->loadInventory($connection, $database);
        }

        if (! is_array($inventory)) {
            $inventory = [];
        }

        return $this->inventories[$inventoryKey] = $inventory;
    }

    /**
     * Load every table and column in one metadata query. This replaces hundreds of
     * isolated hasTable/hasColumn round trips during a dashboard request.
     *
     * @return array<string, array{name: string, columns: array<int, string>, is_table: bool}>
     */
    private function loadInventory(Connection $connection, string $database): array
    {
        $rows = $connection->select(
            'SELECT c.TABLE_NAME AS table_name, c.COLUMN_NAME AS column_name, t.TABLE_TYPE AS table_type '
            .'FROM information_schema.COLUMNS c '
            .'INNER JOIN information_schema.TABLES t '
            .'ON t.TABLE_SCHEMA = c.TABLE_SCHEMA AND t.TABLE_NAME = c.TABLE_NAME '
            .'WHERE c.TABLE_SCHEMA = ? '
            .'ORDER BY c.TABLE_NAME, c.ORDINAL_POSITION',
            [$database]
        );

        $inventory = [];
        foreach ($rows as $row) {
            $values = (array) $row;
            $tableName = trim((string) ($values['table_name'] ?? $values['TABLE_NAME'] ?? ''));
            $columnName = trim((string) ($values['column_name'] ?? $values['COLUMN_NAME'] ?? ''));
            $tableType = strtoupper(trim((string) ($values['table_type'] ?? $values['TABLE_TYPE'] ?? '')));
            if ($tableName === '' || $columnName === '') {
                continue;
            }

            $tableKey = $this->normalize($tableName);
            $inventory[$tableKey] ??= [
                'name' => $tableName,
                'columns' => [],
                'is_table' => in_array($tableType, ['BASE TABLE', 'SYSTEM VERSIONED'], true),
            ];
            $inventory[$tableKey]['columns'][] = $columnName;
        }

        return $inventory;
    }

    /** @return array{0: string, 1: string} */
    private function resolveTableReference(Connection $connection, string $reference): array
    {
        $segments = explode('.', str_replace('`', '', trim($reference)), 2);
        $database = count($segments) === 2
            ? trim($segments[0])
            : trim((string) $connection->getDatabaseName());
        $table = trim($segments[count($segments) - 1]);

        return [$database, $connection->getTablePrefix().$table];
    }

    private function inventoryKey(Connection $connection, string $database): string
    {
        return $this->connectionPrefix($connection).$this->normalize($database);
    }

    private function connectionPrefix(Connection $connection): string
    {
        $name = method_exists($connection, 'getName') ? (string) $connection->getName() : 'default';

        return strtolower($connection->getDriverName().'|'.$name).'|';
    }

    private function persistentCacheKey(string $inventoryKey): string
    {
        return 'schema_metadata_inventory:'.self::CACHE_VERSION.':'.sha1($inventoryKey);
    }

    private function normalize(string $value): string
    {
        return strtolower(trim($value));
    }
}
