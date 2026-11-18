<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Tests\Integration\DependencyInjection;

use Juf\DoctrineImportBundle\JufDoctrineImportBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;

#[CoversClass(JufDoctrineImportBundle::class)]
final class ConfigurationTest extends TestCase
{
    #[Test]
    public function it_provides_defaults_for_every_option(): void
    {
        self::assertSame([
            'entity_manager' => null,
            'table_prefixes' => [],
            'tables' => ['include' => [], 'exclude' => []],
            'entity' => ['namespace' => 'App\Entity', 'dir' => '%kernel.project_dir%/src/Entity'],
            'repository' => ['namespace' => 'App\Repository', 'dir' => '%kernel.project_dir%/src/Repository'],
            'enum' => ['namespace' => 'App\Enum', 'dir' => '%kernel.project_dir%/src/Enum'],
        ], $this->process([]));
    }

    #[Test]
    public function it_accepts_a_complete_configuration(): void
    {
        $configuration = [
            'entity_manager' => 'legacy',
            'table_prefixes' => ['wp_', 'tbl_'],
            'tables' => ['include' => ['blog_*'], 'exclude' => ['*_archive']],
            'entity' => ['namespace' => 'Blog\Domain', 'dir' => '%kernel.project_dir%/src/Domain'],
            'repository' => ['namespace' => 'Blog\Persistence', 'dir' => '%kernel.project_dir%/src/Persistence'],
            'enum' => ['namespace' => 'Blog\Kind', 'dir' => '%kernel.project_dir%/src/Kind'],
        ];

        self::assertSame($configuration, $this->process($configuration));
    }

    /**
     * @param array<string, mixed> $configuration
     *
     * @return array<mixed>
     */
    private function process(array $configuration): array
    {
        $extension = (new JufDoctrineImportBundle())->getContainerExtension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);
        $definition = $extension->getConfiguration([], new ContainerBuilder());
        self::assertInstanceOf(ConfigurationInterface::class, $definition);

        return (new Processor())->processConfiguration($definition, [$configuration]);
    }
}
