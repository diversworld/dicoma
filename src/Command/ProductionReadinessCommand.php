<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Club;
use App\Service\ProductionReadiness;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:release-check', description: 'Prüft Bank-, Steuer-, Malware-, Datenschutz- und WCAG-Produktivfreigaben.')]
final class ProductionReadinessCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ProductionReadiness $readiness,
    ) { parent::__construct(); }

    protected function configure(): void
    {
        $this->addOption('fail-on-blocked', null, InputOption::VALUE_NONE, 'Nicht freigegebene Vereine als Fehler zurückgeben');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $blocked = false;
        foreach ($this->em->getRepository(Club::class)->findAll() as $club) {
            $issues = $this->readiness->blockers($club);
            if ($issues === []) {
                $output->writeln('<info>'.$club->getName().': produktiv freigegeben</info>');
                continue;
            }
            $blocked = true;
            $output->writeln('<comment>'.$club->getName().':</comment>');
            foreach ($issues as $issue) {
                $output->writeln(' - '.$issue);
            }
        }
        return $blocked && $input->getOption('fail-on-blocked') ? Command::FAILURE : Command::SUCCESS;
    }
}
