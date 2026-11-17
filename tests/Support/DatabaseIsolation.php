<?php

namespace Tests\Support;

use Illuminate\Foundation\Application;
use PDO;
use RuntimeException;

class DatabaseIsolation
{
    public static function assert(Application $app): void
    {
        if (!$app->environment('testing') || $app['config']->get('database.default') !== 'sqlite'
            || $app['config']->get('database.connections.sqlite.database') !== ':memory:') {
            throw new RuntimeException('Tests refused: only SQLite :memory: is allowed.');
        }

        $connection = $app['db']->connection();
        if ($connection->getDriverName() !== 'sqlite' || $connection->getDatabaseName() !== ':memory:'
            || $connection->getPdo()->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
            throw new RuntimeException('Tests refused: actual PDO connection is not SQLite :memory:.');
        }
        foreach ($connection->select('PRAGMA database_list') as $database) {
            if ($database->file !== '') {
                throw new RuntimeException('Tests refused: SQLite is attached to a database file.');
            }
        }
    }
}
