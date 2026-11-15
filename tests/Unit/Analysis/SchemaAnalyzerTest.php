<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Tests\Unit\Analysis;

use Juf\DoctrineImportBundle\Analysis\ClassNameResolver;
use Juf\DoctrineImportBundle\Analysis\EntityAnalyzer;
use Juf\DoctrineImportBundle\Analysis\FieldAnalyzer;
use Juf\DoctrineImportBundle\Analysis\MappedType;
use Juf\DoctrineImportBundle\Analysis\Naming\ClassName;
use Juf\DoctrineImportBundle\Analysis\Naming\DefaultNamingStrategy;
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
use Juf\DoctrineImportBundle\Definition\ManyToOneDefinition;
use Juf\DoctrineImportBundle\Definition\OneToManyDefinition;
use Juf\DoctrineImportBundle\Diagnostic\Diagnostic;
use Juf\DoctrineImportBundle\Diagnostic\DiagnosticCollector;
use Juf\DoctrineImportBundle\Diagnostic\Severity;
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

#[CoversClass(SchemaAnalyzer::class)]
#[CoversClass(KnownTablePrefixes::class)]
#[UsesClass(EntityAnalyzer::class)]
#[UsesClass(RelationAnalyzer::class)]
#[UsesClass(RelationSet::class)]
#[UsesClass(EntityDefinition::class)]
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
final class SchemaAnalyzerTest extends TestCase
{
    use DefinitionAssertions;

    #[Test]
    public function it_returns_entities_sorted_by_class_name_with_reading_and_analysis_diagnostics(): void
    {
        $readingWarning = new Diagnostic(Severity::Warning, 'table.outside_search_path', 'Skipped.', 'archive.post');
        $schema = SchemaBuilder::schema([
            SchemaBuilder::table('author', [SchemaBuilder::id()]),
            SchemaBuilder::table('book', [SchemaBuilder::id(), SchemaBuilder::column('author_id')], foreignKeys: [SchemaBuilder::foreignKey(['author_id'], 'author')]),
            SchemaBuilder::table('a_log', [SchemaBuilder::column('message', 'text')], primaryKey: []),
        ], [$readingWarning]);

        $model = (new SchemaAnalyzer())->analyze($schema);

        self::assertSame(['Author', 'Book'], self::classNames($model));
        self::assertSame('author', self::onlyManyToOne(self::entity($model, 'Book'))->propertyName);
        self::assertSame([
            ['warning', 'table.outside_search_path', 'archive.post', null],
            ['error', 'table.no_primary_key', 'a_log', null],
        ], self::diagnostics($model));
    }

    #[Test]
    public function it_suggests_a_known_table_prefix_shared_by_every_table(): void
    {
        $schema = SchemaBuilder::schema([
            SchemaBuilder::table('wp_posts', [SchemaBuilder::id()]),
            SchemaBuilder::table('wp_users', [SchemaBuilder::id()]),
        ]);

        $withoutPrefix = (new SchemaAnalyzer())->analyze($schema);
        $withPrefix = (new SchemaAnalyzer(new DefaultNamingStrategy(tablePrefixes: ['wp_'])))->analyze($schema);

        self::assertSame([['info', 'naming.table_prefix_detected', null, null]], self::diagnostics($withoutPrefix));
        $hint = $withoutPrefix->diagnostics[0] ?? null;
        self::assertNotNull($hint);
        self::assertStringContainsString('"wp_"', $hint->message);
        self::assertSame([], self::diagnostics($withPrefix));
    }

    #[Test]
    public function it_sorts_entities_by_class_name_rather_than_table_name(): void
    {
        $schema = SchemaBuilder::schema([
            SchemaBuilder::table('users', [SchemaBuilder::id()]),
            SchemaBuilder::table('wp_articles', [SchemaBuilder::id()]),
        ]);

        $model = (new SchemaAnalyzer(new DefaultNamingStrategy(tablePrefixes: ['wp_'])))->analyze($schema);

        self::assertSame(['Article', 'User'], self::classNames($model));
    }

    #[Test]
    public function it_suggests_no_table_prefix_for_an_empty_schema(): void
    {
        $model = (new SchemaAnalyzer())->analyze(SchemaBuilder::schema([]));

        self::assertSame([], self::diagnostics($model));
    }
}
