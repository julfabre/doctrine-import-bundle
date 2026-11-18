<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Tests\Unit\Analysis;

use Juf\DoctrineImportBundle\Analysis\MappedType;
use Juf\DoctrineImportBundle\Analysis\TypeMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TypeMapper::class)]
#[CoversClass(MappedType::class)]
final class TypeMapperTest extends TestCase
{
    #[Test]
    public function it_maps_integer_types_to_int(): void
    {
        self::assertSame(['integer', 'int', null], $this->map('integer'));
        self::assertSame(['smallint', 'int', null], $this->map('smallint'));
        self::assertSame(['bigint', 'int', null], $this->map('bigint'));
    }

    #[Test]
    public function it_maps_textual_types_and_decimal_to_string(): void
    {
        self::assertSame(['string', 'string', null], $this->map('string'));
        self::assertSame(['text', 'string', null], $this->map('text'));
        self::assertSame(['guid', 'string', null], $this->map('guid'));
        self::assertSame(['ascii_string', 'string', null], $this->map('ascii_string'));
        self::assertSame(['decimal', 'string', null], $this->map('decimal'));
    }

    #[Test]
    public function it_maps_float_and_boolean_types(): void
    {
        self::assertSame(['float', 'float', null], $this->map('float'));
        self::assertSame(['smallfloat', 'float', null], $this->map('smallfloat'));
        self::assertSame(['boolean', 'bool', null], $this->map('boolean'));
    }

    #[Test]
    public function it_maps_date_and_time_types_to_immutable_types(): void
    {
        self::assertSame(['datetime_immutable', '\DateTimeImmutable', null], $this->map('datetime'));
        self::assertSame(['datetime_immutable', '\DateTimeImmutable', null], $this->map('datetime_immutable'));
        self::assertSame(['datetimetz_immutable', '\DateTimeImmutable', null], $this->map('datetimetz'));
        self::assertSame(['date_immutable', '\DateTimeImmutable', null], $this->map('date'));
        self::assertSame(['time_immutable', '\DateTimeImmutable', null], $this->map('time'));
        self::assertSame(['dateinterval', '\DateInterval', null], $this->map('dateinterval'));
    }

    #[Test]
    public function it_maps_json_and_simple_array_to_array(): void
    {
        self::assertSame(['json', 'array', null], $this->map('json'));
        self::assertSame(['simple_array', 'array', null], $this->map('simple_array'));
    }

    #[Test]
    public function it_maps_enum_to_the_dbal_enum_type(): void
    {
        self::assertSame(['enum', 'string', null], $this->map('enum'));
    }

    #[Test]
    public function it_maps_binary_to_mixed_with_an_info(): void
    {
        self::assertSame(['binary', 'mixed', 'column.binary_as_mixed'], $this->map('binary'));
        self::assertSame(['blob', 'mixed', 'column.binary_as_mixed'], $this->map('blob'));
    }

    #[Test]
    public function it_reports_an_unknown_type_as_unsupported(): void
    {
        self::assertNull((new TypeMapper())->map('geometry'));
    }

    /**
     * @return array{string, string, ?string}
     */
    private function map(string $dbalType): array
    {
        $mapped = (new TypeMapper())->map($dbalType);
        self::assertNotNull($mapped);

        return [$mapped->doctrineType, $mapped->phpType, $mapped->notice];
    }
}
