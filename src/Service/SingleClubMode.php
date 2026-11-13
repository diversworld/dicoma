<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Club;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class SingleClubMode
{
    public function __construct(
        private readonly PublicWebsite $website,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function optionalClub(): ?Club
    {
        $club = $this->website->club();
        if ($club === null) {
            $clubs = $this->em->getRepository(Club::class)->findBy(['active' => true], null, 2);
            if (count($clubs) > 1) {
                throw new \LogicException('Mehrere aktive Vereine vorhanden. PUBLIC_SITE_CLUB_ID muss den Zielverein bezeichnen.');
            }
            $club = $clubs[0] ?? null;
        }
        if ($club !== null && !$club->isActive()) {
            throw new \LogicException('Der konfigurierte Verein muss aktiv sein.');
        }

        return $club;
    }

    public function club(): Club
    {
        return $this->optionalClub() ?? throw new \LogicException('Ein aktiver Verein muss zunächst angelegt werden.');
    }

    public function permitsUser(User $user): bool
    {
        $member = $user->getMember();
        if ($member === null) {
            return true;
        }

        $clubId = $this->optionalClub()?->getId();
        return $clubId !== null && $member->getClub()?->getId() === $clubId;
    }
}
