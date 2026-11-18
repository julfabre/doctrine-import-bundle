<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Tests\Integration\Schema\Fixture;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;

/**
 * Portable schema: author, and author_profile whose unique foreign key makes a one-to-one association.
 */
final class OneToOneSchemaFixture
{
    public static function create(Connection $connection): void
    {
        $schemaManager = $connection->createSchemaManager();
        $schemaManager->createTable(Table::editor()
            ->setUnquotedName('author')
            ->setColumns(self::id(), Column::editor()->setUnquotedName('name')->setTypeName(Types::STRING)->setLength(100)->create())
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
            ->create());
        $schemaManager->createTable(Table::editor()
            ->setUnquotedName('author_profile')
            ->setColumns(
                self::id(),
                Column::editor()->setUnquotedName('author_id')->setTypeName(Types::INTEGER)->create(),
                Column::editor()->setUnquotedName('website')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
            ->setIndexes(Index::editor()->setUnquotedName('uniq_author_profile_author')->setType(IndexType::UNIQUE)->setUnquotedColumnNames('author_id')->create())
            ->setForeignKeyConstraints(ForeignKeyConstraint::editor()
                ->setUnquotedName('fk_author_profile_author')
                ->setUnquotedReferencingColumnNames('author_id')
                ->setUnquotedReferencedTableName('author')
                ->setUnquotedReferencedColumnNames('id')
                ->setOnDeleteAction(ReferentialAction::CASCADE)
                ->create())
            ->create());
    }

    private static function id(): Column
    {
        return Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setAutoincrement(true)->create();
    }
}
