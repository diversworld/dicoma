<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\MemberCsvImport;
use App\Service\SingleClubMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:membership:import-members', description: 'Importiert Mitglieder aus CSV mit Dry-Run, Dublettenprüfung und atomarem Rollback.')]
final class ImportMembersCommand extends Command
{
    public function __construct(
        private readonly MemberCsvImport $import,
        private readonly SingleClubMode $club,
        private readonly EntityManagerInterface $em,
    ) { parent::__construct(); }

    protected function configure(): void
    {
        $this->addArgument('file', InputArgument::REQUIRED, 'CSV-Datei')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Nur validieren und zählen.')
            ->addOption('confirm', null, InputOption::VALUE_NONE, 'Vollständigen Import ausführen.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dry = (bool) $input->getOption('dry-run');
        $confirm = (bool) $input->getOption('confirm');
        if ($dry === $confirm) {
            $output->writeln('<error>Genau eine Option --dry-run oder --confirm ist erforderlich.</error>');
            return Command::INVALID;
        }
        try {
            $result = $this->import->run($this->club->club(), (string) $input->getArgument('file'), $confirm);
        } catch (\DomainException $exception) {
            $output->writeln('<error>'.$exception->getMessage().'</error>');
            return Command::FAILURE;
        }
        foreach ($result['errors'] as $error) { $output->writeln('<error>'.$error.'</error>'); }
        if ($result['duplicates'] !== []) {
            $output->writeln('<comment>Dublette übersprungen: Zeilen '.implode(', ', $result['duplicates']).'.</comment>');
        }
        $output->writeln(sprintf('%s: %d Zeilen, %d neue Mitglieder.', $confirm ? 'Import' : 'Dry-Run', $result['rows'], $result['created']));
        if ($result['errors'] !== []) { $this->em->clear(); return Command::FAILURE; }
        return Command::SUCCESS;
    }
}
