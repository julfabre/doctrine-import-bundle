<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Tests\Functional\Command;

use Juf\DoctrineImportBundle\Command\GenerateEntitiesFromDatabaseCommand;
use Juf\DoctrineImportBundle\JufDoctrineImportBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LazyCommand;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(GenerateEntitiesFromDatabaseCommand::class)]
#[UsesClass(JufDoctrineImportBundle::class)]
final class GenerateEntitiesFromDatabaseCommandTest extends KernelTestCase
{
    private const COMMAND_NAME = 'generate:entities:from-database';

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
        $command = $this->findCommand();

        self::assertSame('Generate Doctrine ORM entities and repositories from an existing database.', $command->getDescription());
    }

    #[Test]
    public function it_fails_with_an_explicit_message_until_generation_is_implemented(): void
    {
        $tester = new CommandTester($this->findCommand());

        $exitCode = $tester->execute([]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('Entity generation is not implemented yet.', $tester->getDisplay());
    }

    private function findCommand(): Command
    {
        return (new Application(self::bootKernel()))->find(self::COMMAND_NAME);
    }
}
