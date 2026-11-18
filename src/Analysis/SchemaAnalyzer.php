<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Analysis;

use Juf\DoctrineImportBundle\Analysis\Naming\DefaultNamingStrategy;
use Juf\DoctrineImportBundle\Analysis\Naming\KnownTablePrefixes;
use Juf\DoctrineImportBundle\Analysis\Naming\NamingStrategyInterface;
use Juf\DoctrineImportBundle\Definition\EntityDefinition;
use Juf\DoctrineImportBundle\Definition\GenerationModel;
use Juf\DoctrineImportBundle\Diagnostic\Diagnostic;
use Juf\DoctrineImportBundle\Diagnostic\Severity;
use Juf\DoctrineImportBundle\Schema\SchemaMetadata;

/**
 * Turns a database schema into entity definitions, with the diagnostics of reading and analysis.
 */
final readonly class SchemaAnalyzer
{
    private EntityAnalyzer $entityAnalyzer;

    private RelationAnalyzer $relationAnalyzer;

    private ManyToManyAnalyzer $manyToManyAnalyzer;

    private JoinTableDetector $joinTables;

    public function __construct(NamingStrategyInterface $naming = new DefaultNamingStrategy(), TypeMapper $types = new TypeMapper())
    {
        $this->entityAnalyzer = new EntityAnalyzer($naming, $types);
        $this->relationAnalyzer = new RelationAnalyzer($naming);
        $this->manyToManyAnalyzer = new ManyToManyAnalyzer($naming);
        $this->joinTables = new JoinTableDetector();
    }

    public function analyze(SchemaMetadata $schema): GenerationModel
    {
        // Join tables become ManyToMany associations, not entities.
        $joinTables = $this->joinTables->detect($schema);
        $entitySchema = $this->joinTables->withoutJoinTables($schema, $joinTables);

        $model = $this->relationAnalyzer->analyze($entitySchema, $this->entityAnalyzer->analyze($entitySchema));
        $model = $this->manyToManyAnalyzer->analyze($model, $joinTables);

        $entities = $model->entities;
        usort($entities, static fn (EntityDefinition $left, EntityDefinition $right): int => $left->className <=> $right->className);

        $diagnostics = [...$schema->diagnostics, ...$model->diagnostics];
        $prefixHint = $this->prefixHint($entities);
        if ($prefixHint instanceof Diagnostic) {
            $diagnostics[] = $prefixHint;
        }

        return new GenerationModel($entities, $diagnostics);
    }

    /**
     * @param list<EntityDefinition> $entities
     */
    private function prefixHint(array $entities): ?Diagnostic
    {
        $prefix = KnownTablePrefixes::sharedBy(array_map(static fn (EntityDefinition $entity): string => $entity->tableName, $entities));
        if (null === $prefix) {
            return null;
        }

        $classPrefix = str_replace('_', '', ucwords($prefix, '_'));
        foreach ($entities as $entity) {
            if (!str_starts_with($entity->className, $classPrefix)) {
                return null;
            }
        }

        return new Diagnostic(
            Severity::Info,
            'naming.table_prefix_detected',
            \sprintf('Every table starts with "%s": configure it as a table prefix to remove it from the class names.', $prefix),
        );
    }
}
