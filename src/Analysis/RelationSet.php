<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Analysis;

use Juf\DoctrineImportBundle\Definition\EntityDefinition;
use Juf\DoctrineImportBundle\Definition\FieldDefinition;
use Juf\DoctrineImportBundle\Definition\ManyToOneDefinition;
use Juf\DoctrineImportBundle\Definition\OneToManyDefinition;
use Juf\DoctrineImportBundle\Definition\OneToOneDefinition;

/**
 * Associations found during a single {@see RelationAnalyzer} run, by table name.
 */
final class RelationSet
{
    /** @var array<string, list<ManyToOneDefinition>> */
    private array $manyToOne = [];

    /** @var array<string, list<OneToManyDefinition>> */
    private array $oneToMany = [];

    /** @var array<string, list<OneToOneDefinition>> */
    private array $oneToOne = [];

    /** @var array<string, list<string>> foreign key columns replaced by an association */
    private array $replacedColumns = [];

    public function add(string $owningTable, ManyToOneDefinition $owningSide, string $inverseTable, OneToManyDefinition $inverseSide): void
    {
        $this->manyToOne[$owningTable][] = $owningSide;
        $this->oneToMany[$inverseTable][] = $inverseSide;
        $this->replacedColumns[$owningTable][] = $owningSide->joinColumn;
    }

    public function addOneToOne(string $owningTable, OneToOneDefinition $owningSide): void
    {
        $this->oneToOne[$owningTable][] = $owningSide;
        $this->replacedColumns[$owningTable][] = $owningSide->joinColumn;
    }

    /**
     * @return list<string>
     */
    public function associationNames(string $table): array
    {
        return [
            ...array_map(static fn (ManyToOneDefinition $association): string => $association->propertyName, $this->manyToOne[$table] ?? []),
            ...array_map(static fn (OneToOneDefinition $association): string => $association->propertyName, $this->oneToOne[$table] ?? []),
        ];
    }

    public function applyTo(EntityDefinition $entity): EntityDefinition
    {
        $replaced = $this->replacedColumns[$entity->tableName] ?? [];

        return $entity->withAssociations(
            array_values(array_filter($entity->fields, static fn (FieldDefinition $field): bool => !\in_array($field->columnName, $replaced, true))),
            $this->manyToOne[$entity->tableName] ?? [],
            $this->oneToMany[$entity->tableName] ?? [],
            $this->oneToOne[$entity->tableName] ?? [],
        );
    }
}
