<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity]
#[ORM\Index(name: 'idx_website_publication_visibility', columns: ['club_id', 'published', 'publish_at'])]
#[ORM\Index(name: 'idx_website_publication_course', columns: ['course_id'])]
#[ORM\Index(name: 'idx_website_publication_tournament', columns: ['tournament_id'])]
class WebsitePublication
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\Version, ORM\Column(type: 'integer')] private int $version = 1;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')] private Club $club;
    #[ORM\Column(length: 20), Assert\Choice(choices: ['article', 'event'])] private string $kind = 'article';
    #[ORM\Column(length: 180), Assert\NotBlank, Assert\Length(max: 180)] private string $title = '';
    #[ORM\Column(length: 500), Assert\NotBlank, Assert\Length(max: 500)] private string $summary = '';
    #[ORM\Column(type: 'text'), Assert\NotBlank, Assert\Length(max: 50000)] private string $body = '';
    #[ORM\Column] private bool $published = false;
    #[ORM\Column(type: 'datetime_immutable'), Assert\NotNull] private ?\DateTimeImmutable $publishAt;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)] private ?\DateTimeImmutable $startsAt = null;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)] private ?\DateTimeImmutable $endsAt = null;
    #[ORM\Column(length: 255), Assert\Length(max: 255)] private string $location = '';
    #[ORM\ManyToOne, ORM\JoinColumn(onDelete: 'RESTRICT')] private ?Courses $course = null;
    #[ORM\ManyToOne, ORM\JoinColumn(onDelete: 'RESTRICT')] private ?Tournament $tournament = null;
    #[ORM\ManyToOne, ORM\JoinColumn(onDelete: 'RESTRICT')] private ?MediaFile $image = null;
    #[ORM\Column(type: 'json')] private array $history = [];

    public function __construct(Club $club) { $this->club = $club; $this->publishAt = new \DateTimeImmutable(); }
    public function getId(): ?int { return $this->id; }
    public function getVersion(): int { return $this->version; }
    public function getClub(): Club { return $this->club; }
    public function getKind(): string { return $this->kind; }
    public function setKind(?string $value): void { $this->kind = $value ?? ''; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(?string $value): void { $this->title = trim($value ?? ''); }
    public function getSummary(): string { return $this->summary; }
    public function setSummary(?string $value): void { $this->summary = trim($value ?? ''); }
    public function getBody(): string { return $this->body; }
    public function setBody(?string $value): void { $this->body = trim($value ?? ''); }
    public function isPublished(): bool { return $this->published; }
    public function setPublished(bool $value): void { $this->published = $value; }
    public function getPublishAt(): ?\DateTimeImmutable { return $this->publishAt; }
    public function setPublishAt(?\DateTimeImmutable $value): void { $this->publishAt = $value; }
    public function getStartsAt(): ?\DateTimeImmutable { return $this->startsAt; }
    public function setStartsAt(?\DateTimeImmutable $value): void { $this->startsAt = $value; }
    public function getEndsAt(): ?\DateTimeImmutable { return $this->endsAt; }
    public function setEndsAt(?\DateTimeImmutable $value): void { $this->endsAt = $value; }
    public function getLocation(): string { return $this->location; }
    public function setLocation(string $value): void { $this->location = trim($value); }
    public function getCourse(): ?Courses { return $this->course; }
    public function setCourse(?Courses $value): void { $this->course = $value; }
    public function getTournament(): ?Tournament { return $this->tournament; }
    public function setTournament(?Tournament $value): void { $this->tournament = $value; }
    public function getImage(): ?MediaFile { return $this->image; }
    public function setImage(?MediaFile $value): void { $this->image = $value; }
    public function getHistory(): array { return $this->history; }
    public function record(string $actor): void
    {
        $this->history[] = ['at' => (new \DateTimeImmutable())->format(DATE_ATOM), 'actor' => $actor, 'published' => $this->published, 'title' => $this->title, 'publishAt' => $this->publishAt->format(DATE_ATOM)];
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        if ($this->image !== null && (!$this->image->isImage() || $this->image->getClub()->getId() !== $this->club->getId())) {
            $context->buildViolation('Nur Bilder des eigenen Vereins können ausgewählt werden.')->atPath('image')->addViolation();
        }
        if ($this->kind === 'event' && ($this->startsAt === null || $this->endsAt === null || $this->endsAt <= $this->startsAt || $this->location === '')) {
            $context->buildViolation('Veranstaltungen benötigen Ort, Beginn und ein späteres Ende.')->atPath('startsAt')->addViolation();
        }
        if ($this->course !== null && $this->tournament !== null) {
            $context->buildViolation('Nur einen Kurs oder ein Turnier verknüpfen.')->atPath('course')->addViolation();
        }
        if ($this->kind !== 'event' && ($this->course !== null || $this->tournament !== null || $this->startsAt !== null || $this->endsAt !== null)) {
            $context->buildViolation('Termine und Fachmodul-Verknüpfungen sind nur bei Veranstaltungen zulässig.')->atPath('kind')->addViolation();
        }
        foreach ([$this->course, $this->tournament] as $source) {
            if ($source !== null && ($source->getClub()?->getId() !== $this->club->getId() || in_array($source->getStatus()->value, ['draft', 'cancelled'], true))) {
                $context->buildViolation('Verknüpfte Veranstaltung muss freigegeben sein und zum eigenen Verein gehören.')->atPath('kind')->addViolation();
            }
        }
    }
}
