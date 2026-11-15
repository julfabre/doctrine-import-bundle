<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class JufDoctrineImportBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->import('../config/definition.php');
    }

    /**
     * @param array<array-key, mixed> $config
     *
     * @SuppressWarnings("PHPMD.UnusedFormalParameter") Signature imposed by AbstractBundle.
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.php');
        $container->parameters()->set('juf_doctrine_import.defaults', $config);
    }
}
