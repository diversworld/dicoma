<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Club;
use App\Entity\Team;
use App\Entity\TeamMember;
use App\Entity\Tournament;
use App\Entity\TournamentEntry;
use App\Entity\User;
use App\Enum\TeamMemberRole;
use App\Enum\TournamentStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class CompetitionAccess
{
    public function __construct(private readonly Security $security, private readonly EntityManagerInterface $em) {}

    public function managesClub(Club $club): bool
    {
        $user = $this->security->getUser();
        return $user instanceof User && ($this->security->isGranted('ROLE_SUPER_ADMIN')
            || ($this->security->isGranted('ROLE_ADMIN') && $club->getId() !== null && $user->getMember()?->getClub()?->getId() === $club->getId()));
    }

    public function managesTeam(Team $team): bool
    {
        if ($team->getSport()?->getClub() === null) { return false; }
        if ($this->managesClub($team->getSport()->getClub())) { return true; }
        $member = $this->security->getUser() instanceof User ? $this->security->getUser()->getMember() : null;
        if ($member === null || !$member->isActiveMember() || !$team->isActive()
            || $member->getClub()?->getId() !== $team->getSport()->getClub()->getId()) { return false; }
        foreach ($this->em->getRepository(TeamMember::class)->findBy(['team' => $team, 'member' => $member]) as $assignment) {
            if ($assignment->isAssignedOn(new \DateTimeImmutable('today'))
                && in_array($assignment->getRole(), [TeamMemberRole::TRAINER, TeamMemberRole::ASSISTANT_TRAINER, TeamMemberRole::TEAM_LEADER], true)) {
                return true;
            }
        }
        return false;
    }

    public function isStaff(): bool
    {
        if ($this->security->isGranted('ROLE_SUPER_ADMIN')) { return true; }
        if ($this->security->isGranted('ROLE_ADMIN')) {
            $user = $this->security->getUser();
            return $user instanceof User && $user->getMember()?->getClub() !== null;
        }
        foreach ($this->em->getRepository(Team::class)->findAll() as $team) {
            if ($this->managesTeam($team)) { return true; }
        }
        return false;
    }

    public function viewsTournament(Tournament $tournament): bool
    {
        if ($this->managesClub($tournament->getClub())) { return true; }
        foreach ($this->em->getRepository(TournamentEntry::class)->findBy(['tournament' => $tournament]) as $entry) {
            if ($entry->getTeam() !== null && $this->managesTeam($entry->getTeam())) { return true; }
        }
        return $tournament->getStatus() === TournamentStatus::OPEN && $this->isStaff();
    }

    public function managesEntry(TournamentEntry $entry): bool
    {
        return $this->managesClub($entry->getTournament()->getClub())
            || ($entry->getTeam() !== null && $this->managesTeam($entry->getTeam()));
    }

    public function require(bool $allowed): void
    {
        if (!$allowed) { throw new AccessDeniedException('Keine Berechtigung für diese Mannschaft oder dieses Turnier.'); }
    }
}
