<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Command;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Juf\DoctrineImportBundle\Application\GenerateEntities;
use Juf\DoctrineImportBundle\Schema\Dbal\DbalSchemaReader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @phpstan-import-type Defaults from GenerationRequestFactory
 */
#[AsCommand(
    name: 'generate:entities:from-database',
    description: 'Generate Doctrine ORM entities and repositories from an existing database.',
)]
final class GenerateEntitiesFromDatabaseCommand extends Command
{
    /**
     * @param Defaults $defaults the bundle configuration, overridden by the command line options
     */
    public function __construct(
        private readonly ManagerRegistry $registry,
        private readonly GenerateEntities $generateEntities,
        private readonly array $defaults,
        private readonly string $projectDirectory,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('em', null, InputOption::VALUE_REQUIRED, 'Entity manager whose connection is read')
            ->addOption('table', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Glob pattern of the tables to generate (repeatable)')
            ->addOption('exclude', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Glob pattern of the tables to ignore (repeatable)')
            ->addOption('table-prefix', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Prefix removed from table names before naming classes (repeatable)')
            ->addOption('namespace', null, InputOption::VALUE_REQUIRED, 'Namespace of the entities')
            ->addOption('dir', null, InputOption::VALUE_REQUIRED, 'Directory of the entities')
            ->addOption('repository-namespace', null, InputOption::VALUE_REQUIRED, 'Namespace of the repositories')
            ->addOption('repository-dir', null, InputOption::VALUE_REQUIRED, 'Directory of the repositories')
            ->addOption('enum-namespace', null, InputOption::VALUE_REQUIRED, 'Namespace of the enums generated for ENUM columns')
            ->addOption('enum-dir', null, InputOption::VALUE_REQUIRED, 'Directory of the enums')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'List the files without writing them')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Overwrite existing files: manual changes are lost')
            ->setHelp(<<<'HELP'
                Reads the tables of the database and writes one entity and one repository per table.
                Existing files are kept unless <info>--force</info> is given. Command line options override the
                <info>juf_doctrine_import</info> configuration; repeatable options replace the configured lists.
                The command fails when a table or a column could not be generated.
                HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $requests = new GenerationRequestFactory($this->defaults);
        $request = $requests->fromInput($input);
        $report = $this->generateEntities->generate(new DbalSchemaReader($this->connection($requests->entityManager($input))), $request);

        (new GenerationReportPrinter($this->projectDirectory))->print(new SymfonyStyle($input, $output), $report, $request->write->dryRun);

        return $report->hasErrors() ? Command::FAILURE : Command::SUCCESS;
    }

    private function connection(?string $name): Connection
    {
        $manager = $this->registry->getManager($name);
        if (!$manager instanceof EntityManagerInterface) {
            throw InvalidEntityManagerException::notAnOrmManager($name, $manager::class);
        }

        return $manager->getConnection();
    }
}
