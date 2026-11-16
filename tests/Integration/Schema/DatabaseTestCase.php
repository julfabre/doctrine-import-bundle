<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Tests\Integration\Schema;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Juf\DoctrineImportBundle\Schema\TableMetadata;
use PHPUnit\Framework\TestCase;

/**
 * Connects to the databases described by the DATABASE_URL_* environment variables
 * (set by the local DDEV environment and by the CI integration job) and resets them.
 */
abstract class DatabaseTestCase extends TestCase
{
    private const DRIVER_SCHEME_ALIASES = [
        'pgsql' => 'pdo_pgsql',
        'mysql' => 'pdo_mysql',
        'sqlite' => 'pdo_sqlite',
    ];

    private ?Connection $connection = null;

    protected function tearDown(): void
    {
        $this->connection?->close();
        $this->connection = null;
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function allPlatforms(): iterable
    {
        yield 'sqlite' => ['sqlite'];
        yield 'postgresql' => ['postgresql'];
        yield 'mysql' => ['mysql'];
        yield 'mariadb' => ['mariadb'];
    }

    /**
     * Returns a connection to an empty database, or skips the test when the platform is not configured.
     */
    protected function connect(string $platform): Connection
    {
        $url = $this->databaseUrl($platform);
        $connection = DriverManager::getConnection((new DsnParser(self::DRIVER_SCHEME_ALIASES))->parse($url));
        $this->connection = $connection;
        $this->resetDatabase($connection, $platform);

        return $connection;
    }

    /**
     * @return array<string, bool> generated flag by column name
     */
    protected static function generatedFlags(TableMetadata $table): array
    {
        $flags = [];
        foreach ($table->columns as $column) {
            $flags[$column->name] = $column->generated;
        }

        return $flags;
    }

    private function databaseUrl(string $platform): string
    {
        if ('sqlite' === $platform) {
            return 'sqlite:///:memory:';
        }

        $variable = 'DATABASE_URL_'.strtoupper($platform);
        $url = getenv($variable);
        if (!\is_string($url) || '' === $url) {
            self::markTestSkipped(\sprintf('Set "%s" to run this test on %s.', $variable, $platform));
        }

        return $url;
    }

    private function resetDatabase(Connection $connection, string $platform): void
    {
        if ('postgresql' === $platform) {
            $connection->executeStatement('DROP SCHEMA IF EXISTS other_schema CASCADE');
            $connection->executeStatement('DROP SCHEMA public CASCADE');
            $connection->executeStatement('CREATE SCHEMA public');

            return;
        }

        if ('sqlite' === $platform) {
            return;
        }

        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        $objects = $connection->fetchAllKeyValue(
            'SELECT table_name, table_type FROM information_schema.tables WHERE table_schema = DATABASE()',
        );
        foreach ($objects as $name => $type) {
            $keyword = 'VIEW' === $type ? 'VIEW' : 'TABLE';
            $connection->executeStatement(\sprintf('DROP %s `%s`', $keyword, $name));
        }

        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
    }
}
