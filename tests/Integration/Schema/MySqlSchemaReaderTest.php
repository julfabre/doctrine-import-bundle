<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Tests\Integration\Schema;

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
use PHPUnit\Framework\Attributes\DataProvider;
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
}
