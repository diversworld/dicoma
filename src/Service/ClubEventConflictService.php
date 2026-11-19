<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ClubEvent;
use App\Entity\Schedule;
use Doctrine\ORM\EntityManagerInterface;

final class ClubEventConflictService
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function firstFor(ClubEvent $event): ?string
    {
        if ($event->getStartsAt() === null || $event->getEndsAt() === null) {
            return null;
        }
        $query = $this->em->getRepository(ClubEvent::class)->createQueryBuilder('event')
            ->where('event.club = :club AND event.cancelled = false')
            ->andWhere('event.startsAt < :endsAt AND event.endsAt > :startsAt')
            ->setParameter('club', $event->getClub())
            ->setParameter('startsAt', $event->getStartsAt())
            ->setParameter('endsAt', $event->getEndsAt());
        if ($event->getId() !== null) {
            $query->andWhere('event != :current')->setParameter('current', $event);
        }
        foreach ($query->getQuery()->getResult() as $other) {
            if ($event->getResponsible()?->getId() !== null
                && $event->getResponsible()->getId() === $other->getResponsible()?->getId()) {
                return sprintf('Die verantwortliche Person ist im Zeitraum der Veranstaltung „%s“ bereits eingeteilt.', $other->getTitle());
            }
            if ($event->getResponsible()?->getId() !== null && $event->getLocation() !== ''
                && mb_strtolower(trim($event->getLocation())) === mb_strtolower(trim($other->getLocation()))) {
                return sprintf('Der Veranstaltungsort wird zur selben Zeit von „%s“ verwendet.', $other->getTitle());
            }
        }
        if ($event->getResponsible()?->getId() !== null) {
            $schedules = $this->em->getRepository(Schedule::class)->createQueryBuilder('schedule')
                ->join('schedule.courses', 'course')
                ->where('schedule.instructor = :responsible')
                ->andWhere('schedule.startDate <= :endDate AND schedule.startDate >= :startDate')
                ->setParameter('responsible', $event->getResponsible())
                ->setParameter('startDate', $event->getStartsAt()->format('Y-m-d'))
                ->setParameter('endDate', $event->getEndsAt()->format('Y-m-d'))
                ->getQuery()->getResult();
            foreach ($schedules as $schedule) {
                if ($schedule->getStartDate() === null || $schedule->getStartTime() === null || !$schedule->getDuration()) {
                    continue;
                }
                $start = new \DateTimeImmutable($schedule->getStartDate()->format('Y-m-d').' '.$schedule->getStartTime()->format('H:i:s'));
                $end = $start->modify('+'.$schedule->getDuration().' minutes');
                if ($event->getStartsAt() < $end && $start < $event->getEndsAt()) {
                    return sprintf('Die verantwortliche Person ist gleichzeitig im Kurstermin „%s“ eingeplant.', $schedule->getTitle());
                }
            }
        }
        return null;
    }
}
