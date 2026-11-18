<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Analysis;

use Juf\DoctrineImportBundle\Analysis\Naming\NamingStrategyInterface;
use Juf\DoctrineImportBundle\Definition\EntityDefinition;
use Juf\DoctrineImportBundle\Definition\GenerationModel;
use Juf\DoctrineImportBundle\Definition\JoinColumnDefinition;
use Juf\DoctrineImportBundle\Definition\JoinTableDefinition;
use Juf\DoctrineImportBundle\Definition\ManyToManyDefinition;
use Juf\DoctrineImportBundle\Diagnostic\DiagnosticCollector;
use Juf\DoctrineImportBundle\Diagnostic\Severity;
use Juf\DoctrineImportBundle\Schema\ForeignKeyMetadata;
use Juf\DoctrineImportBundle\Schema\TableMetadata;

/**
 * Turns each pure join table into a ManyToMany association: the owning side is the table referenced by the first
 * primary key column, the other side is mapped by it. A table referencing itself only gets the owning side.
 */
final readonly class ManyToManyAnalyzer
{
    public function __construct(
        private NamingStrategyInterface $naming,
    ) {
    }

    /**
     * @param list<JoinTable> $joinTables
     */
    public function analyze(GenerationModel $model, array $joinTables): GenerationModel
    {
        $diagnostics = new DiagnosticCollector($model->diagnostics);
        $entities = [];
        foreach ($model->entities as $entity) {
            $entities[$entity->tableName] = $entity;
        }

        foreach ($joinTables as $joinTable) {
            $entities = [...$entities, ...$this->relate($joinTable, $entities, $diagnostics)];
        }

        return new GenerationModel(
            array_map(static fn (EntityDefinition $entity): EntityDefinition => $entities[$entity->tableName] ?? $entity, $model->entities),
            $diagnostics->all(),
        );
    }

    /**
     * @param array<string, EntityDefinition> $entities by table name
     *
     * @return array<string, EntityDefinition> the entities that receive a collection, by table name
     */
    private function relate(JoinTable $join, array $entities, DiagnosticCollector $diagnostics): array
    {
        $joinTable = $join->table;
        $owningKey = $join->owningKey;
        $inverseKey = $join->inverseKey;
        $owning = $entities[$owningKey->foreignTable] ?? null;
        $inverse = $entities[$inverseKey->foreignTable] ?? null;
        if (null === $owning || null === $inverse) {
            $diagnostics->report(Severity::Warning, 'relation.join_table_not_mapped', \sprintf('Table "%s" joins a table that is not generated: it is skipped.', $joinTable->name), $joinTable->name);

            return [];
        }

        foreach ($joinTable->indexes as $index) {
            $diagnostics->report(Severity::Warning, 'relation.join_table_index_renamed', \sprintf('Table "%s" has the index "%s", which a ManyToMany mapping cannot name: doctrine:schema:update would rename it.', $joinTable->name, $index->name), $joinTable->name);
        }

        $selfReferencing = $owning->tableName === $inverse->tableName;
        $owningName = $this->collectionName($owning, $this->naming->associationNameForColumn($inverseKey->localColumns[0]), $joinTable, $diagnostics);
        $inverseName = $selfReferencing ? null : $this->collectionName($inverse, $this->naming->associationNameForColumn($owningKey->localColumns[0]), $joinTable, $diagnostics);

        $owningSide = $owning->withManyToMany([...$owning->manyToMany, new ManyToManyDefinition(
            $owningName,
            $inverse->className,
            new JoinTableDefinition($joinTable->name, $this->joinColumn($owningKey), $this->joinColumn($inverseKey)),
            $inverseName,
            null,
        )]);

        if (null === $inverseName) {
            $diagnostics->report(Severity::Info, 'relation.many_to_many_without_inverse_side', \sprintf('Table "%s" is mapped as the ManyToMany association %s::$%s, without inverse side: add it by hand, with a name of your choice, if needed.', $joinTable->name, $owning->className, $owningName), $joinTable->name);

            return [$owning->tableName => $owningSide];
        }

        $inverseSide = $inverse->withManyToMany([...$inverse->manyToMany, new ManyToManyDefinition($inverseName, $owning->className, null, null, $owningName)]);
        $diagnostics->report(Severity::Info, 'relation.many_to_many', \sprintf('Table "%s" is mapped as the ManyToMany association %s::$%s.', $joinTable->name, $owning->className, $owningName), $joinTable->name);

        return [$owning->tableName => $owningSide, $inverse->tableName => $inverseSide];
    }

    private function collectionName(EntityDefinition $entity, string $associationName, TableMetadata $joinTable, DiagnosticCollector $diagnostics): string
    {
        $name = $this->naming->collectionNameForEntity($associationName);
        $taken = [
            ...array_column($entity->fields, 'propertyName'),
            ...array_column($entity->manyToOne, 'propertyName'),
            ...array_column($entity->oneToOne, 'propertyName'),
            ...array_column($entity->oneToMany, 'propertyName'),
            ...array_column($entity->manyToMany, 'propertyName'),
        ];
        if (!\in_array($name, $taken, true)) {
            return $name;
        }

        $fallback = $this->naming->collectionNameForEntity($this->naming->propertyNameForColumn($joinTable->name));
        $diagnostics->report(Severity::Warning, 'naming.property_collision', \sprintf('Property "%s" already exists in "%s": the ManyToMany association of table "%s" is named "%s".', $name, $entity->className, $joinTable->name, $fallback), $joinTable->name);

        return $fallback;
    }

    private function joinColumn(ForeignKeyMetadata $foreignKey): JoinColumnDefinition
    {
        return new JoinColumnDefinition($foreignKey->localColumns[0], $foreignKey->foreignColumns[0], 'NO ACTION' === $foreignKey->onDelete ? null : $foreignKey->onDelete);
    }
}
