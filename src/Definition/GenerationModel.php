<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Definition;

use Juf\DoctrineImportBundle\Diagnostic\Diagnostic;

final readonly class GenerationModel
{
    /**
     * @param list<EntityDefinition> $entities
     * @param list<Diagnostic>       $diagnostics
     */
    public function __construct(
        public array $entities,
        public array $diagnostics,
    ) {
    }
}
