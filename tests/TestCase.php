<?php

namespace Tests;

use Illuminate\Database\Connection;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use SplObjectStorage;

abstract class TestCase extends BaseTestCase
{
    private static ?SplObjectStorage $guardedDatabaseConnections = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->installTestDatabaseGuard();
    }

    private function installTestDatabaseGuard(): void
    {
        if (!app()->runningUnitTests()) {
            return;
        }

        self::$guardedDatabaseConnections ??= new SplObjectStorage();

        Event::listen(ConnectionEstablished::class, function (ConnectionEstablished $event): void {
            self::guardDatabaseConnection($event->connection);
        });

        self::guardDatabaseConnection(DB::connection());
    }

    private static function guardDatabaseConnection(Connection $connection): void
    {
        self::$guardedDatabaseConnections ??= new SplObjectStorage();

        if (self::$guardedDatabaseConnections->contains($connection)) {
            return;
        }

        self::$guardedDatabaseConnections->attach($connection);

        $connection->beforeExecuting(function (string $query, array $bindings, Connection $connection): void {
            if (!self::isDestructiveSchemaQuery($query) || self::isSafeTestConnection($connection)) {
                return;
            }

            throw new RuntimeException(sprintf(
                'Blocked destructive schema query during php artisan test on non-SQLite connection [%s]: %s',
                $connection->getName() ?: $connection->getDriverName(),
                self::summarizeSql($query)
            ));
        });
    }

    private static function isSafeTestConnection(Connection $connection): bool
    {
        if ($connection->getDriverName() === 'sqlite') {
            return true;
        }

        return filter_var(env('ALLOW_DESTRUCTIVE_TEST_DATABASE_QUERIES', false), FILTER_VALIDATE_BOOLEAN);
    }

    private static function isDestructiveSchemaQuery(string $query): bool
    {
        return preg_match('/^\s*(drop\s+(database|table)|truncate\s+table|alter\s+table\b.*\bdrop\b)/i', $query) === 1;
    }

    private static function summarizeSql(string $query): string
    {
        $normalized = preg_replace('/\s+/', ' ', trim($query)) ?? trim($query);

        return substr($normalized, 0, 180);
    }
}
