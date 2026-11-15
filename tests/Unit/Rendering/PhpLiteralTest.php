<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Tests\Unit\Rendering;

use Juf\DoctrineImportBundle\Rendering\PhpLiteral;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PhpLiteral::class)]
final class PhpLiteralTest extends TestCase
{
    #[Test]
    public function it_converts_only_defaults_that_match_the_property_type(): void
    {
        self::assertSame(-12, PhpLiteral::fromDefault('-12', 'int')?->value);
        self::assertTrue(PhpLiteral::fromDefault('TRUE', 'bool')?->value);
        self::assertFalse(PhpLiteral::fromDefault('No', 'bool')?->value);
        self::assertNull(PhpLiteral::fromDefault('1abc', 'int'));
        self::assertNull(PhpLiteral::fromDefault('abc1', 'int'));
        self::assertNull(PhpLiteral::fromDefault('abc', 'float'));
        self::assertNull(PhpLiteral::fromDefault('maybe', 'bool'));
        self::assertNull(PhpLiteral::fromDefault('{}', 'array'));
        self::assertNull(PhpLiteral::fromDefault(null, 'string'));
    }
}
