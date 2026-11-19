<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Index(name: 'idx_club_document_category_status', columns: ['club_id', 'category', 'status'])]
class ClubDocument
{
    public const CATEGORY_STATUTES = 'statutes';
    public const CATEGORY_CONTRIBUTION_RULES = 'contribution_rules';
    public const CATEGORY_MINUTES = 'minutes';
    public const CATEGORY_ANNUAL_REPORT = 'annual_report';
    public const CATEGORY_FORM = 'form';
    public const CATEGORY_INVOICE = 'invoice';
    public const CATEGORY_OTHER = 'other';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_ARCHIVED = 'archived';

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Club $club;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank, Assert\Length(max: 180)]
    private string $title;

    #[ORM\Column(length: 40)]
    private string $category = self::CATEGORY_OTHER;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_DRAFT;

    #[ORM\Column]
    private bool $memberVisible = false;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private User $createdBy;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\OneToMany(mappedBy: 'document', targetEntity: ClubDocumentVersion::class, cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['version' => 'DESC'])]
    private Collection $versions;

    public function __construct(Club $club, User $createdBy, string $title)
    {
        $this->club = $club;
        $this->createdBy = $createdBy;
        $this->title = trim($title);
        if ($this->title === '') {
            throw new \InvalidArgumentException('Ein Dokumenttitel ist erforderlich.');
        }
        $this->createdAt = new \DateTimeImmutable();
        $this->versions = new ArrayCollection();
    }

    public function __toString(): string { return $this->title; }
    public function getId(): ?int { return $this->id; }
    public function getClub(): Club { return $this->club; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): static { $this->title = trim($title); return $this; }
    public function getCategory(): string { return $this->category; }
    public function setCategory(string $category): static
    {
        if (!in_array($category, array_values(self::categories()), true)) {
            throw new \InvalidArgumentException('Unbekannte Dokumentkategorie.');
        }
        $this->category = $category;
        return $this;
    }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static
    {
        if (!in_array($status, array_values(self::statuses()), true)) {
            throw new \InvalidArgumentException('Unbekannter Dokumentstatus.');
        }
        $this->status = $status;
        return $this;
    }
    public function isMemberVisible(): bool { return $this->memberVisible; }
    public function setMemberVisible(bool $memberVisible): static { $this->memberVisible = $memberVisible; return $this; }
    public function getCreatedBy(): User { return $this->createdBy; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getVersions(): Collection { return $this->versions; }
    public function addVersion(ClubDocumentVersion $version): static
    {
        if (!$this->versions->contains($version)) {
            $this->versions->add($version);
            $version->setDocument($this);
        }
        return $this;
    }
    /** @return array<string, string> */
    public static function categories(): array { return ['Satzung' => self::CATEGORY_STATUTES, 'Beitragsordnung' => self::CATEGORY_CONTRIBUTION_RULES, 'Protokoll' => self::CATEGORY_MINUTES, 'Jahresbericht' => self::CATEGORY_ANNUAL_REPORT, 'Formular' => self::CATEGORY_FORM, 'Rechnung' => self::CATEGORY_INVOICE, 'Sonstiges' => self::CATEGORY_OTHER]; }
    /** @return array<string, string> */
    public static function statuses(): array { return ['Entwurf' => self::STATUS_DRAFT, 'Zur Freigabe' => self::STATUS_PENDING, 'Freigegeben' => self::STATUS_APPROVED, 'Archiviert' => self::STATUS_ARCHIVED]; }
}
