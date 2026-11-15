<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Schema\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use Juf\DoctrineImportBundle\Diagnostic\Diagnostic;
use Juf\DoctrineImportBundle\Diagnostic\Severity;
use Juf\DoctrineImportBundle\Schema\SchemaMetadata;
use Juf\DoctrineImportBundle\Schema\SchemaReaderInterface;
use Juf\DoctrineImportBundle\Schema\TableFilter;
use Juf\DoctrineImportBundle\Schema\TableMetadata;

final readonly class DbalSchemaReader implements SchemaReaderInterface
{
    public function __construct(
        private Connection $connection,
        private TableMetadataFactory $tableFactory = new TableMetadataFactory(),
        private PlatformNameResolver $platformNameResolver = new PlatformNameResolver(),
    ) {
    }

    /**
     * @throws SchemaReadingException
     * @throws DbalException
     */
    public function read(TableFilter $filter): SchemaMetadata
    {
        $schemaManager = $this->connection->createSchemaManager();
        $tables = [];
        $diagnostics = [];

        foreach ($schemaManager->introspectTableNames() as $tableName) {
            $qualifier = $tableName->getQualifier()?->getValue();
            $name = $tableName->getUnqualifiedName()->getValue();

            if (null !== $qualifier) {
                $diagnostics[] = $this->outsideSearchPath($qualifier.'.'.$name);

                continue;
            }

            if ('' === $name || !$filter->accepts($name)) {
                continue;
            }

            try {
                $tables[] = $this->tableFactory->create($schemaManager->introspectTableByQuotedName($name));
            } catch (DbalException $dbalException) {
                $diagnostics[] = $this->unreadable($name, $dbalException);
            }
        }

        usort($tables, static fn (TableMetadata $left, TableMetadata $right): int => $left->name <=> $right->name);

        return new SchemaMetadata(
            $this->platformNameResolver->resolve($this->connection->getDatabasePlatform()),
            $tables,
            $diagnostics,
        );
    }

    /**
     * DBAL reads a table as a whole: a single column of unknown type makes the whole table unreadable.
     */
    private function unreadable(string $table, DbalException $exception): Diagnostic
    {
        return new Diagnostic(
            Severity::Error,
            'table.unreadable',
            \sprintf(
                'Table "%s" cannot be read: %s. Map the type with the "doctrine.dbal.mapping_types" configuration, or exclude the table. The table is skipped.',
                $table,
                rtrim($exception->getMessage(), '.'),
            ),
            $table,
        );
    }

    private function outsideSearchPath(string $qualifiedName): Diagnostic
    {
        return new Diagnostic(
            Severity::Warning,
            'table.outside_search_path',
            \sprintf('Table "%s" is outside the default schema and was not read.', $qualifiedName),
            $qualifiedName,
        );
    }
}
