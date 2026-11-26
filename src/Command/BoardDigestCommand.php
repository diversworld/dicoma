<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\BoardDigest;
use App\Service\SingleClubMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:board:digest', description: 'Erstellt den Vorstands-Digest für Kalender, Protokolle und Aufgaben.')]
final class BoardDigestCommand extends Command
{
    public function __construct(
        private readonly BoardDigest $digest,
        private readonly SingleClubMode $club,
        private readonly EntityManagerInterface $em,
    ) { parent::__construct(); }

    protected function configure(): void
    {
        $this->addOption('days', null, InputOption::VALUE_REQUIRED, 'Vorschauzeitraum in Tagen', '14')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Nur Anzahl und Betreff ausgeben.')
            ->addOption('confirm', null, InputOption::VALUE_NONE, 'Digest-Nachrichten in die Outbox einreihen.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dry = (bool) $input->getOption('dry-run');
        $confirm = (bool) $input->getOption('confirm');
        if ($dry === $confirm) {
            $output->writeln('<error>Genau eine Option --dry-run oder --confirm ist erforderlich.</error>');
            return Command::INVALID;
        }
        $days = filter_var($input->getOption('days'), FILTER_VALIDATE_INT);
        if ($days === false) { return Command::INVALID; }
        $result = $this->digest->queue($this->club->club(), $days, $confirm);
        if ($confirm) { $this->em->flush(); }
        $output->writeln(($confirm ? 'Eingereiht' : 'Vorschau').sprintf(': %d Nachrichten für %d Vorstandsmitglieder. %s', $result['queued'], $result['recipients'], $result['subject']));
        return Command::SUCCESS;
    }
}
