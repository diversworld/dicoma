<?php

namespace App\Entity;

use App\Repository\MemberQualificationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: MemberQualificationRepository::class)]
#[ORM\Table(
    name: 'member_qualification',
    uniqueConstraints: [
        new ORM\UniqueConstraint(
            name: 'uniq_member_qualification',
            columns: ['member_id', 'qualification_id']
        )
    ]
)]
#[Assert\Callback('validateAssignment')]
class MemberQualification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(
        targetEntity: Member::class,
        inversedBy: 'qualifications'
    )]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Member $member = null;

    #[ORM\ManyToOne(
        targetEntity: Qualification::class,
        inversedBy: 'memberQualifications'
    )]
    #[ORM\JoinColumn(nullable: false)]
    private ?Qualification $qualification = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $certificateNumber = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $issuedAt = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $validUntil = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $issuer = null;

    #[ORM\Column]
    private bool $verified = false;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMember(): ?Member
    {
        return $this->member;
    }

    public function setMember(?Member $member): static
    {
        $this->member = $member;

        return $this;
    }

    public function getQualification(): ?Qualification
    {
        return $this->qualification;
    }

    public function setQualification(
        ?Qualification $qualification
    ): static {
        $this->qualification = $qualification;

        return $this;
    }

    public function getCertificateNumber(): ?string
    {
        return $this->certificateNumber;
    }

    public function setCertificateNumber(
        ?string $certificateNumber
    ): static {
        $this->certificateNumber = $certificateNumber !== null
            ? trim($certificateNumber)
            : null;

        return $this;
    }

    public function getIssuedAt(): ?\DateTimeInterface
    {
        return $this->issuedAt;
    }

    public function setIssuedAt(
        ?\DateTimeInterface $issuedAt
    ): static {
        $this->issuedAt = $issuedAt;

        return $this;
    }

    public function getValidUntil(): ?\DateTimeInterface
    {
        return $this->validUntil;
    }

    public function setValidUntil(
        ?\DateTimeInterface $validUntil
    ): static {
        $this->validUntil = $validUntil;

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

    public function isVerified(): bool
    {
        return $this->verified;
    }

    public function setVerified(bool $verified): static
    {
        $this->verified = $verified;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    public function isExpired(): bool
    {
        if ($this->validUntil === null) {
            return false;
        }

        return $this->validUntil < new \DateTimeImmutable('today');
    }

    public function validateAssignment(
        ExecutionContextInterface $context
    ): void {
        if (
            $this->member === null
            || $this->qualification === null
        ) {
            return;
        }

        $qualificationClub = $this->qualification->getClub();

        /*
         * Globale Qualifikationen dürfen jedem Verein
         * zugeordnet werden.
         */
        if ($qualificationClub === null) {
            return;
        }

        if ($qualificationClub !== $this->member->getClub()) {
            $context
                ->buildViolation(
                    'Diese Qualifikation gehört zu einem anderen Verein.'
                )
                ->atPath('qualification')
                ->addViolation();
        }
    }

	public function expiresWithinDays(int $days): bool
	{
		if ($this->validUntil === null) {
			return false;
		}

		$today = new \DateTimeImmutable('today');

		$until = $today->modify(
			sprintf('+%d days', $days)
		);

		return $this->validUntil >= $today
			&& $this->validUntil <= $until;
	}
	
	public function getValidityStatus(): string
	{
		if ($this->validUntil === null) {
			return 'unlimited';
		}

		if ($this->isExpired()) {
			return 'expired';
		}

		if ($this->expiresWithinDays(30)) {
			return 'expiring';
		}

		return 'valid';
	}
    public function __toString(): string
    {
        return $this->qualification?->__toString() ?? '';
    }
}