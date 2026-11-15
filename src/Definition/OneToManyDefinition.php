<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Definition;

final readonly class OneToManyDefinition
{
    public function __construct(
        public string $propertyName,
        public string $targetEntity,
        public string $mappedBy,
    ) {
    }
}
