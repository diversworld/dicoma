<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_function_assignee', columns: ['club_function_id', 'user_id'])]
class ClubFunctionAssignment
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private ClubFunction $clubFunction;

    #[ORM\ManyToOne(inversedBy: 'functionAssignments'), ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private User $user;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $startsAt = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $endsAt = null;

    #[ORM\Version, ORM\Column(type: 'integer')]
    private int $version = 1;

    public function __construct(ClubFunction $clubFunction, User $user)
    {
        $this->clubFunction = $clubFunction;
        $this->user = $user;
        $user->addFunctionAssignment($this);
    }

    public function getId(): ?int { return $this->id; }
    public function getClubFunction(): ClubFunction { return $this->clubFunction; }
    public function getUser(): User { return $this->user; }
    public function getStartsAt(): ?\DateTimeImmutable { return $this->startsAt; }
    public function getEndsAt(): ?\DateTimeImmutable { return $this->endsAt; }
    public function getVersion(): int { return $this->version; }

    public function setValidity(?\DateTimeImmutable $startsAt, ?\DateTimeImmutable $endsAt): void
    {
        ClubFunction::assertValidity($startsAt, $endsAt);
        $this->startsAt = $startsAt?->setTime(0, 0);
        $this->endsAt = $endsAt?->setTime(0, 0);
    }

    public function isEffectiveOn(\DateTimeImmutable $date): bool
    {
        $member = $this->user->getMember();
        $memberClub = $member?->getClub();
        $club = $this->clubFunction->getClub();
        return $member !== null && $member->isActiveMember() && $member->getArchivedAt() === null && $memberClub !== null
            && ($memberClub === $club || ($memberClub->getId() !== null && $memberClub->getId() === $club->getId()))
            && $this->clubFunction->isValidOn($date)
            && ClubFunction::withinValidity($date, $this->startsAt, $this->endsAt);
    }
}
