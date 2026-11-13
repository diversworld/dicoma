<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\User;
use App\Security\DelegatedRoles;
use App\Service\AdminClubContext;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class DelegatedRoleVoter extends Voter
{
    public const MANAGE = 'DELEGATED_ROLE_MANAGE';

    public function __construct(
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly AdminClubContext $clubContext,
    ) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::MANAGE && $subject instanceof User;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $actor = $token->getUser();
        if (!$actor instanceof User || !$subject instanceof User
            || !$this->authorizationChecker->isGranted(User::ROLE_ADMIN)
            || $actor->getId() === null || $subject->getId() === null
            || $actor->getId() === $subject->getId() || !DelegatedRoles::isDelegableTarget($subject)) {
            return false;
        }
        $clubId = $this->clubContext->getClub()?->getId();
        if ($clubId === null || !$subject->getMember()?->isActiveMember()
            || $subject->getMember()?->getClub()?->getId() !== $clubId) {
            return false;
        }
        return $this->authorizationChecker->isGranted(User::ROLE_SUPER_ADMIN)
            || ($actor->getMember()?->isActiveMember() && $actor->getMember()?->getClub()?->getId() === $clubId);
    }
}
