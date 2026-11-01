<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Courses;
use App\Entity\User;
use App\Service\AdminClubContext;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Vereinsspezifische Kursbearbeitung. Im Backend gilt für Superadmins der
 * ausgewählte Verein; vereinslose Kurse bleiben zur Nachbearbeitung zugänglich.
 */
final class CourseManagementVoter extends Voter
{
    public const MANAGE = 'COURSE_MANAGE';

    public function __construct(
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly AdminClubContext $clubContext,
    ) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::MANAGE && $subject instanceof Courses;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        if (!$subject instanceof Courses) {
            return false;
        }

        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        if ($this->authorizationChecker->isGranted('ROLE_SUPER_ADMIN')) {
            if ($this->clubContext->isBackendRequest()) {
                $selectedId = $this->clubContext->getClub()?->getId();
                return $selectedId !== null && ($subject->getClub() === null || $subject->getClub()->getId() === $selectedId);
            }
            return true;
        }

        if (!$this->authorizationChecker->isGranted('ROLE_ADMIN')
            && !$this->authorizationChecker->isGranted('ROLE_TRAINING_MANAGER')
            && !$this->authorizationChecker->isGranted('ROLE_COURSE_ADMIN')) {
            return false;
        }

        $ownClub = $user->getMember()?->getClub();
        $courseClub = $subject->getClub();

        return $ownClub !== null && $courseClub !== null
            && $ownClub->getId() !== null
            && $ownClub->getId() === $courseClub->getId();
    }
}
