<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Definition;

/**
 * One side of a many-to-many association: the owning side holds the join table, the inverse side is mapped by it.
 */
final readonly class ManyToManyDefinition
{
    /**
     * @param JoinTableDefinition|null $joinTable  set on the owning side only
     * @param string|null              $inversedBy collection of the inverse side, on the owning side
     * @param string|null              $mappedBy   collection of the owning side, on the inverse side
     */
    public function __construct(
        public string $propertyName,
        public string $targetEntity,
        public ?JoinTableDefinition $joinTable,
        public ?string $inversedBy,
        public ?string $mappedBy,
    ) {
    }
}
