<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Tests\Unit\Analysis;

use Juf\DoctrineImportBundle\Definition\EntityDefinition;
use Juf\DoctrineImportBundle\Definition\FieldDefinition;
use Juf\DoctrineImportBundle\Definition\GenerationModel;
use Juf\DoctrineImportBundle\Definition\ManyToOneDefinition;
use Juf\DoctrineImportBundle\Diagnostic\Diagnostic;
use PHPUnit\Framework\Assert;

/**
 * Lookups shared by the analysis tests.
 */
trait DefinitionAssertions
{
    private static function entity(GenerationModel $model, string $className): EntityDefinition
    {
        foreach ($model->entities as $entity) {
            if ($className === $entity->className) {
                return $entity;
            }
        }

        Assert::fail(\sprintf('Entity "%s" was not generated.', $className));
    }

    private static function field(EntityDefinition $entity, string $propertyName): FieldDefinition
    {
        foreach ($entity->fields as $field) {
            if ($propertyName === $field->propertyName) {
                return $field;
            }
        }

        Assert::fail(\sprintf('Field "%s::$%s" was not generated.', $entity->className, $propertyName));
    }

    private static function onlyManyToOne(EntityDefinition $entity): ManyToOneDefinition
    {
        Assert::assertCount(1, $entity->manyToOne);

        return $entity->manyToOne[0];
    }

    /**
     * @return list<string>
     */
    private static function propertyNames(EntityDefinition $entity): array
    {
        return array_map(static fn (FieldDefinition $field): string => $field->propertyName, $entity->fields);
    }

    /**
     * @return list<string>
     */
    private static function classNames(GenerationModel $model): array
    {
        return array_map(static fn (EntityDefinition $entity): string => $entity->className, $model->entities);
    }

    /**
     * @return list<array{string, string, ?string, ?string}> severity, code, table, column
     */
    private static function diagnostics(GenerationModel $model): array
    {
        return array_map(
            static fn (Diagnostic $diagnostic): array => [$diagnostic->severity->value, $diagnostic->code, $diagnostic->table, $diagnostic->column],
            $model->diagnostics,
        );
    }
}
