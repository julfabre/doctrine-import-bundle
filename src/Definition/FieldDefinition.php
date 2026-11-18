<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Definition;

final readonly class FieldDefinition
{
    /**
     * @param string              $doctrineType e.g. "integer" or "datetime_immutable"
     * @param string              $phpType      e.g. "int" or "\DateTimeImmutable"
     * @param IdGeneration|null   $idGeneration null when the field is not part of the identifier
     * @param string|null         $sequenceName set when the identifier is taken from a sequence
     * @param EnumDefinition|null $enum         set when the column is mapped to a PHP enum
     */
    public function __construct(
        public string $propertyName,
        public string $columnName,
        public string $doctrineType,
        public string $phpType,
        public bool $nullable,
        public ?IdGeneration $idGeneration,
        public ColumnOptions $options,
        public bool $generated,
        public ?string $sequenceName = null,
        public ?EnumDefinition $enum = null,
    ) {
    }

    public function isId(): bool
    {
        return $this->idGeneration instanceof IdGeneration;
    }
}
