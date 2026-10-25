<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class EquipmentDocument
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne(targetEntity: EquipmentAssetRecord::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private EquipmentAssetRecord $record;
    #[ORM\Column(length: 255)]
    private string $originalName;
    #[ORM\Column(length: 80)]
    private string $storageName;
    #[ORM\Column(length: 80)]
    private string $mimeType;
    #[ORM\Column]
    private int $size;
    #[ORM\Column(length: 180)]
    private string $uploadedBy;
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $uploadedAt;

    public function __construct(EquipmentAssetRecord $record, string $originalName, string $storageName, string $mimeType, int $size, string $actor)
    {
        $this->record = $record;
        $this->originalName = mb_substr(basename($originalName), 0, 255);
        $this->storageName = $storageName;
        $this->mimeType = $mimeType;
        $this->size = $size;
        $this->uploadedBy = mb_substr($actor, 0, 180);
        $this->uploadedAt = new \DateTimeImmutable();
    }
    public function getId(): ?int { return $this->id; }
    public function getRecord(): EquipmentAssetRecord { return $this->record; }
    public function getOriginalName(): string { return $this->originalName; }
    public function getStorageName(): string { return $this->storageName; }
    public function getMimeType(): string { return $this->mimeType; }
    public function getSize(): int { return $this->size; }
    public function getUploadedBy(): string { return $this->uploadedBy; }
    public function getUploadedAt(): \DateTimeImmutable { return $this->uploadedAt; }
}
