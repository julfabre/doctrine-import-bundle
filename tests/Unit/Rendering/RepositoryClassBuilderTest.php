<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Tests\Unit\Rendering;

use Juf\DoctrineImportBundle\Definition\ColumnOptions;
use Juf\DoctrineImportBundle\Definition\EntityDefinition;
use Juf\DoctrineImportBundle\Definition\FieldDefinition;
use Juf\DoctrineImportBundle\Rendering\GeneratedFile;
use Juf\DoctrineImportBundle\Rendering\RenderingOptions;
use Juf\DoctrineImportBundle\Rendering\RepositoryClassBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RepositoryClassBuilder::class)]
#[UsesClass(GeneratedFile::class)]
#[UsesClass(RenderingOptions::class)]
#[UsesClass(EntityDefinition::class)]
#[UsesClass(FieldDefinition::class)]
#[UsesClass(ColumnOptions::class)]
final class RepositoryClassBuilderTest extends TestCase
{
    #[Test]
    public function it_renders_a_service_entity_repository_for_the_entity(): void
    {
        $file = (new RepositoryClassBuilder())->build(DefinitionBuilder::entity('Post'), new RenderingOptions());

        self::assertSame('App\Repository\PostRepository', $file->className);
        self::assertSame(<<<'PHP'
            <?php

            declare(strict_types=1);

            namespace App\Repository;

            use App\Entity\Post;
            use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
            use Doctrine\Persistence\ManagerRegistry;

            /**
             * @extends ServiceEntityRepository<Post>
             */
            class PostRepository extends ServiceEntityRepository
            {
                public function __construct(ManagerRegistry $registry)
                {
                    parent::__construct($registry, Post::class);
                }
            }

            PHP, $file->contents);
    }
}
