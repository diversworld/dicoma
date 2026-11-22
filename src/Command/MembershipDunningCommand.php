<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\MembershipCharge;
use App\Entity\MembershipMessage;
use App\Entity\MembershipDunningNotice;
use App\Service\MembershipService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:membership:queue-dunning', description: 'Bereitet überfällige Beitragsmahnungen in der Mitglieds-Outbox vor.')]
final class MembershipDunningCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MembershipService $memberships,
    ) { parent::__construct(); }

    protected function configure(): void
    {
        $this->addOption('minimum-days', null, InputOption::VALUE_OPTIONAL, 'Optionaler Legacy-Wert für Tage nach Fälligkeit')
            ->addOption('repeat-days', null, InputOption::VALUE_OPTIONAL, 'Optionaler Legacy-Mindestabstand; sonst Vereinsrichtlinie');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $legacyMinimum = $input->getOption('minimum-days');
        $legacyRepeat = $input->getOption('repeat-days');
        $minimumDays = $legacyMinimum === null ? 0 : filter_var($legacyMinimum, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        $repeatDays = $legacyRepeat === null ? null : filter_var($legacyRepeat, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($minimumDays === false || $repeatDays === false) { return Command::INVALID; }
        $today = new \DateTimeImmutable('today');
        $charges = $this->em->getRepository(MembershipCharge::class)->createQueryBuilder('charge')
            ->join('charge.member', 'm')->addSelect('m')
            ->where('charge.amountCents > charge.paidCents')
            ->andWhere('charge.dueAt <= :minimum')
            ->setParameter('minimum', $today->modify('-'.$minimumDays.' days'))
            ->getQuery()->getResult();
        $queued = 0;
        foreach ($charges as $charge) {
            if ($charge->getReminderCount() >= count(MembershipDunningNotice::stages())) {
                continue;
            }
            $member = $charge->getMember();
            $policy = $member->getClub() === null ? null : $this->memberships->policy($member->getClub());
            $intervals = $policy?->getDunningIntervals() ?? ['reminder' => 14, 'first' => 28, 'second' => 42, 'final' => 56];
            $email = $member->getEmail() ?: $member->getUser()?->getEmail();
            if (!is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                continue;
            }
            $stageIndex = min($charge->getReminderCount(), count(MembershipDunningNotice::stages()) - 1);
            $stage = MembershipDunningNotice::stages()[$stageIndex];
            $eligibleAt = $charge->getDueAt()->modify('+'.($legacyMinimum !== null ? (int) $minimumDays : (int) $intervals[$stage]).' days');
            if ($today < $eligibleAt) {
                continue;
            }
            $repeat = $legacyRepeat !== null ? (int) $repeatDays : max(1, (int) (($intervals[MembershipDunningNotice::stages()[$stageIndex + 1] ?? 'final'] ?? $intervals[$stage]) - $intervals[$stage]));
            if ($charge->getLastReminderAt()?->format('Y-m-d') >= $today->modify('-'.$repeat.' days')->format('Y-m-d')) {
                continue;
            }
            $key = sprintf('membership-dunning:%d:%d', $charge->getId(), $charge->getReminderCount() + 1);
            if ($this->em->getRepository(MembershipMessage::class)->findOneBy(['messageKey' => $key]) !== null) {
                continue;
            }
            $defaultSubject = match ($stage) {
                MembershipDunningNotice::STAGE_REMINDER => 'Zahlungserinnerung: '.$charge->getDescription(),
                MembershipDunningNotice::STAGE_FIRST => 'Erste Mahnung: '.$charge->getDescription(),
                MembershipDunningNotice::STAGE_SECOND => 'Zweite Mahnung: '.$charge->getDescription(),
                MembershipDunningNotice::STAGE_FINAL => 'Letzte Mahnung: '.$charge->getDescription(),
            };
            $templates = $policy?->getDunningTemplates() ?? [];
            $body = strtr((string) ($templates[$stage] ?? $defaultSubject), [
                '{{ description }}' => $charge->getDescription(),
                '{{ amount }}' => number_format($charge->getOutstandingCents() / 100, 2, ',', '.'),
                '{{ dueDate }}' => $charge->getDueAt()->format('d.m.Y'),
                '{{ invoice }}' => $charge->getInvoiceNumber() ?? 'noch nicht vergeben',
                '{{ stage }}' => (string) ($stageIndex + 1),
            ]);
            $subject = trim(explode("\n", $body, 2)[0]);
            $this->em->persist(new MembershipMessage(
                $key,
                $email,
                $subject,
                $body,
            ));
            if ($this->em->getRepository(MembershipDunningNotice::class)->findOneBy(['charge' => $charge, 'stage' => $stage]) === null) {
                $this->em->persist(new MembershipDunningNotice($charge, $stage, $subject, $body, $today->modify('+'.$repeat.' days')));
            }
            $charge->markReminder();
            ++$queued;
        }
        $this->em->flush();
        $output->writeln(sprintf('%d Beitragsmahnungen vorbereitet.', $queued));
        return Command::SUCCESS;
    }
}
