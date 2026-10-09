<?php

namespace App\Repository;

use App\Entity\Brevets;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Brevets>
 *
 * @method Brevets|null find($id, $lockMode = null, $lockVersion = null)
 * @method Brevets|null findOneBy(array $criteria, array $orderBy = null)
 * @method Brevets[]    findAll()
 * @method Brevets[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BrevetsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Brevets::class);
    }

//    /**
//     * @return Brevets[] Returns an array of Brevets objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('b')
//            ->andWhere('b.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('b.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Brevets
//    {
//        return $this->createQueryBuilder('b')
//            ->andWhere('b.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
