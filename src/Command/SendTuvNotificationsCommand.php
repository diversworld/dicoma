<?php
declare(strict_types=1);
namespace App\Command;
use App\Entity\TuvNotification;
use App\Service\TuvNotificationSender;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:tuv:send-notifications', description: 'Versendet ausstehende TÜV-Nachrichten')]
final class SendTuvNotificationsCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly TuvNotificationSender $sender) { parent::__construct(); }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $ids = $this->em->getRepository(TuvNotification::class)->createQueryBuilder('n')
            ->select('n.id')->where('n.state = :state')->setParameter('state', 'pending')
            ->orderBy('n.id', 'ASC')->setMaxResults(30)->getQuery()->getScalarResult();
        $counts = ['sent' => 0, 'failed' => 0, 'locked' => 0, 'skipped' => 0];
        foreach ($ids as $row) {
            $result = $this->sender->sendOne((int) $row['id']);
            ++$counts[$result];
            if ($result === 'locked') { break; }
        }
        $output->writeln(sprintf('%d versendet, %d fehlgeschlagen, %d gesperrt, %d übersprungen.', ...array_values($counts)));
        return $counts['failed'] || $counts['locked'] ? Command::FAILURE : Command::SUCCESS;
    }
}
