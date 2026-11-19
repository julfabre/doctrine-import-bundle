<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Tests\Integration\Schema\Fixture;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;

/**
 * Portable schema of composite primary keys whose order differs from the column order:
 * film_actor (two foreign keys), order_item (a foreign key and a scalar) and price (two scalars).
 */
final class DerivedIdentitySchemaFixture
{
    public static function create(Connection $connection): void
    {
        $schemaManager = $connection->createSchemaManager();
        $schemaManager->createTable(self::table('film', [self::integer('id', autoincrement: true)], ['id']));
        $schemaManager->createTable(self::table('actor', [self::integer('id', autoincrement: true)], ['id']));
        $schemaManager->createTable(self::table(
            'film_actor',
            [self::integer('film_id'), self::integer('actor_id'), Column::editor()->setUnquotedName('last_update')->setTypeName(Types::DATETIME_IMMUTABLE)->create()],
            ['actor_id', 'film_id'],
            [self::foreignKey('fk_film_actor_film', 'film_id', 'film'), self::foreignKey('fk_film_actor_actor', 'actor_id', 'actor')],
            // As in Sakila: the second key column has its own named index, the first one is covered by the primary key.
            [Index::editor()->setUnquotedName('idx_fk_film_id')->setUnquotedColumnNames('film_id')->create()],
        ));
        $schemaManager->createTable(self::table(
            'order_item',
            [self::integer('line_no'), self::integer('film_id')],
            ['film_id', 'line_no'],
            [self::foreignKey('fk_order_item_film', 'film_id', 'film')],
        ));
        $schemaManager->createTable(self::table('price', [self::integer('product_code'), self::integer('region_code'), self::integer('amount')], ['region_code', 'product_code']));
    }

    /**
     * @param non-empty-string                 $name
     * @param list<Column>                     $columns
     * @param non-empty-list<non-empty-string> $primaryKey
     * @param list<ForeignKeyConstraint>       $foreignKeys
     * @param list<Index>                      $indexes
     */
    private static function table(string $name, array $columns, array $primaryKey, array $foreignKeys = [], array $indexes = []): Table
    {
        return Table::editor()
            ->setUnquotedName($name)
            ->setColumns(...$columns)
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames(...$primaryKey)->create())
            ->setForeignKeyConstraints(...$foreignKeys)
            ->setIndexes(...$indexes)
            ->create();
    }

    /**
     * @param non-empty-string $name
     */
    private static function integer(string $name, bool $autoincrement = false): Column
    {
        return Column::editor()->setUnquotedName($name)->setTypeName(Types::INTEGER)->setAutoincrement($autoincrement)->create();
    }

    /**
     * @param non-empty-string $name
     * @param non-empty-string $column
     * @param non-empty-string $target
     */
    private static function foreignKey(string $name, string $column, string $target): ForeignKeyConstraint
    {
        return ForeignKeyConstraint::editor()
            ->setUnquotedName($name)
            ->setUnquotedReferencingColumnNames($column)
            ->setUnquotedReferencedTableName($target)
            ->setUnquotedReferencedColumnNames('id')
            ->create();
    }
}
