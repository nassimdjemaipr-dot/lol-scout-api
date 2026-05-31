<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PlayerStatsRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: PlayerStatsRepository::class)]
class PlayerStats
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['player:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    #[Groups(['player:read'])]
    private ?string $tier = null;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    #[Groups(['player:read'])]
    private ?string $winrate = null;

    #[ORM\Column(type: 'decimal', precision: 4, scale: 2)]
    #[Groups(['player:read'])]
    private ?string $averageKda = null;

    #[ORM\Column(type: 'decimal', precision: 4, scale: 2)]
    #[Groups(['player:read'])]
    private ?string $csPerMinute = null;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    #[Groups(['player:read'])]
    private ?string $visionScore = null;

    #[ORM\Column]
    #[Groups(['player:read'])]
    private ?int $rankedGamesCount = null;

    #[ORM\OneToOne(inversedBy: 'stats', targetEntity: RiotAccount::class)]
    #[ORM\JoinColumn(nullable: false, unique: true)]
    private ?RiotAccount $riotAccount = null;

    /**
     * Top champions joués par le compte Riot.
     * Relation inverse de PlayedChampion::$playerStats.
     *
     * @var Collection<int, PlayedChampion>
     */
    #[ORM\OneToMany(mappedBy: 'playerStats', targetEntity: PlayedChampion::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['player:read'])]
    private Collection $playedChampions;

    public function __construct()
    {
        $this->playedChampions = new ArrayCollection();
    }

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

    /**
     * @return Collection<int, PlayedChampion>
     */
    public function getPlayedChampions(): Collection
    {
        return $this->playedChampions;
    }

    public function addPlayedChampion(PlayedChampion $champion): static
    {
        if (!$this->playedChampions->contains($champion)) {
            $this->playedChampions->add($champion);
            $champion->setPlayerStats($this);
        }

        return $this;
    }

    public function removePlayedChampion(PlayedChampion $champion): static
    {
        $this->playedChampions->removeElement($champion);

        return $this;
    }
}
