<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Rendering;

use Juf\DoctrineImportBundle\Definition\EntityDefinition;
use Juf\DoctrineImportBundle\Definition\FieldDefinition;
use Juf\DoctrineImportBundle\Definition\IdGeneration;
use Juf\DoctrineImportBundle\Definition\OneToManyDefinition;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Literal;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PsrPrinter;

/**
 * Renders an entity whose mapping is declared with Doctrine attributes.
 */
final readonly class AttributeMappingStrategy implements MappingStrategyInterface
{
    private const ORM = 'Doctrine\ORM\Mapping';

    private const DATE_EXPRESSIONS = [
        'CURRENT_TIMESTAMP' => 'CurrentTimestamp',
        'CURRENT_DATE' => 'CurrentDate',
        'CURRENT_TIME' => 'CurrentTime',
    ];

    public function __construct(
        private EntityClassBuilder $classBuilder = new EntityClassBuilder(),
    ) {
    }

    public function render(EntityDefinition $entity, RenderingOptions $options): array
    {
        $file = new PhpFile();
        $file->setStrictTypes();

        $namespace = $file->addNamespace($options->entityNamespace)
            ->addUse(self::ORM, 'ORM')
            ->addUse($options->repositoryNamespace.'\\'.$entity->repositoryClassName);
        if ([] !== $entity->fields) {
            $namespace->addUse('Doctrine\DBAL\Types\Types');
        }

        $class = $this->classBuilder->build($entity, $namespace, $options);
        self::mapClass($class, $entity);
        foreach ($entity->fields as $field) {
            self::mapField($class, $field);
        }

        foreach ($entity->manyToOne as $association) {
            self::mapToOne($class, 'ManyToOne', $association->propertyName, $association->targetEntity, $association->inversedBy, [
                'name' => $association->joinColumn,
                'referencedColumnName' => $association->referencedColumn,
                'nullable' => $association->nullable,
                'onDelete' => $association->onDelete,
            ]);
        }

        foreach ($entity->oneToOne as $association) {
            self::mapToOne($class, 'OneToOne', $association->propertyName, $association->targetEntity, null, [
                'name' => $association->joinColumn,
                'referencedColumnName' => $association->referencedColumn,
                'nullable' => $association->nullable,
                'onDelete' => $association->onDelete,
            ]);
        }

        foreach ($entity->oneToMany as $collection) {
            self::mapOneToMany($class, $collection);
        }

        return [new GeneratedFile($options->entityNamespace.'\\'.$entity->className, (new PsrPrinter())->printFile($file))];
    }

    private static function mapClass(ClassType $class, EntityDefinition $entity): void
    {
        $class->addAttribute(self::ORM.'\Entity', ['repositoryClass' => new Literal($entity->repositoryClassName.'::class')]);
        $class->addAttribute(self::ORM.'\Table', self::withoutNulls([
            'name' => $entity->tableName,
            'options' => null === $entity->comment ? null : ['comment' => $entity->comment],
        ]));

        foreach ($entity->indexes as $index) {
            $class->addAttribute(self::ORM.($index->unique ? '\UniqueConstraint' : '\Index'), self::withoutNulls([
                'name' => $index->name,
                'columns' => $index->columns,
                'flags' => [] === $index->flags ? null : $index->flags,
            ]));
        }
    }

    private static function mapField(ClassType $class, FieldDefinition $field): void
    {
        $property = $class->getProperty($field->propertyName);
        if ($field->isId()) {
            $property->addAttribute(self::ORM.'\Id');
        }

        if (IdGeneration::Identity === $field->idGeneration || IdGeneration::Sequence === $field->idGeneration) {
            $property->addAttribute(self::ORM.'\GeneratedValue', ['strategy' => $field->idGeneration->value]);
        }

        if (null !== $field->sequenceName) {
            // One value at a time, like the other clients of the sequence: no gap, no collision.
            $property->addAttribute(self::ORM.'\SequenceGenerator', ['sequenceName' => $field->sequenceName, 'allocationSize' => 1]);
        }

        $property->addAttribute(self::ORM.'\Column', self::columnArguments($field, self::defaultExpression($class, $field)));
    }

    /**
     * @return array<string, mixed>
     */
    private static function columnArguments(FieldDefinition $field, ?Literal $defaultExpression): array
    {
        $options = $field->options;
        $enumClass = $field->enum?->className;
        $columnOptions = self::withoutNulls([
            'unsigned' => self::flag($options->unsigned),
            'fixed' => self::flag($options->fixed),
            'default' => $defaultExpression ?? $options->default,
            'comment' => $options->comment,
        ]);

        return self::withoutNulls([
            'name' => $field->columnName,
            'type' => new Literal('Types::'.strtoupper($field->doctrineType)),
            'length' => $options->length,
            'precision' => $options->precision,
            'scale' => null === $options->precision ? null : $options->scale,
            'nullable' => self::flag($field->nullable),
            // Doctrine must never write a generated column, and must read it back after each write.
            'insertable' => $field->generated ? false : null,
            'updatable' => $field->generated ? false : null,
            'enumType' => null === $enumClass ? null : new Literal($enumClass.'::class'),
            'options' => [] === $columnOptions ? null : $columnOptions,
            'generated' => $field->generated ? 'ALWAYS' : null,
        ]);
    }

    /**
     * @param 'ManyToOne'|'OneToOne' $type
     * @param array<string, mixed>   $joinColumn
     */
    private static function mapToOne(ClassType $class, string $type, string $propertyName, string $targetEntity, ?string $inversedBy, array $joinColumn): void
    {
        $property = $class->getProperty($propertyName);
        $property->addAttribute(self::ORM.'\\'.$type, self::withoutNulls([
            'targetEntity' => new Literal($targetEntity.'::class'),
            'inversedBy' => $inversedBy,
        ]));
        $property->addAttribute(self::ORM.'\JoinColumn', self::withoutNulls($joinColumn));
    }

    private static function mapOneToMany(ClassType $class, OneToManyDefinition $collection): void
    {
        $class->getProperty($collection->propertyName)->addAttribute(self::ORM.'\OneToMany', [
            'targetEntity' => new Literal($collection->targetEntity.'::class'),
            'mappedBy' => $collection->mappedBy,
        ]);
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    private static function withoutNulls(array $arguments): array
    {
        return array_filter($arguments, static fn (mixed $value): bool => null !== $value);
    }

    /**
     * Standard date expressions are rendered as DBAL default expressions, so that every platform gets the right SQL.
     */
    private static function defaultExpression(ClassType $class, FieldDefinition $field): ?Literal
    {
        $expression = self::DATE_EXPRESSIONS[$field->options->default ?? ''] ?? null;
        if (null === $expression || '\DateTimeImmutable' !== $field->phpType) {
            return null;
        }

        $class->getNamespace()?->addUse('Doctrine\DBAL\Schema\DefaultExpression\\'.$expression);

        return new Literal('new '.$expression.'()');
    }

    /**
     * Doctrine attributes default to false: only true flags are worth printing.
     */
    private static function flag(bool $value): ?bool
    {
        return $value ? true : null;
    }
}
