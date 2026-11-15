<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Tests\Unit\Analysis;

use Juf\DoctrineImportBundle\Analysis\ClassNameResolver;
use Juf\DoctrineImportBundle\Analysis\EntityAnalyzer;
use Juf\DoctrineImportBundle\Analysis\FieldAnalyzer;
use Juf\DoctrineImportBundle\Analysis\MappedType;
use Juf\DoctrineImportBundle\Analysis\Naming\ClassName;
use Juf\DoctrineImportBundle\Analysis\Naming\DefaultNamingStrategy;
use Juf\DoctrineImportBundle\Analysis\RelationAnalyzer;
use Juf\DoctrineImportBundle\Analysis\RelationSet;
use Juf\DoctrineImportBundle\Analysis\TypeMapper;
use Juf\DoctrineImportBundle\Definition\ColumnOptions;
use Juf\DoctrineImportBundle\Definition\EntityDefinition;
use Juf\DoctrineImportBundle\Definition\FieldDefinition;
use Juf\DoctrineImportBundle\Definition\GenerationModel;
use Juf\DoctrineImportBundle\Definition\IndexDefinition;
use Juf\DoctrineImportBundle\Definition\ManyToOneDefinition;
use Juf\DoctrineImportBundle\Definition\OneToManyDefinition;
use Juf\DoctrineImportBundle\Diagnostic\Diagnostic;
use Juf\DoctrineImportBundle\Diagnostic\DiagnosticCollector;
use Juf\DoctrineImportBundle\Schema\ColumnMetadata;
use Juf\DoctrineImportBundle\Schema\ColumnType;
use Juf\DoctrineImportBundle\Schema\ForeignKeyMetadata;
use Juf\DoctrineImportBundle\Schema\IndexMetadata;
use Juf\DoctrineImportBundle\Schema\SchemaMetadata;
use Juf\DoctrineImportBundle\Schema\TableMetadata;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RelationAnalyzer::class)]
#[CoversClass(ManyToOneDefinition::class)]
#[CoversClass(OneToManyDefinition::class)]
#[CoversClass(RelationSet::class)]
#[UsesClass(EntityAnalyzer::class)]
#[CoversClass(EntityDefinition::class)]
#[UsesClass(FieldDefinition::class)]
#[UsesClass(ColumnOptions::class)]
#[UsesClass(GenerationModel::class)]
#[UsesClass(Diagnostic::class)]
#[UsesClass(DiagnosticCollector::class)]
#[UsesClass(DefaultNamingStrategy::class)]
#[UsesClass(ClassName::class)]
#[UsesClass(TypeMapper::class)]
#[UsesClass(FieldAnalyzer::class)]
#[UsesClass(ClassNameResolver::class)]
#[UsesClass(MappedType::class)]
#[UsesClass(SchemaMetadata::class)]
#[UsesClass(TableMetadata::class)]
#[UsesClass(ColumnMetadata::class)]
#[UsesClass(ColumnType::class)]
#[UsesClass(ForeignKeyMetadata::class)]
#[UsesClass(IndexMetadata::class)]
#[UsesClass(IndexDefinition::class)]
final class RelationAnalyzerTest extends TestCase
{
    use DefinitionAssertions;

    #[Test]
    public function it_replaces_a_foreign_key_column_with_a_many_to_one_association(): void
    {
        $model = $this->analyze($this->author(), $this->post());

        $post = self::entity($model, 'Post');
        self::assertSame(['id', 'title'], self::propertyNames($post));
        $author = self::onlyManyToOne($post);
        self::assertSame(['author', 'Author', 'author_id', 'id'], [$author->propertyName, $author->targetEntity, $author->joinColumn, $author->referencedColumn]);
        self::assertSame([], self::diagnostics($model));
        self::assertTrue(array_is_list($model->entities));
    }

    #[Test]
    public function it_adds_the_inverse_one_to_many_collection_on_the_target_entity(): void
    {
        $model = $this->analyze($this->author(), $this->post());

        self::assertEquals([new OneToManyDefinition('posts', 'Post', mappedBy: 'author')], self::entity($model, 'Author')->oneToMany);
        self::assertSame('posts', self::onlyManyToOne(self::entity($model, 'Post'))->inversedBy);
    }

    #[Test]
    public function it_keeps_the_join_column_nullability_and_on_delete_action(): void
    {
        $model = $this->analyze($this->author(), SchemaBuilder::table('post', [
            SchemaBuilder::id(),
            SchemaBuilder::column('author_id'),
            SchemaBuilder::column('reviewer_id', nullable: true),
        ], foreignKeys: [
            SchemaBuilder::foreignKey(['author_id'], 'author', onDelete: 'CASCADE'),
            SchemaBuilder::foreignKey(['reviewer_id'], 'author', onDelete: 'NO ACTION'),
        ]));

        [$author, $reviewer] = self::entity($model, 'Post')->manyToOne + [null, null];
        self::assertNotNull($author);
        self::assertNotNull($reviewer);
        self::assertSame([false, 'CASCADE'], [$author->nullable, $author->onDelete]);
        self::assertSame([true, null], [$reviewer->nullable, $reviewer->onDelete]);
    }

    #[Test]
    public function it_names_several_foreign_keys_to_the_same_target_after_their_columns(): void
    {
        $model = $this->analyze(SchemaBuilder::table('user', [SchemaBuilder::id()]), SchemaBuilder::table('post', [
            SchemaBuilder::id(),
            SchemaBuilder::column('created_by'),
            SchemaBuilder::column('updated_by'),
        ], foreignKeys: [
            SchemaBuilder::foreignKey(['created_by'], 'user'),
            SchemaBuilder::foreignKey(['updated_by'], 'user'),
        ]));

        self::assertSame(
            [['createdBy', 'createdByPosts'], ['updatedBy', 'updatedByPosts']],
            array_map(static fn (ManyToOneDefinition $association): array => [$association->propertyName, $association->inversedBy], self::entity($model, 'Post')->manyToOne),
        );
        self::assertEquals([
            new OneToManyDefinition('createdByPosts', 'Post', mappedBy: 'createdBy'),
            new OneToManyDefinition('updatedByPosts', 'Post', mappedBy: 'updatedBy'),
        ], self::entity($model, 'User')->oneToMany);
    }

    #[Test]
    public function it_maps_a_self_referencing_foreign_key_on_the_same_entity(): void
    {
        $model = $this->analyze(SchemaBuilder::table('category', [
            SchemaBuilder::id(),
            SchemaBuilder::column('parent_id', nullable: true),
        ], foreignKeys: [SchemaBuilder::foreignKey(['parent_id'], 'category')]));

        $category = self::entity($model, 'Category');
        $parent = self::onlyManyToOne($category);
        self::assertSame(['parent', 'Category', 'categories'], [$parent->propertyName, $parent->targetEntity, $parent->inversedBy]);
        self::assertEquals([new OneToManyDefinition('categories', 'Category', mappedBy: 'parent')], $category->oneToMany);
    }

    #[Test]
    public function it_maps_a_unique_foreign_key_as_many_to_one_with_an_info(): void
    {
        $model = $this->analyze($this->author(), SchemaBuilder::table('profile', [
            SchemaBuilder::id(),
            SchemaBuilder::column('author_id'),
        ], foreignKeys: [SchemaBuilder::foreignKey(['author_id'], 'author')], indexes: [
            new IndexMetadata('uniq_profile_author', ['author_id'], unique: true),
        ]));

        self::assertSame('author', self::onlyManyToOne(self::entity($model, 'Profile'))->propertyName);
        self::assertSame([['info', 'relation.one_to_one_as_many_to_one', 'profile', 'author_id']], self::diagnostics($model));
    }

    #[Test]
    public function it_keeps_composite_foreign_key_columns_as_scalars_with_a_warning(): void
    {
        $model = $this->analyze(SchemaBuilder::table('order_line', [SchemaBuilder::column('order_id'), SchemaBuilder::column('line_no')], primaryKey: ['order_id', 'line_no']), SchemaBuilder::table('shipment', [
            SchemaBuilder::id(),
            SchemaBuilder::column('order_id'),
            SchemaBuilder::column('line_no'),
        ], foreignKeys: [SchemaBuilder::foreignKey(['order_id', 'line_no'], 'order_line', ['order_id', 'line_no'])]));

        $shipment = self::entity($model, 'Shipment');
        self::assertSame(['id', 'orderId', 'lineNo'], self::propertyNames($shipment));
        self::assertSame([], $shipment->manyToOne);
        self::assertContains(['warning', 'relation.composite_foreign_key', 'shipment', 'order_id'], self::diagnostics($model));
    }

    #[Test]
    public function it_ignores_a_foreign_key_to_a_non_primary_key_column_with_a_warning(): void
    {
        $model = $this->analyze(SchemaBuilder::table('author', [SchemaBuilder::id(), SchemaBuilder::column('code', 'string')]), SchemaBuilder::table('post', [SchemaBuilder::id(), SchemaBuilder::column('author_code', 'string')], foreignKeys: [
            SchemaBuilder::foreignKey(['author_code'], 'author', ['code']),
        ]));

        self::assertSame(['id', 'authorCode'], self::propertyNames(self::entity($model, 'Post')));
        self::assertSame([['warning', 'relation.non_primary_key_target', 'post', 'author_code']], self::diagnostics($model));
        self::assertSame(
            'Column "post.author_code" references a column of "author" that is not its primary key, which Doctrine does not support: it is kept as a scalar field.',
            ($model->diagnostics[0] ?? null)?->message,
        );
    }

    #[Test]
    public function it_keeps_a_primary_key_column_as_id_when_it_is_also_a_foreign_key(): void
    {
        $model = $this->analyze(SchemaBuilder::table('order', [SchemaBuilder::id()]), SchemaBuilder::table('order_detail', [SchemaBuilder::column('order_id'), SchemaBuilder::column('note', 'text')], primaryKey: ['order_id'], foreignKeys: [
            SchemaBuilder::foreignKey(['order_id'], 'order'),
        ]));

        $detail = self::entity($model, 'OrderDetail');
        self::assertTrue(self::field($detail, 'orderId')->isId());
        self::assertSame([], $detail->manyToOne);
        self::assertSame([['warning', 'relation.foreign_key_in_primary_key', 'order_detail', 'order_id']], self::diagnostics($model));
    }

    #[Test]
    public function it_ignores_a_foreign_key_to_a_skipped_table_with_a_warning(): void
    {
        $model = $this->analyze(SchemaBuilder::table('post', [SchemaBuilder::id(), SchemaBuilder::column('author_id')], foreignKeys: [
            SchemaBuilder::foreignKey(['author_id'], 'author'),
        ]));

        self::assertSame(['id', 'authorId'], self::propertyNames(self::entity($model, 'Post')));
        self::assertSame([['warning', 'relation.missing_target', 'post', 'author_id']], self::diagnostics($model));
    }

    #[Test]
    public function it_renames_an_association_that_collides_with_a_field_with_a_warning(): void
    {
        $model = $this->analyze($this->author(), SchemaBuilder::table('post', [
            SchemaBuilder::id(),
            SchemaBuilder::column('author', 'string'),
            SchemaBuilder::column('author_id'),
        ], foreignKeys: [SchemaBuilder::foreignKey(['author_id'], 'author')]));

        $post = self::entity($model, 'Post');
        self::assertSame(['id', 'author'], self::propertyNames($post));
        self::assertSame('authorId', self::onlyManyToOne($post)->propertyName);
        self::assertSame([['warning', 'naming.property_collision', 'post', 'author_id']], self::diagnostics($model));
    }

    #[Test]
    public function it_names_an_association_after_a_foreign_key_column_without_id_suffix(): void
    {
        $model = $this->analyze($this->author(), SchemaBuilder::table('post', [SchemaBuilder::id(), SchemaBuilder::column('author')], foreignKeys: [
            SchemaBuilder::foreignKey(['author'], 'author'),
        ]));

        self::assertSame('author', self::onlyManyToOne(self::entity($model, 'Post'))->propertyName);
        self::assertSame([], self::diagnostics($model));
    }

    #[Test]
    public function it_renames_the_second_association_when_two_columns_give_the_same_name(): void
    {
        $model = $this->analyze($this->author(), SchemaBuilder::table('post', [
            SchemaBuilder::id(),
            SchemaBuilder::column('author_id'),
            SchemaBuilder::column('id_author'),
        ], foreignKeys: [
            SchemaBuilder::foreignKey(['author_id'], 'author'),
            SchemaBuilder::foreignKey(['id_author'], 'author'),
        ]));

        self::assertSame(
            ['author', 'idAuthor'],
            array_map(static fn (ManyToOneDefinition $association): string => $association->propertyName, self::entity($model, 'Post')->manyToOne),
        );
        self::assertSame([['warning', 'naming.property_collision', 'post', 'id_author']], self::diagnostics($model));
    }

    #[Test]
    public function it_keeps_plain_collection_names_for_foreign_keys_to_different_targets(): void
    {
        $model = $this->analyze(
            $this->author(),
            SchemaBuilder::table('category', [SchemaBuilder::id()]),
            SchemaBuilder::table('post', [
                SchemaBuilder::id(),
                SchemaBuilder::column('author_id'),
                SchemaBuilder::column('category_id'),
            ], foreignKeys: [
                SchemaBuilder::foreignKey(['author_id'], 'author'),
                SchemaBuilder::foreignKey(['category_id'], 'category'),
            ], indexes: [new IndexMetadata('idx_post_author', ['author_id'], unique: false)]),
        );

        self::assertEquals([new OneToManyDefinition('posts', 'Post', mappedBy: 'author')], self::entity($model, 'Author')->oneToMany);
        self::assertEquals([new OneToManyDefinition('posts', 'Post', mappedBy: 'category')], self::entity($model, 'Category')->oneToMany);
        self::assertSame([], self::diagnostics($model));
    }

    #[Test]
    public function it_relates_to_a_target_whose_primary_key_is_not_the_first_column(): void
    {
        $model = $this->analyze(
            SchemaBuilder::table('country', [SchemaBuilder::column('name', 'string'), SchemaBuilder::column('code', 'string')], primaryKey: ['code']),
            SchemaBuilder::table('city', [SchemaBuilder::id(), SchemaBuilder::column('country_code', 'string')], foreignKeys: [
                SchemaBuilder::foreignKey(['country_code'], 'country', ['code']),
            ]),
        );

        self::assertSame('countryCode', self::onlyManyToOne(self::entity($model, 'City'))->propertyName);
        self::assertSame([], self::diagnostics($model));
    }

    private function author(): TableMetadata
    {
        return SchemaBuilder::table('author', [SchemaBuilder::id()]);
    }

    private function post(): TableMetadata
    {
        return SchemaBuilder::table('post', [
            SchemaBuilder::id(),
            SchemaBuilder::column('author_id'),
            SchemaBuilder::column('title', 'string'),
        ], foreignKeys: [SchemaBuilder::foreignKey(['author_id'], 'author')]);
    }

    private function analyze(TableMetadata ...$tables): GenerationModel
    {
        $schema = SchemaBuilder::schema(array_values($tables));

        return (new RelationAnalyzer())->analyze($schema, (new EntityAnalyzer())->analyze($schema));
    }
}
