<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\PostVisibility;
use App\Repository\PostRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PostRepository::class)]
#[ORM\Table(name: 'post')]
#[ORM\Index(name: 'idx_post_author', columns: ['author_id'])]
class Post
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(name: 'title', type: Types::STRING, length: 255)]
    private string $title;

    #[ORM\Column(name: 'status', type: Types::STRING, length: 20, options: ['default' => 'draft'])]
    private string $status = 'draft';

    #[ORM\Column(name: 'price', type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $price = null;

    #[ORM\Column(name: 'views', type: Types::INTEGER, options: ['unsigned' => true, 'default' => '0'])]
    private int $views = 0;

    #[ORM\Column(name: 'published_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column(name: 'metadata', type: Types::JSON, nullable: true)]
    private ?array $metadata = null;

    #[ORM\Column(name: 'visibility', type: Types::ENUM, enumType: PostVisibility::class, options: ['default' => 'public'])]
    private PostVisibility $visibility = PostVisibility::Public;

    #[ORM\Column(name: 'title_length', type: Types::INTEGER, nullable: true, insertable: false, updatable: false, generated: 'ALWAYS')]
    private ?int $titleLength = null;

    #[ORM\ManyToOne(targetEntity: Author::class, inversedBy: 'posts')]
    #[ORM\JoinColumn(name: 'author_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Author $author = null;

    #[ORM\ManyToOne(targetEntity: Category::class, inversedBy: 'posts')]
    #[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id', nullable: true)]
    private ?Category $category = null;

    /** @var Collection<int, PostRevision> */
    #[ORM\OneToMany(targetEntity: PostRevision::class, mappedBy: 'post')]
    private Collection $postRevisions;

    /** @var Collection<int, Tag> */
    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'posts')]
    #[ORM\JoinTable(name: 'post_tag')]
    #[ORM\JoinColumn(name: 'post_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'tag_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection $tags;

    public function __construct()
    {
        $this->postRevisions = new ArrayCollection();
        $this->tags = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(?string $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getViews(): int
    {
        return $this->views;
    }

    public function setViews(int $views): static
    {
        $this->views = $views;

        return $this;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?\DateTimeImmutable $publishedAt): static
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): static
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function getVisibility(): PostVisibility
    {
        return $this->visibility;
    }

    public function setVisibility(PostVisibility $visibility): static
    {
        $this->visibility = $visibility;

        return $this;
    }

    public function getTitleLength(): ?int
    {
        return $this->titleLength;
    }

    public function getAuthor(): ?Author
    {
        return $this->author;
    }

    public function setAuthor(?Author $author): static
    {
        $this->author = $author;

        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;

        return $this;
    }

    /**
     * @return Collection<int, PostRevision>
     */
    public function getPostRevisions(): Collection
    {
        return $this->postRevisions;
    }

    public function addPostRevision(PostRevision $postRevision): static
    {
        if (!$this->postRevisions->contains($postRevision)) {
            $this->postRevisions->add($postRevision);
            $postRevision->setPost($this);
        }

        return $this;
    }

    public function removePostRevision(PostRevision $postRevision): static
    {
        // Unset the owning side, unless it already points to another entity.
        if ($this->postRevisions->removeElement($postRevision) && $this === $postRevision->getPost()) {
            $postRevision->setPost(null);
        }

        return $this;
    }

    /**
     * @return Collection<int, Tag>
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(Tag $tag): static
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }

        return $this;
    }

    public function removeTag(Tag $tag): static
    {
        $this->tags->removeElement($tag);

        return $this;
    }
}
