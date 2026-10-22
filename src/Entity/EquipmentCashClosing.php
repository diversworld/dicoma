<?php
declare(strict_types=1);
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'equipment_cash_closing')]
#[ORM\UniqueConstraint(name: 'uniq_cash_closing_club_day', columns: ['club_id', 'closing_date'])]
class EquipmentCashClosing
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\ManyToOne(targetEntity: Club::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')] private Club $club;
    #[ORM\Column(type: 'date_immutable')] private \DateTimeImmutable $closingDate;
    #[ORM\Column(type: 'integer')] private int $openingCents;
    #[ORM\Column(type: 'integer')] private int $cashInCents;
    #[ORM\Column(type: 'integer')] private int $cashOutCents;
    #[ORM\Column(type: 'integer')] private int $expectedCents;
    #[ORM\Column(type: 'integer')] private int $countedCents;
    #[ORM\Column(type: 'integer')] private int $differenceCents;
    #[ORM\Column(length: 64)] private string $movementHash;
    #[ORM\Column(type: 'datetime_immutable')] private \DateTimeImmutable $closedAt;
    #[ORM\Column(length: 180)] private string $closedBy;
    #[ORM\Column(length: 500, nullable: true)] private ?string $note;
    #[ORM\Column(length: 180, nullable: true)] private ?string $handoverTo;
    public function __construct(Club $club, \DateTimeImmutable $date, int $opening, int $in, int $out, int $counted, string $hash, string $by, ?string $note, ?string $handover)
    {
        $this->club=$club; $this->closingDate=$date; $this->openingCents=$opening; $this->cashInCents=$in; $this->cashOutCents=$out;
        $this->expectedCents=$opening+$in-$out; $this->countedCents=$counted; $this->differenceCents=$counted-$this->expectedCents;
        $this->movementHash=$hash; $this->closedAt=new \DateTimeImmutable(); $this->closedBy=mb_substr($by,0,180);
        $this->note=$note ?: null; $this->handoverTo=$handover ?: null;
    }
    public function getId(): ?int {return $this->id;}
    public function getClosingDate(): \DateTimeImmutable {return $this->closingDate;}
    public function getOpeningCents(): int {return $this->openingCents;}
    public function getCashInCents(): int {return $this->cashInCents;}
    public function getCashOutCents(): int {return $this->cashOutCents;}
    public function getExpectedCents(): int {return $this->expectedCents;}
    public function getCountedCents(): int {return $this->countedCents;}
    public function getDifferenceCents(): int {return $this->differenceCents;}
    public function getMovementHash(): string {return $this->movementHash;}
    public function getClosedAt(): \DateTimeImmutable {return $this->closedAt;}
    public function getClosedBy(): string {return $this->closedBy;}
    public function getNote(): ?string {return $this->note;}
    public function getHandoverTo(): ?string {return $this->handoverTo;}
}
