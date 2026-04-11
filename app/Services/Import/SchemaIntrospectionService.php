<?php

namespace App\Services\Import;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SchemaIntrospectionService
{
    private array $tableExists = [];
    private array $columnListings = [];
    private array $columnExists = [];
    private array $columnMetadata = [];

    public function hasTable(string $tableName): bool
    {
        if (!array_key_exists($tableName, $this->tableExists)) {
            $this->tableExists[$tableName] = Schema::hasTable($tableName);
        }

        return $this->tableExists[$tableName];
    }

    public function getColumnListing(string $tableName): array
    {
        if (!array_key_exists($tableName, $this->columnListings)) {
            $this->columnListings[$tableName] = $this->hasTable($tableName)
                ? Schema::getColumnListing($tableName)
                : [];
        }

        return $this->columnListings[$tableName];
    }

    public function hasColumn(string $tableName, string $columnName): bool
    {
        $key = strtolower($tableName . '|' . $columnName);

        if (!array_key_exists($key, $this->columnExists)) {
            $this->columnExists[$key] = Schema::hasColumn($tableName, $columnName);
        }

        return $this->columnExists[$key];
    }

    public function getColumnMetadata(string $tableName): array
    {
        if (array_key_exists($tableName, $this->columnMetadata)) {
            return $this->columnMetadata[$tableName];
        }

        if (!$this->hasTable($tableName)) {
            return $this->columnMetadata[$tableName] = [];
        }

        $safeTableName = str_replace('`', '``', $tableName);
        $rows = DB::select("SHOW FULL COLUMNS FROM `{$safeTableName}`");
        $metadata = [];

        foreach ($rows as $row) {
            $field = (string) ($row->Field ?? $row->field ?? '');
            if ($field === '') {
                continue;
            }

            $parsed = $this->parseMysqlColumnTypeMetadata((string) ($row->Type ?? $row->type ?? ''));
            $parsed['name'] = $field;
            $metadata[strtolower($field)] = $parsed;
        }

        return $this->columnMetadata[$tableName] = $metadata;
    }

    public function parseMysqlColumnTypeMetadata(string $type): array
    {
        $normalizedType = strtolower(trim($type));
        $baseType = preg_replace('/\(.*/', '', $normalizedType) ?: $normalizedType;
        $maxLength = null;

        if (preg_match('/^(?:var)?char\((\d+)\)$/', $normalizedType, $matches)) {
            $maxLength = (int) $matches[1];
        }

        return [
            'type' => $normalizedType,
            'base_type' => $baseType,
            'max_length' => $maxLength,
            'is_textual' => in_array($baseType, ['char', 'varchar', 'tinytext', 'text', 'mediumtext', 'longtext'], true),
        ];
    }
}
