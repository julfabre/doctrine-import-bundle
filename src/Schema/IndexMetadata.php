<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Schema;

final readonly class IndexMetadata
{
    /**
     * @param list<string> $columns
     * @param list<string> $flags   platform-specific index kinds, e.g. "fulltext" or "spatial"
     */
    public function __construct(
        public string $name,
        public array $columns,
        public bool $unique,
        public array $flags = [],
    ) {
    }
}
