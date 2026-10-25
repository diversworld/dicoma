<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class EquipmentFillCharge
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne(targetEntity: Club::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Club $club;
    #[ORM\OneToOne(targetEntity: CompressorFillLog::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'RESTRICT')]
    private ?CompressorFillLog $log = null;
    #[ORM\OneToOne(targetEntity: CompressorFillBatch::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'RESTRICT')]
    private ?CompressorFillBatch $batch = null;
    #[ORM\Column]
    private int $amountCents;
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $method = null;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $collectedAt = null;
    #[ORM\Column(length: 180, nullable: true)]
    private ?string $collectedBy = null;

    public function __construct(Club $club, CompressorFillLog|CompressorFillBatch $source, int $amountCents)
    {
        if ($amountCents < 0 || $amountCents > 999999999) { throw new \DomainException('Ungültige Füllgebühr.'); }
        $this->club = $club;
        if ($source instanceof CompressorFillLog) { $this->log = $source; } else { $this->batch = $source; }
        $this->amountCents = $amountCents;
    }
    public function collect(string $method, string $actor): void
    {
        if ($this->collectedAt !== null || $this->amountCents === 0 || !in_array($method, ['cash', 'bank'], true)) { throw new \DomainException('Füllung ist kostenlos, schon bezahlt oder Zahlungsart ungültig.'); }
        $this->method = $method;
        $this->collectedAt = new \DateTimeImmutable();
        $this->collectedBy = mb_substr($actor, 0, 180);
    }
    public function getId(): ?int { return $this->id; }
    public function getClub(): Club { return $this->club; }
    public function getLabel(): string { return $this->log ? 'Einzelfüllung #'.$this->log->getId() : 'Viererfüllung #'.$this->batch->getId(); }
    public function getAmountCents(): int { return $this->amountCents; }
    public function getMethod(): ?string { return $this->method; }
    public function getCollectedAt(): ?\DateTimeImmutable { return $this->collectedAt; }
    public function getCollectedBy(): ?string { return $this->collectedBy; }
}
