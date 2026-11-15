<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Schema\Dbal;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexedColumn;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\Table;
use Juf\DoctrineImportBundle\Schema\ColumnMetadata;
use Juf\DoctrineImportBundle\Schema\ColumnType;
use Juf\DoctrineImportBundle\Schema\ForeignKeyMetadata;
use Juf\DoctrineImportBundle\Schema\IndexMetadata;
use Juf\DoctrineImportBundle\Schema\TableMetadata;

/**
 * Copies a DBAL table into the bundle model, so that no DBAL object leaves the reader.
 */
final class TableMetadataFactory
{
    public function create(Table $table): TableMetadata
    {
        $comment = $table->getComment();
        $primaryKey = $this->names($table->getPrimaryKeyConstraint()?->getColumnNames() ?? []);

        return new TableMetadata(
            $table->getObjectName()->getUnqualifiedName()->getValue(),
            array_values(array_map($this->column(...), $table->getColumns())),
            $primaryKey,
            $this->indexes($table, $primaryKey),
            array_values(array_map($this->foreignKey(...), $table->getForeignKeys())),
            null === $comment || '' === $comment ? null : $comment,
        );
    }

    private function column(Column $column): ColumnMetadata
    {
        $default = $column->getDefault();
        $comment = $column->getComment();

        return new ColumnMetadata(
            name: $column->getObjectName()->getIdentifier()->getValue(),
            type: new ColumnType(
                name: $column->getTypeName(),
                length: $column->getLength(),
                precision: $column->getPrecision(),
                scale: $column->getScale(),
                unsigned: $column->getUnsigned(),
                fixed: $column->getFixed(),
            ),
            nullable: !$column->getNotnull(),
            autoincrement: $column->getAutoincrement(),
            default: \is_scalar($default) ? (string) $default : null,
            comment: '' === $comment ? null : $comment,
        );
    }

    /**
     * @param list<string> $primaryKey
     *
     * @return list<IndexMetadata>
     */
    private function indexes(Table $table, array $primaryKey): array
    {
        $indexes = array_map(
            static fn (Index $index): IndexMetadata => new IndexMetadata(
                $index->getObjectName()->getIdentifier()->getValue(),
                array_map(
                    static fn (IndexedColumn $column): string => $column->getColumnName()->getIdentifier()->getValue(),
                    $index->getIndexedColumns(),
                ),
                IndexType::UNIQUE === $index->getType(),
            ),
            array_values($table->getIndexes()),
        );
        // DBAL also lists the index backing the primary key: it is already described by the primary key.
        $indexes = array_values(array_filter(
            $indexes,
            static fn (IndexMetadata $index): bool => !$index->unique || $index->columns !== $primaryKey,
        ));
        usort($indexes, static fn (IndexMetadata $left, IndexMetadata $right): int => $left->name <=> $right->name);

        return $indexes;
    }

    private function foreignKey(ForeignKeyConstraint $foreignKey): ForeignKeyMetadata
    {
        return new ForeignKeyMetadata(
            $foreignKey->getObjectName()?->getIdentifier()->getValue() ?? '',
            $this->names($foreignKey->getReferencingColumnNames()),
            $foreignKey->getReferencedTableName()->getUnqualifiedName()->getValue(),
            $this->names($foreignKey->getReferencedColumnNames()),
            $foreignKey->getOnDeleteAction()->value,
            $foreignKey->getOnUpdateAction()->value,
        );
    }

    /**
     * @param array<UnqualifiedName> $names
     *
     * @return list<string>
     */
    private function names(array $names): array
    {
        return array_values(array_map(
            static fn (UnqualifiedName $name): string => $name->getIdentifier()->getValue(),
            $names,
        ));
    }
}
