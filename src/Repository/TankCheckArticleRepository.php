<?php

namespace App\Repository;

use App\Entity\TankCheckArticle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TankCheckArticle>
 *
 * @method TankCheckArticle|null find($id, $lockMode = null, $lockVersion = null)
 * @method TankCheckArticle|null findOneBy(array $criteria, array $orderBy = null)
 * @method TankCheckArticle[]    findAll()
 * @method TankCheckArticle[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TankCheckArticleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TankCheckArticle::class);
    }

    /**
     * @return TankCheckArticle[] Returns an array of TankCheckArticle objects
     */
    public function findByCheck($value): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.tankCheck = :val')
            ->setParameter('val', $value)
            ->orderBy('t.id', 'ASC')
            ->setMaxResults(25)
            ->getQuery()
            ->getResult()
        ;
    }

//    public function findOneBySomeField($value): ?TankCheckArticle
//    {
//        return $this->createQueryBuilder('t')
//            ->andWhere('t.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
