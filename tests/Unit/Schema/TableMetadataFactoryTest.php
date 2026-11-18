<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Tests\Unit\Schema;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Juf\DoctrineImportBundle\Schema\ColumnMetadata;
use Juf\DoctrineImportBundle\Schema\ColumnType;
use Juf\DoctrineImportBundle\Schema\Dbal\TableMetadataFactory;
use Juf\DoctrineImportBundle\Schema\IndexMetadata;
use Juf\DoctrineImportBundle\Schema\TableMetadata;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * The databases tested return indexes sorted by name: an in-memory table checks the order does not depend on it.
 */
#[CoversClass(TableMetadataFactory::class)]
#[UsesClass(TableMetadata::class)]
#[UsesClass(ColumnMetadata::class)]
#[UsesClass(ColumnType::class)]
#[UsesClass(IndexMetadata::class)]
final class TableMetadataFactoryTest extends TestCase
{
    #[Test]
    public function it_sorts_the_indexes_by_name_whatever_their_order_in_the_table(): void
    {
        $table = Table::editor()
            ->setUnquotedName('article')
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->create(),
                Column::editor()->setUnquotedName('title')->setTypeName(Types::STRING)->setLength(100)->create(),
                Column::editor()->setUnquotedName('code')->setTypeName(Types::STRING)->setLength(100)->create(),
                Column::editor()->setUnquotedName('slug')->setTypeName(Types::STRING)->setLength(100)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
            ->setIndexes(
                Index::editor()->setUnquotedName('idx_zeta')->setUnquotedColumnNames('title')->create(),
                Index::editor()->setUnquotedName('idx_mu')->setUnquotedColumnNames('code')->create(),
                Index::editor()->setUnquotedName('idx_alpha')->setUnquotedColumnNames('slug')->create(),
            )
            ->create();

        $indexes = (new TableMetadataFactory())->create($table)->indexes;

        self::assertSame(['idx_alpha', 'idx_mu', 'idx_zeta'], array_map(static fn (IndexMetadata $index): string => $index->name, $indexes));
    }
}
