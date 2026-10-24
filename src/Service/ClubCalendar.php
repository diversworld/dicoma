<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Schedule;
use App\Entity\TeamTraining;
use App\Entity\Tournament;
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
    ) {}

    public function events(\DateTimeImmutable $from, \DateTimeImmutable $until): array
    {
        $events = [];
        foreach ($this->em->getRepository(TeamTraining::class)->createQueryBuilder('s')->addSelect('t')
            ->join('s.team', 't')->where('s.startsAt < :until AND s.endsAt > :from AND s.cancelled = false')
            ->setParameter('from', $from)->setParameter('until', $until)->getQuery()->getResult() as $session) {
            if (!$this->access->managesTeam($session->getTeam())) { continue; }
            $events[] = ['key' => 'training-' . $session->getId(), 'kind' => 'Mannschaftstraining',
                'title' => $session->getTeam()->getName() . ': ' . $session->getTitle(), 'start' => $session->getStartsAt(),
                'end' => $session->getEndsAt(), 'location' => $session->getLocation(),
                'url' => $this->urls->generate('competition_team', ['id' => $session->getTeam()->getId()])];
        }
        foreach ($this->em->getRepository(Tournament::class)->createQueryBuilder('t')
            ->where('t.startsAt < :until AND t.endsAt > :from AND t.status IN (:statuses)')
            ->setParameter('from', $from)->setParameter('until', $until)
            ->setParameter('statuses', [TournamentStatus::OPEN->value, TournamentStatus::RUNNING->value, TournamentStatus::COMPLETED->value])->getQuery()->getResult() as $tournament) {
            if (!$this->access->viewsTournament($tournament)) { continue; }
            $events[] = ['key' => 'tournament-' . $tournament->getId(), 'kind' => 'Turnier',
                'title' => $tournament->getName(), 'start' => $tournament->getStartsAt(), 'end' => $tournament->getEndsAt(),
                'location' => $tournament->getLocation(), 'url' => $this->urls->generate('competition_tournament', ['id' => $tournament->getId()])];
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
                'location' => $schedule->getLocation(), 'url' => $this->urls->generate('admin_portal_training_course', ['id' => $schedule->getCourses()->getId()])];
        }
        usort($events, static fn (array $a, array $b): int => ($a['start'] <=> $b['start']) ?: strcmp($a['key'], $b['key']));
        return $events;
    }
}
