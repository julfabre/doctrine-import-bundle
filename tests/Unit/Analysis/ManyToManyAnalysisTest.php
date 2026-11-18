<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Tests\Unit\Analysis;

use Juf\DoctrineImportBundle\Analysis\ClassNameResolver;
use Juf\DoctrineImportBundle\Analysis\EntityAnalyzer;
use Juf\DoctrineImportBundle\Analysis\EnumAnalyzer;
use Juf\DoctrineImportBundle\Analysis\FieldAnalyzer;
use Juf\DoctrineImportBundle\Analysis\JoinTable;
use Juf\DoctrineImportBundle\Analysis\JoinTableDetector;
use Juf\DoctrineImportBundle\Analysis\ManyToManyAnalyzer;
use Juf\DoctrineImportBundle\Analysis\MappedType;
use Juf\DoctrineImportBundle\Analysis\Naming\ClassName;
use Juf\DoctrineImportBundle\Analysis\Naming\DefaultNamingStrategy;
use Juf\DoctrineImportBundle\Analysis\Naming\InvalidIdentifierException;
use Juf\DoctrineImportBundle\Analysis\Naming\KnownTablePrefixes;
use Juf\DoctrineImportBundle\Analysis\RelationAnalyzer;
use Juf\DoctrineImportBundle\Analysis\RelationSet;
use Juf\DoctrineImportBundle\Analysis\SchemaAnalyzer;
use Juf\DoctrineImportBundle\Analysis\TypeMapper;
use Juf\DoctrineImportBundle\Definition\ColumnOptions;
use Juf\DoctrineImportBundle\Definition\EntityDefinition;
use Juf\DoctrineImportBundle\Definition\FieldDefinition;
use Juf\DoctrineImportBundle\Definition\GenerationModel;
use Juf\DoctrineImportBundle\Definition\IndexDefinition;
use Juf\DoctrineImportBundle\Definition\JoinColumnDefinition;
use Juf\DoctrineImportBundle\Definition\JoinTableDefinition;
use Juf\DoctrineImportBundle\Definition\ManyToManyDefinition;
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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * A pure join table becomes a ManyToMany association instead of an entity.
 */
#[CoversClass(SchemaAnalyzer::class)]
#[UsesClass(EntityAnalyzer::class)]
#[UsesClass(RelationAnalyzer::class)]
#[UsesClass(RelationSet::class)]
#[CoversClass(EntityDefinition::class)]
#[UsesClass(FieldDefinition::class)]
#[UsesClass(ColumnOptions::class)]
#[UsesClass(ManyToOneDefinition::class)]
#[UsesClass(OneToManyDefinition::class)]
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
#[UsesClass(KnownTablePrefixes::class)]
#[CoversClass(JoinTableDetector::class)]
#[CoversClass(JoinTable::class)]
#[CoversClass(ManyToManyAnalyzer::class)]
#[CoversClass(ManyToManyDefinition::class)]
#[CoversClass(JoinTableDefinition::class)]
#[CoversClass(JoinColumnDefinition::class)]
#[UsesClass(OneToOneDefinition::class)]
#[UsesClass(InvalidIdentifierException::class)]
final class ManyToManyAnalysisTest extends TestCase
{
    use DefinitionAssertions;

    #[Test]
    public function it_maps_a_pure_join_table_to_a_many_to_many_association(): void
    {
        $model = $this->analyze($this->post(), $this->tag(), $this->joinTable('post_tag', 'post', 'tag', onDelete: 'CASCADE'));

        self::assertEquals([new ManyToManyDefinition(
            'tags',
            'Tag',
            new JoinTableDefinition('post_tag', new JoinColumnDefinition('post_id', 'id', 'CASCADE'), new JoinColumnDefinition('tag_id', 'id', 'CASCADE')),
            'posts',
            null,
        )], self::entity($model, 'Post')->manyToMany);
        self::assertEquals([new ManyToManyDefinition('posts', 'Post', null, null, 'tags')], self::entity($model, 'Tag')->manyToMany);
    }

    #[Test]
    public function it_does_not_generate_an_entity_for_a_join_table(): void
    {
        $model = $this->analyze($this->post(), $this->tag(), $this->joinTable('post_tag', 'post', 'tag'));

        self::assertSame(['Post', 'Tag'], self::classNames($model));
        self::assertSame([['info', 'relation.many_to_many', 'post_tag', null]], self::diagnostics($model));
        self::assertSame('Table "post_tag" is mapped as the ManyToMany association Post::$tags.', $model->diagnostics[0]->message ?? null);
    }

    #[Test]
    public function it_chooses_the_owning_side_from_the_first_primary_key_column(): void
    {
        $model = $this->analyze($this->post(), $this->tag(), $this->joinTable('tag_post', 'tag', 'post'));

        self::assertSame('tag_post', (self::entity($model, 'Tag')->manyToMany[0] ?? null)?->joinTable?->name);
        self::assertSame('posts', (self::entity($model, 'Post')->manyToMany[0] ?? null)?->mappedBy);
    }

    #[Test]
    public function it_keeps_no_action_out_of_the_join_columns(): void
    {
        $model = $this->analyze($this->post(), $this->tag(), $this->joinTable('post_tag', 'post', 'tag'));

        $joinTable = self::entity($model, 'Post')->manyToMany[0]->joinTable ?? null;
        self::assertEquals(new JoinColumnDefinition('post_id', 'id', null), $joinTable?->joinColumn);
        self::assertEquals(new JoinColumnDefinition('tag_id', 'id', null), $joinTable?->inverseJoinColumn);
    }

    #[Test]
    public function it_keeps_a_table_with_an_extra_column_as_an_entity(): void
    {
        $filmActor = SchemaBuilder::table('film_actor', [
            SchemaBuilder::column('film_id'),
            SchemaBuilder::column('actor_id'),
            SchemaBuilder::column('last_update', 'datetime'),
        ], primaryKey: ['film_id', 'actor_id'], foreignKeys: [
            SchemaBuilder::foreignKey(['film_id'], 'film'),
            SchemaBuilder::foreignKey(['actor_id'], 'actor'),
        ]);

        $model = $this->analyze(SchemaBuilder::table('film', [SchemaBuilder::id()]), SchemaBuilder::table('actor', [SchemaBuilder::id()]), $filmActor);

        self::assertSame(['Actor', 'Film', 'FilmActor'], self::classNames($model));
        self::assertSame([], self::entity($model, 'Film')->manyToMany);
    }

    /**
     * @return iterable<string, array{TableMetadata}>
     */
    public static function tablesThatAreNotPureJoinTables(): iterable
    {
        yield 'unique index besides the primary key' => [SchemaBuilder::table('post_tag', [SchemaBuilder::column('post_id'), SchemaBuilder::column('tag_id')], primaryKey: ['post_id', 'tag_id'], foreignKeys: [
            SchemaBuilder::foreignKey(['post_id'], 'post'),
            SchemaBuilder::foreignKey(['tag_id'], 'tag'),
        ], indexes: [new IndexMetadata('uniq_post_tag_tag', ['tag_id'], unique: true)])];
        yield 'a primary key column without foreign key' => [SchemaBuilder::table('post_tag', [SchemaBuilder::column('post_id'), SchemaBuilder::column('tag_id')], primaryKey: ['post_id', 'tag_id'], foreignKeys: [
            SchemaBuilder::foreignKey(['post_id'], 'post'),
        ])];
        yield 'a primary key on one column' => [SchemaBuilder::table('post_tag', [SchemaBuilder::column('post_id'), SchemaBuilder::column('tag_id')], primaryKey: ['post_id'], foreignKeys: [
            SchemaBuilder::foreignKey(['post_id'], 'post'),
            SchemaBuilder::foreignKey(['tag_id'], 'tag'),
        ])];
        yield 'a foreign key to a column that is not the primary key' => [SchemaBuilder::table('post_tag', [SchemaBuilder::column('post_id'), SchemaBuilder::column('tag_label')], primaryKey: ['post_id', 'tag_label'], foreignKeys: [
            SchemaBuilder::foreignKey(['post_id'], 'post'),
            SchemaBuilder::foreignKey(['tag_label'], 'tag', ['label']),
        ])];
    }

    #[Test]
    #[DataProvider('tablesThatAreNotPureJoinTables')]
    public function it_keeps_a_table_that_is_not_a_pure_join_table_as_an_entity(TableMetadata $table): void
    {
        $model = $this->analyze($this->post(), $this->tag(), $table);

        self::assertContains('PostTag', self::classNames($model));
        self::assertSame([], self::entity($model, 'Post')->manyToMany);
    }

    #[Test]
    public function it_maps_a_self_referencing_join_table_to_an_owning_side_only(): void
    {
        // post_tag comes first: user_friend must still find the User entity, which post_tag does not change.
        $model = $this->analyze($this->post(), $this->tag(), $this->joinTable('post_tag', 'post', 'tag'), SchemaBuilder::table('user', [SchemaBuilder::id()]), SchemaBuilder::table('user_friend', [
            SchemaBuilder::column('user_id'),
            SchemaBuilder::column('friend_id'),
        ], primaryKey: ['user_id', 'friend_id'], foreignKeys: [
            SchemaBuilder::foreignKey(['user_id'], 'user'),
            SchemaBuilder::foreignKey(['friend_id'], 'user'),
        ]));

        self::assertEquals([new ManyToManyDefinition(
            'friends',
            'User',
            new JoinTableDefinition('user_friend', new JoinColumnDefinition('user_id', 'id', null), new JoinColumnDefinition('friend_id', 'id', null)),
            null,
            null,
        )], self::entity($model, 'User')->manyToMany);
        self::assertSame(['Post', 'Tag', 'User'], self::classNames($model));
        self::assertSame([
            ['info', 'relation.many_to_many', 'post_tag', null],
            ['info', 'relation.many_to_many_without_inverse_side', 'user_friend', null],
        ], self::diagnostics($model));
    }

    #[Test]
    public function it_names_a_collection_after_its_join_table_when_the_name_is_taken(): void
    {
        $model = $this->analyze($this->post(), $this->tag(), $this->joinTable('post_tag', 'post', 'tag'), $this->joinTable('post_featured_tag', 'post', 'tag'));

        self::assertSame(['tags', 'postFeaturedTags'], array_map(static fn (ManyToManyDefinition $collection): string => $collection->propertyName, self::entity($model, 'Post')->manyToMany));
        self::assertSame(['posts', 'postFeaturedTags'], array_map(static fn (ManyToManyDefinition $collection): string => $collection->propertyName, self::entity($model, 'Tag')->manyToMany));
        self::assertSame('postFeaturedTags', self::entity($model, 'Tag')->manyToMany[1]->mappedBy ?? null);
        self::assertContains(['warning', 'naming.property_collision', 'post_featured_tag', null], self::diagnostics($model));
    }

    /**
     * @return iterable<string, array{list<TableMetadata>}> the post and tag tables, plus the tables they reference
     */
    public static function postsThatAlreadyHaveATagsProperty(): iterable
    {
        $label = SchemaBuilder::table('label', [SchemaBuilder::id()]);
        $tag = SchemaBuilder::table('tag', [SchemaBuilder::id()]);

        yield 'field' => [[$tag, SchemaBuilder::table('post', [SchemaBuilder::id(), SchemaBuilder::column('tags', 'text')])]];
        yield 'ManyToOne' => [[$tag, $label, SchemaBuilder::table('post', [SchemaBuilder::id(), SchemaBuilder::column('tags_id')], foreignKeys: [SchemaBuilder::foreignKey(['tags_id'], 'label')])]];
        yield 'OneToOne' => [[$tag, $label, SchemaBuilder::table('post', [SchemaBuilder::id(), SchemaBuilder::column('tags_id')], foreignKeys: [SchemaBuilder::foreignKey(['tags_id'], 'label')], indexes: [
            new IndexMetadata('uniq_post_tags', ['tags_id'], unique: true),
        ])]];
        yield 'OneToMany' => [[SchemaBuilder::table('post', [SchemaBuilder::id()]), SchemaBuilder::table('tag', [SchemaBuilder::id(), SchemaBuilder::column('post_id')], foreignKeys: [SchemaBuilder::foreignKey(['post_id'], 'post')])]];
    }

    /**
     * @param list<TableMetadata> $tables
     */
    #[Test]
    #[DataProvider('postsThatAlreadyHaveATagsProperty')]
    public function it_names_a_collection_after_its_join_table_when_any_property_has_the_name(array $tables): void
    {
        $model = $this->analyze(...[...$tables, $this->joinTable('post_tag', 'post', 'tag')]);

        self::assertSame('postTags', self::entity($model, 'Post')->manyToMany[0]->propertyName ?? null);
    }

    #[Test]
    public function it_reports_an_index_of_a_join_table_that_doctrine_would_rename(): void
    {
        $model = $this->analyze($this->post(), $this->tag(), $this->joinTable('post_tag', 'post', 'tag', indexes: [new IndexMetadata('idx_post_tag_tag', ['tag_id'], unique: false)]));

        self::assertCount(1, self::entity($model, 'Post')->manyToMany);
        self::assertContains(['warning', 'relation.join_table_index_renamed', 'post_tag', null], self::diagnostics($model));
        self::assertContains(
            'Table "post_tag" has the index "idx_post_tag_tag", which a ManyToMany mapping cannot name: doctrine:schema:update would rename it.',
            array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message, $model->diagnostics),
        );
    }

    #[Test]
    public function it_skips_a_join_table_whose_target_is_not_generated_with_a_warning(): void
    {
        $model = $this->analyze($this->post(), SchemaBuilder::table('comment', [SchemaBuilder::id()]), SchemaBuilder::table('tag-list', [SchemaBuilder::id()]), SchemaBuilder::table('post_tag', [
            SchemaBuilder::column('post_id'),
            SchemaBuilder::column('tag_id'),
        ], primaryKey: ['post_id', 'tag_id'], foreignKeys: [
            SchemaBuilder::foreignKey(['post_id'], 'post'),
            SchemaBuilder::foreignKey(['tag_id'], 'tag-list'),
        ]));

        self::assertSame(['Comment', 'Post'], self::classNames($model));
        self::assertSame([], self::entity($model, 'Post')->manyToMany);
        self::assertContains(['warning', 'relation.join_table_not_mapped', 'post_tag', null], self::diagnostics($model));
    }

    private function post(): TableMetadata
    {
        return SchemaBuilder::table('post', [SchemaBuilder::id()]);
    }

    private function tag(): TableMetadata
    {
        return SchemaBuilder::table('tag', [SchemaBuilder::id(), SchemaBuilder::column('label', 'string', length: 50)]);
    }

    /**
     * @param list<IndexMetadata> $indexes
     */
    private function joinTable(string $name, string $owning, string $target, string $onDelete = 'NO ACTION', array $indexes = []): TableMetadata
    {
        return SchemaBuilder::table($name, [
            SchemaBuilder::column($owning.'_id'),
            SchemaBuilder::column($target.'_id'),
        ], primaryKey: [$owning.'_id', $target.'_id'], foreignKeys: [
            SchemaBuilder::foreignKey([$owning.'_id'], $owning, onDelete: $onDelete),
            SchemaBuilder::foreignKey([$target.'_id'], $target, onDelete: $onDelete),
        ], indexes: $indexes);
    }

    private function analyze(TableMetadata ...$tables): GenerationModel
    {
        return (new SchemaAnalyzer())->analyze(SchemaBuilder::schema(array_values($tables)));
    }
}
