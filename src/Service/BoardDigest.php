<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\BoardMeeting;
use App\Entity\BoardResolution;
use App\Entity\BoardTask;
use App\Entity\Club;
use App\Entity\ClubEvent;
use App\Entity\Member;
use App\Entity\MembershipMessage;
use App\Entity\User;
use App\Enum\MembershipStatus;
use Doctrine\ORM\EntityManagerInterface;

final class BoardDigest
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    /** @return array{queued:int,recipients:int,subject:string} */
    public function queue(Club $club, int $days, bool $apply): array
    {
        if ($days < 1 || $days > 90) { throw new \DomainException('Digest-Zeitraum muss zwischen 1 und 90 Tagen liegen.'); }
        $today = new \DateTimeImmutable('today');
        $until = $today->modify('+'.$days.' days')->setTime(23, 59, 59);
        $events = $this->em->getRepository(ClubEvent::class)->createQueryBuilder('event')
            ->where('event.club = :club AND event.cancelled = false AND event.startsAt BETWEEN :from AND :until')
            ->setParameter('club', $club)->setParameter('from', $today)->setParameter('until', $until)
            ->orderBy('event.startsAt', 'ASC')->getQuery()->getResult();
        $meetings = $this->em->getRepository(BoardMeeting::class)->createQueryBuilder('meeting')
            ->where('meeting.club = :club AND meeting.startsAt BETWEEN :from AND :until')
            ->setParameter('club', $club)->setParameter('from', $today)->setParameter('until', $until)
            ->orderBy('meeting.startsAt', 'ASC')->getQuery()->getResult();
        $tasks = $this->em->getRepository(BoardTask::class)->createQueryBuilder('task')
            ->where('task.club = :club AND task.status NOT IN (:done)')
            ->andWhere('task.dueAt IS NOT NULL AND task.dueAt <= :until')
            ->setParameter('club', $club)->setParameter('done', [BoardTask::STATUS_DONE, BoardTask::STATUS_CANCELLED])
            ->setParameter('until', $until)->orderBy('task.dueAt', 'ASC')->getQuery()->getResult();
        $resolutions = $this->em->getRepository(BoardResolution::class)->createQueryBuilder('resolution')
            ->join('resolution.meeting', 'meeting')
            ->where('meeting.club = :club AND resolution.status NOT IN (:done)')
            ->setParameter('club', $club)->setParameter('done', [BoardResolution::STATUS_COMPLETED, BoardResolution::STATUS_CANCELLED])
            ->orderBy('resolution.dueAt', 'ASC')->getQuery()->getResult();
        $missingMinutes = $this->em->getRepository(BoardMeeting::class)->createQueryBuilder('meeting')
            ->where('meeting.club = :club AND meeting.startsAt BETWEEN :recent AND :now')
            ->andWhere('meeting.minutes IS NULL OR TRIM(meeting.minutes) = :empty')
            ->setParameter('club', $club)->setParameter('recent', $today->modify('-14 days'))
            ->setParameter('now', $today->setTime(23, 59, 59))->setParameter('empty', '')
            ->orderBy('meeting.startsAt', 'ASC')->getQuery()->getResult();
        $members = $this->em->getRepository(Member::class)->createQueryBuilder('m')
            ->join('m.user', 'account')->addSelect('account')
            ->where('m.club = :club AND m.archivedAt IS NULL')
            ->andWhere('m.membershipStatus != :former')
            ->setParameter('club', $club)->setParameter('former', MembershipStatus::FORMER->value)
            ->getQuery()->getResult();
        $recipients = array_values(array_filter($members, static fn (Member $member): bool =>
            $member->getUser() !== null
            && in_array(User::ROLE_BOARD, $member->getUser()->getStoredRoles(), true)
            && filter_var($member->getEmail(), FILTER_VALIDATE_EMAIL) !== false
        ));
        $subject = sprintf('Vorstands-Digest %s bis %s', $today->format('d.m.Y'), $until->format('d.m.Y'));
        $body = $this->body($events, $meetings, $missingMinutes, $tasks, $resolutions, $days);
        $queued = 0;
        foreach ($recipients as $member) {
            $key = sprintf('board-digest:%d:%s:%s', $club->getId(), $today->format('Ymd'), $member->getId());
            if ($this->em->getRepository(MembershipMessage::class)->findOneBy(['messageKey' => $key]) !== null) { continue; }
            if ($apply) {
                $this->em->persist(new MembershipMessage($key, (string) $member->getEmail(), $subject, $body));
            }
            ++$queued;
        }
        return ['queued' => $queued, 'recipients' => count($recipients), 'subject' => $subject];
    }

    private function body(array $events, array $meetings, array $missingMinutes, array $tasks, array $resolutions, int $days): string
    {
        $lines = [sprintf('Vorstandsüberblick für die nächsten %d Tage', $days), ''];
        $lines[] = 'Kommende Sitzungen und Termine';
        foreach (array_merge($meetings, $events) as $entry) {
            $lines[] = sprintf('- %s: %s', $entry->getStartsAt()->format('d.m.Y H:i'), $entry->getTitle());
        }
        if ($meetings === [] && $events === []) { $lines[] = '- Keine Termine.'; }
        $lines[] = '';
        $lines[] = 'Ausstehende Protokolle';
        foreach ($missingMinutes as $meeting) {
            $lines[] = sprintf('- %s: %s', $meeting->getStartsAt()->format('d.m.Y'), $meeting->getTitle());
        }
        if ($missingMinutes === []) { $lines[] = '- Keine offenen Protokolle.'; }
        $lines[] = '';
        $lines[] = 'Offene Aufgaben';
        foreach ($tasks as $task) {
            $lines[] = sprintf('- %s | %s | %s', $task->getDueAt()->format('d.m.Y'), $task->getTitle(), $task->getAssignee()?->getUser() ?? 'nicht zugewiesen');
        }
        if ($tasks === []) { $lines[] = '- Keine fristgebundenen Aufgaben.'; }
        $lines[] = '';
        $lines[] = 'Offene Beschlüsse';
        foreach ($resolutions as $resolution) {
            $lines[] = sprintf('- %s | %s', $resolution->getDueAt()?->format('d.m.Y') ?? 'ohne Frist', $resolution->getTitle());
        }
        if ($resolutions === []) { $lines[] = '- Keine offenen Beschlüsse.'; }
        return implode("\n", $lines);
    }
}
