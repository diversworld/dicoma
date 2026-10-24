<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\TournamentStatus;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
class Tournament
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\Version, ORM\Column(type: 'integer')]
    private int $version = 1;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Club $club;
    #[ORM\Column(length: 150)]
    #[Assert\NotBlank, Assert\Length(max: 150)]
    private string $name = '';
    #[ORM\Column(length: 80)]
    #[Assert\NotBlank, Assert\Length(max: 80)]
    private string $sportCode = '';
    #[ORM\Column(length: 150)]
    #[Assert\NotBlank, Assert\Length(max: 150)]
    private string $organizer = '';
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank, Assert\Length(max: 255)]
    private string $location = '';
    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 10000)]
    private ?string $description = null;
    #[ORM\Column(type: 'datetime_immutable')]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $startsAt = null;
    #[ORM\Column(type: 'datetime_immutable')]
    #[Assert\NotNull, Assert\GreaterThan(propertyPath: 'startsAt')]
    private ?\DateTimeImmutable $endsAt = null;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Assert\LessThanOrEqual(propertyPath: 'startsAt')]
    private ?\DateTimeImmutable $registrationDeadline = null;
    #[ORM\Column(length: 20, enumType: TournamentStatus::class)]
    private TournamentStatus $status = TournamentStatus::DRAFT;
    #[ORM\Column]
    #[Assert\Range(min: 0, max: 99999999)]
    private int $feeCents = 0;
    #[ORM\Column]
    #[Assert\Range(min: 2, max: 128)]
    private int $maxTeams = 16;
    #[ORM\Column]
    #[Assert\Range(min: 0, max: 100)]
    private int $minPlayers = 0;
    #[ORM\Column]
    #[Assert\Range(min: 1, max: 100), Assert\GreaterThanOrEqual(propertyPath: 'minPlayers')]
    private int $maxPlayers = 12;
    #[ORM\Column]
    #[Assert\Range(min: 0, max: 100)]
    private int $winPoints = 3;
    #[ORM\Column]
    #[Assert\Range(min: 0, max: 100)]
    private int $drawPoints = 1;
    #[ORM\Column]
    #[Assert\Range(min: 0, max: 100)]
    private int $lossPoints = 0;
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $archive = null;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $archivedAt = null;

    public function __construct(Club $club) { $this->club = $club; }
    public function __toString(): string { return $this->name; }
    public function getId(): ?int { return $this->id; }
    public function getVersion(): int { return $this->version; }
    public function getClub(): Club { return $this->club; }
    public function getName(): string { return $this->name; }
    public function setName(string $value): void { $this->name = trim($value); }
    public function getSportCode(): string { return $this->sportCode; }
    public function setSportCode(string $value): void { $this->sportCode = mb_strtolower(trim($value)); }
    public function getOrganizer(): string { return $this->organizer; }
    public function setOrganizer(string $value): void { $this->organizer = trim($value); }
    public function getLocation(): string { return $this->location; }
    public function setLocation(string $value): void { $this->location = trim($value); }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $value): void { $this->description = $value; }
    public function getStartsAt(): ?\DateTimeImmutable { return $this->startsAt; }
    public function setStartsAt(?\DateTimeImmutable $value): void { $this->startsAt = $value; }
    public function getEndsAt(): ?\DateTimeImmutable { return $this->endsAt; }
    public function setEndsAt(?\DateTimeImmutable $value): void { $this->endsAt = $value; }
    public function getRegistrationDeadline(): ?\DateTimeImmutable { return $this->registrationDeadline; }
    public function setRegistrationDeadline(?\DateTimeImmutable $value): void { $this->registrationDeadline = $value; }
    public function getStatus(): TournamentStatus { return $this->status; }
    public function setStatus(TournamentStatus $value): void { $this->status = $value; }
    public function getFeeCents(): int { return $this->feeCents; }
    public function setFeeCents(int $value): void { $this->feeCents = $value; }
    public function getMaxTeams(): int { return $this->maxTeams; }
    public function setMaxTeams(int $value): void { $this->maxTeams = $value; }
    public function getMinPlayers(): int { return $this->minPlayers; }
    public function setMinPlayers(int $value): void { $this->minPlayers = $value; }
    public function getMaxPlayers(): int { return $this->maxPlayers; }
    public function setMaxPlayers(int $value): void { $this->maxPlayers = $value; }
    public function getWinPoints(): int { return $this->winPoints; }
    public function setWinPoints(int $value): void { $this->winPoints = $value; }
    public function getDrawPoints(): int { return $this->drawPoints; }
    public function setDrawPoints(int $value): void { $this->drawPoints = $value; }
    public function getLossPoints(): int { return $this->lossPoints; }
    public function setLossPoints(int $value): void { $this->lossPoints = $value; }
    public function getArchive(): ?array { return $this->archive; }
    public function getArchivedAt(): ?\DateTimeImmutable { return $this->archivedAt; }
    public function archive(array $snapshot): void
    {
        if ($this->archive !== null) { throw new \DomainException('Das Turnierarchiv ist unveränderlich.'); }
        $this->archive = $snapshot;
        $this->archivedAt = new \DateTimeImmutable();
        $this->status = TournamentStatus::COMPLETED;
    }
}
