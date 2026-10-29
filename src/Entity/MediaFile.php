<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Index(name: 'idx_media_file_club', columns: ['club_id'])]
class MediaFile
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Club $club;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: true, onDelete: 'RESTRICT')]
    private ?MediaFolder $folder = null;
    #[ORM\Column(length: 80, unique: true)]
    private string $storageName;
    #[ORM\Column(length: 255), Assert\NotBlank, Assert\Length(max: 255), Assert\Regex(pattern: '/^[^\/\\\\\x00-\x1f\x7f]+$/u', message: 'Der Dateiname darf keine Pfade oder Steuerzeichen enthalten.')]
    private string $originalName;
    #[ORM\Column(length: 120)]
    private string $mimeType;
    #[ORM\Column]
    private int $size;
    #[ORM\Column(length: 255), Assert\Length(max: 255)]
    private string $altText;
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(Club $club, string $storageName, string $originalName, string $mimeType, int $size, string $altText)
    {
        $this->club = $club;
        $this->storageName = $storageName;
        $this->originalName = $originalName;
        $this->mimeType = $mimeType;
        $this->size = $size;
        $this->altText = $altText;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getClub(): Club { return $this->club; }
    public function getFolder(): ?MediaFolder { return $this->folder; }
    public function setFolder(?MediaFolder $folder): void
    {
        if ($folder !== null && $folder->getClub() !== $this->club
            && ($this->club->getId() === null || $folder->getClub()->getId() !== $this->club->getId())) {
            throw new \InvalidArgumentException('Der Ordner gehört zu einem anderen Verein.');
        }
        $this->folder = $folder;
    }
    public function getStorageName(): string { return $this->storageName; }
    public function getOriginalName(): string { return $this->originalName; }
    public function setOriginalName(?string $value): void { $this->originalName = trim($value ?? ''); }
    public function getMimeType(): string { return $this->mimeType; }
    public function getSize(): int { return $this->size; }
    public function getAltText(): string { return $this->altText; }
    public function setAltText(?string $value): void { $this->altText = trim($value ?? ''); }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function isImage(): bool { return in_array($this->mimeType, ['image/jpeg', 'image/png', 'image/webp'], true); }
}
