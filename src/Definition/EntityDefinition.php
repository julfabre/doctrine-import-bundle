<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Definition;

final readonly class EntityDefinition
{
    /** Always the class name followed by "Repository". */
    public string $repositoryClassName;

    /**
     * @param string                     $className  short class name, without namespace
     * @param list<FieldDefinition>      $fields     in column order
     * @param list<ManyToOneDefinition>  $manyToOne
     * @param list<OneToManyDefinition>  $oneToMany
     * @param list<IndexDefinition>      $indexes
     * @param list<OneToOneDefinition>   $oneToOne
     * @param list<ManyToManyDefinition> $manyToMany
     * @param list<string>               $primaryKey columns of the primary key, in key order
     */
    public function __construct(
        public string $className,
        public string $tableName,
        public array $fields,
        public array $manyToOne,
        public array $oneToMany,
        public array $indexes,
        public ?string $comment,
        public array $oneToOne = [],
        public array $manyToMany = [],
        public array $primaryKey = [],
    ) {
        $this->repositoryClassName = $className.'Repository';
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
            $fields,
            $manyToOne,
            $oneToMany,
            $this->indexes,
            $this->comment,
            $oneToOne,
            $this->manyToMany,
            $this->primaryKey,
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
            $this->fields,
            $this->manyToOne,
            $this->oneToMany,
            $this->indexes,
            $this->comment,
            $this->oneToOne,
            $manyToMany,
            $this->primaryKey,
        );
    }
}
