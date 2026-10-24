<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'member_document')]
#[ORM\UniqueConstraint(name: 'UNIQ_MEMBER_DOCUMENT_STORAGE', columns: ['storage_name'])]
#[ORM\Index(name: 'IDX_MEMBER_DOCUMENT_MEMBER', columns: ['member_id'])]
class MemberDocument
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Member::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Member $member = null;

    #[ORM\Column(length: 64)]
    private string $storageName = '';

    #[ORM\Column(length: 255)]
    private string $originalName = '';

    #[ORM\Column(length: 100)]
    private string $mimeType = '';

    #[ORM\Column]
    private int $fileSize = 0;

    #[ORM\Column(length: 30, options: ['default' => 'other'])]
    private string $kind = 'other';

    public static function kinds(): array
    {
        return ['Sonstiges' => 'other', 'Mitgliedsantrag' => 'application', 'Tauchtauglichkeit' => 'medical', 'Einwilligung' => 'consent'];
    }
    public function getKind(): string { return $this->kind; }
    public function setKind(string $value): void
    {
        if (!in_array($value, self::kinds(), true)) { throw new \InvalidArgumentException('Unbekannte Dokumentart.'); }
        $this->kind = $value;
    }

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(Member $member, string $storageName, string $originalName, string $mimeType, int $fileSize)
    {
        $this->member = $member;
        $this->storageName = $storageName;
        $this->originalName = $originalName;
        $this->mimeType = $mimeType;
        $this->fileSize = $fileSize;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getMember(): ?Member { return $this->member; }
    public function getStorageName(): string { return $this->storageName; }
    public function getOriginalName(): string { return $this->originalName; }
    public function getMimeType(): string { return $this->mimeType; }
    public function getFileSize(): int { return $this->fileSize; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
