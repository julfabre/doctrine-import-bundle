<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Analysis\Naming;

final readonly class ClassName
{
    /**
     * @param list<string> $adjustments diagnostic codes describing how the name was changed to be valid
     */
    public function __construct(
        public string $value,
        public array $adjustments = [],
    ) {
    }
}
