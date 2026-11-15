<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Tests\Unit\Application;

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
use Juf\DoctrineImportBundle\Application\GenerateEntities;
use Juf\DoctrineImportBundle\Application\GenerationReport;
use Juf\DoctrineImportBundle\Application\GenerationRequest;
use Juf\DoctrineImportBundle\Command\GenerationReportPrinter;
use Juf\DoctrineImportBundle\Command\GenerationRequestFactory;
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
use Juf\DoctrineImportBundle\Rendering\AttributeMappingStrategy;
use Juf\DoctrineImportBundle\Rendering\CodeGenerator;
use Juf\DoctrineImportBundle\Rendering\EntityClassBuilder;
use Juf\DoctrineImportBundle\Rendering\GeneratedFile;
use Juf\DoctrineImportBundle\Rendering\PhpLiteral;
use Juf\DoctrineImportBundle\Rendering\RenderingOptions;
use Juf\DoctrineImportBundle\Rendering\RepositoryClassBuilder;
use Juf\DoctrineImportBundle\Schema\ColumnMetadata;
use Juf\DoctrineImportBundle\Schema\ColumnType;
use Juf\DoctrineImportBundle\Schema\Dbal\DbalSchemaReader;
use Juf\DoctrineImportBundle\Schema\Dbal\PlatformNameResolver;
use Juf\DoctrineImportBundle\Schema\Dbal\TableMetadataFactory;
use Juf\DoctrineImportBundle\Schema\ForeignKeyMetadata;
use Juf\DoctrineImportBundle\Schema\IndexMetadata;
use Juf\DoctrineImportBundle\Schema\SchemaMetadata;
use Juf\DoctrineImportBundle\Schema\TableFilter;
use Juf\DoctrineImportBundle\Schema\TableMetadata;
use Juf\DoctrineImportBundle\Tests\Unit\Analysis\SchemaBuilder;
use Juf\DoctrineImportBundle\Writer\FileStatus;
use Juf\DoctrineImportBundle\Writer\FileWriter;
use Juf\DoctrineImportBundle\Writer\OutputLocation;
use Juf\DoctrineImportBundle\Writer\WriteOptions;
use Juf\DoctrineImportBundle\Writer\WrittenFile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GenerateEntities::class)]
#[CoversClass(GenerationRequest::class)]
#[CoversClass(GenerationReport::class)]
#[UsesClass(ClassNameResolver::class)]
#[UsesClass(EntityAnalyzer::class)]
#[UsesClass(FieldAnalyzer::class)]
#[UsesClass(MappedType::class)]
#[UsesClass(ClassName::class)]
#[UsesClass(DefaultNamingStrategy::class)]
#[UsesClass(KnownTablePrefixes::class)]
#[UsesClass(RelationAnalyzer::class)]
#[UsesClass(RelationSet::class)]
#[UsesClass(SchemaAnalyzer::class)]
#[UsesClass(TypeMapper::class)]
#[UsesClass(GenerationReportPrinter::class)]
#[UsesClass(GenerationRequestFactory::class)]
#[UsesClass(ColumnOptions::class)]
#[UsesClass(EntityDefinition::class)]
#[UsesClass(FieldDefinition::class)]
#[UsesClass(GenerationModel::class)]
#[UsesClass(IndexDefinition::class)]
#[UsesClass(ManyToOneDefinition::class)]
#[UsesClass(OneToManyDefinition::class)]
#[UsesClass(Diagnostic::class)]
#[UsesClass(DiagnosticCollector::class)]
#[UsesClass(AttributeMappingStrategy::class)]
#[UsesClass(CodeGenerator::class)]
#[UsesClass(EntityClassBuilder::class)]
#[UsesClass(GeneratedFile::class)]
#[UsesClass(PhpLiteral::class)]
#[UsesClass(RenderingOptions::class)]
#[UsesClass(RepositoryClassBuilder::class)]
#[UsesClass(ColumnMetadata::class)]
#[UsesClass(ColumnType::class)]
#[UsesClass(DbalSchemaReader::class)]
#[UsesClass(PlatformNameResolver::class)]
#[UsesClass(TableMetadataFactory::class)]
#[UsesClass(ForeignKeyMetadata::class)]
#[UsesClass(IndexMetadata::class)]
#[UsesClass(SchemaMetadata::class)]
#[UsesClass(TableFilter::class)]
#[UsesClass(TableMetadata::class)]
#[UsesClass(FileWriter::class)]
#[UsesClass(OutputLocation::class)]
#[UsesClass(WriteOptions::class)]
#[UsesClass(WrittenFile::class)]
final class GenerateEntitiesTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/juf_doctrine_import/application_'.bin2hex(random_bytes(6));
    }

    #[Test]
    public function it_reads_analyzes_renders_and_writes_the_schema(): void
    {
        $report = $this->generate(new TableFilter(), []);

        self::assertFileExists($this->directory.'/Entity/Author.php');
        self::assertFileExists($this->directory.'/Repository/AuthorRepository.php');
        self::assertStringContainsString('class WpPost', (string) file_get_contents($this->directory.'/Entity/WpPost.php'));
        self::assertCount(4, $report->files);
    }

    #[Test]
    public function it_applies_the_table_filter_and_prefixes_of_the_request(): void
    {
        $report = $this->generate(new TableFilter(include: ['wp_*']), ['wp_']);

        self::assertSame(
            ['App\Entity\Post', 'App\Repository\PostRepository'],
            array_map(static fn (WrittenFile $file): string => $file->className, $report->files),
        );
        self::assertFalse($report->hasErrors(), 'Warnings alone do not make the generation incomplete.');
    }

    #[Test]
    public function it_reports_the_written_files_and_every_diagnostic(): void
    {
        $report = $this->generate(new TableFilter(), []);

        self::assertSame(FileStatus::Created, $report->files[0]->status ?? null);
        self::assertSame(
            ['table.outside_search_path', 'table.no_primary_key', 'relation.missing_target'],
            array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->code, $report->diagnostics),
        );
        self::assertTrue($report->hasErrors());
    }

    /**
     * @param list<string> $tablePrefixes
     */
    private function generate(TableFilter $filter, array $tablePrefixes): GenerationReport
    {
        $reader = new InMemorySchemaReader(SchemaBuilder::schema([
            SchemaBuilder::table('audit_log', [SchemaBuilder::column('message', 'text')], primaryKey: []),
            SchemaBuilder::table('author', [SchemaBuilder::id()]),
            SchemaBuilder::table('wp_posts', [SchemaBuilder::id(), SchemaBuilder::column('editor_id')], foreignKeys: [
                SchemaBuilder::foreignKey(['editor_id'], 'editor'),
            ]),
        ], [new Diagnostic(Severity::Warning, 'table.outside_search_path', 'Skipped.', 'archive.post')]));

        return (new GenerateEntities())->generate($reader, new GenerationRequest(
            $filter,
            $tablePrefixes,
            new RenderingOptions(),
            $this->directory.'/Entity',
            $this->directory.'/Repository',
            new WriteOptions(dryRun: false, force: false),
        ));
    }
}
