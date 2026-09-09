<?php

namespace App\Support;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\MySqlBuilder;

class CachedMySqlSchemaBuilder extends MySqlBuilder
{
    public function __construct(
        Connection $connection,
        private readonly SchemaMetadataCache $metadataCache
    ) {
        parent::__construct($connection);
    }

    public function hasTable($table)
    {
        return $this->metadataCache->hasTable($this->connection, (string) $table);
    }

    public function hasColumn($table, $column)
    {
        return $this->metadataCache->hasColumn($this->connection, (string) $table, (string) $column);
    }

    public function hasColumns($table, array $columns)
    {
        return $this->metadataCache->hasColumns($this->connection, (string) $table, $columns);
    }

    public function getColumnListing($table)
    {
        return $this->metadataCache->getColumnListing($this->connection, (string) $table);
    }

    public function table($table, Closure $callback)
    {
        try {
            return parent::table($table, $callback);
        } finally {
            $this->metadataCache->invalidate($this->connection);
        }
    }

    public function create($table, Closure $callback)
    {
        try {
            return parent::create($table, $callback);
        } finally {
            $this->metadataCache->invalidate($this->connection);
        }
    }

    public function drop($table)
    {
        try {
            return parent::drop($table);
        } finally {
            $this->metadataCache->invalidate($this->connection);
        }
    }

    public function dropIfExists($table)
    {
        try {
            return parent::dropIfExists($table);
        } finally {
            $this->metadataCache->invalidate($this->connection);
        }
    }

    public function dropColumns($table, $columns)
    {
        try {
            return parent::dropColumns($table, $columns);
        } finally {
            $this->metadataCache->invalidate($this->connection);
        }
    }

    public function rename($from, $to)
    {
        try {
            return parent::rename($from, $to);
        } finally {
            $this->metadataCache->invalidate($this->connection);
        }
    }

    public function dropAllTables()
    {
        try {
            return parent::dropAllTables();
        } finally {
            $this->metadataCache->invalidate($this->connection);
        }
    }

    public function dropAllViews()
    {
        try {
            return parent::dropAllViews();
        } finally {
            $this->metadataCache->invalidate($this->connection);
        }
    }
}
