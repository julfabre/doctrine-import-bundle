<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Definition;

final readonly class EntityDefinition
{
    /**
     * @param string                    $className short class name, without namespace
     * @param list<FieldDefinition>     $fields    in column order
     * @param list<ManyToOneDefinition> $manyToOne
     * @param list<OneToManyDefinition> $oneToMany
     * @param list<IndexDefinition>     $indexes
     */
    public function __construct(
        public string $className,
        public string $tableName,
        public string $repositoryClassName,
        public array $fields,
        public array $manyToOne,
        public array $oneToMany,
        public array $indexes,
        public ?string $comment,
    ) {
    }

    /**
     * @param list<FieldDefinition>     $fields
     * @param list<ManyToOneDefinition> $manyToOne
     * @param list<OneToManyDefinition> $oneToMany
     */
    public function withAssociations(array $fields, array $manyToOne, array $oneToMany): self
    {
        return new self(
            $this->className,
            $this->tableName,
            $this->repositoryClassName,
            $fields,
            $manyToOne,
            $oneToMany,
            $this->indexes,
            $this->comment,
        );
    }
}
