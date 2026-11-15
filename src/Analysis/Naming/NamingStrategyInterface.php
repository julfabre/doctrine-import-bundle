<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Analysis\Naming;

/**
 * Turns database names into PHP names.
 */
interface NamingStrategyInterface
{
    /**
     * @throws InvalidIdentifierException
     */
    public function classNameForTable(string $table): ClassName;

    /**
     * Same as {@see classNameForTable()} without singularization, used to resolve class name collisions.
     *
     * @throws InvalidIdentifierException
     */
    public function unsingularizedClassNameForTable(string $table): ClassName;

    public function propertyNameForColumn(string $column): string;

    public function associationNameForColumn(string $column): string;

    public function collectionNameForEntity(string $className): string;
}
