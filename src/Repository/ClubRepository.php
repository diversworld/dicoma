<?php

namespace App\Repository;

use App\Entity\Club;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Club>
 */
class ClubRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Club::class);
    }

    /**
     * @return list<Club>
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('club')
            ->andWhere('club.active = :active')
            ->setParameter('active', true)
            ->orderBy('club.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}