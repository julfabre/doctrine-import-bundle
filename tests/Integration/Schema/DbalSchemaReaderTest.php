<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Tests\Integration\Schema;

use Doctrine\DBAL\Types\Types;
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
use Juf\DoctrineImportBundle\Tests\Integration\Schema\Fixture\BlogSchemaFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(DbalSchemaReader::class)]
#[CoversClass(SchemaMetadata::class)]
#[CoversClass(TableMetadata::class)]
#[CoversClass(ColumnMetadata::class)]
#[CoversClass(ColumnType::class)]
#[CoversClass(PlatformNameResolver::class)]
#[CoversClass(TableMetadataFactory::class)]
#[CoversClass(IndexMetadata::class)]
#[CoversClass(ForeignKeyMetadata::class)]
#[UsesClass(TableFilter::class)]
final class DbalSchemaReaderTest extends DatabaseTestCase
{
    #[Test]
    #[DataProvider('allPlatforms')]
    public function it_reports_the_database_platform(string $platform): void
    {
        $schema = $this->readFixture($platform);

        self::assertSame($platform, $schema->platform);
    }

    #[Test]
    #[DataProvider('allPlatforms')]
    public function it_reads_every_table_sorted_by_name(string $platform): void
    {
        $schema = $this->readFixture($platform);

        self::assertSame(['author', 'order_line', 'post'], $this->tableNames($schema));
    }

    #[Test]
    #[DataProvider('allPlatforms')]
    public function it_does_not_read_views(string $platform): void
    {
        $schema = $this->readFixture($platform);

        self::assertNotContains('v_published_post', $this->tableNames($schema));
    }

    #[Test]
    #[DataProvider('allPlatforms')]
    public function it_reads_column_names_types_and_nullability(string $platform): void
    {
        $post = $this->table($this->readFixture($platform), 'post');

        self::assertSame(
            ['id', 'author_id', 'title', 'status', 'price', 'is_featured', 'published_at'],
            array_map(static fn (ColumnMetadata $column): string => $column->name, $post->columns),
        );
        self::assertSame([Types::INTEGER, false], $this->typeAndNullability($post, 'id'));
        self::assertSame([Types::STRING, false], $this->typeAndNullability($post, 'title'));
        self::assertSame([Types::DECIMAL, true], $this->typeAndNullability($post, 'price'));
        self::assertSame([Types::BOOLEAN, false], $this->typeAndNullability($post, 'is_featured'));
        self::assertSame([Types::DATETIME_IMMUTABLE, true], $this->typeAndNullability($post, 'published_at'));
        self::assertSame([Types::TEXT, true], $this->typeAndNullability($this->table($this->readFixture($platform), 'author'), 'bio'));
    }

    #[Test]
    #[DataProvider('allPlatforms')]
    public function it_reads_string_length_and_decimal_precision_and_scale(string $platform): void
    {
        $post = $this->table($this->readFixture($platform), 'post');

        self::assertSame(255, $this->column($post, 'title')->type->length);
        self::assertSame(10, $this->column($post, 'price')->type->precision);
        self::assertSame(2, $this->column($post, 'price')->type->scale);
    }

    #[Test]
    #[DataProvider('allPlatforms')]
    public function it_reads_default_values(string $platform): void
    {
        $schema = $this->readFixture($platform);

        self::assertSame('draft', $this->column($this->table($schema, 'post'), 'status')->default);
        self::assertSame('1', $this->column($this->table($schema, 'order_line'), 'quantity')->default);
        self::assertNull($this->column($this->table($schema, 'post'), 'title')->default);
    }

    #[Test]
    #[DataProvider('allPlatforms')]
    public function it_flags_auto_increment_columns(string $platform): void
    {
        $post = $this->table($this->readFixture($platform), 'post');

        self::assertTrue($this->column($post, 'id')->autoincrement);
        self::assertFalse($this->column($post, 'author_id')->autoincrement);
    }

    #[Test]
    #[DataProvider('allPlatforms')]
    public function it_reads_a_single_column_primary_key(string $platform): void
    {
        $post = $this->table($this->readFixture($platform), 'post');

        self::assertSame(['id'], $post->primaryKey);
    }

    #[Test]
    #[DataProvider('allPlatforms')]
    public function it_reads_a_composite_primary_key_in_declared_order(string $platform): void
    {
        $orderLine = $this->table($this->readFixture($platform), 'order_line');

        self::assertSame(['order_id', 'product_id'], $orderLine->primaryKey);
    }

    #[Test]
    #[DataProvider('allPlatforms')]
    public function it_reads_unique_and_non_unique_indexes_without_the_primary_key(string $platform): void
    {
        $schema = $this->readFixture($platform);

        self::assertEquals(
            [new IndexMetadata('uniq_author_email', ['email'], unique: true)],
            $this->table($schema, 'author')->indexes,
        );
        self::assertEquals(
            [new IndexMetadata('idx_post_author', ['author_id'], unique: false)],
            $this->table($schema, 'post')->indexes,
        );
    }

    #[Test]
    #[DataProvider('allPlatforms')]
    public function it_reads_foreign_keys_with_their_target_and_on_delete_action(string $platform): void
    {
        $foreignKeys = $this->table($this->readFixture($platform), 'post')->foreignKeys;

        self::assertCount(1, $foreignKeys);
        self::assertSame(['author_id'], $foreignKeys[0]->localColumns);
        self::assertSame('author', $foreignKeys[0]->foreignTable);
        self::assertSame(['id'], $foreignKeys[0]->foreignColumns);
        self::assertSame('CASCADE', $foreignKeys[0]->onDelete);
    }

    #[Test]
    #[DataProvider('allPlatforms')]
    public function it_reads_column_and_table_comments(string $platform): void
    {
        if ('sqlite' === $platform) {
            self::markTestSkipped('SQLite does not store comments.');
        }

        $author = $this->table($this->readFixture($platform), 'author');

        self::assertSame('Blog authors', $author->comment);
        self::assertSame('Display name', $this->column($author, 'name')->comment);
        self::assertNull($this->column($author, 'email')->comment);
    }

    #[Test]
    #[DataProvider('allPlatforms')]
    public function it_applies_the_table_filter_before_reading_table_details(string $platform): void
    {
        $schema = $this->readFixture($platform, new TableFilter(include: ['post', 'author'], exclude: ['author']));

        self::assertSame(['post'], $this->tableNames($schema));
    }

    private function readFixture(string $platform, TableFilter $filter = new TableFilter()): SchemaMetadata
    {
        $connection = $this->connect($platform);
        BlogSchemaFixture::create($connection);

        return (new DbalSchemaReader($connection))->read($filter);
    }

    /**
     * @return list<string>
     */
    private function tableNames(SchemaMetadata $schema): array
    {
        return array_map(static fn (TableMetadata $table): string => $table->name, $schema->tables);
    }

    private function table(SchemaMetadata $schema, string $name): TableMetadata
    {
        foreach ($schema->tables as $table) {
            if ($name === $table->name) {
                return $table;
            }
        }

        self::fail(\sprintf('Table "%s" was not read.', $name));
    }

    private function column(TableMetadata $table, string $name): ColumnMetadata
    {
        foreach ($table->columns as $column) {
            if ($name === $column->name) {
                return $column;
            }
        }

        self::fail(\sprintf('Column "%s.%s" was not read.', $table->name, $name));
    }

    /**
     * @return array{string, bool}
     */
    private function typeAndNullability(TableMetadata $table, string $column): array
    {
        $metadata = $this->column($table, $column);

        return [$metadata->type->name, $metadata->nullable];
    }
}
