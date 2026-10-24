<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
class TournamentMatch
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\Version, ORM\Column(type: 'integer')]
    private int $version = 1;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Tournament $tournament;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull]
    private ?TournamentEntry $home = null;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull]
    private ?TournamentEntry $away = null;
    #[ORM\Column]
    #[Assert\Range(min: 1, max: 999)]
    private int $roundNumber = 1;
    #[ORM\Column]
    #[Assert\Range(min: 5, max: 360)]
    private int $durationMinutes = 20;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $startsAt = null;
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $location = null;
    #[ORM\Column(nullable: true)]
    private ?int $homeScore = null;
    #[ORM\Column(nullable: true)]
    private ?int $awayScore = null;
    #[ORM\Column(options: ['default' => false])]
    private bool $cancelled = false;
    #[ORM\Column(type: 'json')]
    private array $resultHistory = [];

    public function __construct(Tournament $tournament) { $this->tournament = $tournament; }
    public function getId(): ?int { return $this->id; }
    public function getVersion(): int { return $this->version; }
    public function getTournament(): Tournament { return $this->tournament; }
    public function getHome(): ?TournamentEntry { return $this->home; }
    public function setHome(?TournamentEntry $value): void { $this->home = $value; }
    public function getAway(): ?TournamentEntry { return $this->away; }
    public function setAway(?TournamentEntry $value): void { $this->away = $value; }
    public function getRoundNumber(): int { return $this->roundNumber; }
    public function setRoundNumber(int $value): void { $this->roundNumber = $value; }
    public function getDurationMinutes(): int { return $this->durationMinutes; }
    public function setDurationMinutes(int $value): void { $this->durationMinutes = $value; }
    public function getStartsAt(): ?\DateTimeImmutable { return $this->startsAt; }
    public function setStartsAt(?\DateTimeImmutable $value): void { $this->startsAt = $value; }
    public function getLocation(): ?string { return $this->location; }
    public function setLocation(?string $value): void { $this->location = $value; }
    public function getHomeScore(): ?int { return $this->homeScore; }
    public function getAwayScore(): ?int { return $this->awayScore; }
    public function isCancelled(): bool { return $this->cancelled; }
    public function setCancelled(bool $value): void { $this->cancelled = $value; }
    public function getResultHistory(): array { return $this->resultHistory; }
    public function recordResult(int $home, int $away, string $actor, string $reason): void
    {
        if ($home < 0 || $away < 0 || $home > 9999 || $away > 9999 || trim($reason) === '' || mb_strlen($reason) > 255) {
            throw new \DomainException('Ergebnisse benötigen gültige Punkte und eine Begründung (maximal 255 Zeichen).');
        }
        $this->resultHistory[] = ['home' => $home, 'away' => $away, 'actor' => $actor, 'reason' => $reason, 'at' => (new \DateTimeImmutable())->format(DATE_ATOM)];
        $this->homeScore = $home; $this->awayScore = $away;
    }
}
