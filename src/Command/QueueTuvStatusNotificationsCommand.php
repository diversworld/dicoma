<?php
declare(strict_types=1);

namespace App\Command;

use App\Entity\MemberTankCheckStatusHistory;
use App\Entity\TuvNotification;
use App\Service\TuvMessageRenderer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:tuv:queue-status-notifications', description: 'Stellt TÜV-Statusänderungen idempotent in den Mail-Postausgang')]
final class QueueTuvStatusNotificationsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TuvMessageRenderer $renderer,
        private readonly \App\Service\TuvNotificationSender $sender,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('hours', null, InputOption::VALUE_REQUIRED, 'Nur Statusänderungen der letzten N Stunden berücksichtigen', '24');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Nur Kandidaten zählen, keine Nachrichten speichern');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $hours = filter_var($input->getOption('hours'), FILTER_VALIDATE_INT);
        if ($hours === false || $hours < 1 || $hours > 8760) {
            $output->writeln('<error>--hours muss zwischen 1 und 8760 liegen.</error>');
            return Command::INVALID;
        }

        $since = new \DateTimeImmutable('-'.$hours.' hours');
        $history = $this->em->getRepository(MemberTankCheckStatusHistory::class)
            ->createQueryBuilder('h')
            ->where('h.changedAt >= :since')
            ->setParameter('since', $since)
            ->orderBy('h.id', 'ASC')
            ->getQuery()->toIterable();

        $queued = 0;
        $skipped = 0;
        foreach ($history as $entry) {
            $booking = $entry->getBooking();
            // Vereinsflaschen haben ggf. kein Mitglied. Ohne valide Adresse kein Versand.
            $recipient = trim((string) $booking->getMember()?->getEmail());
            if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                ++$skipped;
                continue;
            }

            $kind = match ($entry->getNewStatus()) {
                'returned' => 'pickup',
                'cancelled', 'canceled' => 'cancellation',
                default => 'status',
            };
            if (!$this->renderer->isEnabled($kind)) {
                ++$skipped;
                continue;
            }
            $key = 'status-history-'.$entry->getId();
            if ($this->em->getRepository(TuvNotification::class)->findOneBy(['dedupeKey' => $key])) {
                ++$skipped;
                continue;
            }
            if (!$input->getOption('dry-run')) {
                ['subject' => $subject, 'body' => $body] = $this->renderer->render($kind, $booking);
                $notice = new TuvNotification($booking, $key, $recipient, $subject, $body);
                $this->em->persist($notice);
                // Einzelne Flushes verhindern wachsenden UnitOfWork-Speicherbedarf.
                $this->em->flush();
                if ($this->sender->isImmediate()) { $this->sender->sendOne((int) $notice->getId()); }
            }
            ++$queued;
        }

        $output->writeln(sprintf('%s: %d neu, %d übersprungen.', $input->getOption('dry-run') ? 'Vorschau' : 'Postausgang', $queued, $skipped));
        return Command::SUCCESS;
    }
}
