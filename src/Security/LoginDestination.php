<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Service\CompetitionAccess;
use App\Service\PublicWebsite;
use Symfony\Bundle\SecurityBundle\Security;

final class LoginDestination
{
    public function __construct(
        private readonly Security $security,
        private readonly PublicWebsite $website,
        private readonly CompetitionAccess $competitions,
    ) {}

    public function route(): string
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return 'app_login';
        }
        $stored = $user->getStoredRoles();
        if (array_intersect($stored, [User::ROLE_INSTRUCTOR, User::ROLE_EQUIPMENT_MANAGER, User::ROLE_COMPRESSOR_OPERATOR]) === []) {
            if ($this->security->isGranted(User::ROLE_ADMIN)) {
                return 'admin';
            }
            if ($this->security->isGranted(User::ROLE_TRAINING_MANAGER)) {
                return 'admin_training_overview';
            }
            if ($this->security->isGranted(User::ROLE_COURSE_ADMIN)) {
                return 'admin_portal_training_portal';
            }
            if ($this->security->isGranted(User::ROLE_BAR_MANAGER)) {
                return 'admin_portal_bar_duty';
            }
            if ($this->website->canManageEditorial()) {
                return 'admin_website_editorial';
            }
            if ($this->competitions->isStaff()) {
                return 'competition_teams';
            }
        }

        return $this->security->isGranted(User::ROLE_MEMBER) ? 'member_portal_dashboard' : 'training_portal';
    }
}
