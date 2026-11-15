<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Tests\Integration\DependencyInjection;

use Juf\DoctrineImportBundle\JufDoctrineImportBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

#[CoversClass(JufDoctrineImportBundle::class)]
final class ServiceDefinitionTest extends TestCase
{
    private const COMMAND_SERVICE_ID = 'juf_doctrine_import.command.generate_entities_from_database';

    #[Test]
    public function it_registers_the_command_as_a_private_service_prefixed_with_the_bundle_alias(): void
    {
        $container = $this->loadBundleServices();

        self::assertTrue($container->hasDefinition(self::COMMAND_SERVICE_ID));
        self::assertFalse($container->getDefinition(self::COMMAND_SERVICE_ID)->isPublic());
    }

    #[Test]
    public function it_declares_the_command_without_autowiring_nor_autoconfiguration(): void
    {
        $definition = $this->commandDefinition();

        self::assertFalse($definition->isAutowired());
        self::assertFalse($definition->isAutoconfigured());
    }

    #[Test]
    public function it_tags_the_command_for_lazy_loading_under_its_name(): void
    {
        $definition = $this->commandDefinition();

        self::assertSame([['command' => 'generate:entities:from-database']], $definition->getTag('console.command'));
    }

    #[Test]
    public function it_passes_the_processed_configuration_to_the_command(): void
    {
        $container = $this->loadBundleServices();

        $defaults = $container->getParameter('juf_doctrine_import.defaults');
        self::assertIsArray($defaults);
        self::assertSame(['namespace' => 'App\\Entity', 'dir' => '%kernel.project_dir%/src/Entity'], $defaults['entity'] ?? null);
    }

    private function commandDefinition(): Definition
    {
        return $this->loadBundleServices()->getDefinition(self::COMMAND_SERVICE_ID);
    }

    private function loadBundleServices(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.build_dir', sys_get_temp_dir());

        $extension = (new JufDoctrineImportBundle())->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);
        $extension->load([], $container);

        return $container;
    }
}
