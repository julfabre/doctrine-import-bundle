<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Analysis;

use Juf\DoctrineImportBundle\Analysis\Naming\ClassName;
use Juf\DoctrineImportBundle\Analysis\Naming\InvalidIdentifierException;
use Juf\DoctrineImportBundle\Analysis\Naming\NamingStrategyInterface;
use Juf\DoctrineImportBundle\Diagnostic\DiagnosticCollector;
use Juf\DoctrineImportBundle\Diagnostic\Severity;
use Juf\DoctrineImportBundle\Schema\TableMetadata;

/**
 * Chooses the entity class name of a table, or reports why the table cannot become an entity.
 */
final readonly class ClassNameResolver
{
    public function __construct(
        private NamingStrategyInterface $naming,
    ) {
    }

    /**
     * @param list<string> $takenClassNames
     */
    public function resolve(TableMetadata $table, array $takenClassNames, DiagnosticCollector $diagnostics): ?string
    {
        if ([] === $table->primaryKey) {
            $diagnostics->report(Severity::Error, 'table.no_primary_key', \sprintf('Table "%s" has no primary key: Doctrine requires an identifier, the table is skipped.', $table->name), $table->name);

            return null;
        }

        try {
            $className = $this->naming->classNameForTable($table->name);
            if (\in_array($className->value, $takenClassNames, true)) {
                $className = $this->resolveCollision($table->name, $className, $takenClassNames, $diagnostics);
            }
        } catch (InvalidIdentifierException $invalidIdentifierException) {
            $diagnostics->report(Severity::Error, 'naming.invalid_identifier', $invalidIdentifierException->getMessage().' The table is skipped.', $table->name);

            return null;
        }

        if (!$className instanceof ClassName) {
            return null;
        }

        foreach ($className->adjustments as $adjustment) {
            $diagnostics->report(Severity::Warning, $adjustment, \sprintf('Table "%s" is mapped to class "%s".', $table->name, $className->value), $table->name);
        }

        return $className->value;
    }

    /**
     * @param list<string> $takenClassNames
     *
     * @throws InvalidIdentifierException
     */
    private function resolveCollision(string $table, ClassName $className, array $takenClassNames, DiagnosticCollector $diagnostics): ?ClassName
    {
        $alternative = $this->naming->unsingularizedClassNameForTable($table);
        if (\in_array($alternative->value, $takenClassNames, true)) {
            $diagnostics->report(Severity::Error, 'naming.class_collision', \sprintf('Table "%s" maps to class "%s", which is already used: the table is skipped.', $table, $className->value), $table);

            return null;
        }

        $diagnostics->report(Severity::Warning, 'naming.class_collision', \sprintf('Class "%s" is already used: table "%s" is mapped to class "%s".', $className->value, $table, $alternative->value), $table);

        return $alternative;
    }
}
