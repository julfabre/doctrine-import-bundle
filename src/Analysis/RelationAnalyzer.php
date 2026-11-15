<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Analysis;

use Juf\DoctrineImportBundle\Analysis\Naming\DefaultNamingStrategy;
use Juf\DoctrineImportBundle\Analysis\Naming\NamingStrategyInterface;
use Juf\DoctrineImportBundle\Definition\EntityDefinition;
use Juf\DoctrineImportBundle\Definition\FieldDefinition;
use Juf\DoctrineImportBundle\Definition\GenerationModel;
use Juf\DoctrineImportBundle\Definition\ManyToOneDefinition;
use Juf\DoctrineImportBundle\Definition\OneToManyDefinition;
use Juf\DoctrineImportBundle\Diagnostic\DiagnosticCollector;
use Juf\DoctrineImportBundle\Diagnostic\Severity;
use Juf\DoctrineImportBundle\Schema\ColumnMetadata;
use Juf\DoctrineImportBundle\Schema\ForeignKeyMetadata;
use Juf\DoctrineImportBundle\Schema\SchemaMetadata;
use Juf\DoctrineImportBundle\Schema\TableMetadata;

/**
 * Turns single-column foreign keys into a ManyToOne association and its inverse OneToMany collection.
 * Anything Doctrine cannot map safely stays a scalar field and is reported.
 */
final readonly class RelationAnalyzer
{
    public function __construct(
        private NamingStrategyInterface $naming = new DefaultNamingStrategy(),
    ) {
    }

    public function analyze(SchemaMetadata $schema, GenerationModel $model): GenerationModel
    {
        $diagnostics = new DiagnosticCollector($model->diagnostics);
        $relations = new RelationSet();
        $entities = [];
        foreach ($model->entities as $entity) {
            $entities[$entity->tableName] = $entity;
        }

        foreach ($schema->tables as $table) {
            $entity = $entities[$table->name] ?? null;
            if (null === $entity) {
                continue;
            }

            foreach ($table->foreignKeys as $foreignKey) {
                $this->relate($table, $entity, $foreignKey, $entities, $relations, $diagnostics);
            }
        }

        return new GenerationModel(array_values(array_map($relations->applyTo(...), $entities)), $diagnostics->all());
    }

    /**
     * @param array<string, EntityDefinition> $entities by table name
     */
    private function relate(TableMetadata $table, EntityDefinition $entity, ForeignKeyMetadata $foreignKey, array $entities, RelationSet $relations, DiagnosticCollector $diagnostics): void
    {
        $target = $entities[$foreignKey->foreignTable] ?? null;
        $problem = $this->problem($table, $foreignKey, $target);
        [$column] = $foreignKey->localColumns;
        if (null !== $problem || null === $target) {
            $diagnostics->report(Severity::Warning, $problem[0] ?? 'relation.missing_target', \sprintf('Column "%s.%s" %s: it is kept as a scalar field.', $table->name, $column, $problem[1] ?? ''), $table->name, $column);

            return;
        }

        if ($this->isUnique($table, $column)) {
            $diagnostics->report(Severity::Info, 'relation.one_to_one_as_many_to_one', \sprintf('Column "%s.%s" is a unique foreign key: it is mapped as ManyToOne, OneToOne is not generated yet.', $table->name, $column), $table->name, $column);
        }

        $propertyName = $this->propertyName($table, $entity, $column, $relations, $diagnostics);
        $inversedBy = $this->inverseName($table, $entity, $propertyName, $foreignKey->foreignTable);
        [$referencedColumn] = $foreignKey->foreignColumns;

        $relations->add(
            $table->name,
            new ManyToOneDefinition($propertyName, $target->className, $column, $referencedColumn, $this->isNullable($table, $column), 'NO ACTION' === $foreignKey->onDelete ? null : $foreignKey->onDelete, $inversedBy),
            $foreignKey->foreignTable,
            new OneToManyDefinition($inversedBy, $entity->className, $propertyName),
        );
    }

    /**
     * @return array{string, string}|null diagnostic code and reason, null when the foreign key can be mapped
     */
    private function problem(TableMetadata $table, ForeignKeyMetadata $foreignKey, ?EntityDefinition $target): ?array
    {
        if (1 !== \count($foreignKey->localColumns)) {
            return ['relation.composite_foreign_key', 'is part of a composite foreign key, which is not mapped as an association yet'];
        }

        if (!$target instanceof EntityDefinition) {
            return ['relation.missing_target', \sprintf('references table "%s", which is not generated', $foreignKey->foreignTable)];
        }

        if ($foreignKey->foreignColumns !== $this->idColumns($target)) {
            return ['relation.non_primary_key_target', \sprintf('references a column of "%s" that is not its primary key, which Doctrine does not support', $foreignKey->foreignTable)];
        }

        if (\in_array($foreignKey->localColumns[0], $table->primaryKey, true)) {
            return ['relation.foreign_key_in_primary_key', 'is both a primary key and a foreign key: derived identities are not supported yet'];
        }

        return null;
    }

    private function propertyName(TableMetadata $table, EntityDefinition $entity, string $column, RelationSet $relations, DiagnosticCollector $diagnostics): string
    {
        $propertyName = $this->naming->associationNameForColumn($column);
        $otherFields = array_filter($entity->fields, static fn (FieldDefinition $field): bool => $column !== $field->columnName);
        $taken = [
            ...array_map(static fn (FieldDefinition $field): string => $field->propertyName, $otherFields),
            ...$relations->associationNames($table->name),
        ];

        if (!\in_array($propertyName, $taken, true)) {
            return $propertyName;
        }

        $fallback = $this->naming->propertyNameForColumn($column);
        $diagnostics->report(Severity::Warning, 'naming.property_collision', \sprintf('Property "%s" already exists in "%s": the association of column "%s" is named "%s".', $propertyName, $entity->className, $column, $fallback), $table->name, $column);

        return $fallback;
    }

    private function inverseName(TableMetadata $table, EntityDefinition $entity, string $propertyName, string $targetTable): string
    {
        // A table referencing itself is a tree: its inverse side reads better as "children" than as a plural.
        $collection = $targetTable === $table->name ? 'children' : $this->naming->collectionNameForEntity($entity->className);
        $foreignKeysToTarget = array_filter($table->foreignKeys, static fn (ForeignKeyMetadata $foreignKey): bool => $targetTable === $foreignKey->foreignTable);

        // Several foreign keys to the same table need distinct inverse collections: createdByPosts, updatedByPosts.
        return \count($foreignKeysToTarget) > 1 ? $propertyName.ucfirst($collection) : $collection;
    }

    /**
     * @return list<string>
     */
    private function idColumns(EntityDefinition $entity): array
    {
        return array_values(array_map(
            static fn (FieldDefinition $field): string => $field->columnName,
            array_filter($entity->fields, static fn (FieldDefinition $field): bool => $field->isId()),
        ));
    }

    private function isUnique(TableMetadata $table, string $column): bool
    {
        foreach ($table->indexes as $index) {
            if ($index->unique && [$column] === $index->columns) {
                return true;
            }
        }

        return false;
    }

    private function isNullable(TableMetadata $table, string $columnName): bool
    {
        $nullableColumns = array_map(
            static fn (ColumnMetadata $column): string => $column->name,
            array_filter($table->columns, static fn (ColumnMetadata $column): bool => $column->nullable),
        );

        return \in_array($columnName, $nullableColumns, true);
    }
}
