<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Analysis;

final readonly class MappedType
{
    /**
     * @param string|null $notice code of an informational diagnostic about the mapping
     */
    public function __construct(
        public string $doctrineType,
        public string $phpType,
        public ?string $notice = null,
    ) {
    }
}
