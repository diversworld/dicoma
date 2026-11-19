<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\ClubEventRegistration;
use App\Entity\MembershipMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:events:queue-reminders', description: 'Bereitet Erinnerungen für bestätigte Veranstaltungen in den nächsten 72 Stunden vor.')]
final class QueueClubEventRemindersCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $from = new \DateTimeImmutable();
        $until = $from->modify('+72 hours');
        $registrations = $this->em->getRepository(ClubEventRegistration::class)->createQueryBuilder('registration')
            ->join('registration.event', 'event')
            ->where('registration.status = :registered')
            ->andWhere('event.cancelled = false')
            ->andWhere('event.published = true')
            ->andWhere('event.startsAt >= :from AND event.startsAt <= :until')
            ->setParameter('registered', 'registered')
            ->setParameter('from', $from)
            ->setParameter('until', $until)
            ->getQuery()->getResult();
        $queued = 0;
        foreach ($registrations as $registration) {
            $email = trim((string) $registration->getPerson()->getEmail());
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                continue;
            }
            $key = sprintf('event-reminder:%d:%d:%s', $registration->getEvent()->getId(), $registration->getId(), $registration->getEvent()->getStartsAt()->format('YmdHi'));
            if ($this->em->getRepository(MembershipMessage::class)->findOneBy(['messageKey' => $key]) !== null) {
                continue;
            }
            $event = $registration->getEvent();
            $this->em->persist(new MembershipMessage(
                $key,
                $email,
                'DICOMA-Erinnerung: '.$event->getTitle(),
                sprintf("Deine Veranstaltung beginnt am %s.\nOrt: %s\n\nBitte prüfe die aktuellen Angaben in DICOMA.", $event->getStartsAt()->format('d.m.Y H:i'), $event->getLocation()),
            ));
            ++$queued;
        }
        $this->em->flush();
        $output->writeln(sprintf('%d Veranstaltungs-Erinnerungen vorbereitet.', $queued));
        return Command::SUCCESS;
    }
}
