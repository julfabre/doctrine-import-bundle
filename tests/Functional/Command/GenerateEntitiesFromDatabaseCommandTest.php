<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Tests\Functional\Command;

use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
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
use Juf\DoctrineImportBundle\Command\GenerateEntitiesFromDatabaseCommand;
use Juf\DoctrineImportBundle\Command\GenerationReportPrinter;
use Juf\DoctrineImportBundle\Command\GenerationRequestFactory;
use Juf\DoctrineImportBundle\Command\InvalidEntityManagerException;
use Juf\DoctrineImportBundle\Definition\ColumnOptions;
use Juf\DoctrineImportBundle\Definition\EntityDefinition;
use Juf\DoctrineImportBundle\Definition\FieldDefinition;
use Juf\DoctrineImportBundle\Definition\GenerationModel;
use Juf\DoctrineImportBundle\Definition\IndexDefinition;
use Juf\DoctrineImportBundle\Definition\ManyToOneDefinition;
use Juf\DoctrineImportBundle\Definition\OneToManyDefinition;
use Juf\DoctrineImportBundle\Diagnostic\Diagnostic;
use Juf\DoctrineImportBundle\Diagnostic\DiagnosticCollector;
use Juf\DoctrineImportBundle\JufDoctrineImportBundle;
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
use Juf\DoctrineImportBundle\Writer\FileWriter;
use Juf\DoctrineImportBundle\Writer\OutputLocation;
use Juf\DoctrineImportBundle\Writer\WriteOptions;
use Juf\DoctrineImportBundle\Writer\WrittenFile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LazyCommand;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;

#[CoversClass(GenerateEntitiesFromDatabaseCommand::class)]
#[UsesClass(JufDoctrineImportBundle::class)]
#[CoversClass(GenerationReportPrinter::class)]
#[CoversClass(GenerationRequestFactory::class)]
#[CoversClass(InvalidEntityManagerException::class)]
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
#[UsesClass(GenerateEntities::class)]
#[UsesClass(GenerationReport::class)]
#[UsesClass(GenerationRequest::class)]
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
final class GenerateEntitiesFromDatabaseCommandTest extends KernelTestCase
{
    private const COMMAND_NAME = 'generate:entities:from-database';

    private string $outputDirectory;

    private KernelInterface $testKernel;

    protected function setUp(): void
    {
        $this->testKernel = self::bootKernel();
        $this->outputDirectory = $this->testKernel->getCacheDir().'/generated';
        (new Filesystem())->remove($this->outputDirectory);
    }

    #[Test]
    public function it_is_available_in_the_console_application(): void
    {
        $command = $this->findCommand();

        // The command is registered lazily: the console exposes a wrapper until it runs.
        self::assertInstanceOf(LazyCommand::class, $command);
        self::assertInstanceOf(GenerateEntitiesFromDatabaseCommand::class, $command->getCommand());
    }

    #[Test]
    public function it_describes_itself_for_the_command_list(): void
    {
        self::assertSame('Generate Doctrine ORM entities and repositories from an existing database.', $this->findCommand()->getDescription());
    }

    #[Test]
    public function it_generates_entities_and_repositories_into_the_configured_directories(): void
    {
        $this->givenBlogTables($this->connection());

        $tester = $this->executeCommand([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertFileExists($this->outputDirectory.'/Entity/Author.php');
        self::assertFileExists($this->outputDirectory.'/Entity/Post.php');
        self::assertFileExists($this->outputDirectory.'/Repository/PostRepository.php');
        self::assertStringContainsString('namespace App\Entity;', (string) file_get_contents($this->outputDirectory.'/Entity/Post.php'));
        self::assertMatchesRegularExpression('#Entity/Post\.php\s+created#', $tester->getDisplay());
        self::assertStringContainsString('Generation complete: 0 error(s), 0 warning(s).', $tester->getDisplay());
    }

    #[Test]
    public function it_lets_command_line_options_override_the_configuration(): void
    {
        $connection = $this->connection();
        $connection->executeStatement('CREATE TABLE app_customer (id INTEGER NOT NULL PRIMARY KEY)');
        $connection->executeStatement('CREATE TABLE app_customer_archive (id INTEGER NOT NULL PRIMARY KEY)');
        $connection->executeStatement('CREATE TABLE other (id INTEGER NOT NULL PRIMARY KEY)');

        $tester = $this->executeCommand([
            '--table' => ['app_*'],
            '--exclude' => ['*_archive'],
            '--table-prefix' => ['app_'],
            '--namespace' => 'Shop\Domain',
            '--dir' => $this->outputDirectory.'/Domain',
            '--repository-namespace' => 'Shop\Persistence',
            '--repository-dir' => $this->outputDirectory.'/Persistence',
        ]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame(['Customer.php'], $this->fileNames($this->outputDirectory.'/Domain'));
        self::assertSame(['CustomerRepository.php'], $this->fileNames($this->outputDirectory.'/Persistence'));
        self::assertStringContainsString('namespace Shop\Domain;', (string) file_get_contents($this->outputDirectory.'/Domain/Customer.php'));
    }

    #[Test]
    public function it_writes_nothing_and_lists_the_files_in_dry_run(): void
    {
        $this->givenBlogTables($this->connection());

        $tester = $this->executeCommand(['--dry-run' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertDirectoryDoesNotExist($this->outputDirectory);
        self::assertMatchesRegularExpression('#Entity/Post\.php\s+to create#', $tester->getDisplay());
        self::assertStringContainsString('Dry run: no file was written.', $tester->getDisplay());
    }

    #[Test]
    public function it_keeps_existing_files_unless_forced(): void
    {
        $this->givenBlogTables($this->connection());
        $this->executeCommand([]);
        file_put_contents($this->outputDirectory.'/Entity/Post.php', "<?php // edited by hand\n");

        $kept = $this->executeCommand([]);
        self::assertSame("<?php // edited by hand\n", file_get_contents($this->outputDirectory.'/Entity/Post.php'));
        self::assertMatchesRegularExpression('#Entity/Post\.php\s+skipped \(exists, use --force\)#', $kept->getDisplay());

        $forced = $this->executeCommand(['--force' => true]);
        self::assertStringContainsString('class Post', (string) file_get_contents($this->outputDirectory.'/Entity/Post.php'));
        self::assertMatchesRegularExpression('#Entity/Post\.php\s+overwritten#', $forced->getDisplay());
    }

    #[Test]
    public function it_reports_diagnostics_grouped_by_severity(): void
    {
        $connection = $this->connection();
        $connection->executeStatement("CREATE TABLE post (id INTEGER NOT NULL PRIMARY KEY, created_at DATETIME DEFAULT (datetime('now')))");
        $connection->executeStatement('CREATE TABLE audit_log (message TEXT)');

        $display = $this->executeCommand([])->getDisplay();

        $error = strpos($display, 'table.no_primary_key');
        $warning = strpos($display, 'column.default_expression');
        self::assertNotFalse($error);
        self::assertNotFalse($warning);
        self::assertLessThan($warning, $error, 'Errors are listed before warnings.');
    }

    #[Test]
    public function it_fails_when_a_table_or_column_is_skipped(): void
    {
        $this->connection()->executeStatement('CREATE TABLE audit_log (message TEXT)');

        $tester = $this->executeCommand([]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('Generation incomplete: 1 error(s), 0 warning(s).', $tester->getDisplay());
    }

    #[Test]
    public function it_reads_the_connection_of_the_entity_manager_given_with_em(): void
    {
        $this->connection('legacy')->executeStatement('CREATE TABLE invoice (id INTEGER NOT NULL PRIMARY KEY)');

        $tester = $this->executeCommand(['--em' => 'legacy']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame(['Invoice.php'], $this->fileNames($this->outputDirectory.'/Entity'));
    }

    #[Test]
    public function it_rejects_a_manager_that_is_not_an_orm_entity_manager(): void
    {
        $registry = self::createStub(ManagerRegistry::class);
        $registry->method('getManager')->willReturn(self::createStub(ObjectManager::class));
        $command = new GenerateEntitiesFromDatabaseCommand($registry, new GenerateEntities(), [
            'entity_manager' => null,
            'table_prefixes' => [],
            'tables' => ['include' => [], 'exclude' => []],
            'entity' => ['namespace' => 'App\\Entity', 'dir' => $this->outputDirectory.'/Entity'],
            'repository' => ['namespace' => 'App\\Repository', 'dir' => $this->outputDirectory.'/Repository'],
        ], $this->outputDirectory);

        $this->expectException(InvalidEntityManagerException::class);
        $this->expectExceptionMessageMatches('/^The manager "odm" is a ".+", not a Doctrine ORM entity manager\.$/');

        (new CommandTester($command))->execute(['--em' => 'odm']);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function executeCommand(array $options): CommandTester
    {
        $tester = new CommandTester($this->findCommand());
        $tester->execute($options);

        return $tester;
    }

    private function findCommand(): Command
    {
        return (new Application($this->testKernel))->find(self::COMMAND_NAME);
    }

    private function connection(string $name = 'default'): Connection
    {
        $registry = self::getContainer()->get('doctrine');
        self::assertInstanceOf(ManagerRegistry::class, $registry);
        $connection = $registry->getConnection($name);
        self::assertInstanceOf(Connection::class, $connection);

        return $connection;
    }

    private function givenBlogTables(Connection $connection): void
    {
        $connection->executeStatement('CREATE TABLE author (id INTEGER NOT NULL PRIMARY KEY, name VARCHAR(100) NOT NULL)');
        $connection->executeStatement('CREATE TABLE post (id INTEGER NOT NULL PRIMARY KEY, author_id INTEGER NOT NULL REFERENCES author (id), title VARCHAR(255) NOT NULL)');
    }

    /**
     * @return list<string>
     */
    private function fileNames(string $directory): array
    {
        $files = scandir($directory);
        self::assertIsArray($files);

        return array_values(array_diff($files, ['.', '..']));
    }
}
