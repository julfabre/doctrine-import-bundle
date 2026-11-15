<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'generate:entities:from-database',
    description: 'Generate Doctrine ORM entities and repositories from an existing database.',
)]
final class GenerateEntitiesFromDatabaseCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        (new SymfonyStyle($input, $output))->error('Entity generation is not implemented yet.');

        return Command::FAILURE;
    }
}
