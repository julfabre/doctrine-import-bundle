<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Rendering;

/**
 * A raw database default value converted to a PHP literal of the property type.
 */
final readonly class PhpLiteral
{
    private const TRUE_VALUES = ['1', 'true', 't', 'y', 'yes'];

    private const FALSE_VALUES = ['0', 'false', 'f', 'n', 'no'];

    private function __construct(
        public int|float|bool|string $value,
    ) {
    }

    /**
     * Returns null when the default cannot initialize a property of this type (dates, arrays, objects...).
     */
    public static function fromDefault(?string $default, string $phpType): ?self
    {
        if (null === $default) {
            return null;
        }

        return match ($phpType) {
            'string' => new self($default),
            'int' => 1 === preg_match('/^-?\d+$/', $default) ? new self((int) $default) : null,
            'float' => is_numeric($default) ? new self((float) $default) : null,
            'bool' => self::boolean($default),
            default => null,
        };
    }

    private static function boolean(string $default): ?self
    {
        $normalized = strtolower($default);

        return match (true) {
            \in_array($normalized, self::TRUE_VALUES, true) => new self(true),
            \in_array($normalized, self::FALSE_VALUES, true) => new self(false),
            default => null,
        };
    }
}
