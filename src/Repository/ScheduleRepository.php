<?php

namespace App\Repository;

use App\Entity\Schedule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository
 *
 * @method Schedule|null find($id, $lockMode = null, $lockVersion = null)
 * @method Schedule|null findOneBy(array $criteria, array $orderBy = null)
 * @method Schedule[]    findAll()
 * @method Schedule[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ScheduleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Schedule::class);
    }

    /**
     * @param $value
     * @return array Returns an array of Schedule objects
     */
    public function countByCourse($value): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.course = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getResult()
            ;
    }

    /**
     * @throws Exception
     */
    public function findAllWithBookingCount(): array
    {
        $qb = $this->createQueryBuilder('s')
            ->select('s.id, s.title, s.startDate, s.startTime, s.location, s.locationStreet, s.locationPostal, s.locationCity, s.duration, s.price, s.notes, COUNT(b.id) AS bookingCount')
            ->leftJoin('App\Entity\Booking', 'b', Join::WITH, 'b.schedule = s')
            >groupBy('s.id, s.title, s.startDate, s.startTime, s.location, s.locationStreet, s.locationPostal, s.locationCity, s.price')
        ;

        return $qb->getQuery()->getResult();
    }

    /**
     * @return array
     */
    public function findScheduleWithBookingCount($courseId): array
    {
        $qb = $this->createQueryBuilder('s')
            ->select('s.id, s.title, s.startDate, s.startTime, s.location, s.locationStreet, s.locationPostal, s.locationCity, s.price, COUNT(b.id) AS bookingCount')
            ->leftJoin('App\Entity\Booking', 'b', Join::WITH, 'b.schedule = s')
            ->where('s.courses = :courseId')
            ->setParameter('courseId', $courseId)
            ->groupBy('s.id, s.title, s.startDate, s.startTime, s.location, s.locationStreet, s.locationPostal, s.locationCity, s.price');

        return $qb->getQuery()->getResult();
    }

//    /**
//     * @return Schedule[] Returns an array of Schedule objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('s')
//            ->andWhere('s.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('s.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Schedule
//    {
//        return $this->createQueryBuilder('s')
//            ->andWhere('s.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
