<?php

declare(strict_types=1);
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity]
#[ORM\Index(name: 'idx_club_event_listing', columns: ['club_id', 'published', 'starts_at'])]
#[ORM\Index(name: 'IDX_5B0B8D9A41859289', columns: ['division_id'])]
#[ORM\Index(name: 'IDX_5B0B8D9A602AD315', columns: ['responsible_id'])]
#[ORM\Index(name: 'IDX_5B0B8D9A7B4CFBA', columns: ['series_parent_id'])]
#[ORM\Index(name: 'idx_club_event_course', columns: ['course_id'])]
#[ORM\Index(name: 'idx_club_event_tournament', columns: ['tournament_id'])]
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
    #[ORM\ManyToOne, ORM\JoinColumn(onDelete: 'RESTRICT')] private ?MediaFile $image = null;
    #[ORM\Column(length: 20, options: ['default' => 'event'])] private string $eventType = 'event';
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: true, onDelete: 'RESTRICT')] private ?Sport $division = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')] private ?Member $responsible = null;
    #[ORM\Column(length: 10, nullable: true)] private ?string $recurrence = null;
    #[ORM\Column(type: 'date_immutable', nullable: true)] private ?\DateTimeImmutable $recurrenceUntil = null;
    #[ORM\ManyToOne(targetEntity: ClubEvent::class), ORM\JoinColumn(nullable: true, onDelete: 'RESTRICT')] private ?self $seriesParent = null;
    #[ORM\Column(options: ['default' => true])] private bool $registrationEnabled = true;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: true, onDelete: 'RESTRICT')] private ?Courses $course = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: true, onDelete: 'RESTRICT')] private ?Tournament $tournament = null;

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
    public function getImage(): ?MediaFile { return $this->image; }
    public function setImage(?MediaFile $value): void { $this->image = $value; }
    public function getEventType(): string { return $this->eventType; }
    public function setEventType(string $value): void { $this->eventType = $value; }
    public function getDivision(): ?Sport { return $this->division; }
    public function setDivision(?Sport $value): void { $this->division = $value; }
    public function getResponsible(): ?Member { return $this->responsible; }
    public function setResponsible(?Member $value): void { $this->responsible = $value; }
    public function getRecurrence(): ?string { return $this->recurrence; }
    public function setRecurrence(?string $value): void { $this->recurrence = $value ?: null; }
    public function getRecurrenceUntil(): ?\DateTimeImmutable { return $this->recurrenceUntil; }
    public function setRecurrenceUntil(?\DateTimeImmutable $value): void { $this->recurrenceUntil = $value; }
    public function getSeriesParent(): ?self { return $this->seriesParent; }
    public function setSeriesParent(?self $value): void { $this->seriesParent = $value; }
    public function isRegistrationEnabled(): bool { return $this->registrationEnabled; }
    public function setRegistrationEnabled(bool $value): void { $this->registrationEnabled = $value; }
    public function getCourse(): ?Courses { return $this->course; }
    public function setCourse(?Courses $value): void { $this->course = $value; }
    public function getTournament(): ?Tournament { return $this->tournament; }
    public function setTournament(?Tournament $value): void { $this->tournament = $value; }
    public function cancel(): void { $this->cancelled = true; }
    public function copyDetailsFrom(self $other): void
    {
        $this->title = $other->title; $this->description = $other->description; $this->pricing = $other->pricing;
        $this->memberFeeCents = $other->memberFeeCents; $this->guestFeeCents = $other->guestFeeCents;
        $this->startsAt = $other->startsAt; $this->endsAt = $other->endsAt; $this->deadline = $other->deadline;
        $this->location = $other->location; $this->capacity = $other->capacity; $this->published = $other->published;
        $this->image = $other->image;
        $this->eventType = $other->eventType;
        $this->division = $other->division;
        $this->responsible = $other->responsible;
        $this->recurrence = $other->recurrence;
        $this->recurrenceUntil = $other->recurrenceUntil;
        $this->registrationEnabled = $other->registrationEnabled;
        $this->course = $other->course;
        $this->tournament = $other->tournament;
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        if ($this->image !== null && (!$this->image->isImage() || $this->image->getClub()->getId() !== $this->club->getId())) {
            $context->buildViolation('Nur Bilder des eigenen Vereins können ausgewählt werden.')->atPath('image')->addViolation();
        }
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
        if (!in_array($this->eventType, ['event', 'course', 'tournament', 'dive', 'trip', 'other'], true)) {
            $context->buildViolation('Ungültige Veranstaltungsart.')->atPath('eventType')->addViolation();
        }
        if ($this->division !== null && $this->division->getClub()?->getId() !== $this->club->getId()) {
            $context->buildViolation('Die Sparte muss zum Veranstaltungsverein gehören.')->atPath('division')->addViolation();
        }
        if ($this->responsible !== null && $this->responsible->getClub()?->getId() !== $this->club->getId()) {
            $context->buildViolation('Die verantwortliche Person muss zum Veranstaltungsverein gehören.')->atPath('responsible')->addViolation();
        }
        if (($this->recurrence !== null && !in_array($this->recurrence, ['weekly', 'monthly'], true))
            || ($this->recurrence === null && $this->recurrenceUntil !== null)
            || ($this->recurrence !== null && ($this->recurrenceUntil === null || $this->startsAt === null
                || $this->recurrenceUntil < $this->startsAt->setTime(0, 0)))) {
            $context->buildViolation('Wiederholung und gültiges Enddatum gemeinsam angeben.')->atPath('recurrenceUntil')->addViolation();
        }
        if ($this->course !== null && $this->tournament !== null) {
            $context->buildViolation('Eine Veranstaltung kann höchstens ein Fachmodul referenzieren.')->atPath('course')->addViolation();
        }
        foreach ([$this->course, $this->tournament] as $source) {
            if ($source !== null && $source->getClub()?->getId() !== $this->club->getId()) {
                $context->buildViolation('Verknüpftes Fachmodul muss zum selben Verein gehören.')->atPath('course')->addViolation();
            }
        }
    }
}
