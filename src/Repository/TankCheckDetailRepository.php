<?php

namespace App\Repository;

use App\Entity\TankCheckDetail;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TankCheckDetail>
 *
 * @method TankCheckDetail|null find($id, $lockMode = null, $lockVersion = null)
 * @method TankCheckDetail|null findOneBy(array $criteria, array $orderBy = null)
 * @method TankCheckDetail[]    findAll()
 * @method TankCheckDetail[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TankCheckDetailRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TankCheckDetail::class);
    }

    /**
     * @throws NonUniqueResultException
     * @throws NoResultException
     */
    public function getSumOfNettoForCheck(int $tankCheckId): float
    {
        return $this->createQueryBuilder('d')
            ->select('SUM(d.netto_price) as netto_total')
            ->where('d.tankCheck = :id')
            ->setParameter('id', $tankCheckId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @throws NonUniqueResultException
     * @throws NoResultException
     */
    public function getSumOfBruttoForCheck(int $tankCheckId): float
    {
        return $this->createQueryBuilder('d')
            ->select('SUM(d.brutto_price) as brutto_total')
            ->where('d.tankCheck = :id')
            ->setParameter('id', $tankCheckId)
            ->getQuery()
            ->getSingleScalarResult();
    }

//    /**
//     * @return TankCheckDetail[] Returns an array of TankCheckDetail objects
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

//    public function findOneBySomeField($value): ?TankCheckDetail
//    {
//        return $this->createQueryBuilder('t')
//            ->andWhere('t.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
