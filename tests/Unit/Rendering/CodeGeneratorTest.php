<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Tests\Unit\Rendering;

use Juf\DoctrineImportBundle\Definition\ColumnOptions;
use Juf\DoctrineImportBundle\Definition\EntityDefinition;
use Juf\DoctrineImportBundle\Definition\FieldDefinition;
use Juf\DoctrineImportBundle\Definition\GenerationModel;
use Juf\DoctrineImportBundle\Rendering\AttributeMappingStrategy;
use Juf\DoctrineImportBundle\Rendering\CodeGenerator;
use Juf\DoctrineImportBundle\Rendering\EntityClassBuilder;
use Juf\DoctrineImportBundle\Rendering\GeneratedFile;
use Juf\DoctrineImportBundle\Rendering\MappingStrategyInterface;
use Juf\DoctrineImportBundle\Rendering\PhpLiteral;
use Juf\DoctrineImportBundle\Rendering\RenderingOptions;
use Juf\DoctrineImportBundle\Rendering\RepositoryClassBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CodeGenerator::class)]
#[UsesClass(AttributeMappingStrategy::class)]
#[UsesClass(EntityClassBuilder::class)]
#[UsesClass(RepositoryClassBuilder::class)]
#[UsesClass(PhpLiteral::class)]
#[UsesClass(GeneratedFile::class)]
#[UsesClass(RenderingOptions::class)]
#[UsesClass(GenerationModel::class)]
#[UsesClass(EntityDefinition::class)]
#[UsesClass(FieldDefinition::class)]
#[UsesClass(ColumnOptions::class)]
final class CodeGeneratorTest extends TestCase
{
    #[Test]
    public function it_generates_one_entity_file_and_one_repository_file_per_entity(): void
    {
        $files = (new CodeGenerator())->generate($this->model(), new RenderingOptions());

        self::assertSame(
            ['App\Entity\Author', 'App\Repository\AuthorRepository', 'App\Entity\Post', 'App\Repository\PostRepository'],
            array_map(static fn (GeneratedFile $file): string => $file->className, $files),
        );
    }

    #[Test]
    public function it_uses_the_configured_namespaces(): void
    {
        $files = (new CodeGenerator())->generate($this->model(), new RenderingOptions('Blog\Domain', 'Blog\Persistence'));

        self::assertSame(
            ['Blog\Domain\Author', 'Blog\Persistence\AuthorRepository', 'Blog\Domain\Post', 'Blog\Persistence\PostRepository'],
            array_map(static fn (GeneratedFile $file): string => $file->className, $files),
        );
        self::assertStringContainsString("use Blog\\Persistence\\PostRepository;\n", $files[2]->contents ?? '');
        self::assertStringContainsString("use Blog\\Domain\\Post;\n", $files[3]->contents ?? '');
    }

    #[Test]
    public function it_keeps_every_file_rendered_by_the_mapping_strategy(): void
    {
        $twoFilesPerEntity = new class implements MappingStrategyInterface {
            public function render(EntityDefinition $entity, RenderingOptions $options): array
            {
                return [new GeneratedFile($entity->className, ''), new GeneratedFile($entity->className.'.orm.xml', '')];
            }
        };

        $files = (new CodeGenerator($twoFilesPerEntity))->generate($this->model(), new RenderingOptions());

        self::assertSame(
            ['Author', 'Author.orm.xml', 'App\\Repository\\AuthorRepository', 'Post', 'Post.orm.xml', 'App\\Repository\\PostRepository'],
            array_map(static fn (GeneratedFile $file): string => $file->className, $files),
        );
    }

    private function model(): GenerationModel
    {
        return new GenerationModel([DefinitionBuilder::entity('Author'), DefinitionBuilder::entity('Post')], []);
    }
}
