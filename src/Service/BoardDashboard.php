<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\BoardMeeting;
use App\Entity\BoardResolution;
use App\Entity\BoardTask;
use App\Entity\Club;
use App\Entity\ClubDocument;
use App\Entity\Member;
use App\Entity\MembershipApplication;
use App\Enum\MembershipStatus;
use Doctrine\ORM\EntityManagerInterface;

final class BoardDashboard
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    /** @return array<string, mixed> */
    public function forClub(Club $club): array
    {
        $today = new \DateTimeImmutable('today');
        $memberQb = $this->em->getRepository(Member::class)->createQueryBuilder('m')
            ->where('m.club = :club')->setParameter('club', $club);
        $active = (int) (clone $memberQb)->select('COUNT(m.id)')
            ->andWhere('m.archivedAt IS NULL AND m.membershipStatus = :status')
            ->setParameter('status', MembershipStatus::ACTIVE->value)
            ->getQuery()->getSingleScalarResult();
        $passive = (int) (clone $memberQb)->select('COUNT(m.id)')
            ->andWhere('m.archivedAt IS NULL AND m.membershipStatus = :status')
            ->setParameter('status', MembershipStatus::PASSIVE->value)
            ->getQuery()->getSingleScalarResult();
        $former = (int) (clone $memberQb)->select('COUNT(m.id)')
            ->andWhere('m.membershipStatus = :status')->setParameter('status', MembershipStatus::FORMER->value)
            ->getQuery()->getSingleScalarResult();

        $tasks = $this->em->getRepository(BoardTask::class)->createQueryBuilder('task')
            ->where('task.club = :club AND task.status NOT IN (:closed)')
            ->setParameter('club', $club)->setParameter('closed', [BoardTask::STATUS_DONE, BoardTask::STATUS_CANCELLED])
            ->orderBy('CASE WHEN task.dueAt IS NULL THEN 1 ELSE 0 END', 'ASC')
            ->addOrderBy('task.dueAt', 'ASC')->setMaxResults(10)->getQuery()->getResult();
        $meetingQb = $this->em->getRepository(BoardMeeting::class)->createQueryBuilder('meeting')
            ->where('meeting.club = :club')->andWhere('meeting.startsAt >= :now')->andWhere('meeting.status = :planned')
            ->setParameter('club', $club)->setParameter('now', $today)->setParameter('planned', BoardMeeting::STATUS_PLANNED)
            ->orderBy('meeting.startsAt', 'ASC');
        $meetingCount = (int) (clone $meetingQb)->select('COUNT(meeting.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();
        $openResolutions = (int) $this->em->getRepository(BoardResolution::class)->createQueryBuilder('resolution')
            ->select('COUNT(resolution.id)')->join('resolution.meeting', 'meeting')
            ->where('meeting.club = :club')->andWhere('resolution.status NOT IN (:closed)')
            ->setParameter('club', $club)->setParameter('closed', [BoardResolution::STATUS_COMPLETED, BoardResolution::STATUS_CANCELLED])
            ->getQuery()->getSingleScalarResult();
        $pendingDocuments = (int) $this->em->getRepository(ClubDocument::class)->count([
            'club' => $club,
            'status' => ClubDocument::STATUS_PENDING,
        ]);

        $applications = $this->em->getRepository(MembershipApplication::class);
        $birthdays = $this->em->getRepository(Member::class)->createQueryBuilder('m')
            ->addSelect('person')->join('m.person', 'person')
            ->where('m.club = :club AND m.archivedAt IS NULL')
            ->andWhere('person.birthday IS NOT NULL')
            ->setParameter('club', $club)->orderBy('person.birthday', 'ASC')->setMaxResults(100)->getQuery()->getResult();
        $birthdayMonthDay = static fn (\DateTimeInterface $date): string => $date->format('m-d');
        $tomorrow = $today->modify('+14 days');
        $birthdays = array_values(array_filter($birthdays, static function (Member $member) use ($today, $tomorrow, $birthdayMonthDay): bool {
            $birthday = $member->getPerson()->getBirthday();
            if ($birthday === null) {
                return false;
            }
            $birthdayThisYear = $birthdayMonthDay(new \DateTimeImmutable($today->format('Y').'-'.$birthday->format('m-d')));
            return $birthdayThisYear >= $today->format('m-d') && $birthdayThisYear <= $tomorrow->format('m-d');
        }));

        return [
            'membersTotal' => $active + $passive,
            'membersActive' => $active,
            'membersPassive' => $passive,
            'memberDepartures' => $former,
            'newMemberApplications' => $applications->count(['club' => $club, 'status' => 'pending']),
            'openTasks' => count($tasks),
            'tasks' => $tasks,
            'upcomingMeetings' => $meetingQb->setMaxResults(5)->getQuery()->getResult(),
            'upcomingMeetingCount' => $meetingCount,
            'openResolutions' => $openResolutions,
            'pendingDocuments' => $pendingDocuments,
            'birthdays' => array_slice($birthdays, 0, 5),
        ];
    }
}
