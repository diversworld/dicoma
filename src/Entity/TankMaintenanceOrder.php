<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'tank_maintenance_order')]
#[ORM\Index(columns: ['tank_id', 'status'], name: 'idx_tank_maintenance_open')]
class TankMaintenanceOrder
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Tank::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'RESTRICT')]
    private ?Tank $tank = null;

    #[ORM\ManyToOne(targetEntity: EquipmentItem::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'RESTRICT')]
    private ?EquipmentItem $equipmentItem = null;

    #[ORM\ManyToOne(targetEntity: Regulator::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'RESTRICT')]
    private ?Regulator $regulator = null;

    #[ORM\Column(length: 20, options: ['default' => 'maintenance'])]
    private string $kind = 'maintenance';

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $responsible = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $openedBy = null;

    #[ORM\Column(length: 20)]
    private string $status = 'open';

    #[ORM\Column(type: 'text')]
    private string $description;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $closedAt = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $closedBy = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $resolution = null;

    public function __construct(EquipmentItem|Regulator|Tank $tank, string $description, string $kind = 'maintenance', ?string $responsible = null, ?string $actor = null)
    {
        $description = trim($description);
        if (mb_strlen($description) < 10) {
            throw new \DomainException('Die Wartungsbeschreibung muss mindestens 10 Zeichen enthalten.');
        }
        if (!in_array($kind, ['maintenance', 'repair', 'defect'], true)) { throw new \DomainException('Ungültige Auftragsart.'); }
        if ($tank instanceof EquipmentItem) { $this->equipmentItem = $tank; }
        elseif ($tank instanceof Regulator) { $this->regulator = $tank; }
        else { $this->tank = $tank; }
        $this->kind = $kind;
        $this->responsible = $responsible;
        $this->openedBy = $actor;
        $this->description = $description;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getTank(): ?Tank { return $this->tank; }
    public function getAsset(): EquipmentItem|Regulator|Tank { return $this->equipmentItem ?? $this->regulator ?? $this->tank; }
    public function getKind(): string { return $this->kind; }
    public function getResponsible(): ?string { return $this->responsible; }
    public function getOpenedBy(): ?string { return $this->openedBy; }
    public function getStatus(): string { return $this->status; }
    public function getDescription(): string { return $this->description; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getClosedAt(): ?\DateTimeImmutable { return $this->closedAt; }
    public function getClosedBy(): ?string { return $this->closedBy; }
    public function getResolution(): ?string { return $this->resolution; }

    public function close(string $actor, string $resolution): void
    {
        if ($this->status !== 'open') { throw new \DomainException('Wartungsauftrag bereits abgeschlossen.'); }
        if (mb_strlen(trim($resolution)) < 10) { throw new \DomainException('Abschlussbegründung zu kurz.'); }
        $this->status = 'closed';
        $this->closedAt = new \DateTimeImmutable();
        $this->closedBy = $actor;
        $this->resolution = trim($resolution);
    }
}
