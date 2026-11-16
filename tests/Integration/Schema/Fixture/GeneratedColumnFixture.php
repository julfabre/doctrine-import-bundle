<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Tests\Integration\Schema\Fixture;

use Doctrine\DBAL\Connection;

/**
 * Adds an integer generated column: DBAL cannot create one, so the SQL is written for each platform.
 */
final class GeneratedColumnFixture
{
    public static function add(Connection $connection, string $platform, string $table, string $column, string $expression): void
    {
        // SQLite only accepts virtual generated columns in ALTER TABLE; PostgreSQL only stored ones.
        $storage = 'sqlite' === $platform ? 'VIRTUAL' : 'STORED';

        $connection->executeStatement(\sprintf('ALTER TABLE %s ADD COLUMN %s INT GENERATED ALWAYS AS (%s) %s', $table, $column, $expression, $storage));
    }
}
