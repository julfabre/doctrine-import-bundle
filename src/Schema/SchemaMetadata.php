<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Schema;

use Juf\DoctrineImportBundle\Diagnostic\Diagnostic;

final readonly class SchemaMetadata
{
    /**
     * @param string              $platform    "postgresql", "mysql", "mariadb" or "sqlite"
     * @param list<TableMetadata> $tables      sorted by name
     * @param list<Diagnostic>    $diagnostics
     */
    public function __construct(
        public string $platform,
        public array $tables,
        public array $diagnostics = [],
    ) {
    }
}
