<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Application;

use Juf\DoctrineImportBundle\Diagnostic\Diagnostic;
use Juf\DoctrineImportBundle\Diagnostic\Severity;
use Juf\DoctrineImportBundle\Writer\WrittenFile;

final readonly class GenerationReport
{
    /**
     * @param list<WrittenFile> $files
     * @param list<Diagnostic>  $diagnostics
     */
    public function __construct(
        public array $files,
        public array $diagnostics,
    ) {
    }

    /**
     * True when part of the schema could not be generated.
     */
    public function hasErrors(): bool
    {
        foreach ($this->diagnostics as $diagnostic) {
            if (Severity::Error === $diagnostic->severity) {
                return true;
            }
        }

        return false;
    }
}
