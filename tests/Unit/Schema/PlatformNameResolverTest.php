<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Tests\Unit\Schema;

use Doctrine\DBAL\Platforms\OraclePlatform;
use Juf\DoctrineImportBundle\Schema\Dbal\PlatformNameResolver;
use Juf\DoctrineImportBundle\Schema\Dbal\SchemaReadingException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PlatformNameResolver::class)]
#[CoversClass(SchemaReadingException::class)]
final class PlatformNameResolverTest extends TestCase
{
    #[Test]
    public function it_rejects_an_unsupported_platform_with_the_list_of_supported_ones(): void
    {
        $this->expectException(SchemaReadingException::class);
        $this->expectExceptionMessage(
            'The database platform "Doctrine\DBAL\Platforms\OraclePlatform" is not supported. Supported platforms are PostgreSQL, MySQL, MariaDB and SQLite.',
        );

        (new PlatformNameResolver())->resolve(new OraclePlatform());
    }
}
