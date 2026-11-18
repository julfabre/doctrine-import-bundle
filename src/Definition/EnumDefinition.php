<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Definition;

/**
 * A string-backed PHP enum generated for an ENUM column.
 */
final readonly class EnumDefinition
{
    /**
     * @param non-empty-list<EnumCaseDefinition> $cases in the order of the column values
     */
    public function __construct(
        public string $className,
        public array $cases,
    ) {
    }
}
