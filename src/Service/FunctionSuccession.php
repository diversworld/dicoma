<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ClubFunction;
use App\Entity\ClubFunctionAssignment;
use App\Entity\Member;
use App\Entity\MembershipEvent;
use App\Entity\User;
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\ORM\EntityManagerInterface;

final class FunctionSuccession
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly bool $enabled,
    ) {}

    /** @return list<string> */
    public function functions(?User $holder): array
    {
        return array_map(
            static fn (ClubFunctionAssignment $assignment): string => self::label($assignment->getClubFunction()),
            $this->assignments($holder),
        );
    }

    /**
     * The caller must hold the club lock while checking and changing assignments or membership.
     *
     * @return list<ClubFunction>
     */
    public function missingSuccessors(?User $holder, ?\DateTimeImmutable $date = null): array
    {
        $date ??= new \DateTimeImmutable('today');
        $missing = [];
        $assignments = $this->assignments($holder);
        foreach ($assignments as $assignment) {
            $function = $assignment->getClubFunction();
            if (!$function->isSuccessorRequired()) {
                continue;
            }
            $query = $this->em->createQueryBuilder()->select('assignment')
                ->from(ClubFunctionAssignment::class, 'assignment')
                ->where('assignment.clubFunction = :function')->setParameter('function', $function)
                ->orderBy('assignment.id', 'ASC')->getQuery();
            $this->lockQuery($query);
            $successors = $query->getResult();
            $found = false;
            foreach ($successors as $successor) {
                $this->refresh($successor);
                $candidate = $successor->getUser();
                $this->refresh($candidate);
                $member = $candidate->getMember();
                if ($member !== null) {
                    $this->refresh($member);
                }
                if ($candidate !== $holder
                    && ($holder->getId() === null || $holder->getId() !== $candidate->getId())
                    && $successor->isEffectiveOn($date)) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $missing[] = $function;
            }
        }
        return $missing;
    }

    /** @return list<string> */
    public function missingLabels(?User $holder, ?\DateTimeImmutable $date = null): array
    {
        return array_map(self::label(...), $this->missingSuccessors($holder, $date));
    }

    public function revoke(Member $member, string $actor): void
    {
        foreach ($this->assignments($member->getUser()) as $assignment) {
            $this->em->persist(new MembershipEvent(
                $member, 'functions_revoked', $actor,
                'Vereinsfunktion dauerhaft entzogen: '.self::label($assignment->getClubFunction()).'.',
            ));
            $assignment->getUser()->removeFunctionAssignment($assignment);
            $this->em->remove($assignment);
        }
    }

    public static function label(ClubFunction $function): string
    {
        return sprintf('Funktion %s (ID %d)', $function->getName(), $function->getId());
    }

    /** @return list<ClubFunctionAssignment> */
    private function assignments(?User $holder): array
    {
        if (!$this->enabled || $holder === null) {
            return [];
        }
        if (!$this->em->getConnection()->isTransactionActive()) {
            throw new \LogicException('Die Funktionsprüfung benötigt eine Transaktion mit Vereinssperre.');
        }
        $query = $this->em->createQueryBuilder()->select('assignment')
            ->from(ClubFunctionAssignment::class, 'assignment')
            ->where('assignment.user = :holder')->setParameter('holder', $holder)
            ->orderBy('assignment.id', 'ASC')->getQuery();
        $this->lockQuery($query);
        $assignments = $query->getResult();
        foreach ($assignments as $assignment) {
            $this->refresh($assignment);
            $this->refresh($assignment->getClubFunction());
        }
        return $assignments;
    }

    private function lockQuery(\Doctrine\ORM\Query $query): void
    {
        if ($this->em->getConnection()->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            $query->setLockMode(LockMode::PESSIMISTIC_WRITE);
        }
    }

    private function refresh(object $entity): void
    {
        $mode = $this->em->getConnection()->getDatabasePlatform() instanceof AbstractMySQLPlatform
            ? LockMode::PESSIMISTIC_WRITE : null;
        $this->em->refresh($entity, $mode);
    }
}
