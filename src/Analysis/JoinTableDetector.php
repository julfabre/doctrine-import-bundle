<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Analysis;

use Juf\DoctrineImportBundle\Schema\ForeignKeyMetadata;
use Juf\DoctrineImportBundle\Schema\IndexMetadata;
use Juf\DoctrineImportBundle\Schema\SchemaMetadata;
use Juf\DoctrineImportBundle\Schema\TableMetadata;

/**
 * Finds the pure join tables of a schema: two columns forming the primary key, each one a foreign key to the
 * single-column primary key of another table, and no other unique index. They become ManyToMany associations.
 */
final readonly class JoinTableDetector
{
    /**
     * @return list<JoinTable>
     */
    public function detect(SchemaMetadata $schema): array
    {
        $tables = [];
        foreach ($schema->tables as $table) {
            $tables[$table->name] = $table;
        }

        $joinTables = [];
        foreach ($schema->tables as $table) {
            $joinTable = $this->joinTable($table, $tables);
            if ($joinTable instanceof JoinTable) {
                $joinTables[] = $joinTable;
            }
        }

        return $joinTables;
    }

    /**
     * @param list<JoinTable> $joinTables
     */
    public function withoutJoinTables(SchemaMetadata $schema, array $joinTables): SchemaMetadata
    {
        $names = array_map(static fn (JoinTable $joinTable): string => $joinTable->table->name, $joinTables);
        $tables = [];
        foreach ($schema->tables as $table) {
            if (!\in_array($table->name, $names, true)) {
                $tables[] = $table;
            }
        }

        return new SchemaMetadata($schema->platform, $tables, $schema->diagnostics);
    }

    /**
     * @param array<string, TableMetadata> $tables by name
     */
    private function joinTable(TableMetadata $table, array $tables): ?JoinTable
    {
        $keysByColumn = $this->singleColumnKeysToPrimaryKeys($table, $tables);

        // Both primary key columns are foreign keys, and the table has no other column, key or unique index.
        $owningKey = $keysByColumn[$table->primaryKey[0] ?? ''] ?? null;
        $inverseKey = $keysByColumn[$table->primaryKey[1] ?? ''] ?? null;
        if (null === $owningKey || null === $inverseKey || 2 !== \count($table->columns) || 2 !== \count($table->foreignKeys) || $this->hasUniqueIndex($table)) {
            return null;
        }

        return new JoinTable($table, $owningKey, $inverseKey);
    }

    /**
     * @param array<string, TableMetadata> $tables
     *
     * @return array<string, ForeignKeyMetadata> by local column
     */
    private function singleColumnKeysToPrimaryKeys(TableMetadata $table, array $tables): array
    {
        $keys = [];
        foreach ($table->foreignKeys as $foreignKey) {
            if (1 === \count($foreignKey->localColumns) && $this->referencesPrimaryKey($foreignKey, $tables)) {
                $keys[$foreignKey->localColumns[0]] = $foreignKey;
            }
        }

        return $keys;
    }

    private function hasUniqueIndex(TableMetadata $table): bool
    {
        return [] !== array_filter($table->indexes, static fn (IndexMetadata $index): bool => $index->unique);
    }

    /**
     * @param array<string, TableMetadata> $tables
     */
    private function referencesPrimaryKey(ForeignKeyMetadata $foreignKey, array $tables): bool
    {
        $target = $tables[$foreignKey->foreignTable] ?? null;

        return $target instanceof TableMetadata && $foreignKey->foreignColumns === $target->primaryKey && 1 === \count($target->primaryKey);
    }
}
