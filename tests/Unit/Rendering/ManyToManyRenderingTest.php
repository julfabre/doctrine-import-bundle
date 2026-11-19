<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Tests\Unit\Rendering;

use Juf\DoctrineImportBundle\Definition\ColumnOptions;
use Juf\DoctrineImportBundle\Definition\EntityDefinition;
use Juf\DoctrineImportBundle\Definition\FieldDefinition;
use Juf\DoctrineImportBundle\Definition\JoinColumnDefinition;
use Juf\DoctrineImportBundle\Definition\JoinTableDefinition;
use Juf\DoctrineImportBundle\Definition\ManyToManyDefinition;
use Juf\DoctrineImportBundle\Rendering\AttributeMappingStrategy;
use Juf\DoctrineImportBundle\Rendering\EntityClassBuilder;
use Juf\DoctrineImportBundle\Rendering\GeneratedFile;
use Juf\DoctrineImportBundle\Rendering\ManyToManyAttributes;
use Juf\DoctrineImportBundle\Rendering\PhpLiteral;
use Juf\DoctrineImportBundle\Rendering\RenderingOptions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AttributeMappingStrategy::class)]
#[CoversClass(EntityClassBuilder::class)]
#[CoversClass(ManyToManyAttributes::class)]
#[UsesClass(PhpLiteral::class)]
#[UsesClass(GeneratedFile::class)]
#[UsesClass(RenderingOptions::class)]
#[UsesClass(EntityDefinition::class)]
#[UsesClass(FieldDefinition::class)]
#[UsesClass(ColumnOptions::class)]
#[UsesClass(ManyToManyDefinition::class)]
#[UsesClass(JoinTableDefinition::class)]
#[UsesClass(JoinColumnDefinition::class)]
final class ManyToManyRenderingTest extends TestCase
{
    #[Test]
    public function it_renders_the_owning_side_with_its_join_table(): void
    {
        $code = $this->render(DefinitionBuilder::entity('Post', manyToMany: [$this->owningSide()]));

        self::assertStringContainsString(
            "    /** @var Collection<int, Tag> */\n"
            ."    #[ORM\\ManyToMany(targetEntity: Tag::class, inversedBy: 'posts')]\n"
            ."    #[ORM\\JoinTable(name: 'post_tag')]\n"
            ."    #[ORM\\JoinColumn(name: 'post_id', referencedColumnName: 'id', onDelete: 'CASCADE')]\n"
            ."    #[ORM\\InverseJoinColumn(name: 'tag_id', referencedColumnName: 'id', onDelete: 'CASCADE')]\n"
            .'    private Collection $tags;',
            $code,
        );
    }

    #[Test]
    public function it_renders_the_inverse_side_mapped_by_the_owning_side(): void
    {
        $code = $this->render(DefinitionBuilder::entity('Tag', manyToMany: [new ManyToManyDefinition('posts', 'Post', null, null, 'tags')]));

        self::assertStringContainsString("    /** @var Collection<int, Post> */\n    #[ORM\\ManyToMany(targetEntity: Post::class, mappedBy: 'tags')]\n    private Collection \$posts;", $code);
    }

    #[Test]
    public function it_renders_an_owning_side_without_inverse_side(): void
    {
        $code = $this->render(DefinitionBuilder::entity('User', manyToMany: [new ManyToManyDefinition(
            'friends',
            'User',
            new JoinTableDefinition('user_friend', new JoinColumnDefinition('user_id', 'id', null), new JoinColumnDefinition('friend_id', 'id', null)),
            null,
            null,
        )]));

        self::assertStringContainsString("    #[ORM\\ManyToMany(targetEntity: User::class)]\n    #[ORM\\JoinTable(name: 'user_friend')]\n    #[ORM\\JoinColumn(name: 'user_id', referencedColumnName: 'id')]\n    #[ORM\\InverseJoinColumn(name: 'friend_id', referencedColumnName: 'id')]\n", $code);
    }

    #[Test]
    public function it_renders_the_column_options_of_the_join_columns(): void
    {
        $code = $this->render(DefinitionBuilder::entity('Post', manyToMany: [new ManyToManyDefinition(
            'tags',
            'Tag',
            new JoinTableDefinition('post_tag', new JoinColumnDefinition('post_id', 'id', null, ['default' => null]), new JoinColumnDefinition('tag_id', 'id', null, ['default' => null])),
            null,
            null,
        )]));

        self::assertStringContainsString("    #[ORM\\JoinColumn(name: 'post_id', referencedColumnName: 'id', options: ['default' => null])]\n    #[ORM\\InverseJoinColumn(name: 'tag_id', referencedColumnName: 'id', options: ['default' => null])]\n", $code);
    }

    #[Test]
    public function it_initializes_many_to_many_collections_in_the_constructor(): void
    {
        $code = $this->render(DefinitionBuilder::entity('Post', manyToMany: [$this->owningSide()]));

        self::assertStringContainsString("use Doctrine\\Common\\Collections\\ArrayCollection;\n", $code);
        self::assertStringContainsString("    public function __construct()\n    {\n        \$this->tags = new ArrayCollection();\n    }", $code);
    }

    #[Test]
    public function it_renders_add_and_remove_methods_on_the_owning_side(): void
    {
        $code = $this->render(DefinitionBuilder::entity('Post', manyToMany: [$this->owningSide()]));

        self::assertStringContainsString("    /**\n     * @return Collection<int, Tag>\n     */\n    public function getTags(): Collection", $code);
        self::assertStringContainsString("    public function addTag(Tag \$tag): static\n    {\n        if (!\$this->tags->contains(\$tag)) {\n            \$this->tags->add(\$tag);\n        }\n\n        return \$this;\n    }", $code);
        self::assertStringContainsString("    public function removeTag(Tag \$tag): static\n    {\n        \$this->tags->removeElement(\$tag);\n\n        return \$this;\n    }", $code);
    }

    #[Test]
    public function it_renders_add_and_remove_methods_that_update_the_owning_side_from_the_inverse_side(): void
    {
        $code = $this->render(DefinitionBuilder::entity('Tag', manyToMany: [new ManyToManyDefinition('posts', 'Post', null, null, 'tags')]));

        self::assertStringContainsString("    public function addPost(Post \$post): static\n    {\n        if (!\$this->posts->contains(\$post)) {\n            \$this->posts->add(\$post);\n            \$post->addTag(\$this);\n        }\n\n        return \$this;\n    }", $code);
        self::assertStringContainsString("    public function removePost(Post \$post): static\n    {\n        if (\$this->posts->removeElement(\$post)) {\n            \$post->removeTag(\$this);\n        }\n\n        return \$this;\n    }", $code);
    }

    private function owningSide(): ManyToManyDefinition
    {
        return new ManyToManyDefinition(
            'tags',
            'Tag',
            new JoinTableDefinition('post_tag', new JoinColumnDefinition('post_id', 'id', 'CASCADE'), new JoinColumnDefinition('tag_id', 'id', 'CASCADE')),
            'posts',
            null,
        );
    }

    private function render(EntityDefinition $entity): string
    {
        return (new AttributeMappingStrategy())->render($entity, new RenderingOptions())[0]->contents ?? '';
    }
}
