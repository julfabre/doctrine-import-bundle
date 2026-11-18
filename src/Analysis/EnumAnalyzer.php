<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Analysis;

use Juf\DoctrineImportBundle\Analysis\Naming\InvalidIdentifierException;
use Juf\DoctrineImportBundle\Analysis\Naming\NamingStrategyInterface;
use Juf\DoctrineImportBundle\Definition\EnumCaseDefinition;
use Juf\DoctrineImportBundle\Definition\EnumDefinition;
use Juf\DoctrineImportBundle\Diagnostic\DiagnosticCollector;
use Juf\DoctrineImportBundle\Diagnostic\Severity;
use Juf\DoctrineImportBundle\Schema\ColumnMetadata;
use Juf\DoctrineImportBundle\Schema\TableMetadata;

/**
 * Turns the values of an ENUM column into a PHP enum, or reports why the column stays a string.
 */
final readonly class EnumAnalyzer
{
    public function __construct(
        private NamingStrategyInterface $naming,
    ) {
    }

    /**
     * @param list<string> $takenClassNames enums already generated for other columns
     */
    public function analyze(TableMetadata $table, ColumnMetadata $column, string $className, array $takenClassNames, DiagnosticCollector $diagnostics): ?EnumDefinition
    {
        $problem = $this->problem($column->type->values, $className, $takenClassNames);
        if (\is_string($problem)) {
            $diagnostics->report(Severity::Warning, 'column.enum_as_string', \sprintf('Column "%s.%s" is mapped as a string, without its allowed values: %s.', $table->name, $column->name, $problem), $table->name, $column->name);

            return null;
        }

        return $problem;
    }

    /**
     * @param list<string> $values
     * @param list<string> $takenClassNames
     *
     * @return EnumDefinition|string the enum, or why it cannot be generated
     */
    private function problem(array $values, string $className, array $takenClassNames): EnumDefinition|string
    {
        if (\in_array($className, $takenClassNames, true)) {
            return \sprintf('the enum "%s" is already generated for another column', $className);
        }

        $cases = [];
        foreach ($values as $value) {
            try {
                $name = $this->naming->enumCaseName($value);
            } catch (InvalidIdentifierException) {
                return \sprintf('the value "%s" gives no valid enum case name', $value);
            }

            if (isset($cases[$name])) {
                return \sprintf('the values "%s" and "%s" give the same enum case "%s"', $cases[$name]->value, $value, $name);
            }

            $cases[$name] = new EnumCaseDefinition($name, $value);
        }

        if ([] === $cases) {
            return 'no allowed value was read';
        }

        return new EnumDefinition($className, array_values($cases));
    }
}
