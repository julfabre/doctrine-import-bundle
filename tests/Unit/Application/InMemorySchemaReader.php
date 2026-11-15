<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Tests\Unit\Application;

use Juf\DoctrineImportBundle\Schema\SchemaMetadata;
use Juf\DoctrineImportBundle\Schema\SchemaReaderInterface;
use Juf\DoctrineImportBundle\Schema\TableFilter;
use Juf\DoctrineImportBundle\Schema\TableMetadata;

/**
 * Serves a schema built in memory, applying the filter like a real reader.
 */
final readonly class InMemorySchemaReader implements SchemaReaderInterface
{
    public function __construct(
        private SchemaMetadata $schema,
    ) {
    }

    public function read(TableFilter $filter): SchemaMetadata
    {
        return new SchemaMetadata(
            $this->schema->platform,
            array_values(array_filter($this->schema->tables, static fn (TableMetadata $table): bool => $filter->accepts($table->name))),
            $this->schema->diagnostics,
        );
    }
}
