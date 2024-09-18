<?php

namespace App\Repository;

use App\Entity\TankCheck;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TankCheck>
 *
 * @method TankCheck|null find($id, $lockMode = null, $lockVersion = null)
 * @method TankCheck|null findOneBy(array $criteria, array $orderBy = null)
 * @method TankCheck[]    findAll()
 * @method TankCheck[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TankCheckRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TankCheck::class);
    }

    public function findNextCheck(): ?TankCheck
    {
        $today = new \DateTime();

        return $this->createQueryBuilder('tc')
            ->where('tc.checkDate > :today')
            ->setParameter('today', $today)
            ->orderBy('tc.checkDate', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
//    /**
//     * @return TankCheck[] Returns an array of TankCheck objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('t')
//            ->andWhere('t.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('t.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?TankCheck
//    {
//        return $this->createQueryBuilder('t')
//            ->andWhere('t.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
