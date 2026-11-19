<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Schema\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;

/**
 * Lists the PostgreSQL partitions of the default schema: Doctrine DBAL lists them as tables but cannot read them,
 * and their rows are mapped through the partitioned table.
 */
final class PartitionReader
{
    private const POSTGRESQL = 'SELECT c.relname FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace WHERE c.relispartition AND n.nspname = current_schema() ORDER BY c.relname';

    /**
     * @param string $platform as returned by {@see PlatformNameResolver}
     *
     * @return list<string> partition names
     *
     * @throws DbalException
     */
    public function read(Connection $connection, string $platform): array
    {
        if ('postgresql' !== $platform) {
            return [];
        }

        return array_map(static fn (mixed $name): string => \is_scalar($name) ? (string) $name : '', $connection->fetchFirstColumn(self::POSTGRESQL));
    }
}
