<?php
// src/Security/UserChecker.php

namespace App\Security;

use App\Entity\User;
use App\Service\SingleClubMode;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    public function __construct(private readonly SingleClubMode $singleClub) {}

    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user->getIsVerified()) {
            throw new CustomUserMessageAccountStatusException('Your email address is not verified.');
        }
        if ($user instanceof User && !$this->singleClub->permitsUser($user)) {
            throw new CustomUserMessageAccountStatusException('Das Konto ist nicht dem konfigurierten Verein zugeordnet.');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        // Any additional checks
    }
}