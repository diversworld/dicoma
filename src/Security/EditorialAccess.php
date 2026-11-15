<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Enum\PermissionAction;
use App\Enum\PermissionModule;
use App\Service\AdminClubContext;
use App\Service\PublicWebsite;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class EditorialAccess
{
    public function __construct(
        private readonly Security $security,
        private readonly PublicWebsite $website,
        private readonly AdminClubContext $clubContext,
        private readonly ModuleAccess $modules,
        private readonly EntityManagerInterface $em,
        #[Autowire('%env(bool:CLUB_FUNCTION_MEMBERSHIP_ENABLED)%')] private readonly bool $enabled,
    ) {}

    public function can(PermissionAction $action): bool
    {
        if (!$this->enabled) {
            return $this->website->canManageEditorial();
        }
        $club = $this->website->club();
        $user = $this->security->getUser();
        if ($user instanceof User && !$this->em->contains($user)) {
            $user = $user->getId() === null ? null : $this->em->find(User::class, $user->getId());
        }
        return $club !== null && $user instanceof User && $user->getMember()?->getClub()?->getId() === $club->getId()
            && $this->clubContext->getClub()?->getId() === $club->getId()
            && $this->modules->can($user, $club, PermissionModule::WEBSITE, $action);
    }

    public function functionModelEnabled(): bool
    {
        return $this->enabled;
    }
}
