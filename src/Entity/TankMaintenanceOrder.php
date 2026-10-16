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
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Tank $tank;

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

    public function __construct(Tank $tank, string $description)
    {
        $description = trim($description);
        if (mb_strlen($description) < 10) {
            throw new \DomainException('Die Wartungsbeschreibung muss mindestens 10 Zeichen enthalten.');
        }
        $this->tank = $tank;
        $this->description = $description;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getTank(): Tank { return $this->tank; }
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
