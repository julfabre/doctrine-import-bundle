<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Tests\Unit\Rendering;

use Juf\DoctrineImportBundle\Definition\EnumCaseDefinition;
use Juf\DoctrineImportBundle\Definition\EnumDefinition;
use Juf\DoctrineImportBundle\Rendering\EnumBuilder;
use Juf\DoctrineImportBundle\Rendering\GeneratedFile;
use Juf\DoctrineImportBundle\Rendering\RenderingOptions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EnumBuilder::class)]
#[UsesClass(EnumDefinition::class)]
#[UsesClass(EnumCaseDefinition::class)]
#[UsesClass(GeneratedFile::class)]
#[UsesClass(RenderingOptions::class)]
final class EnumBuilderTest extends TestCase
{
    #[Test]
    public function it_renders_a_string_backed_enum_with_one_case_per_value(): void
    {
        $enum = new EnumDefinition('FilmRating', [new EnumCaseDefinition('G', 'G'), new EnumCaseDefinition('Pg13', 'PG-13')]);

        $file = (new EnumBuilder())->build($enum, new RenderingOptions(enumNamespace: 'Cinema\Enum'));

        self::assertSame('Cinema\Enum\FilmRating', $file->className);
        self::assertSame(
            "<?php\n\ndeclare(strict_types=1);\n\nnamespace Cinema\\Enum;\n\nenum FilmRating: string\n{\n    case G = 'G';\n    case Pg13 = 'PG-13';\n}\n",
            $file->contents,
        );
    }
}
