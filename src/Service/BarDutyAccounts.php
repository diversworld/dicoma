<?php

declare(strict_types=1);
namespace App\Service;

use App\Entity\{BarDutyAssignment, BarDutyPolicy, BarDutyRequirement, Club, Member};
use Doctrine\ORM\EntityManagerInterface;

final class BarDutyAccounts
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function account(Member $member, int $year): array
    {
        BarDutyWorkflow::year($year);
        $policy = $this->em->getRepository(BarDutyPolicy::class)->findOneBy(['club' => $member->getClub(), 'year' => $year]);
        $exception = $this->em->getRepository(BarDutyRequirement::class)->findOneBy(['member' => $member, 'year' => $year]);
        $required = $exception?->getRequiredMinutes() ?? $policy?->getRequiredMinutes();
        $rows = $this->em->getRepository(BarDutyAssignment::class)->createQueryBuilder('a')->join('a.shift', 's')->addSelect('s')
            ->where('a.member = :member AND s.club = :club AND a.status = :active AND s.status != :cancelled AND s.startsAt >= :start AND s.startsAt < :end')
            ->setParameter('club', $member->getClub())
            ->setParameter('member', $member)->setParameter('active', 'active')->setParameter('cancelled', 'cancelled')
            ->setParameter('start', new \DateTimeImmutable($year.'-01-01'))->setParameter('end', new \DateTimeImmutable(($year + 1).'-01-01'))
            ->orderBy('s.startsAt', 'ASC')->getQuery()->getResult();
        $earned = 0; $planned = 0; $absent = 0;
        foreach ($rows as $assignment) {
            if ($assignment->getAttendance() === 'present') { $earned += $assignment->getCreditedMinutes(); }
            elseif ($assignment->getAttendance() === 'absent') { ++$absent; }
            else { $planned += intdiv($assignment->getShift()->getEndsAt()->getTimestamp() - $assignment->getShift()->getStartsAt()->getTimestamp(), 60); }
        }
        return ['member' => $member, 'year' => $year, 'required' => $required, 'earned' => $earned, 'planned' => $planned,
            'remaining' => $required === null ? null : max(0, $required - $earned), 'balance' => $required === null ? null : $earned - $required,
            'absent' => $absent, 'exception' => $exception, 'assignments' => $rows];
    }

    public function report(Club $club, int $year): array
    {
        $rows = [];
        foreach ($this->em->getRepository(Member::class)->findBy(['club' => $club], ['id' => 'ASC']) as $member) {
            $row = $this->account($member, $year);
            if ($member->isActiveMember() || $row['assignments'] !== [] || $row['exception'] !== null) { $rows[] = $row; }
        }
        return $rows;
    }
}
