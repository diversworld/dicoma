<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Club;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

final class AdminClubContext
{
    public function __construct(
        private readonly Security $security,
        private readonly RequestStack $requests,
        private readonly SingleClubMode $singleClub,
    ) {}

    public function getClub(): ?Club
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return null;
        }
        if (!$this->singleClub->permitsUser($user)) {
            return null;
        }
        $club = $this->singleClub->optionalClub();
        if ($club === null) {
            return null;
        }
        if (!$this->security->isGranted('ROLE_SUPER_ADMIN')
            && $user->getMember()?->getClub()?->getId() !== $club->getId()) {
            return null;
        }
        return $club;
    }

    public function isBackendRequest(): bool
    {
        $path = $this->requests->getCurrentRequest()?->getPathInfo();
        return $path !== null && ($path === '/admin' || str_starts_with($path, '/admin/'));
    }

}
