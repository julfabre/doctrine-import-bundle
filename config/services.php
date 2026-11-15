<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Juf\DoctrineImportBundle\Command\GenerateEntitiesFromDatabaseCommand;

return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set('juf_doctrine_import.command.generate_entities_from_database', GenerateEntitiesFromDatabaseCommand::class)
            ->private()
            ->tag('console.command', ['command' => 'generate:entities:from-database'])
    ;
};
