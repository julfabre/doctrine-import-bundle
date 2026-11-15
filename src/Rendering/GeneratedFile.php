<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Rendering;

final readonly class GeneratedFile
{
    /**
     * @param string $className fully qualified name of the class declared in the file
     */
    public function __construct(
        public string $className,
        public string $contents,
    ) {
    }
}
