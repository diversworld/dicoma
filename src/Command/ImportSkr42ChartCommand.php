<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\FinanceChartService;
use App\Service\SingleClubMode;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Doctrine\ORM\EntityManagerInterface;

#[AsCommand(name: 'app:finance:import-skr42', description: 'Importiert idempotent den SKR42-2026-EÜR-Kontenplan mit internen Vereins-Unterkonten.')]
final class ImportSkr42ChartCommand extends Command
{
    public function __construct(
        private readonly FinanceChartService $chart,
        private readonly SingleClubMode $singleClub,
        private readonly EntityManagerInterface $em,
    ) { parent::__construct(); }
    protected function configure(): void
    {
        $this->addOption('confirm', null, InputOption::VALUE_NONE, 'Import ausdrücklich ausführen');
    }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$input->getOption('confirm')) {
            $output->writeln('<error>Der Import benötigt --confirm.</error>');
            return Command::FAILURE;
        }
        $summary = $this->chart->installSkr42($this->singleClub->club(), 'cli:app:finance:import-skr42');
        $this->em->flush();
        $output->writeln(sprintf('SKR42 2026: %d Konten geprüft, %d angelegt, %d ergänzt.', $summary['total'], $summary['created'], $summary['updated']));
        return Command::SUCCESS;
    }
}
