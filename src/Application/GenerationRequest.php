<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Application;

use Juf\DoctrineImportBundle\Rendering\RenderingOptions;
use Juf\DoctrineImportBundle\Schema\TableFilter;
use Juf\DoctrineImportBundle\Writer\WriteOptions;

final readonly class GenerationRequest
{
    /**
     * @param list<string> $tablePrefixes removed from table names before naming classes
     */
    public function __construct(
        public TableFilter $filter,
        public array $tablePrefixes,
        public RenderingOptions $rendering,
        public string $entityDirectory,
        public string $repositoryDirectory,
        public string $enumDirectory,
        public WriteOptions $write,
    ) {
    }
}
