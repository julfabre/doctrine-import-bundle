<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Analysis;

/**
 * Maps DBAL type names to the Doctrine type and the PHP type of the generated property.
 */
final class TypeMapper
{
    /** @var array<string, array{string, string, 2?: string}> DBAL type => [Doctrine type, PHP type, notice] */
    private const MAP = [
        'integer' => ['integer', 'int'],
        'smallint' => ['smallint', 'int'],
        'bigint' => ['bigint', 'int'],
        'string' => ['string', 'string'],
        'text' => ['text', 'string'],
        'guid' => ['guid', 'string'],
        'ascii_string' => ['ascii_string', 'string'],
        // Doctrine hydrates decimals as strings to keep their precision.
        'decimal' => ['decimal', 'string'],
        'float' => ['float', 'float'],
        'smallfloat' => ['smallfloat', 'float'],
        'boolean' => ['boolean', 'bool'],
        'date' => ['date_immutable', '\DateTimeImmutable'],
        'date_immutable' => ['date_immutable', '\DateTimeImmutable'],
        'datetime' => ['datetime_immutable', '\DateTimeImmutable'],
        'datetime_immutable' => ['datetime_immutable', '\DateTimeImmutable'],
        'datetimetz' => ['datetimetz_immutable', '\DateTimeImmutable'],
        'datetimetz_immutable' => ['datetimetz_immutable', '\DateTimeImmutable'],
        'time' => ['time_immutable', '\DateTimeImmutable'],
        'time_immutable' => ['time_immutable', '\DateTimeImmutable'],
        'dateinterval' => ['dateinterval', '\DateInterval'],
        'json' => ['json', 'array'],
        'simple_array' => ['simple_array', 'array'],
        // A PHP enum is generated from the values; "string" is the fallback when it cannot be.
        'enum' => ['enum', 'string'],
        'binary' => ['binary', 'mixed', 'column.binary_as_mixed'],
        'blob' => ['blob', 'mixed', 'column.binary_as_mixed'],
    ];

    public function map(string $dbalType): ?MappedType
    {
        $mapping = self::MAP[$dbalType] ?? null;

        return null === $mapping ? null : new MappedType($mapping[0], $mapping[1], $mapping[2] ?? null);
    }
}
