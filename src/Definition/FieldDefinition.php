<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Definition;

final readonly class FieldDefinition
{
    /**
     * @param string            $doctrineType e.g. "integer" or "datetime_immutable"
     * @param string            $phpType      e.g. "int" or "\DateTimeImmutable"
     * @param IdGeneration|null $idGeneration null when the field is not part of the identifier
     */
    public function __construct(
        public string $propertyName,
        public string $columnName,
        public string $doctrineType,
        public string $phpType,
        public bool $nullable,
        public ?IdGeneration $idGeneration,
        public ColumnOptions $options,
    ) {
    }

    public function isId(): bool
    {
        return $this->idGeneration instanceof IdGeneration;
    }
}
