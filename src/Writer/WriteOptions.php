<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Writer;

final readonly class WriteOptions
{
    /**
     * @param bool $dryRun report what would be written without touching the filesystem
     * @param bool $force  replace existing files instead of skipping them
     */
    public function __construct(
        public bool $dryRun,
        public bool $force,
    ) {
    }
}
