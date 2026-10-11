<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Schedule;
use App\Entity\User;
use App\Enum\CourseStatus;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class TrainingAssessmentVoter extends Voter
{
    public const ASSESS = 'TRAINING_ASSESS';

    public function __construct(private readonly AuthorizationCheckerInterface $authorizationChecker) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::ASSESS && $subject instanceof Schedule;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        if (!$subject instanceof Schedule) {
            return false;
        }
        $course = $subject->getCourses();
        if ($course === null || $course->getStatus() === CourseStatus::COMPLETED) {
            return false;
        }
        // Administrators may assess all non-completed courses.
        if ($this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            return true;
        }
        if (!$this->authorizationChecker->isGranted('ROLE_INSTRUCTOR')) {
            return false;
        }
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }
        $member = $user->getMember();
        return $member !== null
            && $member->getId() !== null
            && $subject->getInstructor()?->getId() === $member->getId();
    }
}
