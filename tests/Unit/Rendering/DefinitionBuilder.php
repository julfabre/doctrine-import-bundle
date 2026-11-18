<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Tests\Unit\Rendering;

use Juf\DoctrineImportBundle\Definition\ColumnOptions;
use Juf\DoctrineImportBundle\Definition\EntityDefinition;
use Juf\DoctrineImportBundle\Definition\EnumDefinition;
use Juf\DoctrineImportBundle\Definition\FieldDefinition;
use Juf\DoctrineImportBundle\Definition\IdGeneration;
use Juf\DoctrineImportBundle\Definition\IndexDefinition;
use Juf\DoctrineImportBundle\Definition\ManyToOneDefinition;
use Juf\DoctrineImportBundle\Definition\OneToManyDefinition;

/**
 * Builds in-memory entity definitions for the rendering tests.
 */
final class DefinitionBuilder
{
    /**
     * @param list<FieldDefinition>     $fields
     * @param list<ManyToOneDefinition> $manyToOne
     * @param list<OneToManyDefinition> $oneToMany
     * @param list<IndexDefinition>     $indexes
     */
    public static function entity(
        string $className,
        array $fields = [],
        array $manyToOne = [],
        array $oneToMany = [],
        array $indexes = [],
        ?string $tableName = null,
        ?string $comment = null,
    ): EntityDefinition {
        return new EntityDefinition(
            $className,
            $tableName ?? strtolower($className),
            $className.'Repository',
            [] === $fields ? [self::identityId()] : $fields,
            $manyToOne,
            $oneToMany,
            $indexes,
            $comment,
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
