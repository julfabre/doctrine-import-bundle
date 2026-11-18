<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Analysis;

use Juf\DoctrineImportBundle\Analysis\Naming\DefaultNamingStrategy;
use Juf\DoctrineImportBundle\Analysis\Naming\NamingStrategyInterface;
use Juf\DoctrineImportBundle\Definition\EntityDefinition;
use Juf\DoctrineImportBundle\Definition\FieldDefinition;
use Juf\DoctrineImportBundle\Definition\GenerationModel;
use Juf\DoctrineImportBundle\Definition\IndexDefinition;
use Juf\DoctrineImportBundle\Diagnostic\DiagnosticCollector;
use Juf\DoctrineImportBundle\Diagnostic\Severity;
use Juf\DoctrineImportBundle\Schema\IndexMetadata;
use Juf\DoctrineImportBundle\Schema\SchemaMetadata;
use Juf\DoctrineImportBundle\Schema\TableMetadata;

/**
 * Turns each table into an entity with scalar fields. Relations are added by {@see RelationAnalyzer}.
 */
final readonly class EntityAnalyzer
{
    private ClassNameResolver $classNames;

    private FieldAnalyzer $fieldAnalyzer;

    public function __construct(NamingStrategyInterface $naming = new DefaultNamingStrategy(), TypeMapper $types = new TypeMapper())
    {
        $this->classNames = new ClassNameResolver($naming);
        $this->fieldAnalyzer = new FieldAnalyzer($naming, $types);
    }

    public function analyze(SchemaMetadata $schema): GenerationModel
    {
        $diagnostics = new DiagnosticCollector();
        $entities = [];

        $enumClassNames = [];

        foreach ($schema->tables as $table) {
            $className = $this->classNames->resolve($table, array_keys($entities), $diagnostics);
            if (null !== $className) {
                $entities[$className] = $this->entity($table, $className, $enumClassNames, $diagnostics);
                $enumClassNames = [...$enumClassNames, ...$this->enumClassNames($entities[$className])];
            }
        }

        return new GenerationModel(array_values($entities), $diagnostics->all());
    }

    /**
     * @param list<string> $enumClassNames enums already generated for other entities
     */
    private function entity(TableMetadata $table, string $className, array $enumClassNames, DiagnosticCollector $diagnostics): EntityDefinition
    {
        if (\count($table->primaryKey) > 1) {
            $diagnostics->report(Severity::Info, 'table.composite_primary_key', \sprintf('Table "%s" has a composite primary key, which Doctrine supports but advises against.', $table->name), $table->name);
        }

        $fields = [];
        foreach ($table->columns as $column) {
            $field = $this->fieldAnalyzer->analyze($table, $column, $className, $enumClassNames, $diagnostics);
            if ($field instanceof FieldDefinition) {
                $fields[] = $field;
            }
        }

        return new EntityDefinition(
            $className,
            $table->name,
            $fields,
            [],
            [],
            array_map(static fn (IndexMetadata $index): IndexDefinition => new IndexDefinition($index->name, $index->columns, $index->unique, $index->flags), $table->indexes),
            $table->comment,
            primaryKey: $table->primaryKey,
        );
    }

    /**
     * @return list<string>
     */
    private function enumClassNames(EntityDefinition $entity): array
    {
        $names = [];
        foreach ($entity->fields as $field) {
            $enumClassName = $field->enum?->className;
            if (null !== $enumClassName) {
                $names[] = $enumClassName;
            }
        }

        return $names;
    }
}
