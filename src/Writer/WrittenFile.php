<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Writer;

final readonly class WrittenFile
{
    public function __construct(
        public string $className,
        public string $path,
        public FileStatus $status,
    ) {
    }
}
