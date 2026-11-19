<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'bar_stock_entry')]
class BarStockEntry
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] private BarShift $shift;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')] private Member $reportedBy;
    #[ORM\Column(length: 180)] private string $item;
    #[ORM\Column] private int $opening;
    #[ORM\Column] private int $consumed;
    #[ORM\Column] private int $closing;
    #[ORM\Column(type: 'text', nullable: true)] private ?string $notes = null;
    #[ORM\Column(type: 'datetime_immutable')] private \DateTimeImmutable $reportedAt;

    public function __construct(BarShift $shift, Member $member, string $item, int $opening, int $consumed, int $closing, ?string $notes)
    {
        $item = trim($item); $notes = trim((string) $notes);
        if ($item === '' || mb_strlen($item) > 180 || min($opening, $consumed, $closing) < 0
            || $opening - $consumed !== $closing || mb_strlen($notes) > 2000) {
            throw new \DomainException('Bestandsmeldung ist ungültig: Anfang minus Verbrauch muss dem Schlussbestand entsprechen.');
        }
        $this->shift = $shift; $this->reportedBy = $member; $this->item = $item;
        $this->opening = $opening; $this->consumed = $consumed; $this->closing = $closing;
        $this->notes = $notes === '' ? null : $notes; $this->reportedAt = new \DateTimeImmutable();
    }
    public function getId(): ?int { return $this->id; }
    public function getShift(): BarShift { return $this->shift; }
    public function getReportedBy(): Member { return $this->reportedBy; }
    public function getItem(): string { return $this->item; }
    public function getOpening(): int { return $this->opening; }
    public function getConsumed(): int { return $this->consumed; }
    public function getClosing(): int { return $this->closing; }
    public function getNotes(): ?string { return $this->notes; }
    public function getReportedAt(): \DateTimeImmutable { return $this->reportedAt; }
}
