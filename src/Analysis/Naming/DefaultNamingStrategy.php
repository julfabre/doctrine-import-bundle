<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Analysis\Naming;

use Doctrine\Inflector\Inflector;
use Doctrine\Inflector\InflectorFactory;

/**
 * Singular PascalCase class names, camelCase properties, plural collections.
 */
final readonly class DefaultNamingStrategy implements NamingStrategyInterface
{
    private const RESERVED_WORDS = [
        'abstract', 'and', 'array', 'as', 'bool', 'break', 'callable', 'case', 'catch', 'class', 'clone', 'const',
        'continue', 'declare', 'default', 'do', 'echo', 'else', 'elseif', 'empty', 'enddeclare', 'endfor',
        'endforeach', 'endif', 'endswitch', 'endwhile', 'enum', 'eval', 'exit', 'extends', 'false', 'final',
        'finally', 'float', 'fn', 'for', 'foreach', 'function', 'global', 'goto', 'if', 'implements', 'include',
        'include_once', 'instanceof', 'insteadof', 'int', 'interface', 'isset', 'iterable', 'list', 'match',
        'mixed', 'namespace', 'never', 'new', 'null', 'object', 'or', 'parent', 'print', 'private', 'protected',
        'public', 'readonly', 'require', 'require_once', 'return', 'self', 'static', 'string', 'switch', 'throw',
        'trait', 'true', 'try', 'unset', 'use', 'var', 'void', 'while', 'xor', 'yield',
    ];

    /** @var list<string> longest first */
    private array $tablePrefixes;

    private Inflector $inflector;

    /**
     * @param list<string> $tablePrefixes removed from table names before naming classes, e.g. ["wp_"]
     */
    public function __construct(array $tablePrefixes = [])
    {
        usort($tablePrefixes, static fn (string $left, string $right): int => \strlen($right) <=> \strlen($left));
        $this->tablePrefixes = $tablePrefixes;
        $this->inflector = InflectorFactory::create()->build();
    }

    public function classNameForTable(string $table): ClassName
    {
        return $this->className($this->inflector->singularize($this->withoutPrefix($this->validated($table))));
    }

    public function unsingularizedClassNameForTable(string $table): ClassName
    {
        return $this->className($this->withoutPrefix($this->validated($table)));
    }

    public function propertyNameForColumn(string $column): string
    {
        // ALL-CAPS names ("ID", "FIRST_NAME") are lowered first so that "ID" becomes "id", not "iD".
        $name = strtoupper($column) === $column ? strtolower($column) : $column;

        return $this->inflector->camelize($name);
    }

    public function associationNameForColumn(string $column): string
    {
        $name = preg_replace(['/_id$/i', '/^id_/i', '/(?<=[a-z])Id$/'], '', $column) ?? $column;

        return $this->propertyNameForColumn('' === $name ? $column : $name);
    }

    public function collectionNameForEntity(string $className): string
    {
        return lcfirst($this->inflector->pluralize($className));
    }

    public function enumClassName(string $entityClassName, string $propertyName): string
    {
        return $entityClassName.ucfirst($propertyName);
    }

    public function enumCaseName(string $value): string
    {
        $words = preg_split('/[^A-Za-z0-9]+/', $value, -1, \PREG_SPLIT_NO_EMPTY);
        $words = false === $words ? [] : $words;

        $name = implode('', array_map(static fn (string $word): string => ucfirst(strtolower($word)), $words));

        // "class" is reserved for Enum::class.
        if ('' === $name || 'class' === strtolower($name)) {
            throw InvalidIdentifierException::forEnumValue($value);
        }

        return 1 === preg_match('/^\d/', $name) ? 'Value'.$name : $name;
    }

    private function validated(string $name): string
    {
        if (1 !== preg_match('/^[A-Za-z0-9_]+$/D', $name)) {
            throw InvalidIdentifierException::forName($name);
        }

        return $name;
    }

    private function withoutPrefix(string $table): string
    {
        foreach ($this->tablePrefixes as $prefix) {
            $rest = substr($table, \strlen($prefix));
            // A prefix is kept when removing it would leave an empty name or one starting with a digit.
            if (str_starts_with($table, $prefix) && 1 === preg_match('/^[A-Za-z_]/', $rest)) {
                return $rest;
            }
        }

        return $table;
    }

    private function className(string $name): ClassName
    {
        $className = $this->inflector->classify($name);
        $adjustments = [];

        if (1 === preg_match('/^\d/', $className)) {
            $className = 'Table'.$className;
            $adjustments[] = 'naming.leading_digit';
        }

        if (\in_array(strtolower($className), self::RESERVED_WORDS, true)) {
            $className .= 'Entity';
            $adjustments[] = 'naming.reserved_word';
        }

        return new ClassName($className, $adjustments);
    }
}
