<?php

namespace App\Repository;

use App\Entity\Schedule;
use App\Entity\Booking;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\Courses;
use App\Entity\TrainingUnitType;


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

	
	public function countForCourseAndTrainingUnitType(
		Courses $course,
		TrainingUnitType $trainingUnitType
	): int {
		return (int) $this
			->createQueryBuilder('s')
			->select('COUNT(s.id)')
			->andWhere('s.courses = :course')
			->andWhere('s.trainingUnitType = :trainingUnitType')
			->setParameter('course', $course)
			->setParameter('trainingUnitType', $trainingUnitType)
			->getQuery()
			->getSingleScalarResult();
	}
	
	/**
	 * @return Schedule[]
	 */
	public function findOtherSchedulesForBooking(
		Booking $booking
	): array {
		$schedule = $booking->getSchedule();

		if ($schedule === null) {
			return [];
		}

		$course = $schedule->getCourses();

		if ($course === null) {
			return [];
		}

		return $this->createQueryBuilder('schedule')
			->andWhere(
				'schedule.courses = :course'
			)
			->andWhere(
				'schedule != :currentSchedule'
			)
			->setParameter(
				'course',
				$course
			)
			->setParameter(
				'currentSchedule',
				$schedule
			)
			->orderBy(
				'schedule.startDate',
				'ASC'
			)
			->addOrderBy(
				'schedule.startTime',
				'ASC'
			)
			->getQuery()
			->getResult();
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
