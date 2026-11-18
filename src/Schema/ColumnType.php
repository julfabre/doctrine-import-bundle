<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Schema;

/**
 * Type of a column as declared by Doctrine DBAL, with its size and sign.
 */
final readonly class ColumnType
{
    /**
     * @param string       $name   DBAL type name, e.g. "integer" or "datetime_immutable"
     * @param list<string> $values allowed values of an ENUM column
     */
    public function __construct(
        public string $name,
        public ?int $length,
        public ?int $precision,
        public int $scale,
        public bool $unsigned,
        public bool $fixed,
        public array $values = [],
    ) {
    }
}
