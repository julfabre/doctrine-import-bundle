<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Writer;

/**
 * The directory where the classes of a namespace are written, following PSR-4.
 */
final readonly class OutputLocation
{
    public function __construct(
        public string $namespace,
        public string $directory,
    ) {
    }
}
