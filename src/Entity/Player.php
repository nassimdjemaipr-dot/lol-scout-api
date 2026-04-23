<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\PlayerRole;
use App\Repository\PlayerRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PlayerRepository::class)]
class Player
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['player:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 100)]
    #[Groups(['player:read', 'player:write'])]
    private ?string $pseudo = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 100)]
    #[Groups(['player:read', 'player:write'])]
    private ?string $firstName = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 100)]
    #[Groups(['player:read', 'player:write'])]
    private ?string $lastName = null;

    #[ORM\Column(enumType: PlayerRole::class)]
    #[Assert\NotNull]
    #[Groups(['player:read', 'player:write'])]
    private ?PlayerRole $gameRole = null;

    #[ORM\Column]
    #[Groups(['player:read', 'player:write'])]
    private bool $isAvailable = true;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 2000)]
    #[Groups(['player:read', 'player:write'])]
    private ?string $bio = null;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false, unique: true)]
    private ?User $user = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPseudo(): ?string
    {
        return $this->pseudo;
    }

    public function setPseudo(string $pseudo): static
    {
        $this->pseudo = $pseudo;

        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getGameRole(): ?PlayerRole
    {
        return $this->gameRole;
    }

    public function setGameRole(PlayerRole $gameRole): static
    {
        $this->gameRole = $gameRole;

        return $this;
    }

    public function isAvailable(): bool
    {
        return $this->isAvailable;
    }

    public function setIsAvailable(bool $isAvailable): static
    {
        $this->isAvailable = $isAvailable;

        return $this;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): static
    {
        $this->bio = $bio;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }
}
