<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\PlayerRole;
use App\Repository\OfferRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: OfferRepository::class)]
class Offer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['offer:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 150)]
    #[Groups(['offer:read', 'offer:write'])]
    private ?string $title = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank]
    #[Assert\Length(min: 10, max: 5000)]
    #[Groups(['offer:read', 'offer:write'])]
    private ?string $description = null;

    #[ORM\Column(enumType: PlayerRole::class)]
    #[Assert\NotNull]
    #[Groups(['offer:read', 'offer:write'])]
    private ?PlayerRole $wantedRole = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Groups(['offer:read', 'offer:write'])]
    private ?string $minimumRank = null;

    #[ORM\Column]
    #[Groups(['offer:read'])]
    private \DateTimeImmutable $publishedAt;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    #[Assert\GreaterThan('today')]
    #[Groups(['offer:read', 'offer:write'])]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column]
    #[Groups(['offer:read', 'offer:write'])]
    private bool $isActive = true;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['offer:read'])]
    private ?Club $club = null;

    public function __construct()
    {
        $this->publishedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getWantedRole(): ?PlayerRole
    {
        return $this->wantedRole;
    }

    public function setWantedRole(PlayerRole $wantedRole): static
    {
        $this->wantedRole = $wantedRole;

        return $this;
    }

    public function getMinimumRank(): ?string
    {
        return $this->minimumRank;
    }

    public function setMinimumRank(string $minimumRank): static
    {
        $this->minimumRank = $minimumRank;

        return $this;
    }

    public function getPublishedAt(): \DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(\DateTimeImmutable $publishedAt): static
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getClub(): ?Club
    {
        return $this->club;
    }

    public function setClub(Club $club): static
    {
        $this->club = $club;

        return $this;
    }
}