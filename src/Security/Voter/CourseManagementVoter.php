<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Courses;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Vereinsspezifische Kursbearbeitung. Globale Administration ist nur
 * mit ROLE_SUPER_ADMIN erlaubt; ROLE_ADMIN benötigt eine Vereinszuordnung.
 */
final class CourseManagementVoter extends Voter
{
    public const MANAGE = 'COURSE_MANAGE';

    public function __construct(private readonly AuthorizationCheckerInterface $authorizationChecker) {}

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
