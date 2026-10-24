<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class TournamentDocument
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Tournament $tournament;
    #[ORM\Column(length: 255)]
    private string $originalName;
    #[ORM\Column(length: 80)]
    private string $storageName;
    #[ORM\Column(length: 100)]
    private string $mimeType;
    #[ORM\Column]
    private int $size;
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;
    public function __construct(Tournament $tournament, string $originalName, string $storageName, string $mimeType, int $size)
    {
        $this->tournament = $tournament; $this->originalName = $originalName; $this->storageName = $storageName;
        $this->mimeType = $mimeType; $this->size = $size; $this->createdAt = new \DateTimeImmutable();
    }
    public function getId(): ?int { return $this->id; }
    public function getTournament(): Tournament { return $this->tournament; }
    public function getOriginalName(): string { return $this->originalName; }
    public function getStorageName(): string { return $this->storageName; }
    public function getMimeType(): string { return $this->mimeType; }
    public function getSize(): int { return $this->size; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
