<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Schema\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;

/**
 * Lists the SQLite primary keys that alias the rowid, which SQLite numbers itself: Doctrine DBAL only reports them
 * as auto-increment when the AUTOINCREMENT keyword is used.
 */
final class RowidAliasReader
{
    // Only a single-column primary key declared exactly "INTEGER", in a table with a rowid, aliases it.
    private const SQLITE = "SELECT m.name, p.name FROM sqlite_master m JOIN pragma_table_info(m.name) p WHERE m.type = 'table' AND p.pk = 1 AND UPPER(p.type) = 'INTEGER' AND UPPER(m.sql) NOT LIKE '%WITHOUT ROWID%' AND (SELECT COUNT(*) FROM pragma_table_info(m.name) k WHERE k.pk > 0) = 1 ORDER BY m.name";

    /**
     * @param string $platform as returned by {@see PlatformNameResolver}
     *
     * @return array<string, list<string>> rowid alias column names by table name
     *
     * @throws DbalException
     */
    public function read(Connection $connection, string $platform): array
    {
        if ('sqlite' !== $platform) {
            return [];
        }

        $columns = [];
        foreach ($connection->fetchAllNumeric(self::SQLITE) as $row) {
            $columns[$this->text($row[0] ?? null)][] = $this->text($row[1] ?? null);
        }

        return $columns;
    }

    private function text(mixed $value): string
    {
        return \is_string($value) ? $value : '';
    }
}
