<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Club;
use App\Entity\DivisionContribution;
use App\Entity\Member;
use App\Entity\MemberSport;
use App\Entity\MembershipBillingRun;
use App\Entity\MembershipCharge;
use App\Entity\MembershipPayment;
use App\Enum\MembershipStatus;
use Doctrine\ORM\EntityManagerInterface;

final class MembershipBillingService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MembershipFinanceService $finance,
    ) {}

    /** @return array{members: int, charges: int, amountCents: int, skipped: int} */
    public function preview(Club $club, int $year, \DateTimeImmutable $due): array
    {
        $summary = ['members' => 0, 'charges' => 0, 'amountCents' => 0, 'skipped' => 0];
        foreach ($this->eligibleMembers($club, $year) as $member) {
            ++$summary['members'];
            try {
                $amount = $this->annualAmount($member, $year);
                ++$summary['charges'];
                $summary['amountCents'] += $amount;
            } catch (\DomainException) {
                ++$summary['skipped'];
            }
        }
        return $summary;
    }

    public function run(Club $club, int $year, \DateTimeImmutable $due, string $actor): MembershipBillingRun
    {
        $existing = $this->em->getRepository(MembershipBillingRun::class)->findOneBy(['club' => $club, 'year' => $year]);
        if ($existing !== null) {
            throw new \DomainException('Der Beitragslauf für dieses Jahr wurde bereits verarbeitet.');
        }
        return $this->em->wrapInTransaction(function () use ($club, $year, $due, $actor): MembershipBillingRun {
            $run = new MembershipBillingRun($club, $year, $due, $actor);
            $this->em->persist($run);
            $count = 0; $total = 0;
            foreach ($this->eligibleMembers($club, $year) as $member) {
                try {
                    $charge = $this->finance->annual($member, $year, $due, $actor);
                    $surcharge = $this->divisionAmount($member, $year);
                    $charge->issueInvoice($this->invoiceNumber($club, $year, $charge));
                    if ($surcharge > 0) {
                        $extra = $this->finance->special(
                            $member,
                            'Spartenzuschlag '.$year,
                            $surcharge,
                            $due,
                            $actor,
                        );
                        $extra->issueInvoice($this->invoiceNumber($club, $year, $extra));
                    }
                    $count += 1 + ($surcharge > 0 ? 1 : 0);
                    $total += $charge->getAmountCents() + $surcharge;
                } catch (\DomainException) {
                    continue;
                }
            }
            $run->complete($count, $total);
            $this->em->flush();
            return $run;
        });
    }

    public function annualAmount(Member $member, int $year): int
    {
        $class = $member->getContributionClass();
        if ($class === null || !$class->isActive()) {
            throw new \DomainException('Keine aktive Beitragsklasse.');
        }
        return $class->getAnnualCents() - $member->getContributionDiscountCents() + $this->divisionAmount($member, $year);
    }

    public function divisionAmount(Member $member, int $year): int
    {
        $total = 0;
        foreach ($this->em->getRepository(MemberSport::class)->findBy(['member' => $member]) as $membership) {
            if ($membership->getStatus()->value !== 'active') {
                continue;
            }
            $rule = $this->em->getRepository(DivisionContribution::class)->findOneBy([
                'division' => $membership->getSport(), 'year' => $year, 'active' => true,
            ]);
            $total += $rule?->getAnnualCents() ?? 0;
        }
        return $total;
    }

    /** @return list<Member> */
    private function eligibleMembers(Club $club, int $year): array
    {
        return $this->em->getRepository(Member::class)->createQueryBuilder('m')
            ->where('m.club = :club')
            ->andWhere('m.archivedAt IS NULL')
            ->andWhere('m.membershipStatus != :former')
            ->andWhere('m.familyPayer IS NULL')
            ->setParameter('club', $club)
            ->setParameter('former', MembershipStatus::FORMER->value)
            ->orderBy('m.id', 'ASC')
            ->getQuery()->getResult();
    }

    private function invoiceNumber(Club $club, int $year, MembershipCharge $charge): string
    {
        return sprintf('B-%d-%04d-%06d', $year, $club->getId(), $charge->getId());
    }
}
