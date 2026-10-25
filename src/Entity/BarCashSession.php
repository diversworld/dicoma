<?php

declare(strict_types=1);
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'bar_cash_session')]
class BarCashSession
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\OneToOne, ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')] private BarShift $shift;
    #[ORM\Column] private int $openingCents;
    #[ORM\Column(length: 180)] private string $openedBy;
    #[ORM\Column(type: 'datetime_immutable')] private \DateTimeImmutable $openedAt;
    #[ORM\Column(length: 500)] private string $openingNote;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)] private ?\DateTimeImmutable $closedAt = null;
    #[ORM\Column(length: 180, nullable: true)] private ?string $closedBy = null;
    #[ORM\Column(nullable: true)] private ?int $expectedCents = null;
    #[ORM\Column(nullable: true)] private ?int $countedCents = null;
    #[ORM\Column(length: 500, nullable: true)] private ?string $closingNote = null;
    #[ORM\Column(length: 64, nullable: true)] private ?string $movementHash = null;
    public function __construct(BarShift $shift, int $opening, string $actor, string $note)
    {
        if ($opening < 0 || $opening > 999999999) { throw new \DomainException('Anfangsbestand ungültig.'); }
        $this->shift = $shift; $this->openingCents = $opening; $this->openedBy = $actor; $this->openingNote = $note; $this->openedAt = new \DateTimeImmutable();
    }
    public function getId(): ?int { return $this->id; }
    public function getShift(): BarShift { return $this->shift; }
    public function getOpeningCents(): int { return $this->openingCents; }
    public function getOpenedBy(): string { return $this->openedBy; }
    public function getOpenedAt(): \DateTimeImmutable { return $this->openedAt; }
    public function getOpeningNote(): string { return $this->openingNote; }
    public function getClosedAt(): ?\DateTimeImmutable { return $this->closedAt; }
    public function getClosedBy(): ?string { return $this->closedBy; }
    public function getExpectedCents(): ?int { return $this->expectedCents; }
    public function getCountedCents(): ?int { return $this->countedCents; }
    public function getClosingNote(): ?string { return $this->closingNote; }
    public function getMovementHash(): ?string { return $this->movementHash; }
    public function close(int $expected, int $counted, string $note, string $hash, string $actor): void
    {
        if ($this->closedAt !== null || $counted < 0 || $counted > 999999999 || ($expected !== $counted && mb_strlen(trim($note)) < 10)) { throw new \DomainException('Abschluss oder Differenzbegründung ungültig.'); }
        $this->expectedCents = $expected; $this->countedCents = $counted; $this->closingNote = $note;
        $this->movementHash = $hash; $this->closedBy = $actor; $this->closedAt = new \DateTimeImmutable();
    }
}
