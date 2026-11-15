<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Writer;

enum FileStatus: string
{
    case Created = 'created';
    case Overwritten = 'overwritten';
    case Skipped = 'skipped';
}
