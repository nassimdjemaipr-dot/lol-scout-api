<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RiotAccountRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: RiotAccountRepository::class)]
class RiotAccount
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['player:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Groups(['player:read'])]
    private ?string $summonerName = null;

    #[ORM\Column(length: 78, unique: true)]
    #[Groups(['player:read'])]
    private ?string $puuid = null;

    #[ORM\Column(length: 10)]
    #[Groups(['player:read'])]
    private ?string $region = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['player:read'])]
    private ?\DateTimeImmutable $lastSyncAt = null;

    /**
     * Un compte Riot appartient à exactement un joueur LoL Scout.
     */
    #[ORM\OneToOne(inversedBy: 'riotAccount', targetEntity: Player::class)]
    #[ORM\JoinColumn(nullable: false, unique: true)]
    private ?Player $player = null;

    /**
     * Relation inverse vers PlayerStats.
     * Un compte Riot a au maximum un PlayerStats (créé après une sync).
     */
    #[ORM\OneToOne(mappedBy: 'riotAccount', targetEntity: PlayerStats::class)]
    #[Groups(['player:read'])]
    private ?PlayerStats $stats = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSummonerName(): ?string
    {
        return $this->summonerName;
    }

    public function setSummonerName(string $summonerName): static
    {
        $this->summonerName = $summonerName;

        return $this;
    }

    public function getPuuid(): ?string
    {
        return $this->puuid;
    }

    public function setPuuid(string $puuid): static
    {
        $this->puuid = $puuid;

        return $this;
    }

    public function getRegion(): ?string
    {
        return $this->region;
    }

    public function setRegion(string $region): static
    {
        $this->region = $region;

        return $this;
    }

    public function getLastSyncAt(): ?\DateTimeImmutable
    {
        return $this->lastSyncAt;
    }

    public function setLastSyncAt(?\DateTimeImmutable $lastSyncAt): static
    {
        $this->lastSyncAt = $lastSyncAt;

        return $this;
    }

    public function getPlayer(): ?Player
    {
        return $this->player;
    }

    public function setPlayer(Player $player): static
    {
        $this->player = $player;

        return $this;
    }

    public function getStats(): ?PlayerStats
    {
        return $this->stats;
    }

    public function setStats(?PlayerStats $stats): static
    {
        $this->stats = $stats;

        return $this;
    }
}
