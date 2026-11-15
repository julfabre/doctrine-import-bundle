<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Schema;

final readonly class ColumnMetadata
{
    /**
     * @param string|null $default raw default value as returned by the database
     */
    public function __construct(
        public string $name,
        public ColumnType $type,
        public bool $nullable,
        public bool $autoincrement,
        public ?string $default,
        public ?string $comment,
    ) {
    }
}
