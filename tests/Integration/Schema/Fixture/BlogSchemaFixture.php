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
 * Portable schema built with the DBAL API: author, post (foreign key to author), order_line
 * (composite primary key) and a view.
 */
final class BlogSchemaFixture
{
    public static function create(Connection $connection): void
    {
        $schemaManager = $connection->createSchemaManager();
        $schemaManager->createTable(self::author());
        $schemaManager->createTable(self::post());
        $schemaManager->createTable(self::orderLine());

        $connection->executeStatement('CREATE VIEW v_published_post AS SELECT id, title FROM post');
    }

    private static function author(): Table
    {
        return Table::editor()
            ->setUnquotedName('author')
            ->setColumns(
                self::autoIncrementId(),
                Column::editor()->setUnquotedName('name')->setTypeName(Types::STRING)->setLength(100)->setComment('Display name')->create(),
                Column::editor()->setUnquotedName('email')->setTypeName(Types::STRING)->setLength(180)->create(),
                Column::editor()->setUnquotedName('bio')->setTypeName(Types::TEXT)->setNotNull(false)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
            ->setIndexes(Index::editor()->setUnquotedName('uniq_author_email')->setType(IndexType::UNIQUE)->setUnquotedColumnNames('email'))
            ->setComment('Blog authors')
            ->create();
    }

    private static function post(): Table
    {
        return Table::editor()
            ->setUnquotedName('post')
            ->setColumns(
                self::autoIncrementId(),
                Column::editor()->setUnquotedName('author_id')->setTypeName(Types::INTEGER)->create(),
                Column::editor()->setUnquotedName('title')->setTypeName(Types::STRING)->setLength(255)->create(),
                Column::editor()->setUnquotedName('status')->setTypeName(Types::STRING)->setLength(20)->setDefaultValue('draft')->create(),
                Column::editor()->setUnquotedName('price')->setTypeName(Types::DECIMAL)->setPrecision(10)->setScale(2)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('is_featured')->setTypeName(Types::BOOLEAN)->create(),
                Column::editor()->setUnquotedName('published_at')->setTypeName(Types::DATETIME_IMMUTABLE)->setNotNull(false)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
            ->setIndexes(Index::editor()->setUnquotedName('idx_post_author')->setUnquotedColumnNames('author_id'))
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedName('fk_post_author')
                    ->setUnquotedReferencingColumnNames('author_id')
                    ->setUnquotedReferencedTableName('author')
                    ->setUnquotedReferencedColumnNames('id')
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->create();
    }

    private static function orderLine(): Table
    {
        return Table::editor()
            ->setUnquotedName('order_line')
            ->setColumns(
                Column::editor()->setUnquotedName('order_id')->setTypeName(Types::INTEGER)->create(),
                Column::editor()->setUnquotedName('product_id')->setTypeName(Types::INTEGER)->create(),
                Column::editor()->setUnquotedName('quantity')->setTypeName(Types::INTEGER)->setDefaultValue(1)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('order_id', 'product_id')->create())
            ->create();
    }

    private static function autoIncrementId(): Column
    {
        return Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setAutoincrement(true)->create();
    }
}
