<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Juf\DoctrineImportBundle\Application\GenerateEntities;
use Juf\DoctrineImportBundle\Command\GenerateEntitiesFromDatabaseCommand;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('.juf_doctrine_import.generate_entities', GenerateEntities::class);

    $services->set('juf_doctrine_import.command.generate_entities_from_database', GenerateEntitiesFromDatabaseCommand::class)
        ->private()
        ->args([
            service('doctrine'),
            service('.juf_doctrine_import.generate_entities'),
            param('juf_doctrine_import.defaults'),
            param('kernel.project_dir'),
        ])
        ->tag('console.command', ['command' => 'generate:entities:from-database'])
    ;
};
