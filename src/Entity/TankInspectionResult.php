<?php

declare(strict_types=1);
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'tank_inspection_result')]
class TankInspectionResult
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne(targetEntity: Tank::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tank $tank;
    #[ORM\Column(length: 10)]
    private string $result;
    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $inspectedAt;
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;
    #[ORM\Column(length: 180)]
    private string $updatedBy;
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(Tank $tank, string $result, \DateTimeImmutable $date, ?string $notes, string $actor)
    {
        $this->tank = $tank;
        $this->result = $result;
        $this->inspectedAt = $date;
        $this->notes = $notes;
        $this->updatedBy = mb_substr($actor, 0, 180);
        $this->createdAt = new \DateTimeImmutable();
    }
    public function getId(): ?int { return $this->id; }
    public function getTank(): Tank { return $this->tank; }
    public function getResult(): string { return $this->result; }
    public function getInspectedAt(): \DateTimeImmutable { return $this->inspectedAt; }
    public function getNotes(): ?string { return $this->notes; }
    public function getUpdatedBy(): string { return $this->updatedBy; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
