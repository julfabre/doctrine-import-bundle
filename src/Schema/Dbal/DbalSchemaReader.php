<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Schema\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
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

            if ('' !== $name && $filter->accepts($name)) {
                $tables[] = $this->readTable($schemaManager, $name);
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
     * @param AbstractSchemaManager<AbstractPlatform> $schemaManager
     * @param non-empty-string                        $name
     *
     * @throws SchemaReadingException
     */
    private function readTable(AbstractSchemaManager $schemaManager, string $name): TableMetadata
    {
        try {
            return $this->tableFactory->create($schemaManager->introspectTableByQuotedName($name));
        } catch (DbalException $dbalException) {
            throw SchemaReadingException::forTable($name, $dbalException);
        }
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
