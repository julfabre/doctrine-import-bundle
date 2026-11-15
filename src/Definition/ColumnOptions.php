<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Definition;

final readonly class ColumnOptions
{
    /**
     * @param string|null $default literal default value, never an SQL expression
     */
    public function __construct(
        public ?int $length,
        public ?int $precision,
        public int $scale,
        public bool $unsigned,
        public bool $fixed,
        public ?string $default,
        public ?string $comment,
    ) {
    }
}
