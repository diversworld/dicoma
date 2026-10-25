<?php

declare(strict_types=1);
namespace App\Service;

use App\Entity\Club;
use App\Entity\Member;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class BarDutyAccess
{
    public function __construct(private readonly Security $security) {}
    public function user(): User
    {
        $user = $this->security->getUser();
        if (!$user instanceof User || $user->getMember()?->getClub() === null) { throw new AccessDeniedException('Vereinszuordnung erforderlich.'); }
        if (!$this->isManager() && !$user->getMember()->isActiveMember()) { throw new AccessDeniedException('Aktive Mitgliedschaft erforderlich.'); }
        return $user;
    }
    public function club(): Club { return $this->user()->getMember()->getClub(); }
    public function member(): Member { return $this->user()->getMember(); }
    public function actor(): string { return $this->user()->getUserIdentifier(); }
    public function isManager(): bool { return $this->security->isGranted('ROLE_BAR_MANAGER'); }
    public function requireManager(): void
    {
        $this->user();
        if (!$this->isManager()) { throw new AccessDeniedException('Nur die Thekenleitung darf diese Aktion ausführen.'); }
    }
    public function ownsClub(Club $club): void
    {
        if ($club->getId() !== $this->club()->getId()) { throw new AccessDeniedException('Anderer Verein.'); }
    }
}
