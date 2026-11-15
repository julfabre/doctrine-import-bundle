<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Definition;

final readonly class ManyToOneDefinition
{
    /**
     * @param string|null $onDelete referential action kept on the join column, e.g. "CASCADE"
     */
    public function __construct(
        public string $propertyName,
        public string $targetEntity,
        public string $joinColumn,
        public string $referencedColumn,
        public bool $nullable,
        public ?string $onDelete,
        public ?string $inversedBy,
    ) {
    }
}
