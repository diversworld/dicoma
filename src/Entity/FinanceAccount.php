<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_finance_account_code', columns: ['club_id', 'code'])]
class FinanceAccount
{
    public const TYPE_ASSET = 'asset';
    public const TYPE_LIABILITY = 'liability';
    public const TYPE_EQUITY = 'equity';
    public const TYPE_INCOME = 'income';
    public const TYPE_EXPENSE = 'expense';

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Club $club;
    #[ORM\Column(length: 20)]
    private string $code;
    #[ORM\Column(length: 120)]
    private string $name;
    #[ORM\Column(length: 20)]
    private string $type;
    #[ORM\Column]
    private bool $active = true;
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(Club $club, string $code, string $name, string $type)
    {
        if (!in_array($type, self::types(), true) || trim($code) === '' || trim($name) === '') { throw new \DomainException('Ungültiges Konto.'); }
        $this->club = $club;
        $this->code = strtoupper(trim($code));
        $this->name = trim($name);
        $this->type = $type;
        $this->createdAt = new \DateTimeImmutable();
    }
    public function __toString(): string { return $this->code.' – '.$this->name; }
    public function getId(): ?int { return $this->id; }
    public function getClub(): Club { return $this->club; }
    public function getCode(): string { return $this->code; }
    public function setCode(string $code): void { $this->code = strtoupper(trim($code)); }
    public function getName(): string { return $this->name; }
    public function setName(string $name): void { $this->name = trim($name); }
    public function getType(): string { return $this->type; }
    public function setType(string $type): void { if (!in_array($type, self::types(), true)) { throw new \DomainException('Ungültiger Kontotyp.'); } $this->type = $type; }
    public function isActive(): bool { return $this->active; }
    public function setActive(bool $active): void { $this->active = $active; }
    /** @return list<string> */
    public static function types(): array { return [self::TYPE_ASSET, self::TYPE_LIABILITY, self::TYPE_EQUITY, self::TYPE_INCOME, self::TYPE_EXPENSE]; }
}
