<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Tests\Unit\Rendering;

use Juf\DoctrineImportBundle\Definition\ColumnOptions;
use Juf\DoctrineImportBundle\Definition\EntityDefinition;
use Juf\DoctrineImportBundle\Definition\FieldDefinition;
use Juf\DoctrineImportBundle\Definition\IdGeneration;
use Juf\DoctrineImportBundle\Definition\IndexDefinition;
use Juf\DoctrineImportBundle\Definition\ManyToOneDefinition;
use Juf\DoctrineImportBundle\Definition\OneToManyDefinition;
use Juf\DoctrineImportBundle\Rendering\AttributeMappingStrategy;
use Juf\DoctrineImportBundle\Rendering\EntityClassBuilder;
use Juf\DoctrineImportBundle\Rendering\GeneratedFile;
use Juf\DoctrineImportBundle\Rendering\PhpLiteral;
use Juf\DoctrineImportBundle\Rendering\RenderingOptions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AttributeMappingStrategy::class)]
#[CoversClass(EntityClassBuilder::class)]
#[CoversClass(PhpLiteral::class)]
#[CoversClass(GeneratedFile::class)]
#[CoversClass(RenderingOptions::class)]
#[UsesClass(EntityDefinition::class)]
#[UsesClass(FieldDefinition::class)]
#[UsesClass(ColumnOptions::class)]
#[UsesClass(ManyToOneDefinition::class)]
#[UsesClass(OneToManyDefinition::class)]
#[UsesClass(IndexDefinition::class)]
final class AttributeMappingStrategyTest extends TestCase
{
    #[Test]
    public function it_renders_a_strict_typed_non_final_entity_class_in_the_entity_namespace(): void
    {
        $file = $this->renderFile(DefinitionBuilder::entity('Post'));

        self::assertSame('App\Entity\Post', $file->className);
        self::assertStringStartsWith("<?php\n\ndeclare(strict_types=1);\n\nnamespace App\\Entity;\n", $file->contents);
        self::assertStringContainsString("use Doctrine\\ORM\\Mapping as ORM;\n", $file->contents);
        self::assertStringContainsString("\nclass Post\n{\n", $file->contents);
    }

    #[Test]
    public function it_maps_the_entity_to_its_table_and_repository(): void
    {
        $code = $this->render(DefinitionBuilder::entity('Post', tableName: 'blog_post'));

        self::assertStringContainsString("use App\\Repository\\PostRepository;\n", $code);
        self::assertStringContainsString("#[ORM\\Entity(repositoryClass: PostRepository::class)]\n#[ORM\\Table(name: 'blog_post')]\nclass Post", $code);
    }

    #[Test]
    public function it_renders_column_attributes_with_explicit_type_and_only_useful_options(): void
    {
        $code = $this->render(DefinitionBuilder::entity('Product', [
            DefinitionBuilder::identityId(),
            DefinitionBuilder::field('code', 'code', length: 12, fixed: true, comment: 'SKU'),
            DefinitionBuilder::field('price', 'price', 'decimal', nullable: true, precision: 10, scale: 2),
            DefinitionBuilder::field('stock', 'stock', 'integer', 'int', unsigned: true),
            DefinitionBuilder::field('summary', 'summary', 'text'),
        ]));

        self::assertStringContainsString("use Doctrine\\DBAL\\Types\\Types;\n", $code);
        self::assertStringContainsString("#[ORM\\Column(name: 'code', type: Types::STRING, length: 12, options: ['fixed' => true, 'comment' => 'SKU'])]", $code);
        self::assertStringContainsString("#[ORM\\Column(name: 'price', type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]", $code);
        self::assertStringContainsString("#[ORM\\Column(name: 'stock', type: Types::INTEGER, options: ['unsigned' => true])]", $code);
        self::assertStringContainsString("#[ORM\\Column(name: 'summary', type: Types::TEXT)]", $code);
    }

    #[Test]
    public function it_always_names_the_column_explicitly(): void
    {
        $code = $this->render(DefinitionBuilder::entity('User', [
            DefinitionBuilder::identityId(),
            DefinitionBuilder::field('firstName', 'first_name'),
        ]));

        self::assertStringContainsString("#[ORM\\Column(name: 'id', type: Types::INTEGER)]", $code);
        self::assertStringContainsString("#[ORM\\Column(name: 'first_name', type: Types::STRING)]\n    private string \$firstName;", $code);
    }

    #[Test]
    public function it_renders_an_identity_id_without_setter(): void
    {
        $code = $this->render(DefinitionBuilder::entity('Post'));

        self::assertStringContainsString("    #[ORM\\Id]\n    #[ORM\\GeneratedValue(strategy: 'IDENTITY')]\n    #[ORM\\Column(name: 'id', type: Types::INTEGER)]\n    private ?int \$id = null;", $code);
        self::assertStringContainsString("public function getId(): ?int\n    {\n        return \$this->id;\n    }", $code);
        self::assertStringNotContainsString('function setId(', $code);
    }

    #[Test]
    public function it_renders_assigned_and_composite_ids_with_setters(): void
    {
        $code = $this->render(DefinitionBuilder::entity('OrderLine', [
            DefinitionBuilder::field('orderId', 'order_id', 'integer', 'int', idGeneration: IdGeneration::None),
            DefinitionBuilder::field('productId', 'product_id', 'integer', 'int', idGeneration: IdGeneration::None),
        ]));

        self::assertSame(2, substr_count($code, "    #[ORM\\Id]\n"));
        self::assertStringNotContainsString('GeneratedValue', $code);
        self::assertStringContainsString("    #[ORM\\Id]\n    #[ORM\\Column(name: 'order_id', type: Types::INTEGER)]\n    private int \$orderId;", $code);
        self::assertStringContainsString('public function setOrderId(int $orderId): static', $code);
        self::assertStringContainsString('public function setProductId(int $productId): static', $code);
    }

    #[Test]
    public function it_types_nullable_properties_with_a_null_default(): void
    {
        $code = $this->render(DefinitionBuilder::entity('Post', [
            DefinitionBuilder::identityId(),
            DefinitionBuilder::field('publishedAt', 'published_at', 'datetime_immutable', '\DateTimeImmutable', nullable: true),
        ]));

        self::assertStringContainsString('private ?\DateTimeImmutable $publishedAt = null;', $code);
        self::assertStringContainsString('public function getPublishedAt(): ?\DateTimeImmutable', $code);
        self::assertStringContainsString('public function setPublishedAt(?\DateTimeImmutable $publishedAt): static', $code);
    }

    #[Test]
    public function it_leaves_non_nullable_properties_without_default_uninitialized(): void
    {
        $code = $this->render(DefinitionBuilder::entity('Post', [DefinitionBuilder::identityId(), DefinitionBuilder::field('title', 'title')]));

        self::assertStringContainsString("    private string \$title;\n", $code);
        self::assertStringContainsString('public function getTitle(): string', $code);
    }

    #[Test]
    public function it_initializes_properties_with_the_php_literal_of_their_default(): void
    {
        $code = $this->render(DefinitionBuilder::entity('Settings', [
            DefinitionBuilder::identityId(),
            DefinitionBuilder::field('status', 'status', default: "it's draft"),
            DefinitionBuilder::field('retries', 'retries', 'integer', 'int', default: '3'),
            DefinitionBuilder::field('ratio', 'ratio', 'float', 'float', default: '0.5'),
            DefinitionBuilder::field('enabled', 'enabled', 'boolean', 'bool', default: 'true'),
            DefinitionBuilder::field('archived', 'archived', 'boolean', 'bool', default: '0'),
            DefinitionBuilder::field('createdOn', 'created_on', 'date_immutable', '\DateTimeImmutable', default: '2020-01-01'),
            DefinitionBuilder::field('label', 'label', nullable: true, default: 'none'),
        ]));

        self::assertStringContainsString("private string \$status = 'it\\'s draft';", $code);
        self::assertStringContainsString('private int $retries = 3;', $code);
        self::assertStringContainsString('private float $ratio = 0.5;', $code);
        self::assertStringContainsString('private bool $enabled = true;', $code);
        self::assertStringContainsString('private bool $archived = false;', $code);
        self::assertStringContainsString("private ?string \$label = 'none';", $code);
        self::assertStringContainsString("options: ['default' => '2020-01-01'])]\n    private \\DateTimeImmutable \$createdOn;", $code);
    }

    #[Test]
    public function it_renders_is_getters_for_booleans_and_fluent_setters(): void
    {
        $code = $this->render(DefinitionBuilder::entity('Post', [
            DefinitionBuilder::identityId(),
            DefinitionBuilder::field('published', 'published', 'boolean', 'bool'),
            DefinitionBuilder::field('isFeatured', 'is_featured', 'boolean', 'bool'),
            DefinitionBuilder::field('analysisDone', 'analysis_done', 'boolean', 'bool'),
        ]));

        self::assertStringContainsString('public function isPublished(): bool', $code);
        self::assertStringContainsString('public function isFeatured(): bool', $code);
        self::assertStringContainsString('public function isAnalysisDone(): bool', $code);
        self::assertStringContainsString("public function setPublished(bool \$published): static\n    {\n        \$this->published = \$published;\n\n        return \$this;\n    }", $code);
        self::assertStringContainsString('public function setIsFeatured(bool $isFeatured): static', $code);
    }

    #[Test]
    public function it_renders_a_many_to_one_association_with_its_join_column(): void
    {
        $code = $this->render(DefinitionBuilder::entity('Post', manyToOne: [
            new ManyToOneDefinition('author', 'Author', 'author_id', 'id', nullable: false, onDelete: 'CASCADE', inversedBy: 'posts'),
            new ManyToOneDefinition('reviewer', 'Author', 'reviewer_id', 'id', nullable: true, onDelete: null, inversedBy: null),
        ]));

        self::assertStringContainsString("    #[ORM\\ManyToOne(targetEntity: Author::class, inversedBy: 'posts')]\n    #[ORM\\JoinColumn(name: 'author_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]\n    private ?Author \$author = null;", $code);
        self::assertStringContainsString("    #[ORM\\ManyToOne(targetEntity: Author::class)]\n    #[ORM\\JoinColumn(name: 'reviewer_id', referencedColumnName: 'id', nullable: true)]\n    private ?Author \$reviewer = null;", $code);
        self::assertStringContainsString('public function getAuthor(): ?Author', $code);
        self::assertStringContainsString('public function setAuthor(?Author $author): static', $code);
    }

    #[Test]
    public function it_renders_a_one_to_many_collection_initialized_in_the_constructor(): void
    {
        $code = $this->render($this->authorWithPosts());

        self::assertStringContainsString("use Doctrine\\Common\\Collections\\ArrayCollection;\nuse Doctrine\\Common\\Collections\\Collection;\n", $code);
        self::assertStringContainsString("    /** @var Collection<int, Post> */\n    #[ORM\\OneToMany(targetEntity: Post::class, mappedBy: 'author')]\n    private Collection \$posts;", $code);
        self::assertStringContainsString("    public function __construct()\n    {\n        \$this->posts = new ArrayCollection();\n    }", $code);
        self::assertStringContainsString("    /**\n     * @return Collection<int, Post>\n     */\n    public function getPosts(): Collection", $code);
    }

    #[Test]
    public function it_renders_add_and_remove_methods_that_maintain_the_owning_side(): void
    {
        $code = $this->render($this->authorWithPosts());

        self::assertStringContainsString(<<<'PHP'
                public function addPost(Post $post): static
                {
                    if (!$this->posts->contains($post)) {
                        $this->posts->add($post);
                        $post->setAuthor($this);
                    }

                    return $this;
                }
            PHP, $code);
        self::assertStringContainsString(<<<'PHP'
                public function removePost(Post $post): static
                {
                    // Unset the owning side, unless it already points to another entity.
                    if ($this->posts->removeElement($post) && $this === $post->getAuthor()) {
                        $post->setAuthor(null);
                    }

                    return $this;
                }
            PHP, $code);
    }

    #[Test]
    public function it_renders_indexes_unique_constraints_and_table_comment(): void
    {
        $code = $this->render(DefinitionBuilder::entity('User', indexes: [
            new IndexDefinition('uniq_user_email', ['email'], unique: true),
            new IndexDefinition('idx_user_name', ['last_name', 'first_name'], unique: false),
        ], comment: 'Registered users'));

        self::assertStringContainsString(<<<'PHP'
            #[ORM\Table(name: 'user', options: ['comment' => 'Registered users'])]
            #[ORM\UniqueConstraint(name: 'uniq_user_email', columns: ['email'])]
            #[ORM\Index(name: 'idx_user_name', columns: ['last_name', 'first_name'])]
            class User
            PHP, $code);
    }

    #[Test]
    public function it_renders_a_mixed_property_without_nullable_marker(): void
    {
        $code = $this->render(DefinitionBuilder::entity('File', [
            DefinitionBuilder::identityId(),
            DefinitionBuilder::field('content', 'content', 'blob', 'mixed', nullable: true),
        ]));

        self::assertStringContainsString('private mixed $content = null;', $code);
        self::assertStringContainsString('public function getContent(): mixed', $code);
        self::assertStringContainsString('public function setContent(mixed $content): static', $code);
    }

    private function authorWithPosts(): EntityDefinition
    {
        return DefinitionBuilder::entity('Author', oneToMany: [new OneToManyDefinition('posts', 'Post', 'author')]);
    }

    private function renderFile(EntityDefinition $entity): GeneratedFile
    {
        $files = (new AttributeMappingStrategy())->render($entity, new RenderingOptions());
        self::assertCount(1, $files);

        return $files[0];
    }

    private function render(EntityDefinition $entity): string
    {
        return $this->renderFile($entity)->contents;
    }
}
