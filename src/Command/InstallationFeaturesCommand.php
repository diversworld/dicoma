<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\InstallationCatalog;
use App\Service\SingleClubMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:installation:features', description: 'Prüft oder installiert neue DICOMA-Kernfunktionen.')]
final class InstallationFeaturesCommand extends Command
{
    public function __construct(
        private readonly SingleClubMode $clubs,
        private readonly InstallationCatalog $catalog,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('apply', null, InputOption::VALUE_NONE, 'Fehlende Kernfunktionen tatsächlich installieren');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $club = $this->clubs->club();
        $components = $this->catalog->components($club);
        $io->table(['Komponente', 'Status'], array_map(
            static fn (array $component): array => [$component['label'], $component['installed'] ? 'installiert' : 'fehlt'],
            $components,
        ));
        $missing = count(array_filter($components, static fn (array $component): bool => !$component['installed']));
        if (!$input->getOption('apply')) {
            $io->note($missing === 0 ? 'Alle Kernfunktionen sind aktuell.' : sprintf('%d fehlende Kernfunktion(en); mit --apply installieren.', $missing));
            return $missing === 0 ? Command::SUCCESS : Command::FAILURE;
        }
        $installed = $this->catalog->installMissing($club);
        $this->em->flush();
        $io->success(sprintf('%d Kernfunktion(en) installiert.', count($installed)));
        return Command::SUCCESS;
    }
}
