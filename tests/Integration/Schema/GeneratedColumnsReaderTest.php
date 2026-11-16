<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Tests\Integration\Schema;

use Juf\DoctrineImportBundle\Schema\Dbal\GeneratedColumnsReader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(GeneratedColumnsReader::class)]
final class GeneratedColumnsReaderTest extends DatabaseTestCase
{
    #[Test]
    public function it_reports_no_generated_column_on_a_platform_it_has_no_query_for(): void
    {
        $connection = $this->connect('sqlite');
        $connection->executeStatement('CREATE TABLE product (id INTEGER NOT NULL PRIMARY KEY, price INT NOT NULL, price_doubled INT AS (price * 2) STORED)');

        self::assertSame([], (new GeneratedColumnsReader())->read($connection, 'oracle'));
    }

    #[Test]
    public function it_lists_the_generated_columns_by_table_in_declared_order(): void
    {
        $connection = $this->connect('sqlite');
        $connection->executeStatement('CREATE TABLE product (id INTEGER NOT NULL PRIMARY KEY, price INT NOT NULL, price_tripled INT AS (price * 3), price_doubled INT AS (price * 2) STORED)');
        $connection->executeStatement('CREATE TABLE line (id INTEGER NOT NULL PRIMARY KEY, quantity INT NOT NULL, double_quantity INT AS (quantity * 2))');

        self::assertSame(
            ['line' => ['double_quantity'], 'product' => ['price_tripled', 'price_doubled']],
            (new GeneratedColumnsReader())->read($connection, 'sqlite'),
        );
    }
}
