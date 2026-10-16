<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:inspect-doctrine-constraints',
    description: 'Prüft die Doctrine-Unique-Constraints.'
)]
final class InspectDoctrineConstraintsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $entities = [
            \App\Entity\CourseTrainingUnit::class,
            \App\Entity\QualificationTrainingUnit::class,
            \App\Entity\Schedule::class,
            \App\Entity\ScheduleTemplateEntry::class,
            \App\Entity\TrainingUnitResult::class,
            \App\Entity\TrainingUnitType::class,
        ];

        foreach ($entities as $entityClass) {
            $metadata = $this->entityManager
                ->getClassMetadata($entityClass);

            $output->writeln('');
            $output->writeln(
                '<info>' . $entityClass . '</info>'
            );

            $output->writeln(
                'Tabelle: ' . $metadata->getTableName()
            );

            $constraints =
                $metadata->table['uniqueConstraints'] ?? [];

            if ($constraints === []) {
                $output->writeln(
                    '<comment>Keine Unique-Constraints im Mapping.</comment>'
                );

                continue;
            }

            foreach ($constraints as $name => $definition) {
                $columns = $definition['columns'] ?? [];

                $output->writeln(
                    sprintf(
                        '%s: %s',
                        $name,
                        implode(', ', $columns)
                    )
                );
            }
        }

		$output->writeln('Unique-Constraints:');

		print_r(
			$metadata->table['uniqueConstraints'] ?? []
		);

		$output->writeln('Indizes:');

		print_r(
			$metadata->table['indexes'] ?? []
		);
		
        return Command::SUCCESS;
    }
}
