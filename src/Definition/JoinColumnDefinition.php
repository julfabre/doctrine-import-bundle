<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Definition;

final readonly class JoinColumnDefinition
{
    /**
     * @param string|null $onDelete referential action kept on the join column, e.g. "CASCADE"
     */
    public function __construct(
        public string $name,
        public string $referencedColumn,
        public ?string $onDelete,
    ) {
    }
}
