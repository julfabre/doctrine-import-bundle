<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Schema\Dbal;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;

final class PlatformNameResolver
{
    /**
     * @throws SchemaReadingException
     */
    public function resolve(AbstractPlatform $platform): string
    {
        return match (true) {
            $platform instanceof PostgreSQLPlatform => 'postgresql',
            // MariaDB must be tested before MySQL: both share AbstractMySQLPlatform.
            $platform instanceof MariaDBPlatform => 'mariadb',
            $platform instanceof AbstractMySQLPlatform => 'mysql',
            $platform instanceof SQLitePlatform => 'sqlite',
            default => throw SchemaReadingException::unsupportedPlatform($platform::class),
        };
    }
}
