<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AuthorProfileRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AuthorProfileRepository::class)]
#[ORM\Table(name: 'author_profile')]
#[ORM\UniqueConstraint(name: 'uniq_author_profile_author', columns: ['author_id'])]
class AuthorProfile
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(name: 'website', type: Types::STRING, length: 255, nullable: true)]
    private ?string $website = null;

    #[ORM\OneToOne(targetEntity: Author::class)]
    #[ORM\JoinColumn(name: 'author_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Author $author = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWebsite(): ?string
    {
        return $this->website;
    }

    public function setWebsite(?string $website): static
    {
        $this->website = $website;

        return $this;
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
}
