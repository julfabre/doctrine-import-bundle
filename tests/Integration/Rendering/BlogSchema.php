<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Tests\Integration\Rendering;

use Juf\DoctrineImportBundle\Analysis\SchemaAnalyzer;
use Juf\DoctrineImportBundle\Definition\GenerationModel;
use Juf\DoctrineImportBundle\Schema\IndexMetadata;
use Juf\DoctrineImportBundle\Schema\TableMetadata;
use Juf\DoctrineImportBundle\Tests\Unit\Analysis\SchemaBuilder;

/**
 * A blog schema covering every rendering feature: field types and options, defaults, identity and composite
 * identifiers, generated and enum columns, ManyToOne / OneToMany / OneToOne / ManyToMany associations,
 * a self-reference, indexes and comments.
 */
final class BlogSchema
{
    public static function model(): GenerationModel
    {
        return (new SchemaAnalyzer())->analyze(SchemaBuilder::schema([
            self::author(),
            self::authorProfile(),
            self::category(),
            self::orderLine(),
            self::post(),
            self::postTag(),
            self::tag(),
        ]));
    }

    private static function author(): TableMetadata
    {
        return SchemaBuilder::table('author', [
            SchemaBuilder::id(),
            SchemaBuilder::column('name', 'string', comment: 'Display name', length: 100),
            SchemaBuilder::column('email', 'string', length: 180),
            SchemaBuilder::column('bio', 'text', nullable: true),
            SchemaBuilder::column('is_active', 'boolean', default: '1'),
        ], indexes: [new IndexMetadata('uniq_author_email', ['email'], unique: true)], comment: 'Blog authors');
    }

    private static function authorProfile(): TableMetadata
    {
        return SchemaBuilder::table('author_profile', [
            SchemaBuilder::id(),
            SchemaBuilder::column('author_id'),
            SchemaBuilder::column('website', 'string', nullable: true, length: 255),
        ], foreignKeys: [SchemaBuilder::foreignKey(['author_id'], 'author', onDelete: 'CASCADE')], indexes: [
            new IndexMetadata('uniq_author_profile_author', ['author_id'], unique: true),
        ]);
    }

    private static function category(): TableMetadata
    {
        return SchemaBuilder::table('category', [
            SchemaBuilder::id(),
            SchemaBuilder::column('parent_id', nullable: true),
            SchemaBuilder::column('name', 'string', length: 50),
        ], foreignKeys: [SchemaBuilder::foreignKey(['parent_id'], 'category', onDelete: 'SET NULL')]);
    }

    private static function orderLine(): TableMetadata
    {
        return SchemaBuilder::table('order_line', [
            SchemaBuilder::column('order_id'),
            SchemaBuilder::column('product_id'),
            SchemaBuilder::column('quantity', default: '1'),
        ], primaryKey: ['order_id', 'product_id']);
    }

    private static function post(): TableMetadata
    {
        return SchemaBuilder::table('post', [
            SchemaBuilder::id(),
            SchemaBuilder::column('author_id'),
            SchemaBuilder::column('category_id', nullable: true),
            SchemaBuilder::column('title', 'string', length: 255),
            SchemaBuilder::column('status', 'string', default: 'draft', length: 20),
            SchemaBuilder::column('price', 'decimal', nullable: true, precision: 10, scale: 2),
            SchemaBuilder::column('views', 'integer', default: '0', unsigned: true),
            SchemaBuilder::column('published_at', 'datetime', nullable: true),
            SchemaBuilder::column('metadata', 'json', nullable: true),
            SchemaBuilder::column('visibility', 'enum', default: 'public', values: ['public', 'private', 'unlisted']),
            SchemaBuilder::column('title_length', nullable: true, generated: true),
        ], foreignKeys: [
            SchemaBuilder::foreignKey(['author_id'], 'author', onDelete: 'CASCADE'),
            SchemaBuilder::foreignKey(['category_id'], 'category'),
        ], indexes: [new IndexMetadata('idx_post_author', ['author_id'], unique: false)]);
    }

    private static function postTag(): TableMetadata
    {
        return SchemaBuilder::table('post_tag', [
            SchemaBuilder::column('post_id'),
            SchemaBuilder::column('tag_id'),
        ], primaryKey: ['post_id', 'tag_id'], foreignKeys: [
            SchemaBuilder::foreignKey(['post_id'], 'post', onDelete: 'CASCADE'),
            SchemaBuilder::foreignKey(['tag_id'], 'tag', onDelete: 'CASCADE'),
        ]);
    }

    private static function tag(): TableMetadata
    {
        return SchemaBuilder::table('tag', [
            SchemaBuilder::id(),
            SchemaBuilder::column('label', 'string', length: 50),
        ]);
    }
}
