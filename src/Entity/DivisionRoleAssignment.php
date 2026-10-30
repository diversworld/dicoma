<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\DivisionRole;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity]
#[Assert\Callback('validateAssignment')]
class DivisionRoleAssignment
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Sport $sport;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull]
    private ?Member $member = null;

    #[ORM\Column(length: 30, enumType: DivisionRole::class)]
    #[Assert\NotNull]
    private ?DivisionRole $role = null;

    #[ORM\Column(type: 'date_immutable')]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $startsAt;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $endsAt = null;

    public function __construct(Sport $sport)
    {
        $this->sport = $sport;
        $this->startsAt = new \DateTimeImmutable('today');
    }

    public function getId(): ?int { return $this->id; }
    public function getSport(): Sport { return $this->sport; }
    public function getMember(): ?Member { return $this->member; }
    public function setMember(?Member $member): void { $this->member = $member; }
    public function getRole(): ?DivisionRole { return $this->role; }
    public function setRole(?DivisionRole $role): void { $this->role = $role; }
    public function getStartsAt(): ?\DateTimeImmutable { return $this->startsAt; }
    public function setStartsAt(?\DateTimeImmutable $startsAt): void { $this->startsAt = $startsAt; }
    public function getEndsAt(): ?\DateTimeImmutable { return $this->endsAt; }
    public function setEndsAt(?\DateTimeImmutable $endsAt): void { $this->endsAt = $endsAt; }

    public function isAssignedOn(\DateTimeInterface $date): bool
    {
        $day = $date->format('Y-m-d');
        return $this->startsAt !== null && $this->startsAt->format('Y-m-d') <= $day
            && ($this->endsAt === null || $this->endsAt->format('Y-m-d') >= $day);
    }

    public function validateAssignment(ExecutionContextInterface $context): void
    {
        if ($this->member !== null && ($this->sport->getClub()?->getId() === null
            || $this->member->getClub()?->getId() !== $this->sport->getClub()->getId())) {
            $context->buildViolation('Mitglied und Sparte müssen zum selben Verein gehören.')->atPath('member')->addViolation();
        }
        if ($this->startsAt !== null && $this->endsAt !== null && $this->endsAt < $this->startsAt) {
            $context->buildViolation('Das Ende darf nicht vor dem Beginn liegen.')->atPath('endsAt')->addViolation();
        }
    }
}
