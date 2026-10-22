<?php
declare(strict_types=1);
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\Table(name: 'compressor_fill_log')]
#[ORM\Index(columns: ['club_id', 'filled_at'], name: 'idx_fill_club_date')]
class CompressorFillLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne(targetEntity: Club::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Club $club;
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private User $recordedBy;
    #[ORM\ManyToOne(targetEntity: Tank::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Tank $tank = null;
    #[ORM\Column(length: 100)]
    private string $cylinderIdentification;
    #[ORM\Column(length: 100)]
    private string $compressorIdentification;
    #[ORM\Column(length: 30)]
    private string $gasType = 'air';
    #[ORM\Column]
    private int $startPressureBar;
    #[ORM\Column]
    private int $endPressureBar;
    #[ORM\Column(nullable: true)]
    private ?int $operatingHoursTenths = null;
    #[ORM\Column(length: 120, nullable: true)]
    private ?string $filterReference = null;
    #[ORM\Column]
    private bool $cylinderCheckConfirmed = false;
    #[ORM\Column]
    private bool $airQualityConfirmed = false;
    #[ORM\Column]
    private bool $filterCheckConfirmed = false;
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;
    #[ORM\Column]
    private \DateTimeImmutable $filledAt;
    #[ORM\Column]
    private \DateTimeImmutable $createdAt;
    public function __construct(Club $club, User $user, string $cylinder, string $compressor, int $start, int $end)
    {
        $this->club=$club; $this->recordedBy=$user;
        $this->cylinderIdentification=$cylinder; $this->compressorIdentification=$compressor;
        $this->startPressureBar=$start; $this->endPressureBar=$end;
        $this->filledAt=new \DateTimeImmutable(); $this->createdAt=new \DateTimeImmutable();
    }
    public function getId(): ?int { return $this->id; }
    public function getClub(): Club { return $this->club; }
    public function getRecordedBy(): User { return $this->recordedBy; }
    public function getTank(): ?Tank { return $this->tank; }
    public function setTank(?Tank $tank): void { $this->tank=$tank; }
    public function getCylinderIdentification(): string { return $this->cylinderIdentification; }
    public function getCompressorIdentification(): string { return $this->compressorIdentification; }
    public function getGasType(): string { return $this->gasType; }
    public function getStartPressureBar(): int { return $this->startPressureBar; }
    public function getEndPressureBar(): int { return $this->endPressureBar; }
    public function getOperatingHoursTenths(): ?int { return $this->operatingHoursTenths; }
    public function setOperatingHoursTenths(?int $v): void { $this->operatingHoursTenths=$v; }
    public function getFilterReference(): ?string { return $this->filterReference; }
    public function setFilterReference(?string $v): void { $this->filterReference=$v; }
    public function isCylinderCheckConfirmed(): bool { return $this->cylinderCheckConfirmed; }
    public function setCylinderCheckConfirmed(bool $v): void { $this->cylinderCheckConfirmed=$v; }
    public function isAirQualityConfirmed(): bool { return $this->airQualityConfirmed; }
    public function setAirQualityConfirmed(bool $v): void { $this->airQualityConfirmed=$v; }
    public function isFilterCheckConfirmed(): bool { return $this->filterCheckConfirmed; }
    public function setFilterCheckConfirmed(bool $v): void { $this->filterCheckConfirmed=$v; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $v): void { $this->notes=$v; }
    public function getFilledAt(): \DateTimeImmutable { return $this->filledAt; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
