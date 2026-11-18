<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Rendering;

final readonly class RenderingOptions
{
    public function __construct(
        public string $entityNamespace = 'App\Entity',
        public string $repositoryNamespace = 'App\Repository',
        public string $enumNamespace = 'App\Enum',
    ) {
    }
}
