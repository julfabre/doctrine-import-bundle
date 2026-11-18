<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Tests\Integration\Schema;

use Juf\DoctrineImportBundle\Diagnostic\Diagnostic;
use Juf\DoctrineImportBundle\Schema\ColumnMetadata;
use Juf\DoctrineImportBundle\Schema\ColumnType;
use Juf\DoctrineImportBundle\Schema\Dbal\DbalSchemaReader;
use Juf\DoctrineImportBundle\Schema\Dbal\GeneratedColumnsReader;
use Juf\DoctrineImportBundle\Schema\Dbal\PlatformNameResolver;
use Juf\DoctrineImportBundle\Schema\Dbal\TableMetadataFactory;
use Juf\DoctrineImportBundle\Schema\IndexMetadata;
use Juf\DoctrineImportBundle\Schema\SchemaMetadata;
use Juf\DoctrineImportBundle\Schema\TableFilter;
use Juf\DoctrineImportBundle\Schema\TableMetadata;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(DbalSchemaReader::class)]
#[UsesClass(SchemaMetadata::class)]
#[UsesClass(TableMetadata::class)]
#[UsesClass(ColumnMetadata::class)]
#[UsesClass(IndexMetadata::class)]
#[UsesClass(Diagnostic::class)]
#[UsesClass(ColumnType::class)]
#[UsesClass(PlatformNameResolver::class)]
#[UsesClass(TableMetadataFactory::class)]
#[UsesClass(TableFilter::class)]
#[CoversClass(GeneratedColumnsReader::class)]
final class MySqlSchemaReaderTest extends DatabaseTestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function mySqlPlatforms(): iterable
    {
        yield 'mysql' => ['mysql'];
        yield 'mariadb' => ['mariadb'];
    }

    #[Test]
    #[DataProvider('mySqlPlatforms')]
    public function it_reads_the_unsigned_flag_of_integer_columns(string $platform): void
    {
        $connection = $this->connect($platform);
        $connection->executeStatement('CREATE TABLE counter (id INT UNSIGNED NOT NULL PRIMARY KEY, delta INT NOT NULL)');

        $tables = (new DbalSchemaReader($connection))->read(new TableFilter())->tables;

        self::assertSame(['counter'], array_map(static fn (TableMetadata $table): string => $table->name, $tables));
        self::assertSame(
            ['id' => true, 'delta' => false],
            array_column(array_map(
                static fn (ColumnMetadata $column): array => [$column->name, $column->type->unsigned],
                $tables[0]->columns ?? [],
            ), 1, 0),
        );
    }

    #[Test]
    #[DataProvider('mySqlPlatforms')]
    public function it_skips_a_table_with_an_unknown_column_type_and_reports_it(string $platform): void
    {
        $connection = $this->connect($platform);
        $connection->executeStatement('CREATE TABLE address (id INT NOT NULL PRIMARY KEY, location GEOMETRY NOT NULL)');
        $connection->executeStatement('CREATE TABLE city (id INT NOT NULL PRIMARY KEY)');

        $schema = (new DbalSchemaReader($connection))->read(new TableFilter());

        self::assertSame(['city'], array_map(static fn (TableMetadata $table): string => $table->name, $schema->tables));
        self::assertSame(
            [['table.unreadable', 'address']],
            array_map(static fn (Diagnostic $diagnostic): array => [$diagnostic->code, $diagnostic->table], $schema->diagnostics),
        );
    }

    #[Test]
    #[DataProvider('mySqlPlatforms')]
    public function it_reads_virtual_and_stored_generated_columns(string $platform): void
    {
        $connection = $this->connect($platform);
        $connection->executeStatement('CREATE TABLE product (id INT NOT NULL PRIMARY KEY, price INT NOT NULL, price_tripled INT GENERATED ALWAYS AS (price * 3) VIRTUAL, price_doubled INT GENERATED ALWAYS AS (price * 2) STORED)');

        $table = (new DbalSchemaReader($connection))->read(new TableFilter())->tables[0] ?? self::fail('Table "product" was not read.');

        self::assertSame(['id' => false, 'price' => false, 'price_tripled' => true, 'price_doubled' => true], self::generatedFlags($table));
    }

    #[Test]
    #[DataProvider('mySqlPlatforms')]
    public function it_does_not_mark_a_column_with_a_default_and_on_update_expression_as_generated(string $platform): void
    {
        $connection = $this->connect($platform);
        $connection->executeStatement('CREATE TABLE film (id INT NOT NULL PRIMARY KEY, last_update TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)');

        $table = (new DbalSchemaReader($connection))->read(new TableFilter())->tables[0] ?? self::fail('Table "film" was not read.');

        self::assertSame(['id' => false, 'last_update' => false], self::generatedFlags($table));
    }

    #[Test]
    #[DataProvider('mySqlPlatforms')]
    public function it_reads_the_values_of_an_enum_column(string $platform): void
    {
        $connection = $this->connect($platform);
        $connection->executeStatement("CREATE TABLE film (id INT NOT NULL PRIMARY KEY, rating ENUM('G', 'PG-13') DEFAULT 'G')");

        $rating = (new DbalSchemaReader($connection))->read(new TableFilter())->tables[0]->columns[1] ?? self::fail('Column "film.rating" was not read.');

        self::assertSame(['enum', ['G', 'PG-13'], 'G'], [$rating->type->name, $rating->type->values, $rating->default]);
    }

    #[Test]
    #[DataProvider('mySqlPlatforms')]
    public function it_reads_the_flags_of_fulltext_indexes(string $platform): void
    {
        $connection = $this->connect($platform);
        $connection->executeStatement('CREATE TABLE film_text (id INT NOT NULL PRIMARY KEY, title VARCHAR(255) NOT NULL, FULLTEXT KEY idx_title (title), KEY idx_id_title (id, title))');

        $indexes = (new DbalSchemaReader($connection))->read(new TableFilter())->tables[0]->indexes ?? [];

        self::assertEquals([
            new IndexMetadata('idx_id_title', ['id', 'title'], unique: false),
            new IndexMetadata('idx_title', ['title'], unique: false, flags: ['fulltext']),
        ], $indexes);
    }
}
