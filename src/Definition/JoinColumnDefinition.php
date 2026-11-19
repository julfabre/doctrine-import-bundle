<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Definition;

final readonly class JoinColumnDefinition
{
    /**
     * @param string|null          $onDelete referential action kept on the join column, e.g. "CASCADE"
     * @param array<string, mixed> $options  column options of the join column, e.g. ["default" => null]
     */
    public function __construct(
        public string $name,
        public string $referencedColumn,
        public ?string $onDelete,
        public array $options = [],
    ) {
    }
}
