<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Analysis\Naming;

use InvalidArgumentException;
use Juf\DoctrineImportBundle\Exception\ExceptionInterface;

final class InvalidIdentifierException extends InvalidArgumentException implements ExceptionInterface
{
    public static function forName(string $name): self
    {
        return new self(\sprintf('The name "%s" contains characters other than ASCII letters, digits and underscores.', $name));
    }
}
