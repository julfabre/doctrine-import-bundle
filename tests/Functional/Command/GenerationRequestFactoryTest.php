<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Tests\Functional\Command;

use Juf\DoctrineImportBundle\Application\GenerateEntities;
use Juf\DoctrineImportBundle\Application\GenerationRequest;
use Juf\DoctrineImportBundle\Command\GenerateEntitiesFromDatabaseCommand;
use Juf\DoctrineImportBundle\Command\GenerationRequestFactory;
use Juf\DoctrineImportBundle\JufDoctrineImportBundle;
use Juf\DoctrineImportBundle\Rendering\AttributeMappingStrategy;
use Juf\DoctrineImportBundle\Rendering\CodeGenerator;
use Juf\DoctrineImportBundle\Rendering\EntityClassBuilder;
use Juf\DoctrineImportBundle\Rendering\RenderingOptions;
use Juf\DoctrineImportBundle\Schema\TableFilter;
use Juf\DoctrineImportBundle\Writer\WriteOptions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;

/**
 * Reads the options against the real definition of the command, so that an option renamed on one side fails here.
 */
#[CoversClass(GenerationRequestFactory::class)]
#[UsesClass(GenerateEntitiesFromDatabaseCommand::class)]
#[UsesClass(JufDoctrineImportBundle::class)]
#[UsesClass(GenerationRequest::class)]
#[UsesClass(RenderingOptions::class)]
#[UsesClass(TableFilter::class)]
#[UsesClass(WriteOptions::class)]
#[UsesClass(GenerateEntities::class)]
#[UsesClass(AttributeMappingStrategy::class)]
#[UsesClass(CodeGenerator::class)]
#[UsesClass(EntityClassBuilder::class)]
final class GenerationRequestFactoryTest extends KernelTestCase
{
    private const DEFAULTS = [
        'entity_manager' => null,
        'table_prefixes' => [],
        'tables' => ['include' => [], 'exclude' => []],
        'entity' => ['namespace' => 'App\Entity', 'dir' => '/app/src/Entity'],
        'repository' => ['namespace' => 'App\Repository', 'dir' => '/app/src/Repository'],
        'enum' => ['namespace' => 'App\Enum', 'dir' => '/app/src/Enum'],
    ];

    #[Test]
    public function it_takes_the_enum_location_from_the_configuration_by_default(): void
    {
        $request = (new GenerationRequestFactory(self::DEFAULTS))->fromInput(new ArrayInput([], $this->commandDefinition()));

        self::assertSame(['App\Enum', '/app/src/Enum'], [$request->rendering->enumNamespace, $request->enumDirectory]);
    }

    #[Test]
    public function it_lets_the_enum_options_override_the_configuration(): void
    {
        $input = new ArrayInput(['--enum-namespace' => 'Shop\Kind', '--enum-dir' => '/app/src/Kind'], $this->commandDefinition());

        $request = (new GenerationRequestFactory(self::DEFAULTS))->fromInput($input);

        self::assertSame(['Shop\Kind', '/app/src/Kind'], [$request->rendering->enumNamespace, $request->enumDirectory]);
    }

    #[Test]
    public function it_lets_every_location_option_override_the_configuration(): void
    {
        $input = new ArrayInput([
            '--namespace' => 'Shop\Domain',
            '--dir' => '/app/src/Domain',
            '--repository-namespace' => 'Shop\Persistence',
            '--repository-dir' => '/app/src/Persistence',
        ], $this->commandDefinition());

        $request = (new GenerationRequestFactory(self::DEFAULTS))->fromInput($input);

        self::assertSame(
            ['Shop\Domain', '/app/src/Domain', 'Shop\Persistence', '/app/src/Persistence'],
            [$request->rendering->entityNamespace, $request->entityDirectory, $request->rendering->repositoryNamespace, $request->repositoryDirectory],
        );
    }

    #[Test]
    public function it_falls_back_to_the_configuration_for_an_empty_option(): void
    {
        $input = new ArrayInput(['--enum-namespace' => '', '--enum-dir' => ''], $this->commandDefinition());

        $request = (new GenerationRequestFactory(self::DEFAULTS))->fromInput($input);

        self::assertSame(['App\Enum', '/app/src/Enum'], [$request->rendering->enumNamespace, $request->enumDirectory]);
    }

    private function commandDefinition(): InputDefinition
    {
        return (new Application(self::bootKernel()))->find('generate:entities:from-database')->getDefinition();
    }
}
