<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Index(name: 'idx_board_meeting_club_starts', columns: ['club_id', 'starts_at'])]
class BoardMeeting
{
    public const STATUS_PLANNED = 'planned';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Club $club;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank, Assert\Length(max: 180)]
    private string $title;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $startsAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $endsAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $location = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $agenda = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $minutes = null;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PLANNED;

    #[ORM\ManyToMany(targetEntity: User::class)]
    #[ORM\JoinTable(name: 'board_meeting_participant')]
    #[ORM\JoinColumn(name: 'meeting_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'user_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection $participants;

    #[ORM\OneToMany(mappedBy: 'meeting', targetEntity: BoardResolution::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $resolutions;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private User $createdBy;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(Club $club, User $createdBy, string $title, \DateTimeImmutable $startsAt)
    {
        $this->club = $club;
        $this->createdBy = $createdBy;
        $this->title = trim($title);
        if ($this->title === '') {
            throw new \InvalidArgumentException('Ein Sitzungstitel ist erforderlich.');
        }
        $this->startsAt = $startsAt;
        $this->createdAt = new \DateTimeImmutable();
        $this->participants = new ArrayCollection();
        $this->resolutions = new ArrayCollection();
    }

    public function __toString(): string { return $this->title.' ('.$this->startsAt->format('d.m.Y H:i').')'; }
    public function getId(): ?int { return $this->id; }
    public function getClub(): Club { return $this->club; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): static { $this->title = trim($title); return $this; }
    public function getStartsAt(): \DateTimeImmutable { return $this->startsAt; }
    public function setStartsAt(\DateTimeImmutable $startsAt): static
    {
        if ($this->endsAt !== null && $this->endsAt < $startsAt) {
            throw new \InvalidArgumentException('Das Sitzungsende liegt vor dem Beginn.');
        }
        $this->startsAt = $startsAt;
        return $this;
    }
    public function getEndsAt(): ?\DateTimeImmutable { return $this->endsAt; }
    public function setEndsAt(?\DateTimeImmutable $endsAt): static
    {
        if ($endsAt !== null && $endsAt < $this->startsAt) {
            throw new \InvalidArgumentException('Das Sitzungsende liegt vor dem Beginn.');
        }
        $this->endsAt = $endsAt;
        return $this;
    }
    public function getLocation(): ?string { return $this->location; }
    public function setLocation(?string $location): static { $this->location = $location === null ? null : trim($location); return $this; }
    public function getAgenda(): ?string { return $this->agenda; }
    public function setAgenda(?string $agenda): static { $this->agenda = $agenda === null ? null : trim($agenda); return $this; }
    public function getMinutes(): ?string { return $this->minutes; }
    public function setMinutes(?string $minutes): static { $this->minutes = $minutes === null ? null : trim($minutes); return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static
    {
        if (!in_array($status, [self::STATUS_PLANNED, self::STATUS_COMPLETED, self::STATUS_CANCELLED], true)) {
            throw new \InvalidArgumentException('Unbekannter Sitzungsstatus.');
        }
        $this->status = $status;
        return $this;
    }
    public function getParticipants(): Collection { return $this->participants; }
    public function addParticipant(User $participant): static { if (!$this->participants->contains($participant)) { $this->participants->add($participant); } return $this; }
    public function removeParticipant(User $participant): static { $this->participants->removeElement($participant); return $this; }
    public function getResolutions(): Collection { return $this->resolutions; }
    public function addResolution(BoardResolution $resolution): static
    {
        if (!$this->resolutions->contains($resolution)) {
            $this->resolutions->add($resolution);
            $resolution->setMeeting($this);
        }
        return $this;
    }
    public function getCreatedBy(): User { return $this->createdBy; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    /** @return array<string, string> */
    public static function statusChoices(): array { return ['Geplant' => self::STATUS_PLANNED, 'Abgeschlossen' => self::STATUS_COMPLETED, 'Abgesagt' => self::STATUS_CANCELLED]; }
}
