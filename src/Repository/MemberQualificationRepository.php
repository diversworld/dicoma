<?php

namespace App\Repository;

use App\Entity\Club;
use App\Entity\MemberQualification;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MemberQualification>
 */
class MemberQualificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct(
            $registry,
            MemberQualification::class
        );
    }

    /**
     * Bereits abgelaufene Qualifikationen.
     *
     * @return list<MemberQualification>
     */
    public function findExpired(?Club $club = null): array
    {
        $today = new \DateTimeImmutable('today');

        $qb = $this->createQueryBuilder('mq')
            ->addSelect('m')
            ->addSelect('q')
            ->innerJoin('mq.member', 'm')
            ->innerJoin('mq.qualification', 'q')
            ->andWhere('mq.validUntil IS NOT NULL')
            ->andWhere('mq.validUntil < :today')
            ->setParameter('today', $today)
            ->orderBy('mq.validUntil', 'ASC');

        if ($club !== null) {
            $qb
                ->andWhere('m.club = :club')
                ->setParameter('club', $club);
        }

        return $qb
            ->getQuery()
            ->getResult();
    }

    /**
     * Qualifikationen, die innerhalb der nächsten X Tage ablaufen.
     *
     * Bereits abgelaufene Qualifikationen werden nicht geliefert.
     *
     * @return list<MemberQualification>
     */
    public function findExpiringWithinDays(
        int $days = 30,
        ?Club $club = null
    ): array {
        $today = new \DateTimeImmutable('today');

        $until = $today->modify(
            sprintf('+%d days', $days)
        );

        $qb = $this->createQueryBuilder('mq')
            ->addSelect('m')
            ->addSelect('q')
            ->innerJoin('mq.member', 'm')
            ->innerJoin('mq.qualification', 'q')
            ->andWhere('mq.validUntil IS NOT NULL')
            ->andWhere('mq.validUntil >= :today')
            ->andWhere('mq.validUntil <= :until')
            ->setParameter('today', $today)
            ->setParameter('until', $until)
            ->orderBy('mq.validUntil', 'ASC');

        if ($club !== null) {
            $qb
                ->andWhere('m.club = :club')
                ->setParameter('club', $club);
        }

        return $qb
            ->getQuery()
            ->getResult();
    }

    /**
     * Anzahl bereits abgelaufener Qualifikationen.
     */
    public function countExpired(?Club $club = null): int
    {
        $today = new \DateTimeImmutable('today');

        $qb = $this->createQueryBuilder('mq')
            ->select('COUNT(mq.id)')
            ->innerJoin('mq.member', 'm')
            ->andWhere('mq.validUntil IS NOT NULL')
            ->andWhere('mq.validUntil < :today')
            ->setParameter('today', $today);

        if ($club !== null) {
            $qb
                ->andWhere('m.club = :club')
                ->setParameter('club', $club);
        }

        return (int) $qb
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Anzahl der Qualifikationen, die innerhalb der
     * nächsten X Tage ablaufen.
     */
    public function countExpiringWithinDays(
        int $days = 30,
        ?Club $club = null
    ): int {
        $today = new \DateTimeImmutable('today');

        $until = $today->modify(
            sprintf('+%d days', $days)
        );

        $qb = $this->createQueryBuilder('mq')
            ->select('COUNT(mq.id)')
            ->innerJoin('mq.member', 'm')
            ->andWhere('mq.validUntil IS NOT NULL')
            ->andWhere('mq.validUntil >= :today')
            ->andWhere('mq.validUntil <= :until')
            ->setParameter('today', $today)
            ->setParameter('until', $until);

        if ($club !== null) {
            $qb
                ->andWhere('m.club = :club')
                ->setParameter('club', $club);
        }

        return (int) $qb
            ->getQuery()
            ->getSingleScalarResult();
    }
}