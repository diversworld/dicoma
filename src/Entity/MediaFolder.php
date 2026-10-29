<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Index(name: 'idx_media_folder_club', columns: ['club_id'])]
class MediaFolder
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Club $club;

    #[ORM\ManyToOne(targetEntity: self::class), ORM\JoinColumn(nullable: true, onDelete: 'RESTRICT')]
    private ?self $parent;

    #[ORM\Column(length: 120)]
    private string $name;

    public function __construct(Club $club, string $name, ?self $parent = null)
    {
        $name = trim($name);
        if ($name === '' || $name === '.' || $name === '..' || mb_strlen($name) > 120 || preg_match('/[\/\\\\\x00-\x1f\x7f]/u', $name) !== 0) {
            throw new \InvalidArgumentException('Der Ordnername muss 1 bis 120 Zeichen lang sein und darf keine Pfad- oder Steuerzeichen enthalten.');
        }
        if ($parent !== null && $parent->getClub() !== $club
            && ($club->getId() === null || $parent->getClub()->getId() !== $club->getId())) {
            throw new \InvalidArgumentException('Der übergeordnete Ordner gehört zu einem anderen Verein.');
        }
        $this->club = $club;
        $this->name = $name;
        $this->parent = $parent;
    }

    public function getId(): ?int { return $this->id; }
    public function getClub(): Club { return $this->club; }
    public function getParent(): ?self { return $this->parent; }
    public function getName(): string { return $this->name; }

    public function getPath(): string
    {
        $parts = [];
        for ($folder = $this; $folder !== null; $folder = $folder->getParent()) {
            array_unshift($parts, $folder->getName());
        }
        return implode(' / ', $parts);
    }
}
