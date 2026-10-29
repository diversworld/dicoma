<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Schedule;
use App\Entity\BarShift;
use App\Entity\TeamTraining;
use App\Entity\Tournament;
use App\Entity\ClubEvent;
use App\Enum\TournamentStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/** Read-only projection: dates remain owned by their respective modules. */
final class ClubCalendar
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CompetitionAccess $access,
        private readonly Security $security,
        private readonly UrlGeneratorInterface $urls,
        private readonly ClubEventBooking $bookings,
        private readonly PublicWebsite $website,
    ) {}

    public function events(\DateTimeImmutable $from, \DateTimeImmutable $until, bool $backend = true): array
    {
        $events = [];
        $user = $this->security->getUser();
        $club = $user instanceof \App\Entity\User ? $user->getMember()?->getClub() : null;
        if ($club !== null) {
            foreach ($this->em->getRepository(ClubEvent::class)->createQueryBuilder('e')
                ->where('e.club = :club AND e.published = true AND e.cancelled = false AND e.startsAt < :until AND e.endsAt > :from')
                ->setParameter('club', $club)->setParameter('from', $from)->setParameter('until', $until)->getQuery()->getResult() as $event) {
                if (!$this->bookings->canSee($event, $user)) { continue; }
                $url = null;
                if ($backend && $this->security->isGranted('ROLE_ADMIN')) {
                    $url = $this->urls->generate('admin_club_event', ['id' => $event->getId()]);
                } elseif (!$backend && $event->getClub()->getId() === $this->website->club()?->getId()) {
                    $url = $this->urls->generate('club_event_detail', ['id' => $event->getId()]);
                }
                $events[] = ['key' => 'club-event-'.$event->getId(), 'kind' => 'Veranstaltung', 'title' => $event->getTitle(),
                    'start' => $event->getStartsAt(), 'end' => $event->getEndsAt(), 'location' => $event->getLocation(), 'url' => $url];
            }
        }
        if ($club !== null && ($this->security->isGranted('ROLE_BAR_MANAGER') || $user->getMember()->isActiveMember())) {
            foreach ($this->em->getRepository(BarShift::class)->createQueryBuilder('s')
                ->where('s.club = :club AND s.status != :cancelled AND s.startsAt < :until AND s.endsAt > :from')
                ->setParameter('club', $club)->setParameter('cancelled', 'cancelled')->setParameter('from', $from)->setParameter('until', $until)->getQuery()->getResult() as $shift) {
                $events[] = ['key' => 'bar-duty-'.$shift->getId(), 'kind' => 'Thekendienst', 'title' => $shift->getTitle(),
                    'start' => $shift->getStartsAt(), 'end' => $shift->getEndsAt(), 'location' => $shift->getLocation(),
                    'url' => !$backend || $this->security->isGranted('ROLE_BAR_MANAGER')
                        ? $this->urls->generate(($backend ? 'admin_portal_' : '').'bar_duty_shift', ['id' => $shift->getId()]) : null];
            }
        }
        foreach ($this->em->getRepository(TeamTraining::class)->createQueryBuilder('s')->addSelect('t')
            ->join('s.team', 't')->where('s.startsAt < :until AND s.endsAt > :from AND s.cancelled = false')
            ->setParameter('from', $from)->setParameter('until', $until)->getQuery()->getResult() as $session) {
            if (!$this->access->managesTeam($session->getTeam())) { continue; }
            $events[] = ['key' => 'training-' . $session->getId(), 'kind' => 'Mannschaftstraining',
                'title' => $session->getTeam()->getName() . ': ' . $session->getTitle(), 'start' => $session->getStartsAt(),
                'end' => $session->getEndsAt(), 'location' => $session->getLocation(),
                'url' => $backend ? $this->urls->generate('competition_team', ['id' => $session->getTeam()->getId()]) : null];
        }
        foreach ($this->em->getRepository(Tournament::class)->createQueryBuilder('t')
            ->where('t.startsAt < :until AND t.endsAt > :from AND t.status IN (:statuses)')
            ->setParameter('from', $from)->setParameter('until', $until)
            ->setParameter('statuses', [TournamentStatus::OPEN->value, TournamentStatus::RUNNING->value, TournamentStatus::COMPLETED->value])->getQuery()->getResult() as $tournament) {
            if (!$this->access->viewsTournament($tournament)) { continue; }
            $events[] = ['key' => 'tournament-' . $tournament->getId(), 'kind' => 'Turnier',
                'title' => $tournament->getName(), 'start' => $tournament->getStartsAt(), 'end' => $tournament->getEndsAt(),
                'location' => $tournament->getLocation(), 'url' => $backend ? $this->urls->generate('competition_tournament', ['id' => $tournament->getId()]) : null];
        }
        foreach ($this->em->getRepository(Schedule::class)->createQueryBuilder('s')->join('s.courses', 'c')->addSelect('c')
            ->where('s.startDate >= :from AND s.startDate < :until')->setParameter('from', $from->format('Y-m-d'))
            ->setParameter('until', $until->format('Y-m-d'))->getQuery()->getResult() as $schedule) {
            if (!$this->security->isGranted('COURSE_MANAGE', $schedule->getCourses())
                || in_array($schedule->getCourses()->getStatus()->value, ['draft', 'cancelled'], true)) { continue; }
            $start = new \DateTimeImmutable($schedule->getStartDate()->format('Y-m-d') . ' ' . ($schedule->getStartTime()?->format('H:i') ?? '00:00'));
            $events[] = ['key' => 'course-' . $schedule->getId(), 'kind' => 'Tauchausbildung',
                'title' => $schedule->getCourses()->getTitle() . ': ' . $schedule->getTitle(),
                'start' => $start, 'end' => $schedule->getDuration() ? $start->modify('+' . $schedule->getDuration() . ' minutes') : null,
                'location' => $schedule->getLocation(), 'url' => $backend ? $this->urls->generate('admin_portal_training_course', ['id' => $schedule->getCourses()->getId()]) : null];
        }
        usort($events, static fn (array $a, array $b): int => ($a['start'] <=> $b['start']) ?: strcmp($a['key'], $b['key']));
        return $events;
    }
}
