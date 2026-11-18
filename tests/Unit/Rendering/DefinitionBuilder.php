<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Tests\Unit\Rendering;

use Juf\DoctrineImportBundle\Definition\ColumnOptions;
use Juf\DoctrineImportBundle\Definition\EntityDefinition;
use Juf\DoctrineImportBundle\Definition\EnumDefinition;
use Juf\DoctrineImportBundle\Definition\FieldDefinition;
use Juf\DoctrineImportBundle\Definition\IdGeneration;
use Juf\DoctrineImportBundle\Definition\IndexDefinition;
use Juf\DoctrineImportBundle\Definition\ManyToManyDefinition;
use Juf\DoctrineImportBundle\Definition\ManyToOneDefinition;
use Juf\DoctrineImportBundle\Definition\OneToManyDefinition;
use Juf\DoctrineImportBundle\Definition\OneToOneDefinition;

/**
 * Builds in-memory entity definitions for the rendering tests.
 */
final class DefinitionBuilder
{
    /**
     * @param list<FieldDefinition>      $fields
     * @param list<ManyToOneDefinition>  $manyToOne
     * @param list<OneToManyDefinition>  $oneToMany
     * @param list<IndexDefinition>      $indexes
     * @param list<OneToOneDefinition>   $oneToOne
     * @param list<ManyToManyDefinition> $manyToMany
     * @param list<string>|null          $primaryKey defaults to the columns of the identifier fields
     */
    public static function entity(
        string $className,
        array $fields = [],
        array $manyToOne = [],
        array $oneToMany = [],
        array $indexes = [],
        ?string $tableName = null,
        ?string $comment = null,
        array $oneToOne = [],
        array $manyToMany = [],
        ?array $primaryKey = null,
    ): EntityDefinition {
        $fields = [] === $fields ? [self::identityId()] : $fields;

        return new EntityDefinition(
            $className,
            $tableName ?? strtolower($className),
            $fields,
            $manyToOne,
            $oneToMany,
            $indexes,
            $comment,
            $oneToOne,
            $manyToMany,
            $primaryKey ?? array_values(array_map(
                static fn (FieldDefinition $field): string => $field->columnName,
                array_filter($fields, static fn (FieldDefinition $field): bool => $field->isId()),
            )),
        );
    }

    public static function identityId(): FieldDefinition
    {
        return self::field('id', 'id', 'integer', 'int', idGeneration: IdGeneration::Identity);
    }

    public static function field(
        string $propertyName,
        string $columnName,
        string $doctrineType = 'string',
        string $phpType = 'string',
        bool $nullable = false,
        ?IdGeneration $idGeneration = null,
        ?int $length = null,
        ?int $precision = null,
        int $scale = 0,
        bool $unsigned = false,
        bool $fixed = false,
        ?string $default = null,
        ?string $comment = null,
        bool $generated = false,
        ?string $sequenceName = null,
        ?EnumDefinition $enum = null,
    ): FieldDefinition {
        return new FieldDefinition(
            $propertyName,
            $columnName,
            $doctrineType,
            $phpType,
            $nullable,
            $idGeneration,
            new ColumnOptions($length, $precision, $scale, $unsigned, $fixed, $default, $comment),
            $generated,
            $sequenceName,
            $enum,
        );
    }
}
