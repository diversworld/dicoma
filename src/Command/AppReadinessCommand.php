<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:readiness', description: 'Prüft die technische Betriebsbereitschaft ohne Datenbankänderungen')]
final class AppReadinessCommand extends Command
{
    public function __construct(private readonly Connection $connection)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $ok = true;
        foreach (['pdo', 'pdo_mysql', 'intl', 'mbstring', 'openssl'] as $extension) {
            $available = extension_loaded($extension);
            $output->writeln(sprintf('%s PHP-Erweiterung %s', $available ? '[OK]' : '[FEHLER]', $extension));
            $ok = $ok && $available;
        }

        if (PHP_VERSION_ID < 80400) {
            $output->writeln('[FEHLER] PHP 8.4 oder neuer erforderlich');
            $ok = false;
        } else {
            $output->writeln('[OK] PHP ' . PHP_VERSION);
        }

        try {
            $this->connection->connect();
            $this->connection->executeQuery('SELECT 1')->fetchOne();
            $output->writeln('[OK] Datenbankverbindung');
        } catch (\Throwable $exception) {
            // Keine Zugangsdaten, SQL oder Serverfehlermeldungen ausgeben.
            $output->writeln('[FEHLER] Datenbankverbindung nicht möglich; DATABASE_URL und DB-Dienst prüfen');
            $ok = false;
        }

        $output->writeln($ok ? '[OK] Grundlegende Betriebsbereitschaft' : '[FEHLER] Mindestens eine Voraussetzung fehlt');
        return $ok ? Command::SUCCESS : Command::FAILURE;
    }
}
