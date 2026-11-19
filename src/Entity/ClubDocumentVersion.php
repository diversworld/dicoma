<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(uniqueConstraints: [new ORM\UniqueConstraint(name: 'uniq_club_document_version', columns: ['document_id', 'version'])])]
class ClubDocumentVersion
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'versions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ClubDocument $document;

    #[ORM\Column]
    private int $version;

    #[ORM\Column(length: 255)]
    private string $storageName;

    #[ORM\Column(length: 255)]
    private string $originalName;

    #[ORM\Column(length: 100)]
    private string $mimeType;

    #[ORM\Column]
    private int $size;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private User $uploadedBy;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $uploadedAt;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $approvedBy = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $approvedAt = null;

    public function __construct(ClubDocument $document, int $version, string $storageName, string $originalName, string $mimeType, int $size, User $uploadedBy)
    {
        if ($version < 1 || $storageName === '' || $originalName === '' || $size < 0) {
            throw new \InvalidArgumentException('Ungültige Dokumentversion.');
        }
        $this->document = $document;
        $this->version = $version;
        $this->storageName = $storageName;
        $this->originalName = $originalName;
        $this->mimeType = $mimeType;
        $this->size = $size;
        $this->uploadedBy = $uploadedBy;
        $this->uploadedAt = new \DateTimeImmutable();
    }

    public function __toString(): string { return sprintf('Version %d – %s', $this->version, $this->originalName); }
    public function getId(): ?int { return $this->id; }
    public function getDocument(): ClubDocument { return $this->document; }
    public function setDocument(ClubDocument $document): static { $this->document = $document; return $this; }
    public function getVersion(): int { return $this->version; }
    public function getStorageName(): string { return $this->storageName; }
    public function getOriginalName(): string { return $this->originalName; }
    public function getMimeType(): string { return $this->mimeType; }
    public function getSize(): int { return $this->size; }
    public function getUploadedBy(): User { return $this->uploadedBy; }
    public function getUploadedAt(): \DateTimeImmutable { return $this->uploadedAt; }
    public function getApprovedBy(): ?User { return $this->approvedBy; }
    public function getApprovedAt(): ?\DateTimeImmutable { return $this->approvedAt; }
    public function approve(User $approver): void
    {
        if ($this->approvedAt !== null) {
            throw new \DomainException('Diese Dokumentversion wurde bereits freigegeben.');
        }
        $this->approvedBy = $approver;
        $this->approvedAt = new \DateTimeImmutable();
    }
}
