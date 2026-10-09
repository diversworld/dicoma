<?php

namespace App\Repository;

use App\Entity\Booking;
use App\Entity\Member;
use App\Entity\Schedule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Enum\BookingAttendanceStatus;


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

	
	public function existsForScheduleAndMember(
		Schedule $schedule,
		Member $member
	): bool {
		$count = $this
			->createQueryBuilder('booking')
			->select('COUNT(booking.id)')
			->andWhere('booking.schedule = :schedule')
			->andWhere('booking.member = :member')
			->setParameter('schedule', $schedule)
			->setParameter('member', $member)
			->getQuery()
			->getSingleScalarResult();

		return (int) $count > 0;
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
	
	/**
	 * @return Booking[]
	 */
	public function findOpenMakeupBookings(): array
	{
		return $this->createQueryBuilder('booking')
			->leftJoin(
				'booking.makeupBookings',
				'makeup'
			)
			->addSelect('makeup')
			->andWhere(
				'booking.attendanceStatus = :status'
			)
			->andWhere(
				'makeup.id IS NULL'
			)
			->setParameter(
				'status',
				BookingAttendanceStatus::MAKEUP_REQUIRED
			)
			->orderBy(
				'booking.bookingdate',
				'ASC'
			)
			->getQuery()
			->getResult();
	}

}
