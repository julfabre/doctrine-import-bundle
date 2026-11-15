<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Analysis;

use Juf\DoctrineImportBundle\Analysis\Naming\NamingStrategyInterface;
use Juf\DoctrineImportBundle\Definition\ColumnOptions;
use Juf\DoctrineImportBundle\Definition\FieldDefinition;
use Juf\DoctrineImportBundle\Definition\IdGeneration;
use Juf\DoctrineImportBundle\Diagnostic\DiagnosticCollector;
use Juf\DoctrineImportBundle\Diagnostic\Severity;
use Juf\DoctrineImportBundle\Schema\ColumnMetadata;
use Juf\DoctrineImportBundle\Schema\TableMetadata;

/**
 * Turns a column into a scalar field: types, identifier and literal default value.
 */
final readonly class FieldAnalyzer
{
    /** SQL expressions cannot be mapped as a default value: only literals are kept. */
    private const EXPRESSION_DEFAULT = '/\\(|^(CURRENT_(TIMESTAMP|DATE|TIME)|LOCALTIMESTAMP|LOCALTIME)$/i';

    /** Mapped by DBAL to the right SQL on every platform. */
    private const STANDARD_DATE_EXPRESSIONS = ['CURRENT_TIMESTAMP', 'CURRENT_DATE', 'CURRENT_TIME'];

    public function __construct(
        private NamingStrategyInterface $naming,
        private TypeMapper $types,
    ) {
    }

    public function analyze(TableMetadata $table, ColumnMetadata $column, DiagnosticCollector $diagnostics): ?FieldDefinition
    {
        $mapped = $this->types->map($column->type->name);
        if (!$mapped instanceof MappedType) {
            $diagnostics->report(Severity::Error, 'column.unsupported_type', \sprintf('Column "%s.%s" has the unsupported type "%s": the column is skipped.', $table->name, $column->name, $column->type->name), $table->name, $column->name);

            return null;
        }

        if (null !== $mapped->notice) {
            $diagnostics->report(Severity::Info, $mapped->notice, \sprintf('Column "%s.%s" of type "%s" is mapped as "%s".', $table->name, $column->name, $column->type->name, $mapped->phpType), $table->name, $column->name);
        }

        return new FieldDefinition(
            $this->naming->propertyNameForColumn($column->name),
            $column->name,
            $mapped->doctrineType,
            $mapped->phpType,
            $column->nullable,
            $this->idGeneration($table, $column),
            new ColumnOptions(
                $column->type->length,
                $column->type->precision,
                $column->type->scale,
                $column->type->unsigned,
                $column->type->fixed,
                $this->defaultValue($table, $column, $mapped, $diagnostics),
                $column->comment,
            ),
        );
    }

    private function idGeneration(TableMetadata $table, ColumnMetadata $column): ?IdGeneration
    {
        if (!\in_array($column->name, $table->primaryKey, true)) {
            return null;
        }

        return [$column->name] === $table->primaryKey && $column->autoincrement ? IdGeneration::Identity : IdGeneration::None;
    }

    /**
     * Keeps literals and the date expressions every platform supports; reports any other SQL expression.
     */
    private function defaultValue(TableMetadata $table, ColumnMetadata $column, MappedType $mapped, DiagnosticCollector $diagnostics): ?string
    {
        if (null === $column->default) {
            return null;
        }

        // MariaDB reports "current_timestamp()": the standard keyword is kept.
        $keyword = strtoupper(preg_replace('/\(\)$/', '', $column->default) ?? $column->default);
        if ('\DateTimeImmutable' === $mapped->phpType && \in_array($keyword, self::STANDARD_DATE_EXPRESSIONS, true)) {
            return $keyword;
        }

        if (1 !== preg_match(self::EXPRESSION_DEFAULT, $column->default)) {
            return $column->default;
        }

        $diagnostics->report(Severity::Warning, 'column.default_expression', \sprintf('Column "%s.%s" has the default expression "%s", which is not mapped: set the value in PHP or let the database apply it.', $table->name, $column->name, $column->default), $table->name, $column->name);

        return null;
    }
}
