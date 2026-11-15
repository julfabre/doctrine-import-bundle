<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Diagnostic;

/**
 * Something the user should know about the schema, reported instead of generating guessed code.
 */
final readonly class Diagnostic
{
    public function __construct(
        public Severity $severity,
        public string $code,
        public string $message,
        public ?string $table = null,
        public ?string $column = null,
    ) {
    }
}
