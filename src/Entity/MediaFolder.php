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
        $this->club = $club;
        $this->rename($name);
        $this->moveTo($parent);
    }

    public function rename(string $name): void
    {
        $name = trim($name);
        if ($name === '' || $name === '.' || $name === '..' || mb_strlen($name) > 120 || preg_match('/[\/\\\\\x00-\x1f\x7f]/u', $name) !== 0) {
            throw new \InvalidArgumentException('Der Ordnername muss 1 bis 120 Zeichen lang sein und darf keine Pfad- oder Steuerzeichen enthalten.');
        }
        $this->name = $name;
    }

    public function moveTo(?self $parent): void
    {
        if ($parent !== null && $parent->getClub() !== $this->club
            && ($this->club->getId() === null || $parent->getClub()->getId() !== $this->club->getId())) {
            throw new \InvalidArgumentException('Der übergeordnete Ordner gehört zu einem anderen Verein.');
        }
        if ($parent !== null && $parent->isWithin($this)) {
            throw new \InvalidArgumentException('Ein Ordner kann nicht in sich selbst oder einen seiner Unterordner verschoben werden.');
        }
        $this->parent = $parent;
    }

    public function isWithin(self $ancestor): bool
    {
        $seen = [];
        for ($folder = $this; $folder !== null; $folder = $folder->parent) {
            if ($folder === $ancestor || ($folder->id !== null && $folder->id === $ancestor->id)) {
                return true;
            }
            $key = spl_object_id($folder);
            if (isset($seen[$key])) {
                throw new \LogicException('Die Ordnerhierarchie enthält einen Zyklus.');
            }
            $seen[$key] = true;
        }
        return false;
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
