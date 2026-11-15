<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Tests\Integration\Schema;

use Juf\DoctrineImportBundle\Schema\ColumnMetadata;
use Juf\DoctrineImportBundle\Schema\ColumnType;
use Juf\DoctrineImportBundle\Schema\Dbal\DbalSchemaReader;
use Juf\DoctrineImportBundle\Schema\Dbal\PlatformNameResolver;
use Juf\DoctrineImportBundle\Schema\Dbal\TableMetadataFactory;
use Juf\DoctrineImportBundle\Schema\ForeignKeyMetadata;
use Juf\DoctrineImportBundle\Schema\IndexMetadata;
use Juf\DoctrineImportBundle\Schema\SchemaMetadata;
use Juf\DoctrineImportBundle\Schema\TableFilter;
use Juf\DoctrineImportBundle\Schema\TableMetadata;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(TableMetadataFactory::class)]
#[UsesClass(DbalSchemaReader::class)]
#[UsesClass(PlatformNameResolver::class)]
#[UsesClass(SchemaMetadata::class)]
#[UsesClass(TableMetadata::class)]
#[UsesClass(ColumnMetadata::class)]
#[UsesClass(ColumnType::class)]
#[UsesClass(IndexMetadata::class)]
#[UsesClass(ForeignKeyMetadata::class)]
#[UsesClass(TableFilter::class)]
final class SqliteSchemaReaderTest extends DatabaseTestCase
{
    #[Test]
    public function it_reads_a_foreign_key_declared_without_name(): void
    {
        $connection = $this->connect('sqlite');
        $connection->executeStatement('CREATE TABLE parent (id INTEGER NOT NULL PRIMARY KEY)');
        $connection->executeStatement('CREATE TABLE child (id INTEGER NOT NULL PRIMARY KEY, parent_id INTEGER REFERENCES parent (id))');

        $schema = (new DbalSchemaReader($connection))->read(new TableFilter(include: ['child']));

        $foreignKeys = $schema->tables[0]->foreignKeys ?? [];
        self::assertCount(1, $foreignKeys);
        self::assertSame(['parent', ['parent_id']], [$foreignKeys[0]->foreignTable, $foreignKeys[0]->localColumns]);
    }
}
