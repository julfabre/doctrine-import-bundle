<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Diagnostic;

enum Severity: string
{
    case Error = 'error';
    case Warning = 'warning';
    case Info = 'info';
}
