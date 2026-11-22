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
    #[ORM\Column(length: 40, options: ['default' => 'custom'])]
    private string $chart = 'custom';
    #[ORM\Column(length: 80, nullable: true)]
    private ?string $sphere = null;
    #[ORM\Column(name: 'usage_label', length: 120, nullable: true)]
    private ?string $usage = null;
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $sourceReference = null;

    public function __construct(Club $club, string $code, string $name, string $type, string $chart = 'custom', ?string $sphere = null, ?string $usage = null, ?string $sourceReference = null)
    {
        if (!in_array($type, self::types(), true) || trim($code) === '' || trim($name) === '') { throw new \DomainException('Ungültiges Konto.'); }
        $this->club = $club;
        $this->code = strtoupper(trim($code));
        $this->name = trim($name);
        $this->type = $type;
        $this->createdAt = new \DateTimeImmutable();
        $this->chart = trim($chart);
        $this->sphere = $sphere;
        $this->usage = $usage;
        $this->sourceReference = $sourceReference;
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
    public function getChart(): string { return $this->chart; }
    public function getSphere(): ?string { return $this->sphere; }
    public function getUsage(): ?string { return $this->usage; }
    public function getSourceReference(): ?string { return $this->sourceReference; }
    public function applyChartMetadata(string $chart, ?string $sphere, ?string $usage, ?string $sourceReference): void
    {
        $this->chart = trim($chart);
        $this->sphere = $sphere;
        $this->usage = $usage;
        $this->sourceReference = $sourceReference;
    }
    /** @return list<string> */
    public static function types(): array { return [self::TYPE_ASSET, self::TYPE_LIABILITY, self::TYPE_EQUITY, self::TYPE_INCOME, self::TYPE_EXPENSE]; }
}
