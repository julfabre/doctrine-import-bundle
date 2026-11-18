<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PostRevisionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PostRevisionRepository::class)]
#[ORM\Table(name: 'post_revision')]
class PostRevision
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Post::class, inversedBy: 'postRevisions')]
    #[ORM\JoinColumn(name: 'post_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Post $post = null;

    #[ORM\Id]
    #[ORM\Column(name: 'revision', type: Types::INTEGER)]
    private int $revision;

    #[ORM\Column(name: 'body', type: Types::TEXT)]
    private string $body;

    public function getPost(): ?Post
    {
        return $this->post;
    }

    public function setPost(?Post $post): static
    {
        $this->post = $post;

        return $this;
    }

    public function getRevision(): int
    {
        return $this->revision;
    }

    public function setRevision(int $revision): static
    {
        $this->revision = $revision;

        return $this;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(string $body): static
    {
        $this->body = $body;

        return $this;
    }
}
