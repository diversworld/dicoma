<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Member;
use App\Entity\Team;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class AdminTeamScope
{
    public function __construct(private readonly AdminClubContext $context) {}

    public function clubId(): ?int
    {
        return $this->context->getClub()?->getId();
    }

    public function assertMember(?Member $member): void
    {
        $id = $this->clubId();
        if ($id === null || $member?->getClub()?->getId() !== $id) {
            throw new AccessDeniedException('Mitglied gehört nicht zum ausgewählten Verein.');
        }
    }

    public function assertTeam(?Team $team): void
    {
        $id = $this->clubId();
        if ($id === null || $team?->getSport()?->getClub()?->getId() !== $id) {
            throw new AccessDeniedException('Mannschaft gehört nicht zum ausgewählten Verein.');
        }
    }
}
