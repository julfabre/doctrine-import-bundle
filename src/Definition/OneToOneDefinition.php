<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Definition;

/**
 * Owning side of a one-to-one association: a foreign key covered by a single-column unique index.
 */
final readonly class OneToOneDefinition
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
    ) {
    }
}
