<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Schema\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;

/**
 * Lists the columns computed by the database (GENERATED ALWAYS AS), which Doctrine DBAL does not report.
 */
final class GeneratedColumnsReader
{
    // "DEFAULT_GENERATED" marks a default expression on MySQL 8, not a generated column.
    private const MYSQL = "SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND (EXTRA LIKE '%VIRTUAL GENERATED%' OR EXTRA LIKE '%STORED GENERATED%') ORDER BY TABLE_NAME, ORDINAL_POSITION";

    private const QUERIES = [
        'mysql' => self::MYSQL,
        'mariadb' => self::MYSQL,
        // Identity columns are "GENERATED ... AS IDENTITY" but report is_generated = 'NEVER'.
        'postgresql' => "SELECT table_name, column_name FROM information_schema.columns WHERE table_schema = current_schema() AND is_generated = 'ALWAYS' ORDER BY table_name, ordinal_position",
        // table_xinfo flags virtual (2) and stored (3) generated columns, which table_info hides.
        'sqlite' => "SELECT m.name, x.name FROM sqlite_master m JOIN pragma_table_xinfo(m.name) x WHERE m.type = 'table' AND x.hidden IN (2, 3) ORDER BY m.name, x.cid",
    ];

    /**
     * @param string $platform as returned by {@see PlatformNameResolver}
     *
     * @return array<string, list<string>> generated column names by table name
     *
     * @throws DbalException
     */
    public function read(Connection $connection, string $platform): array
    {
        $query = self::QUERIES[$platform] ?? null;
        if (null === $query) {
            return [];
        }

        $columns = [];
        foreach ($connection->fetchAllNumeric($query) as $row) {
            $columns[$this->text($row[0] ?? null)][] = $this->text($row[1] ?? null);
        }

        return $columns;
    }

    private function text(mixed $value): string
    {
        return \is_scalar($value) ? (string) $value : '';
    }
}
