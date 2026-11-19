<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Schema\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use Juf\DoctrineImportBundle\Diagnostic\Diagnostic;
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
        private GeneratedColumnsReader $generatedColumnsReader = new GeneratedColumnsReader(),
        private RowidAliasReader $rowidAliasReader = new RowidAliasReader(),
        private PartitionReader $partitionReader = new PartitionReader(),
    ) {
    }

    /**
     * @throws SchemaReadingException
     * @throws DbalException
     */
    public function read(TableFilter $filter): SchemaMetadata
    {
        $platform = $this->platformNameResolver->resolve($this->connection->getDatabasePlatform());
        $generatedColumns = $this->generatedColumnsReader->read($this->connection, $platform);
        $rowidAliases = $this->rowidAliasReader->read($this->connection, $platform);
        $partitions = $this->partitionReader->read($this->connection, $platform);
        $schemaManager = $this->connection->createSchemaManager();
        $tables = [];
        $diagnostics = [];

        foreach ($schemaManager->introspectTableNames() as $tableName) {
            $qualifier = $tableName->getQualifier()?->getValue();
            $name = $tableName->getUnqualifiedName()->getValue();

            if (null !== $qualifier) {
                $diagnostics[] = SchemaReadingDiagnostics::outsideSearchPath($qualifier.'.'.$name);

                continue;
            }

            if ('' === $name || !$filter->accepts($name)) {
                continue;
            }

            if (\in_array($name, $partitions, true)) {
                $diagnostics[] = SchemaReadingDiagnostics::partition($name);

                continue;
            }

            try {
                // The indexes are read apart: the table adds one for each unindexed foreign key, which the database lacks.
                $table = $this->tableFactory->create($schemaManager->introspectTableByQuotedName($name), $generatedColumns[$name] ?? [], $schemaManager->introspectTableIndexesByQuotedName($name), $rowidAliases[$name] ?? []);
            } catch (DbalException $dbalException) {
                $diagnostics[] = SchemaReadingDiagnostics::unreadableTable($name, $dbalException);

                continue;
            }

            $tables[] = $table;
            $diagnostics = [...$diagnostics, ...$this->unreadGeneratedColumns($table, $generatedColumns[$name] ?? [])];
        }

        usort($tables, static fn (TableMetadata $left, TableMetadata $right): int => $left->name <=> $right->name);

        return new SchemaMetadata($platform, $tables, $diagnostics);
    }

    /**
     * @param list<string> $generatedColumns
     *
     * @return list<Diagnostic>
     */
    private function unreadGeneratedColumns(TableMetadata $table, array $generatedColumns): array
    {
        $unreadColumns = array_diff($generatedColumns, array_column($table->columns, 'name'));

        return array_values(array_map(static fn (string $column): Diagnostic => SchemaReadingDiagnostics::generatedColumnNotRead($table->name, $column), $unreadColumns));
    }
}
