<?php

namespace App\Repository;

use App\Entity\Booking;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class BookingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Booking::class);
    }

//    /**
//     * @return Booking[] Returns an array of Booking objects
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

    public function findByUser($value): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.students = :val')
            ->setParameter('val', $value)
            ->orderBy('b.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }

    public function findLastBookingNr(): ?Booking
    {
        return $this->createQueryBuilder('b')
            ->select('b.id, b.bookingnumber')
            ->orderBy('b.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getResult()
        ;
    }

    public function getLastBookingNumberOfCurrentMonth(string $monthYearPart): ?string
    {
        // Get the last booking of the current month/year
        $queryBuilder = $this->createQueryBuilder('b');
        $queryBuilder
            ->where($queryBuilder->expr()->like('b.bookingnumber', ':monthYearPart'))
            ->orderBy('b.bookingnumber', 'DESC')
            ->setMaxResults(1)
            ->setParameter('monthYearPart', $monthYearPart . '%');

        $lastBooking = $queryBuilder->getQuery()->getOneOrNullResult();

        return $lastBooking ? $lastBooking->getBookingnumber() : null;
    }
}
