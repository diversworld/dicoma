<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class ClubEventDocument
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private ClubEvent $event;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: true, onDelete: 'RESTRICT')]
    private ?ClubEventRegistration $registration;
    #[ORM\Column(length: 80, unique: true)]
    private string $storageName;
    #[ORM\Column(length: 255)]
    private string $originalName;
    #[ORM\Column(length: 120)]
    private string $mimeType;
    #[ORM\Column]
    private int $size;
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(ClubEvent $event, ?ClubEventRegistration $registration, MediaFile $file)
    {
        if ($registration !== null && $registration->getEvent()->getId() !== $event->getId()) {
            throw new \InvalidArgumentException('Die Anmeldung gehört nicht zu dieser Veranstaltung.');
        }
        if ($event->getClub()->getId() !== $file->getClub()->getId()) {
            throw new \InvalidArgumentException('Die Datei gehört nicht zu diesem Verein.');
        }
        $this->event = $event;
        $this->registration = $registration;
        $this->storageName = $file->getStorageName();
        $this->originalName = $file->getOriginalName();
        $this->mimeType = $file->getMimeType();
        $this->size = $file->getSize();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getEvent(): ClubEvent { return $this->event; }
    public function getRegistration(): ?ClubEventRegistration { return $this->registration; }
    public function getOriginalName(): string { return $this->originalName; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function file(): MediaFile
    {
        return new MediaFile($this->event->getClub(), $this->storageName, $this->originalName, $this->mimeType, $this->size, '');
    }
}
