<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Schema;

final readonly class TableMetadata
{
    /**
     * @param list<ColumnMetadata>     $columns     in declaration order
     * @param list<string>             $primaryKey  column names, in declaration order
     * @param list<IndexMetadata>      $indexes     without the primary key index
     * @param list<ForeignKeyMetadata> $foreignKeys
     */
    public function __construct(
        public string $name,
        public array $columns,
        public array $primaryKey,
        public array $indexes,
        public array $foreignKeys,
        public ?string $comment = null,
    ) {
    }
}
