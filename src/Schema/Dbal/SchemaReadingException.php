<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Schema\Dbal;

use Juf\DoctrineImportBundle\Exception\ExceptionInterface;
use RuntimeException;

final class SchemaReadingException extends RuntimeException implements ExceptionInterface
{
    public static function unsupportedPlatform(string $platformClass): self
    {
        return new self(\sprintf(
            'The database platform "%s" is not supported. Supported platforms are PostgreSQL, MySQL, MariaDB and SQLite.',
            $platformClass,
        ));
    }
}
