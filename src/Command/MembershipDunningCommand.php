<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\MembershipCharge;
use App\Entity\MembershipMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:membership:queue-dunning', description: 'Bereitet überfällige Beitragsmahnungen in der Mitglieds-Outbox vor.')]
final class MembershipDunningCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em) { parent::__construct(); }

    protected function configure(): void
    {
        $this->addOption('minimum-days', null, InputOption::VALUE_REQUIRED, 'Tage nach Fälligkeit', '14')
            ->addOption('repeat-days', null, InputOption::VALUE_REQUIRED, 'Mindestabstand zwischen Mahnungen', '30');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $minimumDays = filter_var($input->getOption('minimum-days'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        $repeatDays = filter_var($input->getOption('repeat-days'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
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
            if ($charge->getLastReminderAt()?->format('Y-m-d') >= $today->modify('-'.$repeatDays.' days')->format('Y-m-d')) {
                continue;
            }
            $member = $charge->getMember();
            $email = $member->getEmail() ?: $member->getUser()?->getEmail();
            if (!is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                continue;
            }
            $key = sprintf('membership-dunning:%d:%d', $charge->getId(), $charge->getReminderCount() + 1);
            if ($this->em->getRepository(MembershipMessage::class)->findOneBy(['messageKey' => $key]) !== null) {
                continue;
            }
            $this->em->persist(new MembershipMessage(
                $key,
                $email,
                'Offener Mitgliedsbeitrag: '.$charge->getDescription(),
                sprintf(
                    "Rechnung %s\nOffener Betrag: %.2f EUR\nFällig seit: %s\n\nBitte den offenen Betrag begleichen oder die Vereinsverwaltung kontaktieren.",
                    $charge->getInvoiceNumber() ?? 'noch nicht vergeben',
                    $charge->getOutstandingCents() / 100,
                    $charge->getDueAt()->format('d.m.Y'),
                ),
            ));
            $charge->markReminder();
            ++$queued;
        }
        $this->em->flush();
        $output->writeln(sprintf('%d Beitragsmahnungen vorbereitet.', $queued));
        return Command::SUCCESS;
    }
}
