<?php

declare(strict_types=1);
namespace App\Command;

use App\Entity\{BarDutyAssignment, BarDutyNotification, Club};
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:bar-duty:remind', description: 'Erzeugt einmalige interne Erinnerungen für kommende Thekendienste. Versendet keine E-Mails.')]
final class BarDutyRemindersCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em) { parent::__construct(); }
    protected function configure(): void { $this->addOption('hours', null, InputOption::VALUE_REQUIRED, 'Vorlauf in Stunden (1–168)', '48'); }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $hours = filter_var($input->getOption('hours'), FILTER_VALIDATE_INT);
        if ($hours === false || $hours < 1 || $hours > 168) { $output->writeln('<error>hours muss zwischen 1 und 168 liegen.</error>'); return Command::INVALID; }
        $count = 0; $now = new \DateTimeImmutable(); $until = $now->modify('+'.$hours.' hours');
        foreach ($this->em->getRepository(Club::class)->findAll() as $club) {
            $this->em->wrapInTransaction(function () use ($club, $now, $until, &$count): void {
                $this->em->refresh($club, LockMode::PESSIMISTIC_WRITE);
                $assignments = $this->em->getRepository(BarDutyAssignment::class)->createQueryBuilder('a')->join('a.shift', 's')->addSelect('s')
                    ->where('s.club = :club AND s.status = :open AND a.status = :active AND s.startsAt > :now AND s.startsAt <= :until')
                    ->setParameter('club', $club)->setParameter('open', 'open')->setParameter('active', 'active')
                    ->setParameter('now', $now)->setParameter('until', $until)->getQuery()->setLockMode(LockMode::PESSIMISTIC_READ)->setHint(Query::HINT_REFRESH, true)->getResult();
                foreach ($assignments as $assignment) {
                    $key = 'reminder-'.$assignment->getId();
                    $exists = $this->em->getRepository(BarDutyNotification::class)->createQueryBuilder('n')->where('n.eventKey = :key')->setParameter('key', $key)
                        ->getQuery()->setLockMode(LockMode::PESSIMISTIC_READ)->getOneOrNullResult();
                    if ($exists !== null) { continue; }
                    $shift = $assignment->getShift();
                    $this->em->persist(new BarDutyNotification($assignment->getMember(), $shift, $key, 'Erinnerung an deinen Thekendienst',
                        $shift->getTitle().' am '.$shift->getStartsAt()->format('d.m.Y H:i').' in '.$shift->getLocation().'. Bitte die aktuelle Einteilung im Portal prüfen.'));
                    ++$count;
                }
            });
        }
        $output->writeln($count.' Erinnerungen erstellt.'); return Command::SUCCESS;
    }
}
