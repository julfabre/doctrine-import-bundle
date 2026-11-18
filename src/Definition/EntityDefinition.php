<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Definition;

final readonly class EntityDefinition
{
    /**
     * @param string                     $className  short class name, without namespace
     * @param list<FieldDefinition>      $fields     in column order
     * @param list<ManyToOneDefinition>  $manyToOne
     * @param list<OneToManyDefinition>  $oneToMany
     * @param list<IndexDefinition>      $indexes
     * @param list<OneToOneDefinition>   $oneToOne
     * @param list<ManyToManyDefinition> $manyToMany
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
        public array $oneToOne = [],
        public array $manyToMany = [],
    ) {
    }

    /**
     * @param list<FieldDefinition>     $fields
     * @param list<ManyToOneDefinition> $manyToOne
     * @param list<OneToManyDefinition> $oneToMany
     * @param list<OneToOneDefinition>  $oneToOne
     */
    public function withAssociations(array $fields, array $manyToOne, array $oneToMany, array $oneToOne): self
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
            $oneToOne,
            $this->manyToMany,
        );
    }

    /**
     * @param list<ManyToManyDefinition> $manyToMany
     */
    public function withManyToMany(array $manyToMany): self
    {
        return new self(
            $this->className,
            $this->tableName,
            $this->repositoryClassName,
            $this->fields,
            $this->manyToOne,
            $this->oneToMany,
            $this->indexes,
            $this->comment,
            $this->oneToOne,
            $manyToMany,
        );
    }
}
