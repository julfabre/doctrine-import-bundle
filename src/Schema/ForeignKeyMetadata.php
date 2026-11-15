<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Schema;

final readonly class ForeignKeyMetadata
{
    /**
     * @param list<string> $localColumns
     * @param list<string> $foreignColumns
     * @param string       $onDelete       referential action, e.g. "CASCADE" or "NO ACTION"
     * @param string       $onUpdate       referential action, e.g. "CASCADE" or "NO ACTION"
     */
    public function __construct(
        public string $name,
        public array $localColumns,
        public string $foreignTable,
        public array $foreignColumns,
        public string $onDelete,
        public string $onUpdate,
    ) {
    }
}
