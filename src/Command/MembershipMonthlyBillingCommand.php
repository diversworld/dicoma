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

#[AsCommand(name: 'app:membership:monthly-billing', description: 'Erstellt idempotent Monatsbeiträge für periodische Beitragsklassen.')]
final class MembershipMonthlyBillingCommand extends Command
{
    public function __construct(private readonly MembershipBillingService $billing, private readonly SingleClubMode $singleClub) { parent::__construct(); }
    protected function configure(): void
    {
        $this->addOption('period', null, InputOption::VALUE_REQUIRED, 'Abrechnungsmonat YYYY-MM', date('Y-m'))
            ->addOption('due', null, InputOption::VALUE_REQUIRED, 'Fälligkeit YYYY-MM-DD')
            ->addOption('confirm', null, InputOption::VALUE_NONE, 'Monatslauf ausführen');
    }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $period = (string) $input->getOption('period');
        $dueValue = (string) $input->getOption('due');
        $due = \DateTimeImmutable::createFromFormat('!Y-m-d', $dueValue);
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', $period) || $due === false || $due->format('Y-m-d') !== $dueValue || !$input->getOption('confirm')) {
            $output->writeln('<error>Gültige --period, --due und ausdrückliche --confirm erforderlich.</error>');
            return Command::FAILURE;
        }
        $summary = $this->billing->runMonthly($this->singleClub->club(), $period, $due, 'cli:app:membership:monthly-billing');
        $output->writeln(sprintf('Monatslauf %s: %d Mitglieder, %d Forderungen, %.2f EUR, %d übersprungen.', $period, $summary['members'], $summary['charges'], $summary['amountCents'] / 100, $summary['skipped']));
        return Command::SUCCESS;
    }
}
