<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PlayedChampionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: PlayedChampionRepository::class)]
class PlayedChampion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['player:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    #[Groups(['player:read'])]
    private ?string $championName = null;

    #[ORM\Column]
    #[Groups(['player:read'])]
    private ?int $gamesPlayed = null;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    #[Groups(['player:read'])]
    private ?string $winrate = null;

    #[ORM\Column(type: 'decimal', precision: 4, scale: 2)]
    #[Groups(['player:read'])]
    private ?string $kda = null;

    #[ORM\ManyToOne(inversedBy: 'playedChampions', targetEntity: PlayerStats::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?PlayerStats $playerStats = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getChampionName(): ?string
    {
        return $this->championName;
    }

    public function setChampionName(string $championName): static
    {
        $this->championName = $championName;

        return $this;
    }

    public function getGamesPlayed(): ?int
    {
        return $this->gamesPlayed;
    }

    public function setGamesPlayed(int $gamesPlayed): static
    {
        $this->gamesPlayed = $gamesPlayed;

        return $this;
    }

    public function getWinrate(): ?string
    {
        return $this->winrate;
    }

    public function setWinrate(string $winrate): static
    {
        $this->winrate = $winrate;

        return $this;
    }

    public function getKda(): ?string
    {
        return $this->kda;
    }

    public function setKda(string $kda): static
    {
        $this->kda = $kda;

        return $this;
    }

    public function getPlayerStats(): ?PlayerStats
    {
        return $this->playerStats;
    }

    public function setPlayerStats(PlayerStats $playerStats): static
    {
        $this->playerStats = $playerStats;

        return $this;
    }
}
