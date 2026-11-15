<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Rendering;

use Juf\DoctrineImportBundle\Definition\EntityDefinition;
use Juf\DoctrineImportBundle\Definition\FieldDefinition;
use Juf\DoctrineImportBundle\Definition\IdGeneration;
use Juf\DoctrineImportBundle\Definition\ManyToOneDefinition;
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

        $class = $this->classBuilder->build($entity, $namespace);
        self::mapClass($class, $entity);
        foreach ($entity->fields as $field) {
            self::mapField($class, $field);
        }

        foreach ($entity->manyToOne as $association) {
            self::mapManyToOne($class, $association);
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

        if (IdGeneration::Identity === $field->idGeneration) {
            $property->addAttribute(self::ORM.'\GeneratedValue', ['strategy' => 'IDENTITY']);
        }

        $property->addAttribute(self::ORM.'\Column', self::columnArguments($field, self::defaultExpression($class, $field)));
    }

    /**
     * @return array<string, mixed>
     */
    private static function columnArguments(FieldDefinition $field, ?Literal $defaultExpression): array
    {
        $options = $field->options;
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
            'options' => [] === $columnOptions ? null : $columnOptions,
        ]);
    }

    private static function mapManyToOne(ClassType $class, ManyToOneDefinition $association): void
    {
        $property = $class->getProperty($association->propertyName);
        $property->addAttribute(self::ORM.'\ManyToOne', self::withoutNulls([
            'targetEntity' => new Literal($association->targetEntity.'::class'),
            'inversedBy' => $association->inversedBy,
        ]));
        $property->addAttribute(self::ORM.'\JoinColumn', self::withoutNulls([
            'name' => $association->joinColumn,
            'referencedColumnName' => $association->referencedColumn,
            'nullable' => $association->nullable,
            'onDelete' => $association->onDelete,
        ]));
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
