<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_finance_annual_close', columns: ['club_id', 'year'])]
class FinanceAnnualClose
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Club $club;
    #[ORM\Column(name: 'year')]
    private int $year;
    #[ORM\Column]
    private int $incomeCents;
    #[ORM\Column]
    private int $expenseCents;
    #[ORM\Column(type: 'json')]
    private array $balances;
    #[ORM\Column(length: 64)]
    private string $sha256;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private User $closedBy;
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $closedAt;
    public function __construct(Club $club, int $year, int $income, int $expense, array $balances, string $hash, User $closedBy)
    {
        if ($year < 2000 || $year > 2200 || $hash === '') { throw new \DomainException('Ungültiger Jahresabschluss.'); }
        $this->club = $club; $this->year = $year; $this->incomeCents = $income; $this->expenseCents = $expense;
        $this->balances = $balances; $this->sha256 = $hash; $this->closedBy = $closedBy; $this->closedAt = new \DateTimeImmutable();
    }
    public function getId(): ?int { return $this->id; }
    public function getClub(): Club { return $this->club; }
    public function getYear(): int { return $this->year; }
    public function getIncomeCents(): int { return $this->incomeCents; }
    public function getExpenseCents(): int { return $this->expenseCents; }
    public function getResultCents(): int { return $this->incomeCents - $this->expenseCents; }
    public function getBalances(): array { return $this->balances; }
    public function getSha256(): string { return $this->sha256; }
    public function getClosedBy(): User { return $this->closedBy; }
    public function getClosedAt(): \DateTimeImmutable { return $this->closedAt; }
}
