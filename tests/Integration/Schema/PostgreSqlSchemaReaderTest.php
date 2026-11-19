<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Tests\Integration\Schema;

use Juf\DoctrineImportBundle\Diagnostic\Diagnostic;
use Juf\DoctrineImportBundle\Diagnostic\Severity;
use Juf\DoctrineImportBundle\Schema\ColumnMetadata;
use Juf\DoctrineImportBundle\Schema\ColumnType;
use Juf\DoctrineImportBundle\Schema\Dbal\DbalSchemaReader;
use Juf\DoctrineImportBundle\Schema\Dbal\GeneratedColumnsReader;
use Juf\DoctrineImportBundle\Schema\Dbal\PartitionReader;
use Juf\DoctrineImportBundle\Schema\Dbal\PlatformNameResolver;
use Juf\DoctrineImportBundle\Schema\Dbal\RowidAliasReader;
use Juf\DoctrineImportBundle\Schema\Dbal\SchemaReadingDiagnostics;
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
#[CoversClass(GeneratedColumnsReader::class)]
#[CoversClass(PartitionReader::class)]
#[CoversClass(RowidAliasReader::class)]
#[CoversClass(SchemaReadingDiagnostics::class)]
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
    public function it_reads_the_nextval_default_of_a_serial_column(): void
    {
        $connection = $this->connect('postgresql');
        $connection->executeStatement('CREATE TABLE product (id SERIAL PRIMARY KEY)');

        $id = (new DbalSchemaReader($connection))->read(new TableFilter())->tables[0]->columns[0] ?? self::fail('Column "product.id" was not read.');

        self::assertFalse($id->autoincrement);
        self::assertSame("nextval('product_id_seq'::regclass)", $id->default);
    }

    #[Test]
    public function it_reads_a_stored_generated_column(): void
    {
        $connection = $this->connect('postgresql');
        $connection->executeStatement('CREATE TABLE product (id INT PRIMARY KEY, price INT NOT NULL, price_doubled INT GENERATED ALWAYS AS (price * 2) STORED)');

        $table = (new DbalSchemaReader($connection))->read(new TableFilter())->tables[0] ?? self::fail('Table "product" was not read.');

        self::assertSame(['id' => false, 'price' => false, 'price_doubled' => true], self::generatedFlags($table));
    }

    #[Test]
    public function it_does_not_mark_an_identity_column_as_generated(): void
    {
        $connection = $this->connect('postgresql');
        $connection->executeStatement('CREATE TABLE product (id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY, name TEXT NOT NULL)');

        $table = (new DbalSchemaReader($connection))->read(new TableFilter())->tables[0] ?? self::fail('Table "product" was not read.');

        self::assertSame(['id' => false, 'name' => false], self::generatedFlags($table));
    }

    #[Test]
    public function it_skips_the_partitions_of_a_partitioned_table_with_an_info(): void
    {
        $connection = $this->connect('postgresql');
        $connection->executeStatement('CREATE TABLE measure (id INT NOT NULL, taken_on DATE NOT NULL, PRIMARY KEY (id, taken_on)) PARTITION BY RANGE (taken_on)');
        $connection->executeStatement("CREATE TABLE measure_2026 PARTITION OF measure FOR VALUES FROM ('2026-01-01') TO ('2027-01-01')");

        $schema = (new DbalSchemaReader($connection))->read(new TableFilter());

        self::assertSame(['measure'], array_map(static fn (TableMetadata $table): string => $table->name, $schema->tables));
        self::assertSame([['info', 'table.partition', 'measure_2026']], array_map(static fn (Diagnostic $diagnostic): array => [$diagnostic->severity->value, $diagnostic->code, $diagnostic->table], $schema->diagnostics));
        self::assertSame('Table "measure_2026" is a partition: its rows are mapped through the partitioned table.', $schema->diagnostics[0]->message ?? null);
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
