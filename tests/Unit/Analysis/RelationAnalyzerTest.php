<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Tests\Unit\Analysis;

use Juf\DoctrineImportBundle\Analysis\ClassNameResolver;
use Juf\DoctrineImportBundle\Analysis\EntityAnalyzer;
use Juf\DoctrineImportBundle\Analysis\EnumAnalyzer;
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
use Juf\DoctrineImportBundle\Definition\IdGeneration;
use Juf\DoctrineImportBundle\Definition\IndexDefinition;
use Juf\DoctrineImportBundle\Definition\ManyToOneDefinition;
use Juf\DoctrineImportBundle\Definition\OneToManyDefinition;
use Juf\DoctrineImportBundle\Definition\OneToOneDefinition;
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
#[UsesClass(EnumAnalyzer::class)]
#[CoversClass(OneToOneDefinition::class)]
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
        self::assertSame(['parent', 'Category', 'children'], [$parent->propertyName, $parent->targetEntity, $parent->inversedBy]);
        self::assertEquals([new OneToManyDefinition('children', 'Category', mappedBy: 'parent')], $category->oneToMany);
    }

    #[Test]
    public function it_maps_a_unique_foreign_key_to_a_one_to_one_association(): void
    {
        $model = $this->analyze($this->author(), $this->profile(nullable: false, onDelete: 'CASCADE'));

        $profile = self::entity($model, 'Profile');
        self::assertEquals([new OneToOneDefinition('author', 'Author', 'author_id', 'id', false, 'CASCADE')], $profile->oneToOne);
        self::assertSame([], $profile->manyToOne);
        self::assertSame(['id'], self::propertyNames($profile));
    }

    #[Test]
    public function it_generates_no_inverse_side_for_a_one_to_one_and_reports_it(): void
    {
        $model = $this->analyze($this->author(), $this->profile());

        self::assertSame([], self::entity($model, 'Author')->oneToMany);
        self::assertSame([['info', 'relation.one_to_one_without_inverse_side', 'profile', 'author_id']], self::diagnostics($model));
        self::assertSame(
            'Column "profile.author_id" is mapped as a OneToOne association without inverse side: Doctrine always loads the inverse side of a OneToOne, add it by hand if needed.',
            $model->diagnostics[0]->message ?? null,
        );
    }

    #[Test]
    public function it_renames_an_association_whose_name_is_taken_by_a_one_to_one(): void
    {
        $model = $this->analyze($this->author(), SchemaBuilder::table('profile', [
            SchemaBuilder::id(),
            SchemaBuilder::column('author_id'),
            SchemaBuilder::column('id_author'),
        ], foreignKeys: [
            SchemaBuilder::foreignKey(['author_id'], 'author'),
            SchemaBuilder::foreignKey(['id_author'], 'author'),
        ], indexes: [new IndexMetadata('uniq_profile_author', ['author_id'], unique: true)]));

        $profile = self::entity($model, 'Profile');
        self::assertSame('author', $profile->oneToOne[0]->propertyName ?? null);
        self::assertSame('idAuthor', self::onlyManyToOne($profile)->propertyName);
    }

    #[Test]
    public function it_keeps_a_many_to_one_for_a_foreign_key_in_a_composite_unique_index(): void
    {
        $model = $this->analyze($this->author(), SchemaBuilder::table('profile', [
            SchemaBuilder::id(),
            SchemaBuilder::column('author_id'),
            SchemaBuilder::column('kind', 'string', length: 20),
        ], foreignKeys: [SchemaBuilder::foreignKey(['author_id'], 'author')], indexes: [
            new IndexMetadata('uniq_profile_author_kind', ['author_id', 'kind'], unique: true),
        ]));

        $profile = self::entity($model, 'Profile');
        self::assertSame('author', self::onlyManyToOne($profile)->propertyName);
        self::assertSame([], $profile->oneToOne);
    }

    #[Test]
    public function it_maps_a_self_referencing_unique_foreign_key_to_a_one_to_one(): void
    {
        $model = $this->analyze(SchemaBuilder::table('node', [
            SchemaBuilder::id(),
            SchemaBuilder::column('next_id', nullable: true),
        ], foreignKeys: [SchemaBuilder::foreignKey(['next_id'], 'node')], indexes: [
            new IndexMetadata('uniq_node_next', ['next_id'], unique: true),
        ]));

        $node = self::entity($model, 'Node');
        self::assertEquals([new OneToOneDefinition('next', 'Node', 'next_id', 'id', true, null)], $node->oneToOne);
        self::assertSame([], $node->oneToMany);
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
    public function it_maps_a_foreign_key_in_a_composite_primary_key_to_an_identifier_association(): void
    {
        $model = $this->analyze($this->film(), $this->actor(), $this->filmActor());

        $filmActor = self::entity($model, 'FilmActor');
        self::assertSame(['film', 'actor'], array_map(static fn (ManyToOneDefinition $association): string => $association->propertyName, $filmActor->manyToOne));
        self::assertSame([false, false], array_map(static fn (ManyToOneDefinition $association): bool => $association->nullable, $filmActor->manyToOne));
        self::assertSame(['lastUpdate'], self::propertyNames($filmActor));
        self::assertSame([['info', 'table.composite_primary_key', 'film_actor', null]], self::diagnostics($model));
    }

    #[Test]
    public function it_mixes_scalar_and_association_identifiers(): void
    {
        $model = $this->analyze($this->film(), SchemaBuilder::table('order_item', [
            SchemaBuilder::column('film_id'),
            SchemaBuilder::column('line_no'),
        ], primaryKey: ['film_id', 'line_no'], foreignKeys: [SchemaBuilder::foreignKey(['film_id'], 'film')]));

        $orderItem = self::entity($model, 'OrderItem');
        self::assertSame('film', self::onlyManyToOne($orderItem)->propertyName);
        self::assertSame(IdGeneration::None, self::field($orderItem, 'lineNo')->idGeneration);
        self::assertSame(['film_id', 'line_no'], $orderItem->primaryKey);
    }

    #[Test]
    public function it_adds_the_inverse_collection_of_an_identifier_association(): void
    {
        $model = $this->analyze($this->film(), $this->actor(), $this->filmActor());

        self::assertEquals([new OneToManyDefinition('filmActors', 'FilmActor', 'film')], self::entity($model, 'Film')->oneToMany);
    }

    #[Test]
    public function it_never_maps_a_unique_foreign_key_of_a_composite_primary_key_to_a_one_to_one(): void
    {
        $model = $this->analyze($this->film(), $this->actor(), $this->filmActor([new IndexMetadata('uniq_film_actor_film', ['film_id'], unique: true)]));

        $filmActor = self::entity($model, 'FilmActor');
        self::assertSame([], $filmActor->oneToOne);
        self::assertSame(['film', 'actor'], array_map(static fn (ManyToOneDefinition $association): string => $association->propertyName, $filmActor->manyToOne));
    }

    #[Test]
    public function it_resets_on_a_join_column_the_default_of_the_column_it_references(): void
    {
        $model = $this->analyze($this->serialCity(), SchemaBuilder::table('address', [SchemaBuilder::id(), SchemaBuilder::column('city_id')], foreignKeys: [
            SchemaBuilder::foreignKey(['city_id'], 'city'),
        ]));

        self::assertSame(['default' => null], self::onlyManyToOne(self::entity($model, 'Address'))->joinColumnOptions);
    }

    #[Test]
    public function it_resets_on_a_one_to_one_join_column_the_default_of_the_column_it_references(): void
    {
        $model = $this->analyze($this->serialCity(), SchemaBuilder::table('mayor', [SchemaBuilder::id(), SchemaBuilder::column('city_id')], foreignKeys: [
            SchemaBuilder::foreignKey(['city_id'], 'city'),
        ], indexes: [new IndexMetadata('uniq_mayor_city', ['city_id'], unique: true)]));

        self::assertSame(['default' => null], (self::entity($model, 'Mayor')->oneToOne[0] ?? null)?->joinColumnOptions);
    }

    #[Test]
    public function it_keeps_no_join_column_option_when_the_referenced_column_has_no_default(): void
    {
        $model = $this->analyze($this->author(), $this->post());

        self::assertSame([], self::onlyManyToOne(self::entity($model, 'Post'))->joinColumnOptions);
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
        self::assertSame(
            'Column "order_detail.order_id" forms the whole primary key and a foreign key, a OneToOne sharing its primary key that is not generated yet: it is kept as a scalar field.',
            $model->diagnostics[0]->message ?? null,
        );
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

    #[Test]
    public function it_reports_an_on_update_action_that_doctrine_cannot_map(): void
    {
        $model = $this->analyze($this->author(), SchemaBuilder::table('post', [SchemaBuilder::id(), SchemaBuilder::column('author_id')], foreignKeys: [
            SchemaBuilder::foreignKey(['author_id'], 'author', onDelete: 'RESTRICT', onUpdate: 'CASCADE'),
        ]), SchemaBuilder::table('review', [SchemaBuilder::id(), SchemaBuilder::column('author_id')], foreignKeys: [
            SchemaBuilder::foreignKey(['author_id'], 'author', onUpdate: 'RESTRICT'),
        ]));

        self::assertSame('author', self::onlyManyToOne(self::entity($model, 'Post'))->propertyName);
        self::assertSame([['warning', 'relation.on_update_not_mapped', 'post', 'author_id']], self::diagnostics($model));
    }

    private function author(): TableMetadata
    {
        return SchemaBuilder::table('author', [SchemaBuilder::id()]);
    }

    private function profile(bool $nullable = true, string $onDelete = 'NO ACTION'): TableMetadata
    {
        return SchemaBuilder::table('profile', [
            SchemaBuilder::id(),
            SchemaBuilder::column('author_id', nullable: $nullable),
        ], foreignKeys: [SchemaBuilder::foreignKey(['author_id'], 'author', onDelete: $onDelete)], indexes: [
            new IndexMetadata('uniq_profile_author', ['author_id'], unique: true),
        ]);
    }

    private function film(): TableMetadata
    {
        return SchemaBuilder::table('film', [SchemaBuilder::id()]);
    }

    private function actor(): TableMetadata
    {
        return SchemaBuilder::table('actor', [SchemaBuilder::id()]);
    }

    /**
     * @param list<IndexMetadata> $indexes
     */
    private function filmActor(array $indexes = []): TableMetadata
    {
        return SchemaBuilder::table('film_actor', [
            SchemaBuilder::column('actor_id'),
            SchemaBuilder::column('film_id'),
            SchemaBuilder::column('last_update', 'datetime'),
        ], primaryKey: ['actor_id', 'film_id'], foreignKeys: [
            SchemaBuilder::foreignKey(['film_id'], 'film'),
            SchemaBuilder::foreignKey(['actor_id'], 'actor'),
        ], indexes: $indexes);
    }

    /**
     * The ORM copies the definition of a referenced column, default included, onto every join column.
     */
    private function serialCity(): TableMetadata
    {
        return SchemaBuilder::table('city', [SchemaBuilder::column('id', default: "nextval('city_id_seq'::regclass)")]);
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
