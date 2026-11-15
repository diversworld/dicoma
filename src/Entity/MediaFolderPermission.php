<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_folder_function_permission', columns: ['club_function_id', 'folder_id'])]
class MediaFolderPermission
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ClubFunction $clubFunction;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private MediaFolder $folder;

    #[ORM\Column(nullable: true)]
    private ?bool $canRead = null;
    #[ORM\Column(nullable: true)]
    private ?bool $canUpload = null;
    #[ORM\Column(nullable: true)]
    private ?bool $canEdit = null;
    #[ORM\Column(nullable: true)]
    private ?bool $canDelete = null;

    #[ORM\Version, ORM\Column(type: 'integer')]
    private int $version = 1;

    public function __construct(ClubFunction $clubFunction, MediaFolder $folder)
    {
        $club = $clubFunction->getClub();
        $folderClub = $folder->getClub();
        if ($club !== $folderClub && ($club->getId() === null || $club->getId() !== $folderClub->getId())) {
            throw new \InvalidArgumentException('Funktion und Ordner gehören nicht zum selben Verein.');
        }
        $this->clubFunction = $clubFunction;
        $this->folder = $folder;
    }

    public function getId(): ?int { return $this->id; }
    public function getClubFunction(): ClubFunction { return $this->clubFunction; }
    public function getFolder(): MediaFolder { return $this->folder; }
    public function getVersion(): int { return $this->version; }

    public function permission(FileAction $action): ?bool
    {
        return match ($action) {
            FileAction::READ => $this->canRead,
            FileAction::UPLOAD => $this->canUpload,
            FileAction::EDIT => $this->canEdit,
            FileAction::DELETE => $this->canDelete,
        };
    }

    public function setPermission(FileAction $action, ?bool $value): void
    {
        match ($action) {
            FileAction::READ => $this->canRead = $value,
            FileAction::UPLOAD => $this->canUpload = $value,
            FileAction::EDIT => $this->canEdit = $value,
            FileAction::DELETE => $this->canDelete = $value,
        };
    }
}
