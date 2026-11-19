<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Rendering;

use Juf\DoctrineImportBundle\Definition\JoinTableDefinition;
use Juf\DoctrineImportBundle\Definition\ManyToManyDefinition;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Literal;

/**
 * Adds the ManyToMany attribute of a collection, and the join table attributes on its owning side.
 */
final readonly class ManyToManyAttributes
{
    private const ORM = 'Doctrine\\ORM\\Mapping';

    public function add(ClassType $class, ManyToManyDefinition $collection): void
    {
        $property = $class->getProperty($collection->propertyName);
        $property->addAttribute(self::ORM.'\\ManyToMany', array_filter([
            'targetEntity' => new Literal($collection->targetEntity.'::class'),
            'inversedBy' => $collection->inversedBy,
            'mappedBy' => $collection->mappedBy,
        ], static fn (mixed $value): bool => null !== $value));

        $joinTable = $collection->joinTable;
        if (!$joinTable instanceof JoinTableDefinition) {
            return;
        }

        $property->addAttribute(self::ORM.'\\JoinTable', ['name' => $joinTable->name]);
        foreach (['JoinColumn' => $joinTable->joinColumn, 'InverseJoinColumn' => $joinTable->inverseJoinColumn] as $attribute => $column) {
            $property->addAttribute(self::ORM.'\\'.$attribute, array_filter([
                'name' => $column->name,
                'referencedColumnName' => $column->referencedColumn,
                'onDelete' => $column->onDelete,
                'options' => [] === $column->options ? null : $column->options,
            ], static fn (mixed $value): bool => null !== $value));
        }
    }
}
