<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Club;
use App\Entity\User;

final class SingleClubMode
{
    public function __construct(
        private readonly PublicWebsite $website,
        private readonly bool $enabled,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function club(): Club
    {
        $club = $this->website->club();
        if ($club === null || !$club->isActive()) {
            throw new \LogicException('Der Einzelvereinsbetrieb benötigt einen aktiven PUBLIC_SITE_CLUB_ID.');
        }

        return $club;
    }

    public function permitsUser(User $user): bool
    {
        $member = $user->getMember();
        if ($member === null) {
            return true;
        }

        return $member->getClub()?->getId() === $this->club()->getId();
    }
}
