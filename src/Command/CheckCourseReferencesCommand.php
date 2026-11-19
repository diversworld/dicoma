<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:courses:check-references', description: 'Prüft Kursreferenzen auf Ausbilder, die nicht mehr vorhanden sind, ohne Daten zu ändern.')]
final class CheckCourseReferencesCommand extends Command
{
    public function __construct(private readonly Connection $connection)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT c.id, c.title, c.lead_instructor_id
             FROM courses c
             LEFT JOIN member m ON m.id = c.lead_instructor_id
             WHERE c.lead_instructor_id IS NOT NULL AND m.id IS NULL
             ORDER BY c.id'
        );
        foreach ($rows as $row) {
            $output->writeln(sprintf(
                '<error>Kurs #%d "%s": Ausbilder-ID %d existiert nicht.</error>',
                (int) $row['id'],
                (string) $row['title'],
                (int) $row['lead_instructor_id'],
            ));
        }
        $output->writeln(sprintf('Verwaiste Ausbilderreferenzen: %d. Keine Daten geändert.', count($rows)));
        return $rows === [] ? Command::SUCCESS : Command::FAILURE;
    }
}
