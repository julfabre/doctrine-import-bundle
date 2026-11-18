<?php

declare(strict_types=1);

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;

$listNode = static function (NodeBuilder $parent, string $name, string $info): void {
    $parent->arrayNode($name)->info($info)->scalarPrototype();
};

$location = static function (NodeBuilder $parent, string $name, string $namespace, string $directory): void {
    $children = $parent->arrayNode($name)->addDefaultsIfNotSet()->children();
    $children->scalarNode('namespace')->defaultValue($namespace)->cannotBeEmpty();
    $children->scalarNode('dir')->defaultValue($directory)->cannotBeEmpty();
};

return static function (DefinitionConfigurator $definition) use ($listNode, $location): void {
    $root = $definition->rootNode();
    if (!$root instanceof ArrayNodeDefinition) {
        throw new LogicException('The root node of the bundle configuration must be an array node.');
    }

    $children = $root->children();
    $children->scalarNode('entity_manager')
        ->info('Entity manager whose connection is read. Defaults to the default entity manager.')
        ->defaultNull();
    $listNode($children, 'table_prefixes', 'Prefixes removed from table names before naming classes, e.g. ["wp_"].');

    $tables = $children->arrayNode('tables')->addDefaultsIfNotSet()->children();
    $listNode($tables, 'include', 'Glob patterns of the tables to generate. Empty means every table.');
    $listNode($tables, 'exclude', 'Glob patterns of the tables to ignore. An exclusion wins over an inclusion.');

    $location($children, 'entity', 'App\\Entity', '%kernel.project_dir%/src/Entity');
    $location($children, 'repository', 'App\\Repository', '%kernel.project_dir%/src/Repository');
    $location($children, 'enum', 'App\\Enum', '%kernel.project_dir%/src/Enum');
};
