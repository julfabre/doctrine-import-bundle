<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Writer;

use Juf\DoctrineImportBundle\Exception\ExceptionInterface;
use RuntimeException;

final class WriteFailedException extends RuntimeException implements ExceptionInterface
{
    public static function forPath(string $path): self
    {
        return new self(\sprintf('Cannot write "%s": check that its directory is writable.', $path));
    }
}
