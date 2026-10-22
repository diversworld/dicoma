<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Member;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/** Vereinsbezogene Mitgliedsverwaltung, analog zum CourseManagementVoter. */
final class MemberManagementVoter extends Voter
{
    public const MANAGE = 'MEMBER_MANAGE';

    public function __construct(private readonly AuthorizationCheckerInterface $authorizationChecker) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::MANAGE && $subject instanceof Member;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User || !$subject instanceof Member) {
            return false;
        }
        if ($this->authorizationChecker->isGranted('ROLE_SUPER_ADMIN')) {
            return true;
        }
        if (!$this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            return false;
        }
        $ownClubId = $user->getMember()?->getClub()?->getId();
        $targetClubId = $subject->getClub()?->getId();
        return $ownClubId !== null && $targetClubId !== null && $ownClubId === $targetClubId;
    }
}
