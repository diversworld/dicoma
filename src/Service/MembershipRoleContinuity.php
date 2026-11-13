<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Club;
use App\Entity\Member;
use App\Entity\MembershipEvent;
use App\Entity\User;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final class MembershipRoleContinuity
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {}

    public function lockClub(?Club $club): void
    {
        if ($club?->getId() === null) {
            return;
        }
        $connection = $this->em->getConnection();
        if ($connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            if (!$connection->isTransactionActive()) {
                throw new \LogicException('Die Vereinssperre benötigt eine Transaktion.');
            }
            $connection->fetchOne('SELECT id FROM club WHERE id = ? FOR UPDATE', [$club->getId()]);
        }
    }

    public function refresh(object $entity): void
    {
        $mode = $this->em->getConnection()->getDatabasePlatform() instanceof AbstractMySQLPlatform
            ? LockMode::PESSIMISTIC_WRITE : null;
        $this->em->refresh($entity, $mode);
    }

    /** @return list<string> */
    public function functions(?User $user): array
    {
        if ($user === null) {
            return [];
        }
        return array_values(array_diff($user->getStoredRoles(), [User::ROLE_USER, User::ROLE_MEMBER, User::ROLE_SUPER_ADMIN]));
    }

    /**
     * The caller must hold the club lock while checking and changing membership.
     *
     * @param list<string> $roles
     * @return list<string>
     */
    public function missingSuccessors(Member $member, array $roles): array
    {
        $club = $member->getClub();
        if ($club === null || $roles === []) {
            return $roles;
        }
        foreach ($this->clubMembers($club) as $candidate) {
            if ($candidate->getId() === $member->getId()) {
                continue;
            }
            $this->refresh($candidate);
            $account = $candidate->getUser();
            if (!$candidate->isActiveMember() || $account === null) {
                continue;
            }
            $this->refresh($account);
            $validRoles = $this->validRoles($account);
            if ($validRoles === null) {
                continue;
            }
            $roles = array_values(array_diff($roles, $validRoles));
            if ($roles === []) {
                break;
            }
        }
        return $roles;
    }

    /** @param list<string> $roles */
    public function revoke(Member $member, array $roles, string $actor): void
    {
        $user = $member->getUser();
        foreach ($roles as $role) {
            $user?->removeRole($role);
        }
        if ($roles !== []) {
            $this->em->persist(new MembershipEvent($member, 'functions_revoked', $actor, 'Funktionsrollen dauerhaft entzogen: '.implode(', ', $roles).'.'));
        }
    }

    /**
     * @param list<string> $roles
     * @param list<string> $missing
     */
    public function notifyBoard(Member $member, array $roles, array $missing, string $event, string $key, MembershipService $memberships, bool $excludeMember = false): bool
    {
        if ($roles === []) {
            return true;
        }
        $club = $member->getClub();
        $recipients = [];
        if ($club !== null) {
            foreach ($this->clubMembers($club) as $boardMember) {
                if ($excludeMember && $boardMember->getId() === $member->getId()) {
                    continue;
                }
                $this->refresh($boardMember);
                $account = $boardMember->getUser();
                if (!$boardMember->isActiveMember() || $account === null) {
                    continue;
                }
                $this->refresh($account);
                if (in_array(User::ROLE_BOARD, $account->getStoredRoles(), true)
                    && in_array(User::ROLE_BOARD, $this->validRoles($account) ?? [], true)
                    && filter_var($account->getEmail(), FILTER_VALIDATE_EMAIL)) {
                    $recipients[$account->getEmail()] = true;
                }
            }
        }
        $description = sprintf(
            '%s Mitglied %s; Funktionsrollen: %s. %s',
            $event,
            $member->getMemberNumber() ?? (string) $member->getId(),
            implode(', ', $roles),
            $missing === [] ? 'Für alle Rollen ist derzeit eine Nachfolge vorhanden.' : 'Nachfolge offen: '.implode(', ', $missing).'.'
        );
        foreach (array_keys($recipients) as $recipient) {
            $memberships->queue('role-continuity:'.hash('sha256', $key.'|'.$recipient), $recipient, 'Vereinsfunktionen: Nachfolge prüfen', $description);
        }
        if ($recipients === []) {
            $this->logger->error('Keine zustellbare Adresse eines aktiven Vorstands für die Rollen-Nachfolgemeldung.', [
                'club_id' => $club?->getId(), 'member_id' => $member->getId(), 'roles' => $roles, 'event' => $event,
            ]);
        }
        return $recipients !== [];
    }

    /** @return list<Member> */
    private function clubMembers(Club $club): array
    {
        return $this->em->createQueryBuilder()->select('membership')->from(Member::class, 'membership')
            ->where('membership.club = :club')->andWhere('membership.archivedAt IS NULL')
            ->setParameter('club', $club)->orderBy('membership.id', 'ASC')->getQuery()->getResult();
    }

    /** @return list<string>|null */
    private function validRoles(User $user): ?array
    {
        try {
            return $user->getRoles();
        } catch (\UnexpectedValueException $exception) {
            $this->logger->error('Ungültiger Rollengültigkeitszeitraum bei der Nachfolgeprüfung; Konto wird nicht berücksichtigt.', [
                'user_id' => $user->getId(), 'exception' => $exception,
            ]);
            return null;
        }
    }
}
