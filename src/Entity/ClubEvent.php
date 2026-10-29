<?php

declare(strict_types=1);
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity]
#[ORM\Index(name: 'idx_club_event_listing', columns: ['club_id', 'published', 'starts_at'])]
class ClubEvent
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')] private Club $club;
    #[ORM\Column(length: 180), Assert\NotBlank, Assert\Length(max: 180)] private string $title = '';
    #[ORM\Column(type: 'text'), Assert\NotBlank, Assert\Length(max: 20000)] private string $description = '';
    #[ORM\Column(length: 30), Assert\Choice(choices: ['members_free', 'paid'])] private string $pricing = 'members_free';
    #[ORM\Column] private int $memberFeeCents = 0;
    #[ORM\Column] private int $guestFeeCents = 0;
    #[ORM\Column(type: 'datetime_immutable')] private ?\DateTimeImmutable $startsAt = null;
    #[ORM\Column(type: 'datetime_immutable')] private ?\DateTimeImmutable $endsAt = null;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)] private ?\DateTimeImmutable $deadline = null;
    #[ORM\Column(length: 255), Assert\NotBlank, Assert\Length(max: 255)] private string $location = '';
    #[ORM\Column(nullable: true), Assert\Positive] private ?int $capacity = null;
    #[ORM\Column] private bool $published = false;
    #[ORM\Column] private bool $cancelled = false;

    public function __construct(Club $club) { $this->club = $club; }
    public function getId(): ?int { return $this->id; }
    public function getClub(): Club { return $this->club; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(?string $value): void { $this->title = trim($value ?? ''); }
    public function getDescription(): string { return $this->description; }
    public function setDescription(?string $value): void { $this->description = trim($value ?? ''); }
    public function getPricing(): string { return $this->pricing; }
    public function setPricing(?string $value): void { $this->pricing = $value ?? ''; }
    public function getMemberFeeCents(): int { return $this->memberFeeCents; }
    public function setMemberFeeCents(?int $value): void { $this->memberFeeCents = $value ?? -1; }
    public function getGuestFeeCents(): int { return $this->guestFeeCents; }
    public function setGuestFeeCents(?int $value): void { $this->guestFeeCents = $value ?? -1; }
    public function getStartsAt(): ?\DateTimeImmutable { return $this->startsAt; }
    public function setStartsAt(?\DateTimeImmutable $value): void { $this->startsAt = $value; }
    public function getEndsAt(): ?\DateTimeImmutable { return $this->endsAt; }
    public function setEndsAt(?\DateTimeImmutable $value): void { $this->endsAt = $value; }
    public function getDeadline(): ?\DateTimeImmutable { return $this->deadline; }
    public function setDeadline(?\DateTimeImmutable $value): void { $this->deadline = $value; }
    public function getLocation(): string { return $this->location; }
    public function setLocation(?string $value): void { $this->location = trim($value ?? ''); }
    public function getCapacity(): ?int { return $this->capacity; }
    public function setCapacity(?int $value): void { $this->capacity = $value; }
    public function isPublished(): bool { return $this->published; }
    public function setPublished(bool $value): void { $this->published = $value; }
    public function isCancelled(): bool { return $this->cancelled; }
    public function cancel(): void { $this->cancelled = true; }
    public function copyDetailsFrom(self $other): void
    {
        $this->title = $other->title; $this->description = $other->description; $this->pricing = $other->pricing;
        $this->memberFeeCents = $other->memberFeeCents; $this->guestFeeCents = $other->guestFeeCents;
        $this->startsAt = $other->startsAt; $this->endsAt = $other->endsAt; $this->deadline = $other->deadline;
        $this->location = $other->location; $this->capacity = $other->capacity; $this->published = $other->published;
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        if ($this->startsAt === null || $this->endsAt === null || $this->endsAt <= $this->startsAt) {
            $context->buildViolation('Beginn und ein späteres Ende sind erforderlich.')->atPath('startsAt')->addViolation();
        }
        if ($this->deadline !== null && $this->startsAt !== null && $this->deadline > $this->startsAt) {
            $context->buildViolation('Die Anmeldefrist darf nicht nach dem Beginn liegen.')->atPath('deadline')->addViolation();
        }
        if (($this->pricing === 'members_free' && ($this->memberFeeCents !== 0 || $this->guestFeeCents !== 0))
            || ($this->pricing === 'paid' && ($this->memberFeeCents <= 0 || $this->guestFeeCents <= 0))
            || $this->memberFeeCents > 999999999 || $this->guestFeeCents > 999999999) {
            $context->buildViolation('Kostenfreie Mitgliedsveranstaltungen benötigen zwei Nullbeträge; kostenpflichtige Veranstaltungen zwei positive Beträge.')->atPath('pricing')->addViolation();
        }
    }
}
