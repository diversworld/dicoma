<?php

namespace App\Entity;

use App\Enum\QualificationType;
use App\Repository\QualificationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: QualificationRepository::class)]
#[ORM\Table(
    name: 'qualification',
    uniqueConstraints: [
        new ORM\UniqueConstraint(
            name: 'uniq_qualification_club_name',
            columns: ['club_id', 'name']
        )
    ]
)]
class Qualification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /*
     * NULL bedeutet:
     * Qualifikation steht vereinsübergreifend zur Verfügung.
     *
     * Ein Verein kann zusätzlich eigene Qualifikationen definieren.
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Club $club = null;

    #[ORM\Column(length: 160)]
    private ?string $name = null;

	/**
	 * @var Collection<int, QualificationTrainingUnit>
	 */
	#[ORM\OneToMany(
		mappedBy: 'qualification',
		targetEntity: QualificationTrainingUnit::class,
		cascade: ['persist'],
		orphanRemoval: true
	)]
	private Collection $trainingUnits;
	
    #[ORM\Column(length: 60, nullable: true)]
    private ?string $shortName = null;

    #[ORM\Column(
        type: Types::STRING,
        length: 40,
        enumType: QualificationType::class
    )]
    private QualificationType $type =
        QualificationType::DIVING_CERTIFICATION;

    /*
     * Herausgeber/Verband:
     *
     * VDST
     * CMAS
     * PADI
     * SSI
     * DRK
     * DLRG
     * DOSB
     * ...
     */
    #[ORM\Column(length: 120, nullable: true)]
    private ?string $issuer = null;

    /*
     * Optionale reguläre Gültigkeitsdauer.
     *
     * NULL = unbegrenzt.
     */
    #[ORM\Column(nullable: true)]
    private ?int $validityMonths = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column]
    private int $sortOrder = 0;

    /**
     * @var Collection<int, MemberQualification>
     */
    #[ORM\OneToMany(
        mappedBy: 'qualification',
        targetEntity: MemberQualification::class
    )]
    private Collection $memberQualifications;

    public function __construct()
    {
        $this->memberQualifications = new ArrayCollection();
		$this->trainingUnits = new ArrayCollection();
    }

    public function __toString(): string
    {
        if ($this->issuer) {
            return sprintf(
                '%s – %s',
                $this->issuer,
                $this->name ?? ''
            );
        }

        return $this->name ?? '';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClub(): ?Club
    {
        return $this->club;
    }

    public function setClub(?Club $club): static
    {
        $this->club = $club;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = trim($name);

        return $this;
    }

    public function getShortName(): ?string
    {
        return $this->shortName;
    }

    public function setShortName(?string $shortName): static
    {
        $this->shortName = $shortName !== null
            ? trim($shortName)
            : null;

        return $this;
    }

    public function getType(): QualificationType
    {
        return $this->type;
    }

    public function setType(QualificationType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getIssuer(): ?string
    {
        return $this->issuer;
    }

    public function setIssuer(?string $issuer): static
    {
        $this->issuer = $issuer !== null
            ? trim($issuer)
            : null;

        return $this;
    }

    public function getValidityMonths(): ?int
    {
        return $this->validityMonths;
    }

    public function setValidityMonths(?int $validityMonths): static
    {
        $this->validityMonths = $validityMonths;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }

	/**
	 * @return Collection<int, QualificationTrainingUnit>
	 */
	public function getTrainingUnits(): Collection
	{
		return $this->trainingUnits;
	}

	public function addTrainingUnit(
		QualificationTrainingUnit $trainingUnit
	): static {
		if (!$this->trainingUnits->contains($trainingUnit)) {
			$this->trainingUnits->add($trainingUnit);
			$trainingUnit->setQualification($this);
		}

		return $this;
	}

	public function removeTrainingUnit(
		QualificationTrainingUnit $trainingUnit
	): static {
		if ($this->trainingUnits->removeElement($trainingUnit)) {
			if ($trainingUnit->getQualification() === $this) {
				$trainingUnit->setQualification(null);
			}
		}

		return $this;
	}
	
    /**
     * @return Collection<int, MemberQualification>
     */
    public function getMemberQualifications(): Collection
    {
        return $this->memberQualifications;
    }
}