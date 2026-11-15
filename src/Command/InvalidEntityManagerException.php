<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Command;

use InvalidArgumentException;
use Juf\DoctrineImportBundle\Exception\ExceptionInterface;

final class InvalidEntityManagerException extends InvalidArgumentException implements ExceptionInterface
{
    public static function notAnOrmManager(?string $name, string $class): self
    {
        return new self(\sprintf('The manager "%s" is a "%s", not a Doctrine ORM entity manager.', $name ?? 'default', $class));
    }
}
