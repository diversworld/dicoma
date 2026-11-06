<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\PublicWebsite;
use App\Service\SingleClubReassignment;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:single-club:reassign-tournament', description: 'Prüft eine einzeln freigegebene Turnierzuordnung für den Einzelvereinsbetrieb.')]
final class PrepareSingleClubCommand extends Command
{
    public function __construct(
        private readonly PublicWebsite $website,
        private readonly SingleClubReassignment $reassignment,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('source-club', null, InputOption::VALUE_REQUIRED, 'ID des bisherigen Vereins')
            ->addOption('tournament', null, InputOption::VALUE_REQUIRED, 'ID des ausdrücklich geprüften Turniers')
            ->addOption('apply', null, InputOption::VALUE_NONE, 'Zuordnung tatsächlich ändern')
            ->addOption('confirm-maintenance', null, InputOption::VALUE_NONE, 'Wartungsfenster und geprüftes Backup bestätigen');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $source = filter_var($input->getOption('source-club'), FILTER_VALIDATE_INT);
        $tournament = filter_var($input->getOption('tournament'), FILTER_VALIDATE_INT);
        $target = $this->website->club()?->getId();
        if ($source === false || $source === null || $tournament === false || $tournament === null || $target === null) {
            $output->writeln('<error>PUBLIC_SITE_CLUB_ID, --source-club und --tournament sind erforderlich.</error>');
            return Command::INVALID;
        }
        $apply = (bool) $input->getOption('apply');
        if ($apply && !$input->getOption('confirm-maintenance')) {
            $output->writeln('<error>--apply erfordert ein Wartungsfenster, ein geprüftes Backup und --confirm-maintenance.</error>');
            return Command::INVALID;
        }
        try {
            $blockers = $this->reassignment->run($target, $source, $tournament, $apply);
        } catch (\DomainException $exception) {
            $output->writeln('<error>'.$exception->getMessage().'</error>');
            return Command::INVALID;
        }
        if ($blockers !== []) {
            foreach ($blockers as $blocker) {
                $output->writeln('<error>'.$blocker.'</error>');
            }
            $output->writeln('<error>Keine Änderung vorgenommen; weitere Vereinsdaten müssen zuerst geprüft werden.</error>');
            return Command::FAILURE;
        }
        if ($apply) {
            $this->logger->notice('Turnier für Einzelvereinsbetrieb zugeordnet.', [
                'tournament_id' => $tournament, 'source_club_id' => $source, 'target_club_id' => $target,
            ]);
            $output->writeln('Turnierzuordnung geändert; andere Vereinsdaten wurden nicht gelöscht.');
        } else {
            $output->writeln('Prüfung erfolgreich; keine Daten geändert. --apply nur nach Sicherung und Freigabe verwenden.');
        }
        return Command::SUCCESS;
    }
}
