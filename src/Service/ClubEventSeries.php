<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ClubEvent;
use Doctrine\ORM\EntityManagerInterface;

final class ClubEventSeries
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function materialize(ClubEvent $event): int
    {
        if ($event->getRecurrence() === null || $event->getRecurrenceUntil() === null || $event->getStartsAt() === null
            || $event->getEndsAt() === null) {
            return 0;
        }
        $interval = $event->getRecurrence() === 'weekly' ? '+1 week' : '+1 month';
        $start = $event->getStartsAt()->modify($interval);
        $duration = $event->getStartsAt()->diff($event->getEndsAt());
        $count = 0;
        $guard = 0;
        while ($start->format('Y-m-d') <= $event->getRecurrenceUntil()->format('Y-m-d') && ++$guard <= 104) {
            $copy = new ClubEvent($event->getClub());
            $copy->copyDetailsFrom($event);
            $copy->setStartsAt($start);
            $copy->setEndsAt($start->add($duration));
            $copy->setRecurrence(null);
            $copy->setRecurrenceUntil(null);
            $copy->setSeriesParent($event);
            $this->em->persist($copy);
            ++$count;
            $start = $start->modify($interval);
        }
        return $count;
    }
}
