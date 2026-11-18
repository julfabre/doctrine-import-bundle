<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Tests\Integration\Schema\Fixture;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;

/**
 * Portable schema: post, tag and the pure join table post_tag.
 */
final class ManyToManySchemaFixture
{
    public static function create(Connection $connection): void
    {
        $schemaManager = $connection->createSchemaManager();
        $schemaManager->createTable(self::entityTable('post', 'title'));
        $schemaManager->createTable(self::entityTable('tag', 'label'));
        $schemaManager->createTable(Table::editor()
            ->setUnquotedName('post_tag')
            ->setColumns(
                Column::editor()->setUnquotedName('post_id')->setTypeName(Types::INTEGER)->create(),
                Column::editor()->setUnquotedName('tag_id')->setTypeName(Types::INTEGER)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('post_id', 'tag_id')->create())
            ->setForeignKeyConstraints(self::foreignKey('post_id', 'post'), self::foreignKey('tag_id', 'tag'))
            ->create());
    }

    /**
     * @param non-empty-string $name
     * @param non-empty-string $column
     */
    private static function entityTable(string $name, string $column): Table
    {
        return Table::editor()
            ->setUnquotedName($name)
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setAutoincrement(true)->create(),
                Column::editor()->setUnquotedName($column)->setTypeName(Types::STRING)->setLength(100)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
            ->create();
    }

    /**
     * @param non-empty-string $column
     * @param non-empty-string $target
     */
    private static function foreignKey(string $column, string $target): ForeignKeyConstraint
    {
        return ForeignKeyConstraint::editor()
            ->setUnquotedName('fk_post_tag_'.$target)
            ->setUnquotedReferencingColumnNames($column)
            ->setUnquotedReferencedTableName($target)
            ->setUnquotedReferencedColumnNames('id')
            ->setOnDeleteAction(ReferentialAction::CASCADE)
            ->create();
    }
}
