<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\MembershipCharge;
use App\Service\SingleClubMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:membership:export-finance', description: 'Exportiert offene und bezahlte Mitgliedsforderungen als CSV.')]
final class ExportMembershipFinanceCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SingleClubMode $singleClub,
    ) { parent::__construct(); }

    protected function configure(): void
    {
        $this->addOption('year', null, InputOption::VALUE_REQUIRED, 'Kalenderjahr', (string) date('Y'))
            ->addOption('output', null, InputOption::VALUE_REQUIRED, 'Zieldatei')
            ->addOption('confirm', null, InputOption::VALUE_NONE, 'Bestätigt personenbezogenen Export');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = (string) $input->getOption('output');
        if (!$input->getOption('confirm') || $path === '' || str_contains($path, "\0")) {
            $output->writeln('<error>--output und --confirm sind erforderlich; Datei enthält personenbezogene Finanzdaten.</error>');
            return Command::FAILURE;
        }
        $year = (int) $input->getOption('year');
        $charges = $this->em->getRepository(MembershipCharge::class)->createQueryBuilder('charge')
            ->join('charge.member', 'm')->addSelect('m')
            ->where('m.club = :club')
            ->andWhere('charge.createdAt >= :start AND charge.createdAt < :end')
            ->setParameter('club', $this->singleClub->club())
            ->setParameter('start', new \DateTimeImmutable($year.'-01-01'))
            ->setParameter('end', new \DateTimeImmutable(($year + 1).'-01-01'))
            ->orderBy('charge.id')->getQuery()->getResult();
        $handle = fopen($path, 'x');
        if ($handle === false) {
            throw new \RuntimeException('Exportdatei existiert bereits oder kann nicht angelegt werden.');
        }
        try {
            fputcsv($handle, ['Rechnung', 'Mitglied', 'Beschreibung', 'Fällig', 'Betrag EUR', 'Bezahlt EUR', 'Offen EUR', 'Status'], ';', '"', '\\');
            foreach ($charges as $charge) {
                fputcsv($handle, [
                    $charge->getInvoiceNumber() ?? '',
                    $charge->getMember()->getFullName(),
                    $charge->getDescription(),
                    $charge->getDueAt()->format('Y-m-d'),
                    number_format($charge->getAmountCents() / 100, 2, ',', ''),
                    number_format($charge->getPaidCents() / 100, 2, ',', ''),
                    number_format($charge->getOutstandingCents() / 100, 2, ',', ''),
                    $charge->getStatus(),
                ], ';', '"', '\\');
            }
        } finally {
            fclose($handle);
            chmod($path, 0600);
        }
        $output->writeln(sprintf('%d Finanzzeilen exportiert. Datei vertraulich behandeln.', count($charges)));
        return Command::SUCCESS;
    }
}
