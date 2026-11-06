<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\PublicWebsite;
use App\Service\SingleClubReassignment;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:single-club:check', description: 'Prüft schreibgeschützt die Datenvoraussetzungen für den Einzelvereinsbetrieb.')]
final class CheckSingleClubCommand extends Command
{
    public function __construct(
        private readonly PublicWebsite $website,
        private readonly SingleClubReassignment $reassignment,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $club = $this->website->club();
        if ($club === null || !$club->isActive() || $club->getId() === null) {
            $output->writeln('<error>PUBLIC_SITE_CLUB_ID muss einen aktiven Verein bezeichnen.</error>');
            return Command::FAILURE;
        }
        $blockers = $this->reassignment->blockers($club->getId());
        foreach ($blockers as $blocker) {
            $output->writeln('<error>'.$blocker.'</error>');
        }
        if ($blockers !== []) {
            $output->writeln('<error>Einzelvereinsbetrieb noch nicht aktivieren.</error>');
            return Command::FAILURE;
        }
        $output->writeln('Keine abweichenden oder nicht zugeordneten geprüften Vereinsbezüge gefunden.');
        return Command::SUCCESS;
    }
}
