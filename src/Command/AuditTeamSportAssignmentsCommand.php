<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\TeamMember;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:audit-team-sport-assignments', description: 'Prueft Gruppen- und Sportzuordnungen ohne Daten zu aendern.')]
final class AuditTeamSportAssignmentsCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $checked = 0;
        $missingSport = 0;
        $wrongClub = 0;
        $withoutSport = 0;
        $missingReferences = 0;

        foreach ($this->entityManager->getRepository(TeamMember::class)->findAll() as $assignment) {
            ++$checked;
            $team = $assignment->getTeam();
            $member = $assignment->getMember();
            if ($team === null || $member === null) {
                ++$missingReferences;
                continue;
            }

            $sport = $team->getSport();
            if ($sport === null) {
                ++$withoutSport;
                continue;
            }

            if ($sport->getClub() !== $member->getClub()) {
                ++$wrongClub;
                continue;
            }

            $hasSport = false;
            foreach ($member->getSports() as $membership) {
                if ($membership->getSport() === $sport) {
                    $hasSport = true;
                    break;
                }
            }
            if (!$hasSport) {
                ++$missingSport;
            }
        }

        $output->writeln(sprintf('Gepruefte Gruppenzuordnungen: %d', $checked));
        $output->writeln(sprintf('Fehlende Sportartenzuordnungen: %d', $missingSport));
        $output->writeln(sprintf('Abweichende Vereinszuordnungen: %d', $wrongClub));
        $output->writeln(sprintf('Gruppen ohne Sportart: %d', $withoutSport));
        $output->writeln(sprintf('Fehlende Gruppen-/Mitgliedsreferenzen: %d', $missingReferences));
        $output->writeln('Keine Daten geaendert. Keine personenbezogenen Details ausgegeben.');

        return ($missingSport + $wrongClub + $missingReferences) > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
