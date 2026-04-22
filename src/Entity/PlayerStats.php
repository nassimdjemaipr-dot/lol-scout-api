<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PlayerStatsRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlayerStatsRepository::class)]
class PlayerStats
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $tier = null;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    private ?string $winrate = null;

    #[ORM\Column(type: 'decimal', precision: 4, scale: 2)]
    private ?string $averageKda = null;

    #[ORM\Column(type: 'decimal', precision: 4, scale: 2)]
    private ?string $csPerMinute = null;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    private ?string $visionScore = null;

    #[ORM\Column]
    private ?int $rankedGamesCount = null;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false, unique: true)]
    private ?RiotAccount $riotAccount = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTier(): ?string
    {
        return $this->tier;
    }

    public function setTier(string $tier): static
    {
        $this->tier = $tier;

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

    public function getAverageKda(): ?string
    {
        return $this->averageKda;
    }

    public function setAverageKda(string $averageKda): static
    {
        $this->averageKda = $averageKda;

        return $this;
    }

    public function getCsPerMinute(): ?string
    {
        return $this->csPerMinute;
    }

    public function setCsPerMinute(string $csPerMinute): static
    {
        $this->csPerMinute = $csPerMinute;

        return $this;
    }

    public function getVisionScore(): ?string
    {
        return $this->visionScore;
    }

    public function setVisionScore(string $visionScore): static
    {
        $this->visionScore = $visionScore;

        return $this;
    }

    public function getRankedGamesCount(): ?int
    {
        return $this->rankedGamesCount;
    }

    public function setRankedGamesCount(int $rankedGamesCount): static
    {
        $this->rankedGamesCount = $rankedGamesCount;

        return $this;
    }

    public function getRiotAccount(): ?RiotAccount
    {
        return $this->riotAccount;
    }

    public function setRiotAccount(RiotAccount $riotAccount): static
    {
        $this->riotAccount = $riotAccount;

        return $this;
    }
}
