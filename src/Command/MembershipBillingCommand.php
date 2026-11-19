<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\MembershipBillingService;
use App\Service\SingleClubMode;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:membership:billing-run', description: 'Prüft oder führt den idempotenten Jahresbeitragslauf aus.')]
final class MembershipBillingCommand extends Command
{
    public function __construct(
        private readonly MembershipBillingService $billing,
        private readonly SingleClubMode $singleClub,
    ) { parent::__construct(); }

    protected function configure(): void
    {
        $this->setHelp(
            "Der Beitragslauf benötigt immer eine ausdrückliche Fälligkeit.\n\n".
            "Beispiel Vorschau:\n".
            "  php bin/console app:membership:billing-run --year=2026 --due=2026-01-31 --dry-run\n\n".
            "Beispiel Ausführung nach Freigabe:\n".
            "  php bin/console app:membership:billing-run --year=2026 --due=2026-01-31 --confirm"
        );
        $this->addOption('year', null, InputOption::VALUE_REQUIRED, 'Beitragsjahr')
            ->addOption('due', null, InputOption::VALUE_REQUIRED, 'Fälligkeit YYYY-MM-DD')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Nur Vorschau, keine Forderungen speichern.')
            ->addOption('confirm', null, InputOption::VALUE_NONE, 'Bestätigt den Beitragslauf.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $year = filter_var($input->getOption('year') ?? date('Y'), FILTER_VALIDATE_INT);
        $dueValue = (string) $input->getOption('due');
        $due = $dueValue === '' ? null : \DateTimeImmutable::createFromFormat('!Y-m-d', $dueValue);
        if (!$year || $input->getOption('due') === null || $due === null || $due->format('Y-m-d') !== $dueValue) {
            $output->writeln('<error>Option --due fehlt oder ist ungültig. Erwartet wird YYYY-MM-DD, z. B. --due=2026-01-31.</error>');
            return Command::FAILURE;
        }
        if ((bool) $input->getOption('dry-run') === (bool) $input->getOption('confirm')) {
            $output->writeln('<error>Genau eine Option --dry-run oder --confirm angeben.</error>');
            return Command::FAILURE;
        }
        $club = $this->singleClub->club();
        if ($input->getOption('dry-run')) {
            $summary = $this->billing->preview($club, $year, $due);
            $output->writeln(sprintf('Vorschau: %d Mitglieder, %d Forderungen, %.2f EUR, %d übersprungen.', $summary['members'], $summary['charges'], $summary['amountCents'] / 100, $summary['skipped']));
            return Command::SUCCESS;
        }
        $run = $this->billing->run($club, $year, $due, 'cli:app:membership:billing-run');
        $output->writeln(sprintf('Beitragslauf abgeschlossen: %d Vorgänge, %.2f EUR.', $run->getIssuedCount(), $run->getTotalCents() / 100));
        return Command::SUCCESS;
    }
}
