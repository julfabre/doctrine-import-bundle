<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Tests\Integration\Schema;

use Juf\DoctrineImportBundle\Diagnostic\Diagnostic;
use Juf\DoctrineImportBundle\Diagnostic\Severity;
use Juf\DoctrineImportBundle\Schema\ColumnMetadata;
use Juf\DoctrineImportBundle\Schema\ColumnType;
use Juf\DoctrineImportBundle\Schema\Dbal\DbalSchemaReader;
use Juf\DoctrineImportBundle\Schema\Dbal\PlatformNameResolver;
use Juf\DoctrineImportBundle\Schema\Dbal\TableMetadataFactory;
use Juf\DoctrineImportBundle\Schema\IndexMetadata;
use Juf\DoctrineImportBundle\Schema\SchemaMetadata;
use Juf\DoctrineImportBundle\Schema\TableFilter;
use Juf\DoctrineImportBundle\Schema\TableMetadata;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(DbalSchemaReader::class)]
#[UsesClass(SchemaMetadata::class)]
#[UsesClass(TableMetadata::class)]
#[UsesClass(ColumnMetadata::class)]
#[UsesClass(IndexMetadata::class)]
#[UsesClass(ColumnType::class)]
#[UsesClass(PlatformNameResolver::class)]
#[UsesClass(TableMetadataFactory::class)]
#[UsesClass(TableFilter::class)]
#[CoversClass(Diagnostic::class)]
final class PostgreSqlSchemaReaderTest extends DatabaseTestCase
{
    #[Test]
    public function it_ignores_tables_outside_the_search_path_with_a_warning(): void
    {
        $connection = $this->connect('postgresql');
        $connection->executeStatement('CREATE TABLE article (id INT PRIMARY KEY)');
        $connection->executeStatement('CREATE SCHEMA other_schema');
        $connection->executeStatement('CREATE TABLE other_schema.archive (id INT PRIMARY KEY)');

        $schema = (new DbalSchemaReader($connection))->read(new TableFilter());

        self::assertSame(['article'], array_map(static fn (TableMetadata $table): string => $table->name, $schema->tables));
        self::assertCount(1, $schema->diagnostics);
        self::assertSame(Severity::Warning, $schema->diagnostics[0]->severity);
        self::assertSame('table.outside_search_path', $schema->diagnostics[0]->code);
        self::assertSame('other_schema.archive', $schema->diagnostics[0]->table);
    }

    #[Test]
    public function it_skips_a_table_with_an_unknown_column_type_and_reports_it(): void
    {
        $connection = $this->connect('postgresql');
        $connection->executeStatement("CREATE TYPE mood AS ENUM ('sad', 'happy')");
        $connection->executeStatement('CREATE TABLE person (id INT PRIMARY KEY, current_mood mood)');
        $connection->executeStatement('CREATE TABLE team (id INT PRIMARY KEY)');

        $schema = (new DbalSchemaReader($connection))->read(new TableFilter());

        self::assertSame(['team'], array_map(static fn (TableMetadata $table): string => $table->name, $schema->tables));
        self::assertCount(1, $schema->diagnostics);
        $diagnostic = $schema->diagnostics[0];
        self::assertSame([Severity::Error, 'table.unreadable', 'person'], [$diagnostic->severity, $diagnostic->code, $diagnostic->table]);
        self::assertMatchesRegularExpression('/^Table "person" cannot be read: Unknown database type "mood" requested, .+[^.]\\. Map the type with the "doctrine\\.dbal\\.mapping_types" configuration, or exclude the table\\. The table is skipped\\.$/', $diagnostic->message);
    }
}
