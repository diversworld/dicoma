<?php

namespace App\Repository;

use App\Entity\Club;
use App\Entity\Sport;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Sport>
 */
class SportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Sport::class);
    }

    /**
     * @return list<Sport>
     */
    public function findActiveByClub(Club $club): array
    {
        return $this->createQueryBuilder('sport')
            ->andWhere('sport.club = :club')
            ->andWhere('sport.active = :active')
            ->setParameter('club', $club)
            ->setParameter('active', true)
            ->orderBy('sport.sortOrder', 'ASC')
            ->addOrderBy('sport.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}