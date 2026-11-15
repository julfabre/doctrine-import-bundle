<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Tests\Unit\Analysis;

use Juf\DoctrineImportBundle\Diagnostic\Diagnostic;
use Juf\DoctrineImportBundle\Schema\ColumnMetadata;
use Juf\DoctrineImportBundle\Schema\ColumnType;
use Juf\DoctrineImportBundle\Schema\ForeignKeyMetadata;
use Juf\DoctrineImportBundle\Schema\IndexMetadata;
use Juf\DoctrineImportBundle\Schema\SchemaMetadata;
use Juf\DoctrineImportBundle\Schema\TableMetadata;

/**
 * Builds in-memory schemas for the analysis tests.
 */
final class SchemaBuilder
{
    /**
     * @param list<TableMetadata> $tables
     * @param list<Diagnostic>    $diagnostics
     */
    public static function schema(array $tables, array $diagnostics = []): SchemaMetadata
    {
        return new SchemaMetadata('sqlite', $tables, $diagnostics);
    }

    /**
     * @param list<ColumnMetadata>     $columns
     * @param list<string>             $primaryKey
     * @param list<ForeignKeyMetadata> $foreignKeys
     * @param list<IndexMetadata>      $indexes
     */
    public static function table(
        string $name,
        array $columns,
        array $primaryKey = ['id'],
        array $foreignKeys = [],
        array $indexes = [],
        ?string $comment = null,
    ): TableMetadata {
        return new TableMetadata($name, $columns, $primaryKey, $indexes, $foreignKeys, $comment);
    }

    public static function id(): ColumnMetadata
    {
        return self::column('id', 'integer', autoincrement: true);
    }

    public static function column(
        string $name,
        string $type = 'integer',
        bool $nullable = false,
        bool $autoincrement = false,
        ?string $default = null,
        ?string $comment = null,
        ?int $length = null,
        ?int $precision = null,
        int $scale = 0,
        bool $unsigned = false,
        bool $fixed = false,
    ): ColumnMetadata {
        return new ColumnMetadata(
            $name,
            new ColumnType($type, $length, $precision, $scale, $unsigned, $fixed),
            $nullable,
            $autoincrement,
            $default,
            $comment,
        );
    }

    /**
     * @param non-empty-list<string> $localColumns
     * @param non-empty-list<string> $foreignColumns
     */
    public static function foreignKey(
        array $localColumns,
        string $foreignTable,
        array $foreignColumns = ['id'],
        string $onDelete = 'NO ACTION',
    ): ForeignKeyMetadata {
        return new ForeignKeyMetadata('fk_'.implode('_', $localColumns), $localColumns, $foreignTable, $foreignColumns, $onDelete, 'NO ACTION');
    }
}
