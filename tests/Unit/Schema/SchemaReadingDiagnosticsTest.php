<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Tests\Unit\Schema;

use Doctrine\DBAL\Exception\InvalidArgumentException;
use Doctrine\DBAL\Schema\Exception\TableDoesNotExist;
use Juf\DoctrineImportBundle\Diagnostic\Diagnostic;
use Juf\DoctrineImportBundle\Schema\Dbal\SchemaReadingDiagnostics;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SchemaReadingDiagnostics::class)]
#[UsesClass(Diagnostic::class)]
final class SchemaReadingDiagnosticsTest extends TestCase
{
    #[Test]
    public function it_advises_to_map_an_unknown_type(): void
    {
        $diagnostic = SchemaReadingDiagnostics::unreadableTable('person', new InvalidArgumentException('Unknown database type "mood" requested, SomePlatform may not support it.'));

        self::assertSame(['error', 'table.unreadable', 'person'], [$diagnostic->severity->value, $diagnostic->code, $diagnostic->table]);
        self::assertSame(
            'Table "person" cannot be read: Unknown database type "mood" requested, SomePlatform may not support it. Map the type with the "doctrine.dbal.mapping_types" configuration, or exclude the table. The table is skipped.',
            $diagnostic->message,
        );
    }

    #[Test]
    public function it_gives_no_type_advice_for_another_error(): void
    {
        $diagnostic = SchemaReadingDiagnostics::unreadableTable('archive', TableDoesNotExist::new('archive'));

        self::assertSame('Table "archive" cannot be read: There is no table with name "archive" in the schema. The table is skipped.', $diagnostic->message);
    }
}
