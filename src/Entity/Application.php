<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ApplicationStatus;
use App\Repository\ApplicationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ApplicationRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_player_offer', columns: ['player_id', 'offer_id'])]
#[ORM\Table(name: 'application')]
class Application
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['application:read'])]
    private ?int $id = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(
        min: 10,
        max: 2000,
        minMessage: 'Le message de motivation doit faire au moins 10 caractères',
        maxMessage: 'Le message ne peut excéder 2000 caractères'
    )]
    #[Groups(['application:read', 'application:write'])]
    private ?string $message = null;

    #[ORM\Column(enumType: ApplicationStatus::class)]
    #[Groups(['application:read'])]
    private ApplicationStatus $status = ApplicationStatus::EN_ATTENTE;

    #[ORM\Column]
    #[Groups(['application:read'])]
    private \DateTimeImmutable $appliedAt;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['application:read'])]
    private ?Player $player = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['application:read'])]
    private ?Offer $offer = null;

    public function __construct()
    {
        $this->appliedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function getStatus(): ApplicationStatus
    {
        return $this->status;
    }

    public function setStatus(ApplicationStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getAppliedAt(): \DateTimeImmutable
    {
        return $this->appliedAt;
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

    public function getOffer(): ?Offer
    {
        return $this->offer;
    }

    public function setOffer(Offer $offer): static
    {
        $this->offer = $offer;

        return $this;
    }
}
