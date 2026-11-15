<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Writer;

use InvalidArgumentException;
use Juf\DoctrineImportBundle\Exception\ExceptionInterface;

final class UnmappedClassException extends InvalidArgumentException implements ExceptionInterface
{
    /**
     * @param list<string> $namespaces
     */
    public static function forClass(string $className, array $namespaces): self
    {
        return new self(\sprintf(
            'Class "%s" is not in any configured namespace (%s).',
            $className,
            implode(', ', array_map(static fn (string $namespace): string => '"'.$namespace.'"', $namespaces)),
        ));
    }
}
