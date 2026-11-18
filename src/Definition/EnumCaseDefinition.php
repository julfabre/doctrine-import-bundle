<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Definition;

final readonly class EnumCaseDefinition
{
    public function __construct(
        public string $name,
        public string $value,
    ) {
    }
}
